<?php

namespace com\kbcmdba\aql\Tests\Libs\Models ;

use com\kbcmdba\aql\Libs\Models\HostGroupMapModel ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/Models/HostGroupMapModel.php — many-to-many join model
 * between hosts and host groups.
 */
class HostGroupMapModelTest extends TestCase
{
    protected function setUp() : void
    {
        $_REQUEST = [] ;
    }

    // ========================================================================
    // validateForAdd() — both IDs required and numeric, lastAudited optional
    // ========================================================================

    public function testValidateForAddSucceedsWithBothIds() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $_REQUEST['hostId'] = '10' ;
        $m = new HostGroupMapModel() ;
        $this->assertTrue( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenHostGroupIdMissing() : void
    {
        $_REQUEST['hostId'] = '10' ;
        $m = new HostGroupMapModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenHostIdMissing() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $m = new HostGroupMapModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenHostGroupIdNotNumeric() : void
    {
        $_REQUEST['hostGroupId'] = 'abc' ;
        $_REQUEST['hostId'] = '10' ;
        $m = new HostGroupMapModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenHostIdNotNumeric() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $_REQUEST['hostId'] = 'xyz' ;
        $m = new HostGroupMapModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    /**
     * lastAudited is OPTIONAL - when missing from the request, the validation
     * should still pass. This documents the expected behavior; if the
     * implementation has a bug where the literal string 'lastAudited' is
     * checked instead of Tools::param('lastAudited'), this test will catch it.
     */
    public function testValidateForAddSucceedsWhenLastAuditedMissing() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $_REQUEST['hostId'] = '10' ;
        // No lastAudited in $_REQUEST
        $m = new HostGroupMapModel() ;
        $this->assertTrue(
            $m->validateForAdd(),
            'lastAudited should be optional when missing from request'
        ) ;
    }

    public function testValidateForAddSucceedsWhenLastAuditedIsValidDate() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $_REQUEST['hostId'] = '10' ;
        $_REQUEST['lastAudited'] = '2024-01-15' ;
        $m = new HostGroupMapModel() ;
        $this->assertTrue( $m->validateForAdd() ) ;
    }

    public function testValidateForAddSucceedsWhenLastAuditedIsValidTimestamp() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $_REQUEST['hostId'] = '10' ;
        $_REQUEST['lastAudited'] = '2024-01-15 12:30:45' ;
        $m = new HostGroupMapModel() ;
        $this->assertTrue( $m->validateForAdd() ) ;
    }

    // ========================================================================
    // validateForUpdate() — same as validateForAdd
    // ========================================================================

    public function testValidateForUpdateSucceedsWithBothIds() : void
    {
        $_REQUEST['hostGroupId'] = '5' ;
        $_REQUEST['hostId'] = '10' ;
        $m = new HostGroupMapModel() ;
        $this->assertTrue( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenIdsMissing() : void
    {
        $m = new HostGroupMapModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    // ========================================================================
    // populateFromForm()
    // ========================================================================

    public function testPopulateFromFormCopiesAllFields() : void
    {
        $_REQUEST = [
            'hostGroupId' => '5',
            'hostId' => '10',
            'created' => '2024-01-15 10:00:00',
            'updated' => '2024-01-16 11:00:00',
            'lastAudited' => '2024-01-17 12:00:00',
        ] ;
        $m = new HostGroupMapModel() ;
        $m->populateFromForm() ;
        $this->assertSame( '5', $m->getHostGroupId() ) ;
        $this->assertSame( '10', $m->getHostId() ) ;
        $this->assertSame( '2024-01-15 10:00:00', $m->getCreated() ) ;
        $this->assertSame( '2024-01-16 11:00:00', $m->getUpdated() ) ;
        $this->assertSame( '2024-01-17 12:00:00', $m->getLastAudited() ) ;
    }

    // ========================================================================
    // Getters and setters
    // ========================================================================

    public function testHostGroupIdGetterSetter() : void
    {
        $m = new HostGroupMapModel() ;
        $m->setHostGroupId( 7 ) ;
        $this->assertSame( 7, $m->getHostGroupId() ) ;
    }

    public function testHostIdGetterSetter() : void
    {
        $m = new HostGroupMapModel() ;
        $m->setHostId( 42 ) ;
        $this->assertSame( 42, $m->getHostId() ) ;
    }

    public function testCreatedUpdatedLastAuditedGetterSetter() : void
    {
        $m = new HostGroupMapModel() ;
        $m->setCreated( '2024-01-15 10:00:00' ) ;
        $m->setUpdated( '2024-01-16 11:00:00' ) ;
        $m->setLastAudited( '2024-01-17 12:00:00' ) ;
        $this->assertSame( '2024-01-15 10:00:00', $m->getCreated() ) ;
        $this->assertSame( '2024-01-16 11:00:00', $m->getUpdated() ) ;
        $this->assertSame( '2024-01-17 12:00:00', $m->getLastAudited() ) ;
    }
}
