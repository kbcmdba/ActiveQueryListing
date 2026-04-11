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

namespace com\kbcmdba\aql\Libs ;

/**
 * A series of static methods for re-use.
*/
class Tools
{
    /**
     * Default maximum length for any single input parameter (in bytes).
     * Inputs exceeding this are rejected (return the default value) and logged.
     * 8KB matches the typical web server LimitRequestLine / large_client_header_buffers
     * defaults, providing defense-in-depth for any URL-style input.
     */
    const DEFAULT_MAX_INPUT_LENGTH = 8192 ;

    /**
     * Class Constructor - never intended to be instantiated.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        throw new \Exception("Improper use of Tools class") ;
    }

    /**
     * Validate that an input value is within the maximum length.
     * Returns the original value if OK, or null if it exceeds the limit
     * (and logs a warning to the PHP error log).
     *
     * @param mixed $value     The input value to check
     * @param int   $maxLength Maximum allowed byte length
     * @param string $key      Parameter name (for logging only)
     * @return mixed|null Returns $value if within limits, null if rejected
     */
    private static function checkLength($value, int $maxLength, string $key)
    {
        if (is_scalar($value)) {
            if (strlen((string) $value) > $maxLength) {
                error_log(sprintf(
                    "AQL: rejected oversized input parameter '%s' (length=%d, max=%d) from %s",
                    $key,
                    strlen((string) $value),
                    $maxLength,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                )) ;
                return null ;
            }
            return $value ;
        }
        if (is_array($value)) {
            // For arrays, check each scalar element. Reject the whole thing
            // if any element is too long.
            foreach ($value as $item) {
                if (is_scalar($item) && strlen((string) $item) > $maxLength) {
                    error_log(sprintf(
                        "AQL: rejected oversized array element in parameter '%s' (length=%d, max=%d) from %s",
                        $key,
                        strlen((string) $item),
                        $maxLength,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    )) ;
                    return null ;
                }
            }
            return $value ;
        }
        return $value ;
    }

    /**
     * Return the value from $_REQUEST[ $key ] if available or the default value.
     * If not specified, the default value is an empty string.
     *
     * @param string $key
     * @param string $default
     * @param int    $debug
     * @param int    $maxLength Maximum byte length (default: DEFAULT_MAX_INPUT_LENGTH)
     * @return string
     */
    public static function param($key, $default = '', $debug = 0, $maxLength = self::DEFAULT_MAX_INPUT_LENGTH)
    {
        if (! isset($key) || ! isset($_REQUEST[ $key ])) {
            return $default ;
        }
        $value = self::checkLength($_REQUEST[ $key ], $maxLength, $key) ;
        if ($value === null) {
            return $default ;
        }
        return is_string($value) ? trim($value) : $value ;
    }

    /**
     * Return the values from $_REQUEST[ $key ] if available or the default value.
     * If not specified, the default value is an empty array.
     *
     * @param string $key
     * @param array  $default
     * @param int    $debug
     * @param int    $maxLength Maximum byte length per element
     * @return mixed
     */
    public static function params($key, $default = [], $debug = 0, $maxLength = self::DEFAULT_MAX_INPUT_LENGTH)
    {
        if (! isset($key) || ! isset($_REQUEST[ $key ])) {
            return $default ;
        }
        $value = self::checkLength($_REQUEST[ $key ], $maxLength, $key) ;
        return $value === null ? $default : $value ;
    }

    /**
     * Return the value from $_GET[ $key ] if available or the default value.
     * If not specified, the default value is an empty string.
     *
     * @param string $key
     * @param string $default
     * @param int    $debug
     * @param int    $maxLength Maximum byte length
     * @return string
     */
    public static function get($key, $default = '', $debug = 0, $maxLength = self::DEFAULT_MAX_INPUT_LENGTH)
    {
        $source = (1 === $debug) ? $_REQUEST : $_GET ;
        if (! isset($key) || ! isset($source[ $key ])) {
            return $default ;
        }
        $value = self::checkLength($source[ $key ], $maxLength, $key) ;
        return $value === null ? $default : $value ;
    }

    /**
     * Return the values from $_GET[ $key ] if available or the default value.
     * If not specified, the default value is an empty array.
     *
     * @param string $key
     * @param array  $default
     * @param int    $debug
     * @param int    $maxLength Maximum byte length per element
     * @return mixed
     */
    public static function gets($key, $default = [], $debug = 0, $maxLength = self::DEFAULT_MAX_INPUT_LENGTH)
    {
        $source = (1 === $debug) ? $_REQUEST : $_GET ;
        if (! isset($key) || ! isset($source[ $key ])) {
            return $default ;
        }
        $value = self::checkLength($source[ $key ], $maxLength, $key) ;
        return $value === null ? $default : $value ;
    }

    /**
     * Return the value from $_POST[ $key ] if available or the default value.
     * If not specified, the default value is an empty string.
     *
     * @param string $key
     * @param string $default
     * @param int    $debug
     * @param int    $maxLength Maximum byte length
     * @return string
     */
    public static function post($key, $default = '', $debug = 0, $maxLength = self::DEFAULT_MAX_INPUT_LENGTH)
    {
        $source = (1 === $debug) ? $_REQUEST : $_POST ;
        if (! isset($key) || ! isset($source[ $key ])) {
            return $default ;
        }
        $value = self::checkLength($source[ $key ], $maxLength, $key) ;
        return $value === null ? $default : $value ;
    }

    /**
     * Return the values from $_POST[ $key ] if available or the default value.
     * If not specified, the default value is an empty array.
     *
     * @param string $key
     * @param array  $default
     * @param int    $debug
     * @param int    $maxLength Maximum byte length per element
     * @return mixed
     */
    public static function posts($key, $default = [], $debug = 0, $maxLength = self::DEFAULT_MAX_INPUT_LENGTH)
    {
        $source = (1 === $debug) ? $_REQUEST : $_POST ;
        if (! isset($key) || ! isset($source[ $key ])) {
            return $default ;
        }
        $value = self::checkLength($source[ $key ], $maxLength, $key) ;
        return $value === null ? $default : $value ;
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
     * Return a time-like string of seconds, minutes:seconds or hours:minutes:seconds
     *
     * @param integer $in_seconds
     */
    public static function friendlyTime($in_seconds)
    {
        if (!isset($in_seconds) || !is_numeric($in_seconds)) {
            return $in_seconds ;
        }
        $in_seconds = (int) $in_seconds ;
        $secs = $in_seconds % 60 ;
        if ($in_seconds < 60) {
            return "{$secs}s" ;
        }
        $mins = intdiv($in_seconds, 60) % 60 ;
        if ($in_seconds < 3600) {
            return sprintf('%dm,%ds', $mins, $secs) ;
        }
        $hrs = intdiv($in_seconds, 3600) ;
        if ($hrs < 24) {
            return sprintf('%dh,%dm,%ds', $hrs, $mins, $secs) ;
        } else {
            $days = intdiv($hrs, 24) ;
            $hrs = $hrs % 24 ;
            return sprintf('%dd,%dh,%dm,%ds', $days, $hrs, $mins, $secs) ;
        }
    } // END OF function friendlyTime( $in_seconds )

    /**
     * Change SQL constants (bare numbers and quoted strings) to a PII-safe
     * obscured string using a small state-machine tokenizer. Replaces the
     * old regex-based approach which had bugs around backslash-escaped
     * quotes and other edge cases.
     *
     * Output format (preserves LIKE pattern shape so analysts can still tell
     * `LIKE 'foo%'` from `LIKE '%foo%'`):
     * - Integer/hex literals: N
     * - Empty string '' or "": 'S' / "S"
     * - Plain string 'foo': 'S'
     * - LIKE-pattern strings: 'foo%' -> 'S%', '%foo' -> '%S', '%foo%' -> '%S%',
     *   'foo%bar' -> 'S%S', etc.
     * - Backtick-quoted identifiers (MySQL): preserved as-is, never treated
     *   as PII (they're column/table names, not user data)
     * - Comments (-- ... \n, # ... \n, slash-star ... star-slash): preserved
     *
     * @param string $str SQL Statement to be made safe
     * @return string Obscured SQL statement
     */
    public static function makeQuotedStringPIISafeV2( $str )
    {
        $len = strlen( $str ) ;
        $out = '' ;
        $i = 0 ;
        while ( $i < $len ) {
            $ch = $str[ $i ] ;
            $next = ( $i + 1 < $len ) ? $str[ $i + 1 ] : '' ;

            // Backtick identifier (MySQL): copy through unchanged
            if ( $ch === '`' ) {
                $out .= '`' ;
                $i++ ;
                while ( $i < $len && $str[ $i ] !== '`' ) {
                    $out .= $str[ $i ] ;
                    $i++ ;
                }
                if ( $i < $len ) {
                    $out .= '`' ;
                    $i++ ;
                }
                continue ;
            }

            // Block comment: /* ... */
            if ( $ch === '/' && $next === '*' ) {
                $out .= '/*' ;
                $i += 2 ;
                while ( $i + 1 < $len && ! ( $str[ $i ] === '*' && $str[ $i + 1 ] === '/' ) ) {
                    $out .= $str[ $i ] ;
                    $i++ ;
                }
                if ( $i + 1 < $len ) {
                    $out .= '*/' ;
                    $i += 2 ;
                }
                continue ;
            }

            // Line comment: -- ... newline
            if ( $ch === '-' && $next === '-' ) {
                while ( $i < $len && $str[ $i ] !== "\n" ) {
                    $out .= $str[ $i ] ;
                    $i++ ;
                }
                continue ;
            }

            // Line comment: # ... newline (MySQL)
            if ( $ch === '#' ) {
                while ( $i < $len && $str[ $i ] !== "\n" ) {
                    $out .= $str[ $i ] ;
                    $i++ ;
                }
                continue ;
            }

            // Single-quoted string literal
            if ( $ch === "'" ) {
                [ $sanitized, $consumed ] = self::sanitizeStringLiteral( $str, $i, "'" ) ;
                $out .= $sanitized ;
                $i += $consumed ;
                continue ;
            }

            // Double-quoted string literal
            if ( $ch === '"' ) {
                [ $sanitized, $consumed ] = self::sanitizeStringLiteral( $str, $i, '"' ) ;
                $out .= $sanitized ;
                $i += $consumed ;
                continue ;
            }

            // Hex literal: 0x[0-9a-fA-F]+ (only at word boundary)
            if ( $ch === '0' && ( $next === 'x' || $next === 'X' )
                && ( $i === 0 || ! self::isWordChar( $str[ $i - 1 ] ) ) ) {
                $j = $i + 2 ;
                $hexStart = $j ;
                while ( $j < $len && ctype_xdigit( $str[ $j ] ) ) {
                    $j++ ;
                }
                if ( $j > $hexStart ) {
                    $out .= 'N' ;
                    $i = $j ;
                    continue ;
                }
            }

            // Integer literal at word boundary
            if ( ctype_digit( $ch )
                && ( $i === 0 || ! self::isWordChar( $str[ $i - 1 ] ) ) ) {
                $j = $i ;
                while ( $j < $len && ctype_digit( $str[ $j ] ) ) {
                    $j++ ;
                }
                // Only treat as a number if not followed by an identifier char
                // (so 'col1' stays 'col1', not 'colN')
                if ( $j === $len || ! self::isWordChar( $str[ $j ] ) ) {
                    $out .= 'N' ;
                    $i = $j ;
                    continue ;
                }
            }

            // Default: copy character through
            $out .= $ch ;
            $i++ ;
        }
        return $out ;
    }

    /**
     * Sanitize a single string literal starting at $start in $str.
     * Returns [sanitized_replacement, characters_consumed_including_quotes].
     * Handles backslash escapes (\' \" \\ etc.) and SQL doubled-quote escape ('').
     * Preserves the % positions for LIKE-pattern shape recognition.
     * UTF-8 safe: backslash-escape correctly skips multi-byte characters.
     */
    private static function sanitizeStringLiteral( string $str, int $start, string $quote ) : array
    {
        $len = strlen( $str ) ;
        $i = $start + 1 ;

        // Build a sequence of tokens as we walk: each token is either "S"
        // (a run of one or more non-% characters) or "%" (a literal percent).
        // Consecutive runs of non-% chars collapse into a single "S".
        $tokens = [] ;
        $inRun = false ;  // Are we currently inside a run of non-% chars?

        while ( $i < $len ) {
            $c = $str[ $i ] ;

            // Backslash escape: skip the next FULL UTF-8 character (1-4 bytes).
            // Treat as one "content" character (extends the current run).
            if ( $c === '\\' && $i + 1 < $len ) {
                $i += 1 + self::utf8CharLength( $str, $i + 1 ) ;
                if ( ! $inRun ) {
                    $tokens[] = 'S' ;
                    $inRun = true ;
                }
                continue ;
            }

            // SQL doubled-quote escape: '' or "" inside the same quote type.
            if ( $c === $quote && $i + 1 < $len && $str[ $i + 1 ] === $quote ) {
                $i += 2 ;
                if ( ! $inRun ) {
                    $tokens[] = 'S' ;
                    $inRun = true ;
                }
                continue ;
            }

            // End of literal
            if ( $c === $quote ) {
                $i++ ;
                break ;
            }

            // Literal % - emit as its own token, breaking any current run
            if ( $c === '%' ) {
                $tokens[] = '%' ;
                $inRun = false ;
                $i++ ;
                continue ;
            }

            // Any other byte (ASCII or part of a multi-byte UTF-8 char):
            // extends the current run if we're in one, else starts a new S.
            if ( ! $inRun ) {
                $tokens[] = 'S' ;
                $inRun = true ;
            }
            $i++ ;
        }

        $consumed = $i - $start ;

        // Empty string: '' or ""
        if ( empty( $tokens ) ) {
            return [ $quote . 'S' . $quote, $consumed ] ;
        }

        return [ $quote . implode( '', $tokens ) . $quote, $consumed ] ;
    }

    /**
     * True if a byte is part of an SQL identifier — ASCII alnum/underscore
     * OR any non-ASCII byte (0x80+). The non-ASCII catch-all means UTF-8
     * multi-byte characters are treated as identifier continuations, so an
     * identifier like `é1` is correctly NOT treated as having a digit
     * literal at the end.
     */
    private static function isWordChar( string $c ) : bool
    {
        if ( ctype_alnum( $c ) || $c === '_' ) {
            return true ;
        }
        // Any non-ASCII byte (0x80+) is part of a UTF-8 multi-byte sequence
        // and should be treated as identifier content.
        return ord( $c ) >= 0x80 ;
    }

    /**
     * Return the byte length of the UTF-8 character starting at byte $i.
     * Returns 1 for ASCII or invalid lead bytes, 2-4 for multi-byte sequences.
     */
    private static function utf8CharLength( string $str, int $i ) : int
    {
        if ( $i >= strlen( $str ) ) {
            return 0 ;
        }
        $byte = ord( $str[ $i ] ) ;
        if ( $byte < 0x80 ) {
            return 1 ;  // ASCII
        }
        if ( $byte < 0xC0 ) {
            return 1 ;  // Stray continuation byte (invalid UTF-8); skip 1
        }
        if ( $byte < 0xE0 ) {
            return 2 ;  // 2-byte sequence (U+0080 - U+07FF)
        }
        if ( $byte < 0xF0 ) {
            return 3 ;  // 3-byte sequence (U+0800 - U+FFFF)
        }
        return 4 ;       // 4-byte sequence (U+10000 - U+10FFFF)
    }

    /**
     * @deprecated Use makeQuotedStringPIISafeV2() — has known bugs with
     * backslash-escaped quotes and other edge cases. Kept temporarily for
     * comparison and any callers that haven't migrated.
     *
     * @param string $str SQL Statement to be made safe
     * @return string Obscured SQL statement
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

    /**
     * preformatted print_r back to a web page then maybe exit()
     *
     * @param mixed $data
     * @param boolean $die
     */
    public static function pr( $data, $die = false) {
        echo '<pre>';
        print_r( $data );
        echo '</pre>';
        if ($die) {
            exit();
        }
    }

    /**
     * var_dump back to a web page then maybe exit()
     *
     * @param mixed $data
     * @param boolean $die
     */
    public static function vd( $data, $die = false) {
        var_dump( $data );
        if ($die) {
            exit();
        }
    }

}
