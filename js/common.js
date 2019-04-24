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

///////////////////////////////////////////////////////////////////////////////
/**
 * Make sure that the passed value is valid for the proposed condition. If
 * isRequired is true, dateString must not be blank or null as well as being a
 * valid date string. If isRequired is false, dateString may be blank or null,
 * but when it's not, it must be a valid date string. A valid date string looks
 * like YYYY-MM-DD
 * 
 * @param dateString
 *            {String}
 * @param isRequired
 *            {Boolean}
 * @returns {Boolean}
 */
function isDateValid(dateString, isRequired) {
    var regex = /^\d\d\d\d-\d\d-\d\d$/;
    var retVal = true;

    if (!isRequired) {
        if ((null == dateString) || ('' == dateString)) {
            return true;
        }
    }
    else {
        retVal = ((null !== dateString) && ('' !== dateString));
    }
    retVal = (retVal && (null !== dateString.match(regex)));
    if (retVal) {
        var daysInMonths = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
        var yr = parseInt(dateString.substring(0, 4));
        var mo = parseInt(dateString.substring(5, 7));
        var da = parseInt(dateString.substring(8, 10));
        if ((yr % 4) && ((yr % 400) || !(yr % 100))) {
            daysInMonths[1]++; // Leap day!
        }
        if ((yr < 2000) || (yr > 2038) || (mo < 1) || (mo > 12) || (da < 1)
                || (da > daysInMonths[mo])) {
            retVal = false;
        }
    }
    return (retVal);
}

// /////////////////////////////////////////////////////////////////////////////

/**
 * Make sure that the passed value is valid for the proposed condition. If
 * isRequired is true, dateTimeString must not be blank or null as well as being
 * a valid date and time string. If isRequired is false, dateTimeString may be
 * blank or null, but when it's not, it must be a valid date and time string. A
 * valid date and time string looks like 'YYYY-MM-DD hh:mm:ss'
 * 
 * @param dateTimeString
 *            {String}
 * @param isRequired
 *            {Boolean}
 * @returns {Boolean}
 */
function isDateTimeValid(dateTimeString, isRequired) {
    var regex = /^\d\d\d\d-\d\d-\d\d\s\d\d:\d\d:\d\d$/;
    var retVal = true;
    if (!isRequired) {
        if ((null == dateTimeString) || ('' == dateTimeString)) {
            return true;
        }
    }
    else {
        retVal = ((null !== dateTimeString) && ('' !== dateTimeString));
    }
    retVal = (retVal && (null !== dateTimeString.match(regex)));
    if (retVal) {
        var daysInMonths = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
        var yr = parseInt(dateTimeString.substring(0, 4));
        var mo = parseInt(dateTimeString.substring(5, 7));
        var da = parseInt(dateTimeString.substring(8, 10));
        var hr = parseInt(dateTimeString.substring(11, 13));
        var mi = parseInt(dateTimeString.substring(14, 16));
        var se = parseInt(dateTimeString.substring(17, 19));
        if ((yr % 4) && ((yr % 400) || !(yr % 100))) {
            daysInMonths[1]++; // Leap day!
        }
        if ((yr < 2000) || (yr > 2038) || (mo < 1) || (mo > 12) || (da < 1)
                || (da > daysInMonths[mo]) || (hr < 0) || (hr > 23) || (mi < 0)
                || (mi > 59) || (se < 0) || (se > 59)) {
            retVal = false;
        }
    }
    return (retVal);
}

// /////////////////////////////////////////////////////////////////////////////

function isNumeric(n) {
    return !isNaN(parseFloat(n)) && isFinite(n);
}

/**
 * Load the results of an AJAX call into the target ID
 * 
 * @param uri
 *            URI
 * @param data
 *            Data in URL-encoded format
 * @param targetId
 *            The response will be loaded here.
 * @param isAsync
 *            Load the response asynchronously.
 * @param callback
 *            A user-defined routine to handle the results.
 */
function doLoadAjaxJsonResultWithCallback(uri, data, targetId, isAsync,
        callback) {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            callback(xhttp, targetId);
        }
    };
    xhttp.open("POST", uri, isAsync);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send(data);
}

// /////////////////////////////////////////////////////////////////////////////

/**
 * Dynamically remove a row that was created.
 * 
 * @param rowId
 * @returns {Boolean}
 */
function deleteRow(rowId) {
    var row = document.getElementById(rowId);
    row.parentNode.removeChild(row);
    return false;
}

// /////////////////////////////////////////////////////////////////////////////

function flipFlop() {
    if ($(this).hasClass("less")) {
        $(this).removeClass("less");
        $(this).html("more");
    }
    else {
        $(this).addClass("less");
        $(this).html("less");
    }
    $(this).parent().prev().toggle();
    $(this).prev().toggle();
    return false;
}

// /////////////////////////////////////////////////////////////////////////////

var host_counts = [ [ 'Label', 'link', 'Count' ] ];
var host_count = [];
var counts = {
    'error' : 0,
    'level4' : 0,
    'level3' : 0,
    'level2' : 0,
    'level1' : 0,
    'level0' : 0,
    'ro' : 0,
    'rw' : 0,
    'Dupe' : 0,
    'Similar' : 0,
    'Unique' : 0
};

function drawPieChart1() {
    var data = google.visualization.arrayToDataTable([ [ 'Label', 'Count' ],
            [ 'Error (' + counts['error'] + ')', counts['error'] ],
            [ 'Level 4 (' + counts['level4'] + ')', counts['level4'] ],
            [ 'Level 3 (' + counts['level3'] + ')', counts['level3'] ],
            [ 'Level 2 (' + counts['level2'] + ')', counts['level2'] ],
            [ 'Level 1 (' + counts['level1'] + ')', counts['level1'] ],
            [ 'Level 0 (' + counts['level0'] + ')', counts['level0'] ] ]);

    var options = {
        title : 'Level Counts',
        is3D : true,
        slices : {
            0 : {
                color : 'red'
            },
            1 : {
                color : 'orange'
            },
            2 : {
                color : 'yellow'
            },
            3 : {
                color : 'lightgreen'
            },
            4 : {
                color : '#ddd'
            },
            5 : {
                color : 'cyan'
            }
        }
    };

    var chart = new google.visualization.PieChart(document
            .getElementById('piechart1_3d'));
    chart.draw(data, options);
}

// /////////////////////////////////////////////////////////////////////////////

function drawPieChart2() {
    var data = google.visualization.arrayToDataTable([ [ 'Label', 'Count' ],
            [ 'Dupe (' + counts['Dupe'] + ')', counts['Dupe'] ],
            [ 'Similar (' + counts['Similar'] + ')', counts['Similar'] ],
            [ 'Unique (' + counts['Unique'] + ')', counts['Unique'] ] ]);

    var options = {
        title : 'Dupe / Similar / Unique',
        is3D : true,
        slices : {
            0 : {
                color : 'pink'
            },
            1 : {
                color : 'lightgreen'
            },
            2 : {
                color : 'silver'
            },
        }
    };

    var chart = new google.visualization.PieChart(document
            .getElementById('piechart2_3d'));
    chart.draw(data, options);
}

// /////////////////////////////////////////////////////////////////////////////

function drawPieChart3() {
    var loc = location.href;
    for ( var k in host_count) {
        thisloc = (loc.includes('?')) ? loc + '&' : loc + '?';
        thisloc = thisloc + 'host=' + encodeURI(k);
        host_counts.push([ '' + k + ' (' + host_count[k] + ')',
                           thisloc,
                           host_count[k]
                         ]);
    }
    var data = google.visualization.arrayToDataTable(host_counts);

    var view = new google.visualization.DataView(data);
    view.setColumns([ 0, 2 ]);

    var options = {
        title : 'Queries by Host',
        is3D : true,
    };

    chartId = document.getElementById('piechart3_3d');
    var chart = new google.visualization.PieChart(chartId);
    chart.draw(view, options);

    var selectHandler = function(e) {
        window.location = data.getValue(chart.getSelection()[0]['row'], 1);
    }
    google.visualization.events.addListener(chart, 'select', selectHandler);
}

// /////////////////////////////////////////////////////////////////////////////

function drawPieChart4() {
    var data = google.visualization.arrayToDataTable([ [ 'Label', 'Count' ],
            [ 'Reader (' + counts['ro'] + ')', counts['ro'] ],
            [ 'Reader-Writer (' + counts['rw'] + ')', counts['rw'] ] ]);

    var options = {
        title : 'Node Type',
        is3D : true,
        slices : {
            0 : {
                color : 'cyan'
            },
            1 : {
                color : 'yellow'
            },
        }
    };

    var chart = new google.visualization.PieChart(document
            .getElementById('piechart4_3d'));
    chart.draw(data, options);
}

// /////////////////////////////////////////////////////////////////////////////

/**
 * This will update the summary table and display the pie charts.
 * 
 * @returns void
 */
function displayCounts() {
    google.charts.load('current', {
        packages : [ 'corechart' ]
    });
    google.charts.setOnLoadCallback(drawPieChart1);
    google.charts.setOnLoadCallback(drawPieChart2);
    google.charts.setOnLoadCallback(drawPieChart3);
    google.charts.setOnLoadCallback(drawPieChart4);
}

// /////////////////////////////////////////////////////////////////////////////

function myCallback(i, item) {
    var showChars = 40;
    var itemNo = 0;
    var level = -1;
    var info = '';
    var dupe = '';
    var server = '';
    var first = '';
    var last = '';
    var myRow = '';
    var summaryRow = '' ;
    var myUrl = '';
    var summary_data = item[ 'summary_data' ] ;
    if ((typeof item['result'] !== 'undefined')
            && (typeof item['result'][0] !== 'undefined')
            && (typeof item['result'][0]['level'] !== 'undefined')) {
        // Assumption - if we can get any rows from the server, we should be
        // able to get all of the rows.
        server = item['result'][0]['server'];
        for (itemNo = 0; itemNo < item['result'].length; itemNo++) {
            level = item['result'][itemNo]['level'];
            if (9 == level) {
                counts['error']++;
            }
            else {
                counts['level' + level]++;
            }
            if (item['result'][itemNo]['readOnly'] == 0) {
                counts['rw']++;
            }
            else {
                counts['ro']++;
            }
            dupe = item['result'][itemNo]['dupe'];
            dupeClass = dupe;
            counts[dupe]++;
            if (!host_count.hasOwnProperty(server)) {
                host_count[server] = 0;
            }
            host_count[server]++;
            info = item['result'][itemNo]['info'];
            if (info.length > showChars + 8) {
                first = info.substr(0, showChars);
                last = info.substr(showChars, info.length - showChars);
                info = first + '<span class="moreelipses">...</span>'
                        + '<span class="morecontent"><span>' + last
                        + '</span>&nbsp;&nbsp;<a href="" class="morelink">'
                        + 'more</a></span>';
            }
            myUrl = window.location.href;
            if (myUrl.indexOf("?") === -1) {
                myUrl = myUrl + "?host=" + server;
            }
            else {
                myUrl = myUrl + "&host=" + server;
            }
            myRow = $("<tr class=\"level"
                    + level
                    + "\">"
                    + "<td class=\"comment more\"><a href=\""
                    + myUrl
                    + "\">"
                    + server
                    + "</a>"
                    + "</td><td align=\"center\">"
                    + level
                    + "</td><td align=\"center\">"
                    + item['result'][itemNo]['id']
                    + "</td><td>"
                    + item['result'][itemNo]['user']
                    + "</td><td>"
                    + item['result'][itemNo]['host']
                    + "</td><td>"
                    + item['result'][itemNo]['db']
                    + "</td><td>"
                    + item['result'][itemNo]['command']
                    + "</td><td align=\"center\">"
                    + item['result'][itemNo]['time']
                    + "</td><td>"
                    + item['result'][itemNo]['state']
                    + "</td><td"
                    + (item['result'][itemNo]['readOnly'] == 0 ? ' class="readWrite">OFF'
                            : ' class="readOnly">ON') + "</td><td class=\""
                    + dupeClass + "\">" + dupe
                    + "</td><td class=\"comment more\">" + info + "</td><td>"
                    + item['result'][itemNo]['actions'] + "</td></tr>\n");
            myRow.appendTo("#tbodyid");
        } // END OF for (itemNo = 0; itemNo < item['result'].length; itemNo++)
        summaryRow = $("<tr>"
                      + "<td><a href=\"" + myUrl + "\">" + server + "</a></td>"
                      + "<td>" + summary_data[ 'ActiveTime' ] + "</td>"
                      + "<td>" + summary_data[ 'Sessions' ] + "</td>"
                      + "<td class=\"sleeping\">" + summary_data[ 'Sleep' ] + "</td>"
                      + "<td>" + summary_data[ 'Daemon' ] + "</td>"
                      + "<td class=\"dupe\">" + summary_data[ 'Dupe' ] + "</td>"
                      + "<td class=\"similar\">" + summary_data[ 'Similar' ] + "</td>"
                      + "<td class=\"unique\">" + summary_data[ 'Unique' ] + "</td>"
                      + "<td class=\"error\">" + summary_data[ 'Error' ] + "</td>"
                      + "<td class=\"level4\">" + summary_data[ 'Level4' ] + "</td>"
                      + "<td class=\"level3\">" + summary_data[ 'Level3' ] + "</td>"
                      + "<td class=\"level2\">" + summary_data[ 'Level2' ] + "</td>"
                      + "<td class=\"level1\">" + summary_data[ 'Level1' ] + "</td>"
                      + "<td class=\"level0\">" + summary_data[ 'Level0' ] + "</td>"
                      + "<td class=\"" + ( summary_data[ 'Rw' ] ? 'readWrite' : 'readOnly' ) + "\">" + ( summary_data[ 'Rw' ] ? 'Writer' : 'Reader' ) + "</td>"
                      + "</tr>\n"
                      );
        summaryRow.appendTo("#tbodysummaryid");
    }
    else if (typeof item[0] !== 'undefined'
            && typeof item[0].level !== 'undefined') {
        for (itemNo = 0; itemNo < item.length; itemNo++) {
            level = item[itemNo]['level'];
            if (9 == level) {
                counts['error']++;
            }
            else {
                counts['level' + level]++;
            }
            if (item[itemNo]['readOnly'] == 0) {
                counts['rw']++;
            }
            else {
                counts['ro']++;
            }
            dupe = item[itemNo]['dupe'];
            dupeClass = dupe;
            info = item[itemNo]['info'];
            server = item[itemNo]['server'];
            if (info.length > showChars + 8) {
                first = info.substr(0, showChars);
                last = info.substr(showChars, info.length - showChars);
                info = first + '<span class="moreelipses">...</span>'
                        + '<span class="morecontent"><span>' + last
                        + '</span>&nbsp;&nbsp;<a href="" class="morelink">'
                        + 'more</a></span>';
            }
            myRow = $("<tr class=\"level"
                    + level
                    + "\">"
                    + "<td class=\"comment more\">"
                    + server
                    + "</td><td align=\"center\">"
                    + level
                    + "</td><td align=\"center\">"
                    + item[itemNo]['id']
                    + "</td><td>"
                    + item[itemNo]['user']
                    + "</td><td>"
                    + item[itemNo]['host']
                    + "</td><td>"
                    + item[itemNo]['db']
                    + "</td><td>"
                    + item[itemNo]['command']
                    + "</td><td align=\"center\">"
                    + item[itemNo]['time']
                    + "</td><td>"
                    + item[itemNo]['state']
                    + "</td><td"
                    + (item[itemNo]['readOnly'] == 0 ? ' class="readWrite">OFF'
                                                     : ' class="readOnly">ON') + "</td><td class=\""
                    + dupeClass + "\">" + dupe
                    + "</td><td class=\"comment more\">" + info + "</td><td>"
                    + item[itemNo]['actions'] + "</td></tr>");
            myRow.appendTo("#tbodyid");
        } // END OF for (itemNo = 0; itemNo < item.length; itemNo++)
        summaryRow = $("<tr>"
                     + "<td>" + server + "</td>"
                     + "<td>" + summary_data[ 'ActiveTime' ] + "</td>"
                     + "<td>" + summary_data[ 'Sessions' ] + "</td>"
                     + "<td class=\"sleeping\">" + summary_data[ 'Sleep' ] + "</td>"
                     + "<td>" + summary_data[ 'Daemon' ] + "</td>"
                     + "<td class=\"dupe\">" + summary_data[ 'Dupe' ] + "</td>"
                     + "<td class=\"similar\">" + summary_data[ 'Similar' ] + "</td>"
                     + "<td class=\"unique\">" + summary_data[ 'Unique' ] + "</td>"
                     + "<td class=\"error\">" + summary_data[ 'Error' ] + "</td>"
                     + "<td class=\"level4\">" + summary_data[ 'Level4' ] + "</td>"
                     + "<td class=\"level3\">" + summary_data[ 'Level3' ] + "</td>"
                     + "<td class=\"level2\">" + summary_data[ 'Level2' ] + "</td>"
                     + "<td class=\"level1\">" + summary_data[ 'Level1' ] + "</td>"
                     + "<td class=\"level0\">" + summary_data[ 'Level0' ] + "</td>"
                     + "<td class=\"" + ( summary_data[ 'Rw' ] ? 'readWrite' : 'readOnly' ) + "\">" + ( summary_data[ 'Rw' ] ? 'Writer' : 'Reader' ) + "</td>"
                     + "</tr>\n"
                     );
          summaryRow.appendTo("#tbodysummaryid");
    }
    else if (typeof item['error_output'] !== 'undefined') {
        counts['error']++;
        var myRow = $("<tr class=\"error\"><td>"
                + item['hostname']
                + "</td><td align=\"center\">11</td><td colspan=\"11\" align=\"center\" style=\"font-size: 36pt;\">"
                + item['error_output'] + "</td></tr>");
        myRow.prependTo("#tbodyid");
    }
}

///////////////////////////////////////////////////////////////////////////////

function sortTable() {
    var table, rows, switching, i, x, y, shouldSwitch;
    table = document.getElementById("tbodyid");
    switching = true;
    rows = table.getElementsByTagName("tr");
    rowcount = rows.length;
    while (switching) {
        switching = false;
        for (i = 0; i < (rowcount - 1); i++) {
            shouldSwitch = false;
            x1 = rows[i].getElementsByTagName("td")[1];
            x2 = rows[i].getElementsByTagName("td")[7];
            y1 = rows[i + 1].getElementsByTagName("td")[1];
            y2 = rows[i + 1].getElementsByTagName("td")[7];
            if ((Number(x1.innerHTML) < Number(y1.innerHTML))
                    || (Number(x1.innerHTML) == Number(y1.innerHTML) && Number(x2.innerHTML) < Number(y2.innerHTML))) {
                shouldSwitch = true;
                break;
            }
        }
        if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
        }
    }
}

///////////////////////////////////////////////////////////////////////////////

function urldecode(url) {
    if (typeof url != "string") {
        return url;
    }
    return decodeURIComponent(url.replace(/\+/g, ' '));
}

///////////////////////////////////////////////////////////////////////////////

function killProcOnHost(hostname, pid, user, fromhost, db, command, time,
        state, info) {
    document.getElementById('m_server').innerHTML = hostname;
    document.getElementById('m_pid').innerHTML = pid;
    document.getElementById('m_user').innerHTML = user;
    document.getElementById('m_host').innerHTML = fromhost;
    document.getElementById('m_db').innerHTML = db;
    document.getElementById('m_command').innerHTML = command;
    document.getElementById('m_time').innerHTML = time;
    document.getElementById('m_state').innerHTML = state;
    document.getElementById('m_info').innerText = urldecode(info);
    document.getElementById('i_server').value = hostname;
    document.getElementById('i_pid').value = pid;
    $("#myModal").modal('show');
    return false;
}

///////////////////////////////////////////////////////////////////////////////

function fileIssue(url) {
    var win = window.open(url + '&issueTemplateId=1', '_blank');
    return (false);
}

///////////////////////////////////////////////////////////////////////////////

function togglePageRefresh() {
    if (timeoutId > 0) {
        clearTimeout(timeoutId);
        timeoutId = null;
        document.getElementById("toggleButton").innerHTML = "Turn Automatic Refresh On";
    }
    else {
        timeoutId = setTimeout(function() {
            window.location.reload(1);
        }, reloadSeconds);
        document.getElementById("toggleButton").innerHTML = "Turn Automatic Refresh Off";
    }
}

///////////////////////////////////////////////////////////////////////////////

function toggleSummaryDisplay() {
    var x = document.getElementById("summaryTable");
    if ( "none" == x.style.display ) {
        document.getElementById("toggleSummaryButton").innerHTML = "Turn Summary Display Off";
        x.style.display = "table" ;
    }
    else {
        document.getElementById("toggleSummaryButton").innerHTML = "Turn Summary Display On";
        x.style.display = "none" ;
    }
}

///////////////////////////////////////////////////////////////////////////////
