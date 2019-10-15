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

require_once('Libs/autoload.php') ;

header('Content-type: application/json') ;
header('Access-Control-Allow-Origin: *') ;
header('Expires: Thu, 01 Mar 2018 00:00:00 GMT') ;
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0') ;
header('Cache-Control: post-check=0, pre-check=0', false) ;
header('Pragma: no-cache') ;

try {
    $debug         = Tools::param('debug') === "1" ;
    $hostname      = Tools::param('hostname') ;
    $alertCritSecs = Tools::param('alertCritSecs') ;
    $alertWarnSecs = Tools::param('alertWarnSecs') ;
    $alertInfoSecs = Tools::param('alertInfoSecs') ;
    $alertLowSecs  = Tools::param('alertLowSecs') ;
    $dbc           = new DBConnection('process', $hostname) ;
    $dbh           = $dbc->getConnection() ;
    $processQuery  = <<<SQL
SELECT id
     , user
     , host
     , db
     , command
     , time
     , state
     , info
     , @@global.read_only as read_only
  FROM INFORMATION_SCHEMA.PROCESSLIST
 WHERE 1 = 1

SQL;
    $notIn = "( 'Sleep', 'Daemon', 'Binlog Dump', 'Slave_IO', 'Slave_SQL' )"
    if ( $debug ) {
        $processQuery .= "-- AND COMMAND NOT IN $notIn\n"
                      .  "-- AND id <> connection_id()\n"
                      ;
    }
    else {
        $processQuery .= "AND COMMAND NOT IN $notIn\n"
                      .  "AND id <> connection_id()\n"
                      ;
    }
    $processQuery .= "ORDER BY time DESC\n" ;
    $outputList = [] ;
    $result = $dbh->query($processQuery) ;
    while ($row = $result->fetch_row()) {
        $pid     = $row[ 0 ] ;
        $uid     = $row[ 1 ] ;
        $host    = $row[ 2 ] ;
        $db      = $row[ 3 ] ;
        $command = $row[ 4 ] ;
        $time    = $row[ 5 ] ;
        $state   = $row[ 6 ] ;
        $safeInfo = Tools::makeQuotedStringPIISafe($row[ 7 ]) ;
        $safeUrl = urlencode( $safeInfo ) ;
        $readOnly = $row[ 8 ] ;
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
        $outputItem = [] ;
        $outputItem[ 'level'   ] = $level ;
        $outputItem[ 'time'    ] = $time ;
        $outputItem[ 'server'  ] = $hostname ;
        $outputItem[ 'id'      ] = $pid ;
        $outputItem[ 'user'    ] = $uid ;
        $outputItem[ 'host'    ] = $host ;
        $outputItem[ 'db'      ] = $db ;
        $outputItem[ 'command' ] = $command ;
        $outputItem[ 'state'   ] = $state ;
        $outputItem[ 'info'    ] = htmlspecialchars( $safeInfo ) ;
        $outputItem[ 'actions' ] = "<button type=\"button\" onclick=\"killProcOnHost( '$hostname', $pid ) ; return false ;\">Kill Thread</button>"
                                 . "<button type=\"button\" onclick=\"fileIssue( '$hostname', '$readOnly', '$host', '$uid', '$db', $time, '$safeUrl' ) ; return false ;\">File Issue</button>"
                                 ;
        $outputItem[ 'readOnly' ] = $readOnly ;
        $outputList[] = $outputItem ;
    }
} catch (\Exception $e) {
    echo json_encode([ 'hostname' => $hostname, 'error_output' => $e->getMessage() ]) ;
    exit(1) ;
}
echo json_encode([ 'hostname' => $hostname, 'result' => $outputList ]) . "\n" ;
