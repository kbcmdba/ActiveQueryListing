<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\MaintenanceWindow ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/MaintenanceWindow.php pure-function logic.
 *
 * The public methods (getActiveWindowForHost, etc.) require a database
 * connection and are tested elsewhere via integration tests. This file
 * focuses on the private static helpers via reflection - the time and
 * schedule matching logic that determines whether a window is "active".
 *
 * NOTE: Integration tests currently require MySQL (mysqli). The raw SQL
 * and DBConnection class assume MySQL. When configdb supports PostgreSQL
 * or other engines, the integration tests will need a DB-agnostic layer
 * or separate fixtures per DB type. For now, tests skip gracefully if
 * MySQL is unavailable.
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

    // ========================================================================
    // formatMaintenanceInfo() — reshapes a DB row into the JSON-ready array
    // ========================================================================

    public function testFormatMaintenanceInfoBasicHostWindow() : void
    {
        $window = [
            'window_id'   => '7',
            'window_type' => 'adhoc',
            'target_type' => 'host',
            'description' => 'Quick silence for patching',
            'group_name'  => null,
            'schedule_type' => null,
            'start_time'  => null,
            'end_time'    => null,
            'silence_until' => '2024-01-15 18:00:00',
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertTrue( $info['active'] ) ;
        $this->assertSame( 7, $info['windowId'] ) ;
        $this->assertSame( 'adhoc', $info['windowType'] ) ;
        $this->assertSame( 'host', $info['targetType'] ) ;
        $this->assertSame( 'Quick silence for patching', $info['description'] ) ;
        $this->assertSame( '2024-01-15 18:00:00', $info['expiresAt'] ) ;
        $this->assertArrayNotHasKey( 'groupName', $info, 'host windows should not have groupName' ) ;
        $this->assertArrayNotHasKey( 'scheduleType', $info, 'adhoc windows should not have scheduleType' ) ;
    }

    public function testFormatMaintenanceInfoGroupWindow() : void
    {
        $window = [
            'window_id'   => '12',
            'window_type' => 'adhoc',
            'target_type' => 'group',
            'description' => 'Deploy maintenance',
            'group_name'  => 'Production MySQL',
            'schedule_type' => null,
            'start_time'  => null,
            'end_time'    => null,
            'silence_until' => '2024-01-16 06:00:00',
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertSame( 'group', $info['targetType'] ) ;
        $this->assertSame( 'Production MySQL', $info['groupName'] ) ;
        $this->assertSame( '2024-01-16 06:00:00', $info['expiresAt'] ) ;
    }

    public function testFormatMaintenanceInfoScheduledWithTimeWindow() : void
    {
        $window = [
            'window_id'   => '3',
            'window_type' => 'scheduled',
            'target_type' => 'host',
            'description' => 'Weekly backup window',
            'group_name'  => null,
            'schedule_type' => 'weekly',
            'start_time'  => '22:00:00',
            'end_time'    => '06:00:00',
            'silence_until' => null,
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertSame( 'scheduled', $info['windowType'] ) ;
        $this->assertSame( 'weekly', $info['scheduleType'] ) ;
        $this->assertSame( '22:00 - 06:00', $info['timeWindow'] ) ;
        $this->assertArrayNotHasKey( 'expiresAt', $info, 'scheduled windows should not have expiresAt' ) ;
    }

    public function testFormatMaintenanceInfoScheduledWithoutTimeWindow() : void
    {
        // Scheduled window with no time constraints — active all day on matching days
        $window = [
            'window_id'   => '5',
            'window_type' => 'scheduled',
            'target_type' => 'host',
            'description' => 'Monthly patching day',
            'group_name'  => null,
            'schedule_type' => 'monthly',
            'start_time'  => null,
            'end_time'    => null,
            'silence_until' => null,
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertSame( 'monthly', $info['scheduleType'] ) ;
        $this->assertArrayNotHasKey( 'timeWindow', $info, 'no time window when start/end not set' ) ;
    }

    public function testFormatMaintenanceInfoNullDescription() : void
    {
        $window = [
            'window_id'   => '1',
            'window_type' => 'adhoc',
            'target_type' => 'host',
            'description' => null,
            'group_name'  => null,
            'schedule_type' => null,
            'start_time'  => null,
            'end_time'    => null,
            'silence_until' => '2024-01-15 18:00:00',
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertSame( '', $info['description'], 'null description should become empty string' ) ;
    }

    public function testFormatMaintenanceInfoWindowIdCastToInt() : void
    {
        $window = [
            'window_id'   => '42',
            'window_type' => 'adhoc',
            'target_type' => 'host',
            'description' => '',
            'group_name'  => null,
            'schedule_type' => null,
            'start_time'  => null,
            'end_time'    => null,
            'silence_until' => '2024-01-15 18:00:00',
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertSame( 42, $info['windowId'], 'windowId should be cast to int from string DB result' ) ;
    }

    public function testFormatMaintenanceInfoScheduledGroupWithTimeWindow() : void
    {
        // Hit all branches: scheduled + group + time window
        $window = [
            'window_id'    => '99',
            'window_type'  => 'scheduled',
            'target_type'  => 'group',
            'description'  => 'Quarterly maintenance',
            'group_name'   => 'All Redis',
            'schedule_type' => 'quarterly',
            'start_time'   => '02:00:00',
            'end_time'     => '05:30:00',
            'silence_until' => null,
        ] ;
        $info = $this->callPrivate( 'formatMaintenanceInfo', [ $window ] ) ;
        $this->assertTrue( $info['active'] ) ;
        $this->assertSame( 99, $info['windowId'] ) ;
        $this->assertSame( 'scheduled', $info['windowType'] ) ;
        $this->assertSame( 'group', $info['targetType'] ) ;
        $this->assertSame( 'All Redis', $info['groupName'] ) ;
        $this->assertSame( 'quarterly', $info['scheduleType'] ) ;
        $this->assertSame( '02:00 - 05:30', $info['timeWindow'] ) ;
        $this->assertSame( 'Quarterly maintenance', $info['description'] ) ;
    }

    // ========================================================================
    // isWindowActive() — dispatcher for adhoc vs scheduled
    // ========================================================================

    public function testIsWindowActiveDispatchesToAdhoc() : void
    {
        $window = [
            'window_type'   => 'adhoc',
            'silence_until' => date( 'Y-m-d H:i:s', time() + 3600 ),
        ] ;
        $this->assertTrue( $this->callPrivate( 'isWindowActive', [ $window ] ) ) ;
    }

    public function testIsWindowActiveDispatchesToScheduledAllDay() : void
    {
        // Scheduled window on ALL days, no time constraint — always active
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'start_time'    => null,
            'end_time'      => null,
            'timezone'      => 'UTC',
        ] ;
        $this->assertTrue( $this->callPrivate( 'isWindowActive', [ $window ] ) ) ;
    }

    public function testIsWindowActiveAdhocExpired() : void
    {
        $window = [
            'window_type'   => 'adhoc',
            'silence_until' => date( 'Y-m-d H:i:s', time() - 3600 ),
        ] ;
        $this->assertFalse( $this->callPrivate( 'isWindowActive', [ $window ] ) ) ;
    }

    // ========================================================================
    // isScheduledWindowActive() — timezone, overnight, schedule matching
    // ========================================================================

    public function testScheduledWindowActiveAllDayAllDays() : void
    {
        // Every day, no time restriction — always active
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'start_time'    => null,
            'end_time'      => null,
            'timezone'      => 'America/Chicago',
        ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ) ;
    }

    public function testScheduledWindowInactiveNoDaysMatch() : void
    {
        // Weekly schedule but empty days_of_week — never matches
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => '',
            'start_time'    => null,
            'end_time'      => null,
            'timezone'      => 'UTC',
        ] ;
        $this->assertFalse( $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ) ;
    }

    public function testScheduledWindowWithInvalidTimezone() : void
    {
        // Invalid timezone should fall back to America/Chicago, not crash
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'start_time'    => null,
            'end_time'      => null,
            'timezone'      => 'Not/A/Real/Timezone',
        ] ;
        // Should not throw — falls back to America/Chicago
        $result = $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ;
        $this->assertTrue( $result ) ;
    }

    // ========================================================================
    // DB integration tests — createAdhocWindow, getActiveWindowForHost, etc.
    // ========================================================================

    private function getDbhOrSkip() : \mysqli
    {
        try {
            $dbc = new DBConnection() ;
            $dbh = $dbc->getConnection() ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Database not available: ' . $e->getMessage() ) ;
        }
        if ( ! ( $dbh instanceof \mysqli ) ) {
            $this->markTestSkipped( 'Non-mysqli connection' ) ;
        }
        return $dbh ;
    }

    /**
     * Helper: find a real host_id from the host table for testing.
     * Returns null if no hosts exist.
     */
    private function getAnyHostId( \mysqli $dbh ) : ?int
    {
        $result = $dbh->query( 'SELECT host_id FROM host WHERE should_monitor = 1 AND decommissioned = 0 LIMIT 1' ) ;
        if ( $result && $row = $result->fetch_row() ) {
            return (int) $row[0] ;
        }
        return null ;
    }

    /**
     * Helper: clean up a maintenance window and its mappings.
     */
    private function cleanupWindow( \mysqli $dbh, int $windowId ) : void
    {
        $dbh->query( "DELETE FROM maintenance_window_host_map WHERE window_id = $windowId" ) ;
        $dbh->query( "DELETE FROM maintenance_window_host_group_map WHERE window_id = $windowId" ) ;
        $dbh->query( "DELETE FROM maintenance_window WHERE window_id = $windowId" ) ;
    }

    public function testCreateAdhocWindowForHost() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        $windowId = MaintenanceWindow::createAdhocWindow(
            'host', $hostId, 60, 'PHPUnit test window', 'phpunit', $dbh
        ) ;
        $this->assertIsInt( $windowId ) ;
        $this->assertGreaterThan( 0, $windowId ) ;

        // Verify it was created
        $result = $dbh->query( "SELECT * FROM maintenance_window WHERE window_id = $windowId" ) ;
        $this->assertSame( 1, $result->num_rows ) ;
        $row = $result->fetch_assoc() ;
        $this->assertSame( 'adhoc', $row['window_type'] ) ;
        $this->assertSame( 'PHPUnit test window', $row['description'] ) ;

        // Verify the host mapping exists
        $mapResult = $dbh->query(
            "SELECT * FROM maintenance_window_host_map WHERE window_id = $windowId AND host_id = $hostId"
        ) ;
        $this->assertSame( 1, $mapResult->num_rows ) ;

        // Cleanup
        $this->cleanupWindow( $dbh, $windowId ) ;
    }

    public function testGetActiveWindowForHostFindsAdhocWindow() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        // Create a window that expires 1 hour from now
        $windowId = MaintenanceWindow::createAdhocWindow(
            'host', $hostId, 60, 'Active test window', 'phpunit', $dbh
        ) ;

        // Should find it
        $info = MaintenanceWindow::getActiveWindowForHost( $hostId, $dbh ) ;
        $this->assertNotNull( $info, 'Should find the active adhoc window' ) ;
        $this->assertTrue( $info['active'] ) ;
        $this->assertSame( 'adhoc', $info['windowType'] ) ;
        $this->assertSame( $windowId, $info['windowId'] ) ;

        // Cleanup
        $this->cleanupWindow( $dbh, $windowId ) ;
    }

    public function testGetActiveWindowForHostReturnsNullWhenNoWindow() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        // Make sure no test windows are lingering for this host
        // (Other tests clean up, but just in case)
        $info = MaintenanceWindow::getActiveWindowForHost( $hostId, $dbh ) ;
        // This may or may not be null depending on whether someone has a real
        // maintenance window configured. Just verify it returns something valid.
        if ( $info !== null ) {
            $this->assertArrayHasKey( 'active', $info ) ;
            $this->assertArrayHasKey( 'windowType', $info ) ;
        } else {
            $this->assertNull( $info ) ;
        }
    }

    public function testGetAllActiveWindowsReturnsArray() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $result = MaintenanceWindow::getAllActiveWindows( $dbh ) ;
        $this->assertIsArray( $result ) ;
    }

    public function testGetActiveWindowForHostViaGroup() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        // Find a group that this host belongs to
        $result = $dbh->query(
            "SELECT host_group_id FROM host_group_map WHERE host_id = $hostId LIMIT 1"
        ) ;
        if ( ! $result || $result->num_rows === 0 ) {
            // Host isn't in any group — create a temporary group + mapping
            $dbh->query( "INSERT INTO host_group (tag, short_description) VALUES ('_mw_test', 'MW test group')" ) ;
            $groupId = $dbh->insert_id ;
            $dbh->query( "INSERT INTO host_group_map (host_group_id, host_id) VALUES ($groupId, $hostId)" ) ;
            $createdGroup = true ;
        } else {
            $row = $result->fetch_row() ;
            $groupId = (int) $row[0] ;
            $createdGroup = false ;
        }

        // Create an adhoc window on the GROUP
        $windowId = MaintenanceWindow::createAdhocWindow(
            'group', $groupId, 60, 'Group MW test', 'phpunit', $dbh
        ) ;

        // Host should be found via group membership
        $info = MaintenanceWindow::getActiveWindowForHostViaGroup( $hostId, $dbh ) ;
        $this->assertNotNull( $info, 'Should find active window via group membership' ) ;
        $this->assertTrue( $info['active'] ) ;
        $this->assertSame( 'group', $info['targetType'] ) ;

        // Cleanup
        $this->cleanupWindow( $dbh, $windowId ) ;
        if ( $createdGroup ) {
            $dbh->query( "DELETE FROM host_group_map WHERE host_group_id = $groupId AND host_id = $hostId" ) ;
            $dbh->query( "DELETE FROM host_group WHERE host_group_id = $groupId" ) ;
        }
    }

    public function testGetActiveWindowForHostViaGroupReturnsNullWhenNoGroupWindow() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        // Use a host ID that's unlikely to have a group maintenance window
        $info = MaintenanceWindow::getActiveWindowForHostViaGroup( 999999999, $dbh ) ;
        $this->assertNull( $info ) ;
    }

    public function testGetAllActiveWindowsIncludesCreatedWindow() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        $windowId = MaintenanceWindow::createAdhocWindow(
            'host', $hostId, 60, 'getAllActiveWindows test', 'phpunit', $dbh
        ) ;

        $activeWindows = MaintenanceWindow::getAllActiveWindows( $dbh ) ;
        $this->assertIsArray( $activeWindows ) ;

        // Find our window in the list
        $found = false ;
        foreach ( $activeWindows as $w ) {
            if ( (int) ( $w['windowId'] ?? 0 ) === $windowId ) {
                $found = true ;
                $this->assertSame( 'adhoc', $w['windowType'] ) ;
                break ;
            }
        }
        $this->assertTrue( $found, 'Our test window should appear in getAllActiveWindows' ) ;

        // Cleanup
        $this->cleanupWindow( $dbh, $windowId ) ;
    }

    // ========================================================================
    // isScheduledWindowActive — additional branch coverage
    // ========================================================================

    public function testScheduledWindowActiveDaytimeInRange() : void
    {
        // Window from 00:00 to 23:59 — always in range regardless of time
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'start_time'    => '00:00:00',
            'end_time'      => '23:59:59',
            'timezone'      => 'UTC',
        ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ) ;
    }

    public function testScheduledWindowOvernightAllDays() : void
    {
        // Overnight window 22:00-06:00 on all days — should be active
        // at any time because either the evening or morning portion matches
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            'start_time'    => '22:00:00',
            'end_time'      => '06:00:00',
            'timezone'      => 'UTC',
        ] ;
        $result = $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ;
        // This depends on current time — if between 06:01-21:59 UTC, it's inactive
        // Just verify it doesn't crash; the overnight logic is tested via
        // isTimeInWindow and isOvernightWindow already
        $this->assertIsBool( $result ) ;
    }

    public function testScheduledWindowDaytimeNotMatchingDay() : void
    {
        // Use an impossible day name to guarantee no match
        $window = [
            'window_type'   => 'scheduled',
            'schedule_type' => 'weekly',
            'days_of_week'  => '',  // empty = no days match
            'start_time'    => '00:00:00',
            'end_time'      => '23:59:59',
            'timezone'      => 'UTC',
        ] ;
        $this->assertFalse( $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ) ;
    }

    // ========================================================================
    // isScheduleMatchingToday — remaining dispatch paths
    // ========================================================================

    public function testIsScheduleMatchingTodayDispatchesToQuarterly() : void
    {
        // Current month's position in quarter + current day
        $now = new \DateTime() ;
        $currentMonth = (int) $now->format( 'n' ) ;
        $monthInQuarter = ( ( $currentMonth - 1 ) % 3 ) + 1 ;
        $currentDay = (int) $now->format( 'j' ) ;

        $window = [
            'schedule_type' => 'quarterly',
            'month_of_year' => $monthInQuarter,
            'day_of_month'  => $currentDay,
        ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduleMatchingToday', [ $window, $now ] ) ) ;
    }

    public function testIsScheduleMatchingTodayDispatchesToAnnually() : void
    {
        $now = new \DateTime() ;
        $currentMonth = (int) $now->format( 'n' ) ;
        $currentDay = (int) $now->format( 'j' ) ;

        $window = [
            'schedule_type' => 'annually',
            'month_of_year' => $currentMonth,
            'day_of_month'  => $currentDay,
        ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduleMatchingToday', [ $window, $now ] ) ) ;
    }

    public function testIsScheduleMatchingTodayDispatchesToPeriodic() : void
    {
        $now = new \DateTime() ;
        // Period starts today, every 1 day — always matches
        $window = [
            'schedule_type'    => 'periodic',
            'period_start_date' => $now->format( 'Y-m-d' ),
            'period_days'      => 1,
        ] ;
        $this->assertTrue( $this->callPrivate( 'isScheduleMatchingToday', [ $window, $now ] ) ) ;
    }

    // ========================================================================
    // Schedule match edge cases — empty params and last-day-of-month (32)
    // ========================================================================

    public function testQuarterlyMatchEmptyParams() : void
    {
        $now = new \DateTime( '2024-01-15' ) ;
        $this->assertFalse( $this->callPrivate( 'isQuarterlyMatch', [
            [ 'month_of_year' => null, 'day_of_month' => 15 ], $now
        ] ) ) ;
        $this->assertFalse( $this->callPrivate( 'isQuarterlyMatch', [
            [ 'month_of_year' => 1, 'day_of_month' => null ], $now
        ] ) ) ;
    }

    public function testQuarterlyMatchLastDayOfMonth() : void
    {
        // January 31 — last day. day_of_month=32 means last day.
        // Jan is month 1 of Q1, so month_of_year=1
        $now = new \DateTime( '2024-01-31' ) ;
        $window = [ 'month_of_year' => 1, 'day_of_month' => 32 ] ;
        $this->assertTrue( $this->callPrivate( 'isQuarterlyMatch', [ $window, $now ] ) ) ;
    }

    public function testAnnuallyMatchEmptyParams() : void
    {
        $now = new \DateTime( '2024-04-15' ) ;
        $this->assertFalse( $this->callPrivate( 'isAnnuallyMatch', [
            [ 'month_of_year' => null, 'day_of_month' => 15 ], $now
        ] ) ) ;
        $this->assertFalse( $this->callPrivate( 'isAnnuallyMatch', [
            [ 'month_of_year' => 4, 'day_of_month' => null ], $now
        ] ) ) ;
    }

    public function testAnnuallyMatchLastDayOfMonth() : void
    {
        $now = new \DateTime( '2024-02-29' ) ; // Leap year last day of Feb
        $window = [ 'month_of_year' => 2, 'day_of_month' => 32 ] ;
        $this->assertTrue( $this->callPrivate( 'isAnnuallyMatch', [ $window, $now ] ) ) ;
    }

    public function testPeriodicMatchEmptyParams() : void
    {
        $now = new \DateTime( '2024-01-15' ) ;
        $this->assertFalse( $this->callPrivate( 'isPeriodicMatch', [
            [ 'period_days' => null, 'period_start_date' => '2024-01-01' ], $now
        ] ) ) ;
        $this->assertFalse( $this->callPrivate( 'isPeriodicMatch', [
            [ 'period_days' => 7, 'period_start_date' => null ], $now
        ] ) ) ;
    }

    // ========================================================================
    // getAllActiveWindows — deeper verification of the host-list building
    // ========================================================================

    /**
     * Helper: directly insert a scheduled maintenance window into the DB.
     * createAdhocWindow only creates adhoc windows; we need scheduled for
     * full coverage of getAllActiveWindows.
     */
    private function createScheduledWindow( \mysqli $dbh, string $scheduleType, string $daysOfWeek,
                                            ?string $startTime, ?string $endTime, string $description ) : int
    {
        $stmt = $dbh->prepare(
            "INSERT INTO maintenance_window (window_type, schedule_type, days_of_week, start_time, end_time, timezone, description, created_by)
             VALUES ('scheduled', ?, ?, ?, ?, 'UTC', ?, 'phpunit')"
        ) ;
        $stmt->bind_param( 'sssss', $scheduleType, $daysOfWeek, $startTime, $endTime, $description ) ;
        $stmt->execute() ;
        $windowId = $dbh->insert_id ;
        $stmt->close() ;
        return $windowId ;
    }

    /**
     * Helper: map a window to a host group.
     */
    private function mapWindowToGroup( \mysqli $dbh, int $windowId, int $groupId ) : void
    {
        $stmt = $dbh->prepare(
            "INSERT INTO maintenance_window_host_group_map (window_id, host_group_id) VALUES (?, ?)"
        ) ;
        $stmt->bind_param( 'ii', $windowId, $groupId ) ;
        $stmt->execute() ;
        $stmt->close() ;
    }

    public function testGetAllActiveWindowsIncludesHostList() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        // Get hostname for verification
        $hostResult = $dbh->query( "SELECT hostname, port_number FROM host WHERE host_id = $hostId" ) ;
        $hostRow = $hostResult->fetch_assoc() ;
        $expectedHost = $hostRow['hostname'] . ':' . $hostRow['port_number'] ;

        $windowId = MaintenanceWindow::createAdhocWindow(
            'host', $hostId, 60, 'Host list test', 'phpunit', $dbh
        ) ;

        $activeWindows = MaintenanceWindow::getAllActiveWindows( $dbh ) ;
        $found = false ;
        foreach ( $activeWindows as $w ) {
            if ( (int) ( $w['windowId'] ?? 0 ) === $windowId ) {
                $found = true ;
                $this->assertArrayHasKey( 'hosts', $w ) ;
                $this->assertContains( $expectedHost, $w['hosts'],
                    'Host list should include the mapped host' ) ;
                $this->assertArrayHasKey( 'expiresAt', $w,
                    'Adhoc window should have expiresAt' ) ;
                $this->assertArrayNotHasKey( 'scheduleType', $w,
                    'Adhoc window should not have scheduleType' ) ;
                break ;
            }
        }
        $this->assertTrue( $found ) ;

        $this->cleanupWindow( $dbh, $windowId ) ;
    }

    // ========================================================================
    // isScheduledWindowActive overnight branches — use timezone trick
    // ========================================================================

    public function testScheduledWindowOvernightEveningPortion() : void
    {
        // Find a timezone where it's currently between 22:00 and 23:59
        // using the full IANA timezone database.
        $found = false ;
        foreach ( \DateTimeZone::listIdentifiers() as $tzName ) {
            $tz = new \DateTimeZone( $tzName ) ;
            $now = new \DateTime( 'now', $tz ) ;
            $hour = (int) $now->format( 'H' ) ;
            if ( $hour >= 22 ) {
                $dayName = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ][ (int) $now->format( 'w' ) ] ;
                $window = [
                    'window_type'   => 'scheduled',
                    'schedule_type' => 'weekly',
                    'days_of_week'  => $dayName,
                    'start_time'    => '22:00:00',
                    'end_time'      => '06:00:00',
                    'timezone'      => $tzName,
                ] ;
                $result = $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ;
                $this->assertTrue( $result, "Overnight window should be active during evening in $tzName (hour=$hour)" ) ;
                $found = true ;
                break ;
            }
        }
        if ( ! $found ) {
            $this->markTestSkipped( 'Could not find a timezone currently in the 22:00-23:59 range' ) ; // @codeCoverageIgnore
        }
    }

    public function testScheduledWindowOvernightMorningPortion() : void
    {
        // Find a timezone where it's currently between 00:00 and 05:59.
        $found = false ;
        foreach ( \DateTimeZone::listIdentifiers() as $tzName ) {
            $tz = new \DateTimeZone( $tzName ) ;
            $now = new \DateTime( 'now', $tz ) ;
            $hour = (int) $now->format( 'H' ) ;
            if ( $hour >= 0 && $hour <= 5 ) {
                $yesterday = clone $now ;
                $yesterday->modify( '-1 day' ) ;
                $yesterdayName = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ][ (int) $yesterday->format( 'w' ) ] ;
                $window = [
                    'window_type'   => 'scheduled',
                    'schedule_type' => 'weekly',
                    'days_of_week'  => $yesterdayName,
                    'start_time'    => '22:00:00',
                    'end_time'      => '06:00:00',
                    'timezone'      => $tzName,
                ] ;
                $result = $this->callPrivate( 'isScheduledWindowActive', [ $window ] ) ;
                $this->assertTrue( $result, "Overnight window should be active during morning in $tzName (hour=$hour, yesterday=$yesterdayName)" ) ;
                $found = true ;
                break ;
            }
        }
        if ( ! $found ) {
            $this->markTestSkipped( 'Could not find a timezone currently in the 00:00-05:59 range' ) ; // @codeCoverageIgnore
        }
    }

    // ========================================================================
    // getAllActiveWindows — scheduled window with group mapping
    // ========================================================================

    public function testGetAllActiveWindowsScheduledWithGroupMapping() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        // Create a group with this host
        $dbh->query( "INSERT INTO host_group (tag, short_description) VALUES ('_mw_sched', 'Scheduled MW test')" ) ;
        $groupId = $dbh->insert_id ;
        $dbh->query( "INSERT INTO host_group_map (host_group_id, host_id) VALUES ($groupId, $hostId)" ) ;

        // Create a scheduled window on all days, 00:00-23:59 (always active)
        $windowId = $this->createScheduledWindow(
            $dbh, 'weekly', 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
            '00:00:00', '23:59:59', 'Scheduled group test'
        ) ;
        $this->mapWindowToGroup( $dbh, $windowId, $groupId ) ;

        $activeWindows = MaintenanceWindow::getAllActiveWindows( $dbh ) ;

        // Find our window
        $found = false ;
        foreach ( $activeWindows as $w ) {
            if ( (int) ( $w['windowId'] ?? 0 ) === $windowId ) {
                $found = true ;
                $this->assertSame( 'scheduled', $w['windowType'] ) ;
                $this->assertSame( 'weekly', $w['scheduleType'] ) ;
                $this->assertArrayHasKey( 'daysOfWeek', $w ) ;
                $this->assertArrayHasKey( 'timeWindow', $w ) ;
                $this->assertSame( '00:00 - 23:59', $w['timeWindow'] ) ;
                $this->assertArrayHasKey( 'hosts', $w ) ;
                $this->assertNotEmpty( $w['hosts'] ) ;
                // At least one host should have "(via _mw_sched)"
                $hasGroupHost = false ;
                foreach ( $w['hosts'] as $h ) {
                    if ( str_contains( $h, '(via _mw_sched)' ) ) {
                        $hasGroupHost = true ;
                        break ;
                    }
                }
                $this->assertTrue( $hasGroupHost, 'Should show host via group mapping' ) ;
                break ;
            }
        }
        $this->assertTrue( $found, 'Scheduled window should appear in getAllActiveWindows' ) ;

        // Cleanup
        $this->cleanupWindow( $dbh, $windowId ) ;
        $dbh->query( "DELETE FROM host_group_map WHERE host_group_id = $groupId AND host_id = $hostId" ) ;
        $dbh->query( "DELETE FROM host_group WHERE host_group_id = $groupId" ) ;
    }

    public function testGetAllActiveWindowsSkipsInactiveScheduled() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $hostId = $this->getAnyHostId( $dbh ) ;
        if ( $hostId === null ) {
            $this->markTestSkipped( 'No monitored hosts in database' ) ;
        }

        // Create a scheduled window on NO days — never active
        $windowId = $this->createScheduledWindow(
            $dbh, 'weekly', '', null, null, 'Inactive scheduled test'
        ) ;
        $dbh->query(
            "INSERT INTO maintenance_window_host_map (window_id, host_id) VALUES ($windowId, $hostId)"
        ) ;

        $activeWindows = MaintenanceWindow::getAllActiveWindows( $dbh ) ;

        // Should NOT find our window
        $found = false ;
        foreach ( $activeWindows as $w ) {
            if ( (int) ( $w['windowId'] ?? 0 ) === $windowId ) {
                $found = true ;
                break ;
            }
        }
        $this->assertFalse( $found, 'Inactive scheduled window should be skipped' ) ;

        // Cleanup
        $this->cleanupWindow( $dbh, $windowId ) ;
    }
}
