<?php

namespace com\kbcmdba\aql\Tests\Libs\Exceptions ;

use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;
use com\kbcmdba\aql\Libs\Exceptions\ControllerException ;
use com\kbcmdba\aql\Libs\Exceptions\DaoException ;
use com\kbcmdba\aql\Libs\Exceptions\WebPageException ;
use PHPUnit\Framework\Attributes\DataProvider ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for the AQL exception classes. They're bare \Exception subclasses,
 * so the tests just verify they exist, extend \Exception, can be thrown,
 * and preserve their messages.
 */
class ExceptionsTest extends TestCase
{
    public static function exceptionClassProvider() : array
    {
        return [
            'ConfigurationException' => [ ConfigurationException::class ],
            'ControllerException'    => [ ControllerException::class ],
            'DaoException'           => [ DaoException::class ],
            'WebPageException'       => [ WebPageException::class ],
        ] ;
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionExtendsBaseException( string $class ) : void
    {
        $this->assertTrue(
            is_subclass_of( $class, \Exception::class ),
            "$class should extend \\Exception"
        ) ;
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionCanBeThrown( string $class ) : void
    {
        $this->expectException( $class ) ;
        throw new $class( 'test message' ) ;
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionPreservesMessage( string $class ) : void
    {
        try {
            throw new $class( 'unique message text' ) ;
        } catch ( \Exception $e ) {
            $this->assertSame( 'unique message text', $e->getMessage() ) ;
        }
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionPreservesCode( string $class ) : void
    {
        try {
            throw new $class( 'msg', 42 ) ;
        } catch ( \Exception $e ) {
            $this->assertSame( 42, $e->getCode() ) ;
        }
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExceptionPreservesPrevious( string $class ) : void
    {
        $prev = new \RuntimeException( 'inner' ) ;
        try {
            throw new $class( 'outer', 0, $prev ) ;
        } catch ( \Exception $e ) {
            $this->assertSame( $prev, $e->getPrevious() ) ;
        }
    }
}
