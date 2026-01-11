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

require_once 'vendor/autoload.php';

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\MaintenanceWindow ;
use com\kbcmdba\aql\Libs\Tools ;

header('Content-type: application/json') ;
header('Access-Control-Allow-Origin: *') ;
header('Expires: Thu, 01 Mar 2018 00:00:00 GMT') ;
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0') ;
header('Cache-Control: post-check=0, pre-check=0', false) ;
header('Pragma: no-cache') ;

$overviewData = [
    'aQPS'            => -1
  , 'blank'           => 0
  , 'blocked'         => 0
  , 'blocking'        => 0
  , 'duplicate'       => 0
  , 'level0'          => 0
  , 'level1'          => 0
  , 'level2'          => 0
  , 'level3'          => 0
  , 'level4'          => 0
  , 'level9'          => 0
  , 'longest_running' => -1
  , 'ro'              => 0
  , 'rw'              => 0
  , 'similar'         => 0
  , 'threads'         => 0
  , 'time'            => 0
  , 'unique'          => 0
  , 'uptime'          => 0
  , 'version'         => ''
];
$queries = [] ;
$safeQueries = [] ;
$slaveData = [] ;
$longestRunning = -1 ;

// Blocking cache - stores recent blocking relationships for 60 seconds
// Supports Redis (preferred) with file-based fallback
define( 'BLOCKING_CACHE_DIR', __DIR__ . '/cache' ) ;
define( 'BLOCKING_CACHE_TTL', 60 ) ; // seconds
define( 'BLOCKING_CACHE_REDIS_HOST', '127.0.0.1' ) ;
define( 'BLOCKING_CACHE_REDIS_PORT', 6379 ) ;
define( 'BLOCKING_CACHE_REDIS_PREFIX', 'aql:blocking:' ) ;

// Cache backend detection - try Redis first, fall back to file
$_blockingCacheRedis = null ;
$_blockingCacheType = 'file' ;

function getBlockingCacheRedis() {
    global $_blockingCacheRedis, $_blockingCacheType ;
    if ( $_blockingCacheRedis === null && class_exists( 'Redis' ) ) {
        try {
            $_blockingCacheRedis = new \Redis() ;
            if ( @$_blockingCacheRedis->connect( BLOCKING_CACHE_REDIS_HOST, BLOCKING_CACHE_REDIS_PORT, 0.5 ) ) {
                $_blockingCacheType = 'redis' ;
            } else {
                $_blockingCacheRedis = false ;
            }
        } catch ( \Exception $e ) {
            $_blockingCacheRedis = false ;
        }
    }
    return $_blockingCacheRedis ?: null ;
}

function getBlockingCacheKey( $hostname ) {
    $safeHost = preg_replace( '/[^a-zA-Z0-9._-]/', '_', $hostname ) ;
    return BLOCKING_CACHE_REDIS_PREFIX . $safeHost ;
}

function getBlockingCacheFile( $hostname ) {
    $safeHost = preg_replace( '/[^a-zA-Z0-9._-]/', '_', $hostname ) ;
    return BLOCKING_CACHE_DIR . '/blocking_' . $safeHost . '.json' ;
}

function readBlockingCache( $hostname ) {
    global $_blockingCacheType ;
    $redis = getBlockingCacheRedis() ;

    if ( $redis ) {
        // Redis: get all entries, already filtered by TTL
        $key = getBlockingCacheKey( $hostname ) ;
        $data = $redis->get( $key ) ;
        if ( $data === false ) {
            return [] ;
        }
        $entries = @json_decode( $data, true ) ;
        return is_array( $entries ) ? $entries : [] ;
    }

    // File fallback
    $file = getBlockingCacheFile( $hostname ) ;
    if ( !file_exists( $file ) ) {
        return [] ;
    }
    $data = @json_decode( file_get_contents( $file ), true ) ;
    if ( !is_array( $data ) ) {
        return [] ;
    }
    // Filter out expired entries (file cache only - Redis handles TTL)
    $now = time() ;
    $valid = [] ;
    foreach ( $data as $entry ) {
        if ( isset( $entry['timestamp'] ) && ( $now - $entry['timestamp'] ) < BLOCKING_CACHE_TTL ) {
            $valid[] = $entry ;
        }
    }
    return $valid ;
}

function writeBlockingCache( $hostname, $entries ) {
    $redis = getBlockingCacheRedis() ;

    // Read existing, merge new
    $existing = readBlockingCache( $hostname ) ;
    $now = time() ;

    // Add timestamp to new entries
    foreach ( $entries as &$entry ) {
        if ( !isset( $entry['timestamp'] ) ) {
            $entry['timestamp'] = $now ;
        }
    }

    // Merge: keep existing entries that aren't duplicates of new ones
    $merged = $entries ;
    foreach ( $existing as $old ) {
        $isDupe = false ;
        foreach ( $entries as $new ) {
            if ( $old['blockerThreadId'] == $new['blockerThreadId']
                 && $old['waitingThreadId'] == $new['waitingThreadId'] ) {
                $isDupe = true ;
                break ;
            }
        }
        if ( !$isDupe ) {
            $merged[] = $old ;
        }
    }

    if ( $redis ) {
        // Redis: set with TTL
        $key = getBlockingCacheKey( $hostname ) ;
        $redis->setex( $key, BLOCKING_CACHE_TTL, json_encode( $merged ) ) ;
    } else {
        // File fallback
        @file_put_contents( getBlockingCacheFile( $hostname ), json_encode( $merged ), LOCK_EX ) ;
    }
}

function getCachedBlockersForThread( $hostname, $waitingThreadId ) {
    $cache = readBlockingCache( $hostname ) ;
    $blockers = [] ;
    foreach ( $cache as $entry ) {
        if ( $entry['waitingThreadId'] == $waitingThreadId ) {
            $blockers[] = $entry ;
        }
    }
    return $blockers ;
}

function getBlockingCacheType() {
    global $_blockingCacheType ;
    getBlockingCacheRedis() ; // Initialize
    return $_blockingCacheType ;
}

/**
 * Normalize query for hashing - replace literals to create fingerprint
 * Matches JavaScript normalizeQueryForHash() in common.js
 * @param string $query SQL query text
 * @return string Normalized query
 */
function normalizeQueryForHash( $query ) {
    $normalized = $query ;
    // Remove SQL comments first (may contain sensitive data)
    // Multi-line comments: /* ... */
    $normalized = preg_replace( '/\/\*.*?\*\//s', '/**/deleted', $normalized ) ;
    // Single-line comments: -- ... (MySQL requires space after --)
    $normalized = preg_replace( '/-- .*$/m', '-- deleted', $normalized ) ;
    // Single-line comments: # ... (MySQL style)
    $normalized = preg_replace( '/#.*$/m', '# deleted', $normalized ) ;
    // Hex/binary literals BEFORE string literals (they use quotes too)
    // Hex: 0xDEADBEEF, x'1A2B', X'1A2B'
    $normalized = preg_replace( '/0x[0-9a-fA-F]+/', 'N', $normalized ) ;
    $normalized = preg_replace( "/[xX]'[0-9a-fA-F]*'/", 'N', $normalized ) ;
    // Binary: 0b1010, b'1010', B'1010'
    $normalized = preg_replace( '/0b[01]+/', 'N', $normalized ) ;
    $normalized = preg_replace( "/[bB]'[01]*'/", 'N', $normalized ) ;
    // Single-quoted strings with escaped quotes -> 'S'
    // Handles: 'simple', 'it\'s escaped', 'has ''doubled'' quotes'
    $normalized = preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/", "'S'", $normalized ) ;
    $normalized = preg_replace( "/'(?:[^']|'')*'/", "'S'", $normalized ) ; // MySQL doubled quotes
    // Double-quoted strings with escaped quotes -> "S"
    $normalized = preg_replace( '/"(?:[^"\\\\]|\\\\.)*"/', '"S"', $normalized ) ;
    // Numbers -> N (integers, decimals, scientific notation)
    $normalized = preg_replace( '/\b\d+\.?\d*(?:[eE][+-]?\d+)?\b/', 'N', $normalized ) ;
    return $normalized ;
}

/**
 * Hash string using djb2 algorithm - matches JavaScript hashString() in common.js
 * @param string $str String to hash
 * @return string Hex hash (16 chars)
 */
function hashQueryString( $str ) {
    $hash = 5381 ;
    $len = strlen( $str ) ;
    for ( $i = 0 ; $i < $len ; $i++ ) {
        $hash = ( ( $hash << 5 ) + $hash ) + ord( $str[$i] ) ;
        $hash = $hash & 0xFFFFFFFF ; // Keep as 32-bit
    }
    return str_pad( dechex( abs( $hash ) ), 16, '0', STR_PAD_LEFT ) ;
}

/**
 * Log a blocking query to the blocking_history table
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for deduplication by query_hash
 * @param int $hostId Host ID from aql_db.host table
 * @param string $user MySQL user executing the blocking query
 * @param string $sourceHost Source host/IP of the connection
 * @param string $dbName Database name (may be null)
 * @param string $queryText Original query text (will be normalized)
 * @param int $blockedCount Number of queries being blocked
 * @param int $blockTimeSecs Duration of the blocking in seconds
 */
function logBlockingQuery( $hostId, $user, $sourceHost, $dbName, $queryText, $blockedCount, $blockTimeSecs ) {
    try {
        $dbc = new DBConnection() ;
        $dbh = $dbc->getConnection() ;

        // Handle negative time values (possible due to network time sync issues)
        $blockTimeSecs = max( 0, (int) $blockTimeSecs ) ;

        $normalizedQuery = normalizeQueryForHash( $queryText ) ;
        $queryHash = hashQueryString( $normalizedQuery ) ;

        // INSERT or UPDATE - increment counts if exists, track max block time
        $sql = "INSERT INTO aql_db.blocking_history
                (host_id, query_hash, user, source_host, db_name, query_text, blocked_count, total_blocked, max_block_secs)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE
                    blocked_count = blocked_count + 1,
                    total_blocked = total_blocked + VALUES(total_blocked),
                    max_block_secs = GREATEST(max_block_secs, VALUES(max_block_secs)),
                    last_seen = CURRENT_TIMESTAMP" ;
        $stmt = $dbh->prepare( $sql ) ;
        $stmt->bind_param( 'isssssii', $hostId, $queryHash, $user, $sourceHost, $dbName, $normalizedQuery, $blockedCount, $blockTimeSecs ) ;
        $stmt->execute() ;
        $stmt->close() ;
    } catch ( \Exception $e ) {
        // Silently fail - don't break AQL if logging fails
        error_log( "AQL blocking_history logging failed: " . $e->getMessage() ) ;
    }
}

/**
 * Purge old entries from blocking_history (older than 90 days)
 * Called occasionally to prevent unbounded table growth
 */
function purgeOldBlockingHistory() {
    try {
        $dbc = new DBConnection() ;
        $dbh = $dbc->getConnection() ;
        $dbh->query( "DELETE FROM aql_db.blocking_history WHERE last_seen < DATE_SUB(NOW(), INTERVAL 90 DAY)" ) ;
    } catch ( \Exception $e ) {
        error_log( "AQL blocking_history purge failed: " . $e->getMessage() ) ;
    }
}

/**
 * Look up host_id from hostname:port string
 * @param string $hostnamePort Format "hostname:port"
 * @return int|null Host ID or null if not found
 */
function getHostIdFromHostname( $hostnamePort ) {
    static $cache = [] ;
    if ( isset( $cache[$hostnamePort] ) ) {
        return $cache[$hostnamePort] ;
    }
    try {
        $parts = explode( ':', $hostnamePort ) ;
        $hostname = $parts[0] ;
        $port = isset( $parts[1] ) ? (int) $parts[1] : 3306 ;
        $dbc = new DBConnection() ;
        $dbh = $dbc->getConnection() ;
        $stmt = $dbh->prepare( "SELECT host_id FROM aql_db.host WHERE hostname = ? AND port_number = ?" ) ;
        $stmt->bind_param( 'si', $hostname, $port ) ;
        $stmt->execute() ;
        $result = $stmt->get_result() ;
        $row = $result->fetch_assoc() ;
        $stmt->close() ;
        $hostId = $row ? (int) $row['host_id'] : null ;
        $cache[$hostnamePort] = $hostId ;
        return $hostId ;
    } catch ( \Exception $e ) {
        error_log( "AQL getHostIdFromHostname failed: " . $e->getMessage() ) ;
        return null ;
    }
}

// Get hostname early so we can look up hostId before connection attempt
$hostname = Tools::param('hostname') ;

// Look up host ID early (needed for error responses with silence capability)
$hostId = getHostIdFromHostname( $hostname ) ;

// Get the groups this host belongs to (for browser-local group silencing)
$hostGroups = [] ;
if ( $hostId !== null ) {
    try {
        $grpDbc = new DBConnection() ;
        $grpDbh = $grpDbc->getConnection() ;
        $grpSql = "SELECT hgm.host_group_id, hg.tag
                   FROM aql_db.host_group_map hgm
                   JOIN aql_db.host_group hg ON hg.host_group_id = hgm.host_group_id
                   WHERE hgm.host_id = ?" ;
        $grpStmt = $grpDbh->prepare( $grpSql ) ;
        if ( $grpStmt ) {
            $grpStmt->bind_param( 'i', $hostId ) ;
            $grpStmt->execute() ;
            $grpResult = $grpStmt->get_result() ;
            while ( $row = $grpResult->fetch_assoc() ) {
                $hostGroups[] = [ 'id' => (int) $row['host_group_id'], 'tag' => $row['tag'] ] ;
            }
            $grpStmt->close() ;
        }
    } catch ( \Exception $e ) {
        // Silently ignore - don't break AQL if group lookup fails
    }
}

// Check maintenance window status early (needed for both success and error responses)
$maintenanceInfo = null ;
$config = new Config() ;
if ( $config->getEnableMaintenanceWindows() && $hostId !== null ) {
    try {
        $aqlDbc = new DBConnection() ;
        $aqlDbh = $aqlDbc->getConnection() ;
        $maintenanceInfo = MaintenanceWindow::getActiveWindowForHost( $hostId, $aqlDbh ) ;
        if ( $maintenanceInfo === null ) {
            $maintenanceInfo = MaintenanceWindow::getActiveWindowForHostViaGroup( $hostId, $aqlDbh ) ;
        }
    } catch ( \Exception $e ) {
        // Silently ignore - don't break AQL if maintenance check fails
    }
}

try {
    $roQueryPart   = $config->getRoQueryPart() ;
    $debug         = Tools::param('debug') === "1" ;
    $debugLocks    = Tools::param('debugLocks') === "1" ;
    $alertCritSecs = Tools::param('alertCritSecs') ;
    $alertWarnSecs = Tools::param('alertWarnSecs') ;
    $alertInfoSecs = Tools::param('alertInfoSecs') ;
    $alertLowSecs  = Tools::param('alertLowSecs') ;
    $dbc           = new DBConnection('process', $hostname) ;
    $dbh           = $dbc->getConnection() ;
    $outputList    = [] ;
    $notInCommand  = "( 'Binlog Dump'"
                   . ", 'Binlog Dump GTID'"
                   . ", 'Daemon'"
                   . ", 'Group Replicatio'"
                   . ", 'Slave_IO'"
                   . ", 'Slave_SQL'"
                   . ", 'Slave_worker'"
                   . ", 'Sleep'"
                   . ")" ;
    $notInState    = "( 'Applying batch of row changes (update)'"
                   . ", 'Connection delegated to Group Replication'"
                   . ", 'Queueing master event to the relay log'"
                   . ", 'Queueing source event to the relay log'"
                   . ", 'Replica has read all relay log; waiting for more updates'"
                   . ", 'Slave has read all relay log; waiting for more updates'"
                   . ", 'Waiting for an event from Coordinator'"
                   . ", 'Waiting for master to send event'"
                   . ", 'Waiting for source to send event'"
                   . ", 'reading event from relay log'"
                   . ", 'waiting for handler commit'"
                   . ")" ;
    $debugComment  = ( $debug ) ? '-- ' : '' ;
    $globalStatusDb = $config->getGlobalStatusDb() ;
    $aQuery        = <<<SQL
SELECT Q / U AS aQPS, VERSION(), U
  FROM ( SELECT variable_value AS Q FROM $globalStatusDb.global_status WHERE variable_name = 'Questions' ) AS A,
       ( SELECT variable_value AS U FROM $globalStatusDb.global_status WHERE variable_name = 'Uptime' ) AS B
SQL;
    $aResult    = $dbh->query( $aQuery ) ;
    if ( $aResult === false ) {
        throw new \ErrorException( "Error running query: $aQuery (" . $dbh->error . ")\n" ) ;
    }
    $row = $aResult->fetch_row() ;
    $overviewData[ 'aQPS' ] = $row[ 0 ] ;
    $overviewData[ 'version' ] = $row[ 1 ] ;
    $overviewData[ 'uptime' ] = $row[ 2 ] ;
    $aResult->close() ;
    $showSlaveStatement = $config->getShowSlaveStatement() ;
    $version = $overviewData[ 'version' ] ;
    $replica_labels = [ 'Connection_name' => 'Connection_name'
                      , 'Master_Host' => 'Master_Host'
                      , 'Master_Port' => 'Master_Port'
                      , 'Slave_IO_Running' => 'Slave_IO_Running'
                      , 'Slave_SQL_Running' => 'Slave_SQL_Running'
                      , 'Seconds_Behind_Master' => 'Seconds_Behind_Master'
                      , 'Last_IO_Error' => 'Last_IO_Error'
                      , 'Last_SQL_Error' => 'Last_SQL_Error'
                      ] ;
    switch ( true ) {
        case preg_match( '/^10\.[2-9]\..*-MariaDB.*/', $version ) === 1:
            $showSlaveStatement = 'SHOW ALL SLAVES STATUS' ;
            $roQueryPart = '@@global.read_only OR @@global.innodb_read_only' ;
            break ;
        case preg_match( '/^[345]\..*$/', $version ) === 1:
            $showSlaveStatement = 'SHOW SLAVE STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
        case preg_match( '/^[8]\.0\.21.*$/', $version ) === 1:
            $showSlaveStatement = 'SHOW SLAVE STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
        case preg_match( '/^[8]\..*$/', $version ) === 1:
            $replica_labels = [ 'Connection_name' => 'Channel_Name'
                              , 'Master_Host' => 'Source_Host'
                              , 'Master_Port' => 'Source_Port'
                              , 'Slave_IO_Running' => 'Replica_IO_Running'
                              , 'Slave_SQL_Running' => 'Replica_SQL_Running'
                              , 'Seconds_Behind_Master' => 'Seconds_Behind_Source'
                              , 'Last_IO_Error' => 'Last_IO_Error'
                              , 'Last_SQL_Error' => 'Last_SQL_Error'
                              ] ;
            $showSlaveStatement = 'SHOW REPLICA STATUS' ;
            $roQueryPart = '@@global.read_only' ;
            break ;
    } ;

    // Lock detection - build map of blocked/blocking threads
    // Each query is wrapped in its own try/catch so one failure doesn't block others
    $lockWaitData = [] ;
    $tableLockDebug = null ;

    // 1. Try InnoDB lock detection (row-level locks)
    try {
        // Determine which lock query to use based on MySQL version
        $useMysql8LockQuery = preg_match( '/^[8]\./', $version ) === 1
                           && preg_match( '/^[8]\.0\.[01][^0-9]/', $version ) !== 1 ;

        if ( $useMysql8LockQuery ) {
            // MySQL 8.0.2+ uses performance_schema
            $lockQuery = <<<SQL
SELECT
    r.trx_mysql_thread_id AS waiting_thread_id,
    COALESCE(TIMESTAMPDIFF(SECOND, r.trx_wait_started, NOW()), 0) AS wait_seconds,
    b.trx_mysql_thread_id AS blocking_thread_id,
    CONCAT(dl.OBJECT_SCHEMA, '.', dl.OBJECT_NAME) AS locked_table,
    dl.LOCK_MODE AS lock_mode,
    p.info AS blocker_query
FROM performance_schema.data_lock_waits dlw
JOIN performance_schema.data_locks dl ON dlw.REQUESTING_ENGINE_LOCK_ID = dl.ENGINE_LOCK_ID
JOIN information_schema.INNODB_TRX b ON b.trx_id = dlw.BLOCKING_ENGINE_TRANSACTION_ID
JOIN information_schema.INNODB_TRX r ON r.trx_id = dlw.REQUESTING_ENGINE_TRANSACTION_ID
LEFT JOIN information_schema.PROCESSLIST p ON p.id = b.trx_mysql_thread_id
SQL;
        } else {
            // MySQL 5.x, MariaDB, MySQL 8.0.0-8.0.1 use information_schema
            $lockQuery = <<<SQL
SELECT
    r.trx_mysql_thread_id AS waiting_thread_id,
    COALESCE(TIMESTAMPDIFF(SECOND, r.trx_wait_started, NOW()), 0) AS wait_seconds,
    b.trx_mysql_thread_id AS blocking_thread_id,
    l.lock_table AS locked_table,
    l.lock_mode AS lock_mode,
    p.info AS blocker_query
FROM information_schema.INNODB_LOCK_WAITS w
JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id
JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id
JOIN information_schema.INNODB_LOCKS l ON l.lock_id = w.requested_lock_id
LEFT JOIN information_schema.PROCESSLIST p ON p.id = b.trx_mysql_thread_id
SQL;
        }

        $lockResult = $dbh->query( $lockQuery ) ;
        if ( $lockResult !== false ) {
            while ( $row = $lockResult->fetch_assoc() ) {
                $waitingThreadId = (int) $row['waiting_thread_id'] ;
                $blockingThreadId = (int) $row['blocking_thread_id'] ;
                $blockerQuery = isset( $row['blocker_query'] ) ? $row['blocker_query'] : null ;
                $blockerQuerySafe = $blockerQuery ? Tools::makeQuotedStringPIISafe( $blockerQuery ) : null ;

                // Initialize or update waiting thread entry
                if ( !isset( $lockWaitData[$waitingThreadId] ) ) {
                    $lockWaitData[$waitingThreadId] = [
                        'isBlocked'   => true,
                        'isBlocking'  => false,
                        'waitSeconds' => (int) $row['wait_seconds'],
                        'lockMode'    => $row['lock_mode'],
                        'lockedTable' => $row['locked_table'],
                        'blockedBy'   => [],
                        'blockerQueries' => [],
                        'blocking'    => []
                    ] ;
                }
                $lockWaitData[$waitingThreadId]['blockedBy'][] = $blockingThreadId ;
                $lockWaitData[$waitingThreadId]['blockerQueries'][$blockingThreadId] = $blockerQuerySafe ;

                // Initialize or update blocking thread entry
                if ( !isset( $lockWaitData[$blockingThreadId] ) ) {
                    $lockWaitData[$blockingThreadId] = [
                        'isBlocked'   => false,
                        'isBlocking'  => true,
                        'waitSeconds' => 0,
                        'lockMode'    => null,
                        'lockedTable' => null,
                        'blockedBy'   => [],
                        'blockerQueries' => [],
                        'blocking'    => []
                    ] ;
                }
                $lockWaitData[$blockingThreadId]['isBlocking'] = true ;
                $lockWaitData[$blockingThreadId]['blocking'][] = $waitingThreadId ;
            }
            $lockResult->close() ;
        }
    } catch ( \Exception $e ) {
        // InnoDB lock detection failed - continue with other methods
    }

    // 2. Try metadata lock detection (table-level locks, DDL locks)
    try {
        $metadataLockQuery = <<<SQL
SELECT
    waiting.PROCESSLIST_ID AS waiting_thread_id,
    blocking.PROCESSLIST_ID AS blocking_thread_id,
    granted.OBJECT_SCHEMA,
    granted.OBJECT_NAME,
    granted.LOCK_TYPE AS lock_mode,
    0 AS wait_seconds,
    p.info AS blocker_query
FROM performance_schema.metadata_locks pending
JOIN performance_schema.metadata_locks granted
    ON pending.OBJECT_TYPE = granted.OBJECT_TYPE
    AND pending.OBJECT_SCHEMA = granted.OBJECT_SCHEMA
    AND pending.OBJECT_NAME = granted.OBJECT_NAME
    AND pending.LOCK_STATUS = 'PENDING'
    AND granted.LOCK_STATUS = 'GRANTED'
JOIN performance_schema.threads waiting ON pending.OWNER_THREAD_ID = waiting.THREAD_ID
JOIN performance_schema.threads blocking ON granted.OWNER_THREAD_ID = blocking.THREAD_ID
LEFT JOIN information_schema.PROCESSLIST p ON p.id = blocking.PROCESSLIST_ID
WHERE waiting.PROCESSLIST_ID IS NOT NULL
  AND blocking.PROCESSLIST_ID IS NOT NULL
  AND waiting.PROCESSLIST_ID <> blocking.PROCESSLIST_ID
SQL;
        $metadataLockResult = $dbh->query( $metadataLockQuery ) ;
        if ( $metadataLockResult !== false ) {
            while ( $row = $metadataLockResult->fetch_assoc() ) {
                $waitingThreadId = (int) $row['waiting_thread_id'] ;
                $blockingThreadId = (int) $row['blocking_thread_id'] ;
                $lockedTable = $row['OBJECT_SCHEMA'] . '.' . $row['OBJECT_NAME'] ;
                $blockerQuery = isset( $row['blocker_query'] ) ? $row['blocker_query'] : null ;
                $blockerQuerySafe = $blockerQuery ? Tools::makeQuotedStringPIISafe( $blockerQuery ) : null ;

                // Initialize or update waiting thread entry
                if ( !isset( $lockWaitData[$waitingThreadId] ) ) {
                    $lockWaitData[$waitingThreadId] = [
                        'isBlocked'   => true,
                        'isBlocking'  => false,
                        'waitSeconds' => 0,
                        'lockMode'    => $row['lock_mode'],
                        'lockedTable' => $lockedTable,
                        'blockedBy'   => [],
                        'blockerQueries' => [],
                        'blocking'    => []
                    ] ;
                }
                if ( !in_array( $blockingThreadId, $lockWaitData[$waitingThreadId]['blockedBy'] ) ) {
                    $lockWaitData[$waitingThreadId]['blockedBy'][] = $blockingThreadId ;
                }
                if ( !isset( $lockWaitData[$waitingThreadId]['blockerQueries'] ) ) {
                    $lockWaitData[$waitingThreadId]['blockerQueries'] = [] ;
                }
                $lockWaitData[$waitingThreadId]['blockerQueries'][$blockingThreadId] = $blockerQuerySafe ;

                // Initialize or update blocking thread entry
                if ( !isset( $lockWaitData[$blockingThreadId] ) ) {
                    $lockWaitData[$blockingThreadId] = [
                        'isBlocked'   => false,
                        'isBlocking'  => true,
                        'waitSeconds' => 0,
                        'lockMode'    => $row['lock_mode'],
                        'lockedTable' => $lockedTable,
                        'blockedBy'   => [],
                        'blockerQueries' => [],
                        'blocking'    => []
                    ] ;
                }
                $lockWaitData[$blockingThreadId]['isBlocking'] = true ;
                if ( !in_array( $waitingThreadId, $lockWaitData[$blockingThreadId]['blocking'] ) ) {
                    $lockWaitData[$blockingThreadId]['blocking'][] = $waitingThreadId ;
                }
            }
            $metadataLockResult->close() ;
        }
    } catch ( \Exception $e ) {
        // Metadata lock detection failed - continue with fallback
    }

    // 3. Fallback: check for table-level lock waits via PROCESSLIST state (always works)
    // Also capture the query text so we can try to identify blockers by table name
    $tableLockWaiters = [] ; // Store for later blocker identification
    try {
        $tableLockQuery = <<<SQL
SELECT id AS waiting_thread_id, time AS wait_seconds, state, info, db
FROM INFORMATION_SCHEMA.PROCESSLIST
WHERE LOWER(state) IN (
    'waiting for table level lock',
    'waiting for table metadata lock',
    'locked',
    'waiting for table flush',
    'system lock'
)
SQL;
        $tableLockResult = $dbh->query( $tableLockQuery ) ;
        $tableLockDebug = [ 'queryRan' => ($tableLockResult !== false), 'error' => $dbh->error, 'count' => 0 ] ;
        if ( $tableLockResult !== false ) {
            while ( $row = $tableLockResult->fetch_assoc() ) {
                $waitingThreadId = (int) $row['waiting_thread_id'] ;
                $tableLockDebug['count']++ ;
                // Store for later blocker identification
                $tableLockWaiters[$waitingThreadId] = [
                    'query' => $row['info'],
                    'db'    => $row['db']
                ] ;
                if ( !isset( $lockWaitData[$waitingThreadId] ) ) {
                    $lockWaitData[$waitingThreadId] = [
                        'isBlocked'   => true,
                        'isBlocking'  => false,
                        'waitSeconds' => (int) $row['wait_seconds'],
                        'lockMode'    => 'TABLE',
                        'lockedTable' => $row['state'],
                        'blockedBy'   => [],
                        'blockerQueries' => [],
                        'blocking'    => []
                    ] ;
                }
            }
            $tableLockResult->close() ;
        }
    } catch ( \Exception $e ) {
        $tableLockDebug = [ 'exception' => $e->getMessage() ] ;
    }

    // Build list of blocking thread IDs to include even if they'd normally be filtered out
    $blockingThreadIds = [] ;
    foreach ( $lockWaitData as $threadId => $info ) {
        if ( !empty( $info['isBlocking'] ) ) {
            $blockingThreadIds[] = (int) $threadId ;
        }
    }
    $blockingClause = '' ;
    if ( !empty( $blockingThreadIds ) ) {
        $blockingClause = ' OR id IN (' . implode( ',', $blockingThreadIds ) . ')' ;
    }

    $processQuery  = <<<SQL
SELECT id
     , user
     , host
     , db
     , command
     , time
     , state
     , info
     , $roQueryPart as read_only
  FROM INFORMATION_SCHEMA.PROCESSLIST
 WHERE ( 1 = 1
 $debugComment  AND COMMAND NOT IN $notInCommand
 $debugComment  AND STATE NOT IN $notInState
 $debugComment  AND id <> CONNECTION_ID()
       $blockingClause )
 ORDER BY time DESC

SQL;
    $processResult = $dbh->query($processQuery) ;
    if ( $processResult === false ) {
        throw new \ErrorException( "Error running query: $processQuery (" . $dbh->error . ")\n" ) ;
    }
    while ($row = $processResult->fetch_row()) {
        $overviewData[ 'threads' ] ++ ;
        $dupeState    = '' ;
        $pid          = (int) $row[ 0 ] ;
        $uid          = $row[ 1 ] ;
        $host         = $row[ 2 ] ;
        $db           = $row[ 3 ] ;
        $command      = $row[ 4 ] ;
        $time         = $row[ 5 ] ;
        $friendlyTime = Tools::friendlyTime( $time ) ;
        $state        = $row[ 6 ] ;
        $info         = $row[ 7 ] ;
        $safeInfo     = Tools::makeQuotedStringPIISafe( $info ) ;
        if ( isset($info) && ($info !== '') ) {
            if ( ( $command === 'Query' ) && ( $longestRunning < $time ) ) {
                $longestRunning = $time ;
            }
            if ( isset( $queries[ $info ] ) ) {
                $dupeState = 'Duplicate' ;
                $overviewData[ 'duplicate' ] ++ ;
            }
            elseif ( isset( $safeQueries[ $safeInfo ] ) ) {
                $dupeState = 'Similar' ;
                $overviewData[ 'similar' ] ++ ;
            }
            else {
                $dupeState = 'Unique' ;
                $overviewData[ 'unique' ] ++ ;
            }
        }
        else {
            $dupeState = 'Blank' ;
            $overviewData[ 'blank' ] ++ ;
        }
        $queries[ $info ] = 1 ;
        $safeQueries[ $safeInfo ] = 1 ;
        $safeUrl = urlencode( $safeInfo ) ;
        $readOnly = $row[ 8 ] ;
        $overviewData[ 'time' ] += $time ;
        $overviewData[ ( $readOnly ) ? 'ro' : 'rw' ] ++ ;
        switch (true) {
            case $time >= $alertCritSecs:
                $level = 4 ;
                break ;
            case $time >= $alertWarnSecs:
                $level = 3 ;
                break ;
            case $time >= $alertInfoSecs:
                $level = 2 ;
                break ;
            case $time <= $alertLowSecs:
                $level = 0 ;
                break ;
            default:
                $level = 1 ;
        }

        // Check for lock wait status and elevate level if blocked
        $blockInfo = isset( $lockWaitData[$pid] ) ? $lockWaitData[$pid] : null ;
        if ( $blockInfo !== null ) {
            if ( !empty( $blockInfo['isBlocked'] ) ) {
                $overviewData['blocked'] ++ ;
                // Elevate level based on wait time
                if ( $blockInfo['waitSeconds'] > 60 ) {
                    $level = 4 ;  // Critical if blocked > 60s
                } elseif ( $blockInfo['waitSeconds'] > 30 ) {
                    $level = max( $level, 3 ) ;  // Warning if blocked > 30s
                } else {
                    $level = max( $level, 2 ) ;  // Info if blocked
                }
            }
            if ( !empty( $blockInfo['isBlocking'] ) ) {
                $overviewData['blocking'] ++ ;
            }
        }

        $overviewData[ "level$level" ] ++ ;
        $safeInfoJS   = urlencode( $safeInfo ) ;
        $outputList[] = [
            'level'        => $level
          , 'time'         => $time
          , 'friendlyTime' => $friendlyTime
          , 'server'       => $hostname
          , 'id'           => $pid
          , 'user'         => $uid
          , 'host'         => $host
          , 'db'           => $db
          , 'command'      => $command
          , 'state'        => $state
          , 'dupeState'    => $dupeState
          , 'info'         => htmlspecialchars( $safeInfo )
          , 'actions'      => "<button type=\"button\" onclick=\"killProcOnHost( '$hostname', $pid, '$uid', '$host', '$db', '$command', $time, '$state', '$safeInfoJS' ) ; return false ;\">Kill Thread</button>"
                            . "<button type=\"button\" onclick=\"fileIssue( '$hostname', '$readOnly', '$host', '$uid', '$db', $time, '$safeUrl' ) ; return false ;\">File Issue</button>"
          , 'readOnly'     => $readOnly
          , 'blockInfo'    => $blockInfo
        ] ;
    }
    $processResult->close() ;

    // 4. For table-level lock waiters without identified blockers, try to find potential blockers
    // Collect all tables being waited on for a secondary query
    $waitingTables = [] ;
    $waitingDatabases = [] ;
    foreach ( $tableLockWaiters as $waitingThreadId => $waiterInfo ) {
        // Skip if blocker already identified (e.g., via InnoDB/metadata lock detection)
        if ( !empty( $lockWaitData[$waitingThreadId]['blockedBy'] ) ) {
            continue ;
        }
        $waiterQuery = $waiterInfo['query'] ;
        $waiterDb = $waiterInfo['db'] ;
        if ( !empty( $waiterDb ) ) {
            $waitingDatabases[$waiterDb] = true ;
        }
        if ( empty( $waiterQuery ) ) {
            continue ;
        }
        // Extract table names from the waiting query (simple regex approach)
        $tables = [] ;
        // Match FROM table, INTO table, UPDATE table, JOIN table patterns
        if ( preg_match_all( '/(?:FROM|INTO|UPDATE|JOIN)\s+`?(\w+)`?/i', $waiterQuery, $matches ) ) {
            $tables = array_unique( $matches[1] ) ;
        }
        if ( empty( $tables ) ) {
            continue ;
        }
        foreach ( $tables as $table ) {
            $waitingTables[$table] = true ;
        }
        // Find potential blockers in existing outputList
        foreach ( $outputList as $idx => $thread ) {
            $threadId = (int) $thread['id'] ;
            // Don't match against self or other waiters
            if ( $threadId === $waitingThreadId || isset( $tableLockWaiters[$threadId] ) ) {
                continue ;
            }
            // Skip threads with no query
            $threadQuery = html_entity_decode( $thread['info'] ?? '' ) ;
            if ( empty( $threadQuery ) ) {
                continue ;
            }
            // Check if this thread is accessing any of the same tables
            $isBlocker = false ;
            foreach ( $tables as $table ) {
                if ( preg_match( '/\b' . preg_quote( $table, '/' ) . '\b/i', $threadQuery ) ) {
                    $isBlocker = true ;
                    break ;
                }
            }
            if ( $isBlocker ) {
                // Mark as potential blocker
                $lockWaitData[$waitingThreadId]['blockedBy'][] = $threadId ;
                $safeBlockerQuery = Tools::makeQuotedStringPIISafe( $threadQuery ) ;
                $lockWaitData[$waitingThreadId]['blockerQueries'][$threadId] = $safeBlockerQuery ;
                // Also mark the blocker
                if ( !isset( $lockWaitData[$threadId] ) ) {
                    $lockWaitData[$threadId] = [
                        'isBlocked'   => false,
                        'isBlocking'  => true,
                        'waitSeconds' => 0,
                        'lockMode'    => 'TABLE',
                        'lockedTable' => implode( ', ', $tables ),
                        'blockedBy'   => [],
                        'blockerQueries' => [],
                        'blocking'    => []
                    ] ;
                    $overviewData['blocking'] ++ ;
                }
                $lockWaitData[$threadId]['isBlocking'] = true ;
                $lockWaitData[$threadId]['blocking'][] = $waitingThreadId ;
                // Update the thread's blockInfo in outputList
                $outputList[$idx]['blockInfo'] = $lockWaitData[$threadId] ;
            }
        }
        // Update waiter's blockInfo in outputList
        foreach ( $outputList as $idx => $thread ) {
            if ( (int) $thread['id'] === $waitingThreadId ) {
                $outputList[$idx]['blockInfo'] = $lockWaitData[$waitingThreadId] ;
                break ;
            }
        }
    }

    // 5. If we still have unidentified blockers, search ALL threads (including Sleep) for write operations
    // on the tables that are being waited on
    $unblockedWaiters = [] ;
    foreach ( $tableLockWaiters as $waitingThreadId => $waiterInfo ) {
        if ( empty( $lockWaitData[$waitingThreadId]['blockedBy'] ) ) {
            $unblockedWaiters[] = $waitingThreadId ;
        }
    }
    if ( !empty( $unblockedWaiters ) && !empty( $waitingTables ) ) {
        // Query ALL threads to find potential blockers (write operations on waited tables)
        $potentialBlockerQuery = "SELECT id, user, host, db, command, time, state, info
                                  FROM INFORMATION_SCHEMA.PROCESSLIST
                                  WHERE id NOT IN (" . implode( ',', array_keys( $tableLockWaiters ) ) . ")
                                    AND id <> CONNECTION_ID()
                                    AND time > 0" ;
        try {
            $potentialResult = $dbh->query( $potentialBlockerQuery ) ;
            if ( $potentialResult !== false ) {
                while ( $row = $potentialResult->fetch_assoc() ) {
                    $threadId = (int) $row['id'] ;
                    $threadQuery = $row['info'] ;
                    $threadState = strtolower( $row['state'] ?? '' ) ;
                    // Check if this is a write operation or locked table state
                    $isWriteOp = false ;
                    $matchedTable = null ;
                    if ( !empty( $threadQuery ) ) {
                        // Check for write/DDL operations that hold locks
                        if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|LOCK\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE|RENAME\s+TABLE|CREATE\s+TABLE)/i', $threadQuery ) ) {
                            foreach ( $waitingTables as $table => $v ) {
                                if ( preg_match( '/\b' . preg_quote( $table, '/' ) . '\b/i', $threadQuery ) ) {
                                    $isWriteOp = true ;
                                    $matchedTable = $table ;
                                    break ;
                                }
                            }
                        }
                    }
                    // Also check for "Has table locks" or similar states
                    if ( !$isWriteOp && strpos( $threadState, 'lock' ) !== false ) {
                        $isWriteOp = true ;
                        $matchedTable = 'unknown' ;
                    }
                    if ( $isWriteOp ) {
                        $safeBlockerQuery = $threadQuery ? Tools::makeQuotedStringPIISafe( $threadQuery ) : '[No query - holding lock]' ;
                        // Add this blocker to all unblocked waiters that match the table
                        foreach ( $unblockedWaiters as $waitingThreadId ) {
                            $waiterQuery = $tableLockWaiters[$waitingThreadId]['query'] ?? '' ;
                            $tableMatch = ( $matchedTable === 'unknown' ) ;
                            if ( !$tableMatch && !empty( $waiterQuery ) && $matchedTable !== null ) {
                                $tableMatch = preg_match( '/\b' . preg_quote( $matchedTable, '/' ) . '\b/i', $waiterQuery ) ;
                            }
                            if ( $tableMatch || $matchedTable === 'unknown' ) {
                                $lockWaitData[$waitingThreadId]['blockedBy'][] = $threadId ;
                                $lockWaitData[$waitingThreadId]['blockerQueries'][$threadId] = $safeBlockerQuery ;
                                // Mark the blocker
                                if ( !isset( $lockWaitData[$threadId] ) ) {
                                    $lockWaitData[$threadId] = [
                                        'isBlocked'   => false,
                                        'isBlocking'  => true,
                                        'waitSeconds' => 0,
                                        'lockMode'    => 'TABLE',
                                        'lockedTable' => $matchedTable,
                                        'blockedBy'   => [],
                                        'blockerQueries' => [],
                                        'blocking'    => []
                                    ] ;
                                }
                                $lockWaitData[$threadId]['isBlocking'] = true ;
                                if ( !in_array( $waitingThreadId, $lockWaitData[$threadId]['blocking'] ) ) {
                                    $lockWaitData[$threadId]['blocking'][] = $waitingThreadId ;
                                }
                            }
                        }
                        // Check if this blocker is already in outputList and update it
                        $foundInOutput = false ;
                        foreach ( $outputList as $idx => $thread ) {
                            if ( (int) $thread['id'] === $threadId ) {
                                $outputList[$idx]['blockInfo'] = $lockWaitData[$threadId] ;
                                $foundInOutput = true ;
                                break ;
                            }
                        }
                        // If not in outputList (e.g., Sleep thread), add it
                        if ( !$foundInOutput && isset( $lockWaitData[$threadId] ) ) {
                            $overviewData['blocking'] ++ ;
                            $friendlyTime = Tools::friendlyTime( $row['time'] ) ;
                            $safeInfo = Tools::makeQuotedStringPIISafe( $row['info'] ) ;
                            $safeInfoJS = urlencode( $safeInfo ) ;
                            $safeUrl = urlencode( $safeInfo ) ;
                            $outputList[] = [
                                'level'        => 3,  // Warning level for blockers
                                'time'         => $row['time'],
                                'friendlyTime' => $friendlyTime,
                                'server'       => $hostname,
                                'id'           => $threadId,
                                'user'         => $row['user'],
                                'host'         => $row['host'],
                                'db'           => $row['db'],
                                'command'      => $row['command'],
                                'state'        => $row['state'],
                                'dupeState'    => 'Blank',
                                'info'         => htmlspecialchars( $safeInfo ?: '[Holding table lock]' ),
                                'actions'      => "<button type=\"button\" onclick=\"killProcOnHost( '$hostname', $threadId, '{$row['user']}', '{$row['host']}', '{$row['db']}', '{$row['command']}', {$row['time']}, '{$row['state']}', '$safeInfoJS' ) ; return false ;\">Kill Thread</button>"
                                                . "<button type=\"button\" onclick=\"fileIssue( '$hostname', '0', '{$row['host']}', '{$row['user']}', '{$row['db']}', {$row['time']}, '$safeUrl' ) ; return false ;\">File Issue</button>",
                                'readOnly'     => 0,
                                'blockInfo'    => $lockWaitData[$threadId]
                            ] ;
                            $overviewData['threads'] ++ ;
                        }
                    }
                }
                $potentialResult->close() ;
            }
        } catch ( \Exception $e ) {
            // Ignore errors in secondary blocker search
        }
        // Update waiter blockInfo in outputList after secondary search
        foreach ( $outputList as $idx => $thread ) {
            $threadId = (int) $thread['id'] ;
            if ( isset( $tableLockWaiters[$threadId] ) && isset( $lockWaitData[$threadId] ) ) {
                $outputList[$idx]['blockInfo'] = $lockWaitData[$threadId] ;
            }
        }
    }

    // 5b. Ensure ALL blocking threads are in outputList (they may have been filtered out as Sleep threads)
    $outputThreadIds = [] ;
    foreach ( $outputList as $thread ) {
        $outputThreadIds[(int) $thread['id']] = true ;
    }
    $missingBlockerIds = [] ;
    foreach ( $lockWaitData as $threadId => $info ) {
        if ( !empty( $info['isBlocking'] ) && !isset( $outputThreadIds[(int) $threadId] ) ) {
            $missingBlockerIds[] = (int) $threadId ;
        }
    }
    if ( !empty( $missingBlockerIds ) ) {
        $idList = implode( ',', $missingBlockerIds ) ;
        $blockerQuery = "SELECT id, user, host, db, command, time, state, info, $roQueryPart as read_only
                         FROM INFORMATION_SCHEMA.PROCESSLIST
                         WHERE id IN ($idList)" ;
        try {
            $blockerResult = $dbh->query( $blockerQuery ) ;
            if ( $blockerResult !== false ) {
                while ( $row = $blockerResult->fetch_assoc() ) {
                    $threadId = (int) $row['id'] ;
                    $friendlyTime = Tools::friendlyTime( $row['time'] ) ;
                    $safeInfo = Tools::makeQuotedStringPIISafe( $row['info'] ) ;
                    $safeInfoJS = urlencode( $safeInfo ) ;
                    $safeUrl = urlencode( $safeInfo ) ;
                    $outputList[] = [
                        'level'        => 3,  // Warning level for blockers
                        'time'         => $row['time'],
                        'friendlyTime' => $friendlyTime,
                        'server'       => $hostname,
                        'id'           => $threadId,
                        'user'         => $row['user'],
                        'host'         => $row['host'],
                        'db'           => $row['db'],
                        'command'      => $row['command'],
                        'state'        => $row['state'],
                        'dupeState'    => 'Blank',
                        'info'         => htmlspecialchars( $safeInfo ?: '[Holding lock - no active query]' ),
                        'actions'      => "<button type=\"button\" onclick=\"killProcOnHost( '$hostname', $threadId, '{$row['user']}', '{$row['host']}', '{$row['db']}', '{$row['command']}', {$row['time']}, '{$row['state']}', '$safeInfoJS' ) ; return false ;\">Kill Thread</button>"
                                        . "<button type=\"button\" onclick=\"fileIssue( '$hostname', '0', '{$row['host']}', '{$row['user']}', '{$row['db']}', {$row['time']}, '$safeUrl' ) ; return false ;\">File Issue</button>",
                        'readOnly'     => $row['read_only'],
                        'blockInfo'    => $lockWaitData[$threadId]
                    ] ;
                    $overviewData['threads'] ++ ;
                    // Only increment blocking count if not already counted
                    // (it should have been counted when lockWaitData entry was created)
                }
                $blockerResult->close() ;
            }
        } catch ( \Exception $e ) {
            // Ignore errors fetching missing blockers
        }
    }

    // 6. Store detected blocking relationships in cache for future reference
    $cacheEntries = [] ;
    foreach ( $lockWaitData as $threadId => $info ) {
        if ( !empty( $info['isBlocking'] ) && !empty( $info['blocking'] ) ) {
            foreach ( $info['blocking'] as $waitingId ) {
                $blockerQuery = null ;
                // Find the blocker's query in outputList
                foreach ( $outputList as $thread ) {
                    if ( (int) $thread['id'] === (int) $threadId ) {
                        $blockerQuery = html_entity_decode( $thread['info'] ?? '' ) ;
                        break ;
                    }
                }
                $cacheEntries[] = [
                    'blockerThreadId' => (int) $threadId,
                    'waitingThreadId' => (int) $waitingId,
                    'blockerQuery'    => $blockerQuery ? Tools::makeQuotedStringPIISafe( $blockerQuery ) : null,
                    'table'           => $info['lockedTable'] ?? 'unknown',
                    'lockMode'        => $info['lockMode'] ?? 'unknown'
                ] ;
            }
        }
    }
    if ( !empty( $cacheEntries ) ) {
        writeBlockingCache( $hostname, $cacheEntries ) ;
    }

    // 6b. Log blocking queries to blocking_history table for pattern analysis
    $hostId = getHostIdFromHostname( $hostname ) ;
    if ( $hostId !== null ) {
        foreach ( $lockWaitData as $threadId => $info ) {
            if ( !empty( $info['isBlocking'] ) && !empty( $info['blocking'] ) ) {
                // Find the blocking thread's details in outputList
                foreach ( $outputList as $thread ) {
                    if ( (int) $thread['id'] === (int) $threadId ) {
                        $queryText = html_entity_decode( $thread['info'] ?? '' ) ;
                        // Skip if no meaningful query text
                        if ( !empty( $queryText ) && $queryText !== '[Holding lock - no active query]' && $queryText !== '[Holding table lock]' ) {
                            $blockedCount = count( $info['blocking'] ) ;
                            $blockTimeSecs = (int) ( $thread['time'] ?? 0 ) ;
                            logBlockingQuery(
                                $hostId,
                                $thread['user'] ?? 'unknown',
                                $thread['host'] ?? 'unknown',
                                $thread['db'],
                                $queryText,
                                $blockedCount,
                                $blockTimeSecs
                            ) ;
                        }
                        break ;
                    }
                }
            }
        }
        // Occasionally purge old entries (1% of requests)
        if ( mt_rand( 1, 100 ) === 1 ) {
            purgeOldBlockingHistory() ;
        }
    }

    // 7. For waiting threads without identified blockers, check cache for recent blockers
    foreach ( $outputList as $idx => $thread ) {
        $threadId = (int) $thread['id'] ;
        $blockInfo = $thread['blockInfo'] ?? null ;
        // If thread is blocked but has no identified blocker
        if ( $blockInfo && !empty( $blockInfo['isBlocked'] ) && empty( $blockInfo['blockedBy'] ) ) {
            $cachedBlockers = getCachedBlockersForThread( $hostname, $threadId ) ;
            if ( !empty( $cachedBlockers ) ) {
                foreach ( $cachedBlockers as $cached ) {
                    $lockWaitData[$threadId]['blockedBy'][] = $cached['blockerThreadId'] ;
                    $lockWaitData[$threadId]['blockerQueries'][$cached['blockerThreadId']] = $cached['blockerQuery'] ;
                    $lockWaitData[$threadId]['fromCache'] = true ;
                }
                $outputList[$idx]['blockInfo'] = $lockWaitData[$threadId] ;
            }
        }
    }

    $slaveResult = $dbh->query( $showSlaveStatement ) ;
    if ( $slaveResult === false ) {
        throw new \ErrorException( "Error running query: $processQuery (" . $dbh->error . ")\n" ) ;
    }
    while ($row = $slaveResult->fetch_assoc()) {
        $thisResult = array() ;
        foreach ($replica_labels as $k => $v) {
            $thisResult[ $k ] = $row[ $v ] ?? '' ;
        }
        $slaveData[] = $thisResult ;
    }
    $slaveResult->close() ;
}
catch (\Exception $e) {
    echo json_encode([
        'hostname' => $hostname,
        'hostId' => $hostId,
        'hostGroups' => $hostGroups,
        'error_output' => $e->getMessage(),
        'maintenanceInfo' => $maintenanceInfo
    ]) ;
    exit(1) ;
}
$overviewData[ 'longest_running' ] = $longestRunning ;

$output = [ 'hostname'        => $hostname
          , 'hostId'          => $hostId
          , 'hostGroups'      => $hostGroups
          , 'result'          => $outputList
          , 'overviewData'    => $overviewData
          , 'slaveData'       => $slaveData
          , 'maintenanceInfo' => $maintenanceInfo
          ] ;
// Always add lock debug info when debugLocks=1
$output['debugLocks'] = $debugLocks ;
if ( $debugLocks ) {
    $output['debugLockWaitData'] = $lockWaitData ;
    $output['debugLockWaitCount'] = count( $lockWaitData ) ;
    $output['debugTableLockQuery'] = $tableLockDebug ?? null ;
    // Also count how many threads have "waiting" in their state
    $waitingCount = 0 ;
    foreach ( $outputList as $item ) {
        if ( stripos( $item['state'] ?? '', 'waiting' ) !== false ) {
            $waitingCount++ ;
        }
    }
    $output['debugWaitingThreadsInOutput'] = $waitingCount ;
    // Show open tables with locks
    try {
        $openTablesResult = $dbh->query( "SHOW OPEN TABLES WHERE In_use > 0" ) ;
        $openTables = [] ;
        if ( $openTablesResult !== false ) {
            while ( $row = $openTablesResult->fetch_assoc() ) {
                $openTables[] = $row ;
            }
            $openTablesResult->close() ;
        }
        $output['debugOpenTablesWithLocks'] = $openTables ;
    } catch ( \Exception $e ) {
        $output['debugOpenTablesWithLocks'] = [ 'error' => $e->getMessage() ] ;
    }
    // List waiting tables we identified
    $output['debugWaitingTables'] = array_keys( $waitingTables ?? [] ) ;
    // Show blocking cache contents and type
    $output['debugBlockingCache'] = readBlockingCache( $hostname ) ;
    $output['debugBlockingCacheType'] = getBlockingCacheType() ;
}
echo json_encode( $output ) . "\n" ;
