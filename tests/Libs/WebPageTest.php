<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\Exceptions\WebPageException ;
use com\kbcmdba\aql\Libs\WebPage ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/WebPage.php
 *
 * Focus on the testable surface: constructor defaults, getters/setters,
 * __toString() output shape, meta validation, and the optional non-HTML
 * path. The getNavBar() method requires a real Config object reading the
 * actual aql_config.xml, so it's exercised indirectly via __toString()
 * (which just gets used as part of the output) but not unit-tested in
 * isolation - integration tests cover it via the real pages.
 */
class WebPageTest extends TestCase
{
    // ========================================================================
    // Constructor defaults
    // ========================================================================

    public function testConstructorSetsTitle() : void
    {
        $p = new WebPage( 'My Page' ) ;
        $this->assertSame( 'My Page', $p->getPageTitle() ) ;
    }

    public function testConstructorEmptyTitle() : void
    {
        $p = new WebPage() ;
        $this->assertSame( '', $p->getPageTitle() ) ;
    }

    public function testConstructorDefaultMimeTypeIsHtml() : void
    {
        $p = new WebPage( 'x' ) ;
        $this->assertSame( 'text/html', $p->getMimeType() ) ;
    }

    public function testConstructorPopulatesMeta() : void
    {
        $p = new WebPage( 'x' ) ;
        $meta = $p->getMeta() ;
        $this->assertIsArray( $meta ) ;
        $this->assertNotEmpty( $meta ) ;
    }

    public function testConstructorPopulatesHeadWithStandardAssets() : void
    {
        $p = new WebPage( 'x' ) ;
        $head = $p->getHead() ;
        // The constructor should include standard CSS and JS references
        $this->assertStringContainsString( 'css/main.css', $head ) ;
        $this->assertStringContainsString( 'js/common.js', $head ) ;
        $this->assertStringContainsString( 'jquery', $head ) ;
        $this->assertStringContainsString( 'bootstrap', $head ) ;
    }

    public function testConstructorEmptyTopBodyBottom() : void
    {
        $p = new WebPage( 'x' ) ;
        $this->assertSame( '', $p->getTop() ) ;
        $this->assertSame( '', $p->getBody() ) ;
        $this->assertSame( '', $p->getBottom() ) ;
    }

    // ========================================================================
    // Getters and setters
    // ========================================================================

    public function testPageTitleGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setPageTitle( 'New Title' ) ;
        $this->assertSame( 'New Title', $p->getPageTitle() ) ;
    }

    public function testMimeTypeGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setMimeType( 'application/json' ) ;
        $this->assertSame( 'application/json', $p->getMimeType() ) ;
    }

    public function testHeadGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setHead( '<meta name="custom" content="x">' ) ;
        $this->assertSame( '<meta name="custom" content="x">', $p->getHead() ) ;
    }

    public function testStylesGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setStyles( '<style>body { color: red; }</style>' ) ;
        $this->assertSame( '<style>body { color: red; }</style>', $p->getStyles() ) ;
    }

    public function testTopGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setTop( '<header>Top</header>' ) ;
        $this->assertSame( '<header>Top</header>', $p->getTop() ) ;
    }

    public function testBodyGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setBody( '<p>Hello</p>' ) ;
        $this->assertSame( '<p>Hello</p>', $p->getBody() ) ;
    }

    public function testAppendBody() : void
    {
        $p = new WebPage() ;
        $p->setBody( '<p>One</p>' ) ;
        $p->appendBody( '<p>Two</p>' ) ;
        $this->assertSame( '<p>One</p><p>Two</p>', $p->getBody() ) ;
    }

    public function testBottomGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setBottom( '<footer>Bottom</footer>' ) ;
        $this->assertSame( '<footer>Bottom</footer>', $p->getBottom() ) ;
    }

    public function testDataGetterSetter() : void
    {
        $p = new WebPage() ;
        $p->setData( '{"key":"value"}' ) ;
        $this->assertSame( '{"key":"value"}', $p->getData() ) ;
    }

    // ========================================================================
    // setMeta() / appendMeta() validation
    // ========================================================================

    public function testSetMetaAcceptsArray() : void
    {
        $p = new WebPage() ;
        $p->setMeta( [ 'X-Custom: foo', 'Cache-Control: no-cache' ] ) ;
        $this->assertSame( [ 'X-Custom: foo', 'Cache-Control: no-cache' ], $p->getMeta() ) ;
    }

    public function testSetMetaRejectsNonArray() : void
    {
        $p = new WebPage() ;
        $this->expectException( WebPageException::class ) ;
        $this->expectExceptionMessageMatches( '/setMeta requires an array/' ) ;
        $p->setMeta( 'not an array' ) ;
    }

    public function testAppendMetaAddsToList() : void
    {
        $p = new WebPage() ;
        $p->setMeta( [ 'first' ] ) ;
        $p->appendMeta( 'second' ) ;
        $meta = $p->getMeta() ;
        $this->assertContains( 'first', $meta ) ;
        $this->assertContains( 'second', $meta ) ;
    }

    public function testAppendMetaRejectsNonString() : void
    {
        $p = new WebPage() ;
        $p->setMeta( [] ) ;
        $this->expectException( WebPageException::class ) ;
        $this->expectExceptionMessageMatches( '/Improper usage of appendMeta/' ) ;
        $p->appendMeta( [ 'array', 'instead', 'of', 'string' ] ) ;
    }

    // ========================================================================
    // __toString() — HTML mime type path
    // ========================================================================

    public function testToStringHtmlIncludesDoctype() : void
    {
        $p = new WebPage( 'Test' ) ;
        $this->assertStringContainsString( '<!DOCTYPE HTML>', (string) $p ) ;
    }

    public function testToStringHtmlIncludesTitle() : void
    {
        $p = new WebPage( 'My Page' ) ;
        $this->assertStringContainsString( '<title>My Page</title>', (string) $p ) ;
    }

    public function testToStringHtmlIncludesBody() : void
    {
        $p = new WebPage( 'x' ) ;
        $p->setBody( '<p>Test body content</p>' ) ;
        $this->assertStringContainsString( '<p>Test body content</p>', (string) $p ) ;
    }

    public function testToStringHtmlIncludesTopAndBottom() : void
    {
        $p = new WebPage( 'x' ) ;
        $p->setTop( '<div id="top">Top</div>' ) ;
        $p->setBottom( '<div id="bottom">Bottom</div>' ) ;
        $output = (string) $p ;
        $this->assertStringContainsString( '<div id="top">Top</div>', $output ) ;
        $this->assertStringContainsString( '<div id="bottom">Bottom</div>', $output ) ;
    }

    public function testToStringHtmlOrderTopBeforeBody() : void
    {
        $p = new WebPage( 'x' ) ;
        $p->setTop( 'TOPMARKER' ) ;
        $p->setBody( 'BODYMARKER' ) ;
        $p->setBottom( 'BOTTOMMARKER' ) ;
        $output = (string) $p ;
        $topPos = strpos( $output, 'TOPMARKER' ) ;
        $bodyPos = strpos( $output, 'BODYMARKER' ) ;
        $bottomPos = strpos( $output, 'BOTTOMMARKER' ) ;
        $this->assertLessThan( $bodyPos, $topPos, 'top should appear before body' ) ;
        $this->assertLessThan( $bottomPos, $bodyPos, 'body should appear before bottom' ) ;
    }

    public function testToStringHtmlIncludesEndOfPageMarker() : void
    {
        $p = new WebPage( 'x' ) ;
        $this->assertStringContainsString( '<!-- EndOfPage -->', (string) $p ) ;
    }

    public function testToStringHtmlIncludesStyles() : void
    {
        $p = new WebPage( 'x' ) ;
        $p->setStyles( '<style id="custom">body { background: red; }</style>' ) ;
        $this->assertStringContainsString( '<style id="custom">', (string) $p ) ;
    }

    // ========================================================================
    // __toString() — non-HTML path returns raw data
    // ========================================================================

    public function testToStringJsonReturnsData() : void
    {
        $p = new WebPage() ;
        $p->setMimeType( 'application/json' ) ;
        $p->setData( '{"result":"ok"}' ) ;
        $this->assertSame( '{"result":"ok"}', (string) $p ) ;
    }

    public function testToStringJsonIgnoresHtmlBits() : void
    {
        $p = new WebPage( 'irrelevant' ) ;
        $p->setMimeType( 'application/json' ) ;
        $p->setBody( '<p>this should not appear</p>' ) ;
        $p->setData( '{"only":"data"}' ) ;
        $output = (string) $p ;
        $this->assertSame( '{"only":"data"}', $output ) ;
        $this->assertStringNotContainsString( '<p>', $output ) ;
        $this->assertStringNotContainsString( 'DOCTYPE', $output ) ;
    }

    public function testToStringPlainTextReturnsData() : void
    {
        $p = new WebPage() ;
        $p->setMimeType( 'text/plain' ) ;
        $p->setData( 'just some text' ) ;
        $this->assertSame( 'just some text', (string) $p ) ;
    }
}
