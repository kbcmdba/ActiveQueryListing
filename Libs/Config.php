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

use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;

/**
 * Configuration for this tool set
 */
class Config
{
    /**
     * AQL Version - update this when releasing new versions
     */
    const VERSION = 'v2.92' ;

    /**
     * Configuration Class
     *
     * Note: There are no setters for this class. All the configuration comes from
     * aql_config.xml in the application directory (described in config_sample.xml).
     *
     * Usage Examples:
     *
     * Constructor:
     * $config = new Config() ;
     *
     * Getting configuration data:
     * $dbh = mysql_connect( $config->getDbHost() . ':' . $config->getDbPort()
     * , $config->getDbUser()
     * , $config->getDbPass()
     * ) ;
     * $dbh->mysql_select_db( $config->getDbName() ) ;
     */

    /**
     * #@+
     *
     * @var string
     */
    private $baseUrl = null;
    private $dbHost = null;
    private $dbPort = null;
    private $dbUser = null;
    private $dbPass = null;
    private $dbInstanceName = null;
    private $dbName = null;
    private $timeZone = null;
    private $issueTrackerBaseUrl = null;
    private $roQueryPart = null;
    private $killStatement = null;
    private $showSlaveStatement = null;
    private $doLDAPAuthentication = null ;
    private $ldapHost = null;
    private $ldapDomainName = null;
    private $ldapUserGroup = null;
    private $ldapUserDomain = null;
    private $ldapVerifyCert = null;
    private $ldapDebugConnection = null;
    private $globalStatusDb = null;
    private $jiraEnabled = null;
    private $jiraProjectId = null;
    private $jiraIssueTypeId = null;
    private $jiraQueryHashFieldId = null;
    private $testDbUser = null;
    private $testDbPass = null;
    private $testDbName = null;
    private $enableMaintenanceWindows = null;
    private $dbaSessionTimeout = null;
    private $redisEnabled = null;
    private $redisUser = null;
    private $redisPassword = null;
    private $redisConnectTimeout = null;
    private $redisDatabase = null;
    private $postgresqlEnabled = null;
    private $enableSpeechAlerts = null;
    private $configDbType = null;

    /**
     * DB Type properties - stores enabled/username/password for each database type
     * Structure: $dbTypeProperties['DBType']['enabled'] = true|false
     *            $dbTypeProperties['DBType']['username'] = string
     *            $dbTypeProperties['DBType']['password'] = string
     * @var array
     */
    private $dbTypeProperties = [] ;

    /**
     * #@-
     */

    /**
     * #@+
     *
     * @var int
     */
    private $minRefresh = null;
    private $defaultRefresh = null;
    /**
     * #@-
     */

    /**
     * Default values for config parameters
     *
     * @return array
     */
    private static function getDefaults() {
        return [
            'dbInstanceName' => '',
            'minRefresh' => 15,
            'defaultRefresh' => 60,
            'roQueryPart' => '@@global.read_only',
            'killStatement' => 'kill :pid',
            'showSlaveStatement' => 'show slave status',
            'globalStatusDb' => 'performance_schema',
            'ldapVerifyCert' => 'true',
            'ldapDebugConnection' => 'false',
            'jiraEnabled' => 'false',
            'jiraProjectId' => '',
            'jiraIssueTypeId' => '',
            'jiraQueryHashFieldId' => '',
            'testDbUser' => '',
            'testDbPass' => '',
            'testDbName' => '',
            'enableMaintenanceWindows' => 'false',
            'dbaSessionTimeout' => '86400',
            'redisEnabled' => 'false',
            'redisUser' => '',
            'redisPassword' => '',
            'redisConnectTimeout' => 2,
            'redisDatabase' => 0,
            'postgresqlEnabled' => 'false',
            'enableSpeechAlerts' => 'true',
            'mysqlEnabled' => 'true',
            'configDbType' => 'mysql',
        ] ;
    }

    /**
     * Parameter list with required/optional flags
     *
     * @return array
     */
    private static function getParamList() {
        return [
            'dbHost'               => [ 'isRequired' => 1, 'value' => 0 ],
            'dbPass'               => [ 'isRequired' => 1, 'value' => 0 ],
            'dbInstanceName'       => [ 'isRequired' => 0, 'value' => 0 ],
            'dbName'               => [ 'isRequired' => 1, 'value' => 0 ],
            'dbPort'               => [ 'isRequired' => 1, 'value' => 0 ],
            'dbUser'               => [ 'isRequired' => 1, 'value' => 0 ],
            'baseUrl'              => [ 'isRequired' => 1, 'value' => 0 ],
            'timeZone'             => [ 'isRequired' => 1, 'value' => 0 ],
            'minRefresh'           => [ 'isRequired' => 0, 'value' => 0 ],
            'defaultRefresh'       => [ 'isRequired' => 0, 'value' => 0 ],
            'issueTrackerBaseUrl'  => [ 'isRequired' => 1, 'value' => 0 ],
            'roQueryPart'          => [ 'isRequired' => 1, 'value' => 0 ],
            'killStatement'        => [ 'isRequired' => 0, 'value' => 0 ],
            'showSlaveStatement'   => [ 'isRequired' => 0, 'value' => 0 ],
            'doLDAPAuthentication' => [ 'isRequired' => 0, 'value' => 0 ],
            'ldapHost'             => [ 'isRequired' => 0, 'value' => 0 ],
            'ldapDomainName'       => [ 'isRequired' => 0, 'value' => 0 ],
            'ldapUserGroup'        => [ 'isRequired' => 0, 'value' => 0 ],
            'ldapUserDomain'       => [ 'isRequired' => 0, 'value' => 0 ],
            'ldapVerifyCert'       => [ 'isRequired' => 0, 'value' => 0 ],
            'ldapDebugConnection'  => [ 'isRequired' => 0, 'value' => 0 ],
            'globalStatusDb'       => [ 'isRequired' => 0, 'value' => 0 ],
            'jiraEnabled'          => [ 'isRequired' => 0, 'value' => 0 ],
            'jiraProjectId'        => [ 'isRequired' => 0, 'value' => 0 ],
            'jiraIssueTypeId'      => [ 'isRequired' => 0, 'value' => 0 ],
            'jiraQueryHashFieldId' => [ 'isRequired' => 0, 'value' => 0 ],
            'testDbUser'               => [ 'isRequired' => 0, 'value' => 0 ],
            'testDbPass'               => [ 'isRequired' => 0, 'value' => 0 ],
            'testDbName'               => [ 'isRequired' => 0, 'value' => 0 ],
            'enableMaintenanceWindows' => [ 'isRequired' => 0, 'value' => 0 ],
            'dbaSessionTimeout'        => [ 'isRequired' => 0, 'value' => 0 ],
            'redisEnabled'             => [ 'isRequired' => 0, 'value' => 0 ],
            'redisUser'                => [ 'isRequired' => 0, 'value' => 0 ],
            'redisPassword'            => [ 'isRequired' => 0, 'value' => 0 ],
            'redisConnectTimeout'      => [ 'isRequired' => 0, 'value' => 0 ],
            'redisDatabase'            => [ 'isRequired' => 0, 'value' => 0 ],
            'postgresqlEnabled'        => [ 'isRequired' => 0, 'value' => 0 ],
            'environments'             => [ 'isRequired' => 0, 'value' => 0 ],
            'defaultEnvironment'       => [ 'isRequired' => 0, 'value' => 0 ],
            'adminPassword'            => [ 'isRequired' => 0, 'value' => 0 ],
            'enableSpeechAlerts'       => [ 'isRequired' => 0, 'value' => 0 ],
            // mysqlEnabled is required - AQL's backend database is MySQL
            'mysqlEnabled'             => [ 'isRequired' => 1, 'value' => 0 ]
            // Note: Other DB Type settings ({type}Enabled, {type}Username, {type}Password)
            // are validated dynamically via pattern matching to avoid hardcoding
        ] ;
    }

    /**
     * Detect whether the XML uses the new grouped element format
     *
     * @param \SimpleXMLElement $xml
     * @return bool
     */
    private static function isGroupedFormat( $xml ) {
        $version = (int) ( (string) ( $xml['version'] ?? 0 ) ) ;
        return $version >= 2 || isset( $xml->configdb ) || isset( $xml->monitoring ) || isset( $xml->user ) ;
    }

    /**
     * Parse legacy flat <param> format
     *
     * @param \SimpleXMLElement $xml
     * @param array &$cfgValues
     * @param array &$paramList
     * @param string &$errors
     */
    private function parseFlatConfig( $xml, &$cfgValues, &$paramList, &$errors ) {
        $dbTypeParamPattern = '/^[a-z]+(?:Enabled|Username|Password)$/' ;
        $seenDbTypeParams = [] ;

        foreach ( $xml as $v ) {
            if ( $v->getName() === 'dbtype' ) {
                continue ; // handled by parseDbTypes()
            }

            $key = (string) $v[ 'name' ] ;

            if ( isset( $paramList[ $key ] ) ) {
                if ( $paramList[ $key ][ 'value' ] != 0 ) {
                    $errors .= "Multiply set parameter: " . $key . "\n" ;
                } else {
                    $paramList[ $key ][ 'value' ] ++ ;
                    switch ( $key ) {
                        case 'minRefresh' :
                        case 'defaultRefresh' :
                        case 'redisConnectTimeout' :
                        case 'redisDatabase' :
                            $cfgValues[$key] = (int) $v ;
                            break ;
                        default :
                            $cfgValues[$key] = (string) $v ;
                    }
                }
            } elseif ( preg_match( $dbTypeParamPattern, $key ) ) {
                if ( isset( $seenDbTypeParams[ $key ] ) ) {
                    $errors .= "Multiply set DB type parameter: " . $key . "\n" ;
                } else {
                    $seenDbTypeParams[ $key ] = true ;
                    $cfgValues[ $key ] = (string) $v ;
                }
            } else {
                $errors .= "Unknown parameter: " . $key . "\n" ;
            }
        }
    }

    /**
     * Mapping from grouped XML attributes to flat config keys
     *
     * @return array [ 'elementName' => [ 'xmlAttr' => 'flatKey', ... ], ... ]
     */
    private static function getGroupedMapping() {
        return [
            'configdb' => [
                'host'         => 'dbHost',
                'port'         => 'dbPort',
                'name'         => 'dbName',
                'instanceName' => 'dbInstanceName',
                'type'         => 'configDbType',
            ],
            'monitoring' => [
                'baseUrl'             => 'baseUrl',
                'timeZone'            => 'timeZone',
                'issueTrackerBaseUrl' => 'issueTrackerBaseUrl',
                'minRefresh'          => 'minRefresh',
                'defaultRefresh'      => 'defaultRefresh',
                'roQueryPart'         => 'roQueryPart',
                'killStatement'       => 'killStatement',
                'showSlaveStatement'  => 'showSlaveStatement',
                'globalStatusDb'      => 'globalStatusDb',
            ],
            'authentication' => [
                'adminPassword' => 'adminPassword',
            ],
            'ldap' => [
                'enabled'         => 'doLDAPAuthentication',
                'host'            => 'ldapHost',
                'domainName'      => 'ldapDomainName',
                'userGroup'       => 'ldapUserGroup',
                'userDomain'      => 'ldapUserDomain',
                'verifyCert'      => 'ldapVerifyCert',
                'debugConnection' => 'ldapDebugConnection',
            ],
            'jira' => [
                'enabled'          => 'jiraEnabled',
                'projectId'        => 'jiraProjectId',
                'issueTypeId'      => 'jiraIssueTypeId',
                'queryHashFieldId' => 'jiraQueryHashFieldId',
            ],
            'redis' => [
                'user'           => 'redisUser',
                'password'       => 'redisPassword',
                'connectTimeout' => 'redisConnectTimeout',
                'database'       => 'redisDatabase',
            ],
            'features' => [
                'enableMaintenanceWindows' => 'enableMaintenanceWindows',
                'dbaSessionTimeout'        => 'dbaSessionTimeout',
                'enableSpeechAlerts'       => 'enableSpeechAlerts',
            ],
            'testing' => [
                'dbUser' => 'testDbUser',
                'dbPass' => 'testDbPass',
                'dbName' => 'testDbName',
            ],
        ] ;
    }

    /**
     * Integer-typed config keys that need (int) casting
     */
    private static $intKeys = [
        'minRefresh', 'defaultRefresh', 'redisConnectTimeout', 'redisDatabase',
    ] ;

    /**
     * Parse new grouped element format
     *
     * @param \SimpleXMLElement $xml
     * @param array &$cfgValues
     * @param array &$paramList
     * @param string &$errors
     */
    private function parseGroupedConfig( $xml, &$cfgValues, &$paramList, &$errors ) {
        $mapping = self::getGroupedMapping() ;

        // Parse each grouped element
        foreach ( $mapping as $elementName => $attrMap ) {
            if ( ! isset( $xml->$elementName ) ) {
                continue ;
            }
            $element = $xml->$elementName ;
            foreach ( $attrMap as $xmlAttr => $flatKey ) {
                if ( isset( $element[ $xmlAttr ] ) ) {
                    $value = (string) $element[ $xmlAttr ] ;
                    if ( in_array( $flatKey, self::$intKeys, true ) ) {
                        $cfgValues[ $flatKey ] = (int) $value ;
                    } else {
                        $cfgValues[ $flatKey ] = $value ;
                    }
                    if ( isset( $paramList[ $flatKey ] ) ) {
                        $paramList[ $flatKey ][ 'value' ] ++ ;
                    }
                }
            }
        }

        // Parse <user> elements (admin and monitor)
        if ( isset( $xml->user ) ) {
            foreach ( $xml->user as $userEl ) {
                $userType = (string) $userEl['type'] ;
                $userName = (string) $userEl['name'] ;
                $userPass = (string) $userEl['password'] ;
                if ( $userType === 'admin' ) {
                    $cfgValues['dbUser'] = $userName ;
                    $cfgValues['dbPass'] = $userPass ;
                    if ( isset( $paramList['dbUser'] ) ) {
                        $paramList['dbUser']['value'] ++ ;
                    }
                    if ( isset( $paramList['dbPass'] ) ) {
                        $paramList['dbPass']['value'] ++ ;
                    }
                } elseif ( $userType === 'monitor' ) {
                    $cfgValues['monitorUser'] = $userName ;
                    $cfgValues['monitorPassword'] = $userPass ;
                } else {
                    $errors .= "Unknown user type: " . $userType . "\n" ;
                }
            }
        }

        // Parse <environment_types> element
        if ( isset( $xml->environment_types ) ) {
            $envNames = [] ;
            $defaultEnv = '' ;
            $hasExplicitOrder = false ;
            $hasMissingOrder = false ;

            foreach ( $xml->environment_types->environment_type as $envEl ) {
                $envName = (string) $envEl['name'] ;
                $envNames[] = $envName ;
                if ( isset( $envEl['default'] ) && (string) $envEl['default'] === 'true' ) {
                    $defaultEnv = $envName ;
                }
                if ( isset( $envEl['sort_order'] ) ) {
                    $hasExplicitOrder = true ;
                } else {
                    $hasMissingOrder = true ;
                }
            }

            if ( $hasExplicitOrder && $hasMissingOrder ) {
                $errors .= "environment_types: if any environment_type has sort_order, all must\n" ;
            }

            $cfgValues['environments'] = implode( ',', $envNames ) ;
            if ( isset( $paramList['environments'] ) ) {
                $paramList['environments']['value'] ++ ;
            }
            if ( ! empty( $defaultEnv ) ) {
                $cfgValues['defaultEnvironment'] = $defaultEnv ;
                if ( isset( $paramList['defaultEnvironment'] ) ) {
                    $paramList['defaultEnvironment']['value'] ++ ;
                }
            }
        }
    }

    /**
     * Parse <dbtype> elements (shared by both formats)
     *
     * @param \SimpleXMLElement $xml
     * @param array &$cfgValues
     * @param array &$paramList
     * @param string &$errors
     * @param bool $isGroupedFormat Whether using the new grouped format
     */
    private function parseDbTypes( $xml, &$cfgValues, &$paramList, &$errors, $isGroupedFormat = false ) {
        $seenDbTypes = [] ;

        foreach ( $xml->dbtype as $v ) {
            $typeName = (string) $v['name'] ;
            if ( empty( $typeName ) ) {
                $errors .= "dbtype element missing name attribute\n" ;
                continue ;
            }
            if ( isset( $seenDbTypes[ $typeName ] ) ) {
                $errors .= "Multiply defined dbtype: " . $typeName . "\n" ;
                continue ;
            }
            $seenDbTypes[ $typeName ] = true ;
            $lcType = strtolower( str_replace( [ '-', ' ' ], '', $typeName ) ) ;
            if ( isset( $v['enabled'] ) ) {
                $enabledKey = $lcType . 'Enabled' ;
                $cfgValues[ $enabledKey ] = (string) $v['enabled'] ;
                if ( isset( $paramList[ $enabledKey ] ) ) {
                    $paramList[ $enabledKey ]['value'] ++ ;
                }
            }
            if ( isset( $v['username'] ) ) {
                $cfgValues[ $lcType . 'Username' ] = (string) $v['username'] ;
            } elseif ( $isGroupedFormat && ! empty( $cfgValues['monitorUser'] ) ) {
                // In grouped format, fall back to monitor user credentials
                $cfgValues[ $lcType . 'Username' ] = $cfgValues['monitorUser'] ;
            }
            if ( isset( $v['password'] ) ) {
                $cfgValues[ $lcType . 'Password' ] = (string) $v['password'] ;
            } elseif ( $isGroupedFormat && ! empty( $cfgValues['monitorPassword'] ) ) {
                $cfgValues[ $lcType . 'Password' ] = $cfgValues['monitorPassword'] ;
            }
        }
    }

    /**
     * Assign parsed config values to class properties
     *
     * @param array $cfgValues
     * @param string|null $dbHost Constructor override
     * @param int|null $dbPort Constructor override
     * @param string|null $dbInstanceName Constructor override
     * @param string|null $dbName Constructor override
     * @param string|null $dbUser Constructor override
     * @param string|null $dbPass Constructor override
     */
    private function assignProperties( $cfgValues, $dbHost, $dbPort, $dbInstanceName, $dbName, $dbUser, $dbPass ) {
        $this->baseUrl = $cfgValues[ 'baseUrl' ] ;
        $this->dbHost = (! isset( $dbHost ) ) ? $cfgValues[ 'dbHost' ] : $dbHost ;
        $this->dbPort = (! isset( $dbPort ) ) ? $cfgValues[ 'dbPort' ] : $dbPort ;
        $this->dbInstanceName = (! isset( $dbInstanceName ) ) ? $cfgValues[ 'dbInstanceName' ] : $dbInstanceName ;
        $this->dbName = (! isset( $dbName ) ) ? $cfgValues[ 'dbName' ] : $dbName ;
        $this->dbUser = (! isset( $dbUser ) ) ? $cfgValues[ 'dbUser' ] : $dbUser ;
        $this->dbPass = (! isset( $dbPass ) ) ? $cfgValues[ 'dbPass' ] : $dbPass ;
        $this->minRefresh = $cfgValues[ 'minRefresh' ] ;
        $this->defaultRefresh = $cfgValues[ 'defaultRefresh' ] ;
        $this->timeZone = $cfgValues[ 'timeZone' ] ;
        ini_set( 'date.timezone', $this->timeZone ) ;
        $this->issueTrackerBaseUrl = $cfgValues[ 'issueTrackerBaseUrl' ] ;
        $this->roQueryPart = $cfgValues[ 'roQueryPart' ] ;
        $this->killStatement = $cfgValues[ 'killStatement' ] ;
        $this->showSlaveStatement = $cfgValues[ 'showSlaveStatement' ] ;
        $this->doLDAPAuthentication = $cfgValues[ 'doLDAPAuthentication' ] ;
        $this->ldapHost = $cfgValues[ 'ldapHost' ] ;
        $this->ldapDomainName = $cfgValues[ 'ldapDomainName' ] ;
        $this->ldapUserGroup = $cfgValues[ 'ldapUserGroup' ] ;
        $this->ldapUserDomain = $cfgValues[ 'ldapUserDomain' ] ;
        $this->ldapVerifyCert = $cfgValues[ 'ldapVerifyCert' ] ?? 'true' ;
        $this->ldapDebugConnection = $cfgValues[ 'ldapDebugConnection' ] ?? 'false' ;
        $this->globalStatusDb = $cfgValues[ 'globalStatusDb' ] ;
        $this->jiraEnabled = $cfgValues[ 'jiraEnabled' ] ?? 'false' ;
        $this->jiraProjectId = $cfgValues[ 'jiraProjectId' ] ?? '' ;
        $this->jiraIssueTypeId = $cfgValues[ 'jiraIssueTypeId' ] ?? '' ;
        $this->jiraQueryHashFieldId = $cfgValues[ 'jiraQueryHashFieldId' ] ?? '' ;
        $this->testDbUser = $cfgValues[ 'testDbUser' ] ?? '' ;
        $this->testDbPass = $cfgValues[ 'testDbPass' ] ?? '' ;
        $this->testDbName = $cfgValues[ 'testDbName' ] ?? '' ;
        $this->enableMaintenanceWindows = $cfgValues[ 'enableMaintenanceWindows' ] ?? 'false' ;
        $this->dbaSessionTimeout = $cfgValues[ 'dbaSessionTimeout' ] ?? '86400' ;
        $this->redisEnabled = $cfgValues[ 'redisEnabled' ] ?? 'false' ;
        $this->redisUser = $cfgValues[ 'redisUser' ] ?? '' ;
        $this->redisPassword = $cfgValues[ 'redisPassword' ] ?? '' ;
        $this->redisConnectTimeout = $cfgValues[ 'redisConnectTimeout' ] ?? 2 ;
        $this->redisDatabase = $cfgValues[ 'redisDatabase' ] ?? 0 ;
        $this->postgresqlEnabled = $cfgValues[ 'postgresqlEnabled' ] ?? 'false' ;
        $this->enableSpeechAlerts = $cfgValues[ 'enableSpeechAlerts' ] ?? 'true' ;
        $this->configDbType = $cfgValues[ 'configDbType' ] ?? 'mysql' ;
    }

    /**
     * Class Constructor
     *
     * @param string $dbHost
     * @param int|null $dbPort
     * @param string $dbInstanceName
     * @param string $dbName
     * @param string $dbUser
     * @param string $dbPass
     * @throws ConfigurationException
     * @SuppressWarnings indentation
     */
    public function __construct( $dbHost = null, $dbPort = null, $dbInstanceName = null, $dbName = null, $dbUser = null, $dbPass = null )
    {
        $configFile = __DIR__ . '/../aql_config.xml' ;
        if ( ! is_readable( $configFile ) ) {
            throw new ConfigurationException( "Unable to load configuration from $configFile!" ) ;
        }
        $xml = simplexml_load_file( $configFile ) ;
        if ( ! $xml ) {
            throw new ConfigurationException( "Invalid syntax in $configFile!" ) ;
        }
        $errors = "" ;
        $cfgValues = self::getDefaults() ;
        $paramList = self::getParamList() ;

        $isGrouped = self::isGroupedFormat( $xml ) ;
        if ( $isGrouped ) {
            $this->parseGroupedConfig( $xml, $cfgValues, $paramList, $errors ) ;
        } else {
            $this->parseFlatConfig( $xml, $cfgValues, $paramList, $errors ) ;
        }
        $this->parseDbTypes( $xml, $cfgValues, $paramList, $errors, $isGrouped ) ;

        foreach ($paramList as $key => $x) {
            if ( ( 1 === $x[ 'isRequired' ] ) && ( 0 === $x[ 'value' ] ) ) {
                $errors .= "Missing parameter: " . $key . "\n" ;
            }
        }
        if ($errors !== '') {
            throw new \Exception( "\nConfiguration problem!\n\n" . $errors . "\n" ) ;
        }
        $this->assignProperties( $cfgValues, $dbHost, $dbPort, $dbInstanceName, $dbName, $dbUser, $dbPass ) ;
    }

    /**
     * Another magic method...
     *
     * @return string
     */
    public function __toString()
    {
        return "Config::__toString not implemented for security reasons.";
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getDbHost()
    {
        return $this->dbHost;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getDbPort()
    {
        return $this->dbPort;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getDbUser()
    {
        return $this->dbUser;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getDbPass()
    {
        return $this->dbPass;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getDbInstanceName()
    {
        return $this->dbInstanceName;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getTimeZone()
    {
        return $this->timeZone;
    }

    /**
     * Getter
     *
     * @return int
     */
    public function getMinRefresh()
    {
        return $this->minRefresh;
    }

    /**
     * Getter
     *
     * @return int
     */
    public function getDefaultRefresh()
    {
        return $this->defaultRefresh;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getIssueTrackerBaseUrl()
    {
        return $this->issueTrackerBaseUrl;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getRoQueryPart() {
        return $this->roQueryPart;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getKillStatement() {
        return $this->killStatement;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getShowSlaveStatement() {
        return $this->showSlaveStatement;
    }

    /**
     * Return the DSN for this connection
     *
     * @param string $dbType
     * @return string
     * @SuppressWarnings indentation
     */
    public function getDsn($dbType = 'mysql')
    {
        return $dbType
             . ':host=' . $this->getDbHost() . ':' . $this->getDbPort()
             . ';dbname=' . $this->getDbName();
    }

    /**
     * Return true or false based on ...
     */
    public function getDoLDAPAuthentication() {
        return ( 'true' === $this->doLDAPAuthentication ) ;
    }

    /**
     * Return the LDAP Host for this configuration
     */
    public function getLDAPHost() {
        return ( null !== $this->ldapHost ) ? $this->ldapHost : '' ;
    }

    /**
     * Return the LDAP Host for this configuration
     */
    public function getLDAPDomainName() {
        return ( null !== $this->ldapDomainName ) ? $this->ldapDomainName : '' ;
    }

    /**
     * Return the LDAP User Group for this configuration
     */
    public function getLDAPUserGroup() {
        return ( null !== $this->ldapUserGroup ) ? $this->ldapUserGroup : '' ;
    }

    /**
     * Return the LDAP User Domain for this configuration
     */
    public function getLDAPUserDomain() {
        return ( null !== $this->ldapUserDomain ) ? $this->ldapUserDomain : '' ;
    }

    /**
     * Return whether to verify LDAP SSL certificates (defaults to true)
     */
    public function getLDAPVerifyCert() {
        return ( 'false' !== $this->ldapVerifyCert ) ;
    }

    /**
     * Return whether to show LDAP connection debug output (defaults to false)
     */
    public function getLDAPDebugConnection() {
        return ( 'true' === $this->ldapDebugConnection ) ;
    }

    /**
     * Return the database that the global_status table lives in
     */
    public function getGlobalStatusDb() {
        return ( null != $this->globalStatusDb ) ? $this->globalStatusDb : '' ;
    }

    /**
     * Return whether Jira integration is enabled
     *
     * @return boolean
     */
    public function getJiraEnabled() {
        return ( 'true' === $this->jiraEnabled ) ;
    }

    /**
     * Return the Jira project ID
     *
     * @return string
     */
    public function getJiraProjectId() {
        return ( null !== $this->jiraProjectId ) ? $this->jiraProjectId : '' ;
    }

    /**
     * Return the Jira issue type ID
     *
     * @return string
     */
    public function getJiraIssueTypeId() {
        return ( null !== $this->jiraIssueTypeId ) ? $this->jiraIssueTypeId : '' ;
    }

    /**
     * Return the Jira custom field ID for query hash
     *
     * @return string
     */
    public function getJiraQueryHashFieldId() {
        return ( null !== $this->jiraQueryHashFieldId ) ? $this->jiraQueryHashFieldId : '' ;
    }

    /**
     * Getter for test database user
     *
     * @return string
     */
    public function getTestDbUser() {
        return ( null !== $this->testDbUser ) ? $this->testDbUser : '' ;
    }

    /**
     * Getter for test database password
     *
     * @return string
     */
    public function getTestDbPass() {
        return ( null !== $this->testDbPass ) ? $this->testDbPass : '' ;
    }

    /**
     * Getter for test database name
     *
     * @return string
     */
    public function getTestDbName() {
        return ( null !== $this->testDbName ) ? $this->testDbName : '' ;
    }

    /**
     * Check if maintenance windows feature is enabled
     *
     * @return bool
     */
    public function getEnableMaintenanceWindows() {
        return ( 'true' === $this->enableMaintenanceWindows ) ;
    }

    /**
     * Get DBA session timeout in seconds
     *
     * @return int
     */
    public function getDbaSessionTimeout() {
        return (int) ( $this->dbaSessionTimeout ?? 86400 ) ;
    }

    /**
     * Check if Redis monitoring is enabled
     *
     * @return bool
     */
    public function getRedisEnabled() {
        return ( 'true' === $this->redisEnabled ) ;
    }

    /**
     * Check if PostgreSQL monitoring is enabled
     *
     * @return bool
     */
    public function getPostgresqlEnabled() {
        return ( 'true' === $this->postgresqlEnabled ) ;
    }

    /**
     * Get Redis username (for Redis 6+ ACL authentication)
     *
     * @return string
     */
    public function getRedisUser() {
        return ( null !== $this->redisUser ) ? $this->redisUser : '' ;
    }

    /**
     * Get Redis password
     *
     * @return string
     */
    public function getRedisPassword() {
        return ( null !== $this->redisPassword ) ? $this->redisPassword : '' ;
    }

    /**
     * Get Redis connection timeout in seconds
     *
     * @return int
     */
    public function getRedisConnectTimeout() {
        return (int) ( $this->redisConnectTimeout ?? 2 ) ;
    }

    /**
     * Get Redis database number (0-15)
     *
     * @return int
     */
    public function getRedisDatabase() {
        return (int) ( $this->redisDatabase ?? 0 ) ;
    }

    /**
     * Check if speech alerts are enabled
     *
     * @return bool
     */
    public function getEnableSpeechAlerts() {
        return ( 'true' === $this->enableSpeechAlerts ) ;
    }

    /**
     * Get the configdb type (defaults to 'mysql')
     *
     * @return string
     */
    public function getConfigDbType() {
        return $this->configDbType ?? 'mysql' ;
    }

    /**
     * Get list of DB types from the DDL ENUM definition
     *
     * @param \mysqli $dbh Database connection
     * @return array List of DB type names
     */
    public function getDbTypes( $dbh ) {
        $dbTypes = [] ;
        $sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS "
             . "WHERE TABLE_SCHEMA = '" . $this->dbName . "' AND TABLE_NAME = 'host' AND COLUMN_NAME = 'db_type'" ;
        $result = $dbh->query( $sql ) ;
        if ( $result && $row = $result->fetch_row() ) {
            // Parse enum('val1','val2',...) into array
            if ( preg_match( "/^enum\((.+)\)$/i", $row[0], $matches ) ) {
                preg_match_all( "/'([^']+)'/", $matches[1], $typeMatches ) ;
                if ( ! empty( $typeMatches[1] ) ) {
                    $dbTypes = $typeMatches[1] ;
                }
            }
        }
        return $dbTypes ;
    }

    /**
     * Get properties for all DB types (enabled, username, password)
     * Reads types from DDL and config settings
     *
     * @param \mysqli $dbh Database connection
     * @return array Associative array: $props['DBType']['enabled'|'username'|'password']
     */
    public function getDbTypeProperties( $dbh ) {
        $properties = [] ;
        $dbTypes = $this->getDbTypes( $dbh ) ;
        $cfgValues = $this->oConfig ?? null ;

        foreach ( $dbTypes as $dbType ) {
            // Config keys use lowercase, e.g., mysqlEnabled, redisUsername
            // Handle special case: 'MS-SQL' -> 'mssql', 'InnoDBCluster' -> 'innodbcluster'
            $lcType = strtolower( str_replace( [ '-', ' ' ], '', $dbType ) ) ;

            $properties[ $dbType ] = [
                'enabled'  => $this->isDbTypeEnabled( $lcType ),
                'username' => $this->getConfigValue( $lcType . 'Username', '' ),
                'password' => $this->getConfigValue( $lcType . 'Password', '' ),
            ] ;
        }
        return $properties ;
    }

    /**
     * Check if a specific DB type is enabled in config
     *
     * @param string $lcType Lowercase DB type name (e.g., 'mysql', 'redis')
     * @return bool
     */
    private function isDbTypeEnabled( $lcType ) {
        // Check for {dbtype}Enabled in config
        $value = $this->getConfigValue( $lcType . 'Enabled', 'false' ) ;
        return ( 'true' === $value ) ;
    }

    /**
     * Get a config value by key with default
     * Supports both legacy flat <param> format and new grouped element format
     *
     * @param string $key Config key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getConfigValue( $key, $default = null ) {
        // Access the raw config values array
        static $cfgValues = null ;
        if ( $cfgValues === null ) {
            $configFile = dirname( __DIR__ ) . '/aql_config.xml' ;
            if ( file_exists( $configFile ) ) {
                $xml = @simplexml_load_file( $configFile ) ;
                if ( $xml ) {
                    $cfgValues = [] ;

                    if ( self::isGroupedFormat( $xml ) ) {
                        // New grouped format
                        $mapping = self::getGroupedMapping() ;
                        foreach ( $mapping as $elementName => $attrMap ) {
                            if ( ! isset( $xml->$elementName ) ) continue ;
                            $element = $xml->$elementName ;
                            foreach ( $attrMap as $xmlAttr => $flatKey ) {
                                if ( isset( $element[ $xmlAttr ] ) ) {
                                    $cfgValues[ $flatKey ] = (string) $element[ $xmlAttr ] ;
                                }
                            }
                        }
                        // Parse <user> elements
                        if ( isset( $xml->user ) ) {
                            foreach ( $xml->user as $userEl ) {
                                $userType = (string) $userEl['type'] ;
                                if ( $userType === 'admin' ) {
                                    $cfgValues['dbUser'] = (string) $userEl['name'] ;
                                    $cfgValues['dbPass'] = (string) $userEl['password'] ;
                                } elseif ( $userType === 'monitor' ) {
                                    $cfgValues['monitorUser'] = (string) $userEl['name'] ;
                                    $cfgValues['monitorPassword'] = (string) $userEl['password'] ;
                                }
                            }
                        }
                        // Parse <environment_types>
                        if ( isset( $xml->environment_types ) ) {
                            $envNames = [] ;
                            $defaultEnv = '' ;
                            foreach ( $xml->environment_types->environment_type as $envEl ) {
                                $envNames[] = (string) $envEl['name'] ;
                                if ( isset( $envEl['default'] ) && (string) $envEl['default'] === 'true' ) {
                                    $defaultEnv = (string) $envEl['name'] ;
                                }
                            }
                            $cfgValues['environments'] = implode( ',', $envNames ) ;
                            if ( ! empty( $defaultEnv ) ) {
                                $cfgValues['defaultEnvironment'] = $defaultEnv ;
                            }
                        }
                    } else {
                        // Legacy flat <param> format
                        foreach ( $xml->param as $v ) {
                            $cfgValues[ (string) $v['name'] ] = (string) $v ;
                        }
                    }

                    // Read <dbtype> elements and map to flat keys (shared by both formats)
                    if ( isset( $xml->dbtype ) ) {
                        $isGrouped = self::isGroupedFormat( $xml ) ;
                        foreach ( $xml->dbtype as $dt ) {
                            $typeName = (string) $dt['name'] ;
                            if ( empty( $typeName ) ) continue ;
                            $lcType = strtolower( str_replace( [ '-', ' ' ], '', $typeName ) ) ;
                            if ( isset( $dt['enabled'] ) ) {
                                $cfgValues[ $lcType . 'Enabled' ] = (string) $dt['enabled'] ;
                            }
                            if ( isset( $dt['username'] ) ) {
                                $cfgValues[ $lcType . 'Username' ] = (string) $dt['username'] ;
                            } elseif ( $isGrouped && ! empty( $cfgValues['monitorUser'] ) ) {
                                $cfgValues[ $lcType . 'Username' ] = $cfgValues['monitorUser'] ;
                            }
                            if ( isset( $dt['password'] ) ) {
                                $cfgValues[ $lcType . 'Password' ] = (string) $dt['password'] ;
                            } elseif ( $isGrouped && ! empty( $cfgValues['monitorPassword'] ) ) {
                                $cfgValues[ $lcType . 'Password' ] = $cfgValues['monitorPassword'] ;
                            }
                        }
                    }
                }
            }
            if ( $cfgValues === null ) {
                $cfgValues = [] ;
            }
        }
        return $cfgValues[ $key ] ?? $default ;
    }

    /**
     * Get list of enabled DB types only
     *
     * @param \mysqli $dbh Database connection
     * @return array List of enabled DB type names
     */
    public function getEnabledDbTypes( $dbh ) {
        $enabled = [] ;
        $props = $this->getDbTypeProperties( $dbh ) ;
        foreach ( $props as $dbType => $settings ) {
            if ( $settings['enabled'] ) {
                $enabled[] = $dbType ;
            }
        }
        return $enabled ;
    }

    /**
     * Get list of DB types that have active hosts in the database
     *
     * @param \mysqli $dbh Database connection
     * @return array List of DB type names with active hosts
     */
    public function getDbTypesInUse( $dbh ) {
        $inUse = [] ;
        $sql = "SELECT DISTINCT db_type FROM `" . $this->dbName . "`.host "
             . "WHERE should_monitor = 1 AND decommissioned = 0 ORDER BY db_type" ;
        $result = $dbh->query( $sql ) ;
        if ( $result ) {
            while ( $row = $result->fetch_assoc() ) {
                $inUse[] = $row['db_type'] ;
            }
            $result->close() ;
        }
        return $inUse ;
    }

    /**
     * Get parsed environment types from config (new grouped format only)
     * Returns array of ['name' => string, 'default' => bool, 'sort_order' => int|null]
     *
     * @return array|null Null if using legacy format (use getConfigValue('environments') instead)
     */
    public function getEnvironmentTypes() {
        $configFile = __DIR__ . '/../aql_config.xml' ;
        $xml = @simplexml_load_file( $configFile ) ;
        if ( ! $xml || ! isset( $xml->environment_types ) ) {
            return null ;
        }
        $envTypes = [] ;
        $position = 1 ;
        foreach ( $xml->environment_types->environment_type as $envEl ) {
            $sortOrder = isset( $envEl['sort_order'] ) ? (int) $envEl['sort_order'] : ( $position * 10 ) ;
            $envTypes[] = [
                'name'       => (string) $envEl['name'],
                'default'    => ( isset( $envEl['default'] ) && (string) $envEl['default'] === 'true' ),
                'sort_order' => $sortOrder,
            ] ;
            $position ++ ;
        }
        return $envTypes ;
    }

}
