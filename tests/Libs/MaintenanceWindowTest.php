<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\MaintenanceWindow ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/MaintenanceWindow.php pure-function logic.
 *
 * The public methods (getActiveWindowForHost, etc.) require a database
 * connection and are tested elsewhere via integration tests. This file
 * focuses on the private static helpers via reflection - the time and
 * schedule matching logic that determines whether a window is "active".
 */
class MaintenanceWindowTest extends TestCase
{
    /**
     * Helper to call private static methods via reflection.
     */
    private function callPrivate( string $method, array $args = [] )
    {
        $ref = new \ReflectionMethod( MaintenanceWindow::class, $method ) ;
        $ref->setAccessible( true ) ;
        return $ref->invoke( null, ...$args ) ;
    }

    // ========================================================================
    // isOvernightWindow() - true if start > end (e.g., 22:00 - 06:00)
    // ========================================================================

    public function testIsOvernightWindowTrueForOvernightSpan() : void
    {
        $this->assertTrue( $this->callPrivate( 'isOvernightWindow', [ '22:00:00', '06:00:00' ] ) ) ;
        $this->assertTrue( $this->callPrivate( 'isOvernightWindow', [ '23:30:00', '00:30:00' ] ) ) ;
    }

    public function testIsOvernightWindowFalseForNormalSpan() : void
    {
        $this->assertFalse( $this->callPrivate( 'isOvernightWindow', [ '09:00:00', '17:00:00' ] ) ) ;
        $this->assertFalse( $this->callPrivate( 'isOvernightWindow', [ '00:00:00', '23:59:59' ] ) ) ;
    }

    public function testIsOvernightWindowFalseForEqualTimes() : void
    {
        // start == end is NOT overnight (returns false because $start > $end is false)
        $this->assertFalse( $this->callPrivate( 'isOvernightWindow', [ '12:00:00', '12:00:00' ] ) ) ;
    }

    // ========================================================================
    // isTimeAfterOrEqual() / isTimeBeforeOrEqual()
    // ========================================================================

    public function testIsTimeAfterOrEqualTrueWhenAfter() : void
    {
        $this->assertTrue( $this->callPrivate( 'isTimeAfterOrEqual', [ '10:00:00', '09:00:00' ] ) ) ;
    }

    public function testIsTimeAfterOrEqualTrueWhenEqual() : void
    {
        $this->assertTrue( $this->callPrivate( 'isTimeAfterOrEqual', [ '09:00:00', '09:00:00' ] ) ) ;
    }

    public function testIsTimeAfterOrEqualFalseWhenBefore() : void
    {
        $this->assertFalse( $this->callPrivate( 'isTimeAfterOrEqual', [ '08:00:00', '09:00:00' ] ) ) ;
    }

    public function testIsTimeBeforeOrEqualTrueWhenBefore() : void
    {
        $this->assertTrue( $this->callPrivate( 'isTimeBeforeOrEqual', [ '08:00:00', '09:00:00' ] ) ) ;
    }

    public function testIsTimeBeforeOrEqualTrueWhenEqual() : void
    {
        $this->assertTrue( $this->callPrivate( 'isTimeBeforeOrEqual', [ '09:00:00', '09:00:00' ] ) ) ;
    }

    public function testIsTimeBeforeOrEqualFalseWhenAfter() : void
    {
        $this->assertFalse( $this->callPrivate( 'isTimeBeforeOrEqual', [ '10:00:00', '09:00:00' ] ) ) ;
    }

    // ========================================================================
    // isTimeInWindow() - normal and overnight spans
    // ========================================================================

    public function testIsTimeInWindowNormalSpanInside() : void
    {
        // 09:00 - 17:00, currently 12:00
        $this->assertTrue( $this->callPrivate( 'isTimeInWindow', [ '09:00:00', '17:00:00', '12:00:00' ] ) ) ;
    }

    public function testIsTimeInWindowNormalSpanAtBoundaries() : void
    {
        // Boundaries are inclusive
        $this->assertTrue( $this->callPrivate( 'isTimeInWindow', [ '09:00:00', '17:00:00', '09:00:00' ] ) ) ;
        $this->assertTrue( $this->callPrivate( 'isTimeInWindow', [ '09:00:00', '17:00:00', '17:00:00' ] ) ) ;
    }

    public function testIsTimeInWindowNormalSpanOutside() : void
    {
        $this->assertFalse( $this->callPrivate( 'isTimeInWindow', [ '09:00:00', '17:00:00', '08:59:59' ] ) ) ;
        $this->assertFalse( $this->callPrivate( 'isTimeInWindow', [ '09:00:00', '17:00:00', '17:00:01' ] ) ) ;
    }

    public function testIsTimeInWindowOvernightSpanEveningPortion() : void
    {
        // 22:00 - 06:00, currently 23:00 - in the evening portion
        $this->assertTrue( $this->callPrivate( 'isTimeInWindow', [ '22:00:00', '06:00:00', '23:00:00' ] ) ) ;
    }

    public function testIsTimeInWindowOvernightSpanMorningPortion() : void
    {
        // 22:00 - 06:00, currently 03:00 - in the morning portion
        $this->assertTrue( $this->callPrivate( 'isTimeInWindow', [ '22:00:00', '06:00:00', '03:00:00' ] ) ) ;
    }

    public function testIsTimeInWindowOvernightSpanGap() : void
    {
        // 22:00 - 06:00, currently 12:00 - in the daytime gap, NOT in window
        $this->assertFalse( $this->callPrivate( 'isTimeInWindow', [ '22:00:00', '06:00:00', '12:00:00' ] ) ) ;
    }

    // ========================================================================
    // isWeeklyMatch() - days_of_week is a comma-separated SET like "Mon,Wed,Fri"
    // ========================================================================

    public function testIsWeeklyMatchTodayInList() : void
    {
        // Use a known Wednesday: 2024-01-17 was a Wednesday
        $now = new \DateTime( '2024-01-17' ) ;
        $window = [ 'days_of_week' => 'Mon,Wed,Fri' ] ;
        $this->assertTrue( $this->callPrivate( 'isWeeklyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsWeeklyMatchTodayNotInList() : void
    {
        // 2024-01-16 was a Tuesday
        $now = new \DateTime( '2024-01-16' ) ;
        $window = [ 'days_of_week' => 'Mon,Wed,Fri' ] ;
        $this->assertFalse( $this->callPrivate( 'isWeeklyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsWeeklyMatchEmptyDaysOfWeek() : void
    {
        $now = new \DateTime( '2024-01-17' ) ;
        $window = [ 'days_of_week' => '' ] ;
        $this->assertFalse( $this->callPrivate( 'isWeeklyMatch', [ $window, $now ] ) ) ;
    }

    // ========================================================================
    // isMonthlyMatch() - day_of_month, with 32 = last day of month
    // ========================================================================

    public function testIsMonthlyMatchExactDay() : void
    {
        $now = new \DateTime( '2024-01-15' ) ;
        $window = [ 'day_of_month' => 15 ] ;
        $this->assertTrue( $this->callPrivate( 'isMonthlyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsMonthlyMatchWrongDay() : void
    {
        $now = new \DateTime( '2024-01-14' ) ;
        $window = [ 'day_of_month' => 15 ] ;
        $this->assertFalse( $this->callPrivate( 'isMonthlyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsMonthlyMatchLastDayOfMonth() : void
    {
        // January 31 is the last day - day_of_month=32 means last day
        $now = new \DateTime( '2024-01-31' ) ;
        $window = [ 'day_of_month' => 32 ] ;
        $this->assertTrue( $this->callPrivate( 'isMonthlyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsMonthlyMatchLastDayOfFebruaryLeapYear() : void
    {
        // 2024 is a leap year - Feb 29 is the last day
        $now = new \DateTime( '2024-02-29' ) ;
        $window = [ 'day_of_month' => 32 ] ;
        $this->assertTrue( $this->callPrivate( 'isMonthlyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsMonthlyMatchEmptyDayOfMonth() : void
    {
        $now = new \DateTime( '2024-01-15' ) ;
        $window = [ 'day_of_month' => null ] ;
        $this->assertFalse( $this->callPrivate( 'isMonthlyMatch', [ $window, $now ] ) ) ;
    }

    // ========================================================================
    // isQuarterlyMatch() - month_of_year (1-3 = position in quarter), day_of_month
    // ========================================================================

    public function testIsQuarterlyMatchFirstMonthOfQuarter() : void
    {
        // April is the first month of Q2 (Jan/Apr/Jul/Oct are "month 1" of their quarters)
        $now = new \DateTime( '2024-04-15' ) ;
        $window = [ 'month_of_year' => 1, 'day_of_month' => 15 ] ;
        $this->assertTrue( $this->callPrivate( 'isQuarterlyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsQuarterlyMatchSecondMonthOfQuarter() : void
    {
        // May is the second month of Q2
        $now = new \DateTime( '2024-05-15' ) ;
        $window = [ 'month_of_year' => 2, 'day_of_month' => 15 ] ;
        $this->assertTrue( $this->callPrivate( 'isQuarterlyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsQuarterlyMatchWrongMonthInQuarter() : void
    {
        // April is month 1 of Q2, but window wants month 2 of quarter
        $now = new \DateTime( '2024-04-15' ) ;
        $window = [ 'month_of_year' => 2, 'day_of_month' => 15 ] ;
        $this->assertFalse( $this->callPrivate( 'isQuarterlyMatch', [ $window, $now ] ) ) ;
    }

    // ========================================================================
    // isAnnuallyMatch() - month_of_year (1-12 actual month), day_of_month
    // ========================================================================

    public function testIsAnnuallyMatchExactMonthAndDay() : void
    {
        $now = new \DateTime( '2024-04-15' ) ;
        $window = [ 'month_of_year' => 4, 'day_of_month' => 15 ] ;
        $this->assertTrue( $this->callPrivate( 'isAnnuallyMatch', [ $window, $now ] ) ) ;
    }

    public function testIsAnnuallyMatchWrongMonth() : void
    {
        $now = new \DateTime( '2024-04-15' ) ;
        $window = [ 'month_of_year' => 5, 'day_of_month' => 15 ] ;
        $this->assertFalse( $this->callPrivate( 'isAnnuallyMatch', [ $window, $now ] ) ) ;
    }

    // ========================================================================
    // isPeriodicMatch() - every N days from period_start_date
    // ========================================================================

    public function testIsPeriodicMatchOnCycleDay() : void
    {
        // Period starts 2024-01-01, every 7 days. 2024-01-15 is day 14 (divisible by 7).
        $now = new \DateTime( '2024-01-15' ) ;
        $window = [ 'period_start_date' => '2024-01-01', 'period_days' => 7 ] ;
        $this->assertTrue( $this->callPrivate( 'isPeriodicMatch', [ $window, $now ] ) ) ;
    }

    public function testIsPeriodicMatchOffCycleDay() : void
    {
        // Day 13 is not divisible by 7
        $now = new \DateTime( '2024-01-14' ) ;
        $window = [ 'period_start_date' => '2024-01-01', 'period_days' => 7 ] ;
        $this->assertFalse( $this->callPrivate( 'isPeriodicMatch', [ $window, $now ] ) ) ;
    }

    public function testIsPeriodicMatchOnStartDate() : void
    {
        // Day 0 is divisible by anything
        $now = new \DateTime( '2024-01-01' ) ;
        $window = [ 'period_start_date' => '2024-01-01', 'period_days' => 7 ] ;
        $this->assertTrue( $this->callPrivate( 'isPeriodicMatch', [ $window, $now ] ) ) ;
    }

    // ========================================================================
    // isAdhocWindowActive() - silence_until > now
    // ========================================================================

    public function testIsAdhocWindowActiveFutureSilenceUntil() : void
    {
        $window = [ 'silence_until' => date( 'Y-m-d H:i:s', time() + 3600 ) ] ;
        $this->assertTrue( $this->callPrivate( 'isAdhocWindowActive', [ $window ] ) ) ;
    }

    public function testIsAdhocWindowActivePastSilenceUntil() : void
    {
        $window = [ 'silence_until' => date( 'Y-m-d H:i:s', time() - 3600 ) ] ;
        $this->assertFalse( $this->callPrivate( 'isAdhocWindowActive', [ $window ] ) ) ;
    }

    public function testIsAdhocWindowActiveEmptySilenceUntil() : void
    {
        $window = [ 'silence_until' => '' ] ;
        $this->assertFalse( $this->callPrivate( 'isAdhocWindowActive', [ $window ] ) ) ;
    }

    // ========================================================================
    // isScheduleMatchingToday() - dispatcher
    // ========================================================================

    public function testIsScheduleMatchingTodayUnknownTypeReturnsFalse() : void
    {
        $now = new \DateTime( '2024-01-15' ) ;
        $window = [ 'schedule_type' => 'never_heard_of_this' ] ;
        $this->assertFalse( $this->callPrivate( 'isScheduleMatchingToday', [ $window, $now ] ) ) ;
    }

    public function testIsScheduleMatchingTodayDispatchesToWeekly() : void
    {
        // 2024-01-17 was a Wednesday
        $now = new \DateTime( '2024-01-17' ) ;
        $window = [ 'schedule_type' => 'weekly', 'days_of_week' => 'Wed' ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduleMatchingToday', [ $window, $now ] ) ) ;
    }

    public function testIsScheduleMatchingTodayDispatchesToMonthly() : void
    {
        $now = new \DateTime( '2024-01-15' ) ;
        $window = [ 'schedule_type' => 'monthly', 'day_of_month' => 15 ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduleMatchingToday', [ $window, $now ] ) ) ;
    }
}
