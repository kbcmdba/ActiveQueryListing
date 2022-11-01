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

namespace com\kbcmdba\aql ;

session_start() ;

if ( ! isset( $_SESSION[ 'user' ] ) ) {
    header( "Location: login.php" ) ;
    die() ;
}

require('vendor/autoload.php');
require('utility.php');

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;
use com\kbcmdba\aql\Libs\Exceptions\DaoException;
use com\kbcmdba\aql\Libs\Tools ;
use com\kbcmdba\aql\Libs\WebPage ;

// ///////////////////////////////////////////////////////////////////////////

function checkIs1or0( $value, $errmsg, &$errors ) {
  if ( ( $value !== "1" ) && ( $value !== "0" ) ) {
      $errors .= $errmsg . " needs to be 1 or 0 (yes or no) (got $value)<br />\n";
  }
}

// ///////////////////////////////////////////////////////////////////////////

function checkIsNumeric( $value, $errmsg, &$errors ) {
  if ( ! Tools::isNumeric( $value ) ) {
      $errors .= $errmsg . " (got $value)<br />\n";
  }
}

// ///////////////////////////////////////////////////////////////////////////

$page = new WebPage( 'AQL: Manage Hosts' ) ;

// validate input
$errors = '' ;
$action = Tools::param( 'action' ) ;
$hostId = Tools::param( 'hostId' ) ;
$hostName = Tools::param( 'hostName' ) ;
$portNumber = Tools::param( 'portNumber') ;
$description = Tools::param( 'description' ) ;
$shouldMonitor = Tools::param( 'shouldMonitor' ) ;
$shouldBackup = Tools::param( 'shouldBackup' ) ;
$revenueImpacting = Tools::param('revenueImpacting' ) ;
$decommissioned = Tools::param( 'decommissioned' ) ;
$alertCritSecs = Tools::param( 'alertCritSecs' ) ;
$alertWarnSecs = Tools::param( 'alertWarnSecs' ) ;
$alertInfoSecs = Tools::param( 'alertInfoSecs' ) ;
$alertLowSecs = Tools::param( 'alertLowSecs' ) ;

if (  ( ( 'Update' === $action ) || ( 'Delete' === $action ) )
     && ! Tools::isNumeric( $hostId )
   ) {
    $errors .= "Invalid ID\n" ;
}
if ( 'Delete' !== $action ) {
    if ( Tools::isNullOrEmptyString( $hostName ) ) {
        $errors .= "Host Name cannot be empty.<br />\n" ;
    }  
    checkIsNumeric( $portNumber, "Invalid Port Number.\n", $errors ) ;
    checkIs1or0( $shouldMonitor, "Should Monitor", $errors ) ;
    checkIs1or0( $shouldBackup, "Should Backup", $errors ) ;
    checkIs1or0( $revenueImpacting, "Revenue Impacting", $errors ) ;
    checkIs1or0( $decommissioned, "Decommissioned", $errors ) ;
    checkIsNumeric( $alertCritSecs, "Alert Seconds: Critical", $errors ) ;
    checkIsNumeric( $alertWarnSecs, "Alert Seconds: Warning", $errors ) ;
    checkIsNumeric( $alertInfoSecs, "Alert Seconds: Info", $errors ) ;
    checkIsNumeric( $alertLowSecs, "Alert Seconds: Low", $errors ) ;  
}
$body = "<h2>AQL: Manage Hosts</h2>\n"
      . "<a href=\"index.php\">ActiveQueryListing</a>\n"
      . " | <a href=\"./manageHosts.php\">Manage Hosts</a>\n"
      . " | <a href=\"./manageGroups.php\">Manage Groups</a><p />\n"
      ;
if ( ( '' != $errors ) && ( '' != $action ) ) {
    $page->setBody( $body . $errors ) ;
    $page->displayPage() ;
    exit( 0 ) ;
}

// react accordingly
$dbc = new DBConnection();
$dbh = $dbc->getConnection();
$dbh->set_charset('utf8');
switch ( $action ) {
  case '':
    // Nothing to see here.
    break;
  case 'Add':
    $body .= 'Add - ' ;
    $sql = 'INSERT INTO host SET hostname = ?, port_number = ?'
         . ', description = ?, should_monitor = ?, should_backup = ?'
         . ', revenue_impacting = ?, decommissioned = ?'
         . ', alert_crit_secs = ?, alert_warn_secs = ?'
         . ', alert_info_secs = ?, alert_low_secs = ?'
         . ', created = NOW(), updated = NOW(), last_audited = NOW()'
         ;
    $stmt = $dbh->prepare( $sql ) ;
    $stmt->bind_param( 'sisiiiiiiii'
                     , $hostName, $portNumber, $description, $shouldMonitor
                     , $shouldBackup, $revenueImpacting, $decommissioned
                     , $alertCritSecs, $alertWarnSecs, $alertInfoSecs, $alertLowSecs
                     ) ;
    $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
    break ;
  case 'Update':
    $body .= 'Update - ' ;
    $sql = 'UPDATE host SET hostname = ?, port_number = ?'
         . ', description = ?, should_monitor = ?, should_backup = ?'
         . ', revenue_impacting = ?, decommissioned = ?'
         . ', alert_crit_secs = ?, alert_warn_secs = ?'
         . ', alert_info_secs = ?, alert_low_secs = ?'
         . ', updated = NOW(), last_audited = NOW()'
         . ' WHERE host_id = ?'
         ;
    $stmt = $dbh->prepare( $sql ) ;
    $stmt->bind_param( 'sisiiiiiiiii'
                     , $hostName, $portNumber, $description, $shouldMonitor
                     , $shouldBackup, $revenueImpacting, $decommissioned
                     , $alertCritSecs, $alertWarnSecs, $alertInfoSecs, $alertLowSecs
                     , $hostId
                     ) ;
    $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
    break ;
  case 'Delete':
    $body .= 'Delete - ' ;
    $sql = 'DELETE FROM host WHERE host_id = ?' ;
    $stmt = $dbh->prepare( $sql ) ;
    $stmt->bind_param( 'i', $hostId ) ;
    $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
    break ;
  default:
    $page->setBody( 'Huh?' ) ;
    $page->displayPage() ;
    exit( 0 ) ;
}

$allHostsQuery = <<<SQL
SELECT host_id
     , hostname
     , port_number
     , description
     , should_monitor
     , should_backup
     , revenue_impacting
     , decommissioned
     , alert_crit_secs
     , alert_warn_secs
     , alert_info_secs
     , alert_low_secs
  FROM aql_db.host
 ORDER BY decommissioned DESC, hostname ASC, port_number ASC
 
SQL;
$body .= <<<HTML
<table border=1 cellspacing=0 cellpadding=2 class="tablesorter" width="100%">
  <thead>
    <tr>
      <th rowspan="2">Actions</th>
      <th rowspan="2">Host ID</th>
      <th rowspan="2">Host Name</th>
      <th rowspan="2">Port</th>
      <th rowspan="2">Description</th>
      <th rowspan="2">Should Monitor</th>
      <th rowspan="2">Should Backup</th>
      <th rowspan="2">Revenue Impacting</th>
      <th rowspan="2">Decommissioned</th>
      <th colspan="4">Alert Seconds</th>
    </tr>
    <tr>
      <th>Critical</th>
      <th>Warn</th>
      <th>Info</th>
      <th>Low</th>
    </tr>
  </thead>
  <tbody>

HTML;
// function plainCell( $data ) { return "<td>$data</td>" } ;
// $cb = function ($fn) { return $fn; };
try {
    $result = $dbh->query( $allHostsQuery );
    if ( ! $result ) {
        throw new \ErrorException( "Query failed: $allHostsQuery\n Error: " . $dbh->error );
    }
    while ( $row = $result->fetch_row() ) {
        $body .= "      <tr>"
              .  "<td style=\"text-align: center\">"
              .  "<button type=\"submit\" onclick=\"fillHostForm("
              .  $row[0] . ", '"
              .  $row[1] . "', "
              .  $row[2] . ", '"
              .  $row[3] . "', "
              .  $row[4] . ", "
              .  $row[5] . ", "
              .  $row[6] . ", "
              .  $row[7] . ", "
              .  $row[8] . ", "
              .  $row[9] . ", "
              .  $row[10] . ", "
              .  $row[11]
              .  "); return(false);\">Fill Host Form</button>"
              .  "</td>"
              ;
        for ( $i = 0 ; $i < 12 ; $i++ ) {
            $body .= "<td>{$row[$i]}</td>" ;
        }
        $body .= "</tr>\n" ;
    }
    $body .= <<<HTML
  </tbody>
</table>

<p></p>

<form id="AddUpdateDeleteHostForm">
  <table id="AddUpdateDeleteHostTable" border=1 cellspacing=0 cellpadding=2>
    <caption>Host Form</caption>
    <tr><th colspan="2">ID</th><td><input type="number" id="hostId" name="hostId" readonly="readonly" value="" size=5 /></td></tr>
    <tr><th colspan="2">Host Name</th><td><input type="text" id="hostName" name="hostName" size="32" value="" /></td></tr>
    <tr><th colspan="2">Port Number</th><td><input type="number" id="portNumber" name="portNumber" size="5" value="3306" /></td></tr>
    <tr><th colspan="2">Description</th><td><textarea id="description" name="description" rows="4" cols="60"></textarea></td></tr>
    <tr><th colspan="2">Should Monitor</th><td><select id="shouldMonitor" name="shouldMonitor"><option value="1" selected="selected">Yes</option><option value="0">No</option></select></td></tr>
    <tr><th colspan="2">Should Backup</th><td><select id="shouldBackup" name="shouldBackup"><option value="1" selected="selected">Yes</option><option value="0">No</option></select></td></tr>
    <tr><th colspan="2">Revenue Impacting</th><td><select id="revenueImpacting" name="revenueImpacting"><option value="1" selected="selected">Yes</option><option value="0">No</option></select></td></tr>
    <tr><th colspan="2">Decommissioned</th><td><select id="decommissioned" name="decommissioned"><option value="1">Yes</option><option value="0" selected="selected">No</option></select></td></tr>
    <tr><th rowspan="4">Alert Seconds</th><th>Critical</th><td><input type="number" id="alertCritSecs" name="alertCritSecs" size="3" value="" /></td></tr>
    <tr><th>Warning</th><td><input type="number" id="alertWarnSecs" name="alertWarnSecs" size="3" value="" /></td></tr>
    <tr><th>Info</th><td><input type="number" id="alertInfoSecs" name="alertInfoSecs" size="3" value="" /></td></tr>
    <tr><th>Low</th><td><input type="number" id="alertLowSecs" name="alertLowSecs" size="3" value="" /></td></tr>
    </tr>
  </table>
  <input type="submit" name="action" value="Add"> &nbsp;
  <input type="submit" name="action" value="Update"> &nbsp;
  <input type="submit" name="action" value="Delete"> &nbsp;
</form>

HTML;
    $page->setBody( $body ) ;
}
catch (DaoException $e) {
  $page->appendBody( "<pre>Error interacting with the database\n\n" . $e->getMessage() . "\n</pre>\n" ) ;
}
$page->displayPage() ;
