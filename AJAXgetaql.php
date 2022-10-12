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

require_once('vendor/autoload.php') ;

header('Content-type: application/json') ;
header('Access-Control-Allow-Origin: *') ;
header('Expires: Thu, 01 Mar 2018 00:00:00 GMT') ;
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0') ;
header('Cache-Control: post-check=0, pre-check=0', false) ;
header('Pragma: no-cache') ;

$summaryData = [
    'blank'     => 0
  , 'duplicate' => 0
  , 'level0'    => 0
  , 'level1'    => 0
  , 'level2'    => 0
  , 'level3'    => 0
  , 'level4'    => 0
  , 'level9'    => 0
  , 'ro'        => 0
  , 'rw'        => 0
  , 'similar'   => 0
  , 'threads'   => 0
  , 'time'      => 0
  , 'unique'    => 0
];
$queries = [] ;
$safeQueries = [] ;

$hostData = [] ;
$slaveData = [] ;

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
    $notIn         = "( 'Sleep', 'Daemon', 'Binlog Dump'"
                   . ", 'Slave_IO', 'Slave_SQL', 'Slave_worker' )" ;
    $debugComment  = ( $debug ) ? '-- ' : '' ;
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
 $debugComment  AND id <> CONNECTION_ID()
 ORDER BY time DESC

SQL;
    $result = $dbh->query($processQuery) ;
    if ( $result === false ) {
        throw new \ErrorException( "Error running query: $processQuery (" . $dbh->error . ")\n" ) ;
    }
    while ($row = $result->fetch_row()) {
        $summaryData[ 'threads' ] ++ ;
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
            if ( isset( $queries[ $info ] ) ) {
                $dupeState = 'Duplicate' ;
                $summaryData[ 'duplicate' ] ++ ;
            }
            elseif ( isset( $safeQueries[ $safeInfo ] ) ) {
                $dupeState = 'Similar' ;
                $summaryData[ 'similar' ] ++ ;
            }
            else {
                $dupeState = 'Unique' ;
                $summaryData[ 'unique' ] ++ ;
            }
        }
        else {
            $dupeState = 'Blank' ;
            $summaryData[ 'blank' ] ++ ;
        }
        $queries[ $info ] = 1 ;
        $safeQueries[ $safeInfo ] = 1 ;
        $safeUrl = urlencode( $safeInfo ) ;
        $readOnly = $row[ 8 ] ;
        $summaryData[ 'time' ] += $time ;
        $summaryData[ ( $readOnly ) ? 'ro' : 'rw' ] ++ ;
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
        $summaryData[ "level$level" ] ++ ;
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
}
catch (\Exception $e) {
    echo json_encode([ 'hostname' => $hostname, 'error_output' => $e->getMessage() ]) ;
    exit(1) ;
}
echo json_encode([ 'hostname' => $hostname, 'result' => $outputList, 'summaryData' => $summaryData] ) . "\n" ;
