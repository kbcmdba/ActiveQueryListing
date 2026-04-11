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
}
