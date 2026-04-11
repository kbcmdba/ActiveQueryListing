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
// @todo 05 Adopt PHPUnit for incremental TDD (parent - see subtasks)
//          The longer we delay, the harder full coverage gets.
//          Start with isolated, well-defined classes and expand outward.
//          Bootstrap: DONE. Run `composer test` or `composer test-coverage`.
// @todo 05-20 Config.php unit tests
//          DONE: 29 tests, 62% line coverage on Config.php.
//          Covers parseGroupedConfig, parseFlatConfig, parseDbTypes, credential
//          resolution, dbtype name normalization, format detection, Redis auth
//          regression, required param validation, buildConfigValueArray,
//          environment_types parsing, sort_order all-or-nothing rule, integer
//          field casting. Remaining 38% is mostly trivial getters.
// @todo 05-40 Tools.php / utility.php unit tests
//          - Test friendlyTime(), param(), query normalization
//          - These are pure functions — easiest to test
// @todo 05-50 AJAXgetaql.php handler tests (requires mocking DB connections)
//          - Test JSON output shape for each handler
//          - Test alert level thresholds
//          - Test blocking cache logic
//          - May need to extract handler logic into testable classes first
// @todo 05-60 Pre-commit hook integration
//          - Run PHPUnit on changed files before allowing commit
//          - Fast feedback loop — fail the commit if tests break
//          - Consider phpstan/psalm for static analysis alongside tests
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
// @todo 22 Add PostgreSQL monitoring support (see subtasks 22-10 through 22-50)
// @todo 22-10 PostgreSQL Phase 1 - Basic connectivity and processlist
//          - Install php-pgsql extension
//          - Add 'PostgreSQL' to db_type ENUM (deployDDL.php migration)
//          - Add postgresqlEnabled/Username/Password config params
//          - Create handlePostgreSQLHost() with pg_stat_activity processlist
//          - Wire up AJAX dispatch and JS frontend (dbTypeStats)
//          - Filter out AQL's own monitoring session unless debug=PostgreSQL
//          - Output same JSON shape as handleMySQLHost() (result[], overviewData, slaveData)
// @todo 22-20 PostgreSQL Phase 2 - Lock detection
//          - Query pg_locks joined with pg_stat_activity for blocking detection
//          - Map to same blockInfo structure as MySQL (isBlocked, isBlocking, etc.)
//          - Blocking cache integration (Redis/file fallback)
// @todo 22-30 PostgreSQL Phase 3 - Replication monitoring
//          - pg_stat_replication for primary servers
//          - pg_stat_wal_receiver for replica servers
//          - Map to slaveData[] format compatible with MySQL replication display
// @todo 22-40 PostgreSQL Phase 4 - Global status and overview
//          - pg_stat_database for QPS, transactions, tuple counts
//          - pg_settings for max_connections
//          - Version detection via version()
//          - Uptime via pg_postmaster_start_time()
// @todo 22-50 PostgreSQL Phase 5 - Debug mode diagnostics
//          - pg_stat_statements for query statistics (requires extension)
//          - pg_stat_bgwriter for checkpoint/buffer stats
//          - pg_stat_user_tables for table-level I/O stats
//          - Vacuum/autovacuum status
// @todo 22-56 Add environment CRUD to manageData.php
//          - Add "Environments" data type to manageData.php
//          - Allow add/edit/remove of environment names and sort order
//          - Validate no hosts reference env before allowing delete
// @todo 22-60 Scoreboard rows per dbType + environment combination
//          - e.g., "MySQL Prod", "MySQL Dev", "PG Prod", "Redis Staging"
//          - Requires environment column (22-55)
//          - Build scoreboardItems dynamically from distinct dbType+environment pairs
//          - Track stats per combination in dbTypeStats
// @todo 22-65 Scoreboard filter - configurable dbType/environment visibility
//          - Config or UI toggle to hide specific dbType+environment combos
//          - e.g., "ignore all Dev environments" for production-focused monitoring
//          - Could be per-user preference (localStorage) or global config
// @todo 23 Refactor AJAXgetaql.php to use DBType handler dispatch pattern
//          - handleMySQLHost(), handleRedisHost(), and handlePostgreSQLHost() now implemented
//          - Future: Use dispatch array: $handlers[$dbType]($hostname, $hostId, ...)
//          - Future: Split into separate files (AJAXgetmysqlDB.php, AJAXgetredisDB.php, AJAXgetpgsqlDB.php)
// @todo 24 Per-dbtype kill/cancel support (parent - see subtasks)
//          AJAXKillProc.php is MySQL-only. PG has kill buttons but they're broken.
//          Need dbtype-aware kill dispatch for all supported types.
// @todo 24-10 Refactor AJAXKillProc.php for dbtype dispatch
//          - Pass dbType from JS killProcOnHost() to AJAXKillProc.php
//          - Look up host's db_type from host table (or pass from frontend)
//          - Dispatch to per-type kill handler
// @todo 24-20 Per-type kill statements (replace single killStatement config)
//          - MySQL: KILL :pid
//          - RDS (MySQL): CALL mysql.rds_kill(:pid)
//          - Aurora (MySQL): CALL mysql.rds_kill(:pid)
//          - RDS (PostgreSQL): same as PG (pg_cancel/terminate_backend)
//          - PostgreSQL: SELECT pg_cancel_backend(:pid) (cancel query)
//                        SELECT pg_terminate_backend(:pid) (kill connection)
//          - MS-SQL: KILL :pid
//          - Oracle: ALTER SYSTEM KILL SESSION 'sid,serial#'
//          - GCP Cloud SQL: standard MySQL/PG kill (no wrapper needed)
//          - Azure: standard kill for Azure SQL / Azure DB for MySQL/PG
//          - Key insight: db_type is the CLOUD PLATFORM + ENGINE combination
//            RDS/Aurora are MySQL-compatible but need different kill commands
//            Users may run mixed on-prem + cloud, so db_type must distinguish
//          - Config: per-dbtype killStatement attribute on <dbtype> element
//          - Consider cancel vs terminate as separate actions in UI
// @todo 24-30 Per-type process verification before kill
//          - MySQL: INFORMATION_SCHEMA.PROCESSLIST (current)
//          - PostgreSQL: pg_stat_activity WHERE pid = :pid
//          - MS-SQL: sys.dm_exec_sessions + sys.dm_exec_requests
//          - Oracle: V$SESSION
//          - Each type needs its own connection method (PDO vs pg_connect vs oci)
// @todo 24-40 Per-type kill logging
//          - kill_log table currently MySQL-centric (thread ID, PROCESSLIST fields)
//          - Generalize columns or add db_type column
//          - PG uses pid (not thread ID), different state/command vocabulary
// @todo 24-50 UI: cancel vs terminate for PostgreSQL
//          - pg_cancel_backend() = cancel current query (safe, query stops)
//          - pg_terminate_backend() = kill entire connection (forceful)
//          - Show two buttons or a dropdown for PG hosts
//          - MySQL KILL is closer to pg_terminate_backend
// @todo 25 Move hardcoded values to config (parent - see subtasks)
// @todo 25-10 Blocking cache settings (AJAXgetaql.php lines 79-82)
//          - BLOCKING_CACHE_REDIS_HOST (127.0.0.1), BLOCKING_CACHE_REDIS_PORT (6379)
//          - BLOCKING_CACHE_TTL (60 seconds), BLOCKING_CACHE_REDIS_PREFIX ('aql:blocking:')
//          - Add <blockingCache> element or attributes on <redis>
// @todo 25-20 Timeout values
//          - DB connection timeout (DBConnection.php: 4 seconds)
//          - DB read timeout (DBConnection.php: 8 seconds)
//          - AJAX execution limit (AJAXgetaql.php: 10 seconds)
//          - LDAP timeout (LDAP.php: 10 seconds)
//          - PG connection timeout (AJAXgetaql.php: 4 seconds)
//          - Redis blocking cache connect timeout (AJAXgetaql.php: 0.5 seconds)
//          - Add <timeouts> element or per-group timeout attributes
// @todo 25-30 Redis alert thresholds (relates to @todo 40)
//          - Memory critical (95%), warning (80%)
//          - Fragmentation (100MB absolute)
//          - Blocked clients (5)
//          - Hit ratio (90% with >1000 requests)
//          - Replication lag (10 seconds)
//          - Slowlog duration thresholds (10/50/100/1000 ms)
// @todo 25-40 Display and query limits
//          - Redis command stats top N (10)
//          - Redis slowlog entries (10)
//          - Redis SCAN limit for streams (100)
//          - Redis command truncation (500 chars)
//          - Blocking history query limit (100 rows)
//          - Blocking history query preview truncation (80 chars)
// @todo 25-50 Maintenance window max duration (AJAXsilenceHost.php: 7 days)
// @todo 27 Auto-populated host groups with color badges (parent - see subtasks)
//          Extend host_group with auto_query (SQL WHERE clause) and per-mode colors.
//          Lets companies tag hosts dynamically without explicit membership management.
//          Use cases: backup window monitoring, revenue-impacting hosts, PCI scope,
//          reporting replicas, ETL targets, etc. - whatever each company needs.
// @todo 27-10 Schema migration (deployDDL.php)
//          - ALTER TABLE host_group ADD auto_query TEXT NULL
//                COMMENT 'SQL WHERE clause for automatic membership'
//          - ALTER TABLE host_group ADD color_dark VARCHAR(7) NULL
//                COMMENT 'Hex color for badge in dark mode'
//          - ALTER TABLE host_group ADD color_light VARCHAR(7) NULL
//                COMMENT 'Hex color for badge in light mode'
//          - Migration: existing groups have NULL auto_query (manual membership only)
// @todo 27-20 manageData.php CRUD for new fields
//          - Add color pickers (dark + light) to host_group form
//          - Add auto_query textarea with SQL safety warning
//          - Validate auto_query is a valid WHERE clause (no INSERT/UPDATE/DELETE)
//          - Live preview: show count of matching hosts as user types the query
// @todo 27-30 Auto-population logic
//          - On host save in manageData.php: re-evaluate all auto_query groups
//          - On group save with auto_query: populate/refresh host_host_group entries
//          - Periodic refresh? Or trigger-based? (probably on host changes)
//          - Avoid SQL injection: parameterize or whitelist columns/operators
// @todo 27-40 Badge rendering on dashboard
//          - Render badges next to hostname in main dashboard table
//          - CSS: use inline custom properties for per-group colors
//            <span class="host-badge" style="--badge-color-dark: #d32f2f;
//                                            --badge-color-light: #b71c1c;">
//          - .host-badge { background: var(--badge-color-dark); ... }
//          - .theme-light .host-badge { background: var(--badge-color-light); }
//          - Badge text color: contrast against badge bg (auto-calculate or use --bg-body)
//          - Sort badges by group sort_order or name
//          - Truncate long group names? Tooltip on hover?
// @todo 27-50 Migrate boolean columns to seeded auto-groups
//          - Create seeded groups: "RevenueImpacting", "ShouldBackup", "SchemaSpy"
//          - auto_query examples:
//            - "WHERE revenue_impacting = 1"
//            - "WHERE should_backup = 1"
//            - "WHERE should_schemaspy = 1"
//          - Default colors: red (revenue), blue (backup), green (schemaspy)
//          - Eventually deprecate the boolean columns? Or keep them as backing fields?
//          - Document migration in CLAUDE.md
// @todo 27-60 Group selection UI improvements
//          - Show badges in the group selection dropdown
//          - Distinguish manual groups from auto-populated visually (icon? italics?)
//          - Quick filter: "show only revenue impacting" as one click
// @todo 28 Input size limits / DOS protection (parent - see subtasks)
//          Prevent attackers from using AQL parameters to waste CPU/memory
//          or generate oversized SQL that could DOS the monitored databases.
// @todo 28-10 Per-parameter size caps in Tools::param() / Tools::post()
//          - Add optional max-length parameter (e.g., Tools::param('host', null, 1, 255))
//          - Reject inputs that exceed the cap (return null + log)
//          - Sensible defaults: hostnames 255, descriptions 1024, reasons 4096
// @todo 28-20 Reject oversized request bodies/URIs at PHP level
//          - Already handled by web server (nginx large_client_header_buffers,
//            Apache LimitRequestLine = 8190 default)
//          - But add a defense-in-depth check in Tools::param() for any single
//            input > 8K - return error and log
// @todo 28-30 Audit existing pages for unbounded user input
//          - kill reason in AJAXKillProc.php (currently TEXT in DB)
//          - silence reason in AJAXsilenceHost.php
//          - description in manageData.php host form (already maxlength=65535 - too big?)
//          - Any other free-form fields
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
