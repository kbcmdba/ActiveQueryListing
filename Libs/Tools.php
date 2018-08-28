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

/**
 * A series of static methods for re-use.
*/
class Tools
{

    /**
     * Class Constructor - never intended to be used.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        throw new \Exception("Improper use of Tools class") ;
    }

    /**
     * Return the value from $_REQUEST[ $key ] if available or the default value.
     * If not specified, the default value is an empty string.
     *
     * @param String $key
     * @param String $default
     * @return String
     */
    public static function param($key, $default = '')
    {
        return (isset($key) && (isset($_REQUEST[ $key ]))) ? trim($_REQUEST[ $key ]) : $default ;
    }

    /**
     * Return the values from $_REQUEST[ $key ] if available or the default value.
     * If not specified, the default value is an empty array.
     *
     * @param string $key
     * @param array $default
     * @return mixed
     */
    public static function params($key, $default = [])
    {
        return(isset($key) && isset($_REQUEST[ $key ]) ? $_REQUEST[ $key ] : $default) ;
    }
    
    /**
     * Return the value from $_POST[ $key ] if available or the default value.
     * If not specified, the default value is an empty string.
     *
     * @param String $key
     * @param String $default
     * @return String
     */
    public static function post($key, $default = '')
    {
        return (isset($key) && (isset($_POST[ $key ]))) ? $_POST[ $key ] : $default ;
    }

    /**
     * Return the values from $_POST[ $key ] if available or the default value.
     * If not specified, the default value is an empty array.
     *
     * @param string $key
     * @param array $default
     * @return mixed
     */
    public static function posts($key, $default = [])
    {
        return(isset($key) && isset($_POST[ $key ]) ? $_POST[ $key ] : $default) ;
    }
    
    /**
     * Display a table cell but put a non-blank space in it if it's empty or
     * null. Typically, this helps get around empty boxes without lines in
     * browsers that don't properly support styles to make this happen.
     *
     * @param string $x
     * @return boolean
     */
    public static function nonBlankCell($x)
    {
        return (! isset($x) || ($x === '')) ? "&nbsp;" : $x ;
    }

    /**
     * Return true when the value passed is either NULL or an empty string ('')
     *
     * @param mixed $x
     * @return boolean
     */
    public static function isNullOrEmptyString($x)
    {
        return ((null === $x) || ('' === $x)) ;
    }

    /**
     * Return true when the value passed is a number
     *
     * @param boolean $x
     */
    public static function isNumeric($x)
    {
        return (isset($x) && preg_match('/^(-|)[0-9]+$/', $x)) ;
    }

    /**
     * Return the MySQL format timestamp value for the given time()
     * value. If epochTime is null, return the current date and time.
     *
     * @param int $epochTime Seconds since January 1, 1970 at midnight
     * @return string
     */
    public static function currentTimestamp($epochTime = null)
    {
        if (null === $epochTime) {
            $epochTime = time() ;
        }
        return date('Y-m-d H:i:s', $epochTime) ;
    }

    /**
     *
     * Return a time-like string of seconds, minutes:seconds or hours:minutes:se
conds
     *
     * @param integer $in_seconds
     *            @fixme Comparison to is_integer isn't working as expected.
     */
    public static function friendlyTime($in_seconds)
    {
        if (!isset($in_seconds) || (is_integer($in_seconds))) {
            return $in_seconds ;
        }
        $secs = $in_seconds % 60 ;
        if ($in_seconds < 60) {
            return "${secs}s" ;
        }
        $mins = ($in_seconds / 60) % 60 ;
        if ($in_seconds < 3600) {
            return sprintf('%dm,%ds', $mins, $secs) ;
        }
        $hrs = $in_seconds / 3600 ;
        if ($hrs < 24) {
            return sprintf('%dh,%dm,%ds', $hrs, $mins, $secs) ;
        } else {
            $days = $hrs / 24 ;
            $hrs = $hrs % 24 ;
            return sprintf('%dd,%dh,%dm,%ds', $days, $hrs, $mins, $secs) ;
        }
    } // END OF function friendlyTime( $in_seconds )

    /**
     *
     * Change SQL constants (bare numbers and quoted strings) to a PII safe
     * obscured string. Mimics functionality provided by mysqldumpslow but
     * extends it by making it easier to figure out LIKE searches, treating
     * all strings as a parameter to a LIKE clause.
     *
     * @param String $str
     *            SQL Statement to be made safe
     * @return String Obscured SQL statement
     */
    public static function makeQuotedStringPIISafe($str)
    {
        $regexes = [
                        '/\b\d+\b/' => 'N',
                        '/\b0x[0-9A-Fa-f]+\b/' => 'N',
                        "/''/" => "'S'",
                        '/""/' => '"S"',
                        "/(\\\\')/" => '',
                        '/(\\\\")/' => '',
                        "/'[^%']+'/" => "'S'",
                        '/"[^%"]+"/' => '"S"',
                        "/'[%]+([^'%]+[%]+)+[^'%]+[%]+'/" => "'%S%S%'",
                        "/'[%]+([^'%]+[%]+)+[^'%]+'/" => "'%S%S'",
                        "/'([^'%]+[%]+)+[^'%]+[%]+'/" => "'S%S%'",
                        "/'([^'%]+[%]+)+[^'%]+'/" => "'S%S'",
                        "/'[%]+[^'%]+[%]+'/" => "'%S%'",
                        "/'[%]+[^'%]+'/" => "'%S'",
                        "/'[^'%]+[%]+'/" => "'S%'",
                        "/'([^'%]+[%]+)+[^'%]+'/" => "'S%S'",
                        '/"[^"%]+"/' => '"S"',
                        '/"[%]+([^"%]+[%]+)+[^"%]+[%]+"/' => '"%S%S%"',
                        '/"[%]+([^"%]+[%]+)+[^"%]+"/' => '"%S%S"',
                        '/"([^"%]+[%]+)+[^"%]+[%]+"/' => '"S%S%"',
                        '/"([^"%]+[%]+)+[^"%]+"/' => '"S%S"',
                        '/"[%]+[^"[%]+]+[%]+"/' => '"%S%"',
                        '/"[%]+[^"%]+"/' => '"%S"',
                        '/"[^"%]+%"/' => '"S%"',
                        '/"([^"%]+[%]+)+[^"%]+"/' => '"S%S"'
        ] ;

        $delimPattern = "/('[^']*'|\"[^\"]*\")/" ;
        $result_array = preg_split($delimPattern, $str, 0, PREG_SPLIT_DELIM_CAPTURE) ;
        $newStr = '' ;
        while (count($result_array)) {
            $currStr = array_shift($result_array) ;
            $matchFound = 0 ;
            foreach ($regexes as $k => $v) {
                if (!$matchFound) {
                    $oldStr = $currStr ;
                    $currStr = preg_replace($k, $v, $currStr, -1) ;
                    if ($currStr !== $oldStr) {
                        $matchFound = 1 ;
                    }
                }
            }
            $newStr .= $currStr ;
        }
        $str = $newStr ;
        $str = preg_replace('/(\s{4,})/', "\n$1", $str, -1) ;
        return $str ;
    } // END OF function makeQuotedStringPIISafe( $str )
}
