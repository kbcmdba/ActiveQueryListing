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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\aql\Libs ;

/**
 * MaintenanceWindow - Utility class for checking and creating maintenance windows
 *
 * Provides static methods to:
 * - Check if a host is currently in an active maintenance window
 * - Check if a host is in maintenance via group membership
 * - Create ad-hoc maintenance windows for quick silencing
 */
class MaintenanceWindow
{
    /**
     * Check if a host is in an active maintenance window (direct mapping)
     *
     * @param int $hostId Host ID from aql_db.host table
     * @param \mysqli $dbh Database connection
     * @return array|null Maintenance info array or null if not in maintenance
     */
    public static function getActiveWindowForHost( $hostId, $dbh )
    {
        $sql = "SELECT mw.window_id
                     , mw.window_type
                     , mw.schedule_type
                     , mw.days_of_week
                     , mw.day_of_month
                     , mw.month_of_year
                     , mw.period_days
                     , mw.period_start_date
                     , mw.start_time
                     , mw.end_time
                     , mw.timezone
                     , mw.silence_until
                     , mw.description
                     , 'host' AS target_type
                     , NULL AS group_name
                  FROM aql_db.maintenance_window mw
                  JOIN aql_db.maintenance_window_host_map mwhm ON mwhm.window_id = mw.window_id
                 WHERE mwhm.host_id = ?" ;

        $stmt = $dbh->prepare( $sql ) ;
        if ( ! $stmt ) {
            return null ;
        }
        $stmt->bind_param( 'i', $hostId ) ;
        $stmt->execute() ;
        $result = $stmt->get_result() ;

        while ( $row = $result->fetch_assoc() ) {
            if ( self::isWindowActive( $row ) ) {
                $stmt->close() ;
                return self::formatMaintenanceInfo( $row ) ;
            }
        }

        $stmt->close() ;
        return null ;
    }

    /**
     * Check if a host is in maintenance via group membership
     *
     * @param int $hostId Host ID from aql_db.host table
     * @param \mysqli $dbh Database connection
     * @return array|null Maintenance info array or null if not in maintenance
     */
    public static function getActiveWindowForHostViaGroup( $hostId, $dbh )
    {
        $sql = "SELECT mw.window_id
                     , mw.window_type
                     , mw.schedule_type
                     , mw.days_of_week
                     , mw.day_of_month
                     , mw.month_of_year
                     , mw.period_days
                     , mw.period_start_date
                     , mw.start_time
                     , mw.end_time
                     , mw.timezone
                     , mw.silence_until
                     , mw.description
                     , 'group' AS target_type
                     , hg.tag AS group_name
                  FROM aql_db.maintenance_window mw
                  JOIN aql_db.maintenance_window_host_group_map mwgm ON mwgm.window_id = mw.window_id
                  JOIN aql_db.host_group hg ON hg.host_group_id = mwgm.host_group_id
                  JOIN aql_db.host_group_map hgmap ON hgmap.host_group_id = hg.host_group_id
                 WHERE hgmap.host_id = ?" ;

        $stmt = $dbh->prepare( $sql ) ;
        if ( ! $stmt ) {
            return null ;
        }
        $stmt->bind_param( 'i', $hostId ) ;
        $stmt->execute() ;
        $result = $stmt->get_result() ;

        while ( $row = $result->fetch_assoc() ) {
            if ( self::isWindowActive( $row ) ) {
                $stmt->close() ;
                return self::formatMaintenanceInfo( $row ) ;
            }
        }

        $stmt->close() ;
        return null ;
    }

    /**
     * Create an ad-hoc maintenance window for quick silencing
     *
     * @param string $targetType 'host' or 'group'
     * @param int $targetId Host ID or Host Group ID
     * @param int $durationMinutes Duration in minutes
     * @param string $description Description/reason for the window
     * @param string $createdBy Username creating the window
     * @param \mysqli $dbh Database connection
     * @return int Window ID of created window
     * @throws \Exception on failure
     */
    public static function createAdhocWindow( $targetType, $targetId, $durationMinutes, $description, $createdBy, $dbh )
    {
        // Calculate silence_until
        $silenceUntil = date( 'Y-m-d H:i:s', time() + ( $durationMinutes * 60 ) ) ;

        // Insert the maintenance window
        $sql = "INSERT INTO aql_db.maintenance_window
                (window_type, silence_until, description, created_by)
                VALUES ('adhoc', ?, ?, ?)" ;

        $stmt = $dbh->prepare( $sql ) ;
        if ( ! $stmt ) {
            throw new \Exception( "Failed to prepare statement: " . $dbh->error ) ;
        }
        $stmt->bind_param( 'sss', $silenceUntil, $description, $createdBy ) ;

        if ( ! $stmt->execute() ) {
            $stmt->close() ;
            throw new \Exception( "Failed to create maintenance window: " . $stmt->error ) ;
        }

        $windowId = $dbh->insert_id ;
        $stmt->close() ;

        // Map to host or group
        if ( $targetType === 'host' ) {
            $mapSql = "INSERT INTO aql_db.maintenance_window_host_map (window_id, host_id) VALUES (?, ?)" ;
        } else {
            $mapSql = "INSERT INTO aql_db.maintenance_window_host_group_map (window_id, host_group_id) VALUES (?, ?)" ;
        }

        $mapStmt = $dbh->prepare( $mapSql ) ;
        if ( ! $mapStmt ) {
            throw new \Exception( "Failed to prepare mapping statement: " . $dbh->error ) ;
        }
        $mapStmt->bind_param( 'ii', $windowId, $targetId ) ;

        if ( ! $mapStmt->execute() ) {
            $mapStmt->close() ;
            throw new \Exception( "Failed to create window mapping: " . $mapStmt->error ) ;
        }

        $mapStmt->close() ;
        return $windowId ;
    }

    /**
     * Check if a window is currently active
     *
     * @param array $window Row from maintenance_window table
     * @return bool True if window is active
     */
    private static function isWindowActive( $window )
    {
        if ( $window['window_type'] === 'adhoc' ) {
            return self::isAdhocWindowActive( $window ) ;
        }
        return self::isScheduledWindowActive( $window ) ;
    }

    /**
     * Check if an ad-hoc window is active (silence_until > now)
     *
     * @param array $window Row from maintenance_window table
     * @return bool True if active
     */
    private static function isAdhocWindowActive( $window )
    {
        if ( empty( $window['silence_until'] ) ) {
            return false ;
        }
        $silenceUntil = strtotime( $window['silence_until'] ) ;
        return ( $silenceUntil > time() ) ;
    }

    /**
     * Check if a scheduled window is currently active
     * Handles overnight spans correctly (e.g., 22:00-06:00)
     *
     * @param array $window Row from maintenance_window table
     * @return bool True if active
     */
    private static function isScheduledWindowActive( $window )
    {
        $timezone = $window['timezone'] ?? 'America/Chicago' ;
        try {
            $tz = new \DateTimeZone( $timezone ) ;
        } catch ( \Exception $e ) {
            $tz = new \DateTimeZone( 'America/Chicago' ) ;
        }

        $now = new \DateTime( 'now', $tz ) ;
        $currentTime = $now->format( 'H:i:s' ) ;

        // Check time window constraints
        if ( empty( $window['start_time'] ) || empty( $window['end_time'] ) ) {
            // No time constraints - active all day on matching days
            return self::isScheduleMatchingToday( $window, $now ) ;
        }

        // Check if this is an overnight window (start > end, e.g., 22:00-09:00)
        $isOvernight = self::isOvernightWindow( $window['start_time'], $window['end_time'] ) ;

        if ( $isOvernight ) {
            // For overnight windows, we need to check two cases:
            // 1. Today matches schedule AND current time >= start time (evening portion)
            // 2. YESTERDAY matched schedule AND current time <= end time (morning portion)

            $inEveningPortion = self::isTimeAfterOrEqual( $currentTime, $window['start_time'] ) ;
            $inMorningPortion = self::isTimeBeforeOrEqual( $currentTime, $window['end_time'] ) ;

            if ( $inEveningPortion && self::isScheduleMatchingToday( $window, $now ) ) {
                return true ;
            }

            if ( $inMorningPortion ) {
                // Check if yesterday matched the schedule
                $yesterday = clone $now ;
                $yesterday->modify( '-1 day' ) ;
                if ( self::isScheduleMatchingToday( $window, $yesterday ) ) {
                    return true ;
                }
            }

            return false ;
        }

        // Normal daytime window - today must match and time must be in range
        if ( ! self::isScheduleMatchingToday( $window, $now ) ) {
            return false ;
        }

        return self::isTimeInWindow( $window['start_time'], $window['end_time'], $currentTime ) ;
    }

    /**
     * Check if a time window spans overnight (start > end)
     *
     * @param string $startTime Start time (H:i:s or H:i)
     * @param string $endTime End time (H:i:s or H:i)
     * @return bool True if overnight window
     */
    private static function isOvernightWindow( $startTime, $endTime )
    {
        $start = strtotime( "1970-01-01 $startTime" ) ;
        $end = strtotime( "1970-01-01 $endTime" ) ;
        return ( $start > $end ) ;
    }

    /**
     * Check if current time is at or after a given time
     *
     * @param string $currentTime Current time (H:i:s)
     * @param string $targetTime Target time (H:i:s or H:i)
     * @return bool True if current >= target
     */
    private static function isTimeAfterOrEqual( $currentTime, $targetTime )
    {
        $current = strtotime( "1970-01-01 $currentTime" ) ;
        $target = strtotime( "1970-01-01 $targetTime" ) ;
        return ( $current >= $target ) ;
    }

    /**
     * Check if current time is at or before a given time
     *
     * @param string $currentTime Current time (H:i:s)
     * @param string $targetTime Target time (H:i:s or H:i)
     * @return bool True if current <= target
     */
    private static function isTimeBeforeOrEqual( $currentTime, $targetTime )
    {
        $current = strtotime( "1970-01-01 $currentTime" ) ;
        $target = strtotime( "1970-01-01 $targetTime" ) ;
        return ( $current <= $target ) ;
    }

    /**
     * Check if today matches the schedule
     *
     * @param array $window Window data
     * @param \DateTime $now Current datetime in window's timezone
     * @return bool True if schedule matches today
     */
    private static function isScheduleMatchingToday( $window, $now )
    {
        $scheduleType = $window['schedule_type'] ;

        switch ( $scheduleType ) {
            case 'weekly':
                return self::isWeeklyMatch( $window, $now ) ;

            case 'monthly':
                return self::isMonthlyMatch( $window, $now ) ;

            case 'quarterly':
                return self::isQuarterlyMatch( $window, $now ) ;

            case 'annually':
                return self::isAnnuallyMatch( $window, $now ) ;

            case 'periodic':
                return self::isPeriodicMatch( $window, $now ) ;

            default:
                return false ;
        }
    }

    /**
     * Check weekly schedule (days_of_week SET)
     */
    private static function isWeeklyMatch( $window, $now )
    {
        if ( empty( $window['days_of_week'] ) ) {
            return false ;
        }

        $dayNames = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ] ;
        $todayName = $dayNames[ (int) $now->format( 'w' ) ] ;

        // days_of_week is a comma-separated SET value like "Mon,Tue,Wed"
        $selectedDays = explode( ',', $window['days_of_week'] ) ;
        return in_array( $todayName, $selectedDays ) ;
    }

    /**
     * Check monthly schedule (day_of_month)
     * day_of_month = 32 means last day of month
     */
    private static function isMonthlyMatch( $window, $now )
    {
        if ( empty( $window['day_of_month'] ) ) {
            return false ;
        }

        $targetDay = (int) $window['day_of_month'] ;
        $todayDay = (int) $now->format( 'j' ) ;
        $lastDayOfMonth = (int) $now->format( 't' ) ;

        if ( $targetDay === 32 ) {
            // Last day of month
            return ( $todayDay === $lastDayOfMonth ) ;
        }

        return ( $todayDay === $targetDay ) ;
    }

    /**
     * Check quarterly schedule (month_of_year 1-3, day_of_month)
     * month_of_year: 1 = first month of quarter (Jan, Apr, Jul, Oct)
     *                2 = second month of quarter (Feb, May, Aug, Nov)
     *                3 = third month of quarter (Mar, Jun, Sep, Dec)
     */
    private static function isQuarterlyMatch( $window, $now )
    {
        if ( empty( $window['month_of_year'] ) || empty( $window['day_of_month'] ) ) {
            return false ;
        }

        $currentMonth = (int) $now->format( 'n' ) ; // 1-12
        $monthInQuarter = ( ( $currentMonth - 1 ) % 3 ) + 1 ; // 1, 2, or 3

        if ( $monthInQuarter !== (int) $window['month_of_year'] ) {
            return false ;
        }

        // Check day
        $targetDay = (int) $window['day_of_month'] ;
        $todayDay = (int) $now->format( 'j' ) ;
        $lastDayOfMonth = (int) $now->format( 't' ) ;

        if ( $targetDay === 32 ) {
            return ( $todayDay === $lastDayOfMonth ) ;
        }

        return ( $todayDay === $targetDay ) ;
    }

    /**
     * Check annually schedule (month_of_year, day_of_month)
     */
    private static function isAnnuallyMatch( $window, $now )
    {
        if ( empty( $window['month_of_year'] ) || empty( $window['day_of_month'] ) ) {
            return false ;
        }

        $currentMonth = (int) $now->format( 'n' ) ;
        if ( $currentMonth !== (int) $window['month_of_year'] ) {
            return false ;
        }

        // Check day
        $targetDay = (int) $window['day_of_month'] ;
        $todayDay = (int) $now->format( 'j' ) ;
        $lastDayOfMonth = (int) $now->format( 't' ) ;

        if ( $targetDay === 32 ) {
            return ( $todayDay === $lastDayOfMonth ) ;
        }

        return ( $todayDay === $targetDay ) ;
    }

    /**
     * Check periodic schedule (every N days from period_start_date)
     */
    private static function isPeriodicMatch( $window, $now )
    {
        if ( empty( $window['period_days'] ) || empty( $window['period_start_date'] ) ) {
            return false ;
        }

        $startDate = new \DateTime( $window['period_start_date'] ) ;
        $today = new \DateTime( $now->format( 'Y-m-d' ) ) ;

        $daysSinceStart = $startDate->diff( $today )->days ;
        $periodDays = (int) $window['period_days'] ;

        // Today matches if days since start is divisible by period
        return ( $daysSinceStart % $periodDays === 0 ) ;
    }

    /**
     * Check if current time falls within the time window
     * Handles overnight spans (e.g., 22:00-06:00)
     *
     * @param string $startTime Start time (H:i:s or H:i)
     * @param string $endTime End time (H:i:s or H:i)
     * @param string $currentTime Current time (H:i:s)
     * @return bool True if current time is within window
     */
    private static function isTimeInWindow( $startTime, $endTime, $currentTime )
    {
        // Normalize to comparable format
        $start = strtotime( "1970-01-01 $startTime" ) ;
        $end = strtotime( "1970-01-01 $endTime" ) ;
        $current = strtotime( "1970-01-01 $currentTime" ) ;

        // Normal span: start < end (e.g., 09:00 - 17:00)
        if ( $start <= $end ) {
            return ( $current >= $start && $current <= $end ) ;
        }

        // Overnight span: start > end (e.g., 22:00 - 06:00)
        // Current must be >= start OR <= end
        return ( $current >= $start || $current <= $end ) ;
    }

    /**
     * Format maintenance info for JSON output
     *
     * @param array $window Row from maintenance_window table
     * @return array Formatted maintenance info
     */
    private static function formatMaintenanceInfo( $window )
    {
        $info = [
            'active'      => true,
            'windowId'    => (int) $window['window_id'],
            'windowType'  => $window['window_type'],
            'targetType'  => $window['target_type'],
            'description' => $window['description'] ?? '',
        ] ;

        if ( $window['target_type'] === 'group' ) {
            $info['groupName'] = $window['group_name'] ;
        }

        if ( $window['window_type'] === 'adhoc' ) {
            $info['expiresAt'] = $window['silence_until'] ;
        } else {
            $info['scheduleType'] = $window['schedule_type'] ;
            if ( ! empty( $window['start_time'] ) && ! empty( $window['end_time'] ) ) {
                $info['timeWindow'] = substr( $window['start_time'], 0, 5 ) . ' - ' . substr( $window['end_time'], 0, 5 ) ;
            }
        }

        return $info ;
    }

    /**
     * Get all currently active maintenance windows with their affected hosts
     *
     * @param \mysqli $dbh Database connection
     * @return array Array of active windows with host lists
     */
    public static function getAllActiveWindows( $dbh )
    {
        $activeWindows = [] ;
        $config = new Config() ;
        $tz = new \DateTimeZone( $config->getTimeZone() ) ;
        $now = new \DateTime( 'now', $tz ) ;

        // Get all maintenance windows
        $sql = "SELECT mw.window_id, mw.window_type, mw.schedule_type, mw.days_of_week,
                       mw.day_of_month, mw.month_of_year, mw.period_days, mw.period_start_date,
                       mw.start_time, mw.end_time, mw.timezone, mw.silence_until, mw.description
                FROM maintenance_window mw
                ORDER BY mw.window_id" ;

        $result = $dbh->query( $sql ) ;
        if ( ! $result ) {
            return [] ;
        }

        while ( $window = $result->fetch_assoc() ) {
            // Check if this window is currently active
            $isActive = false ;
            if ( $window['window_type'] === 'adhoc' ) {
                $isActive = self::isAdhocWindowActive( $window ) ;
            } else {
                $isActive = self::isScheduledWindowActive( $window ) ;
            }

            if ( ! $isActive ) {
                continue ;
            }

            // Get hosts affected by this window
            $windowId = (int) $window['window_id'] ;
            $hosts = [] ;

            // Direct host mappings
            $hostSql = "SELECT h.hostname, h.port_number
                        FROM maintenance_window_host_map mwhm
                        JOIN host h ON mwhm.host_id = h.host_id
                        WHERE mwhm.window_id = ?
                        ORDER BY h.hostname" ;
            $hostStmt = $dbh->prepare( $hostSql ) ;
            if ( $hostStmt ) {
                $hostStmt->bind_param( 'i', $windowId ) ;
                $hostStmt->execute() ;
                $hostResult = $hostStmt->get_result() ;
                while ( $h = $hostResult->fetch_assoc() ) {
                    $hosts[] = $h['hostname'] . ':' . $h['port_number'] ;
                }
                $hostStmt->close() ;
            }

            // Hosts via group mappings
            $groupSql = "SELECT h.hostname, h.port_number, hg.tag as group_name
                         FROM maintenance_window_host_group_map mwgm
                         JOIN host_group hg ON mwgm.host_group_id = hg.host_group_id
                         JOIN host_group_map hgm ON hg.host_group_id = hgm.host_group_id
                         JOIN host h ON hgm.host_id = h.host_id
                         WHERE mwgm.window_id = ?
                         ORDER BY hg.tag, h.hostname" ;
            $groupStmt = $dbh->prepare( $groupSql ) ;
            if ( $groupStmt ) {
                $groupStmt->bind_param( 'i', $windowId ) ;
                $groupStmt->execute() ;
                $groupResult = $groupStmt->get_result() ;
                while ( $h = $groupResult->fetch_assoc() ) {
                    $hostEntry = $h['hostname'] . ':' . $h['port_number'] . ' (via ' . $h['group_name'] . ')' ;
                    if ( ! in_array( $h['hostname'] . ':' . $h['port_number'], $hosts ) ) {
                        $hosts[] = $hostEntry ;
                    }
                }
                $groupStmt->close() ;
            }

            if ( count( $hosts ) > 0 ) {
                $windowInfo = [
                    'windowId'    => $windowId,
                    'windowType'  => $window['window_type'],
                    'description' => $window['description'],
                    'hosts'       => $hosts
                ] ;

                if ( $window['window_type'] === 'adhoc' ) {
                    $windowInfo['expiresAt'] = $window['silence_until'] ;
                } else {
                    $windowInfo['scheduleType'] = $window['schedule_type'] ;
                    $windowInfo['daysOfWeek'] = $window['days_of_week'] ;
                    if ( ! empty( $window['start_time'] ) && ! empty( $window['end_time'] ) ) {
                        $windowInfo['timeWindow'] = substr( $window['start_time'], 0, 5 ) . ' - ' . substr( $window['end_time'], 0, 5 ) ;
                    }
                }

                $activeWindows[] = $windowInfo ;
            }
        }
        $result->close() ;

        return $activeWindows ;
    }
}
