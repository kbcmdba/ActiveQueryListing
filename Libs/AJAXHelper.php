<?php

/**
 * aql
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

namespace com\kbcmdba\aql\Libs ;

/**
 * Pure utility functions extracted from AJAXgetaql.php for testability.
 * All methods are stateless and have no side effects.
 */
class AJAXHelper
{

    /**
     * Normalize query for hashing — replace literals to create a fingerprint.
     * Matches JavaScript normalizeQueryForHash() in common.js.
     *
     * @param string $query SQL query text
     * @return string Normalized query with literals replaced
     */
    public static function normalizeQueryForHash( string $query ) : string
    {
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
        // Collapse multiple blank lines into single newline for compact storage
        $normalized = preg_replace( '/\n\s*\n/', "\n", $normalized ) ;
        return $normalized ;
    }

    /**
     * Hash string using djb2 algorithm — matches JavaScript hashString() in common.js.
     *
     * @param string $str String to hash
     * @return string 16-character zero-padded hex hash
     */
    public static function hashQueryString( string $str ) : string
    {
        $hash = 5381 ;
        $len = strlen( $str ) ;
        for ( $i = 0 ; $i < $len ; $i++ ) {
            $hash = ( ( $hash << 5 ) + $hash ) + ord( $str[$i] ) ;
            $hash = $hash & 0xFFFFFFFF ; // Keep as 32-bit
        }
        return str_pad( dechex( abs( $hash ) ), 16, '0', STR_PAD_LEFT ) ;
    }

    /**
     * Build the Redis key for the blocking cache for a given hostname.
     *
     * @param string $hostname   Hostname (may contain colons for port, etc.)
     * @param string $prefix     Key prefix (default: 'aql:blocking:')
     * @return string            Redis key safe for all characters
     */
    public static function getBlockingCacheKey( string $hostname, string $prefix = 'aql:blocking:' ) : string
    {
        $safeHost = preg_replace( '/[^a-zA-Z0-9._-]/', '_', $hostname ) ;
        return $prefix . $safeHost ;
    }

    /**
     * Build the file path for the blocking cache for a given hostname.
     *
     * @param string $hostname   Hostname
     * @param string $cacheDir   Directory that holds cache files
     * @return string            Full path to the cache file
     */
    public static function getBlockingCacheFile( string $hostname, string $cacheDir ) : string
    {
        $safeHost = preg_replace( '/[^a-zA-Z0-9._-]/', '_', $hostname ) ;
        return $cacheDir . '/blocking_' . $safeHost . '.json' ;
    }

    /**
     * Filter out expired entries from file-based cache data.
     * Redis handles TTL natively; this is only needed for the file backend.
     *
     * @param array $data   Raw decoded cache array
     * @param int   $now    Current Unix timestamp
     * @param int   $ttl    Maximum age in seconds
     * @return array        Entries whose 'timestamp' is within $ttl of $now
     */
    public static function filterExpiredCacheEntries( array $data, int $now, int $ttl ) : array
    {
        $valid = [] ;
        foreach ( $data as $entry ) {
            if ( isset( $entry['timestamp'] ) && ( $now - $entry['timestamp'] ) < $ttl ) {
                $valid[] = $entry ;
            }
        }
        return $valid ;
    }

    /**
     * Return cached blocking entries for a specific waiting thread.
     *
     * @param array $cache           Full cache array (already loaded / TTL-filtered)
     * @param int   $waitingThreadId Thread ID that is being blocked
     * @return array                 Cache entries where waitingThreadId matches
     */
    public static function findCachedBlockers( array $cache, int $waitingThreadId ) : array
    {
        $blockers = [] ;
        foreach ( $cache as $entry ) {
            if ( isset( $entry['waitingThreadId'] ) && $entry['waitingThreadId'] == $waitingThreadId ) {
                $blockers[] = $entry ;
            }
        }
        return $blockers ;
    }

    /**
     * Merge new blocking entries with existing ones, deduplicating by
     * (blockerThreadId, waitingThreadId) pair.  New entries take precedence;
     * existing entries that duplicate a new pair are dropped.
     * Entries without a 'timestamp' receive $now.
     *
     * @param array $newEntries New blocking entries (will receive timestamps)
     * @param array $existing   Previously stored entries
     * @param int   $now        Current Unix timestamp for new entries
     * @return array            Merged array (new entries first, then unique old ones)
     */
    public static function mergeBlockingCacheEntries( array $newEntries, array $existing, int $now ) : array
    {
        // Stamp any new entries that lack a timestamp
        foreach ( $newEntries as &$entry ) {
            if ( !isset( $entry['timestamp'] ) ) {
                $entry['timestamp'] = $now ;
            }
        }
        unset( $entry ) ; // break reference

        $merged = $newEntries ;
        foreach ( $existing as $old ) {
            $isDupe = false ;
            foreach ( $newEntries as $new ) {
                if ( isset( $old['blockerThreadId'], $old['waitingThreadId'] )
                     && $old['blockerThreadId'] == $new['blockerThreadId']
                     && $old['waitingThreadId'] == $new['waitingThreadId'] ) {
                    $isDupe = true ;
                    break ;
                }
            }
            if ( !$isDupe ) {
                $merged[] = $old ;
            }
        }
        return $merged ;
    }
}
