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

require('vendor/autoload.php');
require('utility.php');

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;
use com\kbcmdba\aql\Libs\Exceptions\DaoException;
use com\kbcmdba\aql\Libs\Tools ;
use com\kbcmdba\aql\Libs\WebPage ;

$navBar = <<<HTML
<br clear="all" />
Navigate:
 &nbsp; &nbsp; <nobr><a href="#graphs">Top / Graphs</a></nobr>
 &nbsp; &nbsp; <nobr><a href="#nwSlaveStatus">Noteworthy Slave Status</a></nobr>
 &nbsp; &nbsp; <nobr><a href="#nwStatusOverview">Noteworthy Status Overview</a></nobr>
 &nbsp; &nbsp; <nobr><a href="#nwProcessListing">Noteworthy Process Listing</a></nobr>
 &nbsp; &nbsp; <nobr><a href="#fullSlaveStatus">Full Slave Status</a></nobr>
 &nbsp; &nbsp; <nobr><a href="#fullStatusOverview">Full Status Overview</a></nobr>
 &nbsp; &nbsp; <nobr><a href="#fullProcessListing">Full Process Listing</a></nobr>
 &nbsp; &nbsp; <nobr><a href="manageData.php">Manage Data</a></nobr>
<br clear="all" />
HTML;

// ///////////////////////////////////////////////////////////////////////////

function xTable( $prefix, $linkId, $tableId, $headerFooter, $id, $cols ) {
    global $navBar;
    return <<<HTML
$navBar

<a id="{$prefix}$linkId"></a>
<table border=1 cellspacing=0 cellpadding=2 id="{$prefix}{$tableId}Table" width="100%" class="tablesorter">
<thead>
  $headerFooter
</thead>
<tbody id="{$prefix}{$id}tbodyid">
  <tr id="{$prefix}{$id}figment">
    <td colspan="$cols">
      <center>Data loading</center>
    </td>
  </tr>
</tbody>
</table>

HTML;
}

// ///////////////////////////////////////////////////////////////////////////

$hostList = Tools::params( 'hosts' ) ;

if ( ! is_array( $hostList ) ) {
    $hostList = [ Tools::params('hosts') ] ;
}

$processCols = 14 ;
$processHeaderFooterCols = <<<HTML
<tr class="mytr">
      <th>Server</th>
      <th>Alert<br />Level</th>
      <th>Thread<br />ID</th>
      <th>User</th>
      <th>From<br />Host:Port</th>
      <th>DB</th>
      <th>Command <a href="https://dev.mysql.com/doc/refman/5.6/en/thread-commands.html" target="_blank">?</a></th>
      <th>Time<br />Secs</th>
      <th>Friendly<br>Time</th>
      <th>State <a href="https://dev.mysql.com/doc/refman/5.6/en/general-thread-states.html" target="_blank">?</a></th>
      <th>R/O</th>
      <th>Dupe <a onclick="alert('Possible states are Unique, Similar, Duplicate and Blank. Similar indicates that a query is identical to another query except that the numbers and strings may be different. Duplicate means the entire query is identical to another query.') ; return false;">?</a><br>State</th>
      <th>Info</th>
      <th>Actions</th>
    </tr>
HTML;
$fullProcessHeaderFooter = <<<HTML
<tr class="mytr">
      <th colspan="$processCols">Full Process Listing</th>
    </tr>
    $processHeaderFooterCols
HTML;
$NWProcessHeaderFooter = <<<HTML
<tr class="mytr">
      <th colspan="$processCols">Noteworthy Process Listing</th>
    </tr>
    $processHeaderFooterCols
HTML;

$slaveCols = 8 ;
    $slaveHeaderFooterCols = <<<HTML
<tr class="mytr">
      <th>Server</th>
      <th>Connection<br />Name</th>
      <th>Slave Of</th>
      <th>Seconds<br />Behind</th>
      <th>IO Thread<br />Running</th>
      <th>SQL Thread<br />Running</th>
      <th>IO Thread<br />Last Error</th>
      <th>SQL Thread<br />Last Error</th>
    </tr>
HTML;
$fullSlaveHeaderFooter = <<<HTML
<tr class="mytr">
      <th colspan="$slaveCols">Full Slave Status</th>
    </tr>
    $slaveHeaderFooterCols
HTML;
$NWSlaveHeaderFooter = <<<HTML
<tr class="mytr">
      <th colspan="$slaveCols">Noteworthy Slave Status</th>
    </tr>
    $slaveHeaderFooterCols
HTML;

$overviewCols = 18 ;
$overviewHeaderFooterCols = <<<HTML
<tr class="mytr">
      <th>Server</th>
      <th>Version</th>
      <th>Longest<br />Running</th>
      <th>aQPS</th>
      <th>Uptime</th>
      <th>L0</th>
      <th>L1</th>
      <th>L2</th>
      <th>L3</th>
      <th>L4</th>
      <th>L9</th>
      <th>RO</th>
      <th>RW</th>
      <th>Blank</th>
      <th>Duplicate</th>
      <th>Similar</th>
      <th>Threads</th>
      <th>Unique</th>
    </tr>
HTML;
$fullOverviewHeaderFooter = <<<HTML
<tr class="mytr">
      <th colspan="$overviewCols">Full Status Overview</th>
    </tr>
    $overviewHeaderFooterCols
HTML;
$NWOverviewHeaderFooter = <<<HTML
<tr class="mytr">
      <th colspan="$overviewCols">Noteworthy Status Overview</th>
    </tr>
    $overviewHeaderFooterCols
HTML;

$debug = Tools::param('debug') === "1" ;
$page = new WebPage('Active Queries List');
$config = new Config();
$defaultRefresh = $config->getDefaultRefresh() ;
$minRefresh = $config->getMinRefresh() ;
$reloadSeconds = Tools::param('refresh', $defaultRefresh) ;
if ( $reloadSeconds < $minRefresh ) {
    $reloadSeconds = $minRefresh ;
}

$js = [ 'Blocks' => 0
      , 'WhenBlock' => ''
      , 'ThenParamBlock' => ''
      , 'ThenCodeBlock' => ''
      ] ;
try {
    $config = new Config();
}
catch ( ConfigurationException $e ) {
    print("Has AQL been configured? " . $e->getMessage());
    exit(1);
}
$dbc = new DBConnection();
$dbh = $dbc->getConnection();
$dbh->set_charset('utf8');
$allHostsQuery = <<<SQL
SELECT CONCAT( h.hostname, ':', h.port_number )
     , h.alert_crit_secs
     , h.alert_warn_secs
     , h.alert_info_secs
     , h.alert_low_secs
  FROM aql_db.host AS h
 WHERE h.decommissioned = 0
   AND h.should_monitor = 1
 ORDER BY h.hostname, h.port_number
 
SQL;
$in = "'"
    . implode("', '", array_map( [ $dbh, 'real_escape_string' ], $hostList ) )
    . "'" ;
$someHostsQuery = <<<SQL
SELECT CONCAT( h.hostname, ':', h.port_number )
     , h.alert_crit_secs
     , h.alert_warn_secs
     , h.alert_info_secs
     , h.alert_low_secs
  FROM aql_db.host AS h
 WHERE h.decommissioned = 0
   AND CONCAT( h.hostname, ':', h.port_number ) IN ( $in )
 ORDER BY h.hostname, h.port_number

SQL ;
$allHostGroupsQuery = <<<SQL
SELECT hg.host_group_id, hg.tag, CONCAT( '"', GROUP_CONCAT( CONCAT( h.hostname, ':', h.port_number ) SEPARATOR '", "' ), '"' )
  FROM host_group AS hg
  LEFT
  JOIN host_group_map AS hgm
 USING ( host_group_id )
  LEFT
  JOIN host AS h
 USING ( host_id )
 GROUP BY hg.host_group_id
 ORDER BY hg.tag

SQL ;
$allGroupsList = '' ;
$allHostsList = '' ;
$baseUrl = $config->getBaseUrl() ;
$showAllHosts = ( 0 === count( $hostList ) ) ;
$hgjson = 'hostGroupMap = { ' ;
try {
    $hgResult = $dbh->query( $allHostGroupsQuery ) ;
    if ( ! $hgResult ) {
        throw new \ErrorException( "Query failed: $allHostGroupsQuery\n Error: " . $dbh->error ) ;
    }
    while ( $row = $hgResult->fetch_row() ) {
        $hostGroupId = $row[ 0 ] ;
        $hostGroupTag = $row[ 1 ] ;
        $hostGroupHostList = ( '""' === $row[ 2 ] ) ? '' : $row[ 2 ] ;
        $allGroupsList .= "  <option value=\"$hostGroupTag\">$hostGroupTag</option>\n" ;
        $hgjson .= "\"$hostGroupTag\": [$hostGroupHostList]," ;
    }
    $hgResult->close() ;
    $hgjson .= ' }' ;
    $result = $dbh->query( $allHostsQuery ) ;
    if ( ! $result ) {
        throw new \ErrorException( "Query failed: $allHostsQuery\n Error: " . $dbh->error ) ;
    }
    while ( $row = $result->fetch_row() ) {
        $serverName = htmlentities( $row[0] ) ;
        $selected = ( in_array( $row[0], $hostList ) ) ? 'selected="selected"' : '' ;
        $allHostsList .= "  <option value=\"$serverName\" $selected>$serverName</option>\n" ;
        if ( $showAllHosts ) {
            processHost($js, $row[0], $baseUrl, $row[1], $row[2], $row[3], $row[4]);
        }
    }
    if ( ! $showAllHosts ) {
        $result = $dbh->query( $someHostsQuery );
        if (! $result) {
            throw new \ErrorException( "Query failed: $someHostsQuery\n Error: " . $dbh->error );
        }
        while ($row = $result->fetch_row()) {
            processHost($js, $row[0], $baseUrl, $row[1], $row[2], $row[3], $row[4]);
        }
    }
    $whenBlock = $js['WhenBlock'];
    $thenParamBlock = $js['ThenParamBlock'];
    $thenCodeBlock = $js['ThenCodeBlock'];
    $page->setBottom(
        <<<JS
<script>

var timeoutId = null;
var reloadSeconds = $reloadSeconds * 1000 ;

///////////////////////////////////////////////////////////////////////////////

function loadPage() {
    \$("#nwslavetbodyid").html( '<tr id="nwSlavefigment"><td colspan="$slaveCols"><center>Data loading</center></td></tr>' ) ;
    \$("#nwoverviewtbodyid").html( '<tr id="nwOverviewfigment"><td colspan="$overviewCols"><center>Data loading</center></td></tr>' ) ;
    \$("#nwprocesstbodyid").html( '<tr id="nwProcessfigment"><td colspan="$processCols"><center>Data loading</center></td></tr>' ) ;
    \$("#fullslavetbodyid").html( '<tr id="fullSlavefigment"><td colspan="$slaveCols"><center>Data loading</center></td></tr>' ) ;
    \$("#fulloverviewtbodyid").html( '<tr id="fullOverviewfigment"><td colspan="$overviewCols"><center>Data loading</center></td></tr>' ) ;
    \$("#fullprocesstbodyid").html( '<tr id="fullProcessfigment"><td colspan="$processCols"><center>Data loading</center></td></tr>' ) ;
    \$.when($whenBlock).then(
        function ($thenParamBlock ) { $thenCodeBlock
            \$("#nwSlavefigment").remove() ;
            \$("#nwOverviewfigment").remove() ;
            \$("#nwProcessfigment").remove() ;
            \$("#fullSlavefigment").remove() ;
            \$("#fullPverviewfigment").remove() ;
            \$("#fullProcessfigment").remove() ;
            \$("#fullProcessTable").tablesorter( {sortList: [[1, 1], [7, 1]]} ) ; 
            displayCharts() ;
        }
    );
    \$('#nwprocesstbodyid').on('click', '.morelink', flipFlop) ;
    \$('#fullprocesstbodyid').on('click', '.morelink', flipFlop) ;
    timeoutId = setTimeout( function() { window.location.reload( 1 ); }, reloadSeconds ) ;
}

\$(document).ready( loadPage ) ;

var $hgjson;

function addGroupSelection() {
    var elements = document.getElementById( 'groupSelection' ).options ;
    var selectedIndex = elements.selectedIndex ;
    var hostGroupName = elements[ selectedIndex ].attributes[ 0 ].value ;
    var selectedHostList = hostGroupMap[ hostGroupName ] ;
    var hostList = document.getElementById( 'hostList' ) ;
    for ( var i = 0 ; i < selectedHostList.length ; i++ ) {
        for ( var j = 0 ; j < hostList.length ; j++ ) {
            if ( hostList[ j ].attributes[ 0 ].value == selectedHostList[ i ] ) {
                hostList[ j ].selected = true ;
            }
        }
    }
}
</script>

JS
    );
    $now          = Tools::currentTimestamp();
    $debugChecked = ( $debug ) ? 'checked="checked"' : '' ;
    $cb = function ($fn) { return $fn; };
    $page->setBody(
        <<<HTML
<a id="graphs"></a>
<table id="top" width="100%" border="1">
  <tr>
    <td class="headerTableTd"><h1>Active<br/>Query<br/>Listing</h1></td>
    <td id="updatedAt">Page last updated at $now</td>
    <td class="headerTableTd">
      <center>
        <form method="get">
          <select id="hostList" name="hosts[]" multiple="multiple" size=10>
            $allHostsList
          </select><br />
          Refresh every <input type="text" name="refresh" value="$reloadSeconds" size="3" /> seconds<br />
          <input type="checkbox" name="debug" value="1" $debugChecked/> Debug Mode<br />
          <input type="submit" value="Update" />
        </form>
        <button id="toggleButton" onclick="togglePageRefresh(); return false;">Turn Automatic Refresh Off</button>
      </center>
    </td>
    <td class="headerTableTd">
      <center>
        <nobr>Add Group</nobr> <nobr>To Selection</nobr>
        <form method="get">
          <select id="groupSelection" name="groupSelection" size=10>
            $allGroupsList
          </select><br />
          <button id="groupSelect" onclick="addGroupSelection(); return false;">Add Group Selection</button>
        </form>
      </center>
    </td>
    <td class="headerTableTd"><div id="pieChartByLevel" class="chartImage"></div></td>
    <td class="headerTableTd"><div id="pieChartByHost" class="chartImage"></div></td>
    <td class="headerTableTd"><div id="pieChartByDB" class="chartImage"></div></td>
    <td class="headerTableTd"><div id="pieChartByDupeState" class="chartImage"></div></td>
    <td class="headerTableTd"><div id="pieChartByReadWrite" class="chartImage"></div></td>
  </tr>
</table>

<div class="container">
  <!-- Modal -->
  <div class="modal fade" id="myModal" role="dialog">
    &nbsp;
    <p />
    <div class="modal-dialog">
      <!-- Modal Content -->
      <div class="modal-content">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 style="color: red;"<span class="glyphicon glyphicon-lock"></span>Login</h4>
      </div>
      <div class="modal-body" style="background-color: white;">
        <h2>Active Query Listing: Kill Thread Login</h2>
        <form method="post" id="modalForm">
            <input type="hidden" name="server" id="i_server" value="" />
            <input type="hidden" name="pid" id="i_pid" value="" />
            Login: <input type="text" name="login" placeholder="Required" /><br />
            Password: <input type="password" name="password" placeholder="Required" /><br />
            Reason for thread termination: <textarea name="reason" cols="60" rows="5" maxlength="255" placeholder="Required"></textarea><br />
            <p />
            <input type=submit value="Kill Thread" />
          </form>
          <div id="kill-results"></div>
          <table>
            <tr><th>Server</th><td id="m_server">Server</td></tr>
            <tr><th>Thread ID</th><td id="m_pid">Thread ID</td></tr>
            <tr><th>User</th><td id="m_user">User</td></tr>
            <tr><th>From Host</th><td id="m_host">From Host</td></tr>
            <tr><th>Schema</th><td id="m_db">Schema</td></tr>
            <tr><th>Command</th><td id="m_command">Command</td></tr>
            <tr><th>Time</th><td id="m_time">Time</td></tr>
            <tr><th>State</th><td id="m_state">State</td></tr>
            <tr><th>Info</th><td id="m_info">Info</td></tr>
          </table>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-default btn-default pull-left" data-dismiss="modal">
            <span class="glyphicon glyphicon-remove"></span> Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
  $(function() {
      $('#modalForm').on('submit', function(e){
          e.preventDefault();
          $.post( 'AJAXKillProc.php'
                , $('#modalForm').serialize()
                , function(data, status, xhr){
                      alert(JSON.parse(data).result);
                });
      });
  });
</script>

<h2>Noteworthy Data</h2>
{$cb(xTable( 'nw', 'SlaveStatus', 'Slave', $NWSlaveHeaderFooter, 'slave', $slaveCols ))}
{$cb(xTable( 'nw', 'StatusOverview', 'Overview', $NWOverviewHeaderFooter, 'overview', $overviewCols ))}
{$cb(xTable( 'nw', 'ProcessListing', 'Process', $NWProcessHeaderFooter, 'process', $processCols ))}
<h2>Full Data</h2>
{$cb(xTable( 'full', 'SlaveStatus', 'Slave', $fullSlaveHeaderFooter, 'slave', $slaveCols ))}
{$cb(xTable( 'full', 'StatusOverview', 'Overview', $fullOverviewHeaderFooter, 'overview', $overviewCols ))}
{$cb(xTable( 'full', 'ProcessListing', 'Process', $fullProcessHeaderFooter, 'process', $processCols ))}

$navBar

<table border=1 cellspacing=0 cellpadding=2 id="legend" width="100%">
  <caption>Legend</caption>
  <tr><th>Level</th><th>Description</th></tr>
  <tr class="error" ><td>-</td><td>An error has occurred while communicating with the host described.</td></tr>
  <tr class="level4">
    <td>4</td><td>The shown query has reached a critical alert level and should be investigated.</td>
  </tr>
  <tr class="level3"><td>3</td><td>The shown query has reached a warning alert level.</td></tr>
  <tr class="level2"><td>2</td><td>The shown query is running longer than expected.</td></tr>
  <tr class="level1"><td>1</td><td>The shown query is running within normal time parameters.</td></tr>
  <tr class="level0"><td>0</td><td>The shown query has run for less time than expected so far.</td></tr>
</table>

HTML
    ) ;
} catch (DaoException $e) {
    $page->appendBody(
        "<pre>Error interacting with the database\n\n"
                  . $e->getMessage() . "\n</pre>\n"
    ) ;
}
$page->displayPage() ;
