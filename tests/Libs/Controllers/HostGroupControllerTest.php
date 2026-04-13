<?php

namespace com\kbcmdba\aql\Tests\Libs\Controllers ;

use com\kbcmdba\aql\Libs\Controllers\HostGroupController ;
use com\kbcmdba\aql\Libs\Exceptions\ControllerException ;
use com\kbcmdba\aql\Libs\Models\HostGroupModel ;
use PHPUnit\Framework\TestCase ;

/**
 * Integration tests for Libs/Controllers/HostGroupController.php
 *
 * Found Bug #11: get() was missing setTag() — tag fetched from DB but
 * never set on the returned model.
 * Found Bug #12: createTable DDL had typo 'full_descripton' (missing 'i').
 */
class HostGroupControllerTest extends TestCase
{
    private static string $testTagPrefix = '_tgrp_' ;  // 6 chars + 10 from uniqueTag() = 16 max
    private ?HostGroupController $controller = null ;

    protected function setUp() : void
    {
        try {
            $this->controller = new HostGroupController( 'write' ) ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Database not available: ' . $e->getMessage() ) ;
        }
        $_REQUEST = [] ;
    }

    protected function tearDown() : void
    {
        $_REQUEST = [] ;
        if ( $this->controller !== null ) {
            try {
                $all = $this->controller->getAll() ;
                foreach ( $all as $model ) {
                    if ( str_starts_with( $model->getTag() ?? '', self::$testTagPrefix ) ) {
                        $this->controller->delete( $model ) ;
                    }
                }
            } catch ( \Exception $e ) {
                // Best effort cleanup
            }
        }
    }

    private function setRequestForAdd( string $tag ) : void
    {
        $_REQUEST = [
            'tag'              => $tag,
            'shortDescription' => 'PHPUnit test group',
            'fullDescription'  => 'Created by HostGroupControllerTest',
        ] ;
    }

    private function buildModelFromRequest() : HostGroupModel
    {
        $model = new HostGroupModel() ;
        $model->populateFromForm() ;
        return $model ;
    }

    // ========================================================================
    // Constructor
    // ========================================================================

    public function testConstructorSucceeds() : void
    {
        $this->assertInstanceOf( HostGroupController::class, $this->controller ) ;
    }

    // ========================================================================
    // CRUD cycle
    // ========================================================================

    public function testAddAndGetGroup() : void
    {
        $tag = self::$testTagPrefix . substr(uniqid(), -10) ;
        $this->setRequestForAdd( $tag ) ;
        $model = $this->buildModelFromRequest() ;

        $newId = $this->controller->add( $model ) ;
        $this->assertIsInt( $newId ) ;
        $this->assertGreaterThan( 0, $newId ) ;

        $fetched = $this->controller->get( $newId ) ;
        $this->assertInstanceOf( HostGroupModel::class, $fetched ) ;
        $this->assertSame( $tag, $fetched->getTag(), 'Bug #11 regression: tag must be set on fetched model' ) ;
        $this->assertSame( 'PHPUnit test group', $fetched->getShortDescription() ) ;
        $this->assertSame( 'Created by HostGroupControllerTest', $fetched->getFullDescription() ) ;

        // Cleanup
        $this->controller->delete( $fetched ) ;
    }

    public function testGetNonExistentGroupReturnsNull() : void
    {
        $result = $this->controller->get( 999999999 ) ;
        $this->assertNull( $result ) ;
    }

    public function testGetAllReturnsArray() : void
    {
        $all = $this->controller->getAll() ;
        $this->assertIsArray( $all ) ;
    }

    public function testGetSomeWithTagFilter() : void
    {
        $tag = self::$testTagPrefix . substr(uniqid(), -10) ;
        $this->setRequestForAdd( $tag ) ;
        $model = $this->buildModelFromRequest() ;
        $newId = $this->controller->add( $model ) ;

        $results = $this->controller->getSome( [ 'tag' => $tag ] ) ;
        $this->assertCount( 1, $results ) ;
        $this->assertSame( $tag, $results[0]->getTag() ) ;

        // Cleanup
        $model->setId( $newId ) ;
        $_REQUEST['id'] = (string) $newId ;
        $this->controller->delete( $model ) ;
    }

    public function testGetSomeRejectsInvalidFilterColumn() : void
    {
        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid filter column/' ) ;
        $this->controller->getSome( [ 'nonexistent' => 'value' ] ) ;
    }

    public function testUpdateGroup() : void
    {
        $tag = self::$testTagPrefix . substr(uniqid(), -10) ;
        $this->setRequestForAdd( $tag ) ;
        $model = $this->buildModelFromRequest() ;
        $newId = $this->controller->add( $model ) ;

        // Update it
        $newTag = self::$testTagPrefix . substr(uniqid(), -10) ;
        $_REQUEST = [
            'id'               => (string) $newId,
            'tag'              => $newTag,
            'shortDescription' => 'Updated description',
            'fullDescription'  => 'Updated full description',
        ] ;
        $updateModel = $this->buildModelFromRequest() ;
        $updateModel->setId( $newId ) ;
        $this->controller->update( $updateModel ) ;

        // Verify
        $fetched = $this->controller->get( $newId ) ;
        $this->assertSame( $newTag, $fetched->getTag() ) ;
        $this->assertSame( 'Updated description', $fetched->getShortDescription() ) ;

        // Cleanup
        $this->controller->delete( $fetched ) ;
    }

    public function testDeleteGroup() : void
    {
        $tag = self::$testTagPrefix . substr(uniqid(), -10) ;
        $this->setRequestForAdd( $tag ) ;
        $model = $this->buildModelFromRequest() ;
        $newId = $this->controller->add( $model ) ;

        $fetched = $this->controller->get( $newId ) ;
        $this->assertNotNull( $fetched ) ;

        $this->controller->delete( $fetched ) ;

        $afterDelete = $this->controller->get( $newId ) ;
        $this->assertNull( $afterDelete, 'Group should be gone after delete' ) ;
    }

    public function testAddInvalidDataThrows() : void
    {
        // validateForAdd requires tag — don't set it
        $_REQUEST = [] ;
        $model = $this->buildModelFromRequest() ;

        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->controller->add( $model ) ;
    }

    public function testUpdateGroupInvalidDataThrows() : void
    {
        // validateForUpdate requires id + tag — don't set them
        $_REQUEST = [] ;
        $model = $this->buildModelFromRequest() ;

        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->controller->update( $model ) ;
    }

    public function testDeleteInvalidModelThrows() : void
    {
        // Model with no id — validateForDelete() returns false
        $model = new HostGroupModel() ;
        $this->expectException( ControllerException::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid data/' ) ;
        $this->controller->delete( $model ) ;
    }

    // ========================================================================
    // DDL methods
    // ========================================================================

    public function testCreateTableDoesNotCrash() : void
    {
        // Uses IF NOT EXISTS — safe to call when table already exists.
        $this->controller->createTable() ;
        $this->assertTrue( true ) ;
    }

    public function testDropTriggersDoesNotCrash() : void
    {
        $this->controller->dropTriggers() ;
        $this->assertTrue( true ) ;
    }

    public function testCreateTriggersDoesNotCrash() : void
    {
        $this->controller->createTriggers() ;
        $this->assertTrue( true ) ;
    }
}
