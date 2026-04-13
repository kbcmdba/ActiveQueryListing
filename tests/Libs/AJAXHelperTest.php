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

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\AJAXHelper ;
use PHPUnit\Framework\TestCase ;

class AJAXHelperTest extends TestCase
{

    // -------------------------------------------------------------------------
    // hashQueryString
    // -------------------------------------------------------------------------

    public function testHashEmptyString() : void
    {
        // djb2 of empty string is the seed value 5381 = 0x1505
        $this->assertSame( '0000000000001505', AJAXHelper::hashQueryString( '' ) ) ;
    }

    public function testHashHello() : void
    {
        $this->assertSame( '000000000f923099', AJAXHelper::hashQueryString( 'hello' ) ) ;
    }

    public function testHashKnownValue() : void
    {
        $this->assertSame( '000000008acc8096', AJAXHelper::hashQueryString( 'SELECT 1' ) ) ;
    }

    public function testHashIs16HexChars() : void
    {
        $result = AJAXHelper::hashQueryString( 'any string' ) ;
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $result ) ;
    }

    public function testHashIsDeterministic() : void
    {
        $input = 'SELECT * FROM users WHERE id = 42' ;
        $this->assertSame( AJAXHelper::hashQueryString( $input ), AJAXHelper::hashQueryString( $input ) ) ;
    }

    public function testHashDifferentInputsDifferentOutput() : void
    {
        $this->assertNotSame(
            AJAXHelper::hashQueryString( 'SELECT 1' ),
            AJAXHelper::hashQueryString( 'SELECT 2' )
        ) ;
    }

    // -------------------------------------------------------------------------
    // normalizeQueryForHash — numbers
    // -------------------------------------------------------------------------

    public function testNormalizeIntegers() : void
    {
        $this->assertSame(
            'SELECT N FROM t WHERE id = N',
            AJAXHelper::normalizeQueryForHash( 'SELECT 1 FROM t WHERE id = 42' )
        ) ;
    }

    public function testNormalizeDecimal() : void
    {
        $this->assertSame(
            'WHERE price > N',
            AJAXHelper::normalizeQueryForHash( 'WHERE price > 9.99' )
        ) ;
    }

    public function testNormalizeScientificNotation() : void
    {
        $this->assertSame(
            'WHERE val = N',
            AJAXHelper::normalizeQueryForHash( 'WHERE val = 1e10' )
        ) ;
    }

    // -------------------------------------------------------------------------
    // normalizeQueryForHash — string literals
    // -------------------------------------------------------------------------

    public function testNormalizeSingleQuotedString() : void
    {
        $this->assertSame(
            "WHERE name = 'S'",
            AJAXHelper::normalizeQueryForHash( "WHERE name = 'John'" )
        ) ;
    }

    public function testNormalizeSingleQuotedStringWithBackslashEscape() : void
    {
        $this->assertSame(
            "WHERE name = 'S'",
            AJAXHelper::normalizeQueryForHash( "WHERE name = 'it\\'s'" )
        ) ;
    }

    public function testNormalizeSingleQuotedStringWithDoubledQuotes() : void
    {
        // MySQL-style doubled single quotes: 'it''s a test' -> 'S'
        $this->assertSame(
            "WHERE msg = 'S'",
            AJAXHelper::normalizeQueryForHash( "WHERE msg = 'it''s a test'" )
        ) ;
    }

    public function testNormalizeDoubleQuotedString() : void
    {
        $this->assertSame(
            'WHERE name = "S"',
            AJAXHelper::normalizeQueryForHash( 'WHERE name = "Alice"' )
        ) ;
    }

    public function testNormalizeDoubleQuotedStringWithBackslashEscape() : void
    {
        $this->assertSame(
            'WHERE name = "S"',
            AJAXHelper::normalizeQueryForHash( 'WHERE name = "she said \"hi\""' )
        ) ;
    }

    // -------------------------------------------------------------------------
    // normalizeQueryForHash — hex / binary literals
    // -------------------------------------------------------------------------

    public function testNormalizeHexLiteral0x() : void
    {
        $this->assertSame(
            'WHERE id = N',
            AJAXHelper::normalizeQueryForHash( 'WHERE id = 0xDEADBEEF' )
        ) ;
    }

    public function testNormalizeHexLiteralXQuote() : void
    {
        $this->assertSame(
            'WHERE id = N',
            AJAXHelper::normalizeQueryForHash( "WHERE id = x'1A2B'" )
        ) ;
    }

    public function testNormalizeBinaryLiteral0b() : void
    {
        $this->assertSame(
            'WHERE flag = N',
            AJAXHelper::normalizeQueryForHash( 'WHERE flag = 0b1010' )
        ) ;
    }

    public function testNormalizeBinaryLiteralBQuote() : void
    {
        $this->assertSame(
            'WHERE flag = N',
            AJAXHelper::normalizeQueryForHash( "WHERE flag = b'1010'" )
        ) ;
    }

    // -------------------------------------------------------------------------
    // normalizeQueryForHash — SQL comments
    // -------------------------------------------------------------------------

    public function testNormalizeMultiLineComment() : void
    {
        $this->assertSame(
            'SELECT /**/deleted N',
            AJAXHelper::normalizeQueryForHash( 'SELECT /* a comment */ 1' )
        ) ;
    }

    public function testNormalizeSingleLineDoubleDash() : void
    {
        // Note: MySQL requires a space after -- for it to be a comment
        $result = AJAXHelper::normalizeQueryForHash( "SELECT 1 -- this is a comment\nFROM t" ) ;
        $this->assertStringContainsString( '-- deleted', $result ) ;
        $this->assertStringNotContainsString( 'this is a comment', $result ) ;
    }

    public function testNormalizeSingleLineHash() : void
    {
        $result = AJAXHelper::normalizeQueryForHash( "SELECT 1 # hash comment\nFROM t" ) ;
        $this->assertStringContainsString( '# deleted', $result ) ;
        $this->assertStringNotContainsString( 'hash comment', $result ) ;
    }

    public function testNormalizeDoubleDashWithoutSpaceIsNotComment() : void
    {
        // --comment without a space after -- is NOT treated as a SQL comment
        $result = AJAXHelper::normalizeQueryForHash( '--comment' ) ;
        $this->assertStringNotContainsString( '-- deleted', $result ) ;
    }

    // -------------------------------------------------------------------------
    // normalizeQueryForHash — whitespace collapsing
    // -------------------------------------------------------------------------

    public function testNormalizeCollapseMultipleBlankLines() : void
    {
        $input    = "SELECT 1\n\n\n\nFROM t" ;
        $expected = "SELECT N\nFROM t" ;
        $this->assertSame( $expected, AJAXHelper::normalizeQueryForHash( $input ) ) ;
    }

    // -------------------------------------------------------------------------
    // normalizeQueryForHash — combined cases
    // -------------------------------------------------------------------------

    public function testNormalizeTypicalSelectQuery() : void
    {
        $input = "SELECT id, name FROM users WHERE id = 42 AND status = 'active'" ;
        $result = AJAXHelper::normalizeQueryForHash( $input ) ;
        $this->assertStringContainsString( 'N', $result ) ;
        $this->assertStringContainsString( "'S'", $result ) ;
        $this->assertStringNotContainsString( '42', $result ) ;
        $this->assertStringNotContainsString( 'active', $result ) ;
    }

    public function testNormalizePreservesQueryStructure() : void
    {
        // Two queries with same structure but different values should normalize to same string
        $q1 = "SELECT * FROM t WHERE id = 1" ;
        $q2 = "SELECT * FROM t WHERE id = 99" ;
        $this->assertSame(
            AJAXHelper::normalizeQueryForHash( $q1 ),
            AJAXHelper::normalizeQueryForHash( $q2 )
        ) ;
    }

    public function testNormalizeDifferentStructuresDifferentResult() : void
    {
        $q1 = "SELECT * FROM t WHERE id = 1" ;
        $q2 = "SELECT * FROM t WHERE name = 'x'" ;
        $this->assertNotSame(
            AJAXHelper::normalizeQueryForHash( $q1 ),
            AJAXHelper::normalizeQueryForHash( $q2 )
        ) ;
    }

    // -------------------------------------------------------------------------
    // getBlockingCacheKey
    // -------------------------------------------------------------------------

    public function testCacheKeyNormalHostname() : void
    {
        $this->assertSame(
            'aql:blocking:db1.example.com',
            AJAXHelper::getBlockingCacheKey( 'db1.example.com' )
        ) ;
    }

    public function testCacheKeyWithPort() : void
    {
        // Colon in hostname:port gets sanitized to underscore
        $this->assertSame(
            'aql:blocking:db1.example.com_3306',
            AJAXHelper::getBlockingCacheKey( 'db1.example.com:3306' )
        ) ;
    }

    public function testCacheKeyCustomPrefix() : void
    {
        $this->assertSame(
            'myprefix:db1',
            AJAXHelper::getBlockingCacheKey( 'db1', 'myprefix:' )
        ) ;
    }

    public function testCacheKeySpecialCharsReplaced() : void
    {
        $key = AJAXHelper::getBlockingCacheKey( 'host/with spaces&stuff', '' ) ;
        $this->assertMatchesRegularExpression( '/^[a-zA-Z0-9._-]+$/', $key ) ;
    }

    // -------------------------------------------------------------------------
    // getBlockingCacheFile
    // -------------------------------------------------------------------------

    public function testCacheFileNormalHostname() : void
    {
        $this->assertSame(
            '/tmp/cache/blocking_db1.example.com.json',
            AJAXHelper::getBlockingCacheFile( 'db1.example.com', '/tmp/cache' )
        ) ;
    }

    public function testCacheFileWithPort() : void
    {
        $this->assertSame(
            '/var/cache/blocking_db1_3306.json',
            AJAXHelper::getBlockingCacheFile( 'db1:3306', '/var/cache' )
        ) ;
    }

    public function testCacheFileSpecialCharsReplaced() : void
    {
        $path = AJAXHelper::getBlockingCacheFile( 'host with spaces', '/tmp' ) ;
        $this->assertStringContainsString( 'host_with_spaces', $path ) ;
    }

    // -------------------------------------------------------------------------
    // filterExpiredCacheEntries
    // -------------------------------------------------------------------------

    public function testFilterKeepsRecentEntries() : void
    {
        $now  = time() ;
        $data = [
            [ 'blockerThreadId' => 1, 'waitingThreadId' => 2, 'timestamp' => $now - 10 ],
        ] ;
        $result = AJAXHelper::filterExpiredCacheEntries( $data, $now, 60 ) ;
        $this->assertCount( 1, $result ) ;
    }

    public function testFilterDropsExpiredEntries() : void
    {
        $now  = time() ;
        $data = [
            [ 'blockerThreadId' => 1, 'waitingThreadId' => 2, 'timestamp' => $now - 120 ],
        ] ;
        $result = AJAXHelper::filterExpiredCacheEntries( $data, $now, 60 ) ;
        $this->assertCount( 0, $result ) ;
    }

    public function testFilterDropsEntryWithMissingTimestamp() : void
    {
        $now  = time() ;
        $data = [
            [ 'blockerThreadId' => 1, 'waitingThreadId' => 2 ], // no timestamp
        ] ;
        $result = AJAXHelper::filterExpiredCacheEntries( $data, $now, 60 ) ;
        $this->assertCount( 0, $result ) ;
    }

    public function testFilterMixedEntries() : void
    {
        $now  = time() ;
        $data = [
            [ 'blockerThreadId' => 1, 'waitingThreadId' => 2, 'timestamp' => $now - 10 ],  // fresh
            [ 'blockerThreadId' => 3, 'waitingThreadId' => 4, 'timestamp' => $now - 120 ], // expired
            [ 'blockerThreadId' => 5, 'waitingThreadId' => 6, 'timestamp' => $now - 59 ],  // just within TTL
        ] ;
        $result = AJAXHelper::filterExpiredCacheEntries( $data, $now, 60 ) ;
        $this->assertCount( 2, $result ) ;
        $this->assertSame( 1, $result[0]['blockerThreadId'] ) ;
        $this->assertSame( 5, $result[1]['blockerThreadId'] ) ;
    }

    public function testFilterEmptyArray() : void
    {
        $result = AJAXHelper::filterExpiredCacheEntries( [], time(), 60 ) ;
        $this->assertSame( [], $result ) ;
    }

    // -------------------------------------------------------------------------
    // findCachedBlockers
    // -------------------------------------------------------------------------

    public function testFindBlockersMatchesCorrectThread() : void
    {
        $cache = [
            [ 'blockerThreadId' => 10, 'waitingThreadId' => 20 ],
            [ 'blockerThreadId' => 30, 'waitingThreadId' => 40 ],
        ] ;
        $result = AJAXHelper::findCachedBlockers( $cache, 20 ) ;
        $this->assertCount( 1, $result ) ;
        $this->assertSame( 10, $result[0]['blockerThreadId'] ) ;
    }

    public function testFindBlockersNoMatch() : void
    {
        $cache = [
            [ 'blockerThreadId' => 10, 'waitingThreadId' => 20 ],
        ] ;
        $result = AJAXHelper::findCachedBlockers( $cache, 99 ) ;
        $this->assertSame( [], $result ) ;
    }

    public function testFindBlockersMultipleMatches() : void
    {
        $cache = [
            [ 'blockerThreadId' => 10, 'waitingThreadId' => 20 ],
            [ 'blockerThreadId' => 11, 'waitingThreadId' => 20 ],
            [ 'blockerThreadId' => 12, 'waitingThreadId' => 30 ],
        ] ;
        $result = AJAXHelper::findCachedBlockers( $cache, 20 ) ;
        $this->assertCount( 2, $result ) ;
    }

    public function testFindBlockersEmptyCache() : void
    {
        $result = AJAXHelper::findCachedBlockers( [], 42 ) ;
        $this->assertSame( [], $result ) ;
    }

    // -------------------------------------------------------------------------
    // mergeBlockingCacheEntries
    // -------------------------------------------------------------------------

    public function testMergeStampsNewEntriesWithoutTimestamp() : void
    {
        $now     = time() ;
        $entries = [ [ 'blockerThreadId' => 1, 'waitingThreadId' => 2 ] ] ;
        $result  = AJAXHelper::mergeBlockingCacheEntries( $entries, [], $now ) ;
        $this->assertSame( $now, $result[0]['timestamp'] ) ;
    }

    public function testMergePreservesExistingTimestamp() : void
    {
        $now     = time() ;
        $earlier = $now - 30 ;
        $entries = [ [ 'blockerThreadId' => 1, 'waitingThreadId' => 2, 'timestamp' => $earlier ] ] ;
        $result  = AJAXHelper::mergeBlockingCacheEntries( $entries, [], $now ) ;
        $this->assertSame( $earlier, $result[0]['timestamp'] ) ;
    }

    public function testMergeIncludesUniqueOldEntries() : void
    {
        $now = time() ;
        $new = [ [ 'blockerThreadId' => 1, 'waitingThreadId' => 2 ] ] ;
        $old = [ [ 'blockerThreadId' => 3, 'waitingThreadId' => 4, 'timestamp' => $now - 10 ] ] ;
        $result = AJAXHelper::mergeBlockingCacheEntries( $new, $old, $now ) ;
        $this->assertCount( 2, $result ) ;
    }

    public function testMergeDropsDuplicateOldEntries() : void
    {
        $now = time() ;
        $new = [ [ 'blockerThreadId' => 1, 'waitingThreadId' => 2 ] ] ;
        $old = [ [ 'blockerThreadId' => 1, 'waitingThreadId' => 2, 'timestamp' => $now - 10 ] ] ;
        $result = AJAXHelper::mergeBlockingCacheEntries( $new, $old, $now ) ;
        $this->assertCount( 1, $result ) ; // only the new entry survives
    }

    public function testMergeNewEntriesFirst() : void
    {
        $now = time() ;
        $new = [ [ 'blockerThreadId' => 1, 'waitingThreadId' => 2 ] ] ;
        $old = [ [ 'blockerThreadId' => 3, 'waitingThreadId' => 4, 'timestamp' => $now - 10 ] ] ;
        $result = AJAXHelper::mergeBlockingCacheEntries( $new, $old, $now ) ;
        // New entries come first in the merged array
        $this->assertSame( 1, $result[0]['blockerThreadId'] ) ;
        $this->assertSame( 3, $result[1]['blockerThreadId'] ) ;
    }

    public function testMergeEmptyNewWithExisting() : void
    {
        $now = time() ;
        $old = [ [ 'blockerThreadId' => 5, 'waitingThreadId' => 6, 'timestamp' => $now - 5 ] ] ;
        $result = AJAXHelper::mergeBlockingCacheEntries( [], $old, $now ) ;
        $this->assertCount( 1, $result ) ;
        $this->assertSame( 5, $result[0]['blockerThreadId'] ) ;
    }

    public function testMergeBothEmpty() : void
    {
        $result = AJAXHelper::mergeBlockingCacheEntries( [], [], time() ) ;
        $this->assertSame( [], $result ) ;
    }
}
