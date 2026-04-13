<?php

namespace com\kbcmdba\aql\Tests\Libs\Controllers ;

use com\kbcmdba\aql\Libs\Controllers\HostController ;
use com\kbcmdba\aql\Libs\Exceptions\ControllerException ;
use com\kbcmdba\aql\Libs\Models\HostModel ;
use PHPUnit\Framework\TestCase ;

/**
 * Integration tests for Libs/Controllers/HostController.php
 *
 * Tests full CRUD against the real aql_db. Each test that creates data
 * cleans up after itself. Uses a unique hostname prefix to avoid
 * colliding with real monitored hosts.
 *
 * These tests also found Bug #7: all three concrete controllers referenced
 * $this->_dbh (with underscore) but ControllerBase declares $this->dbh
 * (without). Every controller method would have failed at runtime.
 */
class HostControllerTest extends TestCase
{
    private static string $testHostPrefix = '__phpunit_test_host_' ;
    private ?HostController $controller = null ;

    protected function setUp() : void
    {
        try {
            $this->controller = new HostController( 'write' ) ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Database not available: ' . $e->getMessage() ) ;
        }
        // Set up $_REQUEST for model validation (validateForAdd uses Tools::param)
        $_REQUEST = [] ;
    }

    protected function tearDown() : void
    {
        $_REQUEST = [] ;
        // Clean up any test hosts that survived a failed test
        if ( $this->controller !== null ) {
            try {
                $all = $this->controller->getAll() ;
                foreach ( $all as $model ) {
                    if ( str_starts_with( $model->getHostName(), self::$testHostPrefix ) ) {
                        $model->setId( $model->getId() ) ;
                        $this->controller->delete( $model ) ;
                    }
                }
            } catch ( \Exception $e ) {
                // Best effort cleanup
            }
        }
    }

    /**
     * Helper: set $_REQUEST values needed by validateForAdd
     */
    private function setRequestForAdd( string $hostname, int $port = 3306 ) : void
    {
        $_REQUEST = [
            'hostName'         => $hostname,
            'portNumber'       => (string) $port,
            'description'      => 'PHPUnit test host',
            'shouldMonitor'    => '0',
            'shouldBackup'     => '0',
            'shouldSchemaspy'  => '0',
            'revenueImpacting' => '0',
            'decommissioned'   => '1',
            'alertCritSecs'    => '60',
            'alertWarnSecs'    => '30',
            'alertInfoSecs'    => '10',
            'alertLowSecs'     => '0',
        ] ;
    }

    /**
     * Helper: create a HostModel populated from current $_REQUEST
     */
    private function buildModelFromRequest() : HostModel
    {
        $model = new HostModel() ;
        $model->populateFromForm() ;
        return $model ;
    }

    // ========================================================================
    // Constructor
    // ========================================================================

    public function testConstructorSucceeds() : void
    {
        $this->assertInstanceOf( HostController::class, $this->controller ) ;
    }

    // ========================================================================
    // CRUD cycle: add → get → update → delete
    // ========================================================================

    public function testAddAndGetHost() : void
    {
        $hostname = self::$testHostPrefix . uniqid() ;
        $this->setRequestForAdd( $hostname ) ;
        $model = $this->buildModelFromRequest() ;

        $newId = $this->controller->add( $model ) ;
        $this->assertIsInt( $newId ) ;
        $this->assertGreaterThan( 0, $newId ) ;

        // Retrieve it
        $fetched = $this->controller->get( $newId ) ;
        $this->assertInstanceOf( HostModel::class, $fetched ) ;
        $this->assertSame( $hostname, $fetched->getHostName() ) ;
        $this->assertSame( 'PHPUnit test host', $fetched->getDescription() ) ;
        $this->assertSame( 0, $fetched->getShouldMonitor() ) ;
        $this->assertSame( 1, $fetched->getDecommissioned() ) ;

        // Cleanup
        $fetched->setId( $newId ) ;
        $this->controller->delete( $fetched ) ;
    }

    public function testGetNonExistentHostReturnsNull() : void
    {
        $result = $this->controller->get( 999999999 ) ;
        $this->assertNull( $result ) ;
    }

    public function testGetAllReturnsArray() : void
    {
        $all = $this->controller->getAll() ;
        $this->assertIsArray( $all ) ;
        // May be empty if no hosts configured, but should be an array
    }

    public function testGetSomeWithFilters() : void
    {
        // Create a test host
        $hostname = self::$testHostPrefix . uniqid() ;
        $this->setRequestForAdd( $hostname ) ;
        $model = $this->buildModelFromRequest() ;
        $newId = $this->controller->add( $model ) ;

        // Find it by hostname
        $results = $this->controller->getSome( [ 'hostname' => $hostname ] ) ;
        $this->assertCount( 1, $results ) ;
        $this->assertSame( $hostname, $results[0]->getHostName() ) ;

        // Find by decommissioned=1 (our test host has this)
        $decomResults = $this->controller->getSome( [ 'decommissioned' => 1 ] ) ;
        $found = false ;
        foreach ( $decomResults as $h ) {
            if ( $h->getHostName() === $hostname ) {
                $found = true ;
                break ;
            }
        }
        $this->assertTrue( $found, 'Test host should appear in decommissioned filter' ) ;

        // Cleanup
        $model->setId( $newId ) ;
        $_REQUEST['id'] = (string) $newId ;
        $this->controller->delete( $model ) ;
    }

    public function testGetSomeRejectsInvalidFilterColumn() : void
    {
        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid filter column/' ) ;
        $this->controller->getSome( [ 'nonexistent_column' => 'value' ] ) ;
    }

    public function testDeleteHost() : void
    {
        // Create, then delete, then verify gone
        $hostname = self::$testHostPrefix . uniqid() ;
        $this->setRequestForAdd( $hostname ) ;
        $model = $this->buildModelFromRequest() ;
        $newId = $this->controller->add( $model ) ;

        $fetched = $this->controller->get( $newId ) ;
        $this->assertNotNull( $fetched ) ;

        $this->controller->delete( $fetched ) ;

        $afterDelete = $this->controller->get( $newId ) ;
        $this->assertNull( $afterDelete, 'Host should be gone after delete' ) ;
    }

    public function testAddInvalidDataThrows() : void
    {
        // validateForAdd requires hostName — don't set it
        $_REQUEST = [
            'alertCritSecs' => '60',
            'alertWarnSecs' => '30',
            'alertInfoSecs' => '10',
            'alertLowSecs'  => '0',
        ] ;
        $model = $this->buildModelFromRequest() ;

        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->controller->add( $model ) ;
    }

    public function testUpdateHost() : void
    {
        $hostname = self::$testHostPrefix . uniqid() ;
        $this->setRequestForAdd( $hostname ) ;
        $model = $this->buildModelFromRequest() ;
        $newId = $this->controller->add( $model ) ;

        // Update it
        $updatedHostname = self::$testHostPrefix . uniqid() ;
        $_REQUEST = [
            'id'               => (string) $newId,
            'hostName'         => $updatedHostname,
            'portNumber'       => '5432',
            'description'      => 'Updated description',
            'shouldMonitor'    => '0',
            'shouldBackup'     => '0',
            'shouldSchemaspy'  => '0',
            'revenueImpacting' => '0',
            'decommissioned'   => '1',
            'alertCritSecs'    => '120',
            'alertWarnSecs'    => '60',
            'alertInfoSecs'    => '30',
            'alertLowSecs'     => '5',
        ] ;
        $updateModel = $this->buildModelFromRequest() ;
        $updateModel->setId( $newId ) ;
        $returnedId = $this->controller->update( $updateModel ) ;
        $this->assertSame( $newId, $returnedId ) ;

        // Verify
        $fetched = $this->controller->get( $newId ) ;
        $this->assertSame( $updatedHostname, $fetched->getHostName() ) ;
        $this->assertSame( 5432, $fetched->getPortNumber() ) ;
        $this->assertSame( 'Updated description', $fetched->getDescription() ) ;

        // Cleanup
        $this->controller->delete( $fetched ) ;
    }

    public function testUpdateHostInvalidDataThrows() : void
    {
        // validateForUpdate requires id + hostName — don't set them
        $_REQUEST = [
            'alertCritSecs' => '60',
            'alertWarnSecs' => '30',
            'alertInfoSecs' => '10',
            'alertLowSecs'  => '0',
        ] ;
        $model = $this->buildModelFromRequest() ;

        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->controller->update( $model ) ;
    }

    public function testDeleteInvalidModelThrows() : void
    {
        // Model with no id — validateForDelete() returns false → deleteModelById() throws
        $model = new HostModel() ;
        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->controller->delete( $model ) ;
    }

    // ========================================================================
    // DDL methods
    // ========================================================================

    public function testCreateTableDoesNotCrash() : void
    {
        // Uses IF NOT EXISTS — safe to call even when table already exists.
        // Exercises doDDL() success path in ControllerBase.
        $this->controller->createTable() ;
        $this->assertTrue( true ) ;
    }

    public function testDropTriggersDoesNotCrash() : void
    {
        // dropTriggers is a no-op stub
        $this->controller->dropTriggers() ;
        $this->assertTrue( true ) ;
    }

    public function testCreateTriggersDoesNotCrash() : void
    {
        // createTriggers is a no-op stub
        $this->controller->createTriggers() ;
        $this->assertTrue( true ) ;
    }
}
