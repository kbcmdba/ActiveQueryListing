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
const debugString = ( debug == '1' ) ? '&debug=1' : '' ;

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

function sortTable() {
    var table, rows, switching, i, x, y, shouldSwitch ;
    table = document.getElementById("tbodyid") ;
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
    var summaryRow        = '' ;
    var myUrl             = '' ;
    var summaryData = item[ 'summaryData' ] ;
    // We get other types of responses here as well. Ignore the noise.
    if (    ( typeof item[ 'result' ] !== 'undefined' )
         && ( typeof item[ 'result' ][ 0 ] !== 'undefined' )
         && ( typeof item[ 'result' ][ 0 ][ 'level' ] !== 'undefined' )
       ) {
        // Assumption - if we can get any rows from the server, we should be able to get all of the rows.
        server            = item[ 'result' ][ 0 ][ 'server' ] ;
        serverLinkAddress = '<a href="?hosts[]=' + server + debugString + '">' + server + '</a>' ;
        if ( typeof host_count[ server ] === 'undefined' ) {
            host_count[ server ] = 0 ;
        }
        for ( itemNo=0; itemNo<item[ 'result' ].length; itemNo++ ) {
            host_count[ server ] ++ ;
            level = item[ 'result' ][ itemNo ][ 'level' ] ;
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
            var myRow = $("<tr class=\"level" + level + "\">"
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
                         + "</td><td class=\"comment more\">" + info
                         + "</td><td>" + item[ 'result' ][ itemNo ][ 'actions'      ]
                         + "</td></tr>") ;
            myRow.appendTo( "#tbodyid" ) ;
        }
    }
    else if ( typeof item[ 'error_output' ] !== 'undefined' ) {
        var myRow = $("<tr><td class=\"errorNotice\">" + item[ 'hostname' ]
                     + "</td><td class=\"errorNotice\">9</td><td colspan=\"12\" class=\"errorNotice\">" + item[ 'error_output' ]
                     + "</td></tr>") ;
        myRow.prependTo( "#tbodyid" ) ;
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

function fileIssue( hostname, ro, fromHost, user, db, time, safeUrl ) {
    alert( "Not yet implemented.\n\nhostname=" + hostname + "\nro=" + ro + "\nfromHost=" + fromHost + "\nuser=" + user + "\ndb=" + db + "\ntime=" + time + "\nsafeUrl=" + safeUrl + "\n" ) ;
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
    Object
          .keys( db_count )
          .forEach( function(item) {
              db_counts.push( [ item, db_count[ item ] ] ) ;
          } ) ;
    var data = google.visualization.arrayToDataTable( db_counts ) ;
    var options = {
            title : 'Queries by DB'
          , is3D : true
          } ;
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByDB'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByDupeState() {
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
                          } };
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByDupeState'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

function drawPieChartByReadWrite() {
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
                          } };
    var chart = new google.visualization.PieChart(document
              .getElementById('pieChartByReadWrite'));
    chart.draw(data, options);
}

///////////////////////////////////////////////////////////////////////////////

/**
* This will update the summary table and display the pie charts.
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

