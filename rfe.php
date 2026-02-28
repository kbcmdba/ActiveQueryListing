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

// ============================================================================
// Requests for Enhancement (RFE)
// ============================================================================
//
// This file tracks larger feature requests and architectural enhancements
// that would significantly expand AQL's capabilities. These are not yet
// prioritized or scheduled - they represent the "what could be" roadmap.
//
// See todo.php for near-term actionable items.
//
// ============================================================================

// ----------------------------------------------------------------------------
// VERSION ROADMAP
// ----------------------------------------------------------------------------
// @rfe 001 v3 - Complete Redis, Polish PHP Codebase
//          - Finish Redis monitoring (todo 20-xx series)
//          - Enhance UI/UX (Settings dropdown, navigation, render times)
//          - Stabilize and polish for production use
//          - Stack: PHP + jQuery (current)
//
// @rfe 002 v3.x - Expand DB Platform Support
//          - Add monitoring for platforms beyond MySQL/Redis
//          - Candidates: MS-SQL, PostgreSQL, Oracle, Oracle RAC, MongoDB,
//            Cassandra, and others (not committed yet)
//          - Each new platform informs patterns and abstractions for v4
//          - Platform selection depends on cost, access, and production relevance
//
// @rfe 003 v4 - Rewrite with TDD/BDD
//          - Full rewrite, potentially in Node.js
//          - Before starting: catalog all features, decisions, and lessons from v2-v3
//          - TDD/BDD methodology from day one
//          - Not the final version - foundation for continued evolution
//
// @rfe 004 Server-side data gathering (deferred to v3.9x or v4.9x)
//          - Central poller replaces per-browser AJAX polling
//          - Enables historical data: seconds behind master graphs,
//            logged-in user trends, performance trending over time
//          - Reduces load on monitored servers (1 poller vs N browsers)
//          - See @rfe 010 for architectural details

// ----------------------------------------------------------------------------
// ARCHITECTURE
// ----------------------------------------------------------------------------
// @rfe 010 Server-based headless polling
//          - AQL server polls monitored databases centrally (not browsers)
//          - Reduces load on monitored servers (1 poller vs N browser clients)
//          - Consistent point-in-time snapshots across all viewers
//          - Enables server-side logging and recording
//          - Prerequisite for historical data (@rfe 100) and alerting (@rfe 200)
//          - Configurable poll interval per host/group
//          - Clients fetch cached results via AJAX (fast, lightweight)
//          - Optional: real-time push via WebSockets/SSE
//
// @rfe 011 Database handler abstraction
//          - Each database family requires different query mechanisms:
//            * MySQL/MariaDB: SHOW PROCESSLIST, INFORMATION_SCHEMA
//            * PostgreSQL: pg_stat_activity, pg_locks
//            * MongoDB: currentOp(), db.serverStatus()
//            * Redis: CLIENT LIST, SLOWLOG GET
//            * Oracle: V$SESSION, V$SQL
//            * SQL Server: sys.dm_exec_requests, sys.dm_exec_sessions
//          - Option A: Separate scripts (AJAXgetpg.php, AJAXgetmongo.php, etc.)
//            * Simple, isolated, easy to maintain independently
//            * Frontend must know which endpoint per host type
//          - Option B: Unified router with handler classes
//            * Single AJAXget.php dispatches to DbHandler subclasses
//            * Cleaner API, more abstraction overhead
//          - Either approach requires dbType field in host configuration
//          - With @rfe 010 (server polling), dispatch logic moves server-side
//            and clients receive normalized results regardless of DB type
//
// @rfe 012 Multi-server polling for redundancy
//          - Multiple AQL polling servers share the workload
//          - Fault tolerance: if one poller fails, others continue
//          - Maintenance friendly: take a poller offline without blind spots
//          - Load distribution: split monitored hosts across pollers
//          - Leader election or shared state (Redis/etcd) for coordination
//          - Pollers claim hosts to avoid duplicate polling
//          - Health checks between pollers to detect failures
//          - Automatic failover: surviving pollers pick up orphaned hosts
//          - Scales horizontally as monitored host count grows
//          - Relates to @rfe 602 (High availability)

// ----------------------------------------------------------------------------
// HISTORICAL DATA & TRENDING
// ----------------------------------------------------------------------------
// @rfe 100 Historical query data storage
//          - Store snapshots of processlist data periodically (configurable interval)
//          - Enable "what was happening at 3 AM?" post-mortem analysis
//          - Query history retention policy (days/weeks configurable)
//          - Storage considerations: separate database? time-series DB?
//          - UI: timeline view, playback mode, time range selector
//
// @rfe 101 Query trending and analysis
//          - Track query fingerprints over time (normalize parameters)
//          - Identify queries getting slower (execution time trending up)
//          - Detect new queries that appeared recently
//          - "Top N" reports: slowest queries, most frequent, most blocking
//          - Baseline comparison: "this is 50% slower than usual"

// ----------------------------------------------------------------------------
// ALERTING & NOTIFICATIONS
// ----------------------------------------------------------------------------
// @rfe 200 Proactive alerting system
//          - Don't require eyeballs on screen to notice problems
//          - Configurable alert rules (e.g., "any query > 5 min", "blocking > 3 queries")
//          - Alert fatigue prevention: cooldown periods, aggregation
//          - Escalation policies (warn -> critical -> page)
//
// @rfe 201 PagerDuty integration
//          - Trigger incidents for critical alerts
//          - Auto-resolve when condition clears
//          - Include query details in incident
//
// @rfe 202 Slack/Teams integration
//          - Post alerts to configurable channels
//          - Rich formatting with query details
//          - Action buttons (silence, file issue)
//
// @rfe 203 Email alerting
//          - Basic SMTP integration for alerts
//          - Digest mode option (batch alerts over time window)
//
// @rfe 204 Webhook support
//          - Generic webhook for custom integrations
//          - Configurable payload format
//          - Enables integration with any alerting platform

// ----------------------------------------------------------------------------
// ADDITIONAL DATABASE SUPPORT
// ----------------------------------------------------------------------------
// @rfe 300 PostgreSQL support
//          - Use pg_stat_activity for process listing
//          - pg_locks for blocking detection
//          - pg_stat_statements for query statistics
//          - Replication status via pg_stat_replication
//
// @rfe 301 MongoDB support
//          - Use currentOp() for active operations
//          - db.serverStatus() for server metrics
//          - Replica set status monitoring
//          - Note: Previously attempted at SHC - challenging due to
//            fundamental differences in query model and lack of
//            standardized "processlist" equivalent
//
// @rfe 302 Cassandra/DataStax support
//          - nodetool netstats, tpstats for thread pool status
//          - system.peers for cluster topology
//          - Slow query log parsing
//          - Note: Previously attempted at SHC - Cassandra's distributed
//            nature makes "active queries" concept less straightforward
//
// @rfe 303 Oracle Database support
//          - V$SESSION, V$SQL for active queries
//          - V$LOCK for blocking detection
//          - ASH (Active Session History) integration
//          - AWR data access for historical analysis
//          - Requires Oracle client libraries (oci8)
//
// @rfe 304 Oracle RAC support
//          - GV$ views for cluster-wide visibility
//          - Instance-level and cluster-level views
//          - Interconnect traffic monitoring
//          - Global cache coordination visibility
//          - Node failover awareness
//
// @rfe 305 MySQL NDB Cluster support
//          - ndbinfo schema for cluster status
//          - Data node monitoring
//          - Transaction coordinator visibility
//          - Memory usage per node
//          - Network partitioning detection
//
// @rfe 306 Amazon RDS/Aurora enhanced monitoring
//          - CloudWatch metrics integration
//          - Performance Insights API
//          - Enhanced monitoring OS metrics
//
// @rfe 307 Galera Cluster support
//          - wsrep_* status variables
//          - Flow control monitoring (critical for performance)
//          - Cluster state and node health
//          - Certification conflicts visibility
//          - Note: Galera can be temperamental - good monitoring is essential
//
// @rfe 308 CockroachDB support
//          - crdb_internal schema for cluster status
//          - SHOW QUERIES for active statements
//          - Distributed transaction visibility
//          - Range distribution monitoring
//
// @rfe 309 TiDB support
//          - INFORMATION_SCHEMA.CLUSTER_PROCESSLIST
//          - TiKV and PD component status
//          - Distributed execution plan visibility
//          - Hot region detection
//
// @rfe 310 Vitess support
//          - VTGate query monitoring
//          - VTTablet status per shard
//          - Keyspace/shard topology visibility
//          - Query routing transparency
//
// @rfe 311 Google Cloud Spanner support
//          - Query statistics via SPANNER_SYS
//          - Transaction and lock insights
//          - Read/write latency by operation
//          - Requires GCP client libraries
//
// @rfe 312 Azure SQL Database support
//          - sys.dm_exec_requests for active queries
//          - sys.dm_exec_query_stats for performance
//          - Azure-specific DMVs for resource governance
//          - Elastic pool monitoring
//          - DTU/vCore utilization visibility
//
// @rfe 312a Google Cloud SQL for MySQL support
//          - Standard MySQL monitoring (MySQL-compatible)
//          - Cloud SQL Admin API for instance metrics
//          - CPU, memory, disk, connections via API
//          - Replica lag and failover status
//          - Maintenance window awareness
//
// @rfe 312b Azure Database for MySQL support
//          - Standard MySQL monitoring (MySQL-compatible)
//          - Azure Monitor metrics integration
//          - Flexible Server specific metrics
//          - Storage and IOPS utilization
//          - High availability status
//
// @rfe 313 Percona XtraDB Cluster support
//          - wsrep status (similar to Galera)
//          - PXC-specific status variables
//          - Cluster node health monitoring
//          - SST/IST transfer visibility
//          - Desync node detection
//
// @rfe 314 MariaDB Xpand (formerly ClustrixDB) support
//          - system.sessions for active queries
//          - Distributed query execution visibility
//          - Slice/node distribution monitoring
//          - Rebalancing status
//
// @rfe 315 Redis Enterprise (RLEC) support
//          - Cluster API for node status
//          - Database metrics per shard
//          - Proxy connection monitoring
//          - Memory and throughput per database
//          - Active-Active geo-replication status
//
// @rfe 316 Memcached support
//          - stats command for basic metrics
//          - Connection count monitoring
//          - Hit/miss ratios
//          - Memory utilization
//          - Slab allocation visibility
//
// @rfe 317 Elasticsearch support
//          - _tasks API for running operations
//          - _nodes/stats for cluster health
//          - Slow query log integration
//          - Index and shard status
//          - Thread pool utilization
//
// @rfe 318 ClickHouse support
//          - system.processes for active queries
//          - system.query_log for history
//          - Distributed query tracking
//          - Merge and mutation progress
//          - Replica sync status
//
// @rfe 319 Snowflake support
//          - QUERY_HISTORY view
//          - Warehouse utilization
//          - Queued vs running queries
//          - Credit consumption visibility
//          - Requires Snowflake connector
//
// @rfe 320 Amazon DynamoDB support
//          - CloudWatch metrics integration
//          - Consumed capacity monitoring
//          - Throttling detection
//          - GSI/LSI utilization
//          - On-demand vs provisioned visibility
//
// @rfe 321 Couchbase support
//          - N1QL active requests
//          - Bucket and node statistics
//          - XDCR replication monitoring
//          - Index service status
//          - Memory and disk utilization
//
// @rfe 322 Neo4j support
//          - dbms.listQueries() for active queries
//          - Cypher query monitoring
//          - Cluster routing visibility
//          - Transaction and lock status
//          - Heap and page cache metrics
//
// @rfe 323 SingleStore (MemSQL) support
//          - SHOW PROCESSLIST (MySQL-compatible)
//          - Distributed query visibility
//          - Leaf and aggregator node status
//          - Pipeline ingestion monitoring
//          - Memory and storage utilization
//
// @rfe 324 YugabyteDB support
//          - pg_stat_activity (PostgreSQL-compatible)
//          - Tablet and node status
//          - Distributed transaction visibility
//          - Cluster rebalancing status
//          - YSQL and YCQL monitoring
//
// @rfe 325 TimescaleDB support
//          - pg_stat_activity (PostgreSQL-compatible)
//          - Hypertable chunk status
//          - Continuous aggregate refresh status
//          - Compression job monitoring
//          - Retention policy visibility
//
// @rfe 326 Apache Hadoop/HBase support
//          - The original "commodity hardware mega cluster"
//          - HBase: scan of hbase:meta for region status
//          - HBase shell or REST API for active operations
//          - HDFS: NameNode and DataNode status
//          - YARN: ResourceManager job tracking
//          - MapReduce/Spark job visibility
//
// @rfe 327 ScyllaDB support
//          - Cassandra-compatible, C++ implementation
//          - nodetool or REST API for cluster status
//          - Per-shard metrics visibility
//          - Latency histogram tracking
//          - Compaction and repair status
//
// @rfe 328 Greenplum support
//          - MPP PostgreSQL-based analytics
//          - pg_stat_activity across segments
//          - gp_segment_configuration for cluster topology
//          - Query dispatcher and executor visibility
//          - Resource queue monitoring
//
// @rfe 329 Apache Druid support
//          - Real-time analytics database
//          - /druid/v2 API for running queries
//          - Coordinator and Broker status
//          - Segment loading and availability
//          - Ingestion task monitoring
//
// @rfe 330 Apache Pinot support
//          - Real-time OLAP datastore
//          - Controller API for cluster status
//          - Broker query tracking
//          - Segment and table status
//          - Minion task visibility
//
// @rfe 331 FoundationDB support
//          - Apple's distributed key-value store
//          - fdbcli status for cluster health
//          - Transaction latency probes
//          - Storage server utilization
//          - Coordination state visibility
//
// @rfe 332 Riak support
//          - Distributed key-value database
//          - riak-admin status for node health
//          - Active Anti-Entropy status
//          - Handoff monitoring
//          - Ring membership visibility
//
// @rfe 333 SQLite support
//          - Embedded database, but widely used
//          - sqlite3_status() for memory and cache stats
//          - PRAGMA database_list for attached databases
//          - PRAGMA table_info for schema inspection
//          - WAL checkpoint status
//          - Useful for monitoring app-embedded databases
//
// @rfe 334 Berkeley DB (BDB) support
//          - Oracle's embedded key-value store
//          - db_stat utility for database statistics
//          - Lock and transaction statistics
//          - Buffer pool and cache metrics
//          - Replication status (if configured)
//          - Note: Legacy but still in use in many systems

// ----------------------------------------------------------------------------
// APM & OBSERVABILITY INTEGRATION
// ----------------------------------------------------------------------------
// @rfe 400 OpenTelemetry integration
//          - Export traces/metrics in OTel format
//          - Correlate database queries with application traces
//          - Trace context propagation (if available in query comments)
//
// @rfe 401 Datadog integration
//          - Push metrics to Datadog
//          - Link to Datadog APM traces
//          - Custom dashboard widgets
//
// @rfe 402 Prometheus metrics endpoint
//          - /metrics endpoint for scraping
//          - Standard metric naming conventions
//          - Grafana dashboard templates

// ----------------------------------------------------------------------------
// API & EXTENSIBILITY
// ----------------------------------------------------------------------------
// @rfe 500 REST API
//          - Programmatic access to current state
//          - Query history (if @rfe 100 implemented)
//          - Host/group management
//          - Silence/maintenance window management
//          - Authentication (API keys or OAuth)
//
// @rfe 501 Plugin architecture
//          - Allow custom database adapters
//          - Custom alert handlers
//          - Custom UI widgets

// ----------------------------------------------------------------------------
// DEPLOYMENT & OPERATIONS
// ----------------------------------------------------------------------------
// @rfe 600 Docker containerization
//          - Official Dockerfile
//          - Docker Compose for AQL + database
//          - Environment variable configuration
//
// @rfe 601 Kubernetes/Helm support
//          - Helm chart for deployment
//          - ConfigMaps for configuration
//          - Horizontal scaling considerations
//
// @rfe 602 High availability
//          - Multiple AQL instances
//          - Shared state (Redis?)
//          - Load balancer friendly
//
// @rfe 603 Ansible deployment
//          - Ansible playbook for installation
//          - Role-based configuration
//          - Inventory-driven host setup
//          - Secrets via Ansible Vault
//          - Idempotent upgrades

// ----------------------------------------------------------------------------
// UI/UX ENHANCEMENTS
// ----------------------------------------------------------------------------
// @rfe 700 Mobile-responsive design
//          - Usable on phone/tablet
//          - Touch-friendly controls
//          - Condensed views for small screens
//
// @rfe 701 Customizable dashboards
//          - User-defined layouts
//          - Widget selection
//          - Saved views per user
//
// @rfe 702 Query plan visualization
//          - EXPLAIN output display
//          - Visual execution plan (tree/graph)
//          - Index usage recommendations
//
// @rfe 703 Export and reporting
//          - Export current view to CSV/PDF
//          - Scheduled email reports (daily/weekly summaries)
//          - Shareable URLs for specific views/filters
//
// @rfe 704 NOC/dashboard mode
//          - Kiosk mode for wall displays
//          - Iframe-friendly embedding
//          - Simplified view (just the essentials)
//          - Auto-rotate between host groups
//
// @rfe 705 Customizable section ordering
//          - Allow users to reorder page sections by DB type priority
//          - Example: Redis admin sees Redis tables first, MySQL second
//          - Drag-and-drop reordering or preference settings
//          - Persist order in localStorage or user profile (if auth enabled)
//          - Per-user preference: "My priority: Redis > MS-SQL > MySQL"
//          - Option to collapse/hide sections not relevant to user
//          - Responsive: remember different layouts for different screen sizes
//
// @rfe 706 Per-DBType debug mode with selective alerting
//          - Debug one DBType while getting real alerts for others
//          - Use case: developing Redis support while monitoring MySQL in production
//          - Options: ?debug=Redis&alerts=MySQL (debug Redis, alert on MySQL only)
//          - Or: ?debug=Redis&mute=Redis (debug Redis, silence Redis alerts)
//          - Allows focused development without missing production issues

// ----------------------------------------------------------------------------
// SECURITY & ACCESS CONTROL
// ----------------------------------------------------------------------------
// @rfe 800 Role-based access control (RBAC)
//          - Admin, operator, viewer roles
//          - Per-host/group permissions
//          - Kill query requires elevated role
//          - Silence/maintenance window management permissions
//
// @rfe 801 SSO integration beyond LDAP
//          - SAML 2.0 support
//          - OAuth 2.0 / OpenID Connect
//          - Integration with Okta, Azure AD, etc.
//
// @rfe 802 Audit logging
//          - Log who killed which query and when
//          - Log silence/maintenance window changes
//          - Log configuration changes
//          - Exportable audit trail
//
// @rfe 803 Secrets management
//          - HashiCorp Vault integration
//          - AWS Secrets Manager support
//          - Avoid plaintext passwords in config

// ----------------------------------------------------------------------------
// AUTOMATION & RULES
// ----------------------------------------------------------------------------
// @rfe 900 Query kill automation
//          - Auto-kill queries matching patterns (regex)
//          - Time-based rules (kill if > X seconds)
//          - User-based rules (kill queries from specific users)
//          - Dry-run mode for testing rules
//          - Audit trail of automated kills
//
// @rfe 901 Query whitelist/blacklist
//          - Mark known-safe long-running queries (skip alerting)
//          - Flag known-bad query patterns for immediate attention
//          - Per-host or global rules
//
// @rfe 902 Anomaly detection
//          - ML-based "this is unusual" detection
//          - Baseline learning per host/time-of-day
//          - Alert on statistical outliers

// ----------------------------------------------------------------------------
// ACCESSIBILITY & INTERNATIONALIZATION
// ----------------------------------------------------------------------------
// @rfe 950 Accessibility (a11y)
//          - WCAG 2.1 AA compliance
//          - Screen reader support (ARIA labels)
//          - Keyboard navigation
//          - High contrast mode
//          - Reduced motion option
//
// @rfe 951 Internationalization (i18n)
//          - Externalized strings
//          - Language selection
//          - RTL language support
//          - Locale-aware date/time formatting

// ----------------------------------------------------------------------------
// PROXY & MIDDLEWARE VISIBILITY
// ----------------------------------------------------------------------------
// @rfe 960 ProxySQL integration
//          - Monitor ProxySQL stats
//          - Connection pool utilization
//          - Query routing visibility
//
// @rfe 961 MySQL Router / MaxScale support
//          - Router-level metrics
//          - Backend health visibility
