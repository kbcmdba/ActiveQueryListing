<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\Tools ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/Tools.php — focused on the input size-limit security
 * additions (param/get/post family of methods).
 */
class ToolsTest extends TestCase
{
    private $errorLogFile ;
    private $originalErrorLog ;

    protected function setUp() : void
    {
        // Clean superglobals between tests
        $_REQUEST = [] ;
        $_GET = [] ;
        $_POST = [] ;
        // Route error_log() to a temp file so the rejection messages don't
        // pollute test output
        $this->errorLogFile = tempnam(sys_get_temp_dir(), 'aql_test_') ;
        $this->originalErrorLog = ini_get('error_log') ;
        ini_set('error_log', $this->errorLogFile) ;
    }

    protected function tearDown() : void
    {
        ini_set('error_log', $this->originalErrorLog) ;
        if ($this->errorLogFile && file_exists($this->errorLogFile)) {
            unlink($this->errorLogFile) ;
        }
    }

    // ========================================================================
    // param() — reads from $_REQUEST
    // ========================================================================

    public function testParamReturnsValueWhenPresent() : void
    {
        $_REQUEST['host'] = 'mysql1.hole' ;
        $this->assertSame('mysql1.hole', Tools::param('host')) ;
    }

    public function testParamReturnsDefaultWhenAbsent() : void
    {
        $this->assertSame('', Tools::param('missing')) ;
        $this->assertSame('fallback', Tools::param('missing', 'fallback')) ;
    }

    public function testParamTrimsWhitespace() : void
    {
        $_REQUEST['host'] = '  mysql1.hole  ' ;
        $this->assertSame('mysql1.hole', Tools::param('host')) ;
    }

    public function testParamRejectsOversizedInput() : void
    {
        // 8193-byte string — one byte over the default limit
        $_REQUEST['evil'] = str_repeat('A', 8193) ;
        $this->assertSame('', Tools::param('evil'), 'Oversized input should return default') ;
    }

    public function testParamAcceptsExactlyMaxLength() : void
    {
        // Exactly 8192 bytes — should pass
        $payload = str_repeat('A', 8192) ;
        $_REQUEST['ok'] = $payload ;
        $this->assertSame($payload, Tools::param('ok')) ;
    }

    public function testParamCustomMaxLengthEnforced() : void
    {
        $_REQUEST['hostname'] = str_repeat('a', 256) ;
        // Custom max of 255 — input is over
        $this->assertSame('', Tools::param('hostname', '', 0, 255)) ;
    }

    public function testParamCustomMaxLengthAccepts() : void
    {
        $_REQUEST['hostname'] = str_repeat('a', 100) ;
        $this->assertSame(str_repeat('a', 100), Tools::param('hostname', '', 0, 255)) ;
    }

    // ========================================================================
    // get() — reads from $_GET (or $_REQUEST if debug=1)
    // ========================================================================

    public function testGetReturnsValueWhenPresent() : void
    {
        $_GET['host'] = 'pg1.hole' ;
        $this->assertSame('pg1.hole', Tools::get('host')) ;
    }

    public function testGetReturnsDefaultWhenAbsent() : void
    {
        $this->assertSame('', Tools::get('missing')) ;
    }

    public function testGetRejectsOversizedInput() : void
    {
        $_GET['evil'] = str_repeat('B', 9000) ;
        $this->assertSame('', Tools::get('evil')) ;
    }

    public function testGetCustomMaxLength() : void
    {
        $_GET['short'] = 'this is too long' ;
        $this->assertSame('', Tools::get('short', '', 0, 5)) ;
        $_GET['short'] = 'hi' ;
        $this->assertSame('hi', Tools::get('short', '', 0, 5)) ;
    }

    public function testGetDebugModeReadsFromRequest() : void
    {
        // Debug mode reads from $_REQUEST instead of $_GET
        $_REQUEST['x'] = 'request_value' ;
        $_GET['x'] = 'get_value' ;
        $this->assertSame('request_value', Tools::get('x', '', 1)) ;
        $this->assertSame('get_value', Tools::get('x', '', 0)) ;
    }

    // ========================================================================
    // post() — reads from $_POST (or $_REQUEST if debug=1)
    // ========================================================================

    public function testPostReturnsValueWhenPresent() : void
    {
        $_POST['user'] = 'kbenton' ;
        $this->assertSame('kbenton', Tools::post('user')) ;
    }

    public function testPostRejectsOversizedInput() : void
    {
        $_POST['evil'] = str_repeat('C', 8193) ;
        $this->assertSame('', Tools::post('evil')) ;
    }

    public function testPostCustomMaxLengthForPasswords() : void
    {
        // Reasonable password max — 256 chars
        $_POST['password'] = str_repeat('p', 257) ;
        $this->assertSame('', Tools::post('password', '', 0, 256)) ;
        $_POST['password'] = 'normal_password' ;
        $this->assertSame('normal_password', Tools::post('password', '', 0, 256)) ;
    }

    // ========================================================================
    // params() / gets() / posts() — array variants
    // ========================================================================

    public function testParamsReturnsArrayWhenPresent() : void
    {
        $_REQUEST['hosts'] = [ 'a', 'b', 'c' ] ;
        $this->assertSame([ 'a', 'b', 'c' ], Tools::params('hosts')) ;
    }

    public function testParamsRejectsArrayWithOversizedElement() : void
    {
        $_REQUEST['hosts'] = [ 'normal', str_repeat('X', 9000), 'also_normal' ] ;
        // Default empty array on rejection
        $this->assertSame([], Tools::params('hosts')) ;
    }

    public function testParamsAcceptsArrayWithAllElementsWithinLimit() : void
    {
        $_REQUEST['hosts'] = [ 'a', 'b', 'c' ] ;
        $this->assertSame([ 'a', 'b', 'c' ], Tools::params('hosts', [], 0, 100)) ;
    }

    public function testGetsReadsFromGet() : void
    {
        $_GET['ids'] = [ '1', '2', '3' ] ;
        $this->assertSame([ '1', '2', '3' ], Tools::gets('ids')) ;
    }

    public function testPostsReadsFromPost() : void
    {
        $_POST['names'] = [ 'a', 'b' ] ;
        $this->assertSame([ 'a', 'b' ], Tools::posts('names')) ;
    }

    // ========================================================================
    // Existing helper tests
    // ========================================================================

    public function testIsNullOrEmptyString() : void
    {
        $this->assertTrue(Tools::isNullOrEmptyString(null)) ;
        $this->assertTrue(Tools::isNullOrEmptyString('')) ;
        $this->assertFalse(Tools::isNullOrEmptyString('hello')) ;
        $this->assertFalse(Tools::isNullOrEmptyString('0')) ;
        $this->assertFalse(Tools::isNullOrEmptyString(' ')) ;
    }

    public function testIsNumeric() : void
    {
        $this->assertTrue((bool) Tools::isNumeric('123')) ;
        $this->assertTrue((bool) Tools::isNumeric('-456')) ;
        $this->assertTrue((bool) Tools::isNumeric('0')) ;
        $this->assertFalse((bool) Tools::isNumeric('12.5')) ;
        $this->assertFalse((bool) Tools::isNumeric('abc')) ;
        $this->assertFalse((bool) Tools::isNumeric('12a')) ;
        $this->assertFalse((bool) Tools::isNumeric('')) ;
    }

    // ========================================================================
    // DEFAULT_MAX_INPUT_LENGTH constant
    // ========================================================================

    public function testDefaultMaxLengthIs8K() : void
    {
        $this->assertSame(8192, Tools::DEFAULT_MAX_INPUT_LENGTH) ;
    }

    // ========================================================================
    // Constructor must throw (Tools is static-only)
    // ========================================================================

    public function testConstructorThrows() : void
    {
        $this->expectException(\Exception::class) ;
        $this->expectExceptionMessageMatches('/Improper use/') ;
        new Tools() ;
    }

    // ========================================================================
    // nonBlankCell()
    // ========================================================================

    public function testNonBlankCellEmptyString() : void
    {
        $this->assertSame('&nbsp;', Tools::nonBlankCell('')) ;
    }

    public function testNonBlankCellNull() : void
    {
        $this->assertSame('&nbsp;', Tools::nonBlankCell(null)) ;
    }

    public function testNonBlankCellWithValue() : void
    {
        $this->assertSame('hello', Tools::nonBlankCell('hello')) ;
        $this->assertSame('0', Tools::nonBlankCell('0')) ;
    }

    // ========================================================================
    // currentTimestamp()
    // ========================================================================

    public function testCurrentTimestampWithEpoch() : void
    {
        // Use a known epoch and pin the timezone so the test is deterministic
        $oldTz = date_default_timezone_get() ;
        date_default_timezone_set('UTC') ;
        try {
            // 2024-01-15 12:30:45 UTC = 1705321845
            $this->assertSame('2024-01-15 12:30:45', Tools::currentTimestamp(1705321845)) ;
            // Epoch zero
            $this->assertSame('1970-01-01 00:00:00', Tools::currentTimestamp(0)) ;
        } finally {
            date_default_timezone_set($oldTz) ;
        }
    }

    public function testCurrentTimestampWithoutArgUsesNow() : void
    {
        $before = time() ;
        $result = Tools::currentTimestamp() ;
        $after = time() ;
        // Just verify the format and that it's within the test window
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result) ;
        $resultEpoch = strtotime($result) ;
        $this->assertGreaterThanOrEqual($before - 1, $resultEpoch) ;
        $this->assertLessThanOrEqual($after + 1, $resultEpoch) ;
    }

    // ========================================================================
    // friendlyTime()
    // ========================================================================

    public function testFriendlyTimeSecondsOnly() : void
    {
        $this->assertSame('0s', Tools::friendlyTime(0)) ;
        $this->assertSame('1s', Tools::friendlyTime(1)) ;
        $this->assertSame('59s', Tools::friendlyTime(59)) ;
    }

    public function testFriendlyTimeMinutesAndSeconds() : void
    {
        $this->assertSame('1m,0s', Tools::friendlyTime(60)) ;
        $this->assertSame('1m,30s', Tools::friendlyTime(90)) ;
        $this->assertSame('59m,59s', Tools::friendlyTime(3599)) ;
    }

    public function testFriendlyTimeHoursMinutesSeconds() : void
    {
        $this->assertSame('1h,0m,0s', Tools::friendlyTime(3600)) ;
        $this->assertSame('1h,30m,0s', Tools::friendlyTime(5400)) ;
        $this->assertSame('23h,59m,59s', Tools::friendlyTime(86399)) ;
    }

    public function testFriendlyTimeDaysHoursMinutesSeconds() : void
    {
        // 1 day exactly
        $this->assertSame('1d,0h,0m,0s', Tools::friendlyTime(86400)) ;
        // 1 day, 1 hour
        $this->assertSame('1d,1h,0m,0s', Tools::friendlyTime(86400 + 3600)) ;
        // The example from CLAUDE.md user preferences: "34052 (9h,27m,32s)"
        $this->assertSame('9h,27m,32s', Tools::friendlyTime(34052)) ;
    }

    public function testFriendlyTimeWithStringInput() : void
    {
        // friendlyTime accepts numeric strings
        $this->assertSame('1m,0s', Tools::friendlyTime('60')) ;
    }

    public function testFriendlyTimeWithNonNumericReturnsInput() : void
    {
        // Non-numeric returns the input unchanged (defensive behavior)
        $this->assertSame('not a number', Tools::friendlyTime('not a number')) ;
        $this->assertNull(Tools::friendlyTime(null)) ;
    }

    // ========================================================================
    // params() / gets() / posts() — debug-mode and oversized variants
    // (closes coverage gaps in the array variants)
    // ========================================================================

    public function testParamsReturnsDefaultWhenAbsent() : void
    {
        $this->assertSame([], Tools::params('missing')) ;
        $this->assertSame(['a'], Tools::params('missing', ['a'])) ;
    }

    public function testGetsReturnsDefaultWhenAbsent() : void
    {
        $this->assertSame([], Tools::gets('missing')) ;
    }

    public function testGetsRejectsOversizedElement() : void
    {
        $_GET['hosts'] = ['ok', str_repeat('X', 9000)] ;
        $this->assertSame([], Tools::gets('hosts')) ;
    }

    public function testGetsDebugModeReadsFromRequest() : void
    {
        $_REQUEST['ids'] = ['1', '2'] ;
        $_GET['ids'] = ['x', 'y'] ;
        $this->assertSame(['1', '2'], Tools::gets('ids', [], 1)) ;
    }

    public function testPostsReturnsDefaultWhenAbsent() : void
    {
        $this->assertSame([], Tools::posts('missing')) ;
    }

    public function testPostsRejectsOversizedElement() : void
    {
        $_POST['names'] = ['ok', str_repeat('Y', 8500)] ;
        $this->assertSame([], Tools::posts('names')) ;
    }

    public function testPostsDebugModeReadsFromRequest() : void
    {
        $_REQUEST['names'] = ['from_request'] ;
        $_POST['names'] = ['from_post'] ;
        $this->assertSame(['from_request'], Tools::posts('names', [], 1)) ;
    }

    public function testPostDebugModeReadsFromRequest() : void
    {
        // Currently covered by ToolsTest::testGetDebugModeReadsFromRequest pattern,
        // but post() debug branch is its own line
        $_REQUEST['x'] = 'from_request' ;
        $_POST['x'] = 'from_post' ;
        $this->assertSame('from_request', Tools::post('x', '', 1)) ;
    }

    // ========================================================================
    // pr() — preformatted print_r helper
    // ========================================================================

    public function testPrEchoesPreformattedDataWithoutDie() : void
    {
        ob_start() ;
        Tools::pr(['key' => 'value']) ;
        $output = ob_get_clean() ;
        $this->assertStringStartsWith('<pre>', $output) ;
        $this->assertStringEndsWith('</pre>', $output) ;
        $this->assertStringContainsString('key', $output) ;
        $this->assertStringContainsString('value', $output) ;
    }

    public function testPrHandlesScalarInput() : void
    {
        ob_start() ;
        Tools::pr('hello world') ;
        $output = ob_get_clean() ;
        $this->assertStringContainsString('hello world', $output) ;
    }

    public function testPrHandlesNullInput() : void
    {
        ob_start() ;
        Tools::pr(null) ;
        $output = ob_get_clean() ;
        $this->assertStringContainsString('<pre>', $output) ;
        $this->assertStringContainsString('</pre>', $output) ;
    }

    // ========================================================================
    // vd() — var_dump helper
    // ========================================================================

    public function testVdEchoesVarDumpWithoutDie() : void
    {
        ob_start() ;
        Tools::vd(['key' => 'value']) ;
        $output = ob_get_clean() ;
        $this->assertStringContainsString('key', $output) ;
        $this->assertStringContainsString('value', $output) ;
    }

    public function testVdHandlesScalarInput() : void
    {
        ob_start() ;
        Tools::vd(42) ;
        $output = ob_get_clean() ;
        $this->assertStringContainsString('42', $output) ;
    }

    public function testVdHandlesNullInput() : void
    {
        ob_start() ;
        Tools::vd(null) ;
        $output = ob_get_clean() ;
        $this->assertStringContainsString('NULL', $output) ;
    }

    // ========================================================================
    // makeQuotedStringPIISafe() — state-machine tokenizer
    // Walks input one byte at a time, UTF-8 safe, handles escapes/comments/etc
    // ========================================================================

    public function testPIISafeReplacesIntegerLiteral() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM users WHERE id = 12345") ;
        $this->assertStringNotContainsString('12345', $result) ;
        $this->assertStringContainsString(' = N', $result) ;
        $this->assertStringContainsString('FROM users', $result) ;
    }

    public function testPIISafeReplacesHexLiteral() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE col = 0xDEADBEEF") ;
        $this->assertStringNotContainsString('DEADBEEF', $result) ;
        $this->assertStringContainsString(' = N', $result) ;
    }

    public function testPIISafeDoesNotReplaceDigitsInIdentifiers() : void
    {
        // 'col1' should stay as 'col1', not 'colN'
        $result = Tools::makeQuotedStringPIISafe("SELECT col1, col2 FROM t") ;
        $this->assertStringContainsString('col1', $result) ;
        $this->assertStringContainsString('col2', $result) ;
    }

    public function testPIISafeReplacesPlainSingleQuotedString() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'kbenton'") ;
        $this->assertStringNotContainsString('kbenton', $result) ;
        $this->assertStringContainsString("'S'", $result) ;
    }

    public function testPIISafeReplacesEmptyString() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = ''") ;
        $this->assertStringContainsString("'S'", $result) ;
    }

    public function testPIISafeReplacesPlainDoubleQuotedString() : void
    {
        $result = Tools::makeQuotedStringPIISafe('SELECT * FROM t WHERE name = "secret"') ;
        $this->assertStringNotContainsString('secret', $result) ;
        $this->assertStringContainsString('"S"', $result) ;
    }

    public function testPIISafeHandlesBackslashEscapedQuoteInString() : void
    {
        // The big bug from the old version: 'O\'Brien' leaked "Brien"
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'O\\'Brien'") ;
        $this->assertStringNotContainsString('Brien', $result, 'must not leak text after escaped quote') ;
        $this->assertStringNotContainsString("O\\'", $result) ;
    }

    public function testPIISafeHandlesDoubledQuoteEscapeInString() : void
    {
        // SQL standard: 'O''Brien' is the escape form
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'O''Brien'") ;
        $this->assertStringNotContainsString('Brien', $result) ;
        $this->assertStringNotContainsString("O''", $result) ;
        $this->assertStringContainsString("'S'", $result) ;
    }

    public function testPIISafePreservesLikePatternLeadingPercent() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name LIKE '%foo'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertStringContainsString("'%S'", $result) ;
    }

    public function testPIISafePreservesLikePatternTrailingPercent() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name LIKE 'foo%'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertStringContainsString("'S%'", $result) ;
    }

    public function testPIISafePreservesLikePatternBothEnds() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name LIKE '%foo%'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertStringContainsString("'%S%'", $result) ;
    }

    public function testPIISafePreservesLikePatternInternalPercent() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name LIKE 'foo%bar'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertStringNotContainsString('bar', $result) ;
        $this->assertStringContainsString("'S%S'", $result) ;
    }

    public function testPIISafePreservesBacktickIdentifiers() : void
    {
        // MySQL allows quoting identifiers with backticks - these are NOT
        // user data and should NOT be sanitized
        $result = Tools::makeQuotedStringPIISafe("SELECT `user_id`, `name` FROM `users` WHERE `id` = 5") ;
        $this->assertStringContainsString('`user_id`', $result) ;
        $this->assertStringContainsString('`name`', $result) ;
        $this->assertStringContainsString('`users`', $result) ;
        $this->assertStringContainsString('`id`', $result) ;
        $this->assertStringContainsString(' = N', $result) ;
    }

    public function testPIISafeBacktickContainingQuoteCharacter() : void
    {
        // Backtick identifier containing an apostrophe should be preserved
        // as-is and not start a string-literal scan
        $result = Tools::makeQuotedStringPIISafe("SELECT `O'Brien_users` FROM t WHERE id = 1") ;
        $this->assertStringContainsString("`O'Brien_users`", $result) ;
        $this->assertStringContainsString(' = N', $result) ;
    }

    public function testPIISafePreservesBlockComments() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT /* hint: use_index */ * FROM t WHERE id = 1") ;
        $this->assertStringContainsString('/* hint: use_index */', $result) ;
        $this->assertStringContainsString(' = N', $result) ;
    }

    public function testPIISafePreservesLineCommentsDashDash() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t -- this is a comment\nWHERE id = 1") ;
        $this->assertStringContainsString('-- this is a comment', $result) ;
    }

    public function testPIISafePreservesLineCommentsHash() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t # MySQL comment\nWHERE id = 1") ;
        $this->assertStringContainsString('# MySQL comment', $result) ;
    }

    public function testPIISafeHandlesMultipleStringsInOneQuery() : void
    {
        $result = Tools::makeQuotedStringPIISafe(
            "SELECT * FROM t WHERE name = 'kbenton' AND email = 'k@example.com'"
        ) ;
        $this->assertStringNotContainsString('kbenton', $result) ;
        $this->assertStringNotContainsString('@example.com', $result) ;
        $this->assertSame(2, substr_count($result, "'S'")) ;
    }

    // ========================================================================
    // UTF-8 handling — the bane of every parser
    // ========================================================================

    public function testPIISafeUTF8StringContentDoesNotCorruptOutput() : void
    {
        // String literal with UTF-8 content - the entire thing should be
        // sanitized, not partially leaked
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'résumé'") ;
        $this->assertStringNotContainsString('résumé', $result) ;
        $this->assertStringContainsString("'S'", $result) ;
        // Output should still be valid UTF-8 (just an ASCII subset really)
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeUTF8WithBackslashEscape() : void
    {
        // Backslash followed by a multi-byte UTF-8 char - must skip the
        // entire char (1-4 bytes), not just the first byte. Otherwise we
        // leave a stray continuation byte in the output.
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'foo\\é bar'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertStringNotContainsString('bar', $result) ;
        // Output is still valid UTF-8 (no orphaned continuation bytes)
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'),
            'Output must remain valid UTF-8 after sanitization') ;
    }

    public function testPIISafeUTF8IdentifierWithDigit() : void
    {
        // An unquoted identifier like 'café1' (column or table name with
        // unicode and a trailing digit) should NOT have the '1' replaced
        // with N. The trailing digit is part of the identifier.
        // Note: in real SQL these usually need backticks, but the function
        // shouldn't break if they don't have them.
        $result = Tools::makeQuotedStringPIISafe("SELECT café1 FROM t") ;
        $this->assertStringContainsString('café1', $result) ;
    }

    public function testPIISafeUTF8MultiByteCharsCommonLatin() : void
    {
        // Various 2-byte UTF-8 characters in a string literal
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'Müller-Schäfer'") ;
        $this->assertStringNotContainsString('Müller', $result) ;
        $this->assertStringNotContainsString('Schäfer', $result) ;
        $this->assertStringContainsString("'S'", $result) ;
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeUTF8MultiByteChars3Byte() : void
    {
        // 3-byte UTF-8 characters (CJK)
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = '中文测试'") ;
        $this->assertStringNotContainsString('中文', $result) ;
        $this->assertStringContainsString("'S'", $result) ;
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeUTF8MultiByteChars4Byte() : void
    {
        // 4-byte UTF-8 characters (emoji, supplementary plane)
        $result = Tools::makeQuotedStringPIISafe("INSERT INTO t (msg) VALUES ('hello 🎉 world')") ;
        $this->assertStringNotContainsString('🎉', $result) ;
        $this->assertStringNotContainsString('hello', $result) ;
        $this->assertStringContainsString("'S'", $result) ;
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeUTF8WithLikePattern() : void
    {
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name LIKE '%résumé%'") ;
        $this->assertStringNotContainsString('résumé', $result) ;
        $this->assertStringContainsString("'%S%'", $result) ;
    }

    // ========================================================================
    // Edge cases and integration
    // ========================================================================

    public function testPIISafeEmptyInput() : void
    {
        $this->assertSame('', Tools::makeQuotedStringPIISafe('')) ;
    }

    public function testPIISafeOnlyWhitespace() : void
    {
        $this->assertSame('   ', Tools::makeQuotedStringPIISafe('   ')) ;
    }

    public function testPIISafeUnclosedString() : void
    {
        // Truncated query with an unclosed string - should not crash, should
        // sanitize what it can
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = 'foo") ;
        $this->assertStringNotContainsString('foo', $result) ;
    }

    public function testPIISafeUnclosedBacktick() : void
    {
        // Truncated identifier - should not crash
        $result = Tools::makeQuotedStringPIISafe("SELECT `col FROM t") ;
        // No assertion about specific output - just verify it doesn't crash
        $this->assertIsString($result) ;
    }

    public function testPIISafeQueryWithMixedNumbersAndStrings() : void
    {
        $sql = "INSERT INTO orders (user_id, amount, note) VALUES (12345, 99.99, 'rush order')" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringNotContainsString('12345', $result) ;
        $this->assertStringNotContainsString('99', $result) ;
        $this->assertStringNotContainsString('rush', $result) ;
        $this->assertStringContainsString('INSERT INTO orders', $result) ;
    }

    // ========================================================================
    // Coverage gap closers — the last few branches not yet exercised
    // ========================================================================

    public function testCheckLengthPassesNonScalarNonArrayThrough() : void
    {
        // checkLength's final branch: input is neither scalar nor array
        // (objects, resources). Returns the value unchanged.
        // We exercise this via $_REQUEST containing an object - unusual but
        // possible if a caller poked it directly.
        $_REQUEST['weird'] = new \stdClass() ;
        $result = Tools::param('weird', 'fallback') ;
        // Object passes through checkLength unchanged, but param() then
        // returns it directly (or trims it if string). For objects, it
        // skips the trim branch since !is_string(). It just returns the value.
        $this->assertInstanceOf(\stdClass::class, $result) ;
    }

    public function testPostKeyMissingDebugMode() : void
    {
        // post() debug=1 branch when key isn't set in $_REQUEST either
        $this->assertSame('default_val', Tools::post('totally_missing', 'default_val', 1)) ;
    }

    public function testPIISafeBackslashAtStartOfString() : void
    {
        // sanitizeStringLiteral: the !$inRun branch in the backslash-escape
        // path - backslash is the first thing inside the quote
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = '\\nfoo'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertStringContainsString("'S'", $result) ;
    }

    public function testPIISafeDoubledQuoteAtStartOfString() : void
    {
        // sanitizeStringLiteral: the !$inRun branch in the doubled-quote path -
        // doubled quote is the first thing inside the literal
        $result = Tools::makeQuotedStringPIISafe("SELECT * FROM t WHERE name = ''''") ;
        // 4 single quotes = '' (doubled escape) inside '...' literal = string
        // containing one literal apostrophe
        $this->assertStringContainsString("'S'", $result) ;
    }

    public function testPIISafeBackslashAtVeryEndOfString() : void
    {
        // utf8CharLength: the $i >= strlen branch - backslash is the very
        // last char before EOF (no closing quote, no char to escape)
        $result = Tools::makeQuotedStringPIISafe("SELECT 'foo\\") ;
        // No crash; output is a string
        $this->assertIsString($result) ;
    }

    public function testPIISafeBackslashEscapingTwoByteUtf8() : void
    {
        // utf8CharLength returns 2 for 0xC0-0xDF lead byte
        $result = Tools::makeQuotedStringPIISafe("SELECT 'a\\é b'") ;
        $this->assertStringNotContainsString('foo', $result) ;
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeBackslashEscapingThreeByteUtf8() : void
    {
        // utf8CharLength returns 3 for 0xE0-0xEF lead byte (CJK)
        $result = Tools::makeQuotedStringPIISafe("SELECT 'a\\中 b'") ;
        $this->assertStringContainsString("'S'", $result) ;
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeBackslashEscapingFourByteUtf8() : void
    {
        // utf8CharLength returns 4 for 0xF0+ lead byte (emoji, supplementary plane)
        $result = Tools::makeQuotedStringPIISafe("SELECT 'a\\🎉 b'") ;
        $this->assertStringContainsString("'S'", $result) ;
        $this->assertTrue(mb_check_encoding($result, 'UTF-8')) ;
    }

    public function testPIISafeStrayContinuationByte() : void
    {
        // utf8CharLength: the 0x80-0xBF "stray continuation byte" branch
        // Construct a string with a backslash followed by an isolated
        // continuation byte (invalid UTF-8 but should not crash)
        $strayByte = chr(0x80) ; // continuation byte alone
        $result = Tools::makeQuotedStringPIISafe("SELECT 'a\\" . $strayByte . "b'") ;
        $this->assertIsString($result) ;
    }

    public function testUtf8CharLengthPastEnd() : void
    {
        // utf8CharLength: the $i >= strlen branch (return 0)
        // Only reachable via direct call - the public callers always check
        // bounds first. Test via reflection to lock in the contract.
        $ref = new \ReflectionMethod(Tools::class, 'utf8CharLength') ;
        $ref->setAccessible(true) ;
        $this->assertSame(0, $ref->invoke(null, 'abc', 3)) ; // index == length
        $this->assertSame(0, $ref->invoke(null, 'abc', 99)) ; // index past length
    }

    public function testUtf8CharLengthAllBranches() : void
    {
        // Lock in the byte-length contract for every UTF-8 sequence type.
        $ref = new \ReflectionMethod(Tools::class, 'utf8CharLength') ;
        $ref->setAccessible(true) ;
        $this->assertSame(1, $ref->invoke(null, 'A', 0), 'ASCII = 1 byte') ;
        $this->assertSame(2, $ref->invoke(null, 'é', 0), '2-byte UTF-8') ;
        $this->assertSame(3, $ref->invoke(null, '中', 0), '3-byte UTF-8') ;
        $this->assertSame(4, $ref->invoke(null, '🎉', 0), '4-byte UTF-8') ;
        $this->assertSame(1, $ref->invoke(null, chr(0x80), 0), 'stray continuation byte = 1') ;
    }

    // NOTE: Tools::pr() and Tools::vd() each have a `if ($die) exit();`
    // branch that cannot be tested in-process - calling exit() would
    // terminate the test runner. These two lines will always show as
    // uncovered. Leaving them this way is the right call - refactoring to
    // inject the exit function would add complexity for no real benefit.
}
