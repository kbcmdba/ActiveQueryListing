<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\Exceptions\DaoException ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/DBConnection.php
 *
 * Integration tests against the real MySQL at mysql1.hole. Tests are skipped
 * if the database is unavailable.
 */
class DBConnectionTest extends TestCase
{
    private function getDbcOrSkip( string $connClass = 'mysqli' ) : DBConnection
    {
        try {
            return new DBConnection( null, null, null, null, null, null, null, $connClass ) ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Database not available: ' . $e->getMessage() ) ;
        }
    }

    // ========================================================================
    // Constructor + getConnection() — mysqli path
    // ========================================================================

    public function testMysqliConnectionSucceeds() : void
    {
        $dbc = $this->getDbcOrSkip() ;
        $dbh = $dbc->getConnection() ;
        $this->assertInstanceOf( \mysqli::class, $dbh ) ;
    }

    public function testMysqliConnectionClassIsMysqli() : void
    {
        $dbc = $this->getDbcOrSkip() ;
        $this->assertSame( 'mysqli', $dbc->getConnectionClass() ) ;
    }

    public function testCreatedDbDefaultsFalse() : void
    {
        $dbc = $this->getDbcOrSkip() ;
        // Normal connection to existing DB should not create a new one
        $this->assertNull( $dbc->getCreatedDb() ) ;
    }

    // ========================================================================
    // Constructor — PDO path
    // ========================================================================

    public function testPdoConnectionSucceeds() : void
    {
        $dbc = $this->getDbcOrSkip( 'PDO' ) ;
        $dbh = $dbc->getConnection() ;
        $this->assertInstanceOf( \PDO::class, $dbh ) ;
    }

    public function testPdoConnectionClassIsPDO() : void
    {
        $dbc = $this->getDbcOrSkip( 'PDO' ) ;
        $this->assertSame( 'PDO', $dbc->getConnectionClass() ) ;
    }

    // ========================================================================
    // Constructor — error paths
    // ========================================================================

    public function testUnknownConnectionClassThrows() : void
    {
        $this->expectException( DaoException::class ) ;
        $this->expectExceptionMessageMatches( '/Unknown connection class/' ) ;
        new DBConnection( null, null, null, null, null, null, null, 'totally_fake' ) ;
    }

    public function testMsSqlConnectionClassThrows() : void
    {
        $this->expectException( DaoException::class ) ;
        $this->expectExceptionMessageMatches( '/not implemented/' ) ;
        new DBConnection( null, null, null, null, null, null, null, 'MS-SQL' ) ;
    }

    // ========================================================================
    // myErrorHandler — static, just returns (suppresses warnings during connect)
    // ========================================================================

    public function testMyErrorHandlerReturnsNull() : void
    {
        // The error handler is a no-op that suppresses PHP warnings during
        // the mysqli real_connect call. Verify it doesn't crash.
        $result = DBConnection::myErrorHandler() ;
        $this->assertNull( $result ) ;
    }

    // ========================================================================
    // __toString
    // ========================================================================

    public function testToStringWithConnection() : void
    {
        $dbc = $this->getDbcOrSkip() ;
        $str = (string) $dbc ;
        // __toString returns $this->oConfig which is a Config object,
        // and Config::__toString now returns a masked summary
        $this->assertStringContainsString( 'security', $str ) ;
    }

    public function testToStringWithoutConnection() : void
    {
        // Create an instance via reflection without calling the constructor
        // so dbh is null
        $ref = new \ReflectionClass( DBConnection::class ) ;
        $dbc = $ref->newInstanceWithoutConstructor() ;
        $str = (string) $dbc ;
        $this->assertSame( 'Not connected.', $str ) ;
    }

    public function testGetConnectionThrowsWhenNotConnected() : void
    {
        $ref = new \ReflectionClass( DBConnection::class ) ;
        $dbc = $ref->newInstanceWithoutConstructor() ;
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid connection/' ) ;
        $dbc->getConnection() ;
    }

    public function testGetCreatedDbNullByDefault() : void
    {
        $ref = new \ReflectionClass( DBConnection::class ) ;
        $dbc = $ref->newInstanceWithoutConstructor() ;
        $this->assertNull( $dbc->getCreatedDb() ) ;
    }
}
