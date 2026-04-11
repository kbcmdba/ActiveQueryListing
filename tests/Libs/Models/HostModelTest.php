<?php

namespace com\kbcmdba\aql\Tests\Libs\Models ;

use com\kbcmdba\aql\Libs\Models\HostModel ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/Models/HostModel.php
 *
 * Note: This Model class is orphaned scaffolding - manageData.php and the
 * AJAX endpoints bypass the Model layer and call Tools::param() directly.
 * These tests document the contract for if/when the Model layer gets wired
 * up. They also caught a real bug in populateFromForm() which called the
 * non-existent setHostId() instead of setId().
 */
class HostModelTest extends TestCase
{
    /** Minimum required POST/GET state for validateForAdd to pass */
    private function validAddRequest() : array
    {
        return [
            'hostName' => 'mysql1.hole',
            'alertCritSecs' => '60',
            'alertWarnSecs' => '30',
            'alertInfoSecs' => '10',
            'alertLowSecs' => '0',
        ] ;
    }

    /** Same as add, plus an id (for update) */
    private function validUpdateRequest() : array
    {
        return [ 'id' => '5' ] + $this->validAddRequest() ;
    }

    protected function setUp() : void
    {
        $_REQUEST = [] ;
    }

    // ========================================================================
    // validateForAdd()
    // ========================================================================

    public function testValidateForAddSucceedsWithMinimumFields() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        $m = new HostModel() ;
        $this->assertTrue( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenIdPresent() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        $_REQUEST['id'] = '5' ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForAdd(), 'id must be empty for add' ) ;
    }

    public function testValidateForAddFailsWhenHostNameMissing() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        unset( $_REQUEST['hostName'] ) ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenAlertCritSecsMissing() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        unset( $_REQUEST['alertCritSecs'] ) ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenAlertCritSecsNotNumeric() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        $_REQUEST['alertCritSecs'] = 'abc' ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    public function testValidateForAddFailsWhenAlertWarnSecsNotNumeric() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        $_REQUEST['alertWarnSecs'] = 'xyz' ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForAdd() ) ;
    }

    // ========================================================================
    // validateForUpdate()
    // ========================================================================

    public function testValidateForUpdateSucceedsWithIdAndFields() : void
    {
        $_REQUEST = $this->validUpdateRequest() ;
        $m = new HostModel() ;
        $this->assertTrue( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenIdMissing() : void
    {
        $_REQUEST = $this->validAddRequest() ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenIdNotNumeric() : void
    {
        $_REQUEST = $this->validUpdateRequest() ;
        $_REQUEST['id'] = 'abc' ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    public function testValidateForUpdateFailsWhenHostNameMissing() : void
    {
        $_REQUEST = $this->validUpdateRequest() ;
        unset( $_REQUEST['hostName'] ) ;
        $m = new HostModel() ;
        $this->assertFalse( $m->validateForUpdate() ) ;
    }

    // ========================================================================
    // populateFromForm() - REGRESSION TEST: caught the setHostId bug
    // ========================================================================

    public function testPopulateFromFormDoesNotCrash() : void
    {
        $_REQUEST = [
            'id' => '5',
            'hostName' => 'mysql1.hole',
            'portNumber' => '3306',
            'description' => 'Primary MySQL',
            'shouldMonitor' => '1',
            'shouldBackup' => '1',
            'revenueImpacting' => '1',
            'decommissioned' => '0',
            'alertCritSecs' => '60',
            'alertWarnSecs' => '30',
            'alertInfoSecs' => '10',
            'alertLowSecs' => '0',
            'created' => '2024-01-15 10:00:00',
            'updated' => '2024-01-16 11:00:00',
            'lastAudited' => '2024-01-17 12:00:00',
        ] ;
        $m = new HostModel() ;
        // This used to throw a fatal because populateFromForm() called
        // the non-existent setHostId() method instead of setId().
        $m->populateFromForm() ;
        $this->assertSame( '5', $m->getId() ) ;
    }

    public function testPopulateFromFormCopiesAllFields() : void
    {
        $_REQUEST = [
            'id' => '7',
            'hostName' => 'pg1.hole',
            'portNumber' => '5432',
            'description' => 'Primary PG',
            'shouldMonitor' => '1',
            'shouldBackup' => '0',
            'revenueImpacting' => '1',
            'decommissioned' => '0',
            'alertCritSecs' => '120',
            'alertWarnSecs' => '60',
            'alertInfoSecs' => '20',
            'alertLowSecs' => '5',
            'created' => '2024-01-15 10:00:00',
            'updated' => '2024-01-16 11:00:00',
            'lastAudited' => '2024-01-17 12:00:00',
        ] ;
        $m = new HostModel() ;
        $m->populateFromForm() ;
        $this->assertSame( '7', $m->getId() ) ;
        $this->assertSame( 'pg1.hole', $m->getHostName() ) ;
        $this->assertSame( '5432', $m->getPortNumber() ) ;
        $this->assertSame( 'Primary PG', $m->getDescription() ) ;
        $this->assertSame( 1, $m->getShouldMonitor() ) ;
        $this->assertSame( 0, $m->getShouldBackup() ) ;
        $this->assertSame( 1, $m->getRevenueImpacting() ) ;
        $this->assertSame( 0, $m->getDecommissioned() ) ;
        $this->assertSame( '120', $m->getAlertCritSecs() ) ;
        $this->assertSame( '60', $m->getAlertWarnSecs() ) ;
        $this->assertSame( '20', $m->getAlertInfoSecs() ) ;
        $this->assertSame( '5', $m->getAlertLowSecs() ) ;
        $this->assertSame( '2024-01-15 10:00:00', $m->getCreated() ) ;
    }

    // ========================================================================
    // setBooleanAssumeTrue() (via setShouldMonitor etc.) - "true unless explicitly false"
    // ========================================================================

    public function testBooleanFieldDefaultsToTrue() : void
    {
        $m = new HostModel() ;
        // Anything truthy becomes 1
        $m->setShouldMonitor( '1' ) ;
        $this->assertSame( 1, $m->getShouldMonitor() ) ;
        $m->setShouldMonitor( 'yes' ) ;
        $this->assertSame( 1, $m->getShouldMonitor() ) ;
        $m->setShouldMonitor( 'true' ) ;
        $this->assertSame( 1, $m->getShouldMonitor() ) ;
    }

    public function testBooleanFieldFalseValues() : void
    {
        $m = new HostModel() ;
        // Explicit false-y values become 0
        $m->setShouldMonitor( false ) ;
        $this->assertSame( 0, $m->getShouldMonitor() ) ;
        $m->setShouldMonitor( '0' ) ;
        $this->assertSame( 0, $m->getShouldMonitor() ) ;
        $m->setShouldMonitor( 0 ) ;
        $this->assertSame( 0, $m->getShouldMonitor() ) ;
    }

    public function testRevenueImpactingBooleanLogic() : void
    {
        $m = new HostModel() ;
        $m->setRevenueImpacting( '1' ) ;
        $this->assertSame( 1, $m->getRevenueImpacting() ) ;
        $m->setRevenueImpacting( '0' ) ;
        $this->assertSame( 0, $m->getRevenueImpacting() ) ;
    }

    public function testDecommissionedBooleanLogic() : void
    {
        $m = new HostModel() ;
        $m->setDecommissioned( '1' ) ;
        $this->assertSame( 1, $m->getDecommissioned() ) ;
        $m->setDecommissioned( '0' ) ;
        $this->assertSame( 0, $m->getDecommissioned() ) ;
    }

    public function testShouldBackupBooleanLogic() : void
    {
        $m = new HostModel() ;
        $m->setShouldBackup( '1' ) ;
        $this->assertSame( 1, $m->getShouldBackup() ) ;
        $m->setShouldBackup( '0' ) ;
        $this->assertSame( 0, $m->getShouldBackup() ) ;
    }

    // ========================================================================
    // setPortNumber() - special case: defaults to 3306 when empty
    // ========================================================================

    public function testPortNumberDefaultsTo3306WhenEmpty() : void
    {
        $m = new HostModel() ;
        $m->setPortNumber( '' ) ;
        $this->assertSame( 3306, $m->getPortNumber() ) ;
    }

    public function testPortNumberDefaultsTo3306WhenNull() : void
    {
        $m = new HostModel() ;
        $m->setPortNumber( null ) ;
        $this->assertSame( 3306, $m->getPortNumber() ) ;
    }

    public function testPortNumberPreservesExplicitValue() : void
    {
        $m = new HostModel() ;
        $m->setPortNumber( '5432' ) ;
        $this->assertSame( '5432', $m->getPortNumber() ) ;
    }

    // ========================================================================
    // Plain getters and setters
    // ========================================================================

    public function testIdGetterSetter() : void
    {
        $m = new HostModel() ;
        $m->setId( 42 ) ;
        $this->assertSame( 42, $m->getId() ) ;
    }

    public function testHostNameGetterSetter() : void
    {
        $m = new HostModel() ;
        $m->setHostName( 'mysql1.hole' ) ;
        $this->assertSame( 'mysql1.hole', $m->getHostName() ) ;
    }

    public function testDescriptionGetterSetter() : void
    {
        $m = new HostModel() ;
        $m->setDescription( 'Primary database' ) ;
        $this->assertSame( 'Primary database', $m->getDescription() ) ;
    }

    public function testAlertSecondsGetterSetters() : void
    {
        $m = new HostModel() ;
        $m->setAlertCritSecs( 60 ) ;
        $m->setAlertWarnSecs( 30 ) ;
        $m->setAlertInfoSecs( 10 ) ;
        $m->setAlertLowSecs( 0 ) ;
        $this->assertSame( 60, $m->getAlertCritSecs() ) ;
        $this->assertSame( 30, $m->getAlertWarnSecs() ) ;
        $this->assertSame( 10, $m->getAlertInfoSecs() ) ;
        $this->assertSame( 0, $m->getAlertLowSecs() ) ;
    }

    public function testTimestampGetterSetters() : void
    {
        $m = new HostModel() ;
        $m->setCreated( '2024-01-15 10:00:00' ) ;
        $m->setUpdated( '2024-01-16 11:00:00' ) ;
        $m->setLastAudited( '2024-01-17 12:00:00' ) ;
        $this->assertSame( '2024-01-15 10:00:00', $m->getCreated() ) ;
        $this->assertSame( '2024-01-16 11:00:00', $m->getUpdated() ) ;
        $this->assertSame( '2024-01-17 12:00:00', $m->getLastAudited() ) ;
    }
}
