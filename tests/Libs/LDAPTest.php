<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\LDAP ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/LDAP.php — integration tests against a real Samba AD server.
 *
 * Requirements:
 * - LDAP enabled in aql_config.xml (<ldap enabled="true" ... />)
 * - Test users configured in <testing ldapTestUser="..." ldapTestPass="..."
 *   ldapTestUserNoGroup="..." ldapTestPassNoGroup="..." />
 * - Samba AD at ad1.hole reachable from the test host
 *
 * Tests are skipped (not failed) if LDAP is disabled or test credentials
 * are not configured. This allows the test suite to pass on machines
 * without LDAP infrastructure.
 */
class LDAPTest extends TestCase
{
    private ?Config $config = null ;
    private string $ldapUser = '' ;
    private string $ldapPass = '' ;
    private string $ldapUserNoGroup = '' ;
    private string $ldapPassNoGroup = '' ;

    protected function setUp() : void
    {
        // Start a session if one isn't active (LDAP::authenticate writes to $_SESSION)
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            session_start() ;
        }
        // Clear any existing auth state
        unset( $_SESSION['AuthUser'], $_SESSION['AuthCanAccess'], $_SESSION['AuthLoginTime'] ) ;

        try {
            $this->config = new Config() ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Config not available: ' . $e->getMessage() ) ;
        }

        $this->ldapUser        = $this->config->getConfigValue( 'ldapTestUser', '' ) ;
        $this->ldapPass        = $this->config->getConfigValue( 'ldapTestPass', '' ) ;
        $this->ldapUserNoGroup = $this->config->getConfigValue( 'ldapTestUserNoGroup', '' ) ;
        $this->ldapPassNoGroup = $this->config->getConfigValue( 'ldapTestPassNoGroup', '' ) ;
    }

    protected function tearDown() : void
    {
        // Clean up session state so we don't leak auth between tests
        unset( $_SESSION['AuthUser'], $_SESSION['AuthCanAccess'], $_SESSION['AuthLoginTime'] ) ;
    }

    private function requireLdapEnabled() : void
    {
        if ( ! $this->config->getDoLDAPAuthentication() ) {
            $this->markTestSkipped( 'LDAP not enabled in config' ) ;
        }
    }

    private function requireTestCredentials() : void
    {
        $this->requireLdapEnabled() ;
        if ( empty( $this->ldapUser ) || empty( $this->ldapPass ) ) {
            $this->markTestSkipped( 'LDAP test credentials not configured (ldapTestUser/ldapTestPass in <testing>)' ) ;
        }
    }

    private function requireNoGroupCredentials() : void
    {
        $this->requireLdapEnabled() ;
        if ( empty( $this->ldapUserNoGroup ) || empty( $this->ldapPassNoGroup ) ) {
            $this->markTestSkipped( 'LDAP no-group test credentials not configured' ) ;
        }
    }

    // ========================================================================
    // Local auth path — tests authenticateLocally() directly, no config needed
    // ========================================================================

    public function testLocalAuthSucceedsWithCorrectPassword() : void
    {
        $result = LDAP::authenticateLocally( 'testuser', 'correct_pass', 'correct_pass' ) ;
        $this->assertTrue( $result ) ;
        $this->assertSame( 'testuser', $_SESSION['AuthUser'] ) ;
        $this->assertSame( 1, $_SESSION['AuthCanAccess'] ) ;
        $this->assertArrayHasKey( 'AuthLoginTime', $_SESSION ) ;
    }

    public function testLocalAuthFailsWithWrongPassword() : void
    {
        $result = LDAP::authenticateLocally( 'testuser', 'wrong_pass', 'correct_pass' ) ;
        $this->assertFalse( $result ) ;
        $this->assertArrayNotHasKey( 'AuthUser', $_SESSION ) ;
    }

    public function testLocalAuthFailsWithEmptyAdminPassword() : void
    {
        $result = LDAP::authenticateLocally( 'testuser', 'any_pass', '' ) ;
        $this->assertFalse( $result ) ;
    }

    public function testLocalAuthFailsWithEmptyUser() : void
    {
        $result = LDAP::authenticateLocally( '', 'any_pass', 'admin_pass' ) ;
        $this->assertFalse( $result ) ;
    }

    public function testLocalAuthFailsWithEmptyPassword() : void
    {
        $result = LDAP::authenticateLocally( 'testuser', '', 'admin_pass' ) ;
        $this->assertFalse( $result ) ;
    }

    // ========================================================================
    // Empty credentials — never reaches the LDAP server
    // ========================================================================

    public function testAuthFailsWithEmptyUsername() : void
    {
        $result = LDAP::authenticate( '', 'some_password' ) ;
        $this->assertFalse( $result ) ;
    }

    public function testAuthFailsWithEmptyPassword() : void
    {
        $result = LDAP::authenticate( 'some_user', '' ) ;
        $this->assertFalse( $result ) ;
    }

    public function testAuthFailsWithBothEmpty() : void
    {
        $result = LDAP::authenticate( '', '' ) ;
        $this->assertFalse( $result ) ;
    }

    public function testAuthFailsWithNullUsername() : void
    {
        $result = LDAP::authenticate( null, 'password' ) ;
        $this->assertFalse( $result ) ;
    }

    public function testAuthFailsWithNullPassword() : void
    {
        $result = LDAP::authenticate( 'user', null ) ;
        $this->assertFalse( $result ) ;
    }

    // ========================================================================
    // LDAP integration — requires Samba AD at ad1.hole
    // ========================================================================

    public function testLdapAuthSucceedsWithValidCredentialsAndGroup() : void
    {
        $this->requireTestCredentials() ;

        $result = LDAP::authenticate( $this->ldapUser, $this->ldapPass ) ;
        $this->assertTrue( $result, 'aql_test should authenticate successfully via LDAP' ) ;
        $this->assertSame( $this->ldapUser, $_SESSION['AuthUser'] ) ;
        $this->assertSame( 1, $_SESSION['AuthCanAccess'] ) ;
        $this->assertArrayHasKey( 'AuthLoginTime', $_SESSION ) ;
    }

    public function testLdapAuthFailsWithWrongPassword() : void
    {
        $this->requireTestCredentials() ;

        $result = LDAP::authenticate( $this->ldapUser, 'this_is_the_wrong_password' ) ;
        $this->assertFalse( $result, 'Wrong password should fail' ) ;
        $this->assertArrayNotHasKey( 'AuthUser', $_SESSION ) ;
    }

    public function testLdapAuthFailsWhenUserNotInRequiredGroup() : void
    {
        $this->requireNoGroupCredentials() ;

        $result = LDAP::authenticate( $this->ldapUserNoGroup, $this->ldapPassNoGroup ) ;
        $this->assertFalse( $result, 'User not in AQL_Admins should fail auth' ) ;
        $this->assertArrayNotHasKey( 'AuthUser', $_SESSION ) ;
    }

    public function testLdapAuthFailsWithNonExistentUser() : void
    {
        $this->requireLdapEnabled() ;

        $result = LDAP::authenticate( 'totally_nonexistent_user_xyz', 'any_password' ) ;
        $this->assertFalse( $result ) ;
        $this->assertArrayNotHasKey( 'AuthUser', $_SESSION ) ;
    }

    // ========================================================================
    // Session state verification
    // ========================================================================

    public function testSuccessfulAuthSetsLoginTimestamp() : void
    {
        $this->requireTestCredentials() ;

        $before = time() ;
        $result = LDAP::authenticate( $this->ldapUser, $this->ldapPass ) ;
        $after = time() ;

        $this->assertTrue( $result ) ;
        $this->assertArrayHasKey( 'AuthLoginTime', $_SESSION ) ;
        $this->assertGreaterThanOrEqual( $before, $_SESSION['AuthLoginTime'] ) ;
        $this->assertLessThanOrEqual( $after, $_SESSION['AuthLoginTime'] ) ;
    }

    public function testFailedAuthDoesNotSetSessionVariables() : void
    {
        $this->requireTestCredentials() ;

        LDAP::authenticate( $this->ldapUser, 'wrong_password' ) ;
        $this->assertArrayNotHasKey( 'AuthUser', $_SESSION ) ;
        $this->assertArrayNotHasKey( 'AuthCanAccess', $_SESSION ) ;
        $this->assertArrayNotHasKey( 'AuthLoginTime', $_SESSION ) ;
    }
}
