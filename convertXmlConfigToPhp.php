<?php

/*
 * convertXmlConfigToPhp.php - Convert aql_config.xml to aql_config.php
 *
 * PHP config files cannot be served as static content by any web server
 * (Nginx, Apache, etc.) because they are always processed by the PHP
 * interpreter. This eliminates the risk of credential exposure via
 * direct URL access to the config file.
 *
 * Usage:
 *   php convertXmlConfigToPhp.php                  # Preview (dry run)
 *   php convertXmlConfigToPhp.php --write          # Write aql_config.php
 *                                                  # (XML file is NOT deleted)
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

require __DIR__ . '/vendor/autoload.php' ;

use com\kbcmdba\aql\Libs\Config ;

$xmlFile = __DIR__ . '/aql_config.xml' ;
$phpFile = __DIR__ . '/aql_config.php' ;
$doWrite = in_array( '--write', $argv ?? [] ) ;

if ( ! file_exists( $xmlFile ) ) {
    fwrite( STDERR, "Error: $xmlFile not found.\n" ) ;
    exit( 1 ) ;
}

$xmlString = file_get_contents( $xmlFile ) ;
if ( false === $xmlString ) {
    fwrite( STDERR, "Error: Could not read $xmlFile.\n" ) ;
    exit( 1 ) ;
}

try {
    $cfgValues = Config::parseConfigXml( $xmlString ) ;
} catch ( \Exception $e ) {
    fwrite( STDERR, "Error parsing XML config: " . $e->getMessage() . "\n" ) ;
    exit( 1 ) ;
}

// Identify which keys are passwords/secrets for masking in the output comment
$secretKeys = [
    'dbPass', 'monitorPassword', 'adminPassword',
    'redisPassword', 'testDbPass',
] ;
// Also catch per-dbtype passwords
foreach ( $cfgValues as $k => $v ) {
    if ( preg_match( '/Password$/', $k ) && ! in_array( $k, $secretKeys, true ) ) {
        $secretKeys[] = $k ;
    }
}

// Group keys for readable output
$groups = [
    'Config Database' => [ 'configDbType', 'dbHost', 'dbPort', 'dbInstanceName', 'dbName', 'dbUser', 'dbPass' ],
    'Monitor Credentials' => [ 'monitorUser', 'monitorPassword' ],
    'Monitoring' => [ 'baseUrl', 'timeZone', 'minRefresh', 'defaultRefresh',
                      'issueTrackerBaseUrl', 'roQueryPart', 'killStatement',
                      'showSlaveStatement', 'globalStatusDb' ],
    'Timeouts' => [ 'connectTimeout', 'readTimeout' ],
    'LDAP' => [ 'doLDAPAuthentication', 'ldapHost', 'ldapDomainName',
                'ldapUserGroup', 'ldapUserDomain', 'ldapVerifyCert',
                'ldapDebugConnection', 'ldapStartTls' ],
    'Authentication' => [ 'adminPassword' ],
    'Jira' => [ 'jiraEnabled', 'jiraProjectId', 'jiraIssueTypeId', 'jiraQueryHashFieldId' ],
    'Redis' => [ 'redisEnabled', 'redisUser', 'redisPassword',
                 'redisConnectTimeout', 'redisDatabase' ],
    'PostgreSQL' => [ 'postgresqlEnabled' ],
    'Features' => [ 'enableMaintenanceWindows', 'dbaSessionTimeout', 'enableSpeechAlerts' ],
    'Environments' => [ 'environments', 'defaultEnvironment' ],
    'Testing' => [ 'testDbUser', 'testDbPass', 'testDbName' ],
] ;

// Collect any DB-type keys not in the groups above
$groupedKeys = [] ;
foreach ( $groups as $keys ) {
    $groupedKeys = array_merge( $groupedKeys, $keys ) ;
}
$dbTypeKeys = [] ;
foreach ( $cfgValues as $k => $v ) {
    if ( ! in_array( $k, $groupedKeys, true ) ) {
        $dbTypeKeys[] = $k ;
    }
}
if ( ! empty( $dbTypeKeys ) ) {
    sort( $dbTypeKeys ) ;
    $groups['DB Types'] = $dbTypeKeys ;
}

// Build the PHP config file content
$lines = [] ;
$lines[] = '<?php' ;
$lines[] = '' ;
$lines[] = '/*' ;
$lines[] = ' * AQL Configuration' ;
$lines[] = ' *' ;
$lines[] = ' * Generated from aql_config.xml by convertXmlConfigToPhp.php' ;
$lines[] = ' * on ' . date( 'Y-m-d H:i:s T' ) ;
$lines[] = ' *' ;
$lines[] = ' * This file is safe from direct URL access — PHP files are always' ;
$lines[] = ' * processed by the interpreter, never served as static content.' ;
$lines[] = ' */' ;
$lines[] = '' ;
$lines[] = 'return [' ;

foreach ( $groups as $groupName => $keys ) {
    $lines[] = '' ;
    $lines[] = '    // ' . str_repeat( '=', 60 ) ;
    $lines[] = '    // ' . $groupName ;
    $lines[] = '    // ' . str_repeat( '=', 60 ) ;

    foreach ( $keys as $key ) {
        if ( ! array_key_exists( $key, $cfgValues ) ) {
            continue ;
        }
        $value = $cfgValues[ $key ] ;
        if ( is_int( $value ) ) {
            $repr = (string) $value ;
        } elseif ( is_null( $value ) ) {
            $repr = 'null' ;
        } elseif ( is_bool( $value ) ) {
            $repr = $value ? 'true' : 'false' ;
        } else {
            $repr = "'" . addcslashes( (string) $value, "'\\" ) . "'" ;
        }
        $lines[] = "    '" . $key . "' => " . $repr . ',' ;
    }
}

$lines[] = '' ;
$lines[] = '] ;' ;
$lines[] = '' ;

$output = implode( "\n", $lines ) ;

if ( ! $doWrite ) {
    echo $output ;
    fwrite( STDERR, "\nDry run complete. Use --write to save as $phpFile\n" ) ;
    exit( 0 ) ;
}

if ( file_exists( $phpFile ) ) {
    $backupFile = $phpFile . '.bk' ;
    if ( ! copy( $phpFile, $backupFile ) ) {
        fwrite( STDERR, "Error: Could not create backup at $backupFile\n" ) ;
        exit( 1 ) ;
    }
    fwrite( STDERR, "Backup saved to: $backupFile\n" ) ;
}

if ( file_put_contents( $phpFile, $output ) === false ) {
    fwrite( STDERR, "Error: Could not write to $phpFile\n" ) ;
    exit( 1 ) ;
}

fwrite( STDERR, "Config written to: $phpFile\n" ) ;
fwrite( STDERR, "\nIMPORTANT: Remove or rename $xmlFile to prevent credential exposure.\n" ) ;
fwrite( STDERR, "           AQL will now use $phpFile if it exists.\n" ) ;
