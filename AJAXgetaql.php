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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\aql ;

require_once 'vendor/autoload.php';

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\Tools ;

header('Content-type: application/json') ;
header('Access-Control-Allow-Origin: *') ;
header('Expires: Thu, 01 Mar 2018 00:00:00 GMT') ;
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0') ;
header('Cache-Control: post-check=0, pre-check=0', false) ;
header('Pragma: no-cache') ;

$overviewData = [
    'aQPS'            => -1
  , 'blank'           => 0
  , 'duplicate'       => 0
  , 'level0'          => 0
  , 'level1'          => 0
  , 'level2'          => 0
  , 'level3'          => 0
  , 'level4'          => 0
  , 'level9'          => 0
  , 'longest_running' => -1
  , 'ro'              => 0
  , 'rw'              => 0
  , 'similar'         => 0
  , 'threads'         => 0
  , 'time'            => 0
  , 'unique'          => 0
  , 'uptime'          => 0
  , 'version'         => ''
];
$queries = [] ;
$safeQueries = [] ;
$slaveData = [] ;
$longestRunning = -1 ;

try {
    $config        = new Config() ;
    $roQueryPart   = $config->getRoQueryPart() ;
    $debug         = Tools::param('debug') === "1" ;
    $hostname      = Tools::param('hostname') ;
    $alertCritSecs = Tools::param('alertCritSecs') ;
    $alertWarnSecs = Tools::param('alertWarnSecs') ;
    $alertInfoSecs = Tools::param('alertInfoSecs') ;
    $alertLowSecs  = Tools::param('alertLowSecs') ;
    $dbc           = new DBConnection('process', $hostname) ;
    $dbh           = $dbc->getConnection() ;
    $outputList    = [] ;
    $notIn         = "( 'Sleep', 'Daemon', 'Binlog Dump', 'Slave_IO', 'Slave_SQL', 'Slave_worker' )" ;
    $notInState    = "( 'Waiting for master to send event', 'Slave has read all relay log; waiting for more updates' )" ;
    $debugComment  = ( $debug ) ? '-- ' : '' ;
    $globalStatusDb = $config->getGlobalStatusDb() ;
    $aQuery        = <<<SQL
SELECT Q / U AS aQPS, VERSION(), U
  FROM ( SELECT variable_value AS Q FROM $globalStatusDb.global_status WHERE variable_name = 'Questions' ) AS A,
       ( SELECT variable_value AS U FROM $globalStatusDb.global_status WHERE variable_name = 'Uptime' ) AS B
SQL;
    $aResult    = $dbh->query( $aQuery ) ;
    if ( $aResult === false ) {
        throw new \ErrorException( "Error running query: $aQuery (" . $dbh->error . ")\n" ) ;
    }
    $row = $aResult->fetch_row() ;
    $overviewData[ 'aQPS' ] = $row[ 0 ] ;
    $overviewData[ 'version' ] = $row[ 1 ] ;
    $overviewData[ 'uptime' ] = $row[ 2 ] ;
    $aResult->close() ;
    $showSlaveStatement = $config->getShowSlaveStatement() ;
    $version = $overviewData[ 'version' ] ;
    switch ( true ) {
        case preg_match( '/^10\.[2-9]\..*-MariaDB.*/', $version ) === 1:
            $showSlaveStatement = 'SHOW ALL SLAVES STATUS' ;
            $roQueryPart = '@@global.read_only OR @@global.innodb_read_only' ;
            break ;
        case preg_match( '/^[345]\..*$/', $version ) === 1:
            $showSlaveStatement = 'SHOW SLAVE STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
        case preg_match( '/^[8]\..*$/', $version ) === 1:
            $showSlaveStatement = 'SHOW REPLICA STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
    } ;
    $processQuery  = <<<SQL
SELECT id
     , user
     , host
     , db
     , command
     , time
     , state
     , info
     , $roQueryPart as read_only
  FROM INFORMATION_SCHEMA.PROCESSLIST
 WHERE 1 = 1
 $debugComment  AND COMMAND NOT IN $notIn
 $debugComment  AND STATE NOT IN $notInState
 $debugComment  AND id <> CONNECTION_ID()
 ORDER BY time DESC

SQL;
    $processResult = $dbh->query($processQuery) ;
    if ( $processResult === false ) {
        throw new \ErrorException( "Error running query: $processQuery (" . $dbh->error . ")\n" ) ;
    }
    while ($row = $processResult->fetch_row()) {
        $overviewData[ 'threads' ] ++ ;
        $dupeState    = '' ;
        $pid          = $row[ 0 ] ;
        $uid          = $row[ 1 ] ;
        $host         = $row[ 2 ] ;
        $db           = $row[ 3 ] ;
        $command      = $row[ 4 ] ;
        $time         = $row[ 5 ] ;
        $friendlyTime = Tools::friendlyTime( $time ) ;
        $state        = $row[ 6 ] ;
        $info         = $row[ 7 ] ;
        $safeInfo     = Tools::makeQuotedStringPIISafe( $info ) ;
        if ( isset($info) && ($info !== '') ) {
            if ( ( $command === 'Query' ) && ( $longestRunning < $time ) ) {
                $longestRunning = $time ;
            }
            if ( isset( $queries[ $info ] ) ) {
                $dupeState = 'Duplicate' ;
                $overviewData[ 'duplicate' ] ++ ;
            }
            elseif ( isset( $safeQueries[ $safeInfo ] ) ) {
                $dupeState = 'Similar' ;
                $overviewData[ 'similar' ] ++ ;
            }
            else {
                $dupeState = 'Unique' ;
                $overviewData[ 'unique' ] ++ ;
            }
        }
        else {
            $dupeState = 'Blank' ;
            $overviewData[ 'blank' ] ++ ;
        }
        $queries[ $info ] = 1 ;
        $safeQueries[ $safeInfo ] = 1 ;
        $safeUrl = urlencode( $safeInfo ) ;
        $readOnly = $row[ 8 ] ;
        $overviewData[ 'time' ] += $time ;
        $overviewData[ ( $readOnly ) ? 'ro' : 'rw' ] ++ ;
        switch (true) {
            case $time >= $alertCritSecs:
                $level = 4 ;
                break ;
            case $time >= $alertWarnSecs:
                $level = 3 ;
                break ;
            case $time >= $alertInfoSecs:
                $level = 2 ;
                break ;
            case $time <= $alertLowSecs:
                $level = 0 ;
                break ;
            default:
                $level = 1 ;
        }
        $overviewData[ "level$level" ] ++ ;
        $safeInfoJS   = urlencode( $safeInfo ) ;
        $outputList[] = [
            'level'        => $level
          , 'time'         => $time
          , 'friendlyTime' => $friendlyTime
          , 'server'       => $hostname
          , 'id'           => $pid
          , 'user'         => $uid
          , 'host'         => $host
          , 'db'           => $db
          , 'command'      => $command
          , 'state'        => $state
          , 'dupeState'    => $dupeState
          , 'info'         => htmlspecialchars( $safeInfo )
          , 'actions'      => "<button type=\"button\" onclick=\"killProcOnHost( '$hostname', $pid, '$uid', '$host', '$db', '$command', $time, '$state', '$safeInfoJS' ) ; return false ;\">Kill Thread</button>"
                            . "<button type=\"button\" onclick=\"fileIssue( '$hostname', '$readOnly', '$host', '$uid', '$db', $time, '$safeUrl' ) ; return false ;\">File Issue</button>"
          , 'readOnly'    => $readOnly
        ] ;
    }
    $processResult->close() ;
    $slaveResult = $dbh->query( $showSlaveStatement ) ;
    if ( $slaveResult === false ) {
        throw new \ErrorException( "Error running query: $processQuery (" . $dbh->error . ")\n" ) ;
    }
    while ($row = $slaveResult->fetch_assoc()) {
        $thisResult = array() ;
        foreach (['Connection_name', 'Master_Host', 'Master_Port', 'Slave_IO_Running'
                 , 'Slave_SQL_Running', 'Seconds_Behind_Master', 'Last_IO_Error'
                 , 'Last_SQL_Error'] as $i) {
          $thisResult[ $i ] = $row[ $i ] ;
        }
        $slaveData[] = $thisResult ;
    }
    $slaveResult->close() ;
}
catch (\Exception $e) {
    echo json_encode([ 'hostname' => $hostname, 'error_output' => $e->getMessage() ]) ;
    exit(1) ;
}
$overviewData[ 'longest_running' ] = $longestRunning ;
echo json_encode([ 'hostname'     => $hostname
                 , 'result'       => $outputList
                 , 'overviewData' => $overviewData
                 , 'slaveData'    => $slaveData
                 ]) . "\n" ;
