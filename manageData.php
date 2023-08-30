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

session_start() ;

require( 'vendor/autoload.php' ) ;
require( 'utility.php' ) ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;
use com\kbcmdba\aql\Libs\Exceptions\DaoException;
use com\kbcmdba\aql\Libs\LDAP ;
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

function doLoginOrDie( $page ) {
    $loginPage = <<<HTML
<h2>Login</h2>
<div>
    <form method="post" action="manageData.php">
        <label for="user"><b>Username</b></label>
        <input type="text" name="user" required="required" />
        <label for="password"><b>Password</b></label>
        <input type="password" name="password" required="required" />
        <input type="submit" name="submit" value="submit" />
    </form> 
</div>

HTML;
    if ( ! Tools::isNullOrEmptyString( Tools::param( 'logout' ) ) ) {
        session_unset() ;
        session_destroy() ;
        $page->setBody( $loginPage );
        $page->displayPage() ;
        die() ;
    }
    if ( ! isset( $_SESSION[ 'AuthUser' ] ) || ( ! isset( $_SESSION[ 'AuthCanAccess' ] ) ) ) {
        if  ( !Tools::isNullOrEmptyString( Tools::post( 'user', null, 1 ) )
        && !Tools::isNullOrEmptyString( Tools::post( 'password', null, 1 ) )
            ) {
            if ( LDAP::authenticate( Tools::post( 'user', null, 1 ), Tools::post( 'password', null, 1 ) ) ) {
                return ; // User was successfully logged in.
            }
            echo "Login failed: Incorrect user name, password, or access.<br />" ;
        }
        $page->setBody( $loginPage );
        $page->displayPage() ;
        die() ;
    }
} // END OF doLoginOrDie

// ///////////////////////////////////////////////////////////////////////////

$page = new WebPage( 'AQL: Manage Data' ) ;
$page->setTop( "<h2>AQL: Manage Data</h2>\n"
             .  "<a href=\"index.php\">ActiveQueryListing</a>\n"
             .  " | <a href=\"./manageData.php?data=Hosts\">Manage Hosts</a>\n"
             .  " | <a href=\"./manageData.php?data=Groups\">Manage Groups</a>\n"
             .  " | <a href=\"./manageData.php?logout=logout\">Log out of Manage Data</a>\n"
             .  "<p />\n"
             ) ;
             // .  " | <a href=\"./manageData.php?data=Hosts\">Manage Hosts</a>\n"
doLoginOrDie( $page ) ;
switch ( Tools::param( 'data' ) ) {
    case 'Hosts':
        // validate input
        $errors = '' ;
        $action = Tools::param( 'action' ) ;
        $hostId = Tools::param( 'hostId' ) ;
        $hostName = Tools::param( 'hostName' ) ;
        $portNumber = Tools::param( 'portNumber') ;
        $description = Tools::param( 'description' ) ;
        $shouldMonitor = Tools::param( 'shouldMonitor' ) ;
        $shouldBackup = Tools::param( 'shouldBackup' ) ;
        $shouldSchemaspy = Tools::param( 'shouldSchemaspy' ) ;
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
            checkIs1or0( $shoulSchemaspy, "Should Schemaspy", $errors ) ;
            checkIs1or0( $revenueImpacting, "Revenue Impacting", $errors ) ;
            checkIs1or0( $decommissioned, "Decommissioned", $errors ) ;
            checkIsNumeric( $alertCritSecs, "Alert Seconds: Critical", $errors ) ;
            checkIsNumeric( $alertWarnSecs, "Alert Seconds: Warning", $errors ) ;
            checkIsNumeric( $alertInfoSecs, "Alert Seconds: Info", $errors ) ;
            checkIsNumeric( $alertLowSecs, "Alert Seconds: Low", $errors ) ;  
        }

        if ( ( '' != $errors ) && ( '' != $action ) ) {
            $page->setBody( $links . $body . $errors ) ;
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
                     . ', should_schemaspy = ?, revenue_impacting = ?, decommissioned = ?'
                     . ', alert_crit_secs = ?, alert_warn_secs = ?'
                     . ', alert_info_secs = ?, alert_low_secs = ?'
                     . ', created = NOW(), updated = NOW(), last_audited = NOW()'
                     ;
                $stmt = $dbh->prepare( $sql ) ;
                $stmt->bind_param( 'sisiiiiiiiii'
                                 , $hostName, $portNumber, $description, $shouldMonitor
                                 , $shouldBackup, $shouldSchemaspy, $revenueImpacting, $decommissioned
                                 , $alertCritSecs, $alertWarnSecs, $alertInfoSecs, $alertLowSecs
                                 ) ;
                $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
            break ;
            case 'Update':
                $body .= 'Update - ' ;
                $sql = 'UPDATE host SET hostname = ?, port_number = ?'
                     . ', description = ?, should_monitor = ?'
                     . ', should_backup = ?, should_schemaspy = ?'
                     . ', revenue_impacting = ?, decommissioned = ?'
                     . ', alert_crit_secs = ?, alert_warn_secs = ?'
                     . ', alert_info_secs = ?, alert_low_secs = ?'
                     . ', updated = NOW(), last_audited = NOW()'
                     . ' WHERE host_id = ?'
                     ;
                $stmt = $dbh->prepare( $sql ) ;
                $stmt->bind_param( 'sisiiiiiiiiii'
                                 , $hostName, $portNumber, $description, $shouldMonitor
                                 , $shouldBackup, $shouldSchemaspy, $revenueImpacting, $decommissioned
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
                $page->setBody( $links, 'Huh?' ) ;
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
     , should_schemaspy
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
      <th rowspan="2">Should Schemaspy</th>
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
                      .  $row[11] . ", "
                      .  $row[12]
                      .  "); return(false);\">Fill Host Form</button>"
                      .  "</td>"
                      ;
                for ( $i = 0 ; $i < 13 ; $i++ ) {
                    $body .= "<td>{$row[$i]}</td>" ;
                }
                $body .= "</tr>\n" ;
            }
            $body .= <<<HTML
  </tbody>
</table>

<p></p>

<form id="AddUpdateDeleteHostForm" action="manageData.php">
  <input type="hidden" name="data" value="Hosts">
  <table id="AddUpdateDeleteHostTable" border=1 cellspacing=0 cellpadding=2>
    <caption>Host Form</caption>
    <tr><th colspan="2">ID</th><td><input type="number" id="hostId" name="hostId" readonly="readonly" value="" size=5 /></td></tr>
    <tr><th colspan="2">Host Name</th><td><input type="text" id="hostName" name="hostName" size="32" value="" /></td></tr>
    <tr><th colspan="2">Port Number</th><td><input type="number" id="portNumber" name="portNumber" size="5" value="3306" /></td></tr>
    <tr><th colspan="2">Description</th><td><textarea id="description" name="description" rows="4" cols="60"></textarea></td></tr>
    <tr><th colspan="2">Should Monitor</th><td><select id="shouldMonitor" name="shouldMonitor"><option value="1" selected="selected">Yes</option><option value="0">No</option></select></td></tr>
    <tr><th colspan="2">Should Backup</th><td><select id="shouldBackup" name="shouldBackup"><option value="1" selected="selected">Yes</option><option value="0">No</option></select></td></tr>
    <tr><th colspan="2">Should Schemaspy</th><td><select id="shouldSchemaspy" name="shoulSchemaspy"><option value="1">Yes</option><option value="0" selected="selected">No</option></select></td></tr>
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
            $page->setBody( $links . $body ) ;
        }
        catch (DaoException $e) {
            $page->appendBody( "<pre>Error interacting with the database\n\n" . $e->getMessage() . "\n</pre>\n" ) ;
        }
        $page->displayPage() ;

        break ;
    case 'Groups':
        $body = $errors = '' ;
        $action = Tools::param( 'action' ) ;
        $groupId = Tools::param( 'groupId' ) ;
        $groupTag = Tools::param( 'groupTag' ) ;
        $shortDesc = Tools::param( 'shortDescription' ) ;
        $fullDesc = Tools::param( 'fullDescription' ) ;
        $groupSelection = Tools::params( 'groupSelect' ) ;

        if (  ( ( 'Update' === $action ) || ( 'Delete' === $action ) )
            && ! Tools::isNumeric( $groupId )
        ) {
            $errors .= "Invalid ID\n" ;
        }
        if ( ( 'Add' === $action ) || ( 'Update' === $action ) ) {
            if ( Tools::isNullOrEmptyString( $groupTag ) ) {
                $errors .= "Tag cannot be empty.<br />\n" ;
            }
            if ( strlen( $groupTag ) > 16 )  {
                $errors .= "Group Tag is too long.<br />\n" ;
            }
            if ( ! is_array( $groupSelection ) ) {
                // Someone is fiddling with the input.
                echo "Huh?\n" ; exit( 0 ) ;
            }
            $countIsValid = 0 ;
            // Check each of the $groupSelection array members
            foreach ( $groupSelection as $value ) {
                if ( ! Tools::isNumeric( $value ) ) {
                    // Someone is fiddling with the input.
                    echo "Huh?\n" ; exit( 1 ) ;
                }
                $countIsValid = 1 ;
            }
            if ( 0 === $countIsValid ) {
                $errors .= "Must specify at least one host in group.<br />\n" ;
            }
        }

        if ( $errors !== '' ) {
            $page->setBody( $links . $errors ) ;
            $page->displayPage() ;
            exit( 0 );
        }

        $dbc = new DBConnection() ;
        $dbh = $dbc->getConnection() ;
        $dbh->set_charset('utf8') ;
        // Handle group change
        switch ( $action ) {
            case '':
                // Nothing to see here.
                break ;
            case 'Add':
                $body .= 'Add - ' ;
                $sql = 'INSERT INTO host_group SET tag = ?'
                     . ', short_description = ?'
                     . ', full_description = ?'
                     . ', created = CURRENT_TIMESTAMP()'
                     . ', updated = CURRENT_TIMESTAMP()'
                     ;
                $stmt = $dbh->prepare( $sql ) ;
                $stmt->bind_param( 'sss', $groupTag, $shortDesc, $fullDesc ) ;
                $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
                // get new $groupId
                $groupId = $dbh->insert_id ;
                break ;
            case 'Update':
                $body .= 'Update - ' ;
                $sql = 'UPDATE host_group SET tag = ?'
                     . ', short_description = ?'
                     . ', full_description = ?'
                     . ', updated = CURRENT_TIMESTAMP()'
                     . 'WHERE host_group_id = ?'
                    ;
                $stmt = $dbh->prepare( $sql ) ;
                $stmt->bind_param( 'sssi', $groupTag, $shortDesc, $fullDesc, $groupId ) ;
                $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
                break ;
            case 'Delete':
                $body .= 'Delete - ' ;
                $sql = 'DELETE FROM host_group_map WHERE host_group_id = ?' ;
                $stmt = $dbh->prepare( $sql ) ;
                $stmt->bind_param( 'i', $groupId ) ;
                if ( ! $stmt->execute() ) {
                    echo "Conflicting update?<br />\n" ;
                    exit( 1 ) ;
                }
                $sql = 'DELETE FROM host_group WHERE host_group_id = ?' ;
                $stmt = $dbh->prepare( $sql ) ;
                $stmt->bind_param( 'i', $groupId ) ;
                $body .= ( $stmt->execute() ) ? "Success.<br />\n" : "Failed.<br />\n" ;
                break ;
            default:
                $page->setBody( $links . 'Huh?' ) ;
                $page->displayPage() ;
                exit( 0 );
        }

        if ( 'Update' === $action ) {
            // Delete group members not in list
            $hostGroupList = implode( ",", $groupSelection ) ;
            // These are already certified injection-proof so go ahead and expand here.
            $sql = "DELETE FROM host_group_map WHERE host_group_id = $groupId AND host_id NOT IN ({$hostGroupList})" ;
            if ( ! $dbh->query( $sql ) ) {
                echo "Conflicting update?<br />\n" ;
                exit( 1 ) ;
            }
        }
        if ( ( 'Add' === $action ) || ( 'Update' === $action ) ) {
            // Add group members
            $sql = "INSERT IGNORE INTO host_group_map"
                 . " SET host_group_id = $groupId, host_id = ?, created = NOW(), updated = NOW(), last_audited = NOW()"
                 ;
            $stmt = $dbh->prepare( $sql ) ;
            foreach ( $groupSelection as $hostId ) {
                $stmt->bind_param( "i", $hostId ) ;
                $stmt->execute() ;
            }
        }

        $groupQuery = <<<SQL
SELECT hg.host_group_id
     , hg.tag
     , hg.short_description
     , hg.full_description
     , COUNT( hgm.host_id ) as host_cnt
     , GROUP_CONCAT( DISTINCT hgm.host_id ) as host_list
  FROM host_group AS hg
  LEFT
  JOIN host_group_map AS hgm USING ( host_group_id )
 GROUP BY hg.host_group_id 
 ORDER BY hg.tag

SQL;
        $hostsQuery = <<<SQL
SELECT host_id, CONCAT( hostname, ':', port_number )
  FROM aql_db.host
 WHERE decommissioned = 0
 ORDER BY hostname, port_number
 
SQL;
        $body .= <<<HTML
<table border=1 cellspacing=0 cellpadding=2 class="tablesorter" width="100%">
  <thead>
    <tr>
      <th>Actions</th>
      <th>Group ID</th>
      <th>Group Tag</th>
      <th>Description</th>
      <th>Full Description</th>
      <th>Member Count</th>
    </tr>
  </thead>
  <tbody>

HTML;
        try {
            $groupResult = $dbh->query( $groupQuery ) ;
            if ( ! $groupResult ) {
                throw new \ErrorException( "Query failed: $groupQuery\n Error: " . $dbh->error ) ;
            }
            while ( $row = $groupResult->fetch_row() ) {
                $body .= "      <tr>"
                      .  "<td><button type=\"submit\""
                      .  " onclick=\"fillGroupForm({$row[0]}, '{$row[1]}', '{$row[2]}', '{$row[3]}', [{$row[5]}]); return false;\""
                      .  ">Fill In Form</button></td>"
                      ;
                for ( $i = 0 ; $i < 5 ; $i++ ) {
                    $body .= "<td>{$row[$i]}</td>" ;
                }
                $body .= "</tr>\n" ;
            }
            $hostResult = $dbh->query( $hostsQuery ) ;
            if ( ! $hostResult ) {
                throw new \ErrorException( "Query failed: $hostsQuery\n Error: " . $dbh->error ) ;
            }
            $groupSelect = "<select name=\"groupSelect[]\" id=\"groupSelect\" size=\"25\" multiple=\"multiple\">\n" ;
            while ( $row = $hostResult->fetch_row() ) {
                $groupSelect .= "  <option value=\"{$row[0]}\">{$row[1]}</option>\n" ;
            }
            $groupSelect .= "</select>\n" ;
            $body .= <<<HTML
  </tbody>
</table>

<p></p>

<form method="get" action="manageData.php">
  <input type="hidden" name="data" value="Groups">
  <table id="Form" border=1 cellspacing=0 cellpadding=2>
    <tr><th>Group ID</th><td><input type="number" id="groupId" name="groupId" readonly="readonly" size=5 /></td></tr>
    <tr><th>Group Tag (16)</th><td><input type="text" id="groupTag" name="groupTag" size="16" maxlength="16" /></td></tr>
    <tr><th>Description</th><td><input type="text" id="shortDescription" name="shortDescription" size="80" maxlength="255" /></td></tr>
    <tr><th>Full Description</th><td><textarea id="fullDescription" name="fullDescription" rows="4" cols="80" maxlength="65535"></textarea></td></tr>
    <tr><th>Members</th><td>$groupSelect</td></tr>
  </table>
  <input type="submit" name="action" value="Add"> &nbsp; &nbsp;
  <input type="submit" name="action" value="Update"> &nbsp; &nbsp;
  <input type="submit" name="action" value="Delete"> &nbsp; &nbsp;
  <br clear="all" />
</form>

HTML;
            $page->setBody( $links . $body ) ;
        }
        catch (DaoException $e) {
        $page->appendBody( "<pre>Error interacting with the database\n\n" . $e->getMessage() . "\n</pre>\n" ) ;
        }
        $page->displayPage() ;

        break ;
    default:
        $page->setBody( $links ) ;
        $page->displayPage() ;
        break ;
}
