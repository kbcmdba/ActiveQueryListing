<?php

namespace com\kbcmdba\aql\Tests\Libs\Controllers ;

use com\kbcmdba\aql\Libs\Controllers\HostController ;
use com\kbcmdba\aql\Libs\Controllers\HostGroupController ;
use com\kbcmdba\aql\Libs\Controllers\HostGroupMapController ;
use com\kbcmdba\aql\Libs\Exceptions\ControllerException ;
use com\kbcmdba\aql\Libs\Models\HostGroupMapModel ;
use com\kbcmdba\aql\Libs\Models\HostGroupModel ;
use com\kbcmdba\aql\Libs\Models\HostModel ;
use PHPUnit\Framework\TestCase ;

/**
 * Integration tests for Libs/Controllers/HostGroupMapController.php
 *
 * This is a join table controller — we need real host and host_group rows
 * to create mappings. Tests create prerequisite rows, test the mapping CRUD,
 * and clean up everything in tearDown.
 *
 * Found Bug #13: get() used new HostGroupModel() instead of new HostGroupMapModel()
 * Found Bug #14: get() called setLastUpdated(\$lastUpdated) — wrong method AND
 *                wrong variable. Should be setLastAudited(\$lastAudited).
 */
class HostGroupMapControllerTest extends TestCase
{
    private ?HostGroupMapController $mapController = null ;
    private ?HostController $hostController = null ;
    private ?HostGroupController $groupController = null ;
    private array $createdHostIds = [] ;
    private array $createdGroupIds = [] ;

    protected function setUp() : void
    {
        try {
            $this->mapController = new HostGroupMapController( 'write' ) ;
            $this->hostController = new HostController( 'write' ) ;
            $this->groupController = new HostGroupController( 'write' ) ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Database not available: ' . $e->getMessage() ) ;
        }
        $_REQUEST = [] ;
    }

    protected function tearDown() : void
    {
        // Clean up mappings first (FK constraints), then groups and hosts
        if ( $this->mapController !== null ) {
            try {
                foreach ( $this->createdGroupIds as $groupId ) {
                    $maps = $this->mapController->getSome( [ 'host_group_id' => $groupId ] ) ;
                    foreach ( $maps as $map ) {
                        $this->mapController->delete( $map ) ;
                    }
                }
            } catch ( \Exception $e ) { /* best effort */ }
        }
        if ( $this->groupController !== null ) {
            foreach ( $this->createdGroupIds as $groupId ) {
                try {
                    $g = $this->groupController->get( $groupId ) ;
                    if ( $g ) { $this->groupController->delete( $g ) ; }
                } catch ( \Exception $e ) { /* best effort */ }
            }
        }
        if ( $this->hostController !== null ) {
            foreach ( $this->createdHostIds as $hostId ) {
                try {
                    $h = $this->hostController->get( $hostId ) ;
                    if ( $h ) { $this->hostController->delete( $h ) ; }
                } catch ( \Exception $e ) { /* best effort */ }
            }
        }
        $_REQUEST = [] ;
        $this->createdHostIds = [] ;
        $this->createdGroupIds = [] ;
    }

    /**
     * Helper: create a test host, track for cleanup
     */
    private function createTestHost() : int
    {
        $_REQUEST = [
            'hostName'      => '__phpunit_map_' . substr( uniqid(), -8 ),
            'portNumber'    => '3306',
            'description'   => 'Map test host',
            'shouldMonitor' => '0',
            'shouldBackup'  => '0',
            'revenueImpacting' => '0',
            'decommissioned'   => '1',
            'alertCritSecs' => '60',
            'alertWarnSecs' => '30',
            'alertInfoSecs' => '10',
            'alertLowSecs'  => '0',
        ] ;
        $model = new HostModel() ;
        $model->populateFromForm() ;
        $id = $this->hostController->add( $model ) ;
        $this->createdHostIds[] = $id ;
        return $id ;
    }

    /**
     * Helper: create a test group, track for cleanup
     */
    private function createTestGroup() : int
    {
        $_REQUEST = [
            'tag'              => '_m' . substr( uniqid(), -10 ),
            'shortDescription' => 'Map test group',
            'fullDescription'  => '',
        ] ;
        $model = new HostGroupModel() ;
        $model->populateFromForm() ;
        $id = $this->groupController->add( $model ) ;
        $this->createdGroupIds[] = $id ;
        return $id ;
    }

    // ========================================================================
    // Constructor
    // ========================================================================

    public function testConstructorSucceeds() : void
    {
        $this->assertInstanceOf( HostGroupMapController::class, $this->mapController ) ;
    }

    // ========================================================================
    // CRUD cycle
    // ========================================================================

    public function testAddAndGetMapping() : void
    {
        $hostId = $this->createTestHost() ;
        $groupId = $this->createTestGroup() ;

        $_REQUEST = [
            'hostGroupId' => (string) $groupId,
            'hostId'      => (string) $hostId,
        ] ;
        $model = new HostGroupMapModel() ;
        $model->populateFromForm() ;
        $this->mapController->add( $model ) ;

        // Retrieve it — this is the Bug #13/#14 regression test
        $fetched = $this->mapController->get( $groupId, $hostId ) ;
        $this->assertInstanceOf( HostGroupMapModel::class, $fetched,
            'Bug #13 regression: get() must return HostGroupMapModel, not HostGroupModel' ) ;
        $this->assertSame( $groupId, (int) $fetched->getHostGroupId() ) ;
        $this->assertSame( $hostId, (int) $fetched->getHostId() ) ;
    }

    public function testGetNonExistentMappingReturnsNull() : void
    {
        $result = $this->mapController->get( 999999, 999999 ) ;
        $this->assertNull( $result ) ;
    }

    public function testGetAllReturnsArray() : void
    {
        $all = $this->mapController->getAll() ;
        $this->assertIsArray( $all ) ;
    }

    public function testGetSomeByGroupId() : void
    {
        $hostId = $this->createTestHost() ;
        $groupId = $this->createTestGroup() ;

        $_REQUEST = [
            'hostGroupId' => (string) $groupId,
            'hostId'      => (string) $hostId,
        ] ;
        $model = new HostGroupMapModel() ;
        $model->populateFromForm() ;
        $this->mapController->add( $model ) ;

        $results = $this->mapController->getSome( [ 'host_group_id' => $groupId ] ) ;
        $this->assertCount( 1, $results ) ;
        $this->assertSame( $hostId, (int) $results[0]->getHostId() ) ;
    }

    public function testGetSomeRejectsInvalidFilterColumn() : void
    {
        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid filter column/' ) ;
        $this->mapController->getSome( [ 'nonexistent' => 'value' ] ) ;
    }

    public function testDeleteMapping() : void
    {
        $hostId = $this->createTestHost() ;
        $groupId = $this->createTestGroup() ;

        $_REQUEST = [
            'hostGroupId' => (string) $groupId,
            'hostId'      => (string) $hostId,
        ] ;
        $model = new HostGroupMapModel() ;
        $model->populateFromForm() ;
        $this->mapController->add( $model ) ;

        $fetched = $this->mapController->get( $groupId, $hostId ) ;
        $this->assertNotNull( $fetched ) ;

        $this->mapController->delete( $fetched ) ;

        $afterDelete = $this->mapController->get( $groupId, $hostId ) ;
        $this->assertNull( $afterDelete, 'Mapping should be gone after delete' ) ;
    }

    public function testAddInvalidDataThrows() : void
    {
        $_REQUEST = [] ;
        $model = new HostGroupMapModel() ;
        $model->populateFromForm() ;

        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->mapController->add( $model ) ;
    }

    public function testUpdateMapping() : void
    {
        $hostId = $this->createTestHost() ;
        $groupId = $this->createTestGroup() ;

        $_REQUEST = [
            'hostGroupId' => (string) $groupId,
            'hostId'      => (string) $hostId,
        ] ;
        $model = new HostGroupMapModel() ;
        $model->populateFromForm() ;
        $this->mapController->add( $model ) ;

        // Update it — only last_audited can be changed on this join table
        $newAudited = date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) ;
        $_REQUEST = [
            'hostGroupId' => (string) $groupId,
            'hostId'      => (string) $hostId,
            'lastAudited' => $newAudited,
        ] ;
        $updateModel = new HostGroupMapModel() ;
        $updateModel->populateFromForm() ;
        $result = $this->mapController->update( $updateModel ) ;
        $this->assertSame( 0, $result ) ;

        // Verify the row still exists
        $fetched = $this->mapController->get( $groupId, $hostId ) ;
        $this->assertInstanceOf( HostGroupMapModel::class, $fetched ) ;
    }

    public function testUpdateMappingInvalidDataThrows() : void
    {
        // validateForUpdate = validateForAdd: requires hostGroupId + hostId
        $_REQUEST = [] ;
        $model = new HostGroupMapModel() ;
        $model->populateFromForm() ;

        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->mapController->update( $model ) ;
    }

    // ========================================================================
    // DDL stubs
    // ========================================================================

    public function testDropTriggersDoesNotCrash() : void
    {
        $this->mapController->dropTriggers() ;
        $this->assertTrue( true ) ;
    }

    public function testCreateTriggersDoesNotCrash() : void
    {
        $this->mapController->createTriggers() ;
        $this->assertTrue( true ) ;
    }
}
