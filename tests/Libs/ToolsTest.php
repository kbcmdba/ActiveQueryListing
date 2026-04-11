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
    // makeQuotedStringPIISafe() — query normalization for blocking_history
    // ========================================================================

    public function testMakeQuotedStringPIISafeReplacesIntegers() : void
    {
        $sql = "SELECT * FROM users WHERE id = 12345" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringNotContainsString('12345', $result) ;
        $this->assertStringContainsString('N', $result) ;
    }

    public function testMakeQuotedStringPIISafeReplacesQuotedStrings() : void
    {
        $sql = "SELECT * FROM users WHERE name = 'kbenton'" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringNotContainsString('kbenton', $result) ;
    }

    public function testMakeQuotedStringPIISafePreservesStructure() : void
    {
        // The query should still have the SELECT and WHERE keywords
        $sql = "SELECT id, name FROM users WHERE active = 1 AND created > '2024-01-01'" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringContainsString('SELECT', $result) ;
        $this->assertStringContainsString('FROM users', $result) ;
        $this->assertStringContainsString('WHERE', $result) ;
        $this->assertStringContainsString('AND created >', $result) ;
    }

    public function testMakeQuotedStringPIISafeHandlesHexLiterals() : void
    {
        $sql = "SELECT * FROM t WHERE blob_col = 0xDEADBEEF" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringNotContainsString('DEADBEEF', $result) ;
    }

    // NOTE: makeQuotedStringPIISafe has a known issue with backslash-escaped
    // quotes inside string literals - the segmentation strips the backslash
    // but then leaves the inner text exposed. See @todo 28-50 for the fix.

    public function testMakeQuotedStringPIISafeHandlesEmptyQuotes() : void
    {
        // Empty single quotes get replaced with 'S'
        $sql = "SELECT * FROM t WHERE name = ''" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringContainsString("'S'", $result) ;
    }

    public function testMakeQuotedStringPIISafeHandlesDoubleQuotedStrings() : void
    {
        $sql = 'SELECT * FROM t WHERE name = "secret_user"' ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringNotContainsString('secret_user', $result) ;
    }

    public function testMakeQuotedStringPIISafeWrapsLikePatterns() : void
    {
        // LIKE patterns get wrapped in % markers
        $sql = "SELECT * FROM t WHERE name LIKE '%foo%'" ;
        $result = Tools::makeQuotedStringPIISafe($sql) ;
        $this->assertStringNotContainsString('foo', $result) ;
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
}
