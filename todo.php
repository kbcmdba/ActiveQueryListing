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
// @todo 15 Per-tab vs per-browser silencing behavior (UNDER CONSIDERATION)
//          - Currently uses localStorage (shared across all tabs)
//          - Question: Should silencing a host affect all tabs or just current tab?
//          - Option A: Keep current behavior (all tabs share silence state)
//          - Option B: Use sessionStorage for per-tab silencing
//          - Option C: User preference to choose behavior
//          - Consider: Multiple DBAs monitoring same hosts in different contexts
//          - Consider: "Silenced by" tracking - who/which session silenced the host
// @todo 20 Add Redis support for monitoring (see subtasks 20-35 through 20-40)
// @todo 20-35 Redis Debug Mode - Additional diagnostics when debug=Redis
//          - HIGH VALUE COMPLETE: keyspace, ops/sec, CPU, persistence, client buffers
//          - Medium Value (deeper diagnostics):
//            - Replication details (replicas): master link status, seconds since IO, offset lag
//            - Latency events: display LATENCY LATEST spike data (already gathered)
//            - Pub/Sub details: channel names, subscribers per channel (NUMSUB)
//            - Client flags decoded: N=normal, M=master, S=slave, O=MONITOR, etc.
//          - Lower Priority:
//            - Memory by data type: strings vs lists vs hashes vs sets vs zsets
//            - Cluster topology (if clustered): slots, nodes, state
// @todo 20-40 Redis Phase 4 - OSS Advanced Features
//          - SSL/TLS connection support
//          - Redis Cluster topology awareness (CLUSTER INFO, CLUSTER NODES, CLUSTER SLOTS)
//          - Redis Sentinel monitoring (SENTINEL commands)
// @todo 20-43 Revisit Redis alert thresholds after production testing
//          - Current thresholds set without real-world validation
//          - Level 4: evicted>0, rejected>0, mem%>95, replication broken
//          - Level 3: frag>100MB, blocked>5, mem%>80
//          - Level 2: hit%<90 (>1000 req), repl lag>10s
//          - May need per-host thresholds (see @todo 40)
// @todo 20-45 Redis Phase 5 - Redis Enterprise (RLEC) Support
//          - Add RedisEnterprise as separate db_type (different monitoring approach)
//          - REST API integration for cluster-level metrics
//          - Multi-database per cluster awareness
//          - Proxy layer authentication handling
//          - Enterprise-specific metrics (Active-Active replication, Redis on Flash stats)
//          - Admin console API for node/shard health
// @todo 23 Refactor AJAXgetaql.php to use DBType handler dispatch pattern
//          - handleMySQLHost() and handleRedisHost() now implemented
//          - Future: Use dispatch array: $handlers[$dbType]($hostname, $hostId, ...)
//          - Future: Split into separate files (AJAXgetmysqlDB.php, AJAXgetredisDB.php)
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
// @todo 40 Per-host alert thresholds for all DB types
//          - MySQL: already has time-based thresholds (alert_crit_secs, etc.) per host
//          - Redis: currently hardcoded (frag_bytes > 100MB, mem_pct > 80%, etc.)
//          - MS-SQL: will need its own thresholds when implemented
//          - Design: Alert Template system
//            - alert_template table: template_id, name, db_type, thresholds (JSON)
//            - host table: add alert_template_id FK (nullable for custom overrides)
//            - Templates define common configurations (e.g., "Production Redis", "Dev MySQL")
//            - Hosts can use a template OR have custom thresholds (JSON column)
//            - Precedence: host custom > host template > global defaults
//          - Benefits:
//            - New hosts can inherit from template - easy onboarding
//            - Change template = update all hosts using it
//            - Still allows per-host customization when needed
//          - Need to update manageData.php for template CRUD and host assignment
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
// @todo 65 Auto-unsilence after service recovery
//          - When silencing a host/group, add option "Re-enable alerts when service recovers"
//          - Track the error condition that triggered silencing (e.g., host unreachable, specific error)
//          - When condition clears (host returns to level 0-2), automatically remove silence
//          - Works for both local (browser) and global (database) silencing
//          - May need grace period to avoid flapping (e.g., must be healthy for N minutes)
// @todo 70 Start autorefresh countdown after AJAX completes
//          - Currently countdown starts at page load
//          - When AJAX is slow, page refreshes before users can evaluate data
//          - Move startAutorefresh() call to end of loadPage() after all data rendered
// @todo 72 AJAX Render Times enhancements (parent - see subtasks below)
// @todo 72-10 Store render times in Redis for historical ranking
//          - Must be gated by user configuration (opt-in, not automatic)
//          - Store per-host render time data in Redis sorted sets
//          - Allow ranking by slowest hosts over time
// @todo 72-15 Per-phase server-side timing breakdown display
//          - Clickable/expandable detail per host showing phase timings
//          - MySQL phases: connect, globalStatus, lockDetection, processlist, replication
//          - Redis phases: connect, info, commandStats, slowlog, clients, memoryStats, latency, streams
//          - Helps identify which specific operation is causing slowness
// @todo 72-20 Configurable section position
//          - Config option to place render times first, middle, or last on page
//          - User preference stored in config or cookie
// @todo 72-30 Per-host minimum render time threshold
//          - Add column to aql_db.host for render_time_threshold_ms
//          - Only display hosts in render times table if they exceed threshold
//          - Reduces noise when most hosts are fast
// @todo 72-40 Dynamic positioning based on overall render performance
//          - When render times are high, move section higher on page for visibility
//          - When render times are normal, keep at default position
// @todo 72-50 Render times exportable report
//          - Downloadable/printable report with all render time data plus server breakdown
//          - CSV or HTML format with per-phase timing columns
//          - Useful for capacity planning and performance trending
// @todo 98 Group Mute/Unmute locally
//          - Allow muting/unmuting all hosts in a group via localStorage
//          - Add group-level silence icon/link in UI
//          - Leverage existing hostGroupMap for group membership
// @todo 99 Implement Host/Group Limiter
//          - There's a "Add Group Selection" button on the main index that *should* select all hosts/ports associated with the group (additive, not exclusive).
