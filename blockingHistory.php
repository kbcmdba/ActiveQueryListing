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
use com\kbcmdba\aql\Libs\Exceptions\DaoException;
use com\kbcmdba\aql\Libs\Tools ;
use com\kbcmdba\aql\Libs\WebPage ;

// ///////////////////////////////////////////////////////////////////////////

$page = new WebPage( 'Blocking History' ) ;
$page->setTop( "<h2>AQL: Blocking History</h2>\n"
             . "<p>This page shows queries that have been observed blocking other queries. "
             . "Query text is normalized (strings/numbers replaced) to avoid storing sensitive data.</p>\n"
             ) ;

$body = '' ;

try {
    $dbc = new DBConnection() ;
    $dbh = $dbc->getConnection() ;
    $dbh->set_charset( 'utf8' ) ;

    // Get filter parameters
    $filterHostId = Tools::param( 'hostId' ) ;
    $filterUser = Tools::param( 'user' ) ;
    $filterQuery = Tools::param( 'query' ) ;
    $filterDateFrom = Tools::param( 'dateFrom' ) ;
    $filterDateTo = Tools::param( 'dateTo' ) ;

    // Build host dropdown
    $hostOptions = "<option value=\"\">-- All Hosts --</option>\n" ;
    $hostQuery = "SELECT host_id, hostname, port_number FROM aql_db.host ORDER BY hostname, port_number" ;
    $hostResult = $dbh->query( $hostQuery ) ;
    if ( $hostResult !== false ) {
        while ( $row = $hostResult->fetch_assoc() ) {
            $selected = ( $filterHostId == $row['host_id'] ) ? ' selected' : '' ;
            $hostOptions .= "<option value=\"" . intval( $row['host_id'] ) . "\"$selected>"
                         . htmlspecialchars( $row['hostname'] . ':' . $row['port_number'] )
                         . "</option>\n" ;
        }
        $hostResult->close() ;
    }

    // Build user list for fuzzy autocomplete
    $userList = [] ;
    $userQuery = "SELECT DISTINCT user FROM aql_db.blocking_history ORDER BY user" ;
    $userResult = $dbh->query( $userQuery ) ;
    if ( $userResult !== false ) {
        while ( $row = $userResult->fetch_assoc() ) {
            $userList[] = $row['user'] ;
        }
        $userResult->close() ;
    }

    // Build query list for fuzzy autocomplete (top 100 most frequent, truncated)
    $queryList = [] ;
    $queryListQuery = "SELECT DISTINCT LEFT(query_text, 80) AS query_preview
                         FROM aql_db.blocking_history
                        ORDER BY blocked_count DESC
                        LIMIT 100" ;
    $queryListResult = $dbh->query( $queryListQuery ) ;
    if ( $queryListResult !== false ) {
        while ( $row = $queryListResult->fetch_assoc() ) {
            $queryList[] = $row['query_preview'] ;
        }
        $queryListResult->close() ;
    }

    // Get total count of entries
    $totalCount = 0 ;
    $countResult = $dbh->query( "SELECT COUNT(*) AS cnt FROM aql_db.blocking_history" ) ;
    if ( $countResult !== false ) {
        $countRow = $countResult->fetch_assoc() ;
        $totalCount = intval( $countRow['cnt'] ) ;
        $countResult->close() ;
    }

    // Check if any filters are applied
    $hasFilters = !empty( $filterHostId ) || !empty( $filterUser ) || !empty( $filterQuery )
                || !empty( $filterDateFrom ) || !empty( $filterDateTo ) ;

    // Show total count
    $body .= "<p><strong>Total blocking history entries:</strong> " . number_format( $totalCount ) . "</p>\n" ;

    // Filter form
    $body .= "<form method=\"get\" action=\"blockingHistory.php\">\n"
          .  "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n"
          .  "<tr>\n"
          .  "  <th>Host</th>\n"
          .  "  <td><select name=\"hostId\">$hostOptions</select></td>\n"
          .  "  <th>User</th>\n"
          .  "  <td><input type=\"text\" name=\"user\" id=\"userFilter\" value=\"" . htmlspecialchars( $filterUser ?? '' ) . "\" size=\"20\" placeholder=\"Fuzzy search user\" autocomplete=\"off\"></td>\n"
          .  "  <th>Query Pattern</th>\n"
          .  "  <td><input type=\"text\" name=\"query\" id=\"queryFilter\" value=\"" . htmlspecialchars( $filterQuery ?? '' ) . "\" size=\"40\" placeholder=\"Fuzzy search query\" autocomplete=\"off\"></td>\n"
          .  "</tr>\n"
          .  "<tr>\n"
          .  "  <th>From Date</th>\n"
          .  "  <td><input type=\"date\" name=\"dateFrom\" value=\"" . htmlspecialchars( $filterDateFrom ?? '' ) . "\"></td>\n"
          .  "  <th>To Date</th>\n"
          .  "  <td><input type=\"date\" name=\"dateTo\" value=\"" . htmlspecialchars( $filterDateTo ?? '' ) . "\"></td>\n"
          .  "  <td colspan=\"2\"><input type=\"submit\" value=\"Apply Filters\"> &nbsp; <a href=\"blockingHistory.php\">Clear Filters</a></td>\n"
          .  "</tr>\n"
          .  "</table>\n"
          .  "</form>\n"
          .  "<script>\n"
          .  "var userOptions = " . json_encode( $userList ) . ";\n"
          .  "var queryOptions = " . json_encode( $queryList ) . ";\n"
          .  "document.addEventListener('DOMContentLoaded', function() {\n"
          .  "    initFuzzyAutocomplete('userFilter', userOptions, 10);\n"
          .  "    initFuzzyAutocomplete('queryFilter', queryOptions, 10);\n"
          .  "});\n"
          .  "</script>\n"
          .  "<p />\n" ;

    // Build the results query with filters
    $sql = "SELECT h.hostname
                 , h.port_number
                 , bh.user
                 , bh.source_host
                 , bh.db_name
                 , bh.blocked_count
                 , bh.total_blocked
                 , bh.max_block_secs
                 , bh.query_text
                 , bh.first_seen
                 , bh.last_seen
              FROM aql_db.blocking_history bh
              JOIN aql_db.host h ON h.host_id = bh.host_id
             WHERE 1=1" ;
    $params = [] ;
    $types = '' ;

    if ( !empty( $filterHostId ) && is_numeric( $filterHostId ) ) {
        $sql .= " AND bh.host_id = ?" ;
        $params[] = (int) $filterHostId ;
        $types .= 'i' ;
    }
    if ( !empty( $filterUser ) ) {
        $sql .= " AND bh.user LIKE ?" ;
        $params[] = '%' . $filterUser . '%' ;
        $types .= 's' ;
    }
    if ( !empty( $filterQuery ) ) {
        $sql .= " AND bh.query_text LIKE ?" ;
        $params[] = '%' . $filterQuery . '%' ;
        $types .= 's' ;
    }
    if ( !empty( $filterDateFrom ) ) {
        $sql .= " AND bh.last_seen >= ?" ;
        $params[] = $filterDateFrom . ' 00:00:00' ;
        $types .= 's' ;
    }
    if ( !empty( $filterDateTo ) ) {
        $sql .= " AND bh.last_seen <= ?" ;
        $params[] = $filterDateTo . ' 23:59:59' ;
        $types .= 's' ;
    }

    // Default: most recent first. With filters: by blocked_count then last_seen
    if ( $hasFilters ) {
        $sql .= " ORDER BY bh.blocked_count DESC, bh.last_seen DESC" ;
    } else {
        $sql .= " ORDER BY bh.last_seen DESC" ;
    }
    $sql .= " LIMIT 100" ;

    $stmt = $dbh->prepare( $sql ) ;
    if ( !empty( $params ) ) {
        $stmt->bind_param( $types, ...$params ) ;
    }
    $stmt->execute() ;
    $result = $stmt->get_result() ;

    // Results table
    $body .= "<table id=\"blockingHistoryTable\" class=\"tablesorter aql-listing\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n"
          .  "<thead>\n"
          .  "<tr>\n"
          .  "  <th>Host</th>\n"
          .  "  <th>User</th>\n"
          .  "  <th>Source Host</th>\n"
          .  "  <th>Database</th>\n"
          .  "  <th>Times Seen</th>\n"
          .  "  <th>Total Seen Blocked</th>\n"
          .  "  <th>Max Block Secs Seen</th>\n"
          .  "  <th>Query</th>\n"
          .  "  <th>First Seen</th>\n"
          .  "  <th>Last Seen</th>\n"
          .  "</tr>\n"
          .  "</thead>\n"
          .  "<tbody>\n" ;

    $rowCount = 0 ;
    while ( $row = $result->fetch_assoc() ) {
        $rowCount++ ;
        $host = htmlspecialchars( $row['hostname'] . ':' . $row['port_number'] ) ;
        $user = htmlspecialchars( $row['user'] ) ;
        $sourceHost = htmlspecialchars( $row['source_host'] ) ;
        $dbName = htmlspecialchars( $row['db_name'] ?? '' ) ;
        $blockedCount = intval( $row['blocked_count'] ) ;
        $totalBlocked = intval( $row['total_blocked'] ) ;
        $maxBlockSecs = intval( $row['max_block_secs'] ?? 0 ) ;
        $queryFull = htmlspecialchars( $row['query_text'] ) ;
        $queryTrunc = htmlspecialchars( mb_substr( $row['query_text'], 0, 80 ) ) ;
        if ( mb_strlen( $row['query_text'] ) > 80 ) {
            $queryTrunc .= '...' ;
        }
        $firstSeen = htmlspecialchars( $row['first_seen'] ) ;
        $lastSeen = htmlspecialchars( $row['last_seen'] ) ;

        $body .= "<tr>\n"
              .  "  <td>$host</td>\n"
              .  "  <td>$user</td>\n"
              .  "  <td>$sourceHost</td>\n"
              .  "  <td>$dbName</td>\n"
              .  "  <td style=\"text-align:right;\">$blockedCount</td>\n"
              .  "  <td style=\"text-align:right;\">$totalBlocked</td>\n"
              .  "  <td style=\"text-align:right;\">$maxBlockSecs</td>\n"
              .  "  <td title=\"$queryFull\"><code>$queryTrunc</code></td>\n"
              .  "  <td>$firstSeen</td>\n"
              .  "  <td>$lastSeen</td>\n"
              .  "</tr>\n" ;
    }

    $body .= "</tbody>\n"
          .  "</table>\n" ;

    if ( $rowCount === 0 ) {
        if ( $hasFilters ) {
            $body .= "<p><em>No blocking history found matching the current filters.</em></p>\n" ;
        } else {
            $body .= "<p><em>No blocking history recorded yet.</em></p>\n" ;
        }
    } else {
        $showing = $hasFilters ? "Showing $rowCount matching record" : "Showing $rowCount most recent record" ;
        $body .= "<p>" . $showing . ( $rowCount !== 1 ? 's' : '' ) . " (limit 100)</p>\n" ;
    }

    $stmt->close() ;

} catch ( \Exception $e ) {
    $body .= "<p class=\"errorNotice\">Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
}

$page->setBody( $body ) ;
$page->displayPage() ;
