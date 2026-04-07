<?php

/*
 * upgradeConfig.php - Convert aql_config.xml from legacy flat <param> format
 *                     to new grouped element format.
 *
 * Usage:
 *   php upgradeConfig.php                  # Preview new format (dry run)
 *   php upgradeConfig.php --write          # Write new format to aql_config.xml
 *                                          # (saves backup as aql_config.xml.bk)
 *
 * aql - Active Query Listing
 *
 * Copyright (C) 2018 Kevin Benton - kbcmdba [at] gmail [dot] com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

$configFile = __DIR__ . '/aql_config.xml' ;
$doWrite = in_array( '--write', $argv ?? [] ) ;

if ( ! file_exists( $configFile ) ) {
    fwrite( STDERR, "Error: $configFile not found.\n" ) ;
    exit( 1 ) ;
}

$xml = @simplexml_load_file( $configFile ) ;
if ( ! $xml ) {
    fwrite( STDERR, "Error: Invalid XML in $configFile.\n" ) ;
    exit( 1 ) ;
}

// Check config version
$configVersion = (int) ( (string) $xml['version'] ?? 0 ) ;
if ( $configVersion >= 2 ) {
    fwrite( STDERR, "Config is already version $configVersion. Nothing to do.\n" ) ;
    exit( 0 ) ;
}

// Parse legacy flat params
$params = [] ;
foreach ( $xml as $v ) {
    if ( $v->getName() === 'dbtype' ) continue ;
    $key = (string) $v['name'] ;
    $params[ $key ] = (string) $v ;
}

// Parse dbtype elements
$dbtypes = [] ;
foreach ( $xml->dbtype as $dt ) {
    $entry = [ 'name' => (string) $dt['name'] ] ;
    if ( isset( $dt['enabled'] ) )  $entry['enabled']  = (string) $dt['enabled'] ;
    if ( isset( $dt['username'] ) ) $entry['username'] = (string) $dt['username'] ;
    if ( isset( $dt['password'] ) ) $entry['password'] = (string) $dt['password'] ;
    $dbtypes[] = $entry ;
}

// Helper to escape XML attribute values
function xmlAttr( $value ) {
    return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) ;
}

// Helper to build an attribute string from key-value pairs
function buildAttrs( $attrs ) {
    $parts = [] ;
    foreach ( $attrs as $name => $value ) {
        if ( $value !== null && $value !== '' ) {
            $parts[] = $name . '="' . xmlAttr( $value ) . '"' ;
        }
    }
    return implode( ' ', $parts ) ;
}

// Helper to emit a self-closing element with attributes, multi-line if many
function emitElement( $indent, $name, $attrs ) {
    $nonEmpty = array_filter( $attrs, function( $v ) { return $v !== null ; } ) ;
    if ( empty( $nonEmpty ) ) return '' ;

    $attrStr = buildAttrs( $nonEmpty ) ;
    $line = "$indent<$name $attrStr />" ;

    // If line is short enough, keep on one line
    if ( strlen( $line ) <= 100 ) {
        return $line . "\n" ;
    }

    // Multi-line: first attr on same line, rest indented
    $keys = array_keys( $nonEmpty ) ;
    $result = "$indent<$name" ;
    $attrIndent = $indent . str_repeat( ' ', strlen( $name ) + 2 ) ;
    foreach ( $keys as $i => $key ) {
        $attr = $key . '="' . xmlAttr( $nonEmpty[$key] ) . '"' ;
        if ( $i === 0 ) {
            $result .= " $attr" ;
        } else {
            $result .= "\n$attrIndent$attr" ;
        }
    }
    $result .= " />\n" ;
    return $result ;
}

// Build the new XML output
$out = '' ;
$out .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ;
$out .= '<!DOCTYPE config SYSTEM "aql_config.dtd">' . "\n" ;
$out .= '<config version="2">' . "\n" ;

// configdb
$out .= emitElement( '    ', 'configdb', [
    'type' => 'mysql',
    'host' => $params['dbHost'] ?? null,
    'port' => $params['dbPort'] ?? null,
    'name' => $params['dbName'] ?? null,
    'instanceName' => ! empty( $params['dbInstanceName'] ) ? $params['dbInstanceName'] : null,
] ) ;
$out .= "\n" ;

// users
$out .= emitElement( '    ', 'user', [
    'type' => 'admin',
    'name' => $params['dbUser'] ?? null,
    'password' => $params['dbPass'] ?? null,
] ) ;
// For monitor user, use mysql dbtype credentials if available, else fall back to db credentials
$monUser = $params['dbUser'] ?? '' ;
$monPass = $params['dbPass'] ?? '' ;
foreach ( $dbtypes as $dt ) {
    if ( strtolower( $dt['name'] ) === 'mysql' && ! empty( $dt['username'] ) ) {
        $monUser = $dt['username'] ;
        $monPass = $dt['password'] ?? $monPass ;
        break ;
    }
}
$out .= emitElement( '    ', 'user', [
    'type' => 'monitor',
    'name' => $monUser,
    'password' => $monPass,
] ) ;
$out .= "\n" ;

// monitoring
$out .= emitElement( '    ', 'monitoring', [
    'baseUrl' => $params['baseUrl'] ?? null,
    'timeZone' => $params['timeZone'] ?? null,
    'minRefresh' => $params['minRefresh'] ?? null,
    'defaultRefresh' => $params['defaultRefresh'] ?? null,
    'issueTrackerBaseUrl' => $params['issueTrackerBaseUrl'] ?? null,
    'roQueryPart' => $params['roQueryPart'] ?? null,
    'killStatement' => $params['killStatement'] ?? null,
    'showSlaveStatement' => $params['showSlaveStatement'] ?? null,
    'globalStatusDb' => $params['globalStatusDb'] ?? null,
] ) ;
$out .= "\n" ;

// authentication
$out .= emitElement( '    ', 'authentication', [
    'adminPassword' => $params['adminPassword'] ?? null,
] ) ;
$out .= "\n" ;

// ldap
$ldapEnabled = ( $params['doLDAPAuthentication'] ?? 'false' ) ;
$out .= emitElement( '    ', 'ldap', [
    'enabled' => $ldapEnabled,
    'host' => $params['ldapHost'] ?? null,
    'domainName' => $params['ldapDomainName'] ?? null,
    'userGroup' => $params['ldapUserGroup'] ?? null,
    'userDomain' => $params['ldapUserDomain'] ?? null,
    'verifyCert' => $params['ldapVerifyCert'] ?? null,
    'debugConnection' => $params['ldapDebugConnection'] ?? null,
] ) ;
$out .= "\n" ;

// jira
$out .= emitElement( '    ', 'jira', [
    'enabled' => $params['jiraEnabled'] ?? 'false',
    'projectId' => $params['jiraProjectId'] ?? null,
    'issueTypeId' => $params['jiraIssueTypeId'] ?? null,
    'queryHashFieldId' => $params['jiraQueryHashFieldId'] ?? null,
] ) ;
$out .= "\n" ;

// environment_types
$envList = $params['environments'] ?? 'dev,test,qa,pilot,staging,production' ;
$envNames = array_map( 'trim', explode( ',', $envList ) ) ;
$envNames = array_filter( $envNames, function( $v ) { return $v !== '' ; } ) ;
$defaultEnv = $params['defaultEnvironment'] ?? 'production' ;
$out .= "    <environment_types>\n" ;
foreach ( $envNames as $envName ) {
    $attrs = 'name="' . xmlAttr( $envName ) . '"' ;
    if ( $envName === $defaultEnv ) {
        $attrs .= ' default="true"' ;
    }
    $out .= "        <environment_type $attrs />\n" ;
}
$out .= "    </environment_types>\n" ;
$out .= "\n" ;

// redis (connection tuning)
$hasRedisParams = ! empty( $params['redisConnectTimeout'] ) || ! empty( $params['redisDatabase'] )
               || ! empty( $params['redisUser'] ) || ! empty( $params['redisPassword'] ) ;
if ( $hasRedisParams ) {
    $out .= emitElement( '    ', 'redis', [
        'user' => ! empty( $params['redisUser'] ) ? $params['redisUser'] : null,
        'password' => ! empty( $params['redisPassword'] ) ? $params['redisPassword'] : null,
        'connectTimeout' => $params['redisConnectTimeout'] ?? null,
        'database' => $params['redisDatabase'] ?? null,
    ] ) ;
    $out .= "\n" ;
}

// features
$out .= emitElement( '    ', 'features', [
    'enableMaintenanceWindows' => $params['enableMaintenanceWindows'] ?? 'false',
    'dbaSessionTimeout' => $params['dbaSessionTimeout'] ?? '86400',
    'enableSpeechAlerts' => $params['enableSpeechAlerts'] ?? 'true',
] ) ;
$out .= "\n" ;

// testing
$hasTestParams = ! empty( $params['testDbUser'] ) || ! empty( $params['testDbPass'] ) || ! empty( $params['testDbName'] ) ;
if ( $hasTestParams ) {
    $out .= emitElement( '    ', 'testing', [
        'dbUser' => $params['testDbUser'] ?? null,
        'dbPass' => $params['testDbPass'] ?? null,
        'dbName' => $params['testDbName'] ?? null,
    ] ) ;
    $out .= "\n" ;
}

// dbtypes (preserved as-is)
foreach ( $dbtypes as $dt ) {
    $attrs = [ 'name' => $dt['name'], 'enabled' => $dt['enabled'] ?? 'false' ] ;
    if ( ! empty( $dt['username'] ) ) $attrs['username'] = $dt['username'] ;
    if ( ! empty( $dt['password'] ) ) $attrs['password'] = $dt['password'] ;
    $out .= emitElement( '    ', 'dbtype', $attrs ) ;
}

$out .= '</config>' . "\n" ;

if ( $doWrite ) {
    // Backup existing config
    $backupFile = $configFile . '.bk' ;
    if ( ! copy( $configFile, $backupFile ) ) {
        fwrite( STDERR, "Error: Could not create backup at $backupFile\n" ) ;
        exit( 1 ) ;
    }
    fwrite( STDERR, "Backup saved to: $backupFile\n" ) ;

    if ( file_put_contents( $configFile, $out ) === false ) {
        fwrite( STDERR, "Error: Could not write to $configFile\n" ) ;
        exit( 1 ) ;
    }
    fwrite( STDERR, "Config upgraded: $configFile\n" ) ;

    // Validate
    $dtdFile = __DIR__ . '/aql_config.dtd' ;
    if ( file_exists( $dtdFile ) ) {
        exec( "xmllint --valid --noout " . escapeshellarg( $configFile ) . " 2>&1", $output, $rc ) ;
        if ( $rc === 0 ) {
            fwrite( STDERR, "DTD validation: PASSED\n" ) ;
        } else {
            fwrite( STDERR, "DTD validation: FAILED\n" ) ;
            fwrite( STDERR, implode( "\n", $output ) . "\n" ) ;
        }
    }
} else {
    // Dry run - print to stdout
    echo $out ;
    fwrite( STDERR, "\n--- Dry run. Use --write to apply changes. ---\n" ) ;
}
