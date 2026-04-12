<?php

namespace com\kbcmdba\aql\Tests\Libs\Controllers ;

use com\kbcmdba\aql\Libs\Controllers\ControllerBase ;
use com\kbcmdba\aql\Libs\Exceptions\ControllerException ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/Controllers/ControllerBase.php
 *
 * The static date/time helpers (timestamp, datestamp, now, today, tomorrow,
 * yesterday) are pure functions with no DB dependency. The constructor and
 * DB methods (doDDL, deleteModelById) need a real DB connection and are
 * tested via the concrete controller subclass tests.
 */
class ControllerBaseTest extends TestCase
{
    // ========================================================================
    // timestamp() — returns "Y-m-d H:i:s" for a given epoch or current time
    // ========================================================================

    public function testTimestampWithEpoch() : void
    {
        $oldTz = date_default_timezone_get() ;
        date_default_timezone_set('UTC') ;
        try {
            $this->assertSame('2024-01-15 12:30:45', ControllerBase::timestamp(1705321845)) ;
        } finally {
            date_default_timezone_set($oldTz) ;
        }
    }

    public function testTimestampWithZero() : void
    {
        $oldTz = date_default_timezone_get() ;
        date_default_timezone_set('UTC') ;
        try {
            $this->assertSame('1970-01-01 00:00:00', ControllerBase::timestamp(0)) ;
        } finally {
            date_default_timezone_set($oldTz) ;
        }
    }

    public function testTimestampWithNullUsesNow() : void
    {
        $before = time() ;
        $result = ControllerBase::timestamp(null) ;
        $after = time() ;
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result) ;
        $epoch = strtotime($result) ;
        $this->assertGreaterThanOrEqual($before - 1, $epoch) ;
        $this->assertLessThanOrEqual($after + 1, $epoch) ;
    }

    public function testTimestampThrowsOnNonNumeric() : void
    {
        $this->expectException(ControllerException::class) ;
        $this->expectExceptionMessageMatches('/Invalid timestamp/') ;
        ControllerBase::timestamp('not_a_number') ;
    }

    public function testTimestampAcceptsStringNumeric() : void
    {
        $oldTz = date_default_timezone_get() ;
        date_default_timezone_set('UTC') ;
        try {
            $this->assertSame('2024-01-15 12:30:45', ControllerBase::timestamp('1705321845')) ;
        } finally {
            date_default_timezone_set($oldTz) ;
        }
    }

    // ========================================================================
    // datestamp() — returns "Y-m-d" for a given epoch or current date
    // ========================================================================

    public function testDatestampWithEpoch() : void
    {
        $oldTz = date_default_timezone_get() ;
        date_default_timezone_set('UTC') ;
        try {
            $this->assertSame('2024-01-15', ControllerBase::datestamp(1705321845)) ;
        } finally {
            date_default_timezone_set($oldTz) ;
        }
    }

    public function testDatestampWithNullUsesNow() : void
    {
        $result = ControllerBase::datestamp(null) ;
        $this->assertSame(date('Y-m-d'), $result) ;
    }

    public function testDatestampThrowsOnNonNumeric() : void
    {
        $this->expectException(ControllerException::class) ;
        $this->expectExceptionMessageMatches('/Invalid timestamp/') ;
        ControllerBase::datestamp('garbage') ;
    }

    // ========================================================================
    // now() — returns current timestamp like MySQL NOW()
    // ========================================================================

    public function testNowReturnsCurrentTimestamp() : void
    {
        $before = time() ;
        $result = ControllerBase::now() ;
        $after = time() ;
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result) ;
        $epoch = strtotime($result) ;
        $this->assertGreaterThanOrEqual($before - 1, $epoch) ;
        $this->assertLessThanOrEqual($after + 1, $epoch) ;
    }

    // ========================================================================
    // today() — returns current date like MySQL CURDATE()
    // ========================================================================

    public function testTodayReturnsCurrentDate() : void
    {
        $result = ControllerBase::today() ;
        $this->assertSame(date('Y-m-d'), $result) ;
    }

    // ========================================================================
    // tomorrow() — returns tomorrow's date
    // ========================================================================

    public function testTomorrowReturnsNextDay() : void
    {
        $result = ControllerBase::tomorrow() ;
        $expected = date('Y-m-d', time() + 86400) ;
        $this->assertSame($expected, $result) ;
    }

    // ========================================================================
    // yesterday() — returns yesterday's date
    // ========================================================================

    public function testYesterdayReturnsPreviousDay() : void
    {
        $result = ControllerBase::yesterday() ;
        $expected = date('Y-m-d', time() - 86400) ;
        $this->assertSame($expected, $result) ;
    }
}
