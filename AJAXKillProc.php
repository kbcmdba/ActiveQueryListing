<?php

/*
 *
 * aql - Active Query Listing
 *
 * Copyright (C) 2019 Kevin Benton - kbcmdba [at] gmail [dot] com
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

namespace com\kbcmdba\aql ;

require( 'vendor/autoload.php' ) ;

use com\kbcmdba\aql\Libs\Config;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;
use com\kbcmdba\aql\Libs\Tools ;

$login    = Tools::param( 'login' ) ;
$password = Tools::param( 'password' ) ;
$pid      = Tools::param( 'pid' ) ;
$reason   = Tools::param( 'reason' ) ;
$server   = Tools::param( 'server' ) ;

if  ( ( strlen( $login ) < 2 )
   || ( strlen( $password ) < 2 )
   || ( strlen( $reason ) < 2 )
   || ( $server === '' )
   || ( $pid === '' )
    ) {
    echo json_encode( [ 'result' => 'Error: Invalid parameters.' ] ) . "\n" ;
    exit( 0 ) ;
}
try {
    $oConfig = new Config() ;
}
catch ( ConfigurationException $e ) {
    echo json_encode( [ 'result' => 'Unable to load configuration properly: ' . $e->getMessage() ] ) . "\n" ;
    exit( 1 ) ;
}
if ( ! Tools::isNumeric( $pid ) ) {
    echo json_encode( [ 'result' => 'Invalid process/thread ID' ] ) . "\n" ;
    exit( 0 ) ;
}
try {
    $dbh = $log_dbh = $sth = $log_sth = null ;
    try {
        $dbc = new DBConnection( 'admin', $server, null, $login, $password, null, 'PDO' ) ;
        $dbh = $dbc->getConnection() ;
        $log_dbc = new DBConnection( 'admin', null, null, null, null, null, 'PDO' ) ;
        $log_dbh = $log_dbc->getConnection() ;
    }
    catch ( \PDOException $e ) {
        echo json_encode( [ 'result' => 'Connection failed.' ] ) . "\n" ;
        exit( 1 ) ;
    }
    $query = <<<SQL
SELECT id, user, host, db, command, time, state, info
  FROM INFORMATION_SCHEMA.PROCESSLIST
 WHERE id = :pid

SQL;
    try {
        $sth = $dbh->prepare( $query ) ;
    }
    catch ( \PDOException $e ) {
        echo json_encode( [ 'result' => 'An error has occurred: ' . $e->getMessage() ] ) . "\n" ;
        exit( 1 ) ;
    }
    try {
        $sth->execute( [ 'pid' => $pid ] ) ;
    }
    catch ( \PDOException $e ) {
        echo json_encde( [ 'result' => 'Execution error: ' . $e->getMessage() ] ) . "\n" ;
        exit( 1 ) ;
    }
    $r_id = $r_user = $r_host = $r_db = $r_command = $r_time = $r_state = $r_info = null ;
    $sth->bindColumn( 1, $r_id ) ;
    $sth->bindColumn( 2, $r_user ) ;
    $sth->bindColumn( 3, $r_db ) ;
    $sth->bindColumn( 4, $r_host ) ;
    $sth->bindColumn( 5, $r_command ) ;
    $sth->bindColumn( 6, $r_time ) ;
    $sth->bindColumn( 7, $r_state ) ;
    $sth->bindColumn( 8, $r_info ) ;
    try {
        $sth->fetch( \PDO::FETCH_BOUND ) ;
    }
    catch ( \PDOException $e ) {
        echo json_encode( [ 'result' => 'Process/Thread has gone away.' ] ) . "\n" ;
        exit( 0 ) ;
    }
    if ( ! isset( $r_id ) || ( 0 >= $r_id ) ) {
        echo json_encode( [ 'result' => 'Process/thread has gone away.' ] ) . "\n" ;
        exit( 0 ) ;
    }
    $sth->closeCursor() ;
    // If the kill_log table doesn't exist, create it.
    $query = <<<SQL
CREATE TABLE IF NOT EXISTS kill_log 
     ( id            INT UNSIGNED NOT NULL AUTO_INCREMENT 
     , killer        VARCHAR(64) NOT NULL DEFAULT ''
     , target_server VARCHAR(128) NOT NULL DEFAULT ''
     , pid           BIGINT UNSIGNED NOT NULL
     , user          VARCHAR(64) NULL DEFAULT '' COMMENT 'User running the query'
     , host          VARCHAR(128) NULL DEFAULT '' COMMENT 'Host the query ran from'
     , db            VARCHAR(128) NULL DEFAULT '' COMMENT 'DB/Schema that the user was USEing at the time the thread started'
     , command       VARCHAR(64) NULL DEFAULT ''
     , time          BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'How long the query was running (in seconds) at the time it was seen by AJAXKillProc.php'
     , state         VARCHAR(64) NULL DEFAULT ''
     , info          LONGTEXT NULL DEFAULT NULL
     , reason        TEXT NULL DEFAULT NULL
     , created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , PRIMARY KEY    ( id )
     , KEY killer_idx ( killer )
     , KEY user_idx   ( user )
     , KEY time_idx   ( time )
     )

SQL;
    if ( ! $log_dbh->query( $query ) ) {
        echo json_encode( [ 'result' => 'Unable to create kill_log table. Kill failed.' ] ) . "\n" ;
        exit( 1 ) ;
    }
    // Log the query about to be killed
    $log_sth = $log_dbh->prepare( "INSERT INTO kill_log" .
                                    . " ( killer, target_server, pid, user, host, db, command"
                                     . ", time, state, info, reason )"
                               . " VALUES ( :login, :server, :id, :user, :host, :db"
                                        . ", :command, :time, :state, :info, :reason )" ) ;
    if ( ! $log_sth->execute( [ ':login' => $login
                              , ':server' => $server
                              , ':id' => $r_id
                              , ':user' => $r_user
                              , ':host' => $r_host
                              , ':db' => $r_db
                              , ':command' => $r_command
                              , ':time' => $r_time
                              , ':state' => $r_state
                              , ':info' => $r_info
                              , ':reason' => $reason
                              ] ) ) {
        echo json_encode( [ 'result' => 'Unable to log kill data. Kill failed.' ] ) . "\n" ;
        exit( 1 ) ;
    }
    if ( ! $log_sth->closeCursor() ) {
        echo json_encode( [ 'result' => 'Unable to log kill data. Kill failed. ' . $log_sth->errorInfo() ] ) . "\n" ;
        exit( 1 ) ;
    }
    // Kill the query
    $sth = $dbh->prepare( $oConfig->getKillStatement() ) ;
    if ( FALSE === $sth ) {
        echo json_encode( [ 'result' => 'Prepare kill query failed. ' . $sth->errorInfo() ] ) . "\n" ;
        exit( 1 ) ;
    }
    if ( ! $sth->execute( [ 'pid' => $r_id ] ) ) {
        echo json_encode( [ 'result' => 'Execute kill query failed. ' . $sth->errorInfo() ] ) . "\n" ;
        exit( 1 ) ;
    }
    if ( ! $sth->closeCursor() ) {
        echo json_encode( [ 'result'=>'Close kill query failed. ' . $sth->errorInfo() ] ) . "\n" ;
        exit( 1 ) ;
    }
    // Report back to the user that "Query 123 was killed. This has been logged."
    echo json_encode( [ 'result' => 'Query ' . $r_id . ' has been killed. This has been logged.' ] ) . "\n";
    exit( 0 ) ;
}
catch ( \Exception $e ) {
    echo json_encode( [ 'result' => "Error connecting to database: " . $e->getMessage() ] ) . "\n" ;
    exit( 1 ) ;
}
