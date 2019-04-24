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

require_once('vendor/autoload.php') ;

use com\kbcmdba\ActiveQueryListing\Libs\Config ;
use com\kbcmdba\ActiveQueryListing\Libs\DBConnection ;
use com\kbcmdba\ActiveQueryListing\Libs\Tools ;

header('Content-type: application/json') ;
header('Access-Control-Allow-Origin: *') ;
header('Expires: Thu, 01 Mar 2018 00:00:00 GMT') ;
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0') ;
header('Cache-Control: post-check=0, pre-check=0', false) ;
header('Pragma: no-cache') ;

$config      = new Config() ;
$issueUrl    = $config->getIssueUrl() ;
$dupeList    = [] ;
$similarList = [] ;
$summaryData = [ 'Sleep'      => 0
               , 'Daemon'     => 0
               , 'Sessions'   => 1
               , 'Dupe'       => 0
               , 'Similar'    => 0
               , 'Unique'     => 0
               , 'Error'      => 0
               , 'Level4'     => 0
               , 'Level3'     => 0
               , 'Level2'     => 0
               , 'Level1'     => 0
               , 'Level0'     => 0
               , 'Ro'         => 0
               , 'Rw'         => 0
               , 'ActiveTime' => 0
               ] ;
try {
    $debug         = Tools::param('debug') === "1" ;
    $kioskMode     = Tools::param('mode') === 'Kiosk' ;
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
     , @@global.innodb_read_only as read_only
  FROM INFORMATION_SCHEMA.PROCESSLIST
 WHERE 1 = 1

SQL;
    if ( ! $debug ) {
        $processQuery .= "AND id <> connection_id()\n" ;
    }
    $processQuery .= "ORDER BY time DESC\n" ;
    $outputList = [] ;
    $result = $dbh->query($processQuery) ;
    while ($row = $result->fetch_row()) {
        $summaryData[ 'Sessions' ]++ ;
        $pid      = $row[ 0 ] ;
        $uid      = $row[ 1 ] ;
        $host     = $row[ 2 ] ;
        $db       = $row[ 3 ] ;
        $command  = $row[ 4 ] ;
        $time     = $row[ 5 ] ;
        $state    = $row[ 6 ] ;
        $rawInfo  = isset( $row[ 7 ] ) ? $row[ 7 ] : '' ;
        $readOnly = $row[ 8 ] ;
        $summaryData[ 'Rw' ] = 1 - $readOnly ;
        $summaryData[ 'Ro' ] = $readOnly ;
        if ( ( 'Sleep' === $command ) || ( 'Daemon' === $command ) ) {
            $summaryData[ $command ]++ ;
            if ( ! $debug ) {
                continue ;
            }
        }
        $summaryData[ 'ActiveTime' ] += $time ;
        $safeInfo = Tools::makeQuotedStringPIISafe( $rawInfo ) ;
        $dupe = 'Unique' ;
        if  ( isset( $similarList[ "${db}:${safeInfo}" ] )
           && ( 1 === $similarList[ "${db}:${safeInfo}" ] )
           ) {
            $dupe = 'Similar' ;
        }
        else {
            $similarList[ "${db}:${safeInfo}" ] = 1 ;
        }
        if  ( isset($dupeList[ "${db}:${rawInfo}" ] )
           && ( 1 === $dupeList[ "${db}:${rawInfo}" ] )
            ) {
            $dupe = 'Dupe' ;
        }
        else {
            $dupeList[ "${db}:${rawInfo}" ] = 1 ;
        }
        $summaryData[ $dupe ]++ ;
        switch ( true ) {
            case $time >= $alertCritSecs:
                $level = 4 ;
                $summaryData[ 'Level4' ]++ ;
                break ;
            case $time >= $alertWarnSecs:
                $level = 3 ;
                $summaryData[ 'Level3' ]++ ;
                break ;
            case $time >= $alertInfoSecs:
                $level = 2 ;
                $summaryData[ 'Level2' ]++ ;
                break ;
            case $time <= $alertLowSecs:
                $level = 0 ;
                $summaryData[ 'Level0' ]++ ;
                break ;
            default:
                $level = 1 ;
                $summaryData[ 'Level1' ]++ ;
        }
        $query_summary = urlencode('MySQL Query to Investigate');
        $description  = urlencode("||Host|$hostname||\n||Read-Only|$readOnly||\n||User|$uid||\n||DB|$db||\n||Time|$time||\n\nQuery:\n\n{code:sql}\n$safeInfo\n{code}\n");
        $thisIssueUrl = str_replace('%SUMMARY%', $query_summary, $issueUrl);
        $thisIssueUrl = str_replace('%DESCRIPTION%', $description, $thisIssueUrl);
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
        $safeInfoJS = urlencode($safeInfo);
        $outputItem[ 'dupe'    ] = $dupe ;
        $outputItem[ 'info'    ] = htmlspecialchars( $safeInfo ) ;
        $outputItem[ 'actions' ] = "<button type=\"button\" onclick=\"killProcOnHost( '$hostname', $pid, '$uid', '$host', '$db', '$command', $time, '$state', '$safeInfoJS') ; return false ;\">Kill Thread</button>"
                                 . "<button type=\"button\" onclick=\"fileIssue('$thisIssueUrl')\">File Issue</button>"
                                 ;
        if ( $kioskMode ) {
            $outputItem[ 'actions' ] = '' ;
        }
        $outputItem[ 'readOnly' ] = $readOnly ;
        $outputList[] = $outputItem ;
    }
} catch (\Exception $e) {
    $summaryData[ 'Error' ]++ ;
    echo json_encode([ 'hostname' => $hostname, 'error_output' => $e->getMessage(), 'summary_data' => $summaryData ]) ;
    exit(1) ;
}
echo json_encode([ 'hostname' => $hostname, 'result' => $outputList, 'summary_data' => $summaryData ]) . "\n" ;
exit(0);
