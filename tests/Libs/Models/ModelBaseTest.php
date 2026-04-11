<?php

namespace com\kbcmdba\aql\Tests\Libs\Models ;

use com\kbcmdba\aql\Libs\Models\ModelBase ;
use PHPUnit\Framework\TestCase ;

/**
 * Concrete subclass for testing the abstract ModelBase.
 * Provides minimal implementations of the abstract methods plus an
 * id field with a getter so validateForDelete() has something to call.
 */
class TestableModel extends ModelBase
{
    private $id ;

    public function __construct( $id = null )
    {
        parent::__construct() ;
        $this->id = $id ;
    }

    public function getId()
    {
        return $this->id ;
    }

    public function setId( $id ) : void
    {
        $this->id = $id ;
    }

    public function validateForAdd()
    {
        return true ;
    }

    public function validateForUpdate()
    {
        return true ;
    }
}

class ModelBaseTest extends TestCase
{
    // ========================================================================
    // validateId() — must match /^[1-9][0-9]*$/
    // ========================================================================

    public function testValidateIdAcceptsPositiveInteger() : void
    {
        $m = new TestableModel() ;
        $this->assertTrue( $m->validateId( '1' ) ) ;
        $this->assertTrue( $m->validateId( '42' ) ) ;
        $this->assertTrue( $m->validateId( '999999' ) ) ;
    }

    public function testValidateIdRejectsZero() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateId( '0' ) ) ;
    }

    public function testValidateIdRejectsNegative() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateId( '-1' ) ) ;
    }

    public function testValidateIdRejectsLeadingZero() : void
    {
        $m = new TestableModel() ;
        // /^[1-9].../  - first digit can't be 0
        $this->assertFalse( $m->validateId( '01' ) ) ;
    }

    public function testValidateIdRejectsNonNumeric() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateId( 'abc' ) ) ;
        $this->assertFalse( $m->validateId( '1a' ) ) ;
        $this->assertFalse( $m->validateId( '' ) ) ;
    }

    public function testValidateIdRejectsDecimal() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateId( '1.5' ) ) ;
    }

    // ========================================================================
    // validateDate() — must match /^20[123][0-9]-MM-DD$/ (2010-2039)
    // ========================================================================

    public function testValidateDateAcceptsValidDate() : void
    {
        $m = new TestableModel() ;
        $this->assertTrue( $m->validateDate( '2024-01-15' ) ) ;
        $this->assertTrue( $m->validateDate( '2030-12-31' ) ) ;
        $this->assertTrue( $m->validateDate( '2010-06-15' ) ) ;
    }

    public function testValidateDateRejectsInvalidMonth() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateDate( '2024-00-15' ) ) ;
        $this->assertFalse( $m->validateDate( '2024-13-15' ) ) ;
    }

    public function testValidateDateRejectsInvalidDay() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateDate( '2024-01-00' ) ) ;
        $this->assertFalse( $m->validateDate( '2024-01-32' ) ) ;
    }

    public function testValidateDateRejectsOutOfRangeYear() : void
    {
        $m = new TestableModel() ;
        // Year 2000 not allowed (regex starts with 20[123])
        $this->assertFalse( $m->validateDate( '2000-01-15' ) ) ;
        // Year 2040 not allowed
        $this->assertFalse( $m->validateDate( '2040-01-15' ) ) ;
        // Year 1999 not allowed
        $this->assertFalse( $m->validateDate( '1999-12-31' ) ) ;
    }

    public function testValidateDateRejectsBadFormat() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateDate( '01-15-2024' ) ) ;  // wrong order
        $this->assertFalse( $m->validateDate( '2024/01/15' ) ) ;  // wrong separator
        $this->assertFalse( $m->validateDate( '2024-1-1' ) ) ;    // missing zero pad
    }

    // ========================================================================
    // validateTimestamp() — date + time
    // ========================================================================

    public function testValidateTimestampAcceptsValidTimestamp() : void
    {
        $m = new TestableModel() ;
        $this->assertTrue( $m->validateTimestamp( '2024-01-15 12:30:45' ) ) ;
        $this->assertTrue( $m->validateTimestamp( '2030-12-31 00:00:00' ) ) ;
        $this->assertTrue( $m->validateTimestamp( '2024-06-15 23:59:59' ) ) ;
    }

    public function testValidateTimestampRejectsInvalidTime() : void
    {
        $m = new TestableModel() ;
        // Hour > 59 (regex actually allows 0-59 for all three groups, so 60 fails)
        $this->assertFalse( $m->validateTimestamp( '2024-01-15 60:30:45' ) ) ;
        // Minute > 59
        $this->assertFalse( $m->validateTimestamp( '2024-01-15 12:60:45' ) ) ;
        // Seconds > 59
        $this->assertFalse( $m->validateTimestamp( '2024-01-15 12:30:60' ) ) ;
    }

    public function testValidateTimestampRejectsMissingTime() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateTimestamp( '2024-01-15' ) ) ;
    }

    public function testValidateTimestampRejectsBadFormat() : void
    {
        $m = new TestableModel() ;
        $this->assertFalse( $m->validateTimestamp( '2024-01-15T12:30:45' ) ) ;  // ISO 8601 with T
        $this->assertFalse( $m->validateTimestamp( 'garbage' ) ) ;
    }

    // ========================================================================
    // validateForDelete() — uses validateId() on the model's id
    // ========================================================================

    public function testValidateForDeletePassesWithValidId() : void
    {
        $m = new TestableModel( 42 ) ;
        $this->assertTrue( $m->validateForDelete() ) ;
    }

    public function testValidateForDeleteFailsWithZeroId() : void
    {
        $m = new TestableModel( 0 ) ;
        $this->assertFalse( $m->validateForDelete() ) ;
    }

    public function testValidateForDeleteFailsWithNullId() : void
    {
        $m = new TestableModel( null ) ;
        $this->assertFalse( $m->validateForDelete() ) ;
    }

    // ========================================================================
    // Constructor exists and does nothing harmful
    // ========================================================================

    public function testCanConstruct() : void
    {
        $m = new TestableModel() ;
        $this->assertInstanceOf( ModelBase::class, $m ) ;
    }

    public function testAbstractMethodsWorkInSubclass() : void
    {
        $m = new TestableModel( 1 ) ;
        $this->assertTrue( $m->validateForAdd() ) ;
        $this->assertTrue( $m->validateForUpdate() ) ;
    }
}
