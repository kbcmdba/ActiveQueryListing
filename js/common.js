/**
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

const urlParams = new URLSearchParams(window.location.search);
const debug = urlParams.get('debug');
const debugLocks = urlParams.get('debugLocks');
const debugScoreboard = urlParams.get('debugScoreboard');
const debugString = ( debug == '1' ? '&debug=1' : '' ) + ( debugLocks == '1' ? '&debugLocks=1' : '' ) ;

///////////////////////////////////////////////////////////////////////////////
// Theme Toggle Support
///////////////////////////////////////////////////////////////////////////////

/**
 * Get the current theme from URL param or cookie
 * URL param takes precedence over cookie
 * @returns {string} 'dark' or 'light'
 */
function getTheme() {
    // Check URL param first
    const urlTheme = urlParams.get('theme');
    if (urlTheme === 'light' || urlTheme === 'dark') {
        // Sync to cookie
        setThemeCookie(urlTheme);
        return urlTheme;
    }

    // Check cookie
    const cookieTheme = getThemeCookie();
    if (cookieTheme === 'light' || cookieTheme === 'dark') {
        return cookieTheme;
    }

    // Default to dark
    return 'dark';
}

/**
 * Get theme from cookie
 * @returns {string|null}
 */
function getThemeCookie() {
    const match = document.cookie.match(/(?:^|; )aql_theme=([^;]*)/);
    return match ? match[1] : null;
}

/**
 * Set theme cookie (expires in 1 year)
 * @param {string} theme - 'dark' or 'light'
 */
function setThemeCookie(theme) {
    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = 'aql_theme=' + theme + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
}

/**
 * Apply theme to the page
 * @param {string} theme - 'dark' or 'light'
 */
function applyTheme(theme) {
    if (theme === 'light') {
        document.body.classList.add('theme-light');
    } else {
        document.body.classList.remove('theme-light');
    }
    // Update toggle button if it exists
    const toggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');
    const themeLabel = document.getElementById('themeLabel');
    if (toggleBtn) {
        toggleBtn.title = theme === 'light' ? 'Switch to Dark Mode' : 'Switch to Light Mode';
    }
    if (themeIcon) {
        themeIcon.textContent = theme === 'light' ? '🌙' : '☀️';
    }
    if (themeLabel) {
        themeLabel.textContent = theme === 'light' ? 'Dark Mode' : 'Light Mode';
    }
}

/**
 * Toggle between light and dark themes
 */
function toggleTheme() {
    const currentTheme = document.body.classList.contains('theme-light') ? 'light' : 'dark';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    setThemeCookie(newTheme);
    applyTheme(newTheme);
    // Redraw charts if they exist
    if (typeof redrawCharts === 'function') {
        redrawCharts();
    }
}

/**
 * Get chart colors based on current theme
 * @returns {object} Chart color configuration
 */
function getChartColors() {
    const isLight = document.body.classList.contains('theme-light');
    return {
        backgroundColor: isLight ? '#f5f5f5' : '#333333',
        titleColor: isLight ? '#222222' : '#ffffff',
        legendTextColor: isLight ? '#222222' : '#ffffff'
    };
}

// Apply theme on page load (before DOMContentLoaded to prevent flash)
(function() {
    const theme = getTheme();
    // Apply immediately if body exists, otherwise wait
    if (document.body) {
        applyTheme(theme);
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            applyTheme(theme);
        });
    }
})();

///////////////////////////////////////////////////////////////////////////////
// DBType Statistics Tracking (for Overview boxes and Scoreboard)
///////////////////////////////////////////////////////////////////////////////

/**
 * Track statistics by database type for the DBType Overview boxes and Scoreboard
 * Tracks per-level counts like a real scoreboard
 * Reset at start of each page load cycle
 */
var dbTypeStats = {
    'MySQL': {
        hostCount: 0,
        levels: { 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 9: 0 },
        levelHosts: { 0: {}, 1: {}, 2: {}, 3: {}, 4: {}, 9: {} },  // hostname -> count
        worstLevel: 0,
        // MySQL-specific aggregates
        longestRunning: 0,
        blocking: 0,
        blocked: 0
    },
    'Redis': {
        hostCount: 0,
        levels: { 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 9: 0 },
        levelHosts: { 0: {}, 1: {}, 2: {}, 3: {}, 4: {}, 9: {} },  // hostname -> count
        worstLevel: 0,
        // Redis-specific aggregates
        evicted: 0,
        rejected: 0,
        blockedClients: 0
    }
} ;

/**
 * Reset dbTypeStats at the start of a new data load cycle
 */
function resetDbTypeStats() {
    for ( var dbType in dbTypeStats ) {
        if ( dbTypeStats.hasOwnProperty( dbType ) ) {
            dbTypeStats[ dbType ].hostCount = 0 ;
            dbTypeStats[ dbType ].worstLevel = 0 ;
            dbTypeStats[ dbType ].levels = { 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 9: 0 } ;
            dbTypeStats[ dbType ].levelHosts = { 0: {}, 1: {}, 2: {}, 3: {}, 4: {}, 9: {} } ;
            // Reset type-specific fields
            if ( dbType === 'MySQL' ) {
                dbTypeStats[ dbType ].longestRunning = 0 ;
                dbTypeStats[ dbType ].blocking = 0 ;
                dbTypeStats[ dbType ].blocked = 0 ;
            } else if ( dbType === 'Redis' ) {
                dbTypeStats[ dbType ].evicted = 0 ;
                dbTypeStats[ dbType ].rejected = 0 ;
                dbTypeStats[ dbType ].blockedClients = 0 ;
            }
        }
    }
}

/**
 * Track a level count for a DBType
 * @param {string} dbType - 'MySQL' or 'Redis'
 * @param {number} level - The level (0, 1, 2, 3, 4, or 9)
 * @param {number} count - How many to add (default 1)
 * @param {string} hostname - Required hostname for drill-down tracking
 */
function trackLevelByDbType( dbType, level, count, hostname ) {
    // Normalize dbType
    if ( dbType === 'MariaDB' || dbType === 'InnoDBCluster' ) {
        dbType = 'MySQL' ;
    }
    if ( !dbTypeStats[ dbType ] ) return ;

    // Hostname is required for drill-down tracking
    if ( !hostname ) {
        console.warn( '[scoreboard] trackLevelByDbType called without hostname for ' + dbType + ' L' + level ) ;
        return ;
    }

    // Default to 1 only if count is undefined/null, not if it's 0
    if ( typeof count === 'undefined' || count === null ) {
        count = 1 ;
    }
    if ( count === 0 ) return ;  // Don't track zero counts

    var stats = dbTypeStats[ dbType ] ;
    stats.levels[ level ] = ( stats.levels[ level ] || 0 ) + count ;

    // Track hostname for drill-down
    if ( !stats.levelHosts[ level ] ) {
        stats.levelHosts[ level ] = {} ;
    }
    stats.levelHosts[ level ][ hostname ] = ( stats.levelHosts[ level ][ hostname ] || 0 ) + count ;

    // Audit logging - shows hostname attribution
    if ( debugScoreboard === '1' ) {
        console.log( '[scoreboard] ' + hostname + ' => ' + dbType + ' L' + level + ' +' + count + ' (total: ' + stats.levels[ level ] + ')' ) ;
    }

    // Update worst level
    if ( level > stats.worstLevel ) {
        stats.worstLevel = level ;
    }
}

/**
 * Track a host for a DBType (increments host count)
 * @param {string} dbType - 'MySQL' or 'Redis'
 */
function trackHostByDbType( dbType ) {
    if ( dbType === 'MariaDB' || dbType === 'InnoDBCluster' ) {
        dbType = 'MySQL' ;
    }
    if ( !dbTypeStats[ dbType ] ) return ;
    dbTypeStats[ dbType ].hostCount++ ;
}

/**
 * Track MySQL-specific aggregates
 * @param {object} overviewData - The overviewData from AJAX response
 */
function trackMySQLAggregates( overviewData ) {
    var stats = dbTypeStats[ 'MySQL' ] ;
    var lr = overviewData[ 'longest_running' ] || 0 ;
    if ( lr > stats.longestRunning ) stats.longestRunning = lr ;
    stats.blocking += overviewData[ 'blocking' ] || 0 ;
    stats.blocked += overviewData[ 'blocked' ] || 0 ;
}

/**
 * Track Redis-specific aggregates
 * @param {object} redisOverview - The redisOverviewData from AJAX response
 */
function trackRedisAggregates( redisOverview ) {
    var stats = dbTypeStats[ 'Redis' ] ;
    stats.evicted += redisOverview[ 'evictedKeys' ] || 0 ;
    stats.rejected += redisOverview[ 'rejectedConnections' ] || 0 ;
    stats.blockedClients += redisOverview[ 'blockedClients' ] || 0 ;
}

/**
 * Update the DBType Overview boxes with current stats
 */
function updateDbTypeOverview() {
    for ( var dbType in dbTypeStats ) {
        if ( !dbTypeStats.hasOwnProperty( dbType ) ) continue ;
        var stats = dbTypeStats[ dbType ] ;
        var boxId = 'dbType' + dbType ;
        var box = document.getElementById( boxId ) ;
        if ( box ) {
            // Update tooltip
            var tooltip = dbType + ' Status: ' + stats.hostCount + ' hosts reporting' ;
            box.title = tooltip ;
        }

        // Update each level count: L9, L4, L3, L2, L1, L0
        var levels = [ 9, 4, 3, 2, 1, 0 ] ;
        for ( var i = 0 ; i < levels.length ; i++ ) {
            var level = levels[ i ] ;
            var el = document.getElementById( 'dbType' + dbType + 'L' + level ) ;
            if ( el ) {
                el.textContent = stats.levels[ level ] ;
            }
        }

        // Update total
        var totalEl = document.getElementById( 'dbType' + dbType + 'Total' ) ;
        if ( totalEl ) {
            totalEl.textContent = stats.hostCount + ' Total' ;
        }
    }
}

/**
 * Update the Scoreboard in the navbar with current stats
 * Shows worst level color with count of items at that level
 */
function updateScoreboard() {
    for ( var dbType in dbTypeStats ) {
        if ( !dbTypeStats.hasOwnProperty( dbType ) ) continue ;
        var stats = dbTypeStats[ dbType ] ;
        var scoreboardItem = document.getElementById( 'scoreboard' + dbType ) ;

        // Debug logging
        if ( debugScoreboard === '1' ) {
            console.log( '[scoreboard] updateScoreboard ' + dbType + ':', JSON.stringify( stats.levels ), 'hosts:', stats.hostCount ) ;
        }

        if ( scoreboardItem ) {
            // Build tooltip
            var tooltip = dbType + ' Status: ' + stats.hostCount + ' hosts reporting' ;
            scoreboardItem.title = tooltip ;
        }

        // Update each level count: L9, L4, L3, L2, L1, L0
        var levels = [ 9, 4, 3, 2, 1, 0 ] ;
        for ( var i = 0 ; i < levels.length ; i++ ) {
            var level = levels[ i ] ;
            var el = document.getElementById( 'scoreboard' + dbType + 'L' + level ) ;
            if ( el ) {
                el.textContent = stats.levels[ level ] ;
            }
        }

        // Update total
        var totalEl = document.getElementById( 'scoreboard' + dbType + 'Total' ) ;
        if ( totalEl ) {
            totalEl.textContent = stats.hostCount + ' Total' ;
        }
    }
}

/**
 * Show drill-down modal for a specific DBType and level
 * Shows which hosts contribute to that level count
 * @param {string} dbType - 'MySQL' or 'Redis'
 * @param {number} level - The level (0, 1, 2, 3, 4, or 9)
 */
function showLevelDrilldown( dbType, level ) {
    var stats = dbTypeStats[ dbType ] ;
    if ( !stats ) {
        console.warn( '[scoreboard] Unknown dbType: ' + dbType ) ;
        return ;
    }

    var levelHosts = stats.levelHosts[ level ] || {} ;
    var hostnames = Object.keys( levelHosts ) ;
    var count = stats.levels[ level ] || 0 ;

    // Level labels for display
    var levelLabels = {
        0: 'Level 0 (Green - Normal)',
        1: 'Level 1 (Light Green - Low)',
        2: 'Level 2 (Yellow - Info)',
        3: 'Level 3 (Orange - Warning)',
        4: 'Level 4 (Red - Critical)',
        9: 'Level 9 (Dark Red - Error/Unreachable)'
    } ;

    // Build modal title
    var title = dbType + ' - ' + levelLabels[ level ] ;

    // Build host list
    var listHtml = '' ;
    if ( hostnames.length === 0 ) {
        listHtml = '<p class="text-muted">No hosts at this level</p>' ;
    } else {
        // Sort by count descending, then by hostname
        hostnames.sort( function( a, b ) {
            var countDiff = levelHosts[ b ] - levelHosts[ a ] ;
            if ( countDiff !== 0 ) return countDiff ;
            return a.localeCompare( b ) ;
        } ) ;

        listHtml = '<table class="table table-condensed table-hover drilldown-table">' ;
        listHtml += '<thead><tr><th>Hostname</th><th class="text-right">Count</th></tr></thead>' ;
        listHtml += '<tbody>' ;
        for ( var i = 0 ; i < hostnames.length ; i++ ) {
            var hostname = hostnames[ i ] ;
            var hostCount = levelHosts[ hostname ] ;
            listHtml += '<tr class="level' + level + '">' ;
            listHtml += '<td><a href="#" onclick="scrollToHost(\'' + hostname.replace( /'/g, "\\'" ) + '\'); $(\'#drilldownModal\').modal(\'hide\'); return false;">' + hostname + '</a></td>' ;
            listHtml += '<td class="text-right">' + hostCount + '</td>' ;
            listHtml += '</tr>' ;
        }
        listHtml += '</tbody></table>' ;
    }

    // Update modal content
    $( '#drilldownModalTitle' ).text( title ) ;
    $( '#drilldownModalBody' ).html( listHtml ) ;
    $( '#drilldownModalCount' ).text( count + ' total' ) ;

    // Show modal
    $( '#drilldownModal' ).modal( 'show' ) ;
}

/**
 * Scroll to a specific host in the page
 * @param {string} hostname - The hostname to scroll to
 */
function scrollToHost( hostname ) {
    // Try to find the host in various tables
    var found = false ;
    $( 'td a' ).each( function() {
        if ( $( this ).text().trim() === hostname ) {
            var row = $( this ).closest( 'tr' ) ;
            if ( row.length ) {
                $( 'html, body' ).animate( {
                    scrollTop: row.offset().top - 100
                }, 300 ) ;
                // Highlight the row briefly
                row.addClass( 'highlight-row' ) ;
                setTimeout( function() { row.removeClass( 'highlight-row' ) ; }, 2000 ) ;
                found = true ;
                return false ;  // break
            }
        }
    } ) ;

    if ( !found ) {
        console.log( '[scoreboard] Could not find host in page: ' + hostname ) ;
    }
}

/**
 * Handle Redis host data from AJAX response
 * @param {number} i - Index
 * @param {object} item - Response data from AJAXgetaql.php
 */
function redisCallback( i, item ) {
    // Skip if not Redis data
    if ( item[ 'dbType' ] !== 'Redis' ) return ;

    var redisOverview = item[ 'redisOverviewData' ] ;
    var slowlogData = item[ 'slowlogData' ] || [] ;
    var hostname = item[ 'hostname' ] ;
    var hostId = item[ 'hostId' ] ;
    var hostGroups = item[ 'hostGroups' ] || [] ;
    var maintenanceInfo = item[ 'maintenanceInfo' ] ;

    // Track for klaxon silencing
    if ( typeof window.hostIdMap === 'undefined' ) { window.hostIdMap = {} ; }
    if ( typeof window.hostGroupMap === 'undefined' ) { window.hostGroupMap = {} ; }
    if ( typeof window.hostsInMaintenance === 'undefined' ) { window.hostsInMaintenance = {} ; }
    window.hostIdMap[ hostname ] = hostId ;
    window.hostGroupMap[ hostname ] = hostGroups ;
    window.hostsInMaintenance[ hostname ] = ( maintenanceInfo && maintenanceInfo.active ) ? true : false ;

    // Handle error response
    if ( typeof item[ 'error_output' ] !== 'undefined' ) {
        var errorRow = '<tr class="level9"><td>' + hostname + '</td>'
                     + '<td colspan="11" class="errorNotice">' + item[ 'error_output' ] + '</td></tr>' ;
        $( errorRow ).appendTo( '#fullredisoverviewtbodyid' ) ;
        $( errorRow ).prependTo( '#nwredisoverviewtbodyid' ) ;
        trackHostByDbType( 'Redis' ) ;
        trackLevelByDbType( 'Redis', 9, 1, hostname ) ;
        return ;
    }

    if ( typeof redisOverview === 'undefined' ) return ;

    var level = redisOverview[ 'level' ] || 0 ;

    // Track stats for scoreboard
    trackHostByDbType( 'Redis' ) ;
    trackLevelByDbType( 'Redis', level, 1, hostname ) ;
    trackRedisAggregates( redisOverview ) ;

    // Build server link with management icons
    var serverLink = '<a href="?hosts[]=' + hostname + debugString + '">' + hostname + '</a>' ;
    if ( maintenanceInfo && maintenanceInfo.active ) {
        var mwType = ( maintenanceInfo.windowType === 'adhoc' ) ? 'Ad-hoc' : 'Scheduled' ;
        var icon = ( maintenanceInfo.windowType === 'adhoc' ) ? '&#128263;' : '&#128295;' ;
        serverLink += ' <span class="maintenanceIndicator ' + maintenanceInfo.windowType
            + '" title="' + mwType + ' maintenance">' + icon + '</span>' ;
    }
    if ( hostId ) {
        serverLink += ' <a href="#" onclick="openSilenceModal(\'host\', ' + hostId + ', \'' + hostname.replace(/'/g, "\\'") + '\'); return false;"'
            + ' title="Silence alerts for this host" class="silence-link">&#128263;</a>' ;
        serverLink += ' <a href="manageData.php?data=MaintenanceWindows&preselect=host&preselectId=' + hostId + '"'
            + ' title="Manage maintenance windows" class="maintenance-link">&#9881;</a>' ;
    }

    // Build overview row
    var levelClass = 'level' + level ;
    var memPctClass = redisOverview[ 'memoryPct' ] > 80 ? ' class="level3"' : '' ;
    var blockedClass = redisOverview[ 'blockedClients' ] > 0 ? ' class="level2"' : '' ;
    var hitClass = redisOverview[ 'hitRatio' ] < 90 ? ' class="level2"' : '' ;
    var evictedClass = redisOverview[ 'evictedKeys' ] > 0 ? ' class="level4"' : '' ;
    var rejectedClass = redisOverview[ 'rejectedConnections' ] > 0 ? ' class="level4"' : '' ;
    var fragClass = redisOverview[ 'fragmentationRatio' ] > 1.5 ? ' class="level3"' : '' ;

    var overviewRow = '<tr class="' + levelClass + '">'
        + '<td>' + serverLink + '</td>'
        + '<td>' + ( redisOverview[ 'version' ] || '-' ) + '</td>'
        + '<td>' + ( redisOverview[ 'uptimeHuman' ] || '-' ) + '</td>'
        + '<td>' + ( redisOverview[ 'usedMemoryHuman' ] || '-' ) + '</td>'
        + '<td' + memPctClass + '>' + ( redisOverview[ 'memoryPct' ] || 0 ) + '%</td>'
        + '<td>' + ( redisOverview[ 'connectedClients' ] || 0 ) + '</td>'
        + '<td' + blockedClass + '>' + ( redisOverview[ 'blockedClients' ] || 0 ) + '</td>'
        + '<td' + hitClass + '>' + ( redisOverview[ 'hitRatio' ] || 0 ) + '%</td>'
        + '<td' + evictedClass + '>' + ( redisOverview[ 'evictedKeys' ] || 0 ) + '</td>'
        + '<td' + rejectedClass + '>' + ( redisOverview[ 'rejectedConnections' ] || 0 ) + '</td>'
        + '<td' + fragClass + '>' + ( redisOverview[ 'fragmentationRatio' ] || '-' ) + '</td>'
        + '<td>' + level + '</td>'
        + '</tr>' ;

    $( overviewRow ).appendTo( '#fullredisoverviewtbodyid' ) ;
    if ( hasIssues ) {
        $( overviewRow ).appendTo( '#nwredisoverviewtbodyid' ) ;
    }

    // Build slowlog rows
    if ( slowlogData.length > 0 ) {
        for ( var j = 0; j < slowlogData.length; j++ ) {
            var entry = slowlogData[ j ] ;
            var slLevelClass = 'level' + ( entry[ 'level' ] || 0 ) ;
            var slowlogRow = '<tr class="' + slLevelClass + '">'
                + '<td>' + hostname + '</td>'
                + '<td>' + ( entry[ 'timestampHuman' ] || '-' ) + '</td>'
                + '<td>' + ( entry[ 'durationMs' ] || 0 ) + ' ms</td>'
                + '<td>' + ( entry[ 'command' ] || '-' ) + '</td>'
                + '<td>' + ( entry[ 'level' ] || 0 ) + '</td>'
                + '</tr>' ;
            $( slowlogRow ).appendTo( '#fullredisslowlogtbodyid' ) ;
            if ( entry[ 'level' ] >= 2 ) {
                $( slowlogRow ).appendTo( '#nwredisslowlogtbodyid' ) ;
            }
        }
    }
}

///////////////////////////////////////////////////////////////////////////////

/**
 * Make sure that the passed value is valid for the proposed condition. If
 * isRequired is true, dateString must not be blank or null as well as being
 * a valid date string. If isRequired is false, dateString may be blank or null,
 * but when it's not, it must be a valid date string. A valid date string looks
 * like YYYY-MM-DD
 *
 * @param dateString {String}
 * @param isRequired {Boolean}
 * @returns {Boolean}
 */
function isDateValid( dateString, isRequired ) {
    var regex = /^\d\d\d\d-\d\d-\d\d$/ ;
    var retVal = true ;

    if ( ! isRequired ) {
        if ( ( null == dateString ) || ( '' == dateString ) ) {
            return true ;
        }
    }
    else {
        retVal = ( ( null !== dateString ) && ( '' !== dateString ) ) ;
    }
    retVal = ( retVal && ( null !== dateString.match( regex ) ) ) ;
    if ( retVal ) {
        var daysInMonths = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ] ;
        var yr = parseInt( dateString.substring( 0, 4 ) ) ;
        var mo = parseInt( dateString.substring( 5, 7 ) ) ;
        var da = parseInt( dateString.substring( 8, 10 ) ) ;
        if ( ( yr % 4 ) && ( ( yr % 400 ) || ! ( yr % 100 ) ) ) {
                daysInMonths[ 1 ]++ ; // Leap day!
        }
        if  ( ( yr < 2000 ) || ( yr > 2038 )
           || ( mo < 1 ) || ( mo > 12 )
           || ( da < 1 ) || ( da > daysInMonths[ mo ] )
            ) {
            retVal = false ;
        }
    }
    return ( retVal ) ;
} 

///////////////////////////////////////////////////////////////////////////////

/**
 * Make sure that the passed value is valid for the proposed condition. If
 * isRequired is true, dateTimeString must not be blank or null as well as being
 * a valid date and time string. If isRequired is false, dateTimeString may be
 * blank or null, but when it's not, it must be a valid date and time string. A
 * valid date and time string looks like 'YYYY-MM-DD hh:mm:ss'
 *
 * @param dateTimeString {String}
 * @param isRequired {Boolean}
 * @returns {Boolean}
 */
function isDateTimeValid( dateTimeString, isRequired ) {
    var regex = /^\d\d\d\d-\d\d-\d\d\s\d\d:\d\d:\d\d$/ ;
    var retVal = true ;
    if ( ! isRequired ) {
        if ( ( null == dateTimeString ) || ( '' == dateTimeString ) ) {
            return true ;
        }
    }
    else {
        retVal = ( ( null !== dateTimeString ) && ( '' !== dateTimeString ) ) ;
    }
    retVal = ( retVal && ( null !== dateTimeString.match( regex ) ) ) ;
    if ( retVal ) {
        var daysInMonths = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ] ;
        var yr = parseInt( dateTimeString.substring( 0, 4 ) ) ;
        var mo = parseInt( dateTimeString.substring( 5, 7 ) ) ;
        var da = parseInt( dateTimeString.substring( 8, 10 ) ) ;
        var hr = parseInt( dateTimeString.substring( 11, 13 ) ) ;
        var mi = parseInt( dateTimeString.substring( 14, 16 ) ) ;
        var se = parseInt( dateTimeString.substring( 17, 19 ) ) ;
        if ( ( yr % 4 ) && ( ( yr % 400 ) || ! ( yr % 100 ) ) ) {
            daysInMonths[ 1 ]++ ; // Leap day!
        }
        if  ( ( yr < 2000 ) || ( yr > 2038 )
           || ( mo < 1 ) || ( mo > 12 )
           || ( da < 1 ) || ( da > daysInMonths[ mo ] )
           || ( hr < 0 ) || ( hr > 23 )
           || ( mi < 0 ) || ( mi > 59 )
           || ( se < 0 ) || ( se > 59 )
            ) {
            retVal = false ;
        }
    }
    return ( retVal ) ;
}

///////////////////////////////////////////////////////////////////////////////

function isNumeric( n ) {
    return ! isNaN( parseFloat( n ) ) && isFinite( n ) ;
}

/**
 * Load the results of an AJAX call into the target ID
 *
 * @param uri        URI
 * @param data        Data in URL-encoded format
 * @param targetId    The response will be loaded here.
 * @param isAsync    Load the response asynchronously.
 * @param callback    A user-defined routine to handle the results.
 */
function doLoadAjaxJsonResultWithCallback( uri, data, targetId, isAsync, callback ) {
    var xhttp = new XMLHttpRequest() ;
    xhttp.onreadystatechange = function() {
        if ( xhttp.readyState == 4 && xhttp.status == 200 ) {
            callback( xhttp, targetId ) ;
        }
    } ;
    xhttp.open( "POST", uri, isAsync ) ;
    xhttp.setRequestHeader( "Content-type", "application/x-www-form-urlencoded" ) ;
    xhttp.send( data ) ;
}

///////////////////////////////////////////////////////////////////////////////

/**
 * Dynamically remove a row that was created.
 *
 * @param rowId
 * @returns {Boolean}
 */
function deleteRow( rowId ) {
    var row = document.getElementById( rowId ) ;
    row.parentNode.removeChild( row ) ;
    return false ;
}


///////////////////////////////////////////////////////////////////////////////

function sortProcessTable() {
    var table, rows, switching, i, x, y, shouldSwitch ;
    table = document.getElementById("fullprocesstbodyid") ;
    switching = true ;
    rows = table.getElementsByTagName("tr") ;
    rowcount = rows.length;
    while ( switching ) {
        switching = false ;
        for (i = 0; i < (rowcount - 1); i++) {
            shouldSwitch = false ;
            x1 = rows[i].getElementsByTagName("td")[1] ;
            x2 = rows[i].getElementsByTagName("td")[7] ;
            y1 = rows[i + 1].getElementsByTagName("td")[1] ;
            y2 = rows[i + 1].getElementsByTagName("td")[7] ;
            if  ( ( Number(x1.innerHTML) < Number(y1.innerHTML) )
               ||  ( Number(x1.innerHTML) == Number(y1.innerHTML)
                  && Number(x2.innerHTML) < Number(y2.innerHTML)
                   )
                ) {
                shouldSwitch = true ;
                break ;
            }
        }
        if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]) ;
            switching = true ;
        }
    }
}

///////////////////////////////////////////////////////////////////////////////

function flipFlop() {
    if ( $(this).hasClass( "less" ) ) {
        $(this).removeClass( "less" );
        $(this).html( "more" ) ;
    }
    else {
        $(this).addClass( "less" ) ;
        $(this).html( "less" ) ;
    }
    $(this).parent().prev().toggle() ;
    $(this).prev().toggle() ;

    e = window.event ;
    e.preventDefault() ;
    return false ;
}

///////////////////////////////////////////////////////////////////////////////

var host_counts = [ [ 'Label', 'Link', 'Count' ] ] ;
var host_count = [] ;
var db_counts = [ [ 'Label', 'Count' ] ] ;
var db_count = [] ;
var base_counts = {
                  'Blank'     : 0
                , 'Duplicate' : 0
                , 'Error'     : 0
                , 'Level4'    : 0
                , 'Level3'    : 0
                , 'Level2'    : 0
                , 'Level1'    : 0
                , 'Level0'    : 0
                , 'RO'        : 0
                , 'RW'        : 0
                , 'Similar'   : 0
                , 'Unique'    : 0
                } ;

// Version summary data: { 'version_string': { count: N, hosts: ['host1', 'host2', ...] } }
var versionData = {} ;

///////////////////////////////////////////////////////////////////////////////

// Update version summary table with current versionData
function updateVersionSummary() {
    var tbody = $("#versionsummarytbodyid") ;
    tbody.empty() ;

    // Sort versions by count descending
    var versions = Object.keys(versionData).sort(function(a, b) {
        return versionData[b].count - versionData[a].count ;
    }) ;

    if (versions.length === 0) {
        tbody.append('<tr><td colspan="2">No version data available</td></tr>') ;
        return ;
    }

    for (var i = 0; i < versions.length; i++) {
        var version = versions[i] ;
        var data = versionData[version] ;
        var hostList = data.hosts.join(', ') ;
        var row = '<tr>'
                + '<td>' + version + '</td>'
                + '<td title="' + hostList + '" class="help-cursor">' + data.count + '</td>'
                + '</tr>' ;
        tbody.append(row) ;
    }
}

///////////////////////////////////////////////////////////////////////////////

function myCallback( i, item ) {
    const showChars       = 40;
    var itemNo            = 0;
    var level             = -1;
    var info              = '' ;
    var dupeState         = '' ;
    var server            = '' ;
    var serverLinkAddress = '' ;
    var first             = '' ;
    var last              = '' ;
    var myRow             = '' ;
    var overviewRow       = '' ;
    var myUrl             = '' ;
    var overviewData      = item[ 'overviewData' ] ;
    var slaveData         = item[ 'slaveData' ] ;

    // Debug: log lock detection data if present
    if ( debugLocks === '1' ) {
        console.log( 'AQL Lock Debug for ' + item[ 'hostname' ] + ':',
                     'lockWaitCount=' + ( item[ 'debugLockWaitCount' ] || 0 ),
                     'waitingThreadsInOutput=' + ( item[ 'debugWaitingThreadsInOutput' ] || 0 ),
                     'cacheType=' + ( item[ 'debugBlockingCacheType' ] || 'unknown' ),
                     'tableLockQuery=', item[ 'debugTableLockQuery' ] || 'NOT SET',
                     'lockWaitData=', item[ 'debugLockWaitData' ] || 'NOT SET',
                     'openTablesWithLocks=', item[ 'debugOpenTablesWithLocks' ] || [],
                     'waitingTables=', item[ 'debugWaitingTables' ] || [],
                     'blockingCache=', item[ 'debugBlockingCache' ] || [] ) ;
    }

    // We get other types of responses here as well. Ignore the noise.
    // If we have an error, assume it's critical and show it at the top of the process listing.
    if ( typeof item[ 'error_output' ] !== 'undefined' ) {
        var errorServer = item[ 'hostname' ] ;
        var errorHostId = item[ 'hostId' ] ;
        var errorHostGroups = item[ 'hostGroups' ] || [] ;
        var errorMaintenanceInfo = item[ 'maintenanceInfo' ] ;

        // Track for klaxon silencing even on errors
        if ( typeof window.hostIdMap === 'undefined' ) { window.hostIdMap = {} ; }
        if ( typeof window.hostGroupMap === 'undefined' ) { window.hostGroupMap = {} ; }
        if ( typeof window.hostsInMaintenance === 'undefined' ) { window.hostsInMaintenance = {} ; }
        window.hostIdMap[ errorServer ] = errorHostId ;
        window.hostGroupMap[ errorServer ] = errorHostGroups ;
        window.hostsInMaintenance[ errorServer ] = ( errorMaintenanceInfo && errorMaintenanceInfo.active ) ? true : false ;

        // Check recovery - error state resets healthy count
        if ( errorHostId ) {
            checkHostRecovery( errorHostId, 9, true ) ; // isError = true resets count
        }

        // Build maintenance indicator for error row (if in maintenance)
        var errorMaintenanceIndicator = '' ;
        if ( errorMaintenanceInfo && errorMaintenanceInfo.active ) {
            var mwType = ( errorMaintenanceInfo.windowType === 'adhoc' ) ? 'Ad-hoc' : 'Scheduled' ;
            var mwExpiry = errorMaintenanceInfo.expiresAt || ( errorMaintenanceInfo.timeWindow || 'per schedule' ) ;
            var mwDesc = errorMaintenanceInfo.description || '' ;
            var tooltipText = mwType + ' maintenance' ;
            if ( errorMaintenanceInfo.targetType === 'group' ) {
                tooltipText += ' (via group: ' + errorMaintenanceInfo.groupName + ')' ;
            }
            if ( mwExpiry ) { tooltipText += '&#10;Expires: ' + mwExpiry ; }
            if ( mwDesc ) { tooltipText += '&#10;Note: ' + mwDesc ; }
            var icon = ( errorMaintenanceInfo.windowType === 'adhoc' ) ? '&#128263;' : '&#128295;' ;
            errorMaintenanceIndicator = ' <span class="maintenanceIndicator ' + errorMaintenanceInfo.windowType
                + '" title="' + tooltipText.replace( /"/g, '&quot;' ) + '">' + icon + '</span>' ;
        }

        // Build silence icons for error row
        var errorSilenceIcons = '' ;
        if ( errorHostId ) {
            errorSilenceIcons = ' <a href="#" onclick="openSilenceModal(\'host\', ' + errorHostId + ', \'' + errorServer.replace(/'/g, "\\'") + '\'); return false;" title="Silence this host" class="silence-link">🔇</a>'
                              + ' <a href="manageData.php?data=MaintenanceWindows&preselect=host&preselectId=' + errorHostId + '" title="Manage maintenance windows" class="maintenance-link">⚙</a>' ;
        }

        var myRow = "<tr data-hostname=\"" + errorServer + "\"><td class=\"errorNotice\">" + errorServer + errorMaintenanceIndicator + errorSilenceIcons
                  + "</td><td class=\"errorNotice\">9</td><td colspan=\"13\" class=\"errorNotice\">" + item[ 'error_output' ]
                  + "</td></tr>" ;
        $(myRow).prependTo( "#nwprocesstbodyid" ) ;
        $(myRow).prependTo( "#fullprocesstbodyid" ) ;

        // Add error row to Status Overview tables too
        var overviewErrorRow = "<tr data-hostname=\"" + errorServer + "\"><td class=\"errorNotice\">" + errorServer + errorMaintenanceIndicator + errorSilenceIcons
                  + "</td><td colspan=\"20\" class=\"errorNotice\">" + item[ 'error_output' ]
                  + "</td></tr>" ;
        $(overviewErrorRow).prependTo( "#nwoverviewtbodyid" ) ;
        $(overviewErrorRow).prependTo( "#fulloverviewtbodyid" ) ;

        // Track MySQL error for DBType scoreboard (skip if this is Redis data)
        if ( item[ 'dbType' ] !== 'Redis' ) {
            trackHostByDbType( 'MySQL' ) ;
            trackLevelByDbType( 'MySQL', 9, 1, errorServer ) ;
        }
    } else {
        if ( typeof overviewData !== 'undefined' ) {
            var server            = item[ 'hostname' ] ;
            var serverLinkAddress = '<a href="?hosts[]=' + server + debugString + '">' + server + '</a>' ;

            // Check for maintenance window and add indicator
            var maintenanceInfo = item[ 'maintenanceInfo' ] ;
            var hostId = item[ 'hostId' ] ;
            var hostGroups = item[ 'hostGroups' ] || [] ;

            // Initialize tracking objects for klaxon.js
            if ( typeof window.hostsInMaintenance === 'undefined' ) {
                window.hostsInMaintenance = {} ;
            }
            if ( typeof window.hostIdMap === 'undefined' ) {
                window.hostIdMap = {} ;
            }
            if ( typeof window.hostGroupMap === 'undefined' ) {
                window.hostGroupMap = {} ;
            }

            window.hostsInMaintenance[ server ] = ( maintenanceInfo && maintenanceInfo.active ) ? true : false ;
            window.hostIdMap[ server ] = hostId ;
            window.hostGroupMap[ server ] = hostGroups ;

            // Check for auto-recovery (if host is locally silenced with autoRecover)
            if ( hostId ) {
                // Determine current level (worst/highest level with queries)
                var currentLevel = 0 ;
                if ( overviewData[ 'level9' ] > 0 ) currentLevel = 9 ;
                else if ( overviewData[ 'level4' ] > 0 ) currentLevel = 4 ;
                else if ( overviewData[ 'level3' ] > 0 ) currentLevel = 3 ;
                else if ( overviewData[ 'level2' ] > 0 ) currentLevel = 2 ;
                else if ( overviewData[ 'level1' ] > 0 ) currentLevel = 1 ;
                // level0 or no queries = 0
                checkHostRecovery( hostId, currentLevel, false ) ;
            }

            if ( maintenanceInfo && maintenanceInfo.active ) {
                var mwType = ( maintenanceInfo.windowType === 'adhoc' ) ? 'Ad-hoc' : 'Scheduled' ;
                var mwExpiry = maintenanceInfo.expiresAt || ( maintenanceInfo.timeWindow || 'per schedule' ) ;
                var mwDesc = maintenanceInfo.description || '' ;
                var tooltipText = mwType + ' maintenance' ;
                if ( maintenanceInfo.targetType === 'group' ) {
                    tooltipText += ' (via group: ' + maintenanceInfo.groupName + ')' ;
                }
                if ( mwExpiry ) {
                    tooltipText += '&#10;Expires: ' + mwExpiry ;
                }
                if ( mwDesc ) {
                    tooltipText += '&#10;Note: ' + mwDesc ;
                }
                var icon = ( maintenanceInfo.windowType === 'adhoc' ) ? '&#128263;' : '&#128295;' ; // muted speaker / wrench
                serverLinkAddress += ' <span class="maintenanceIndicator ' + maintenanceInfo.windowType
                    + '" title="' + tooltipText.replace( /"/g, '&quot;' ) + '">' + icon + '</span>' ;
            }

            // Add quick management links if hostId is available
            if ( hostId ) {
                // Silence icon - opens modal for quick silencing
                serverLinkAddress += ' <a href="#" onclick="openSilenceModal(\'host\', ' + hostId + ', \'' + server.replace(/'/g, "\\'") + '\'); return false;"'
                    + ' title="Silence alerts for this host" class="silence-link">&#128263;</a>' ;
                // Gear icon - links to manage maintenance windows with host preselected
                serverLinkAddress += ' <a href="manageData.php?data=MaintenanceWindows&preselect=host&preselectId=' + hostId + '"'
                    + ' title="Manage maintenance windows" class="maintenance-link">&#9881;</a>' ;
            }

            var l0                = ( overviewData[ 'level0' ] > 0 ) ? ' class="level0"' : '' ;
            var l1                = ( overviewData[ 'level1' ] > 0 ) ? ' class="level1"' : '' ;
            var l2                = ( overviewData[ 'level2' ] > 0 ) ? ' class="level2"' : '' ;
            var l3                = ( overviewData[ 'level3' ] > 0 ) ? ' class="level3"' : '' ;
            var l4                = ( overviewData[ 'level4' ] > 0 ) ? ' class="level4"' : '' ;
            var l9                = ( overviewData[ 'level9' ] > 0 ) ? ' class="level9"' : '' ;
            var ro                = ( overviewData[ 'ro' ] > 0 ) ? ' class="readOnly"' : '' ;
            var rw                = ( overviewData[ 'rw' ] > 0 ) ? ' class="readWrite"' : '' ;
            var blocking          = ( overviewData[ 'blocking' ] > 0 ) ? ' class="blocking"' : '' ;
            var blocked           = ( overviewData[ 'blocked' ] > 0 ) ? ' class="blocked"' : '' ;
            var bl                = ( overviewData[ 'blank' ] > 0 ) ? ' class="Blank"' : '' ;
            var un                = ( overviewData[ 'unique' ] > 0 ) ? ' class="Unique"' : '' ;
            var si                = ( overviewData[ 'similar' ] > 0 ) ? ' class="Similar"' : '' ;
            var du                = ( overviewData[ 'dupe' ] > 0 ) ? ' class="Duplicate"' : '' ;
            var connPct = ( overviewData[ 'maxConnections' ] > 0 )
                        ? Math.round( overviewData[ 'threadsConnected' ] / overviewData[ 'maxConnections' ] * 100 )
                        : 0 ;
            var myRow             = "<tr><td>" + serverLinkAddress
                                  + "</td><td>" + overviewData[ 'version' ]
                                  + "</td><td>" + overviewData[ 'longest_running' ]
                                  + "</td><td>" + overviewData[ 'aQPS' ]
                                  + "</td><td>" + overviewData[ 'threadsRunning' ]
                                  + "</td><td>" + connPct + "%"
                                  + "</td><td>" + overviewData[ 'uptime' ]
                                  + "</td><td" + l0 + ">" + overviewData[ 'level0' ]
                                  + "</td><td" + l1 + ">" + overviewData[ 'level1' ]
                                  + "</td><td" + l2 + ">" + overviewData[ 'level2' ]
                                  + "</td><td" + l3 + ">" + overviewData[ 'level3' ]
                                  + "</td><td" + l4 + ">" + overviewData[ 'level4' ]
                                  + "</td><td" + l9 + ">" + overviewData[ 'level9' ]
                                  + "</td><td" + ro + ">" + overviewData[ 'ro' ]
                                  + "</td><td" + rw + ">" + overviewData[ 'rw' ]
                                  + "</td><td" + blocking + ">" + overviewData[ 'blocking' ]
                                  + "</td><td" + blocked + ">" + overviewData[ 'blocked' ]
                                  + "</td><td" + bl + ">" + overviewData[ 'blank' ]
                                  + "</td><td" + du + ">" + overviewData[ 'duplicate' ]
                                  + "</td><td" + si + ">" + overviewData[ 'similar' ]
                                  + "</td><td>" + overviewData[ 'threads' ]
                                  + "</td><td" + un + ">" + overviewData[ 'unique' ]
                                  + "</td></tr>" ;
            $(myRow).appendTo( "#fulloverviewtbodyid" ) ;
            var sum = overviewData[ 'level2' ]
                    + overviewData[ 'level3' ]
                    + overviewData[ 'level4' ]
                    + overviewData[ 'level9' ]
                    + overviewData[ 'blocking' ]
                    + overviewData[ 'blocked' ]
                    ;
            if ( sum > 0 ) {
                $(myRow).appendTo( '#nwoverviewtbodyid' ) ;
            }

            // Track version data for version summary table
            var version = overviewData[ 'version' ] ;
            if ( version ) {
                if ( typeof versionData[ version ] === 'undefined' ) {
                    versionData[ version ] = { count: 0, hosts: [] } ;
                }
                // Only add host if not already tracked (avoid duplicates on refresh)
                if ( versionData[ version ].hosts.indexOf( server ) === -1 ) {
                    versionData[ version ].count++ ;
                    versionData[ version ].hosts.push( server ) ;
                }
                updateVersionSummary() ;
            }

            // Track MySQL host for DBType scoreboard
            trackHostByDbType( 'MySQL' ) ;
            trackLevelByDbType( 'MySQL', 0, overviewData[ 'level0' ] || 0, server ) ;
            trackLevelByDbType( 'MySQL', 1, overviewData[ 'level1' ] || 0, server ) ;
            trackLevelByDbType( 'MySQL', 2, overviewData[ 'level2' ] || 0, server ) ;
            trackLevelByDbType( 'MySQL', 3, overviewData[ 'level3' ] || 0, server ) ;
            trackLevelByDbType( 'MySQL', 4, overviewData[ 'level4' ] || 0, server ) ;
            trackLevelByDbType( 'MySQL', 9, overviewData[ 'level9' ] || 0, server ) ;
            trackMySQLAggregates( overviewData ) ;
        }
        if ( ( typeof slaveData !== 'undefined' ) && ( typeof slaveData[ 0 ] !== 'undefined' ) ) {
            var server            = item[ 'hostname' ] ;
            var hostId            = item[ 'hostId' ] ;
            var serverLinkAddress = '<a href="?hosts[]=' + server + debugString + '">' + server + '</a>' ;

            // Add quick management links if hostId is available (same as overview)
            if ( hostId ) {
                serverLinkAddress += ' <a href="#" onclick="openSilenceModal(\'host\', ' + hostId + ', \'' + server.replace(/'/g, "\\'") + '\'); return false;"'
                    + ' title="Silence alerts for this host" class="silence-link">&#128263;</a>' ;
                serverLinkAddress += ' <a href="manageData.php?data=MaintenanceWindows&preselect=host&preselectId=' + hostId + '"'
                    + ' title="Manage maintenance windows" class="maintenance-link">&#9881;</a>' ;
            }

            for ( itemNo=0; itemNo<slaveData.length; itemNo++ ) {
                var sbmClass = ( 0 < slaveData[ itemNo ][ 'Seconds_Behind_Master' ] ) ? ' class="level4"' : '' ;
                var sioClass = ( 'No' == slaveData[ itemNo ][ 'Slave_IO_Running'] ) ? ' class="errorNotice"' : '' ;
                var sqlClass = ( 'No' == slaveData[ itemNo ][ 'Slave_SQL_Running'] ) ? ' class="errorNotice"' : '' ;
                var sieClass = ( '' !== slaveData[ itemNo ][ 'Last_IO_Error'] ) ? ' class="errorNotice"' : '' ;
                var sqeClass = ( '' !== slaveData[ itemNo ][ 'Last_SQL_Error'] ) ? ' class="errorNotice"' : '' ;
                var myRow = "<tr><td>" + serverLinkAddress
                          + "</td><td>" + slaveData[ itemNo ][ 'Connection_name']
                          + "</td><td>" + slaveData[ itemNo ][ 'Master_Host' ]
                          + ':' + slaveData[ itemNo ][ 'Master_Port' ]
                          + "</td><td" + sbmClass + ">" + slaveData[ itemNo ][ 'Seconds_Behind_Master']
                          + "</td><td" + sioClass + ">" + slaveData[ itemNo ][ 'Slave_IO_Running']
                          + "</td><td" + sqlClass + ">" + slaveData[ itemNo ][ 'Slave_SQL_Running']
                          + "</td><td" + sieClass + ">" + slaveData[ itemNo ][ 'Last_IO_Error']
                          + "</td><td" + sqeClass + ">" + slaveData[ itemNo ][ 'Last_SQL_Error']
                          + "</td></tr>" ;
                $(myRow).appendTo( "#fullslavetbodyid" ) ;
                if ( ( 0 < slaveData[ itemNo ][ 'Seconds_Behind_Master' ] )
                  || ( 'Yes' !== slaveData[ itemNo ][ 'Slave_IO_Running' ] )
                  || ( 'Yes' !== slaveData[ itemNo ][ 'Slave_SQL_Running' ] )
                  || ( '' !== slaveData[ itemNo ][ 'Last_IO_Error' ] )
                  || ( '' !== slaveData[ itemNo ][ 'Last_SQL_Error' ] )
                   ) {
                    $(myRow).appendTo( '#nwslavetbodyid' ) ;
                }
          }
        }
        if (    ( typeof item[ 'result' ] !== 'undefined' )
             && ( typeof item[ 'result' ][ 0 ] !== 'undefined' )
             && ( typeof item[ 'result' ][ 0 ][ 'level' ] !== 'undefined' )
           ) {
            // Assumption - if we can get any rows from the server, we should be able to get all of the rows.
            server            = item[ 'result' ][ 0 ][ 'server' ] ;
            var hostIdProc    = item[ 'hostId' ] ;
            serverLinkAddress = '<a href="?hosts[]=' + server + debugString + '">' + server + '</a>' ;

            // Add quick management links if hostId is available (same as overview)
            if ( hostIdProc ) {
                serverLinkAddress += ' <a href="#" onclick="openSilenceModal(\'host\', ' + hostIdProc + ', \'' + server.replace(/'/g, "\\'") + '\'); return false;"'
                    + ' title="Silence alerts for this host" class="silence-link">&#128263;</a>' ;
                serverLinkAddress += ' <a href="manageData.php?data=MaintenanceWindows&preselect=host&preselectId=' + hostIdProc + '"'
                    + ' title="Manage maintenance windows" class="maintenance-link">&#9881;</a>' ;
            }

            if ( typeof host_count[ server ] === 'undefined' ) {
                host_count[ server ] = 0 ;
            }
            var prevLevel = -1 ;
            var levelShadeAlt = false ;
            for ( itemNo=0; itemNo<item[ 'result' ].length; itemNo++ ) {
                host_count[ server ] ++ ;
                level = item[ 'result' ][ itemNo ][ 'level' ] ;
                // Alternate shades for consecutive rows of same level
                if ( level === prevLevel ) {
                    levelShadeAlt = !levelShadeAlt ;
                } else {
                    levelShadeAlt = false ;
                }
                prevLevel = level ;
                if ( 9 == level ) {
                    base_counts['Error'] ++ ;
                }
                else {
                    base_counts['Level' + level] ++ ;
                }
                if (item['result'][itemNo]['readOnly'] == "0") {
                    base_counts[ 'RW' ] ++ ;
                }
                else {
                    base_counts[ 'RO' ] ++ ;
                }
                dupeState = item[ 'result' ][ itemNo ][ 'dupeState' ] ;
                base_counts[ dupeState ] ++ ;
                info      = item[ 'result' ][ itemNo ][ 'info' ] ;
                db        = item[ 'result' ][ itemNo ][ 'db' ] ;
                if ( typeof db_count[ db ] === 'undefined' ) {
                    db_count[ db ] = 0 ;
                }
                db_count[ db ] ++ ;
                if ( info.length > showChars + 8 ) {
                    var first = info.substr( 0, showChars ) ;
                    var last  = info.substr( showChars, info.length - showChars ) ;
                    info      = first
                            + '<span class="moreelipses">...</span>'
                            + '<span class="morecontent"><span>'
                            + last
                            + '</span>&nbsp;&nbsp;<a href="" class="morelink">'
                            + 'more</a></span>' ;
                }
                // Build lock status indicator
                var blockInfo = item[ 'result' ][ itemNo ][ 'blockInfo' ] ;
                var lockStatus = '' ;
                var lockClass = '' ;
                if ( blockInfo ) {
                    if ( blockInfo.isBlocked ) {
                        var fromCache = blockInfo.fromCache ? ' (from recent history)' : '' ;
                        var blockedTitle = 'Blocked by thread(s) '
                                        + ( blockInfo.blockedBy ? blockInfo.blockedBy.join(', ') : '?' )
                                        + fromCache
                                        + ' for ' + blockInfo.waitSeconds + 's on '
                                        + ( blockInfo.lockedTable || 'unknown' ) ;
                        // Add blocker query text if available
                        if ( blockInfo.blockerQueries ) {
                            for ( var blockerId in blockInfo.blockerQueries ) {
                                if ( blockInfo.blockerQueries.hasOwnProperty( blockerId ) ) {
                                    var blockerQuery = blockInfo.blockerQueries[ blockerId ] ;
                                    if ( blockerQuery ) {
                                        blockedTitle += '\n\nBlocker #' + blockerId + ' query' + fromCache + ':\n' + blockerQuery ;
                                    } else {
                                        blockedTitle += '\n\nBlocker #' + blockerId + ': (transaction holding lock, no active query)' ;
                                    }
                                }
                            }
                        }
                        lockStatus += '<span class="blockedIndicator" title="' + blockedTitle.replace(/"/g, '&quot;') + '">BLOCKED</span>' ;
                        lockClass = ' blocked' ;
                    }
                    if ( blockInfo.isBlocking ) {
                        var blockingCount = blockInfo.blocking ? blockInfo.blocking.length : 0 ;
                        lockStatus += '<span class="blockingIndicator" title="Blocking thread(s) '
                                   + ( blockInfo.blocking ? blockInfo.blocking.join(', ') : '?' )
                                   + '">BLOCKING (' + blockingCount + ')</span>' ;
                        lockClass += ' blocking' ;
                    }
                }
                var levelClass = ( level === 9 ? 'error' : 'level' + level ) + ( levelShadeAlt ? '-alt' : '' ) ;
                var myRow = "<tr class=\"" + levelClass + lockClass + "\">"
                          +      "<td class=\"comment more\">" + serverLinkAddress
                          + "</td><td align=\"center\">" + level
                          + "</td><td align=\"center\">" + item[ 'result' ][ itemNo ][ 'id'           ]
                          + "</td><td>" + item[ 'result' ][ itemNo ][ 'user'         ]
                          + "</td><td>" + item[ 'result' ][ itemNo ][ 'host'         ]
                          + "</td><td>" + item[ 'result' ][ itemNo ][ 'db'           ]
                          + "</td><td>" + item[ 'result' ][ itemNo ][ 'command'      ]
                          + "</td><td align=\"center\">" + item[ 'result' ][ itemNo ][ 'time'         ]
                          + "</td><td align=\"center\">" + item[ 'result' ][ itemNo ][ 'friendlyTime' ]
                          + "</td><td>" + item[ 'result' ][ itemNo ][ 'state'        ]
                          + "</td><td" + ( item[ 'result' ][ itemNo ][ 'readOnly'     ] == 0 ? ' class="readWrite">OFF' : ' class="readOnly">ON' )
                          + "</td><td class=\"" + dupeState + "\">" + dupeState
                          + "</td><td>" + lockStatus
                          + "</td><td class=\"comment more\">" + info
                          + "</td><td>" + modifyActionsForBlocking( item[ 'result' ][ itemNo ][ 'actions' ], blockInfo )
                          + "</td></tr>" ;
                $(myRow).appendTo( "#fullprocesstbodyid" ) ;
                // Show in Noteworthy if level > 1 OR if blocking other queries
                if ( level > 1 || ( blockInfo && blockInfo.isBlocking ) ) {
                    $(myRow).appendTo( "#nwprocesstbodyid" ) ;
                }
            }
        }
    }
}

///////////////////////////////////////////////////////////////////////////////

function urldecode(url) {
	if ( typeof url != "string" ) {
		return url ;
	}
	return decodeURIComponent( url.replace( /\+/g, ' ' ) ) ;
}

///////////////////////////////////////////////////////////////////////////////

function killProcOnHost( hostname, pid, user, fromHost, db, command, time, state, info ) {
    document.getElementById( 'm_server' ).innerHTML = hostname ;
    document.getElementById( 'm_pid' ).innerHTML = pid ;
    document.getElementById( 'm_user' ).innerHTML = user ;
    document.getElementById( 'm_host' ).innerHTML = fromHost ;
    document.getElementById( 'm_db' ).innerHTML = db ;
    document.getElementById( 'm_command' ).innerHTML = command ;
    document.getElementById( 'm_time' ).innerHTML = time ;
    document.getElementById( 'm_state' ).innerHTML = state ;
    document.getElementById( 'm_info' ).innerHTML = urldecode( info ) ;
    document.getElementById( 'i_server' ).value = hostname ;
    document.getElementById( 'i_pid' ).value = pid ;
    $( "#myModal" ).modal( 'show' ) ;
    return false ;
}

///////////////////////////////////////////////////////////////////////////////

// Normalize query for fingerprinting: replace strings with 'S', numbers with N
function normalizeQueryForHash(query) {
    return query
        .replace(/'[^']*'/g, "'S'")      // Single-quoted strings -> 'S'
        .replace(/"[^"]*"/g, '"S"')      // Double-quoted strings -> "S"
        .replace(/\b\d+\.?\d*\b/g, 'N'); // Numbers -> N
}

// Simple hash function (djb2)
function hashString(str) {
    var hash = 5381;
    for (var i = 0; i < str.length; i++) {
        hash = ((hash << 5) + hash) + str.charCodeAt(i);
        hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash).toString(16);
}

/**
 * Modify the actions HTML to include blocking count for blocking queries
 */
function modifyActionsForBlocking( actionsHtml, blockInfo ) {
    if ( !blockInfo || !blockInfo.isBlocking ) {
        return actionsHtml ;
    }
    var blockingCount = blockInfo.blocking ? blockInfo.blocking.length : 0 ;
    if ( blockingCount === 0 ) {
        return actionsHtml ;
    }
    // Add blocking count parameter to fileIssue call
    // fileIssue( hostname, ro, fromHost, user, db, time, safeUrl ) -> add blockingCount
    var modified = actionsHtml.replace(
        /fileIssue\(\s*([^)]+)\s*\)/,
        'fileIssue( $1, ' + blockingCount + ' )'
    ) ;
    // Add visual indicator after the buttons
    modified += ' <span class="blockingIndicator blocking-indicator">(blocking ' + blockingCount + ')</span>' ;
    return modified ;
}

function fileIssue( hostname, ro, fromHost, user, db, time, safeUrl, blockingCount ) {
    if (!jiraConfig.enabled) {
        alert('Jira integration is not configured. Set jiraEnabled to true in aql_config.xml.');
        return;
    }

    var query = decodeURIComponent(safeUrl.replace(/\+/g, ' '));
    var roLabel = (ro == 1) ? 'Read-Only' : 'Read-Write';

    // Generate query fingerprint hash
    var normalizedQuery = normalizeQueryForHash(query);
    var queryHash = hashString(normalizedQuery);

    var summary = 'Long Running Query on ' + hostname + ' from ' + user + '@' + fromHost + ' for ' + time + 's';
    if ( blockingCount && blockingCount > 0 ) {
        summary = 'BLOCKING Query on ' + hostname + ' from ' + user + '@' + fromHost + ' (blocking ' + blockingCount + ' queries)';
    }

    var description =
        '*Server:* ' + hostname + '\n' +
        '*Database:* ' + db + '\n' +
        '*User:* ' + user + '\n' +
        '*Source Host:* ' + fromHost + '\n' +
        '*Query Time:* ' + time + ' seconds\n' +
        '*Access:* ' + roLabel + '\n' +
        ( blockingCount && blockingCount > 0 ? '*Blocking Count at time issue was filed:* ' + blockingCount + '\n' : '' ) +
        '\n*Query:*\n{code:sql}\n' + query + '\n{code}';

    var jiraUrl = jiraConfig.baseUrl + 'secure/CreateIssueDetails!init.jspa?' +
        'pid=' + encodeURIComponent(jiraConfig.projectId) +
        '&issuetype=' + encodeURIComponent(jiraConfig.issueTypeId) +
        '&summary=' + encodeURIComponent(summary) +
        '&description=' + encodeURIComponent(description);

    // Add custom field for query hash if configured
    if (jiraConfig.queryHashFieldId) {
        jiraUrl += '&' + jiraConfig.queryHashFieldId + '=' + encodeURIComponent(queryHash);
    }

    window.open(jiraUrl, '_blank');
}

///////////////////////////////////////////////////////////////////////////////

function togglePageRefresh() {
    if ( timeoutId > 0 ) {
        clearTimeout( timeoutId ) ;
        timeoutId = null ;
        document.getElementById("toggleButton").innerHTML = "Turn Automatic Refresh On" ;
    }
    else {
        timeoutId = setTimeout( function() { window.location.reload( 1 ); }, reloadSeconds ) ;
        document.getElementById("toggleButton").innerHTML = "Turn Automatic Refresh Off" ;
    }
}

///////////////////////////////////////////////////////////////////////////////


function makeHref( item ) {
    var loc = location.protocol + '//' + location.host + location.pathname ;
    return loc + '?hosts[]=' + encodeURI( item ) + debugString ;
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByLevel() {
    var chartColors = getChartColors();
    var data = google.visualization.arrayToDataTable(
             [ [ 'Label', 'Count' ]
             , [ 'Error (' + base_counts['Error'] + ')', base_counts['Error'] ]
             , [ 'Level 4 (' + base_counts['Level4'] + ')', base_counts['Level4'] ]
             , [ 'Level 3 (' + base_counts['Level3'] + ')', base_counts['Level3'] ]
             , [ 'Level 2 (' + base_counts['Level2'] + ')', base_counts['Level2'] ]
             , [ 'Level 1 (' + base_counts['Level1'] + ')', base_counts['Level1'] ]
             , [ 'Level 0 (' + base_counts['Level0'] + ')', base_counts['Level0'] ]
             ] ) ;
    var options = {
                  title : 'Level Counts'
                , backgroundColor: chartColors.backgroundColor
                , titleTextStyle: { color: chartColors.titleColor }
                , legendTextStyle: { color: chartColors.legendTextColor }
                , is3D : true
                , slices : { 0 : { color : 'red'        }
                           , 1 : { color : 'orange'     }
                           , 2 : { color : 'yellow'     }
                           , 3 : { color : 'lightgreen' }
                           , 4 : { color : '#ddd'       }
                           , 5 : { color : 'cyan'       }
                           }
                } ;
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByLevel'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByHost() {
    var chartColors = getChartColors();
    Object
        .keys( host_count )
        .forEach( function(item) {
            host_counts.push( [ item, makeHref( item ), host_count[ item ] ] ) ;
        } ) ;
    var data = google.visualization.arrayToDataTable( host_counts ) ;
    var view = new google.visualization.DataView(data);
    view.setColumns([ 0, 2 ]);
    var options = {
            title : 'Queries by Host'
          , is3D : true
          , backgroundColor: chartColors.backgroundColor
          , titleTextStyle: { color: chartColors.titleColor }
          , legendTextStyle: { color: chartColors.legendTextColor }
          } ;
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByHost'));
    chart.draw(view, options);
    var selectHandler = function(e) {
        window.location = data.getValue(chart.getSelection()[0]['row'], 1);
    }
    google.visualization.events.addListener(chart, 'select', selectHandler);
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByDB() {
    var chartColors = getChartColors();
    Object
          .keys( db_count )
          .forEach( function(item) {
              db_counts.push( [ item, db_count[ item ] ] ) ;
          } ) ;
    var data = google.visualization.arrayToDataTable( db_counts ) ;
    var options = {
            title : 'Queries by DB'
          , is3D : true
          , backgroundColor: chartColors.backgroundColor
          , titleTextStyle: { color: chartColors.titleColor }
          , legendTextStyle: { color: chartColors.legendTextColor }
          } ;
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByDB'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByDupeState() {
    var chartColors = getChartColors();
    var dupe = base_counts['Duplicate'] ;
    var similar = base_counts['Similar'] ;
    var unique = base_counts['Unique'] ;
    var blank = base_counts['Blank'] ;
    var x = [ [ 'Label', 'Count' ]
            , [ 'Duplicate (' + dupe + ')', dupe ]
            , [ 'Similar (' + similar + ')', similar ]
            , [ 'Unique (' + unique + ')', unique ]
            , [ 'Blank (' + blank + ')', blank ]
            ] ;
    var data = google.visualization.arrayToDataTable( x ) ;
    var options = {
                 title : 'Duplicate/Similar/Unique/Blank Counts'
               , is3D : true
               , slices : { 0 : { color : 'pink'   }
                          , 1 : { color : 'yellow' }
                          , 2 : { color : 'silver' }
                          , 3 : { color : 'white'  }
                          }
               , backgroundColor: chartColors.backgroundColor
               , titleTextStyle: { color: chartColors.titleColor }
               , legendTextStyle: { color: chartColors.legendTextColor }
               } ;
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByDupeState'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByReadWrite() {
    var chartColors = getChartColors();
    var data = google.visualization.arrayToDataTable(
            [ [ 'Label', 'Count' ]
            , [ 'Read-Only (' + base_counts['RO'] + ')', base_counts['RO'] ]
            , [ 'Read-Write (' + base_counts['RW'] + ')', base_counts['RW'] ]
            ] );
   var options = {
                 title : 'Read-Only vs. Read-Write Counts'
               , is3D : true
               , slices : { 0 : { color : 'cyan'   }
                          , 1 : { color : 'yellow' }
                          }
               , backgroundColor: chartColors.backgroundColor
               , titleTextStyle: { color: chartColors.titleColor }
               , legendTextStyle: { color: chartColors.legendTextColor }
               };
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByReadWrite'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

/**
* This will update the overview table and display the pie charts.
*
* @returns void
*/
function displayCharts() {
    google.charts.load('current', {
        packages : [ 'corechart' ]
    });
    google.charts.setOnLoadCallback(drawPieChartByLevel);
    google.charts.setOnLoadCallback(drawPieChartByHost);
    google.charts.setOnLoadCallback(drawPieChartByDB);
    google.charts.setOnLoadCallback(drawPieChartByReadWrite);
    google.charts.setOnLoadCallback(drawPieChartByDupeState);
}

///////////////////////////////////////////////////////////////////////////////

/**
 * Redraw all charts with current theme colors
 * Called when theme is toggled
 */
function redrawCharts() {
    // Only redraw if Google Charts is loaded and chart elements exist
    if (typeof google !== 'undefined' && google.visualization) {
        if (document.getElementById('pieChartByLevel')) {
            drawPieChartByLevel();
        }
        if (document.getElementById('pieChartByHost')) {
            drawPieChartByHost();
        }
        if (document.getElementById('pieChartByDB')) {
            drawPieChartByDB();
        }
        if (document.getElementById('pieChartByReadWrite')) {
            drawPieChartByReadWrite();
        }
        if (document.getElementById('pieChartByDupeState')) {
            drawPieChartByDupeState();
        }
    }
}

///////////////////////////////////////////////////////////////////////////////

function fillHostForm( host_id
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
                     , db_type ) {
    document.getElementById( 'hostId' ).value = host_id ;
    document.getElementById( 'hostName' ).value = hostname ;
    document.getElementById( 'portNumber' ).value = port_number ;
    document.getElementById( 'description' ).value = description ;
    document.getElementById( 'dbType' ).value = db_type ;
    document.getElementById( 'shouldMonitor' ).value = should_monitor ;
    document.getElementById( 'shouldBackup' ).value = should_backup ;
    document.getElementById( 'shouldSchemaspy' ).value = should_schemaspy ;
    document.getElementById( 'revenueImpacting' ).value = revenue_impacting ;
    document.getElementById( 'decommissioned' ).value = decommissioned ;
    document.getElementById( 'alertCritSecs' ).value = alert_crit_secs ;
    document.getElementById( 'alertWarnSecs' ).value = alert_warn_secs ;
    document.getElementById( 'alertInfoSecs' ).value = alert_info_secs ;
    document.getElementById( 'alertLowSecs' ).value = alert_low_secs ;
}

///////////////////////////////////////////////////////////////////////////////

function fillGroupForm( group_id
                      , group_tag
                      , short_desc
                      , full_desc
                      , host_list ) {
    document.getElementById( 'groupId' ).value = group_id ;
    document.getElementById( 'groupTag' ).value = group_tag ;
    document.getElementById( 'shortDescription' ).value = short_desc ;
    document.getElementById( 'fullDescription' ).value = full_desc ;
    var elements = document.getElementById( 'groupSelect' ) ;
    elements.value = '' ;
    for ( var i = 0 ; i < elements.length ; i++ ) {
        for ( var j = 0 ; j < host_list.length ; j++ ) {
            if ( elements[ i ].value == host_list[ j ] ) {
                elements[ i ].selected = true ;
            }
        }
    }
}

///////////////////////////////////////////////////////////////////////////////

function fillMaintenanceWindowForm( window_id
                                  , window_type
                                  , schedule_type
                                  , target_type
                                  , target_id
                                  , days_of_week
                                  , day_of_month
                                  , month_of_year
                                  , period_days
                                  , period_start_date
                                  , start_time
                                  , end_time
                                  , timezone
                                  , silence_until
                                  , description ) {
    document.getElementById( 'windowId' ).value = window_id ;
    document.getElementById( 'windowType' ).value = window_type ;
    document.getElementById( 'scheduleType' ).value = schedule_type || 'weekly' ;
    document.getElementById( 'targetType' ).value = target_type ;

    // Set the appropriate target dropdown
    if ( target_type === 'host' ) {
        document.getElementById( 'targetHost' ).value = target_id ;
    } else {
        document.getElementById( 'targetGroup' ).value = target_id ;
    }

    // Set days of week checkboxes
    var dayCheckboxes = document.querySelectorAll( 'input[name="daysOfWeek[]"]' ) ;
    var daysArray = days_of_week ? days_of_week.split( ',' ) : [] ;
    dayCheckboxes.forEach( function( cb ) {
        cb.checked = daysArray.indexOf( cb.value ) !== -1 ;
    } ) ;

    // Set extended schedule fields
    document.getElementById( 'dayOfMonth' ).value = day_of_month || '' ;
    document.getElementById( 'monthOfYear' ).value = month_of_year || '' ;
    document.getElementById( 'periodDays' ).value = period_days || '' ;
    document.getElementById( 'periodStartDate' ).value = period_start_date || '' ;

    document.getElementById( 'startTime' ).value = start_time ;
    document.getElementById( 'endTime' ).value = end_time ;
    document.getElementById( 'timezone' ).value = timezone ;

    // Convert silence_until to datetime-local format (remove seconds)
    if ( silence_until && silence_until.length > 16 ) {
        silence_until = silence_until.substring( 0, 16 ).replace( ' ', 'T' ) ;
    }
    document.getElementById( 'silenceUntil' ).value = silence_until ;

    document.getElementById( 'mwDescription' ).value = description ;

    // Toggle visibility based on selections
    if ( typeof toggleWindowTypeFields === 'function' ) {
        toggleWindowTypeFields() ;
    }
    if ( typeof toggleTargetFields === 'function' ) {
        toggleTargetFields() ;
    }
}

///////////////////////////////////////////////////////////////////////////////

function initAlerts() {
    // Needed to enable audio playback on Chrome
    document.getElementById("klaxon").play().then(() => {
        document.getElementById("klaxon").pause();
        document.getElementById("klaxon").currentTime = 0;
        console.log("Audio primed.");
    });

    if (Notification.permission !== "granted") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                console.log("Notifications enabled.");
            }
        });
    }
}

///////////////////////////////////////////////////////////////////////////////

// Check if alerts are muted via URL parameter or cookie (supports timed mute)
function isAlertMuted() {
    const urlParams = new URLSearchParams(window.location.search);
    // Check URL params first
    const urlMuteUntil = urlParams.get('mute_until');
    if (urlMuteUntil !== null) {
        const expiry = parseInt(urlMuteUntil, 10);
        return expiry === 0 || Date.now() < expiry;
    }
    // Legacy support: mute=1 means indefinite
    if (urlParams.get('mute') === '1') {
        return true;
    }
    // Check cookie for timed mute
    const match = document.cookie.match(/aql_mute_until=(\d+)/);
    if (match) {
        const expiry = parseInt(match[1], 10);
        return expiry === 0 || Date.now() < expiry;
    }
    return false;
}

// Call this when a long-running query is detected
function triggerAlert(queryId) {
    if (isAlertMuted()) return;

    const sound = document.getElementById("klaxon");

    if (Notification.permission === "granted") {
        new Notification("⚠️ Long-running query", {
            body: `Query ${queryId} exceeded the time threshold!`,
            icon: "Images/alert-icon.png"
        });
    }

    // Play klaxon sound
    sound.play().catch(e => {
        console.error("Failed to play sound:", e);
    });
}

///////////////////////////////////////////////////////////////////////////////

// TableSorter URL persistence functions

// Parse sort parameter from URL (format: "col,dir" e.g., "7,1" for column 7 descending)
function getSortFromUrl(tableKey) {
    var urlParams = new URLSearchParams(window.location.search);
    var sortParam = urlParams.get(tableKey + '_sort');
    if (sortParam) {
        var parts = sortParam.split(',');
        if (parts.length === 2) {
            return [[parseInt(parts[0], 10), parseInt(parts[1], 10)]];
        }
    }
    return null;
}

// Update URL with sort parameter and reload
function updateSortUrl(tableKey, sortList) {
    if (!sortList || sortList.length === 0) return;
    var url = new URL(window.location.href);
    url.searchParams.set(tableKey + '_sort', sortList[0][0] + ',' + sortList[0][1]);
    window.location.href = url.toString();
}

// Initialize a single table with sorting and URL persistence
function initTableSortWithUrl(tableId, tableKey, defaultSort) {
    var $table = $(tableId);
    if ($table.length === 0) return;

    var sortList = getSortFromUrl(tableKey) || defaultSort;
    $table.tablesorter({ sortList: sortList });

    // On sort change, update URL and reload
    $table.on('sortEnd', function(e) {
        var newSort = e.target.config.sortList;
        var currentSort = getSortFromUrl(tableKey);
        // Only reload if sort actually changed from URL value
        if (!currentSort || newSort[0][0] !== currentSort[0][0] || newSort[0][1] !== currentSort[0][1]) {
            updateSortUrl(tableKey, newSort);
        }
    });
}

// Auto-initialize sorting for manageData tables on DOM ready
$(document).ready(function() {
    // Only init these if they exist (manageData.php)
    initTableSortWithUrl('#hostEdit', 'host', [[2, 0]]);   // Host Name ascending
    initTableSortWithUrl('#groupEdit', 'group', [[1, 0]]); // Group Name ascending
    // Blocking history table (blockingHistory.php)
    initTableSortWithUrl('#blockingHistoryTable', 'blocking', [[4, 1]]); // Times Seen descending
});

///////////////////////////////////////////////////////////////////////////////
// Hash-based Navigation (for AJAX-loaded content)
///////////////////////////////////////////////////////////////////////////////

/**
 * Scroll to the element specified by the URL hash, if present.
 * Call this after AJAX content has loaded.
 */
function scrollToHashIfPresent() {
    var hash = window.location.hash ;
    if ( hash && hash.length > 1 ) {
        var targetId = hash.substring( 1 ) ;
        var target = document.getElementById( targetId ) ;
        if ( target ) {
            // Small delay to ensure DOM is fully rendered
            setTimeout( function() {
                target.scrollIntoView( { behavior: 'smooth', block: 'start' } ) ;
            }, 100 ) ;
        }
    }
}

/**
 * Scroll to a specific section by ID.
 * Used by DBType Overview boxes and Scoreboard clicks.
 * @param {string} sectionId - The ID of the element to scroll to
 */
function scrollToSection( sectionId ) {
    var target = document.getElementById( sectionId ) ;
    if ( target ) {
        target.scrollIntoView( { behavior: 'smooth', block: 'start' } ) ;
        // Update URL hash without triggering scroll (for shareable URLs)
        if ( history.pushState ) {
            history.pushState( null, null, '#' + sectionId ) ;
        }
    }
}

// Intercept clicks on same-page anchor links to avoid full reload
$(document).ready(function() {
    $(document).on('click', 'a[href*="index.php#"]', function(e) {
        // Only intercept if we're already on index.php
        if ( window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/') ) {
            var href = $(this).attr('href') ;
            var hashIndex = href.indexOf('#') ;
            if ( hashIndex !== -1 ) {
                var targetId = href.substring( hashIndex + 1 ) ;
                var target = document.getElementById( targetId ) ;
                if ( target ) {
                    e.preventDefault() ;
                    target.scrollIntoView( { behavior: 'smooth', block: 'start' } ) ;
                    // Update URL hash without reload
                    history.pushState( null, null, '#' + targetId ) ;
                }
            }
        }
    }) ;
}) ;

///////////////////////////////////////////////////////////////////////////////
// Maintenance Window Silencing
///////////////////////////////////////////////////////////////////////////////

/**
 * Browser-local silencing (stored in localStorage)
 * Allows users to silence hosts/groups in their browser without DBA access
 */

// Get all locally silenced items (hosts and groups)
function getLocalSilenced() {
    try {
        var data = localStorage.getItem( 'aql_local_silenced' ) ;
        if ( !data ) return { hosts: {}, groups: {} } ;
        return JSON.parse( data ) ;
    } catch ( e ) {
        return { hosts: {}, groups: {} } ;
    }
}

// Save locally silenced items
function saveLocalSilenced( data ) {
    localStorage.setItem( 'aql_local_silenced', JSON.stringify( data ) ) ;
}

// Silence a host locally (browser only)
function silenceHostLocally( hostId, hostname, durationMinutes, autoRecover, recoverLevel, recoverCount ) {
    var data = getLocalSilenced() ;
    var expiry = durationMinutes > 0 ? Date.now() + ( durationMinutes * 60 * 1000 ) : 0 ;
    data.hosts[ hostId ] = {
        hostname: hostname,
        expiry: expiry,
        autoRecover: autoRecover || false,
        recoverLevel: recoverLevel || 'not-error',
        recoverCount: parseInt( recoverCount, 10 ) || 2,
        healthyCount: 0
    } ;
    saveLocalSilenced( data ) ;
}

// Silence a group locally (browser only)
function silenceGroupLocally( groupId, groupTag, durationMinutes, autoRecover, recoverLevel, recoverCount ) {
    var data = getLocalSilenced() ;
    var expiry = durationMinutes > 0 ? Date.now() + ( durationMinutes * 60 * 1000 ) : 0 ;
    data.groups[ groupId ] = {
        tag: groupTag,
        expiry: expiry,
        autoRecover: autoRecover || false,
        recoverLevel: recoverLevel || 'not-error',
        recoverCount: parseInt( recoverCount, 10 ) || 2,
        healthyCount: 0
    } ;
    saveLocalSilenced( data ) ;
}

// Check if a host is locally silenced
function isHostLocallySilenced( hostId ) {
    var data = getLocalSilenced() ;
    var entry = data.hosts[ hostId ] ;
    if ( !entry ) return false ;
    if ( entry.expiry === 0 ) return true ; // indefinite
    return Date.now() < entry.expiry ;
}

// Check if a group is locally silenced
function isGroupLocallySilenced( groupId ) {
    var data = getLocalSilenced() ;
    var entry = data.groups[ groupId ] ;
    if ( !entry ) return false ;
    if ( entry.expiry === 0 ) return true ; // indefinite
    return Date.now() < entry.expiry ;
}

// Check if any of a host's groups are locally silenced
function isHostGroupLocallySilenced( hostGroups ) {
    if ( !hostGroups || !Array.isArray( hostGroups ) ) return false ;
    for ( var i = 0 ; i < hostGroups.length ; i++ ) {
        if ( isGroupLocallySilenced( hostGroups[ i ].id ) ) {
            return true ;
        }
    }
    return false ;
}

// Check host health and update recovery tracking
// Returns true if host was auto-unsilenced
function checkHostRecovery( hostId, currentLevel, isError ) {
    var data = getLocalSilenced() ;
    var entry = data.hosts[ hostId ] ;
    if ( !entry || !entry.autoRecover ) return false ;

    // Determine if host meets recovery criteria
    var recovered = false ;
    if ( isError ) {
        // Still in error state - reset healthy count
        entry.healthyCount = 0 ;
    } else if ( entry.recoverLevel === 'not-error' ) {
        // Any non-error response counts as healthy
        recovered = true ;
    } else {
        var targetLevel = parseInt( entry.recoverLevel, 10 ) ;
        if ( currentLevel <= targetLevel ) {
            recovered = true ;
        } else {
            // Not at target level - reset healthy count
            entry.healthyCount = 0 ;
        }
    }

    if ( recovered ) {
        entry.healthyCount = ( entry.healthyCount || 0 ) + 1 ;
        if ( entry.healthyCount >= entry.recoverCount ) {
            // Host has recovered! Remove the silence
            var hostname = entry.hostname || hostId ;
            delete data.hosts[ hostId ] ;
            saveLocalSilenced( data ) ;
            // Track recovered hosts for UI notification
            if ( typeof window.recoveredHosts === 'undefined' ) {
                window.recoveredHosts = [] ;
            }
            window.recoveredHosts.push( hostname ) ;
            return true ;
        }
    }

    // Save updated healthy count
    saveLocalSilenced( data ) ;
    return false ;
}

// Clean up expired local silences
function cleanupLocalSilenced() {
    var data = getLocalSilenced() ;
    var now = Date.now() ;
    var changed = false ;

    for ( var hostId in data.hosts ) {
        if ( data.hosts[ hostId ].expiry !== 0 && data.hosts[ hostId ].expiry < now ) {
            delete data.hosts[ hostId ] ;
            changed = true ;
        }
    }
    for ( var groupId in data.groups ) {
        if ( data.groups[ groupId ].expiry !== 0 && data.groups[ groupId ].expiry < now ) {
            delete data.groups[ groupId ] ;
            changed = true ;
        }
    }

    if ( changed ) {
        saveLocalSilenced( data ) ;
    }
}

// Run cleanup periodically
setInterval( cleanupLocalSilenced, 60000 ) ;

// Remove a specific local silence
function removeLocalSilence( type, id ) {
    var data = getLocalSilenced() ;
    if ( type === 'host' ) {
        delete data.hosts[ id ] ;
    } else if ( type === 'group' ) {
        delete data.groups[ id ] ;
    }
    saveLocalSilenced( data ) ;
    updateLocalSilencesUI() ;
}

// Clear all local silences
function clearAllLocalSilences() {
    saveLocalSilenced( { hosts: {}, groups: {} } ) ;
    updateLocalSilencesUI() ;
}

// Format time remaining for display
function formatSilenceTimeRemaining( ms ) {
    if ( ms <= 0 ) return 'expired' ;
    var mins = Math.floor( ms / 60000 ) ;
    if ( mins < 60 ) return mins + 'm' ;
    var hours = Math.floor( mins / 60 ) ;
    mins = mins % 60 ;
    if ( hours < 24 ) return hours + 'h ' + mins + 'm' ;
    var days = Math.floor( hours / 24 ) ;
    hours = hours % 24 ;
    return days + 'd ' + hours + 'h' ;
}

// Update the local silences UI panel
function updateLocalSilencesUI() {
    var data = getLocalSilenced() ;
    var container = document.getElementById( 'localSilencesContainer' ) ;
    var list = document.getElementById( 'localSilencesList' ) ;

    if ( !container || !list ) return ;

    var html = '' ;
    var count = 0 ;
    var now = Date.now() ;

    // Show recovered hosts notification (if any)
    if ( typeof window.recoveredHosts !== 'undefined' && window.recoveredHosts.length > 0 ) {
        for ( var i = 0 ; i < window.recoveredHosts.length ; i++ ) {
            html += '<div class="local-silence-recovered">✓ ' + window.recoveredHosts[ i ] + ' recovered - alerts restored</div>' ;
        }
        // Clear after displaying (will disappear on next refresh)
        window.recoveredHosts = [] ;
    }

    // List silenced hosts
    for ( var hostId in data.hosts ) {
        var entry = data.hosts[ hostId ] ;
        if ( entry.expiry !== 0 && entry.expiry < now ) continue ; // skip expired
        var remaining = entry.expiry === 0 ? '∞' : formatSilenceTimeRemaining( entry.expiry - now ) ;
        var displayName = entry.hostname || ( 'Host #' + hostId ) ;

        // Build auto-recover badge if applicable
        var autoBadge = '' ;
        if ( entry.autoRecover ) {
            var progress = ( entry.healthyCount || 0 ) + '/' + entry.recoverCount ;
            autoBadge = '<span class="local-silence-auto-badge" title="Auto-unmute when recovered">⟳' + progress + '</span>' ;
        }

        html += '<div class="local-silence-item">'
              + '<span><span class="local-silence-name">' + displayName + '</span>'
              + autoBadge
              + '<span class="local-silence-expiry">(' + remaining + ')</span></span>'
              + '<a href="#" onclick="removeLocalSilence(\'host\', \'' + hostId + '\'); return false;" '
              + 'class="local-silence-remove" title="Unmute">✕</a>'
              + '</div>' ;
        count++ ;
    }

    // List silenced groups
    for ( var groupId in data.groups ) {
        var entry = data.groups[ groupId ] ;
        if ( entry.expiry !== 0 && entry.expiry < now ) continue ; // skip expired
        var remaining = entry.expiry === 0 ? '∞' : formatSilenceTimeRemaining( entry.expiry - now ) ;
        var displayName = entry.tag || ( 'Group #' + groupId ) ;

        // Build auto-recover badge if applicable
        var autoBadge = '' ;
        if ( entry.autoRecover ) {
            var progress = ( entry.healthyCount || 0 ) + '/' + entry.recoverCount ;
            autoBadge = '<span class="local-silence-auto-badge" title="Auto-unmute when recovered">⟳' + progress + '</span>' ;
        }

        html += '<div class="local-silence-item group">'
              + '<span><span class="local-silence-name">' + displayName + '</span>'
              + autoBadge
              + '<span class="local-silence-expiry">(' + remaining + ')</span></span>'
              + '<a href="#" onclick="removeLocalSilence(\'group\', \'' + groupId + '\'); return false;" '
              + 'class="local-silence-remove" title="Unmute">✕</a>'
              + '</div>' ;
        count++ ;
    }

    list.innerHTML = html || '<div class="local-silences-empty">None active</div>' ;
    container.style.display = ( count > 0 || html !== '' ) ? 'block' : 'none' ;
}

// Update UI on load and periodically
$( document ).ready( function() {
    updateLocalSilencesUI() ;
    setInterval( updateLocalSilencesUI, 30000 ) ; // refresh countdown every 30 seconds
}) ;

// Expose for klaxon.js
window.isHostLocallySilenced = isHostLocallySilenced ;
window.isGroupLocallySilenced = isGroupLocallySilenced ;
window.isHostGroupLocallySilenced = isHostGroupLocallySilenced ;

///////////////////////////////////////////////////////////////////////////////
// Silence Modal Functions
///////////////////////////////////////////////////////////////////////////////

// Track if refresh was active before modal opened
var refreshWasActive = false ;

// Pause auto-refresh (for modal dialogs)
function pauseAutoRefresh() {
    if ( timeoutId > 0 ) {
        refreshWasActive = true ;
        clearTimeout( timeoutId ) ;
        timeoutId = null ;
    } else {
        refreshWasActive = false ;
    }
}

// Resume auto-refresh if it was active before
function resumeAutoRefresh() {
    if ( refreshWasActive && timeoutId === null ) {
        timeoutId = setTimeout( function() { window.location.reload( 1 ); }, reloadSeconds ) ;
    }
}

// Hook modal events to pause/resume refresh
$( document ).ready( function() {
    $( '#silenceModal' ).on( 'show.bs.modal', function() {
        pauseAutoRefresh() ;
    }) ;
    $( '#silenceModal' ).on( 'hidden.bs.modal', function() {
        resumeAutoRefresh() ;
    }) ;
}) ;

// Toggle auto-recover options visibility
function toggleAutoRecoverOptions() {
    var checked = $( '#silenceAutoRecover' ).prop( 'checked' ) ;
    $( '#silenceAutoRecoverOptions' ).css( 'display', checked ? 'block' : 'none' ) ;
}

/**
 * Open the silence modal for a host or group
 * @param {string} targetType - 'host' or 'group'
 * @param {int} targetId - Host ID or Group ID (optional for group mode)
 * @param {string} targetName - Display name for the target (optional for group mode)
 */
// Store target name for local silencing
var silenceTargetName = '' ;

function openSilenceModal( targetType, targetId, targetName ) {
    $( '#silenceTargetType' ).val( targetType ) ;
    $( '#silenceDuration' ).val( 60 ) ; // default 1 hour
    $( '#silenceDescription' ).val( '' ) ;
    $( '#silenceScopeLocal' ).prop( 'checked', true ) ; // default to local
    $( '#silenceAutoRecover' ).prop( 'checked', false ) ;
    $( '#silenceAutoRecoverOptions' ).hide() ;
    $( '#silenceRecoverLevel' ).val( '2' ) ;
    $( '#silenceRecoverCount' ).val( '2' ) ;

    if ( targetType === 'group' && !targetId ) {
        // Group selection mode - show dropdown, hide target display
        $( '#silenceTargetRow' ).hide() ;
        $( '#silenceGroupRow' ).show() ;
        $( '#silenceGroupSelect' ).val( '' ) ;
        $( '#silenceTargetId' ).val( '' ) ;
        silenceTargetName = '' ;
    } else {
        // Direct target mode - show target display, hide dropdown
        $( '#silenceTargetRow' ).show() ;
        $( '#silenceGroupRow' ).hide() ;
        $( '#silenceTargetId' ).val( targetId ) ;
        $( '#silenceTargetDisplay' ).text( targetName + ' (' + targetType + ')' ) ;
        silenceTargetName = targetName ;
    }

    $( '#silenceModal' ).modal( 'show' ) ;
}

/**
 * Open the silence modal in group selection mode
 */
function openSilenceGroupModal() {
    openSilenceModal( 'group', null, null ) ;
}

/**
 * Set silence duration from preset buttons
 * @param {int} minutes - Duration in minutes
 */
function setSilenceDuration( minutes ) {
    $( '#silenceDuration' ).val( minutes ) ;
}

/**
 * Submit the silence request
 */
function submitSilence() {
    var targetType = $( '#silenceTargetType' ).val() ;
    var targetId = $( '#silenceTargetId' ).val() ;
    var targetName = silenceTargetName ;
    var duration = parseInt( $( '#silenceDuration' ).val(), 10 ) ;
    var description = $( '#silenceDescription' ).val() ;
    var scope = $( 'input[name="silenceScope"]:checked' ).val() ;

    // If group mode with dropdown, get the selected group
    if ( targetType === 'group' && !targetId ) {
        targetId = $( '#silenceGroupSelect' ).val() ;
        targetName = $( '#silenceGroupSelect option:selected' ).text() ;
        if ( !targetId ) {
            alert( 'Please select a group.' ) ;
            return ;
        }
    }

    if ( !duration || duration <= 0 ) {
        alert( 'Please enter a valid duration.' ) ;
        return ;
    }

    if ( scope === 'local' ) {
        // Browser-local silencing (no server call)
        var autoRecover = $( '#silenceAutoRecover' ).prop( 'checked' ) ;
        var recoverLevel = $( '#silenceRecoverLevel' ).val() ;
        var recoverCount = $( '#silenceRecoverCount' ).val() ;

        // Cap auto-recover silences at 72 hours (4320 minutes) as safety net
        var maxAutoRecoverMins = 4320 ;
        if ( autoRecover && ( duration > maxAutoRecoverMins || duration <= 0 ) ) {
            duration = maxAutoRecoverMins ;
        }

        if ( targetType === 'host' ) {
            silenceHostLocally( targetId, targetName, duration, autoRecover, recoverLevel, recoverCount ) ;
        } else {
            silenceGroupLocally( targetId, targetName, duration, autoRecover, recoverLevel, recoverCount ) ;
        }

        var msg = 'Silenced ' + targetType + ' "' + targetName + '" for ' + duration + ' minutes (this browser only).' ;
        if ( autoRecover ) {
            msg += '\n\nWill auto-unmute when service recovers (max 72 hours).' ;
        }
        alert( msg ) ;
        $( '#silenceModal' ).modal( 'hide' ) ;
        updateLocalSilencesUI() ;
        return ;
    }

    // Global silencing (database) - requires DBA access
    $.post( 'AJAXsilenceHost.php', {
        targetType: targetType,
        targetId: targetId,
        duration: duration,
        description: description
    }, function( response ) {
        if ( response.success ) {
            alert( response.message ) ;
            $( '#silenceModal' ).modal( 'hide' ) ;
            // Refresh the page to show the new maintenance indicator
            location.reload() ;
        } else {
            alert( 'Failed to silence: ' + response.error ) ;
        }
    }).fail(function() {
        alert( 'Network error while trying to silence host.' ) ;
    }) ;
}

/**
 * Quick silence a host without opening the modal
 * @param {int} hostId - Host ID
 * @param {int} durationMinutes - Duration in minutes
 */
function quickSilenceHost( hostId, durationMinutes ) {
    if ( !confirm( 'Silence this host for ' + durationMinutes + ' minutes?' ) ) {
        return ;
    }

    $.post( 'AJAXsilenceHost.php', {
        targetType: 'host',
        targetId: hostId,
        duration: durationMinutes,
        description: 'Quick silence from AQL'
    }, function( response ) {
        if ( response.success ) {
            alert( response.message ) ;
            location.reload() ;
        } else {
            alert( 'Failed to silence: ' + response.error ) ;
        }
    }).fail(function() {
        alert( 'Network error while trying to silence host.' ) ;
    }) ;
}

///////////////////////////////////////////////////////////////////////////////
// Fuzzy Search Autocomplete (fzf-style)
///////////////////////////////////////////////////////////////////////////////

/**
 * fzf-style fuzzy match - characters must appear in order but not consecutively
 * @param {string} pattern - Search pattern (e.g., "tt")
 * @param {string} str - String to match against (e.g., "Scott.Adams")
 * @returns {object|null} - {score, matches} or null if no match
 */
function fuzzyMatch(pattern, str) {
    if (!pattern) return { score: 0, matches: [] };

    pattern = pattern.toLowerCase();
    var strLower = str.toLowerCase();
    var patternIdx = 0;
    var matches = [];
    var score = 0;
    var lastMatchIdx = -1;

    for (var i = 0; i < str.length && patternIdx < pattern.length; i++) {
        if (strLower[i] === pattern[patternIdx]) {
            matches.push(i);
            // Bonus for consecutive matches
            if (lastMatchIdx === i - 1) {
                score += 10;
            }
            // Bonus for start of string or after separator
            if (i === 0 || /[._\-@\s]/.test(str[i - 1])) {
                score += 5;
            }
            score += 1;
            lastMatchIdx = i;
            patternIdx++;
        }
    }

    // All pattern characters must be found
    if (patternIdx !== pattern.length) {
        return null;
    }

    // Bonus for shorter strings (more precise match)
    score += Math.max(0, 20 - str.length);

    return { score: score, matches: matches };
}

/**
 * Highlight matched characters in a string
 * @param {string} str - Original string
 * @param {array} matches - Array of matched character indices
 * @returns {string} - HTML with matched chars wrapped in <b>
 */
function highlightMatches(str, matches) {
    if (!matches || matches.length === 0) return escapeHtml(str);

    var result = '';
    var matchSet = new Set(matches);
    for (var i = 0; i < str.length; i++) {
        if (matchSet.has(i)) {
            result += '<b>' + escapeHtml(str[i]) + '</b>';
        } else {
            result += escapeHtml(str[i]);
        }
    }
    return result;
}

/**
 * Escape HTML special characters
 */
function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Initialize fuzzy autocomplete on an input field
 * @param {string} inputId - ID of the input element
 * @param {array} options - Array of strings to search
 * @param {number} maxResults - Maximum results to show (default 10)
 */
function initFuzzyAutocomplete(inputId, options, maxResults) {
    maxResults = maxResults || 10;
    var input = document.getElementById(inputId);
    if (!input) return;

    // Create dropdown container
    var dropdown = document.createElement('div');
    dropdown.className = 'fuzzy-dropdown';
    dropdown.style.cssText = 'position:absolute;background:#333;border:1px solid #555;max-height:200px;overflow-y:auto;display:none;z-index:1000;min-width:200px;';
    input.parentNode.style.position = 'relative';
    input.parentNode.appendChild(dropdown);

    var selectedIdx = -1;

    function updateDropdown() {
        var query = input.value;
        dropdown.innerHTML = '';
        selectedIdx = -1;

        if (!query) {
            dropdown.style.display = 'none';
            return;
        }

        // Score and filter options
        var scored = [];
        for (var i = 0; i < options.length; i++) {
            var match = fuzzyMatch(query, options[i]);
            if (match) {
                scored.push({ text: options[i], score: match.score, matches: match.matches });
            }
        }

        // Sort by score descending
        scored.sort(function(a, b) { return b.score - a.score; });

        // Take top results
        var results = scored.slice(0, maxResults);

        if (results.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        // Build dropdown items
        for (var j = 0; j < results.length; j++) {
            var item = document.createElement('div');
            item.className = 'fuzzy-item';
            item.style.cssText = 'padding:5px 10px;cursor:pointer;color:#fff;';
            item.innerHTML = highlightMatches(results[j].text, results[j].matches);
            item.setAttribute('data-value', results[j].text);
            item.setAttribute('data-index', j);

            item.addEventListener('mouseover', function() {
                var items = dropdown.querySelectorAll('.fuzzy-item');
                for (var k = 0; k < items.length; k++) {
                    items[k].style.background = '';
                }
                this.style.background = '#555';
                selectedIdx = parseInt(this.getAttribute('data-index'));
            });

            item.addEventListener('click', function() {
                input.value = this.getAttribute('data-value');
                dropdown.style.display = 'none';
            });

            dropdown.appendChild(item);
        }

        dropdown.style.display = 'block';
    }

    input.addEventListener('input', updateDropdown);
    input.addEventListener('focus', updateDropdown);

    input.addEventListener('keydown', function(e) {
        var items = dropdown.querySelectorAll('.fuzzy-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIdx = Math.min(selectedIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIdx = Math.max(selectedIdx - 1, 0);
        } else if (e.key === 'Enter' && selectedIdx >= 0) {
            e.preventDefault();
            input.value = items[selectedIdx].getAttribute('data-value');
            dropdown.style.display = 'none';
            return;
        } else if (e.key === 'Escape') {
            dropdown.style.display = 'none';
            return;
        } else {
            return;
        }

        // Update highlight
        for (var i = 0; i < items.length; i++) {
            items[i].style.background = (i === selectedIdx) ? '#555' : '';
        }
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

///////////////////////////////////////////////////////////////////////////////

/**
 * Copy text to clipboard - shared utility function
 * @param {string} elementId - ID of element containing text to copy (uses innerText)
 * @param {string} buttonId - ID of button to show feedback on
 */
function copyToClipboard( elementId, buttonId ) {
    var element = document.getElementById( elementId ) ;
    var text = element.innerText || element.textContent ;
    navigator.clipboard.writeText( text ).then( function() {
        var btn = document.getElementById( buttonId ) ;
        var originalText = btn.innerText ;
        btn.innerText = 'Copied!' ;
        setTimeout( function() { btn.innerText = originalText ; }, 1500 ) ;
    }).catch( function( err ) {
        console.error( 'Failed to copy:', err ) ;
        alert( 'Failed to copy to clipboard' ) ;
    }) ;
}

///////////////////////////////////////////////////////////////////////////////
