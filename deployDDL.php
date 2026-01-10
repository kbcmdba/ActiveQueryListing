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
$body .= "<p><em>Safe for new installs and existing installs - all operations are idempotent.</em></p>\n" ;

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

// Get database name from config
$config = new Config() ;
$dbName = $config->getDbName() ;

// ///////////////////////////////////////////////////////////////////////////
// Database and Base Table Creation (for new installs)
// ///////////////////////////////////////////////////////////////////////////

$body .= "<h3>Database Setup</h3>\n" ;
$body .= "<table class='tablesorter'>\n" ;
$body .= "<thead><tr><th>Item</th><th>Status</th><th>Action</th></tr></thead>\n" ;
$body .= "<tbody>\n" ;

// Check/create database
$dbCheckSql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . $dbh->real_escape_string( $dbName ) . "'" ;
$dbCheckResult = $dbh->query( $dbCheckSql ) ;
if ( $dbCheckResult && $dbCheckResult->num_rows > 0 ) {
    $body .= "<tr><td>Database $dbName</td><td>OK</td><td>-</td></tr>\n" ;
} else {
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET = 'utf8mb4' DEFAULT COLLATE = 'utf8mb4_bin'" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>Database $dbName</td><td>CREATED</td><td>Database created</td></tr>\n" ;
        $results[] = "Created database $dbName" ;
    } else {
        $body .= "<tr><td>Database $dbName</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create database: " . $dbh->error ;
    }
}

// Ensure we're using the correct database
$dbh->select_db( $dbName ) ;

// ---------------------------------------------------------------------------
// Base table: host
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'host' ) ) {
    $sql = "CREATE TABLE host (
           host_id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
         , hostname          VARCHAR( 64 ) NOT NULL
         , port_number       SMALLINT UNSIGNED NOT NULL DEFAULT 3306
         , description       TEXT NULL DEFAULT NULL
         , db_type           ENUM('MySQL', 'MariaDB', 'InnoDBCluster', 'MS-SQL', 'Redis', 'Oracle', 'Cassandra', 'DataStax', 'MongoDB') NOT NULL DEFAULT 'MySQL'
         , db_version        VARCHAR( 30 ) NOT NULL DEFAULT ''
         , should_monitor    BOOLEAN NOT NULL DEFAULT 1
         , should_backup     BOOLEAN NOT NULL DEFAULT 1
         , should_schemaspy  BOOLEAN NOT NULL DEFAULT 0
         , revenue_impacting BOOLEAN NOT NULL DEFAULT 1
         , decommissioned    BOOLEAN NOT NULL DEFAULT 0
         , alert_crit_secs   INT NOT NULL DEFAULT 0
         , alert_warn_secs   INT NOT NULL DEFAULT 0
         , alert_info_secs   INT NOT NULL DEFAULT 0
         , alert_low_secs    INT NOT NULL DEFAULT -1
         , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
         , last_audited      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , UNIQUE udx_hostname_port_number ( hostname, port_number )
         , KEY idx_should_monitor ( should_monitor, decommissioned )
         , KEY idx_decommissioned ( decommissioned )
         ) ENGINE=InnoDB" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>host table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created host table" ;
        // Insert default localhost entry
        $insertSql = "INSERT INTO host (hostname, port_number, description, alert_crit_secs, alert_warn_secs, alert_info_secs)
                      VALUES ('localhost', 3306, 'Local MySQL server', 10, 5, 2)
                      ON DUPLICATE KEY UPDATE updated = CURRENT_TIMESTAMP" ;
        $dbh->query( $insertSql ) ;
    } else {
        $body .= "<tr><td>host table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create host table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>host table</td><td>OK</td><td>-</td></tr>\n" ;
}

// ---------------------------------------------------------------------------
// Base table: host_group
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'host_group' ) ) {
    $sql = "CREATE TABLE host_group (
           host_group_id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
         , tag               VARCHAR( 16 ) NOT NULL DEFAULT ''
         , short_description VARCHAR( 255 ) NOT NULL DEFAULT ''
         , full_description  TEXT NULL DEFAULT NULL
         , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
         , UNIQUE ux_tag ( tag )
         ) ENGINE=InnoDB" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>host_group table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created host_group table" ;
        // Insert default groups
        $insertSql = "INSERT INTO host_group (tag, short_description, full_description) VALUES
                      ('localhost', 'localhost', 'localhost in all forms'),
                      ('prod', 'prod', 'Production'),
                      ('pilot', 'pilot', 'Pilot'),
                      ('stage', 'stage', 'Staging'),
                      ('qa', 'qa', 'QA'),
                      ('dev', 'dev', 'Development')
                      ON DUPLICATE KEY UPDATE updated = CURRENT_TIMESTAMP" ;
        $dbh->query( $insertSql ) ;
    } else {
        $body .= "<tr><td>host_group table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create host_group table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>host_group table</td><td>OK</td><td>-</td></tr>\n" ;
}

// ---------------------------------------------------------------------------
// Base table: host_group_map (depends on host and host_group)
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'host_group_map' ) ) {
    $sql = "CREATE TABLE host_group_map (
           host_group_id INT UNSIGNED NOT NULL
         , host_id       INT UNSIGNED NOT NULL
         , created       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , updated       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP
         , last_audited  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , PRIMARY KEY ux_host_group_host ( host_id, host_group_id )
         , FOREIGN KEY ( host_group_id ) REFERENCES host_group( host_group_id )
                                          ON DELETE RESTRICT ON UPDATE RESTRICT
         , FOREIGN KEY ( host_id ) REFERENCES host( host_id )
                                          ON DELETE RESTRICT ON UPDATE RESTRICT
         ) ENGINE=InnoDB COMMENT='Many-many relationship of groups and host'" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>host_group_map table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created host_group_map table" ;
    } else {
        $body .= "<tr><td>host_group_map table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create host_group_map table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>host_group_map table</td><td>OK</td><td>-</td></tr>\n" ;
}

// ---------------------------------------------------------------------------
// Base table: maintenance_window
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'maintenance_window' ) ) {
    $sql = "CREATE TABLE maintenance_window (
           window_id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
         , window_type       ENUM('scheduled', 'adhoc') NOT NULL
         , schedule_type     ENUM('weekly', 'monthly', 'quarterly', 'annually', 'periodic')
                               NULL DEFAULT 'weekly'
                               COMMENT 'Type of recurring schedule'
         , days_of_week      SET('Sun','Mon','Tue','Wed','Thu','Fri','Sat') NULL DEFAULT NULL
         , day_of_month      TINYINT UNSIGNED NULL DEFAULT NULL
                               COMMENT 'Day of month (1-31, 32=last day of month)'
         , month_of_year     TINYINT UNSIGNED NULL DEFAULT NULL
                               COMMENT 'Month of year (1-12) for quarterly/annually'
         , period_days       SMALLINT UNSIGNED NULL DEFAULT NULL
                               COMMENT 'Number of days between occurrences for periodic schedule'
         , period_start_date DATE NULL DEFAULT NULL
                               COMMENT 'Start date for periodic schedule calculation'
         , start_time        TIME NULL DEFAULT NULL
         , end_time          TIME NULL DEFAULT NULL
         , timezone          VARCHAR(64) NOT NULL DEFAULT 'America/Chicago'
         , silence_until     DATETIME NULL DEFAULT NULL
         , description       VARCHAR(255) NOT NULL DEFAULT ''
         , created_by        VARCHAR(64) NOT NULL DEFAULT ''
         , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
         , KEY idx_window_type ( window_type )
         , KEY idx_silence_until ( silence_until )
         ) ENGINE=InnoDB
           COMMENT='Maintenance window definitions - scheduled recurring or ad-hoc silencing'" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>maintenance_window table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created maintenance_window table" ;
    } else {
        $body .= "<tr><td>maintenance_window table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create maintenance_window table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>maintenance_window table</td><td>OK</td><td>-</td></tr>\n" ;
}

// ---------------------------------------------------------------------------
// Base table: maintenance_window_host_map
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'maintenance_window_host_map' ) ) {
    $sql = "CREATE TABLE maintenance_window_host_map (
           mw_host_map_id    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
         , window_id         INT UNSIGNED NOT NULL
         , host_id           INT UNSIGNED NOT NULL
         , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
         , UNIQUE KEY ux_window_host ( window_id, host_id )
         , FOREIGN KEY fk_mwh_window ( window_id ) REFERENCES maintenance_window( window_id )
                       ON DELETE CASCADE ON UPDATE CASCADE
         , FOREIGN KEY fk_mwh_host ( host_id ) REFERENCES host( host_id )
                       ON DELETE CASCADE ON UPDATE CASCADE
         ) ENGINE=InnoDB
           COMMENT='Maps maintenance windows to hosts'" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>maintenance_window_host_map table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created maintenance_window_host_map table" ;
    } else {
        $body .= "<tr><td>maintenance_window_host_map table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create maintenance_window_host_map table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>maintenance_window_host_map table</td><td>OK</td><td>-</td></tr>\n" ;
}

// ---------------------------------------------------------------------------
// Base table: maintenance_window_host_group_map
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'maintenance_window_host_group_map' ) ) {
    $sql = "CREATE TABLE maintenance_window_host_group_map (
           mw_host_group_map_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
         , window_id         INT UNSIGNED NOT NULL
         , host_group_id     INT UNSIGNED NOT NULL
         , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
         , UNIQUE KEY ux_window_host_group ( window_id, host_group_id )
         , FOREIGN KEY fk_mwg_window ( window_id ) REFERENCES maintenance_window( window_id )
                       ON DELETE CASCADE ON UPDATE CASCADE
         , FOREIGN KEY fk_mwg_host_group ( host_group_id ) REFERENCES host_group( host_group_id )
                       ON DELETE CASCADE ON UPDATE CASCADE
         ) ENGINE=InnoDB
           COMMENT='Maps maintenance windows to host groups'" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>maintenance_window_host_group_map table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created maintenance_window_host_group_map table" ;
    } else {
        $body .= "<tr><td>maintenance_window_host_group_map table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create maintenance_window_host_group_map table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>maintenance_window_host_group_map table</td><td>OK</td><td>-</td></tr>\n" ;
}

// ---------------------------------------------------------------------------
// Base table: blocking_history
// ---------------------------------------------------------------------------
if ( ! tableExists( $dbh, 'blocking_history' ) ) {
    $sql = "CREATE TABLE blocking_history (
           blocking_id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
         , host_id           INT UNSIGNED NOT NULL
         , query_hash        CHAR( 16 ) NOT NULL
                               COMMENT 'Hash of normalized query for deduplication'
         , user              VARCHAR( 64 ) NOT NULL
         , source_host       VARCHAR( 255 ) NOT NULL
         , db_name           VARCHAR( 64 ) NULL DEFAULT NULL
         , query_text        TEXT NOT NULL
                               COMMENT 'Normalized query text (strings/numbers replaced to avoid sensitive data)'
         , blocked_count     INT UNSIGNED NOT NULL DEFAULT 1
                               COMMENT 'Times this query was seen blocking (not total executions)'
         , total_blocked     INT UNSIGNED NOT NULL DEFAULT 1
                               COMMENT 'Sum of blocked queries each time this was seen blocking'
         , max_block_secs    INT UNSIGNED NOT NULL DEFAULT 0
                               COMMENT 'Maximum blocking duration seen in seconds'
         , first_seen        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
         , last_seen         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
         , UNIQUE KEY ux_host_hash ( host_id, query_hash )
         , KEY idx_last_seen ( last_seen )
         , KEY idx_blocked_count ( blocked_count DESC )
         , FOREIGN KEY fk_bh_host ( host_id ) REFERENCES host( host_id )
                       ON DELETE CASCADE ON UPDATE CASCADE
         ) ENGINE=InnoDB
           COMMENT='Historical record of blocking queries seen for pattern analysis'" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>blocking_history table</td><td>CREATED</td><td>Table created</td></tr>\n" ;
        $results[] = "Created blocking_history table" ;
    } else {
        $body .= "<tr><td>blocking_history table</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to create blocking_history table: " . $dbh->error ;
    }
} else {
    $body .= "<tr><td>blocking_history table</td><td>OK</td><td>-</td></tr>\n" ;
}

$body .= "</tbody>\n</table>\n" ;

// ///////////////////////////////////////////////////////////////////////////
// Schema Migrations (for upgrades)
// ///////////////////////////////////////////////////////////////////////////

$body .= "<h3>Schema Migrations</h3>\n" ;
$body .= "<table class='tablesorter'>\n" ;
$body .= "<thead><tr><th>Check</th><th>Status</th><th>Action</th></tr></thead>\n" ;
$body .= "<tbody>\n" ;

// ---------------------------------------------------------------------------
// Migration 001: Extended Schedule Types (for pre-existing installations)
// Add schedule_type, day_of_month, month_of_year, period_days, period_start_date
// These columns are already included in new table creation above, so this
// only applies when upgrading from an older version.
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Migration 002: Add max_block_secs to blocking_history
// ---------------------------------------------------------------------------

if ( tableExists( $dbh, 'blocking_history' ) && ! columnExists( $dbh, 'blocking_history', 'max_block_secs' ) ) {
    $sql = "ALTER TABLE blocking_history
              ADD COLUMN max_block_secs INT UNSIGNED NOT NULL DEFAULT 0
                  COMMENT 'Maximum blocking duration seen in seconds'
                  AFTER total_blocked" ;
    if ( $dbh->query( $sql ) ) {
        $body .= "<tr><td>max_block_secs column</td><td>ADDED</td><td>Column created</td></tr>\n" ;
        $results[] = "Added max_block_secs column to blocking_history" ;
    } else {
        $body .= "<tr><td>max_block_secs column</td><td>ERROR</td><td>" . htmlspecialchars( $dbh->error ) . "</td></tr>\n" ;
        $errors[] = "Failed to add max_block_secs column: " . $dbh->error ;
    }
} else if ( tableExists( $dbh, 'blocking_history' ) ) {
    $body .= "<tr><td>max_block_secs column</td><td>OK</td><td>-</td></tr>\n" ;
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
