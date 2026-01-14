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
// @todo 20-35 Redis Debug Mode - Additional diagnostics when debug=1
//          - High Value (troubleshooting essentials):
//            - Keyspace breakdown: keys per DB, keys with TTL, expired count
//            - Ops/sec & throughput: instantaneous_ops_per_sec, input/output_kbps
//            - CPU usage: used_cpu_sys, used_cpu_user
//            - Persistence status: bgsave/AOF rewrite in progress, last bgsave status
//            - Client buffer details: output buffer (omem), query buffer (qbuf)
//          - Medium Value (deeper diagnostics):
//            - Replication details (replicas): master link status, seconds since IO, offset lag
//            - Latency events: display LATENCY LATEST spike data (already gathered)
//            - Pub/Sub details: channel names, subscribers per channel (NUMSUB)
//            - Client flags decoded: N=normal, M=master, S=slave, O=MONITOR, etc.
//          - Lower Priority:
//            - Memory by data type: strings vs lists vs hashes vs sets vs zsets
//            - Cluster topology (if clustered): slots, nodes, state
// @todo 20-40 Redis Phase 4 - Enterprise Features
//          - SSL/TLS connection support
//          - Redis Cluster topology awareness
//          - Redis Sentinel monitoring
// @todo 21 Per-database-type debug mode
//          - Replace single "Debug Mode" checkbox with per-type checkboxes
//          - Only show checkboxes for DB types that exist in host table (not all supported types)
//          - Query: SELECT DISTINCT db_type FROM host WHERE active = 1
//          - URL params: debugMySQL=1, debugRedis=1, etc. (instead of debug=1)
//          - Benefits:
//            - Debug Redis without flooding display with MySQL debug info
//            - More targeted troubleshooting
//            - Preserves backward compat: debug=1 could enable all types
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
// @todo 99 Implement Host/Group Limiter
//          - There's a "Add Group Selection" button on the main index that *should* select all hosts/ports associated with the group (additive, not exclusive).
