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
// @todo 20 Add Redis support for monitoring (Medium effort: 2-3 weeks MVP, 4-5 weeks full)
//          Connection & Config:
//          - Implement phpredis or Predis connection in DBConnection.php
//          - Add Redis as db_type in host table, config for auth/SSL
//          - Add Redis-specific display in AJAXgetaql.php
//          Key Monitoring Commands:
//          - CLIENT LIST: connected clients, idle time, current command, connection age
//          - SLOWLOG GET: historical slow commands (find KEYS *, large SMEMBERS, etc.)
//          - INFO sections: clients, memory, stats, replication, persistence, commandstats
//          - LATENCY DOCTOR/LATEST/HISTORY: latency spike detection (Redis 2.8.13+)
//          - PUBSUB CHANNELS/NUMSUB: pub/sub channel and subscriber counts
//          - XINFO STREAM/XPENDING: Streams backlog monitoring (Redis 5.0+)
//          - MEMORY DOCTOR/STATS: memory fragmentation and issues (Redis 4.0+)
//          Critical Metrics to Display:
//          - connected_clients, blocked_clients (waiting on BLPOP etc.)
//          - used_memory vs maxmemory (eviction risk)
//          - Cache hit ratio: keyspace_hits / (keyspace_hits + keyspace_misses)
//          - expired_keys, evicted_keys (evicted = data loss!)
//          - rejected_connections (maxclients exceeded)
//          - Memory fragmentation ratio (used_memory_rss / used_memory, alert if >1.5)
//          - Replication lag (master_repl_offset vs slave offset)
//          - Per-command stats: calls, total_time, avg_time (find expensive ops)
//          - Persistence: last RDB save status, AOF rewrite status
//          Alert Conditions:
//          - evicted_keys increasing (at maxmemory, losing data)
//          - rejected_connections > 0
//          - blocked_clients high
//          - Fragmentation ratio > 1.5
//          - Replication lag growing
//          - KEYS/SMEMBERS on large keyspaces in slowlog
// @todo 23 Refactor AJAXgetaql.php to use DBType handler dispatch pattern
//          - Create handleMySQLHost() function (extract existing MySQL code)
//          - Use dispatch array: $handlers[$dbType]($hostname, $hostId, $hostGroups, $maintenanceInfo, $config)
//          - Enables cleaner addition of future DBTypes (MongoDB, MS-SQL, etc.)
//          - handleRedisHost() already implemented as reference pattern
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
