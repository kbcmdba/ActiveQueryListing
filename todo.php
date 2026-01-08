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

// @todo 10 Add mute button for klaxon alerts
//          - URL parameter (e.g., ?mute=1) to disable sound temporarily
//          - Add "?" help icon with mouseover explaining:
//            * What the mute button does
//            * How to re-enable sound
//            * Chrome instructions for allowing sound from the site
// @todo 26 Add alternating row colors to index.php tables
//          - Apply aql-listing class to tables where rows aren't already colored
//          - Noteworthy and Full sections for Process, Overview, Slave tables
// @todo 27 Add version summary table
//          - Show count of hosts per MySQL/MariaDB version
//          - Mouseover on count shows list of hosts running that version
// @todo 28 Jira integration for File Issue button
//          - Configure in aql_config.xml: Jira URL, Project, Component (optional), auth
//          - Pre-fill issue with query data (PCI-masked), query time, user, source host
//          - Include database, lock state, and other relevant context
//          - Use Jira REST API to create issues
// @todo 29 Add light/dark mode toggle
//          - Current UI is dark mode only
//          - Add toggle to switch between light and dark themes
//          - Use URL parameter (e.g., ?theme=light) for easy sharing
// @todo 50 Add MS-SQL Server support (Large effort: 9-13 weeks full, 4-5 weeks MVP)
//          - Implement sqlsrv connection in DBConnection.php
//          - Rewrite AJAXgetaql.php queries using sys.dm_exec_* DMVs
//          - Convert INFORMATION_SCHEMA.PROCESSLIST to sys.dm_exec_requests/sessions
//          - Handle lock detection via sys.dm_tran_locks
//          - Update DDL (AUTO_INCREMENT -> IDENTITY, ENUM -> CHECK constraints)
//          - Skip replication monitoring for v1 (AlwaysOn is fundamentally different)
// @todo 51 Add CA cert to system trust store for LDAP SSL verification
// @todo 52 Add Redis support for long-running query monitoring
//          - Use CLIENT LIST to get connected clients and current commands
//          - Use SLOWLOG GET to retrieve slow queries
//          - Use INFO commandstats for command statistics
//          - Implement phpredis or Predis connection in DBConnection.php
//          - Add Redis-specific display in AJAXgetaql.php
// @todo 53 Add system load monitoring per host
//          - Read /proc/loadavg via SSH/agent or MySQL LOAD_FILE() if permitted
//          - Display load averages in Host Status Overview
// @todo 54 Add user statistics drilldown
//          - Display like Noteworthy Status Overview but per-user across all watched systems
//          - Columns: User, Longest Running, Idle Time (aggregate), Level counts (0-4, Error)
//          - Show RO/RW counts, Duplicate/Similar/Unique counts, Thread count
//          - Drilldown to see individual queries per user
// @todo 55 Add source host statistics drilldown
//          - Show which client hosts are hammering the database
//          - Query counts and time grouped by source host
//          - Drilldown to see queries from each source host
// @todo 60 Enable sortable table columns
//          - TableSorter JS is loaded but may not be initialized on all tables
//          - Click column header to sort ascending/descending
//          - Apply to Process, Overview, Slave, and other data tables
// @todo 99 Implement Host/Group Limiter
//          - There's a "Add Group Selection" button on the main index that *should* select all hosts/ports associated with the group (additive, not exclusive).
