# Beyond the Lights: Keeping Redis Running and Healthy

A DBA's guide to Redis monitoring - what to watch, what it means, and when to panic.

## Table of Contents

1. [The Redis Paradigm Shift](#the-redis-paradigm-shift)
2. [Essential Monitoring Commands](#essential-monitoring-commands)
3. [Critical Metrics Deep Dive](#critical-metrics-deep-dive)
4. [Alert Conditions](#alert-conditions)
5. [Command Reference](#command-reference)

---

## The Redis Paradigm Shift

If you're coming from MySQL/PostgreSQL, Redis monitoring requires a mental shift:

| MySQL Concept | Redis Equivalent |
|---------------|------------------|
| `SHOW PROCESSLIST` (concurrent queries) | `CLIENT LIST` (connected clients) |
| Slow query log (real-time) | `SLOWLOG` (historical, in-memory) |
| Multiple concurrent queries | Single-threaded command execution |
| Lock contention | Blocking commands (`BLPOP`, `BRPOP`) |
| Buffer pool hit ratio | Keyspace hit ratio |
| Replication lag (seconds) | Replication offset delta (bytes) |

**Key insight**: Redis is single-threaded for command execution. You won't see "23 queries running" - instead, you watch for *backpressure* (blocked/rejected clients) and *latency spikes*.

---

## Essential Monitoring Commands

### INFO - The Swiss Army Knife

The `INFO` command returns server statistics organized by section:

```
INFO [section]
```

Useful sections:
- `INFO clients` - Connection statistics
- `INFO memory` - Memory usage and fragmentation
- `INFO stats` - General statistics (hits, misses, expired keys)
- `INFO replication` - Master/slave status
- `INFO persistence` - RDB/AOF status
- `INFO commandstats` - Per-command statistics
- `INFO all` - Everything

### CLIENT LIST - Who's Connected

```
CLIENT LIST
```

Returns all connected clients with:
- `addr` - Client IP:port
- `age` - Connection age in seconds
- `idle` - Seconds since last command
- `cmd` - Last/current command
- `flags` - Client flags (N=normal, M=master, S=slave, b=blocked)

### SLOWLOG - Historical Slow Commands

```
SLOWLOG GET [count]
SLOWLOG LEN
SLOWLOG RESET
```

Shows commands that exceeded `slowlog-log-slower-than` (default: 10,000 microseconds = 10ms).

**Red flags in slowlog:**
- `KEYS *` - O(n) scan of entire keyspace
- `SMEMBERS` on large sets
- `HGETALL` on large hashes
- `LRANGE 0 -1` on large lists

### LATENCY Commands (Redis 2.8.13+)

```
LATENCY DOCTOR      -- Diagnostic advice
LATENCY LATEST      -- Most recent latency events
LATENCY HISTORY event-name
```

Enable with: `CONFIG SET latency-monitor-threshold 100` (milliseconds)

---

## Critical Metrics Deep Dive

### Memory Metrics

| Metric | Source | What It Means |
|--------|--------|---------------|
| `used_memory` | INFO memory | Total bytes allocated by Redis |
| `used_memory_rss` | INFO memory | Bytes allocated by OS (includes fragmentation) |
| `used_memory_peak` | INFO memory | Historical peak memory usage |
| `maxmemory` | CONFIG GET | Memory limit (0 = unlimited) |
| `mem_fragmentation_ratio` | INFO memory | RSS / used_memory |

**Fragmentation ratio interpretation:**
- `< 1.0` - Redis is swapping (very bad!)
- `1.0 - 1.5` - Healthy
- `> 1.5` - High fragmentation, wasting memory
- `> 2.0` - Restart may be needed to reclaim memory

### Connection Metrics

| Metric | Source | What It Means |
|--------|--------|---------------|
| `connected_clients` | INFO clients | Current client connections |
| `blocked_clients` | INFO clients | Clients waiting on BLPOP/BRPOP/etc |
| `rejected_connections` | INFO stats | Connections refused (maxclients hit) |
| `maxclients` | CONFIG GET | Connection limit |

### Cache Effectiveness

| Metric | Source | What It Means |
|--------|--------|---------------|
| `keyspace_hits` | INFO stats | Successful key lookups |
| `keyspace_misses` | INFO stats | Failed key lookups |

**Hit ratio calculation:**
```
hit_ratio = keyspace_hits / (keyspace_hits + keyspace_misses)
```

- `> 0.95` (95%) - Excellent
- `0.90 - 0.95` - Good
- `0.80 - 0.90` - Investigate miss patterns
- `< 0.80` - Cache may not be effective

### Eviction & Expiration

| Metric | Source | What It Means |
|--------|--------|---------------|
| `expired_keys` | INFO stats | Total keys expired (TTL reached) |
| `evicted_keys` | INFO stats | Keys forcibly removed (maxmemory reached) |

**Critical distinction:**
- `expired_keys` increasing = Normal, TTLs doing their job
- `evicted_keys` increasing = **Data loss!** You're at maxmemory and Redis is dropping keys

### Replication Metrics

| Metric | Source | What It Means |
|--------|--------|---------------|
| `role` | INFO replication | master or slave |
| `connected_slaves` | INFO replication | Number of replicas (on master) |
| `master_link_status` | INFO replication | up or down (on replica) |
| `master_repl_offset` | INFO replication | Master's replication position |
| `slave_repl_offset` | INFO replication | Replica's position |

**Replication lag** = `master_repl_offset - slave_repl_offset` (bytes behind)

### Persistence Metrics

| Metric | Source | What It Means |
|--------|--------|---------------|
| `rdb_last_save_time` | INFO persistence | Unix timestamp of last RDB save |
| `rdb_last_bgsave_status` | INFO persistence | ok or err |
| `aof_enabled` | INFO persistence | 0 or 1 |
| `aof_last_rewrite_status` | INFO persistence | ok or err |
| `aof_last_write_status` | INFO persistence | ok or err |

### Pub/Sub Metrics

| Metric | Source | What It Means |
|--------|--------|---------------|
| `pubsub_channels` | INFO clients | Active pub/sub channels |
| `pubsub_patterns` | INFO clients | Active pattern subscriptions |

**Detailed pub/sub info:**
```
PUBSUB CHANNELS [pattern]  -- List active channels
PUBSUB NUMSUB channel      -- Subscriber count per channel
PUBSUB NUMPAT              -- Total pattern subscriptions
```

### Streams Metrics (Redis 5.0+)

```
XINFO STREAM key           -- Stream length, radix tree info
XINFO GROUPS key           -- Consumer groups on a stream
XINFO CONSUMERS key group  -- Consumers in a group
XPENDING key group         -- Pending (unacknowledged) messages
```

**Watch for:** Growing `XPENDING` counts indicate consumers can't keep up.

### Command Statistics

```
INFO commandstats
```

Returns per-command:
- `calls` - Total invocations
- `usec` - Total microseconds spent
- `usec_per_call` - Average microseconds per call

**Find expensive operations:**
```
cmdstat_keys:calls=1523,usec=892341,usec_per_call=585.91  # KEYS is slow!
cmdstat_get:calls=9823412,usec=2341234,usec_per_call=0.24  # GET is fast
```

---

## Alert Conditions

### Immediate Action Required

| Condition | Threshold | Why It's Critical |
|-----------|-----------|-------------------|
| `evicted_keys` increasing | Any increase | Data loss occurring |
| `rejected_connections` | > 0 | Clients can't connect |
| `master_link_status` | down | Replication broken |
| `aof_last_write_status` | err | Persistence failing |
| `mem_fragmentation_ratio` | < 1.0 | Redis is swapping |

### Warning Level

| Condition | Threshold | Action |
|-----------|-----------|--------|
| `used_memory / maxmemory` | > 80% | Plan capacity increase |
| `mem_fragmentation_ratio` | > 1.5 | Consider restart during maintenance |
| `blocked_clients` | > 10 | Investigate blocking operations |
| `connected_clients / maxclients` | > 80% | Increase maxclients or add capacity |
| Hit ratio | < 90% | Review cache strategy |
| Replication lag | Growing trend | Network or replica performance issue |

### Slowlog Red Flags

Alert if these appear in `SLOWLOG GET`:

| Command | Why It's Bad |
|---------|--------------|
| `KEYS *` | O(n) full keyspace scan |
| `SMEMBERS` (large set) | Returns entire set into memory |
| `HGETALL` (large hash) | Returns entire hash |
| `LRANGE 0 -1` (large list) | Returns entire list |
| `SORT` (large dataset) | CPU-intensive |
| `FLUSHALL` / `FLUSHDB` | Deletes everything |

---

## Command Reference

### Quick Health Check Script

```bash
redis-cli INFO | grep -E "(connected_clients|blocked_clients|used_memory_human|maxmemory_human|mem_fragmentation_ratio|evicted_keys|rejected_connections|keyspace_hits|keyspace_misses|master_link_status)"
```

### Calculate Hit Ratio

```bash
redis-cli INFO stats | grep keyspace | awk -F: '
/hits/ {hits=$2}
/misses/ {misses=$2}
END {printf "Hit ratio: %.2f%%\n", hits/(hits+misses)*100}'
```

### Find Big Keys

```bash
redis-cli --bigkeys
```

**Note:** This uses `SCAN` internally and is safe for production, but may take time on large datasets.

### Memory Usage for Specific Key

```bash
redis-cli MEMORY USAGE mykey
```

### Monitor Commands in Real-Time

```bash
redis-cli MONITOR
```

**Warning:** High overhead on busy servers. Use briefly for debugging only.

### Check Slowlog

```bash
redis-cli SLOWLOG GET 10
```

---

## Monitoring Tools Integration

### Prometheus/Grafana
- [redis_exporter](https://github.com/oliver006/redis_exporter) - Exports INFO metrics to Prometheus

### Command-Line
- `redis-cli --stat` - Continuous stats output
- `redis-cli --latency` - Measure latency
- `redis-cli --bigkeys` - Find large keys

### Sentinel Monitoring (HA setups)
```
SENTINEL masters
SENTINEL master <name>
SENTINEL slaves <name>
SENTINEL sentinels <name>
```

---

## Further Reading

- [Redis Administration](https://redis.io/docs/management/)
- [Redis Latency Problems](https://redis.io/docs/management/optimization/latency/)
- [Redis Memory Optimization](https://redis.io/docs/management/optimization/memory-optimization/)

---

*Document created for AQL Redis monitoring implementation planning.*
