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

use com\kbcmdba\aql\Libs\Exceptions\DaoException ;

/**
 * DBConnection
 */
class DBConnection
{
    const CONNECT_TIMEOUT_SECONDS = 4 ;

    private $dbh;
    private $oConfig;
    private $connectionClass;
    private $createdDb;

    /**
     * Class Constructor
     *
     * @param String $connType
     * @param String $dbHost
     * @param String $dbInstanceName
     * @param String $dbName
     * @param String $dbUser
     * @param String $dbPass
     * @param Integer $dbPort
     * @param String $connClass
     *            Must be 'mysql', 'mysqli', 'PDO', or 'MS-SQL' for now.
     * @return void
     * @throws \Exception
     * @SuppressWarnings indentation
     * @SuppressWarnings cyclomaticComplexity
     */
    public function __construct(
        $connType       = null,
        $dbHost         = null,
        $dbInstanceName = null,
        $dbName         = null,
        $dbUser         = null,
        $dbPass         = null,
        $dbPort         = null,
        $connClass      = 'mysqli',
        $createDb       = false
    ) {
        $oConfig = new Config($dbHost, $dbPort, $dbInstanceName, $dbName, $dbUser, $dbPass);
        $this->oConfig = $oConfig;
        switch ($connClass) {
            case 'mysql':
                $this->dbh = mysql_connect(
                    $oConfig->getDbHost() . ':' . $oConfig->getDbPort(),
                    $oConfig->getDbUser(),
                    $oConfig->getDbPass()
                );
                if (! $this->dbh) {
                    throw new \Exception('Error connecting to database server('
                                        . $oConfig->getDbHost() . ')! : '
                                        . mysql_error());
                }
                $dbName = Tools::coalesce([
                    $oConfig->getDbName(),
                    ''
                ]);
                if ($dbName !== '') {
                    if (! mysql_select_db($dbName, $this->dbh)) {
                        throw new \Exception('Database does not exist: ', $dbName);
                    }
                }
                break;
            case 'mysqli':
                $mysqli = \mysqli_init();
                if (! $mysqli) {
                    throw new DaoException("Failed to allocate connection class!");
                }
                if (! $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT_SECONDS)) {
                    throw new DaoException('Failed setting connection timeout.');
                }
                try {
                    set_error_handler("\\com\\kbcmdba\\aql\\Libs\\DBConnection::myErrorHandler");
                    $result = $mysqli->real_connect(
                        $oConfig->getDbHost(),
                        $oConfig->getDbUser(),
                        $oConfig->getDbPass(),
                        null,
                        $oConfig->getDbPort()
                    );
                    restore_error_handler();
                } catch (\Exception $e) {
                    throw new DaoException($e->getMessage() . "\n" ) ;
                }
                if ((! $result) || ($mysqli->connect_errno)) {
                    throw new DaoException(
                        'Error connecting to database server(' . $oConfig->getDbHost() . ')! : '
                        . $mysqli->connect_error
                    );
                }
                $mysqli->autocommit( true ) ;
                $this->dbh = $mysqli;
                if ($this->dbh->connect_error) {
                    throw new DaoException(
                        'Error connecting to database server(' . $oConfig->getDbHost() . ')! : ' .
                        $this->dbh->connect_error
                    );
                }
                $this->dbh->query("SET @@SESSION.SQL_MODE = 'ALLOW_INVALID_DATES'");
                if (! $mysqli->select_db($oConfig->getDbName())) {
                    if ($createDb) {
                        $this->createdDb = true;
                        $this->dbh->query("CREATE DATABASE IF NOT EXISTS " . $oConfig->getDbName());
                        if (! $mysqli->select_db($oConfig->getDbName())) {
                            throw new DaoException(
                                "Database: {$oConfig->getDbName()} is missing. Please use resetDb.php to install"
                                . " the database."
                            );
                        }
                    } else {
                        throw new DaoException(
                            "Database: {$oConfig->getDbName()} is missing. Please use resetDb.php to install "
                            . "the database."
                        );
                    }
                }
                break;
            case 'PDO':
                // May throw PDOException by itself.
                $this->dbh = new \PDO($oConfig->getDsn(), $oConfig->getDbUser(), $oConfig->getDbPass(),
                                       [\PDO::ATTR_TIMEOUT=>self::CONNECT_TIMEOUT_SECONDS,
                                        \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION
                                       ]
                                     );
                if (! $this->dbh) {
                    throw new DaoException('Error connecting to database server(' . $oConfig->getDbHost() . ')!');
                }
                break;
            case 'MS-SQL':
                throw new DaoException('Connection class not implemented: ' . $connClass);
                // https://www.php.net/manual/en/function.sqlsrv-connect.php
                $serverName = $oConfig->getDbHost() . "\\" . $oConfig->getDbInstanceName;
                $connectionInfo = array("Database" => "aql_db", "UID" => $oConfig->getDbUser(), "PWD" => $oConfig->getDbPassword());
                $this->dbh = sqlsrv_connect($serverName, $connectionInfo);
                if (! $this->dbh) {
                    throw new DaoException('Connection to $serverName could not be established. ' . sqlsrv_errors());
                }
                break ;
            default:
                throw new DaoException('Unknown connection class: ' . $connClass);
        } // END OF switch ( $connClass )
        $this->connectionClass = $connClass;
    }

    /**
     * __toString
     *
     * @return string
     */
    public function __toString()
    {
        if (isset($this->dbh)) {
            return $this->oConfig;
        } else {
            return "Not connected.";
        }
    }

    public static function myErrorHandler()
    {
        return;
    }

    /**
     * Give back the database handle
     *
     * @return mixed
     * @throws \Exception
     */
    public function getConnection()
    {
        if ((! isset($this->dbh)) || (! ($this->dbh))) {
            throw new \Exception('Invalid connection!');
        } else {
            return $this->dbh;
        }
    }

    /**
     * Give back the connection class passed to the constructor.
     *
     * @return mixed
     */
    public function getConnectionClass()
    {
        return $this->connectionClass;
    }

    /**
     *
     * @return boolean
     */
    public function getCreatedDb()
    {
        return $this->createdDb;
    }
}
