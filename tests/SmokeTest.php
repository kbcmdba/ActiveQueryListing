<?php

namespace com\kbcmdba\aql\Tests ;

use PHPUnit\Framework\TestCase ;

/**
 * Smoke test - verifies the PHPUnit bootstrap is working.
 * Delete this file once real tests start landing.
 */
class SmokeTest extends TestCase
{
    public function testBootstrapWorks() : void
    {
        $this->assertTrue( true ) ;
    }

    public function testAutoloaderResolves() : void
    {
        $this->assertTrue(
            class_exists( 'com\kbcmdba\aql\Libs\Config' ),
            'Config class should be autoloadable from Libs/'
        ) ;
    }
}
