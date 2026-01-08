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
use com\kbcmdba\aql\Libs\WebPage ;

// ///////////////////////////////////////////////////////////////////////////

/**
 * Check if a column exists in a table
 * @param mysqli $dbh Database connection handle
 * @param string $table Table name
 * @param string $column Column name
 * @return bool True if column exists
 */
function columnExists( $dbh, $table, $column ) {
    $safeTable = $dbh->real_escape_string( $table ) ;
    $safeColumn = $dbh->real_escape_string( $column ) ;
    $sql = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'" ;
    $result = $dbh->query( $sql ) ;
    return ( $result && $result->num_rows > 0 ) ;
}

/**
 * Check if a table exists
 * @param mysqli $dbh Database connection handle
 * @param string $table Table name
 * @return bool True if table exists
 */
function tableExists( $dbh, $table ) {
    $safeTable = $dbh->real_escape_string( $table ) ;
    $sql = "SHOW TABLES LIKE '$safeTable'" ;
    $result = $dbh->query( $sql ) ;
    return ( $result && $result->num_rows > 0 ) ;
}

// ///////////////////////////////////////////////////////////////////////////

$page = new WebPage( 'Deploy DDL' ) ;
$page->setTop( "<h2>AQL: Deploy DDL</h2>\n"
             . "<a href=\"index.php\">ActiveQueryListing</a>\n"
             . " | <a href=\"./manageData.php\">Manage Data</a>\n"
             . "<p />\n"
             ) ;

$body = "<p>This page checks the current database schema and applies any needed updates.</p>\n" ;

$results = [] ;
$errors = [] ;

try {
    $dbc = new DBConnection() ;
    $dbh = $dbc->getConnection() ;
    $dbh->set_charset( 'utf8' ) ;
} catch ( DaoException $e ) {
    $body .= "<p class='error'>Database connection failed: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    $page->setBody( $body ) ;
    $page->displayPage() ;
    exit ;
}

// ///////////////////////////////////////////////////////////////////////////
// Migration checks and updates
// ///////////////////////////////////////////////////////////////////////////

$body .= "<h3>Checking Schema...</h3>\n" ;
$body .= "<table class='tablesorter'>\n" ;
$body .= "<thead><tr><th>Check</th><th>Status</th><th>Action</th></tr></thead>\n" ;
$body .= "<tbody>\n" ;

// ---------------------------------------------------------------------------
// Check: maintenance_window table exists
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'maintenance_window' ) ) {
    $body .= "<tr><td>maintenance_window table</td><td>MISSING</td>" ;
    $body .= "<td>Please run setup_db.sql to create the base schema</td></tr>\n" ;
    $errors[] = "maintenance_window table is missing" ;
} else {
    $body .= "<tr><td>maintenance_window table</td><td>OK</td><td>-</td></tr>\n" ;

    // -----------------------------------------------------------------------
    // Migration 001: Extended Schedule Types
    // Add schedule_type, day_of_month, month_of_year, period_days, period_start_date
    // -----------------------------------------------------------------------

    // Check/add schedule_type column
    if ( ! columnExists( $dbh, 'maintenance_window', 'schedule_type' ) ) {
        $sql = "ALTER TABLE maintenance_window
                  ADD COLUMN schedule_type ENUM('weekly', 'monthly', 'quarterly', 'annually', 'periodic')
                      NULL DEFAULT 'weekly'
                      COMMENT 'Type of recurring schedule'
                      AFTER window_type" ;
        if ( $dbh->query( $sql ) ) {
            $body .= "<tr><td>schedule_type column</td><td>ADDED</td><td>Column created</td></tr>\n" ;
            $results[] = "Added schedule_type column" ;
            // Update existing scheduled windows to weekly
            $dbh->query( "UPDATE maintenance_window SET schedule_type = 'weekly' WHERE window_type = 'scheduled' AND schedule_type IS NULL" ) ;
        } else {
            $body .= "<tr><td>schedule_type column</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
            $errors[] = "Failed to add schedule_type column: " . $dbh->error ;
        }
    } else {
        $body .= "<tr><td>schedule_type column</td><td>OK</td><td>-</td></tr>\n" ;
    }

    // Check/add day_of_month column
    if ( ! columnExists( $dbh, 'maintenance_window', 'day_of_month' ) ) {
        $sql = "ALTER TABLE maintenance_window
                  ADD COLUMN day_of_month TINYINT UNSIGNED NULL DEFAULT NULL
                      COMMENT 'Day of month (1-31, 32=last day of month)'
                      AFTER days_of_week" ;
        if ( $dbh->query( $sql ) ) {
            $body .= "<tr><td>day_of_month column</td><td>ADDED</td><td>Column created</td></tr>\n" ;
            $results[] = "Added day_of_month column" ;
        } else {
            $body .= "<tr><td>day_of_month column</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
            $errors[] = "Failed to add day_of_month column: " . $dbh->error ;
        }
    } else {
        $body .= "<tr><td>day_of_month column</td><td>OK</td><td>-</td></tr>\n" ;
    }

    // Check/add month_of_year column
    if ( ! columnExists( $dbh, 'maintenance_window', 'month_of_year' ) ) {
        $sql = "ALTER TABLE maintenance_window
                  ADD COLUMN month_of_year TINYINT UNSIGNED NULL DEFAULT NULL
                      COMMENT 'Month of year (1-12) for quarterly/annually'
                      AFTER day_of_month" ;
        if ( $dbh->query( $sql ) ) {
            $body .= "<tr><td>month_of_year column</td><td>ADDED</td><td>Column created</td></tr>\n" ;
            $results[] = "Added month_of_year column" ;
        } else {
            $body .= "<tr><td>month_of_year column</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
            $errors[] = "Failed to add month_of_year column: " . $dbh->error ;
        }
    } else {
        $body .= "<tr><td>month_of_year column</td><td>OK</td><td>-</td></tr>\n" ;
    }

    // Check/add period_days column
    if ( ! columnExists( $dbh, 'maintenance_window', 'period_days' ) ) {
        $sql = "ALTER TABLE maintenance_window
                  ADD COLUMN period_days SMALLINT UNSIGNED NULL DEFAULT NULL
                      COMMENT 'Number of days between occurrences for periodic schedule'
                      AFTER month_of_year" ;
        if ( $dbh->query( $sql ) ) {
            $body .= "<tr><td>period_days column</td><td>ADDED</td><td>Column created</td></tr>\n" ;
            $results[] = "Added period_days column" ;
        } else {
            $body .= "<tr><td>period_days column</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
            $errors[] = "Failed to add period_days column: " . $dbh->error ;
        }
    } else {
        $body .= "<tr><td>period_days column</td><td>OK</td><td>-</td></tr>\n" ;
    }

    // Check/add period_start_date column
    if ( ! columnExists( $dbh, 'maintenance_window', 'period_start_date' ) ) {
        $sql = "ALTER TABLE maintenance_window
                  ADD COLUMN period_start_date DATE NULL DEFAULT NULL
                      COMMENT 'Start date for periodic schedule calculation'
                      AFTER period_days" ;
        if ( $dbh->query( $sql ) ) {
            $body .= "<tr><td>period_start_date column</td><td>ADDED</td><td>Column created</td></tr>\n" ;
            $results[] = "Added period_start_date column" ;
        } else {
            $body .= "<tr><td>period_start_date column</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
            $errors[] = "Failed to add period_start_date column: " . $dbh->error ;
        }
    } else {
        $body .= "<tr><td>period_start_date column</td><td>OK</td><td>-</td></tr>\n" ;
    }
}

// Check: maintenance_window_host_map table exists
if ( ! tableExists( $dbh, 'maintenance_window_host_map' ) ) {
    $body .= "<tr><td>maintenance_window_host_map table</td><td>MISSING</td>" ;
    $body .= "<td>Please run setup_db.sql to create the base schema</td></tr>\n" ;
    $errors[] = "maintenance_window_host_map table is missing" ;
} else {
    $body .= "<tr><td>maintenance_window_host_map table</td><td>OK</td><td>-</td></tr>\n" ;
}

// Check: maintenance_window_host_group_map table exists
if ( ! tableExists( $dbh, 'maintenance_window_host_group_map' ) ) {
    $body .= "<tr><td>maintenance_window_host_group_map table</td><td>MISSING</td>" ;
    $body .= "<td>Please run setup_db.sql to create the base schema</td></tr>\n" ;
    $errors[] = "maintenance_window_host_group_map table is missing" ;
} else {
    $body .= "<tr><td>maintenance_window_host_group_map table</td><td>OK</td><td>-</td></tr>\n" ;
}

$body .= "</tbody>\n</table>\n" ;

// ///////////////////////////////////////////////////////////////////////////
// Summary
// ///////////////////////////////////////////////////////////////////////////

$body .= "<h3>Summary</h3>\n" ;

if ( count( $errors ) > 0 ) {
    $body .= "<p class='error'>Errors occurred:</p>\n<ul>\n" ;
    foreach ( $errors as $error ) {
        $body .= "<li>" . htmlspecialchars( $error ) . "</li>\n" ;
    }
    $body .= "</ul>\n" ;
}

if ( count( $results ) > 0 ) {
    $body .= "<p>Changes applied:</p>\n<ul>\n" ;
    foreach ( $results as $result ) {
        $body .= "<li>" . htmlspecialchars( $result ) . "</li>\n" ;
    }
    $body .= "</ul>\n" ;
} else if ( count( $errors ) === 0 ) {
    $body .= "<p>Schema is up to date. No changes needed.</p>\n" ;
}

$body .= "<p><a href=\"deployDDL.php\">Re-check Schema</a> | <a href=\"manageData.php\">Manage Data</a></p>\n" ;

$page->setBody( $body ) ;
$page->displayPage() ;
