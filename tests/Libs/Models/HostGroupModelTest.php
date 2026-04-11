<?php

namespace com\kbcmdba\aql\Tests\Libs\Models ;

use com\kbcmdba\aql\Libs\Models\HostGroupModel ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/Models/HostGroupModel.php — validation and getter/setter
 * round-trips. validateForAdd/validateForUpdate read from $_REQUEST via
 * Tools::param(), so tests set $_REQUEST in setUp.
 */
class HostGroupModelTest extends TestCase
{
    protected function setUp() : void
    {
        $_REQUEST = [] ;
    }

    // ========================================================================
    // validateForAdd() — id must be empty (it's auto-generated), tag required
    // ========================================================================

    public function testValidateForAddSucceedsWithTagAndNoId() : void
    {
        $_REQUEST['tag'] = 'production' ;
        $m = new HostGroupModel() ;
        $this->assertTrue( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenTagMissing() : void
    {
        $m = new HostGroupModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenIdPresent() : void
    {
        $_REQUEST['tag'] = 'production' ;
        $_REQUEST['id'] = '5' ;
        $m = new HostGroupModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenTagEmptyString() : void
    {
        $_REQUEST['tag'] = '' ;
        $m = new HostGroupModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    // ========================================================================
    // validateForUpdate() — id required, tag required
    // ========================================================================

    public function testValidateForUpdateSucceedsWithIdAndTag() : void
    {
        $_REQUEST['id'] = '5' ;
        $_REQUEST['tag'] = 'production' ;
        $m = new HostGroupModel() ;
        $this->assertTrue( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenIdMissing() : void
    {
        $_REQUEST['tag'] = 'production' ;
        $m = new HostGroupModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenIdNotNumeric() : void
    {
        $_REQUEST['id'] = 'abc' ;
        $_REQUEST['tag'] = 'production' ;
        $m = new HostGroupModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenTagMissing() : void
    {
        $_REQUEST['id'] = '5' ;
        $m = new HostGroupModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    // ========================================================================
    // populateFromForm() — copies $_REQUEST values into model fields
    // ========================================================================

    public function testPopulateFromFormCopiesAllFields() : void
    {
        $_REQUEST = [
            'id' => '7',
            'tag' => 'staging',
            'shortDescription' => 'Staging environment',
            'fullDescription' => 'Staging environment for QA testing',
            'created' => '2024-01-15 10:00:00',
            'updated' => '2024-01-16 11:00:00',
        ] ;
        $m = new HostGroupModel() ;
        $m->populateFromForm() ;
        $this->assertSame( '7', $m->getId() ) ;
        $this->assertSame( 'staging', $m->getTag() ) ;
        $this->assertSame( 'Staging environment', $m->getShortDescription() ) ;
        $this->assertSame( 'Staging environment for QA testing', $m->getFullDescription() ) ;
        $this->assertSame( '2024-01-15 10:00:00', $m->getCreated() ) ;
        $this->assertSame( '2024-01-16 11:00:00', $m->getUpdated() ) ;
    }

    // ========================================================================
    // Getters and setters round-trip
    // ========================================================================

    public function testIdGetterSetter() : void
    {
        $m = new HostGroupModel() ;
        $m->setId( 42 ) ;
        $this->assertSame( 42, $m->getId() ) ;
    }

    public function testTagGetterSetter() : void
    {
        $m = new HostGroupModel() ;
        $m->setTag( 'mytag' ) ;
        $this->assertSame( 'mytag', $m->getTag() ) ;
    }

    public function testShortAndFullDescriptionGetterSetter() : void
    {
        $m = new HostGroupModel() ;
        $m->setShortDescription( 'short' ) ;
        $m->setFullDescription( 'long description here' ) ;
        $this->assertSame( 'short', $m->getShortDescription() ) ;
        $this->assertSame( 'long description here', $m->getFullDescription() ) ;
    }

    public function testCreatedAndUpdatedGetterSetter() : void
    {
        $m = new HostGroupModel() ;
        $m->setCreated( '2024-01-15 10:00:00' ) ;
        $m->setUpdated( '2024-01-16 11:00:00' ) ;
        $this->assertSame( '2024-01-15 10:00:00', $m->getCreated() ) ;
        $this->assertSame( '2024-01-16 11:00:00', $m->getUpdated() ) ;
    }
}
