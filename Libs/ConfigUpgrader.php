<?php

/*
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

namespace com\kbcmdba\aql\Libs ;

/**
 * Convert v1 (flat <param>) AQL config XML to v2 (grouped element) format.
 *
 * Pure transformation — no file IO. The script upgradeConfig.php handles
 * file reading/writing/backup; this class does the actual conversion.
 *
 * Usage:
 *     $newXml = ConfigUpgrader::upgrade( $oldXmlString ) ;
 *     // throws \Exception if input is invalid or already v2
 */
class ConfigUpgrader
{
    /**
     * Convert a v1 config XML string to v2 format.
     *
     * @param string $xmlString Source XML (v1 flat <param> format)
     * @return string The new v2 XML
     * @throws \Exception When XML is invalid or already version 2+
     */
    public static function upgrade( string $xmlString ) : string
    {
        $xml = @simplexml_load_string( $xmlString ) ;
        if ( false === $xml ) {
            throw new \Exception( "Invalid XML: cannot parse" ) ;
        }

        $configVersion = (int) ( (string) ( $xml['version'] ?? 0 ) ) ;
        if ( $configVersion >= 2 ) {
            throw new \Exception( "Config is already version $configVersion. Nothing to do." ) ;
        }

        // Parse legacy flat params
        $params = [] ;
        foreach ( $xml as $v ) {
            if ( $v->getName() === 'dbtype' ) {
                continue ;
            }
            $key = (string) $v['name'] ;
            $params[ $key ] = (string) $v ;
        }

        // Parse dbtype elements
        $dbtypes = [] ;
        foreach ( $xml->dbtype as $dt ) {
            $entry = [ 'name' => (string) $dt['name'] ] ;
            if ( isset( $dt['enabled'] ) ) {
                $entry['enabled'] = (string) $dt['enabled'] ;
            }
            if ( isset( $dt['username'] ) ) {
                $entry['username'] = (string) $dt['username'] ;
            }
            if ( isset( $dt['password'] ) ) {
                $entry['password'] = (string) $dt['password'] ;
            }
            $dbtypes[] = $entry ;
        }

        return self::buildV2Xml( $params, $dbtypes ) ;
    }

    /**
     * Check whether an XML string is already v2 format (or higher).
     *
     * @param string $xmlString
     * @return bool
     */
    public static function isAlreadyUpgraded( string $xmlString ) : bool
    {
        $xml = @simplexml_load_string( $xmlString ) ;
        if ( false === $xml ) {
            return false ;
        }
        return (int) ( (string) ( $xml['version'] ?? 0 ) ) >= 2 ;
    }

    /**
     * Escape a value for use as an XML attribute.
     */
    private static function xmlAttr( string $value ) : string
    {
        return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) ;
    }

    /**
     * Build an attribute string from a key-value array.
     * Skips null and empty values.
     */
    private static function buildAttrs( array $attrs ) : string
    {
        $parts = [] ;
        foreach ( $attrs as $name => $value ) {
            if ( $value !== null && $value !== '' ) {
                $parts[] = $name . '="' . self::xmlAttr( (string) $value ) . '"' ;
            }
        }
        return implode( ' ', $parts ) ;
    }

    /**
     * Emit a self-closing element. Multi-line if attribute count makes
     * the line longer than 100 characters.
     */
    private static function emitElement( string $indent, string $name, array $attrs ) : string
    {
        $nonEmpty = array_filter( $attrs, function( $v ) { return $v !== null && $v !== '' ; } ) ;
        if ( empty( $nonEmpty ) ) {
            return '' ;
        }

        $attrStr = self::buildAttrs( $nonEmpty ) ;
        $line = "$indent<$name $attrStr />" ;

        if ( strlen( $line ) <= 100 ) {
            return $line . "\n" ;
        }

        // Multi-line: first attr on same line, rest indented
        $keys = array_keys( $nonEmpty ) ;
        $result = "$indent<$name" ;
        $attrIndent = $indent . str_repeat( ' ', strlen( $name ) + 2 ) ;
        foreach ( $keys as $i => $key ) {
            $attr = $key . '="' . self::xmlAttr( (string) $nonEmpty[$key] ) . '"' ;
            if ( $i === 0 ) {
                $result .= " $attr" ;
            } else {
                $result .= "\n$attrIndent$attr" ;
            }
        }
        $result .= " />\n" ;
        return $result ;
    }

    /**
     * Assemble the v2 output XML from parsed v1 params and dbtypes.
     */
    private static function buildV2Xml( array $params, array $dbtypes ) : string
    {
        $out = '' ;
        $out .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ;
        $out .= '<!DOCTYPE config SYSTEM "aql_config.dtd">' . "\n" ;
        $out .= '<config version="2">' . "\n" ;

        // configdb
        $out .= self::emitElement( '    ', 'configdb', [
            'type' => 'mysql',
            'host' => $params['dbHost'] ?? null,
            'port' => $params['dbPort'] ?? null,
            'name' => $params['dbName'] ?? null,
            'instanceName' => ! empty( $params['dbInstanceName'] ) ? $params['dbInstanceName'] : null,
        ] ) ;
        $out .= "\n" ;

        // users — admin from dbUser/dbPass, monitor from mysql dbtype creds (or fall back)
        $out .= self::emitElement( '    ', 'user', [
            'type' => 'admin',
            'name' => $params['dbUser'] ?? null,
            'password' => $params['dbPass'] ?? null,
        ] ) ;
        $monUser = $params['dbUser'] ?? '' ;
        $monPass = $params['dbPass'] ?? '' ;
        foreach ( $dbtypes as $dt ) {
            if ( strtolower( $dt['name'] ?? '' ) === 'mysql' && ! empty( $dt['username'] ) ) {
                $monUser = $dt['username'] ;
                $monPass = $dt['password'] ?? $monPass ;
                break ;
            }
        }
        $out .= self::emitElement( '    ', 'user', [
            'type' => 'monitor',
            'name' => $monUser,
            'password' => $monPass,
        ] ) ;
        $out .= "\n" ;

        // monitoring
        $out .= self::emitElement( '    ', 'monitoring', [
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
        $out .= self::emitElement( '    ', 'authentication', [
            'adminPassword' => $params['adminPassword'] ?? null,
        ] ) ;
        $out .= "\n" ;

        // ldap
        $out .= self::emitElement( '    ', 'ldap', [
            'enabled' => $params['doLDAPAuthentication'] ?? 'false',
            'host' => $params['ldapHost'] ?? null,
            'domainName' => $params['ldapDomainName'] ?? null,
            'userGroup' => $params['ldapUserGroup'] ?? null,
            'userDomain' => $params['ldapUserDomain'] ?? null,
            'verifyCert' => $params['ldapVerifyCert'] ?? null,
            'debugConnection' => $params['ldapDebugConnection'] ?? null,
        ] ) ;
        $out .= "\n" ;

        // jira
        $out .= self::emitElement( '    ', 'jira', [
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
            $attrs = 'name="' . self::xmlAttr( $envName ) . '"' ;
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
            $out .= self::emitElement( '    ', 'redis', [
                'user' => ! empty( $params['redisUser'] ) ? $params['redisUser'] : null,
                'password' => ! empty( $params['redisPassword'] ) ? $params['redisPassword'] : null,
                'connectTimeout' => $params['redisConnectTimeout'] ?? null,
                'database' => $params['redisDatabase'] ?? null,
            ] ) ;
            $out .= "\n" ;
        }

        // features
        $out .= self::emitElement( '    ', 'features', [
            'enableMaintenanceWindows' => $params['enableMaintenanceWindows'] ?? 'false',
            'dbaSessionTimeout' => $params['dbaSessionTimeout'] ?? '86400',
            'enableSpeechAlerts' => $params['enableSpeechAlerts'] ?? 'true',
        ] ) ;
        $out .= "\n" ;

        // testing (only if any test params are present)
        $hasTestParams = ! empty( $params['testDbUser'] ) || ! empty( $params['testDbPass'] ) || ! empty( $params['testDbName'] ) ;
        if ( $hasTestParams ) {
            $out .= self::emitElement( '    ', 'testing', [
                'dbUser' => $params['testDbUser'] ?? null,
                'dbPass' => $params['testDbPass'] ?? null,
                'dbName' => $params['testDbName'] ?? null,
            ] ) ;
            $out .= "\n" ;
        }

        // dbtypes (preserved as-is)
        foreach ( $dbtypes as $dt ) {
            $attrs = [ 'name' => $dt['name'], 'enabled' => $dt['enabled'] ?? 'false' ] ;
            if ( ! empty( $dt['username'] ) ) {
                $attrs['username'] = $dt['username'] ;
            }
            if ( ! empty( $dt['password'] ) ) {
                $attrs['password'] = $dt['password'] ;
            }
            $out .= self::emitElement( '    ', 'dbtype', $attrs ) ;
        }

        $out .= '</config>' . "\n" ;
        return $out ;
    }
}
