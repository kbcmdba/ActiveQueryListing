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
  , 'blocked'         => 0
  , 'blocking'        => 0
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
    $notInCommand  = "( 'Binlog Dump'"
                   . ", 'Binlog Dump GTID'"
                   . ", 'Daemon'"
                   . ", 'Group Replicatio'"
                   . ", 'Slave_IO'"
                   . ", 'Slave_SQL'"
                   . ", 'Slave_worker'"
                   . ", 'Sleep'"
                   . ")" ;
    $notInState    = "( 'Applying batch of row changes (update)'"
                   . ", 'Connection delegated to Group Replication'"
                   . ", 'Queueing master event to the relay log'"
                   . ", 'Queueing source event to the relay log'"
                   . ", 'Replica has read all relay log; waiting for more updates'"
                   . ", 'Slave has read all relay log; waiting for more updates'"
                   . ", 'Waiting for an event from Coordinator'"
                   . ", 'Waiting for master to send event'"
                   . ", 'Waiting for source to send event'"
                   . ", 'reading event from relay log'"
                   . ", 'waiting for handler commit'"
                   . ")" ;
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
    $replica_labels = [ 'Connection_name' => 'Connection_name'
                      , 'Master_Host' => 'Master_Host'
                      , 'Master_Port' => 'Master_Port'
                      , 'Slave_IO_Running' => 'Slave_IO_Running'
                      , 'Slave_SQL_Running' => 'Slave_SQL_Running'
                      , 'Seconds_Behind_Master' => 'Seconds_Behind_Master'
                      , 'Last_IO_Error' => 'Last_IO_Error'
                      , 'Last_SQL_Error' => 'Last_SQL_Error'
                      ] ;
    switch ( true ) {
        case preg_match( '/^10\.[2-9]\..*-MariaDB.*/', $version ) === 1:
            $showSlaveStatement = 'SHOW ALL SLAVES STATUS' ;
            $roQueryPart = '@@global.read_only OR @@global.innodb_read_only' ;
            break ;
        case preg_match( '/^[345]\..*$/', $version ) === 1:
            $showSlaveStatement = 'SHOW SLAVE STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
        case preg_match( '/^[8]\.0\.21.*$/', $version ) === 1:
            $showSlaveStatement = 'SHOW SLAVE STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
        case preg_match( '/^[8]\..*$/', $version ) === 1:
            $replica_labels = [ 'Connection_name' => 'Channel_Name'
                              , 'Master_Host' => 'Source_Host'
                              , 'Master_Port' => 'Source_Port'
                              , 'Slave_IO_Running' => 'Replica_IO_Running'
                              , 'Slave_SQL_Running' => 'Replica_SQL_Running'
                              , 'Seconds_Behind_Master' => 'Seconds_Behind_Source'
                              , 'Last_IO_Error' => 'Last_IO_Error'
                              , 'Last_SQL_Error' => 'Last_SQL_Error'
                              ] ;
            $showSlaveStatement = 'SHOW REPLICA STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
    } ;

    // Lock detection - build map of blocked/blocking threads
    $lockWaitData = [] ;
    try {
        // Determine which lock query to use based on MySQL version
        $useMysql8LockQuery = preg_match( '/^[8]\./', $version ) === 1
                           && preg_match( '/^[8]\.0\.[01][^0-9]/', $version ) !== 1 ;

        if ( $useMysql8LockQuery ) {
            // MySQL 8.0.2+ uses performance_schema
            $lockQuery = <<<SQL
SELECT
    r.trx_mysql_thread_id AS waiting_thread_id,
    COALESCE(TIMESTAMPDIFF(SECOND, r.trx_wait_started, NOW()), 0) AS wait_seconds,
    b.trx_mysql_thread_id AS blocking_thread_id,
    CONCAT(dl.OBJECT_SCHEMA, '.', dl.OBJECT_NAME) AS locked_table,
    dl.LOCK_MODE AS lock_mode
FROM performance_schema.data_lock_waits dlw
JOIN performance_schema.data_locks dl ON dlw.REQUESTING_ENGINE_LOCK_ID = dl.ENGINE_LOCK_ID
JOIN information_schema.INNODB_TRX b ON b.trx_id = dlw.BLOCKING_ENGINE_TRANSACTION_ID
JOIN information_schema.INNODB_TRX r ON r.trx_id = dlw.REQUESTING_ENGINE_TRANSACTION_ID
SQL;
        } else {
            // MySQL 5.x, MariaDB, MySQL 8.0.0-8.0.1 use information_schema
            $lockQuery = <<<SQL
SELECT
    r.trx_mysql_thread_id AS waiting_thread_id,
    COALESCE(TIMESTAMPDIFF(SECOND, r.trx_wait_started, NOW()), 0) AS wait_seconds,
    b.trx_mysql_thread_id AS blocking_thread_id,
    l.lock_table AS locked_table,
    l.lock_mode AS lock_mode
FROM information_schema.INNODB_LOCK_WAITS w
JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id
JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id
JOIN information_schema.INNODB_LOCKS l ON l.lock_id = w.requested_lock_id
SQL;
        }

        $lockResult = $dbh->query( $lockQuery ) ;
        if ( $lockResult !== false ) {
            while ( $row = $lockResult->fetch_assoc() ) {
                $waitingThreadId = (int) $row['waiting_thread_id'] ;
                $blockingThreadId = (int) $row['blocking_thread_id'] ;

                // Initialize or update waiting thread entry
                if ( !isset( $lockWaitData[$waitingThreadId] ) ) {
                    $lockWaitData[$waitingThreadId] = [
                        'isBlocked'   => true,
                        'isBlocking'  => false,
                        'waitSeconds' => (int) $row['wait_seconds'],
                        'lockMode'    => $row['lock_mode'],
                        'lockedTable' => $row['locked_table'],
                        'blockedBy'   => [],
                        'blocking'    => []
                    ] ;
                }
                $lockWaitData[$waitingThreadId]['blockedBy'][] = $blockingThreadId ;

                // Initialize or update blocking thread entry
                if ( !isset( $lockWaitData[$blockingThreadId] ) ) {
                    $lockWaitData[$blockingThreadId] = [
                        'isBlocked'   => false,
                        'isBlocking'  => true,
                        'waitSeconds' => 0,
                        'lockMode'    => null,
                        'lockedTable' => null,
                        'blockedBy'   => [],
                        'blocking'    => []
                    ] ;
                }
                $lockWaitData[$blockingThreadId]['isBlocking'] = true ;
                $lockWaitData[$blockingThreadId]['blocking'][] = $waitingThreadId ;
            }
            $lockResult->close() ;
        }

        // Also check for table-level lock waits (MyISAM, metadata locks)
        $tableLockQuery = <<<SQL
SELECT id AS waiting_thread_id, time AS wait_seconds, state
FROM INFORMATION_SCHEMA.PROCESSLIST
WHERE state IN (
    'Waiting for table level lock',
    'Waiting for table metadata lock',
    'Locked',
    'Waiting for table flush'
)
SQL;
        $tableLockResult = $dbh->query( $tableLockQuery ) ;
        if ( $tableLockResult !== false ) {
            while ( $row = $tableLockResult->fetch_assoc() ) {
                $waitingThreadId = (int) $row['waiting_thread_id'] ;
                if ( !isset( $lockWaitData[$waitingThreadId] ) ) {
                    $lockWaitData[$waitingThreadId] = [
                        'isBlocked'   => true,
                        'isBlocking'  => false,
                        'waitSeconds' => (int) $row['wait_seconds'],
                        'lockMode'    => 'TABLE',
                        'lockedTable' => $row['state'],
                        'blockedBy'   => [],
                        'blocking'    => []
                    ] ;
                }
            }
            $tableLockResult->close() ;
        }
    } catch ( \Exception $e ) {
        // Lock detection is supplementary - don't fail if it errors
        // Just continue with empty $lockWaitData
    }

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
 $debugComment  AND COMMAND NOT IN $notInCommand
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

        // Check for lock wait status and elevate level if blocked
        $blockInfo = isset( $lockWaitData[$pid] ) ? $lockWaitData[$pid] : null ;
        if ( $blockInfo !== null ) {
            if ( !empty( $blockInfo['isBlocked'] ) ) {
                $overviewData['blocked'] ++ ;
                // Elevate level based on wait time
                if ( $blockInfo['waitSeconds'] > 60 ) {
                    $level = 4 ;  // Critical if blocked > 60s
                } elseif ( $blockInfo['waitSeconds'] > 30 ) {
                    $level = max( $level, 3 ) ;  // Warning if blocked > 30s
                } else {
                    $level = max( $level, 2 ) ;  // Info if blocked
                }
            }
            if ( !empty( $blockInfo['isBlocking'] ) ) {
                $overviewData['blocking'] ++ ;
            }
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
          , 'readOnly'     => $readOnly
          , 'blockInfo'    => $blockInfo
        ] ;
    }
    $processResult->close() ;
    $slaveResult = $dbh->query( $showSlaveStatement ) ;
    if ( $slaveResult === false ) {
        throw new \ErrorException( "Error running query: $processQuery (" . $dbh->error . ")\n" ) ;
    }
    while ($row = $slaveResult->fetch_assoc()) {
        $thisResult = array() ;
        foreach ($replica_labels as $k => $v) {
            $thisResult[ $k ] = $row[ $v ] ?? '' ;
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
