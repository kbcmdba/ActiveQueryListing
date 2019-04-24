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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\ActiveQueryListing ;

require_once('vendor/autoload.php') ;
require_once('utility.php') ;

use com\kbcmdba\ActiveQueryListing\Libs\Config;
use com\kbcmdba\ActiveQueryListing\Libs\DBConnection;
use com\kbcmdba\ActiveQueryListing\Libs\Tools;
use com\kbcmdba\ActiveQueryListing\Libs\WebPage;
use com\kbcmdba\ActiveQueryListing\Libs\Exceptions\DaoException;

$debug = Tools::param('debug') === "1";
$debugMode = ( $debug ) ? "&debug=1" : "" ;
$kioskMode = Tools::param('mode') === 'Kiosk';
$host = Tools::param('host') ;
$actions = $kioskMode ? '' : 'Actions' ;
$headerFooterRow = <<<HTML
<tr>
      <th>Server</th>
      <th>Alert<br />Level</th>
      <th>Thread<br />ID</th>
      <th>User</th>
      <th>From<br />Host</th>
      <th>DB</th>
      <th>Command <a href="https://dev.mysql.com/doc/refman/5.6/en/thread-commands.html" target="_blank">?</a></th>
      <th>Time<br />Secs</th>
      <th>State <a href="https://dev.mysql.com/doc/refman/5.6/en/general-thread-states.html" target="_blank">?</a></th>
      <th>R/O</th>
      <th>Dupe <a onclick="alert('Dupe=A duplicate query on the same host & schema.\\nSimilar=A duplicate query on the same host & schema except that the parameters are different.'); preventDefault();">?</a></th>
      <th>Info</th>
      <th>$actions</th>
    </tr>

HTML;
$page = new WebPage('Active Queries List');
$config = new Config();
$reloadSeconds = $config->getDefaultRefresh();
if ( Tools::isNumeric( Tools::param('refresh') ) && ( Tools::param( 'refresh' ) >= $config->getMinRefresh() ) ) {
    $reloadSeconds = Tools::param('refresh');
}
$js = [] ;
$js['Blocks'] = 0;
$js['WhenBlock'] = '';
$js['ThenParamBlock'] = '';
$js['ThenCodeBlock'] = '';
$allHostsQuery = <<<SQL
SELECT h.hostname
     , h.alert_crit_secs
     , h.alert_warn_secs
     , h.alert_info_secs
     , h.alert_low_secs
  FROM aql_db.host AS h
 WHERE 1 = 1
   AND should_monitor = 1
   AND decommissioned = 0
 
SQL;
if ( '' !== $host ) {
    $allHostsQuery .= "   AND h.hostname = ?\n" ;
}

try {
    $config = new Config();
    $dbc = new DBConnection();
    $dbh = $dbc->getConnection();
    $sth = $dbh->prepare($allHostsQuery);
    if ( '' !== $host ) {
        $sth->bind_param("s", $host);
    }
    $result = $sth->execute();
    $hostname = $alert_crit_secs = $alert_warn_secs = $alert_info_secs = $alert_low_secs = NULL;
    $sth->bind_result( $hostname, $alert_crit_secs, $alert_warn_secs, $alert_info_secs, $alert_low_secs );
    if ($result) {
        while ($sth->fetch()) {
            processHost($js, $hostname, $config->getBaseUrl(), $alert_crit_secs, $alert_warn_secs, $alert_info_secs, $alert_low_secs );
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

function loadPage() {
    \$("#tbodyid").html( '<tr id="figment"><td colspan="13"><center>Data Loading</center></td></tr>' ) ;
    \$("#tbodysummaryid").html( '<tr id="summaryfigment"><td colspan="15"><center>Data Loading</center></td></tr>' ) ;
    \$.when($whenBlock).then(
        function ( $thenParamBlock ) {
            $thenCodeBlock
            \$("#summaryfigment").remove() ;
            \$("#figment").remove() ;
            \$("#dataTable").tablesorter( {sortList: [[1,1], [7, 1]]} );
            \$("#summaryTable").tablesorter( {sortList: [[0,0]]} );
            displayCounts();
        }
    );
    \$('#tbodyid').on('click', '.morelink', flipFlop) ;
    timeoutId = setTimeout( function() { window.location.reload( 1 ); }, reloadSeconds ) ;
}

\$(document).ready( loadPage ) ;
</script>

JS
    );
    $now = Tools::currentTimestamp();
    $autoRefreshButton = $kioskMode ? '' : '<button type="button" id="toggleButton" onclick="togglePageRefresh(); return false;">Turn Automatic Refresh Off</button>' ;
    $toggleSummaryDisplayButton = $kioskMode ? '' : '<button type="button" id="toggleSummaryButton" onclick="toggleSummaryDisplay(); return false;">Turn Summary Display On</button>' ;
    $page->setBody(
        <<<HTML

<table width="100%">
  <tr>
    <td><h1>Active Queries List</h1></td>
    <td><div id="piechart1_3d" style="width: 350px; height: 250px;"></div></td>
    <td><div id="piechart2_3d" style="width: 350px; height: 250px;"></div></td>
    <td><div id="piechart3_3d" style="width: 350px; height: 250px;"></div></td>
    <td><div id="piechart4_3d" style="width: 350px; height: 250px;"></div></td>
    <td valign="middle" align="right"><div class="now">Last retrieve started at $now</div></td>
  </tr>
</table>
<p />
<div class="container">
  <!-- Modal -->
  <div class="modal fade" id="myModal" role="dialog">
    <div class="modal-dialog">

      <!-- Modal content-->
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 style="color:red;"><span class="glyphicon glyphicon-lock"></span> Login</h4>
        </div>
        <div class="modal-body">
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
            <tr><th>DB</th><td id="m_db">DB</td></tr>
            <tr><th>Command</th><td id="m_command">Command</td></tr>
            <tr><th>Time</th><td id="m_time">Time</td></tr>
            <tr><th>State</th><td id="m_state">State</td></tr>
            <tr><th>Info</th><td id="m_info">Info</td></tr>
          </table>
       </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-default btn-default pull-left" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span> Cancel</button>
        </div>
      </div>
    </div>
  </div> 
</div>
<script type="text/javascript">
  $(function() {
      $('#modalForm').on('submit', function(e){
          e.preventDefault();
          $.post( 'killproc.php' 
                , $('#modalForm').serialize()
                , function(data, status, xhr){
                      alert(JSON.parse(data).result);
                });
      });
  });
</script>
<p />
$autoRefreshButton
$toggleSummaryDisplayButton
<p />
<table id="summaryTable" class="tablesorter" style="display: none;">
  <thead>
    <tr>
      <th>host</th>
      <th>Active<br />Time</th>
      <th>Sessions</th>
      <th>Sleeping</th>
      <th>Daemon</th>
      <th>Dupe</th>
      <th>Similar</th>
      <th>Unique</th>
      <th>Error</th>
      <th>Level 4</th>
      <th>Level 3</th>
      <th>Level 2</th>
      <th>Level 1</th>
      <th>Level 0</th>
      <th>Reader<br/>Writer</th>
    </tr>
  </thead>
  <tbody id="tbodysummaryid">
    <tr id="summaryfigment">
      <td colspan="15"><center>Data Loading</center></td>
    </tr>
  </tbody>
</table>
&nbsp;<p />
<table id="dataTable" class="tablesorter">
  <thead>
    $headerFooterRow
  </thead>
  <tbody id="tbodyid">
    <tr id="figment">
      <td colspan="13"><center>Data Loading</center></td>
    </tr>
  </tbody>
</table>
<p />
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
