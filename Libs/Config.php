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
    const VERSION = 'v2.5' ;

    /**
     * Configuration Class
     *
     * Note: There are no setters for this class. All the configuration comes from
     * /etc/aql_config.xml (described in config_sample.xml).
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
        $cfgValues = [
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
            'testDbName' => ''
        ] ;
        $paramList = [
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
            'testDbUser'           => [ 'isRequired' => 0, 'value' => 0 ],
            'testDbPass'           => [ 'isRequired' => 0, 'value' => 0 ],
            'testDbName'           => [ 'isRequired' => 0, 'value' => 0 ]
        ] ;

        // verify that all the parameters are present and just once.
        foreach ( $xml as $v ) {
            $key = (string) $v[ 'name' ] ;
            if ( ( ! isset($paramList[ $key ] ) ) || ( $paramList[ $key ][ 'value' ] != 0 ) ) {
                $errors .= "Unset or multiply set name: " . $key . "\n" ;
            } else {
                $paramList[ $key ][ 'value' ] ++ ;
                switch ( $key ) {
                    case 'minRefresh' :
                    case 'defaultRefresh' :
                        $cfgValues[$key] = (int) $v ;
                        break ;
                    default :
                        $cfgValues[$key] = (string) $v ;
                }
            }
        }
        foreach ($paramList as $key => $x) {
            if ( ( 1 === $x[ 'isRequired' ] ) && ( 0 === $x[ 'value' ] ) ) {
                $errors .= "Missing parameter: " . $key . "\n" ;
            }
        }
        if ($errors !== '') {
            throw new \Exception( "\nConfiguration problem!\n\n" . $errors . "\n" ) ;
        }
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

}

