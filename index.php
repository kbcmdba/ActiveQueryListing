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

require('Libs/autoload.php');
require('utility.php');

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
      <th>Info</th>
      <th>Actions</th>
    </tr>

HTML;
$debug = Tools::param('debug') === "1" ;
$page = new WebPage('Active Queries List');
$config = new Config();
$reloadSeconds = $config->getDefaultRefresh();
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
$allHostsList = '';
try {
    $config = new Config();
    $dbc = new DBConnection();
    $dbh = $dbc->getConnection();
    $result = $dbh->query($allHostsQuery);
    if ($result) {
        while ($row = $result->fetch_row()) {
            $serverName = htmlentities($row[0]);
            $allHostsList .= "  <option value=\"$serverName\">$serverName</option>\n";
            processHost($js, $row[0], $config->getBaseUrl(), $row[1], $row[2], $row[3], $row[4]);
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
    \$("#tbodyid").html( '<tr id="figment"><td colspan="12"><center>Data loading</center></td></tr>' ) ;
    \$.when($whenBlock).then(
        function ( $thenParamBlock ) {
            $thenCodeBlock
            \$("#figment").remove() ;
            \$("#dataTable").tablesorter( {sortList: [[1,1], [7, 1]]} ); 
        }
    );
    \$('#tbodyid').on('click', '.morelink', flipFlop) ;
    timeoutId = setTimeout( function() { window.location.reload( 1 ); }, reloadSeconds ) ;
}

\$(document).ready( loadPage ) ;
</script>

JS
    );
    $page->setBody(
        <<<HTML
<h1>Active Queries List</h1>

<button id="toggleButton" onclick="togglePageRefresh(); return false;">Turn Automatic Refresh Off</button>
<table border=1 cellspacing=0 cellpadding=2 id="dataTable" width="100%" class="tablesorter">
  <thead>
    $headerFooterRow
  </thead>
  <tbody id="tbodyid">
    <tr id="figment">
      <td colspan="11">
        <center>Data loading</center>
      </td>
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
