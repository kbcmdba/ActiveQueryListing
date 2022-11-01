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

$page = new WebPage( 'AQL: Manage Groups' ) ;

// validate input
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
if ( 'Delete' !== $action ) {
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
    $page->setBody( $errors ) ;
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
        $page->setBody( 'Huh?' ) ;
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

<form>
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
    $page->setBody( $body ) ;
}
catch (DaoException $e) {
   $page->appendBody( "<pre>Error interacting with the database\n\n" . $e->getMessage() . "\n</pre>\n" ) ;
}
$page->displayPage() ;
