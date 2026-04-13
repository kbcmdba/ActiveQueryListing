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
    const READ_TIMEOUT_SECONDS = 8 ;  // Query execution timeout

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
            case 'mysql': // @codeCoverageIgnore
                $this->dbh = mysql_connect( // @codeCoverageIgnore
                    $oConfig->getDbHost() . ':' . $oConfig->getDbPort(), // @codeCoverageIgnore
                    $oConfig->getDbUser(), // @codeCoverageIgnore
                    $oConfig->getDbPass() // @codeCoverageIgnore
                ); // @codeCoverageIgnore
                if (! $this->dbh) { // @codeCoverageIgnore
                    throw new \Exception('Error connecting to database server(' // @codeCoverageIgnore
                                        . $oConfig->getDbHost() . ')! : ' // @codeCoverageIgnore
                                        . mysql_error()); // @codeCoverageIgnore
                } // @codeCoverageIgnore
                $dbName = Tools::coalesce([ // @codeCoverageIgnore
                    $oConfig->getDbName(), // @codeCoverageIgnore
                    '' // @codeCoverageIgnore
                ]); // @codeCoverageIgnore
                if ($dbName !== '') { // @codeCoverageIgnore
                    if (! mysql_select_db($dbName, $this->dbh)) { // @codeCoverageIgnore
                        throw new \Exception('Database does not exist: ', $dbName); // @codeCoverageIgnore
                    } // @codeCoverageIgnore
                } // @codeCoverageIgnore
                break; // @codeCoverageIgnore
            case 'mysqli':
                $mysqli = \mysqli_init();
                if (! $mysqli) {
                    throw new DaoException("Failed to allocate connection class!"); // @codeCoverageIgnore
                }
                if (! $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT_SECONDS)) {
                    throw new DaoException('Failed setting connection timeout.'); // @codeCoverageIgnore
                }
                if (! $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, self::READ_TIMEOUT_SECONDS)) {
                    throw new DaoException('Failed setting read timeout.'); // @codeCoverageIgnore
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
                } catch (\Exception $e) { // @codeCoverageIgnore
                    throw new DaoException($e->getMessage() . "\n" ) ; // @codeCoverageIgnore
                } // @codeCoverageIgnore
                if ((! $result) || ($mysqli->connect_errno)) { // @codeCoverageIgnore
                    throw new DaoException( // @codeCoverageIgnore
                        'Error connecting to database server(' . $oConfig->getDbHost() . ')! : ' // @codeCoverageIgnore
                        . $mysqli->connect_error // @codeCoverageIgnore
                    ); // @codeCoverageIgnore
                } // @codeCoverageIgnore
                $mysqli->autocommit( true ) ;
                $this->dbh = $mysqli;
                // @codeCoverageIgnoreStart
                if ($this->dbh->connect_error) {
                    throw new DaoException(
                        'Error connecting to database server(' . $oConfig->getDbHost() . ')! : ' .
                        $this->dbh->connect_error
                    );
                }
                // @codeCoverageIgnoreEnd
                $this->dbh->query("SET @@SESSION.SQL_MODE = 'ALLOW_INVALID_DATES'");
                $dbNameToSelect = $oConfig->getDbName() ;
                // @codeCoverageIgnoreStart — DB-missing/createDb paths
                // require a missing database which can't be arranged safely in tests
                if (( $dbNameToSelect !== null ) && ( $dbNameToSelect !== '' ) && ! $mysqli->select_db($dbNameToSelect)) {
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
                // @codeCoverageIgnoreEnd
                break;
            case 'PDO':
                // May throw PDOException by itself.
                $this->dbh = new \PDO($oConfig->getDsn(), $oConfig->getDbUser(), $oConfig->getDbPass(),
                                       [\PDO::ATTR_TIMEOUT=>self::CONNECT_TIMEOUT_SECONDS,
                                        \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION
                                       ]
                                     );
                if (! $this->dbh) {
                    throw new DaoException('Error connecting to database server(' . $oConfig->getDbHost() . ')!'); // @codeCoverageIgnore
                }
                break;
            case 'MS-SQL':
                throw new DaoException('Connection class not implemented: ' . $connClass);
                // https://www.php.net/manual/en/function.sqlsrv-connect.php
                $serverName = $oConfig->getDbHost() . "\\" . $oConfig->getDbInstanceName;
                $connectionInfo = array("Database" => $oConfig->getDbName(), "UID" => $oConfig->getDbUser(), "PWD" => $oConfig->getDbPassword());
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

    /**
     * Intentionally suppresses PHP warnings during mysqli::real_connect().
     *
     * real_connect emits E_WARNING on failures (DNS resolution, connection
     * refused, etc.) BEFORE returning false. Without this handler, those
     * warnings pollute HTML output or error logs with raw PHP messages.
     *
     * We swallow the warning here and let the error-checking code after
     * real_connect() handle the failure using $mysqli->connect_error, which
     * contains the full, structured error message. This is more informative
     * than the warning text, especially when real_connect emits multiple
     * warnings (e.g., DNS failed AND connection refused).
     *
     * Throwing here would work (the catch block converts to DaoException)
     * but would lose context from any subsequent warnings and skip the
     * more descriptive connect_error path.
     */
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
