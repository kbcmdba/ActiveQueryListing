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
use com\kbcmdba\aql\Libs\MaintenanceWindow ;
use com\kbcmdba\aql\Libs\Tools ;
use com\kbcmdba\aql\Libs\WebPage ;

// ///////////////////////////////////////////////////////////////////////////

function xTable( $prefix, $linkId, $tableId, $headerFooter, $id, $cols ) {
    return <<<HTML
<p />
<a id="{$prefix}$linkId"></a>
<table border=1 cellspacing=0 cellpadding=2 id="{$prefix}{$tableId}Table" width="100%" class="tablesorter aql-listing">
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

$processCols = 15 ;
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
      <th>Lock <a onclick="alert('Shows if this query is BLOCKED waiting for a lock, or is BLOCKING other queries.') ; return false;">?</a><br>Status</th>
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

$overviewCols = 22 ;
$overviewHeaderFooterCols = <<<HTML
<tr class="mytr">
      <th>Server</th>
      <th>Version</th>
      <th>Longest<br />Running</th>
      <th>aQPS</th>
      <th>Running <a onclick="alert('Threads actively executing (includes internal threads, replication, event scheduler)'); return false;">?</a></th>
      <th>Conn% <a onclick="alert('Client connections as percentage of max_connections'); return false;">?</a></th>
      <th>Uptime</th>
      <th>L0</th>
      <th>L1</th>
      <th>L2</th>
      <th>L3</th>
      <th>L4</th>
      <th>L9</th>
      <th>RO</th>
      <th>RW</th>
      <th>Blocking</th>
      <th>Blocked</th>
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
$muted = Tools::param('mute') === "1" ;
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

// Fetch active maintenance windows for display
$activeMaintenanceWindows = [] ;
if ( $config->getEnableMaintenanceWindows() ) {
    $activeMaintenanceWindows = MaintenanceWindow::getAllActiveWindows( $dbh ) ;
}

$allHostsQuery = <<<SQL
SELECT CONCAT( h.hostname, ':', h.port_number )
     , h.alert_crit_secs
     , h.alert_warn_secs
     , h.alert_info_secs
     , h.alert_low_secs
  FROM aql_db.host AS h
 WHERE h.decommissioned = 0
   AND h.should_monitor = 1
   AND h.db_type IN ( 'MySQL', 'MariaDB', 'InnoDBCluster' )
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
   AND h.db_type IN ( 'MySQL', 'MariaDB', 'InnoDBCluster' )
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
 WHERE h.db_type IN ( 'MySQL', 'MariaDB', 'InnoDBCluster' )
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
    $jiraConfigJson = json_encode([
        'enabled' => $config->getJiraEnabled(),
        'baseUrl' => $config->getIssueTrackerBaseUrl(),
        'projectId' => $config->getJiraProjectId(),
        'issueTypeId' => $config->getJiraIssueTypeId(),
        'queryHashFieldId' => $config->getJiraQueryHashFieldId()
    ]);
    $page->setBottom(
        <<<JS
<script>

var timeoutId = null;
var reloadSeconds = $reloadSeconds * 1000 ;
var jiraConfig = {$jiraConfigJson};

// Debug logging: enable with ?refresh_debug=1 in URL
var REFRESH_DEBUG = new URLSearchParams(window.location.search).get('refresh_debug') === '1';
var refreshLog = function() { if (REFRESH_DEBUG) console.log.apply(console, ['[refresh]'].concat(Array.prototype.slice.call(arguments))); };

// Reset the refresh timer when user interacts with form controls
function resetRefreshTimer() {
    if (timeoutId !== null) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(function() { window.location.reload(1); }, reloadSeconds);
        refreshLog('Timer reset, next refresh in', reloadSeconds / 1000, 'seconds');
    }
}

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
            \$("#fullOverviewfigment").remove() ;
            \$("#fullProcessfigment").remove() ;
            \$("#fullProcessTable").tablesorter( {sortList: [[1, 1], [7, 1]]} ) ;
            initTableSorting();
            displayCharts() ;
            scrollToHashIfPresent() ;
        }
    );
    \$('#nwprocesstbodyid').on('click', '.morelink', flipFlop) ;
    \$('#fullprocesstbodyid').on('click', '.morelink', flipFlop) ;
    timeoutId = setTimeout( function() { window.location.reload( 1 ); }, reloadSeconds ) ;
}

\$(document).ready( loadPage ) ;

// Reset refresh timer when user interacts with form controls
\$(document).ready(function() {
    // Host and group selection dropdowns
    \$('#hostList, #groupSelection').on('change focus', resetRefreshTimer);
    // Refresh interval and debug checkbox
    \$('input[name="refresh"], input[name="debug"]').on('focus change input', resetRefreshTimer);
    // Mute duration inputs and datetime picker
    \$('#muteDays, #muteHours, #muteMinutes, #muteUntilDateTime').on('focus change input', resetRefreshTimer);
    // Navigation menu dropdowns - reset timer when opened or interacted with
    \$('.navbar .dropdown').on('show.bs.dropdown hide.bs.dropdown', resetRefreshTimer);
    \$('.navbar .dropdown-menu').on('click', 'a', resetRefreshTimer);
    refreshLog('Event listeners attached for refresh timer reset');
});

// Initialize sorting for data tables (uses functions from common.js)
function initTableSorting() {
    // Noteworthy tables
    initTableSortWithUrl('#nwSlaveTable', 'nwslave', [[3, 1]]);        // Seconds Behind desc
    initTableSortWithUrl('#nwOverviewTable', 'nwoverview', [[2, 1]]);  // Longest Running desc
    initTableSortWithUrl('#nwProcessTable', 'nwprocess', [[7, 1]]);    // Time Secs desc
    // Full tables
    initTableSortWithUrl('#fullSlaveTable', 'fullslave', [[3, 1]]);    // Seconds Behind desc
    initTableSortWithUrl('#fullOverviewTable', 'fulloverview', [[2, 1]]); // Longest Running desc
    refreshLog('Table sorting initialized');
}

$hgjson

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
    $aqlVersion   = Config::VERSION ;
    $debugChecked = ( $debug ) ? 'checked="checked"' : '' ;
    $muteButtonText = ( $muted ) ? 'Unmute Alerts' : 'Mute Alerts' ;
    $muteToggleValue = ( $muted ) ? '0' : '1' ;
    $cb = function ($fn) { return $fn; };

    // Generate group options for silence modal
    $groupOptionsHtml = '' ;
    try {
        $groupDbc = new DBConnection() ;
        $groupDbh = $groupDbc->getConnection() ;
        $groupResult = $groupDbh->query( "SELECT host_group_id, tag, short_description FROM aql_db.host_group ORDER BY tag" ) ;
        if ( $groupResult !== false ) {
            while ( $row = $groupResult->fetch_assoc() ) {
                $groupOptionsHtml .= "              <option value=\"" . intval( $row['host_group_id'] ) . "\">" . htmlspecialchars( $row['tag'] . ' - ' . $row['short_description'] ) . "</option>\n" ;
            }
            $groupResult->close() ;
        }
    } catch ( \Exception $e ) {
        // Silently ignore errors loading groups
    }

    // Build active maintenance windows display HTML
    $maintenanceWindowsHtml = '' ;
    if ( ! empty( $activeMaintenanceWindows ) ) {
        $maintenanceWindowsHtml = '<div id="activeMaintenanceWindows" class="maintenance-windows-panel">' ;
        $maintenanceWindowsHtml .= '<h4>Active Maintenance Windows</h4>' ;
        $maintenanceWindowsHtml .= '<table class="maintenance-windows-table">' ;
        $maintenanceWindowsHtml .= '<thead><tr><th class="mw-type">Type</th><th class="mw-desc">Description</th><th class="mw-details">Details</th><th class="mw-hosts">Affected Hosts</th></tr></thead>' ;
        $maintenanceWindowsHtml .= '<tbody>' ;
        foreach ( $activeMaintenanceWindows as $win ) {
            $typeIcon = ( $win['windowType'] === 'adhoc' ) ? '🔇' : '🔧' ;
            $typeLabel = ucfirst( $win['windowType'] ) ;
            $desc = htmlspecialchars( $win['description'] ?? 'No description' ) ;

            // Build details column
            $details = '' ;
            if ( $win['windowType'] === 'adhoc' && ! empty( $win['expiresAt'] ) ) {
                $details = 'Expires: ' . htmlspecialchars( $win['expiresAt'] ) ;
            } elseif ( ! empty( $win['timeWindow'] ) ) {
                $details = htmlspecialchars( $win['timeWindow'] ) ;
                if ( ! empty( $win['daysOfWeek'] ) ) {
                    $details .= ' (' . htmlspecialchars( $win['daysOfWeek'] ) . ')' ;
                }
            }

            // Build hosts list
            $hostsHtml = '' ;
            if ( ! empty( $win['hosts'] ) ) {
                $hostsHtml = implode( ', ', array_map( 'htmlspecialchars', $win['hosts'] ) ) ;
            }

            $maintenanceWindowsHtml .= "<tr><td class=\"mw-type\">{$typeIcon} {$typeLabel}</td><td class=\"mw-desc\">{$desc}</td><td class=\"mw-details\">{$details}</td><td class=\"mw-hosts\">{$hostsHtml}</td></tr>" ;
        }
        $maintenanceWindowsHtml .= '</tbody></table></div>' ;
    }

    $page->setBody(
        <<<HTML
<script>
// Timed mute support: cookie stores expiry timestamp (0 = indefinite, >0 = when to unmute)
const MAX_MUTE_DAYS = 90;
// Debug logging: enable with ?mute_debug=1 in URL
const MUTE_DEBUG = new URLSearchParams(window.location.search).get('mute_debug') === '1';
const muteLog = (...args) => { if (MUTE_DEBUG) console.log('[mute]', ...args); };

function getMuteExpiry() {
    // Check URL param first
    const urlParams = new URLSearchParams(window.location.search);
    const urlMuteUntil = urlParams.get('mute_until');
    if (urlMuteUntil !== null) {
        return parseInt(urlMuteUntil, 10);
    }
    // Legacy support: mute=1 URL param means indefinite
    if (urlParams.get('mute') === '1') {
        return 0;
    }
    // Check new cookie format
    const match = document.cookie.match(/aql_mute_until=(\d+)/);
    if (match) {
        return parseInt(match[1], 10);
    }
    // Check legacy cookie format (aql_mute=1 means indefinite)
    if (document.cookie.split('; ').some(c => c === 'aql_mute=1')) {
        return 0;
    }
    return null;
}

function isMuted() {
    const expiry = getMuteExpiry();
    if (expiry === null) return false;
    if (expiry === 0) return true; // Indefinite
    return Date.now() < expiry;
}

// Get the directory path for this AQL installation (for path-specific cookies)
function getAqlPath() {
    const path = window.location.pathname;
    // Get directory portion (remove filename if present)
    return path.substring(0, path.lastIndexOf('/') + 1) || '/';
}

function setMuteCookie(expiryTimestamp) {
    const cookieExpires = new Date(Date.now() + 365 * 864e5).toUTCString();
    document.cookie = 'aql_mute_until=' + expiryTimestamp + '; expires=' + cookieExpires + '; path=' + getAqlPath();
}

function clearMuteCookie() {
    const expired = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
    // Try clearing at multiple possible paths
    const paths = ['/', getAqlPath(), window.location.pathname, ''];
    const names = ['aql_mute_until', 'aql_mute'];
    muteLog('Clearing mute cookies. Current cookies:', document.cookie);
    for (const name of names) {
        for (const path of paths) {
            const cookieStr = name + '=; ' + expired + (path ? '; path=' + path : '');
            document.cookie = cookieStr;
            muteLog('Cleared:', cookieStr);
        }
    }
    muteLog('After clear:', document.cookie);
}

function setMuteFor(days, hours, minutes) {
    days = parseInt(days, 10) || 0;
    hours = parseInt(hours, 10) || 0;
    minutes = parseInt(minutes, 10) || 0;

    if (days === 0 && hours === 0 && minutes === 0) {
        // Indefinite mute
        setMuteCookie(0);
    } else {
        const ms = ((days * 24 + hours) * 60 + minutes) * 60 * 1000;
        setMuteCookie(Date.now() + ms);
    }
    updateMuteUI();
}

function setMuteUntil(dateTimeStr) {
    const target = new Date(dateTimeStr).getTime();
    const maxAllowed = Date.now() + MAX_MUTE_DAYS * 24 * 60 * 60 * 1000;
    if (target > maxAllowed) {
        alert('Maximum mute duration is ' + MAX_MUTE_DAYS + ' days.');
        return;
    }
    if (target <= Date.now()) {
        alert('Please select a future date/time.');
        return;
    }
    setMuteCookie(target);
    updateMuteUI();
}

var clearMuteInProgress = false;
function clearMute() {
    if (clearMuteInProgress) {
        muteLog('clearMute() already in progress, ignoring');
        return;
    }
    clearMuteInProgress = true;
    muteLog('clearMute() called');
    clearMuteCookie();
    // Remove URL params and reload to ensure clean state
    const url = new URL(window.location.href);
    url.searchParams.delete('mute');
    url.searchParams.delete('mute_until');
    // Remove hash to prevent scroll-triggered issues
    url.hash = '';
    muteLog('Reloading to:', url.toString());
    window.location.href = url.toString();
}

function formatTimeRemaining(ms) {
    if (ms <= 0) return 'expired';
    const days = Math.floor(ms / (24 * 60 * 60 * 1000));
    const hours = Math.floor((ms % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));
    const minutes = Math.floor((ms % (60 * 60 * 1000)) / (60 * 1000));
    let parts = [];
    if (days > 0) parts.push(days + 'd');
    if (hours > 0) parts.push(hours + 'h');
    if (minutes > 0 || parts.length === 0) parts.push(minutes + 'm');
    return parts.join(' ');
}

function updateMuteUI() {
    const expiry = getMuteExpiry();
    muteLog('updateMuteUI() - expiry:', expiry, 'isMuted:', expiry !== null && (expiry === 0 || Date.now() < expiry));
    const muteStatus = document.getElementById('muteStatus');
    const muteControls = document.getElementById('muteControls');
    const unmuteBtnContainer = document.getElementById('unmuteBtnContainer');
    muteLog('DOM elements found:', !!muteStatus, !!muteControls, !!unmuteBtnContainer);

    if (expiry === null || (expiry > 0 && Date.now() >= expiry)) {
        // Not muted or expired
        muteStatus.textContent = 'Alerts: ON';
        muteStatus.classList.remove('mute-status-muted');
        muteStatus.classList.add('mute-status-on');
        muteControls.style.display = 'block';
        unmuteBtnContainer.style.display = 'none';
        if (expiry > 0 && Date.now() >= expiry) {
            clearMuteCookie(); // Clean up expired
        }
    } else if (expiry === 0) {
        // Indefinite mute
        muteStatus.textContent = 'Alerts: MUTED (indefinite)';
        muteStatus.classList.remove('mute-status-on');
        muteStatus.classList.add('mute-status-muted');
        muteControls.style.display = 'none';
        unmuteBtnContainer.style.display = 'block';
    } else {
        // Timed mute
        const remaining = expiry - Date.now();
        muteStatus.textContent = 'Alerts: MUTED (' + formatTimeRemaining(remaining) + ' left)';
        muteStatus.classList.remove('mute-status-on');
        muteStatus.classList.add('mute-status-muted');
        muteControls.style.display = 'none';
        unmuteBtnContainer.style.display = 'block';
    }
}

function applyQuickMute(preset) {
    switch(preset) {
        case '30m': setMuteFor(0, 0, 30); break;
        case '1h': setMuteFor(0, 1, 0); break;
        case '2h': setMuteFor(0, 2, 0); break;
        case '4h': setMuteFor(0, 4, 0); break;
        case '8h': setMuteFor(0, 8, 0); break;
        case '1d': setMuteFor(1, 0, 0); break;
        case 'indef': setMuteFor(0, 0, 0); break;
    }
}

function applyCustomMute() {
    const d = document.getElementById('muteDays').value;
    const h = document.getElementById('muteHours').value;
    const m = document.getElementById('muteMinutes').value;
    setMuteFor(d, h, m);
}

function applyDateTimeMute() {
    const dt = document.getElementById('muteUntilDateTime').value;
    if (dt) setMuteUntil(dt);
}

// Sync cookie with URL param on page load
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlMuteUntil = urlParams.get('mute_until');
    const urlMute = urlParams.get('mute');
    if (urlMuteUntil !== null) {
        setMuteCookie(parseInt(urlMuteUntil, 10));
    } else if (urlMute === '1') {
        setMuteCookie(0); // Indefinite
    } else if (urlMute === '0') {
        clearMuteCookie();
    }
})();

// Update UI on load and periodically
document.addEventListener('DOMContentLoaded', function() {
    updateMuteUI();
    setInterval(updateMuteUI, 60000); // Update every minute
});
</script>
<a id="top"></a>
<a id="graphs"></a>
<audio id="klaxon" src="Images/honk-alarm-repeat-loop-101015.mp3" preload="auto"></audio>
<table id="headerTable" width="100%" border="1">
  <tr>
    <td class="headerTableTd"><h1>Active<br/>Query<br/>Listing</h1><div class="version-display">$aqlVersion</div></td>
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
        <br /><br />
        <div class="mute-status-container"><span id="muteStatus" class="mute-status-label">Alerts: ON</span> <a onclick="alert('Sound Controls Help\\n\\n• Quick mute: Click 30m, 1h, 2h, etc. to mute for that duration.\\n• ∞ button: Mute indefinitely until you click Unmute.\\n• Custom duration: Enter days/hours/minutes and click Set.\\n• Until date/time: Pick a specific date/time to unmute.\\n• Maximum mute: 90 days.\\n\\n• Chrome users: If sound does not play, click the lock icon in the address bar, go to Site Settings, and set Sound to Allow.'); return false;" class="help-cursor">?</a></div>
        <div id="muteControls">
          <nobr>
            <button onclick="applyQuickMute('30m'); return false;" title="Mute for 30 minutes">30m</button>
            <button onclick="applyQuickMute('1h'); return false;" title="Mute for 1 hour">1h</button>
            <button onclick="applyQuickMute('2h'); return false;" title="Mute for 2 hours">2h</button>
            <button onclick="applyQuickMute('4h'); return false;" title="Mute for 4 hours">4h</button>
            <button onclick="applyQuickMute('8h'); return false;" title="Mute for 8 hours">8h</button>
            <button onclick="applyQuickMute('1d'); return false;" title="Mute for 1 day">1d</button>
            <button onclick="applyQuickMute('indef'); return false;" title="Mute indefinitely">∞</button>
          </nobr>
          <br />
          <nobr class="compact-form-row">
            <input type="number" id="muteDays" value="0" min="0" max="90" class="input-narrow" title="Days"/>d
            <input type="number" id="muteHours" value="0" min="0" max="23" class="input-narrow" title="Hours"/>h
            <input type="number" id="muteMinutes" value="0" min="0" max="59" class="input-narrow" title="Minutes"/>m
            <button onclick="applyCustomMute(); return false;">Set</button>
          </nobr>
          <br />
          <nobr class="compact-form-row">
            Until: <input type="datetime-local" id="muteUntilDateTime" step="60" class="input-wide"/>
            <button onclick="applyDateTimeMute(); return false;">Set</button>
          </nobr>
        </div>
        <div id="unmuteBtnContainer" style="display: none;">
          <button onclick="clearMute(); return false;">Unmute Alerts</button>
        </div>
        <div id="localSilencesContainer" class="local-silences-container">
          <div class="local-silences-header">
            🔇 Local Silences
            <a href="#" onclick="clearAllLocalSilences(); return false;" class="local-silences-clear-all" title="Remove all local silences">Clear All</a>
          </div>
          <div id="localSilencesList" class="local-silences-list"></div>
        </div>
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
          <button onclick="openSilenceGroupModal(); return false;" title="Silence a group for maintenance">🔇 Silence Group</button>
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
        <h4 class="msg-error"><span class="glyphicon glyphicon-lock"></span>Login</h4>
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

<!-- Silence Host/Group Modal -->
<div class="modal fade" id="silenceModal" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4>Silence Alerts</h4>
      </div>
      <div class="modal-body">
        <form id="silenceForm">
          <input type="hidden" id="silenceTargetType" name="targetType" value="host" />
          <input type="hidden" id="silenceTargetId" name="targetId" value="" />
          <p id="silenceTargetRow"><strong>Target:</strong> <span id="silenceTargetDisplay"></span></p>
          <p id="silenceGroupRow" style="display:none;">
            <label>Select Group:</label><br/>
            <select id="silenceGroupSelect" class="select-min-width">
              <option value="">-- Select a Group --</option>
{$groupOptionsHtml}            </select>
          </p>
          <p>
            <label>Scope:</label><br/>
            <label class="label-spaced">
              <input type="radio" name="silenceScope" id="silenceScopeLocal" value="local" checked />
              This browser only
            </label>
            <label class="label-normal">
              <input type="radio" name="silenceScope" id="silenceScopeGlobal" value="global" />
              Everyone <span class="msg-muted">(requires authorization)</span>
            </label>
          </p>
          <p>
            <label>Quick presets:</label><br/>
            <button type="button" class="btn btn-sm btn-default" onclick="setSilenceDuration(30)">30m</button>
            <button type="button" class="btn btn-sm btn-default" onclick="setSilenceDuration(60)">1h</button>
            <button type="button" class="btn btn-sm btn-default" onclick="setSilenceDuration(120)">2h</button>
            <button type="button" class="btn btn-sm btn-default" onclick="setSilenceDuration(240)">4h</button>
            <button type="button" class="btn btn-sm btn-default" onclick="setSilenceDuration(480)">8h</button>
          </p>
          <p>
            <label>Duration (minutes):</label><br/>
            <input type="number" id="silenceDuration" name="duration" min="1" max="10080" value="60" class="input-medium" />
            <span class="form-hint">(max 7 days)</span>
          </p>
          <p>
            <label>Description (optional):</label><br/>
            <input type="text" id="silenceDescription" name="description" size="40"
                   placeholder="e.g., Working on issue JIRA-1234" />
          </p>
          <p id="silenceAutoRecoverRow">
            <label class="label-normal">
              <input type="checkbox" id="silenceAutoRecover" onchange="toggleAutoRecoverOptions()" />
              Auto-unmute when service recovers
            </label>
            <div id="silenceAutoRecoverOptions" class="silence-auto-recover-options">
              <label>
                Consider recovered when host reaches:
                <select id="silenceRecoverLevel">
                  <option value="not-error">Not in error state</option>
                  <option value="2" selected>Level 2 or better</option>
                  <option value="1">Level 1 or better</option>
                  <option value="0">Level 0 only</option>
                </select>
              </label>
              <label>
                For at least:
                <select id="silenceRecoverCount">
                  <option value="1">1 refresh cycle</option>
                  <option value="2" selected>2 consecutive cycles</option>
                  <option value="3">3 consecutive cycles</option>
                  <option value="5">5 consecutive cycles</option>
                </select>
              </label>
            </div>
          </p>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="submitSilence()">Silence</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
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
{$maintenanceWindowsHtml}
{$cb(xTable( 'nw', 'SlaveStatus', 'Slave', $NWSlaveHeaderFooter, 'slave', $slaveCols ))}
{$cb(xTable( 'nw', 'StatusOverview', 'Overview', $NWOverviewHeaderFooter, 'overview', $overviewCols ))}
{$cb(xTable( 'nw', 'ProcessListing', 'Process', $NWProcessHeaderFooter, 'process', $processCols ))}
<h2>Full Data</h2>
{$cb(xTable( 'full', 'SlaveStatus', 'Slave', $fullSlaveHeaderFooter, 'slave', $slaveCols ))}
{$cb(xTable( 'full', 'StatusOverview', 'Overview', $fullOverviewHeaderFooter, 'overview', $overviewCols ))}
{$cb(xTable( 'full', 'ProcessListing', 'Process', $fullProcessHeaderFooter, 'process', $processCols ))}

<p />

<a id="versionSummary"></a>
<table border=1 cellspacing=0 cellpadding=2 id="versionSummaryTable" class="tablesorter aql-listing">
<thead>
  <tr><th colspan="2">Version Summary</th></tr>
  <tr><th>Version</th><th>Host Count</th></tr>
</thead>
<tbody id="versionsummarytbodyid">
  <tr><td colspan="2"><center>Data loading</center></td></tr>
</tbody>
</table>

<p />

<table border=1 cellspacing=0 cellpadding=2 id="legend" width="100%">
  <caption>Legend</caption>
  <tr><th>Level</th><th>Description</th></tr>
  <tr class="legendError"><td>-</td><td>An error has occurred while communicating with the host described.</td></tr>
  <tr class="legendLevel4"><td>4</td><td>The shown query has reached a critical alert level and should be investigated.</td></tr>
  <tr class="legendLevel3"><td>3</td><td>The shown query has reached a warning alert level.</td></tr>
  <tr class="legendLevel2"><td>2</td><td>The shown query is running longer than expected.</td></tr>
  <tr class="legendLevel1"><td>1</td><td>The shown query is running within normal time parameters.</td></tr>
  <tr class="legendLevel0"><td>0</td><td>The shown query has run for less time than expected so far.</td></tr>
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
