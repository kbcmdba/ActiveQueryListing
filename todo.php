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

// @todo 02 Build out testAQL.php as comprehensive test harness (parent - see sub-tasks below)
// @todo 02-60 LDAP/authentication tests (when doLDAPAuthentication=true)
//             - Test LDAP server connectivity
//             - Verify SSL certificate (if ldapVerifyCert=true)
//             - Test bind with a known test credential (optional, manual)
// @todo 02-80 Verify host permissions when adding new host in manageData.php
//             - Test connection to new host with configured credentials
//             - Verify PROCESS privilege before allowing host to be added
//             - Verify REPLICATION CLIENT if replication monitoring enabled
//             - Display clear error if permissions are missing
// @todo 03-20 Log blocking queries for historical analysis
//             - Store blocking events in database table (host, thread_id, user, query_hash, query_text, blocked_count, timestamp)
//             - Auto-purge entries older than 90 days to prevent unbounded growth
//             - UI to view blocking history: filter by host, user, query pattern
//             - Identify repeat offenders and problematic query patterns
//             - Could feed into automation/alerting for known bad queries
// @todo 04 Alternating row colors for all alert levels in Full Process Listing
//          - Each level gets two shades: bright and muted (e.g., level4: red/dark red)
//          - Helps visually track consecutive rows of the same level
//          - Apply to level0 through level4 (and error level if applicable)
//          - Add version query param to main.css in WebPage.php for cache busting
// @todo 17 Implement maintenance windows for hosts (parent - see sub-tasks below)
// @todo 17-30 Ad-hoc "do it live" silencing
//             - Per-host or per-group silence button: "Silence for X minutes/hours"
//             - Quick presets like global mute (30m, 1h, 2h, etc.)
//             - DBA silences host/group when issue is "being worked on"
//             - Store as ad-hoc window with silence_until timestamp
// @todo 17-40 Backend: Check if host/group is in active maintenance window
//             - Function to check scheduled windows (day-of-week + time range)
//             - Function to check ad-hoc silencing (silence_until > now)
//             - Handle overnight spans correctly
//             - Return window info for display
// @todo 17-50 index.php: Visual indicators for hosts in maintenance
//             - Icon/badge showing host is in maintenance window
//             - Tooltip with window details (scheduled vs ad-hoc, expiry)
//             - Different indicators for scheduled vs ad-hoc
// @todo 17-60 index.php: Quick link to manage host/group maintenance
//             - Click host to open maintenance management (modal or link to manageData.php)
//             - Pre-select the host/group in the management UI
//             - Quick ad-hoc silence directly from index.php
// @todo 17-70 Alert suppression integration
//             - Modify klaxon.js to check maintenance status
//             - Suppress alerts for hosts/groups in active window
//             - Still display data, just don't sound alerts
// @todo 17-80 DBA credential session persistence
//             - Remember DBA credentials for a configurable period (e.g., 24 hours)
//             - Store auth in session with expiry timestamp
//             - Reduce friction for DBAs managing maintenance windows
//             - Configurable timeout in aql_config.xml
// @todo 25 Add light/dark mode toggle
//          - Current UI is dark mode only
//          - Use CSS variables for colors (--bg-color, --text-color, etc.)
//          - Add .theme-light class that overrides the variables
//          - JavaScript toggles class on body based on cookie/URL param
//          - URL parameter (e.g., ?theme=light) for easy sharing, syncs to cookie
// @todo 30 MS-SQL Server support (Large effort: 9-13 weeks full, 4-5 weeks MVP)
//          - Implement sqlsrv connection in DBConnection.php
//          - Rewrite AJAXgetaql.php queries using sys.dm_exec_* DMVs
//          - Convert INFORMATION_SCHEMA.PROCESSLIST to sys.dm_exec_requests/sessions
//          - Handle lock detection via sys.dm_tran_locks
//          - Update DDL (AUTO_INCREMENT -> IDENTITY, ENUM -> CHECK constraints)
//          - Skip replication monitoring for v1 (AlwaysOn is fundamentally different)
// @todo 35 Add CA cert to system trust store for LDAP SSL verification
// @todo 36 Renew LDAP server SSL certificate (EXTERNAL - AD admin task)
//          - Certificate on ce-cook-adc1101.cashtn.com expired Jan 9, 2025
//          - Currently using ldapVerifyCert=false as workaround (security risk)
//          - Once renewed, remove ldapVerifyCert=false from aql_config.xml
// @todo 37 Create verifyAQLConfiguration.php for new user setup guidance
//          - Show all config parameters (required and optional) with current status
//          - Green checkmark for configured, red X for missing required, yellow for optional not set
//          - Test database connectivity and report results
//          - Test LDAP connectivity if doLDAPAuthentication=true
//          - Test Jira API if jiraEnabled=true
//          - Guide users through fixing configuration issues
//          - Document in README.md alongside testAQL.php
// @todo 40 Add Redis support for long-running query monitoring
//          - Use CLIENT LIST to get connected clients and current commands
//          - Use SLOWLOG GET to retrieve slow queries
//          - Use INFO commandstats for command statistics
//          - Implement phpredis or Predis connection in DBConnection.php
//          - Add Redis-specific display in AJAXgetaql.php
// @todo 45 Add system load monitoring per host
//          - Read /proc/loadavg via SSH/agent or MySQL LOAD_FILE() if permitted
//          - Display load averages in Host Status Overview
// @todo 50 Add user statistics drilldown
//          - Display like Noteworthy Status Overview but per-user across all watched systems
//          - Columns: User, Longest Running, Idle Time (aggregate), Level counts (0-4, Error)
//          - Show RO/RW counts, Duplicate/Similar/Unique counts, Thread count
//          - Drilldown to see individual queries per user
// @todo 55 Add source host statistics drilldown
//          - Show which client hosts are hammering the database
//          - Query counts and time grouped by source host
//          - Drilldown to see queries from each source host
// @todo 60 Optional klaxon alert for blocking queries (low priority - revisit when alert noise reduced)
//          - Configurable toggle (off by default)
//          - Threshold-based: only alert if blocking N+ queries (e.g., 5) for X+ seconds (e.g., 30)
//          - Consider separate, gentler sound to distinguish from long-running alerts
//          - Currently blockers get level 3, so they alert if they hit warning time threshold
// @todo 99 Implement Host/Group Limiter
//          - There's a "Add Group Selection" button on the main index that *should* select all hosts/ports associated with the group (additive, not exclusive).
