<?php

/*  
 *  
 * ActiveQueryListing - Active Query Listing
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *  
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *      
 */     

namespace com\kbcmdba\ActiveQueryListing ;

require_once('vendor/autoload.php');
require_once('utility.php');

use com\kbcmdba\ActiveQueryListing\Libs\DBConnection;
use com\kbcmdba\ActiveQueryListing\Libs\Tools;
use com\kbcmdba\ActiveQueryListing\Libs\Exceptions\DaoException;

// Check the data
if ( ( strlen(Tools::param('login') ) <2 )
  || ( strlen(Tools::param('password') ) <2 )
  || ( strlen(Tools::param('reason') ) <2 )
  || ( Tools::param('server') === '')
  || ( Tools::param('pid') === '')
   ) {
    echo json_encode(['result'=>'Error: Invalid data. Missing login/password/reason?']) . "\n";
    exit();
}
$login = Tools::param('login');
$server = Tools::param('server');
$password = Tools::param('password');
$pid = Tools::param('pid');
$reason = Tools::param( 'reason' );
if ( !Tools::isNumeric($pid) ) {
    echo json_encode(['result'=>'Invalid thread ID']);
    exit(1);
}
try {
    $dbc = new DBConnection('admin', $server, null, $login, $password);
    $dbh = $dbc->getConnection();
    if ( FALSE === $dbh ) {
        echo json_encode(['result'=>'Unable to connect to DB server. Invalid credentials?']) . "\n";
        exit(1);
    }
    $log_dbc = new DBConnection('admin');
    $log_dbh = $log_dbc->getConnection();
    if ( FALSE === $log_dbh ) {
        echo json_encode(['result'=>'Unable to connect to admin DB server. Invalid configuration?']) . "\n";
        exit(1);
    }
    $stmt = $dbh->prepare('SELECT id, user, host, db, command, time, state, info FROM INFORMATION_SCHEMA.PROCESSLIST WHERE id = ?');
    $stmt->bind_param("i", $pid);
    if ( FALSE === $stmt ) {
        echo json_encode(['result'=>'An error occurred: ' . $stmt->error]) . "\n";
        exit(1);
    }
    if ( FALSE === $stmt->execute()) {
        echo json_encode(['result'=>'Execution error']);
        exit(1);
    }
    $r_id = $r_user = $r_host = $r_db = $r_command = $r_time = $r_state = $r_info = null ;
    if ( FALSE === $stmt->bind_result($r_id, $r_user, $r_host, $r_db, $r_command, $r_time, $r_state, $r_info) ) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ") " . $stmt->error;
        exit(1);
    }
    if ( ( FALSE === $stmt->fetch() ) || ! ( 0 < $r_id ) ) {
        echo json_encode(['result'=>'Thread is no longer running.']) . "\n";
        exit(0);
    }
    $stmt->close();
    // Create the appropriate aql_db table where logging will be done if it doesn't already exist.
    $query = "CREATE TABLE IF NOT EXISTS kill_log "
           .      "( id INT UNSIGNED NOT NULL AUTO_INCREMENT"
           .      ", killer VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'User doing the killing'"
           .      ", target_server VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'The server where the killing is done'"
           .      ", pid BIGINT UNSIGNED NOT NULL"
           .      ", user VARCHAR(64) NULL DEFAULT '' COMMENT 'User running the query'"
           .      ", host VARCHAR(128) NOT NULL DEFAULT ''"
           .      ", db VARCHAR(128) NULL DEFAULT ''"
           .      ", command VARCHAR(64) NULL DEFAULT ''"
           .      ", time BIGINT UNSIGNED NOT NULL DEFAULT 0"
           .      ", state VARCHAR(64) NULL DEFAULT ''"
           .      ", info LONGTEXT NULL DEFAULT NULL"
           .      ", reason TEXT NULL DEFAULT NULL"
           .      ", created_utc_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
           .      ", updated_utc_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
           .      ", PRIMARY KEY (id)"
           .      ", KEY killer_idx (killer)"
           .      ", KEY user_idx (user)"
           .      ", KEY time_idx (time)"
           .      ")";
    if ( ! $log_dbh->query( $query ) ) {
        echo json_encode( ['result'=>'Unable to create log table. Kill failed. ' . $dbh->error] ) . "\n";
        exit(1);
    }
    // Log the query being killed
    $log_stmt = $log_dbh->prepare( "INSERT INTO kill_log VALUES ( NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL )" );
    if ( ! $log_stmt->bind_param( "ssissssisss"
                                , $login
                                , $server
                                , $r_id
                                , $r_user
                                , $r_host
                                , $r_db
                                , $r_command
                                , $r_time
                                , $r_state
                                , $r_info
                                , $reason
                                ) ) {
        echo json_encode( ['result'=>'Unable to log kill data. Kill failed. ' . $log_stmt->error] ) . "\n";
        exit(1);
    }
    if ( ! $log_stmt->execute() ) {
        echo json_encode( ['result'=>'Unable to log kill data. Kill failed. ' . $log_stmt->error] ) . "\n";
        exit(1);
    }
    if ( ! $log_stmt->close() ) {
        echo json_encode( ['result'=>'Unable to log kill data. Kill failed. ' . $log_stmt->error] ) . "\n";
        exit(1);
    }
    // Kill the query
    //
    // For Aurora or RDS, use this statement instead...
    // $stmt = $dbh->prepare( "CALL mysql.rds_kill(?)" );
    $stmt = $dbh->prepare( "KILL ?" );
    if ( FALSE === $stmt ) {
        echo json_encode( ['result'=>'Prepare kill query failed. ' . $stmt->error] ) . "\n" ;
        exit(1);
    }
    if ( ! $stmt->bind_param( "i", $r_id ) ) {
        echo json_encode( ['result'=>'Bind kill query failed. ' . $stmt->error] ) . "\n" ;
        exit(1);
    }
    if ( ! $stmt->execute() ) {
        echo json_encode( ['result'=>'Execute kill query failed. ' . $stmt->error] ) . "\n" ;
        exit(1);
    }
    if ( ! $stmt->close() ) {
        echo json_encode( ['result'=>'Close kill query failed. ' . $stmt->error] ) . "\n" ;
        exit(1);
    }
    // Report back to the user that "Query 123 was killed. This has been logged."
    echo json_encode( ['result'=>'Query ' . $r_id . ' has been killed. This has been logged.'] ) . "\n";
    exit(0);
}
catch ( DaoException $e ) {
    echo json_encode( ['result'=>"Error connecting to database: " . $e->getMessage()] ) . "\n";
    exit(1);
}
// SHOULD NEVER GET HERE!!!
echo json_encode( ['result'=>"Made it here! (this is a bad thing)\n"] ) . "\n";
