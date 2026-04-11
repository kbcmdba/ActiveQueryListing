<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\ConfigUpgrader ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/ConfigUpgrader.php — converts v1 flat config to v2 grouped.
 */
class ConfigUpgraderTest extends TestCase
{
    /**
     * Helper: build a minimal v1 config with the required params.
     */
    private function v1Minimal( array $extraParams = [], array $dbtypes = [] ) : string
    {
        $required = [
            'baseUrl' => 'https://localhost/aql/AJAXgetaql.php',
            'dbHost' => '127.0.0.1',
            'dbPort' => '3306',
            'dbUser' => 'aql_app',
            'dbPass' => 'app_secret',
            'dbName' => 'aql_db',
            'timeZone' => 'America/Chicago',
            'issueTrackerBaseUrl' => 'https://example.atlassian.net/',
            'roQueryPart' => '@@global.read_only',
        ] ;
        $params = array_merge( $required, $extraParams ) ;
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<config>\n" ;
        foreach ( $params as $name => $value ) {
            $xml .= "    <param name=\"" . htmlspecialchars( $name ) . "\">"
                 . htmlspecialchars( (string) $value ) . "</param>\n" ;
        }
        $hasMysql = false ;
        foreach ( $dbtypes as $dt ) {
            if ( strtolower( $dt['name'] ?? '' ) === 'mysql' ) { $hasMysql = true ; break ; }
        }
        if ( ! $hasMysql ) {
            $xml .= "    <dbtype name=\"mysql\" enabled=\"true\" />\n" ;
        }
        foreach ( $dbtypes as $dt ) {
            $attrs = '' ;
            foreach ( $dt as $k => $v ) {
                $attrs .= " $k=\"" . htmlspecialchars( (string) $v ) . "\"" ;
            }
            $xml .= "    <dbtype$attrs />\n" ;
        }
        $xml .= "</config>\n" ;
        return $xml ;
    }

    // ========================================================================
    // Idempotence — calling on a v2 config should refuse
    // ========================================================================

    public function testIsAlreadyUpgradedReturnsFalseForV1() : void
    {
        $this->assertFalse( ConfigUpgrader::isAlreadyUpgraded( $this->v1Minimal() ) ) ;
    }

    public function testIsAlreadyUpgradedReturnsTrueForV2() : void
    {
        $v2 = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n</config>\n" ;
        $this->assertTrue( ConfigUpgrader::isAlreadyUpgraded( $v2 ) ) ;
    }

    public function testUpgradeRefusesAlreadyV2Config() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/already version 2/' ) ;
        $v2 = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n</config>\n" ;
        ConfigUpgrader::upgrade( $v2 ) ;
    }

    public function testUpgradeRejectsInvalidXml() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid XML/' ) ;
        ConfigUpgrader::upgrade( '<config><not closed' ) ;
    }

    public function testIsAlreadyUpgradedReturnsFalseForInvalidXml() : void
    {
        $this->assertFalse( ConfigUpgrader::isAlreadyUpgraded( 'garbage' ) ) ;
    }

    // ========================================================================
    // Output structure — the upgraded XML must have version="2" and the
    // expected grouped elements
    // ========================================================================

    public function testUpgradedOutputHasVersion2() : void
    {
        $v2 = ConfigUpgrader::upgrade( $this->v1Minimal() ) ;
        $this->assertStringContainsString( '<config version="2">', $v2 ) ;
    }

    public function testUpgradedOutputHasAllExpectedGroupedElements() : void
    {
        $v2 = ConfigUpgrader::upgrade( $this->v1Minimal() ) ;
        $this->assertStringContainsString( '<configdb', $v2 ) ;
        $this->assertStringContainsString( '<user type="admin"', $v2 ) ;
        $this->assertStringContainsString( '<user type="monitor"', $v2 ) ;
        $this->assertStringContainsString( '<monitoring', $v2 ) ;
        $this->assertStringContainsString( '<environment_types>', $v2 ) ;
        $this->assertStringContainsString( '<features', $v2 ) ;
    }

    public function testUpgradedOutputIsValidXml() : void
    {
        $v2 = ConfigUpgrader::upgrade( $this->v1Minimal() ) ;
        $xml = @simplexml_load_string( $v2 ) ;
        $this->assertNotFalse( $xml, 'Upgraded XML must be parseable' ) ;
    }

    // ========================================================================
    // Round-trip — feed the upgraded XML to Config::parseConfigXml and check
    // that the values match the original. This is the strongest correctness
    // test: it confirms the upgrade preserves semantics, not just syntax.
    // ========================================================================

    public function testRoundTripPreservesDbHostPortNameUserPass() : void
    {
        $v1 = $this->v1Minimal() ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( '127.0.0.1', $cfg['dbHost'] ) ;
        $this->assertSame( '3306', $cfg['dbPort'] ) ;
        $this->assertSame( 'aql_db', $cfg['dbName'] ) ;
        $this->assertSame( 'aql_app', $cfg['dbUser'] ) ;
        $this->assertSame( 'app_secret', $cfg['dbPass'] ) ;
    }

    public function testRoundTripPreservesMonitoringSettings() : void
    {
        $v1 = $this->v1Minimal( [
            'minRefresh' => '20',
            'defaultRefresh' => '90',
            'killStatement' => 'CALL mysql.rds_kill(:pid)',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 20, $cfg['minRefresh'] ) ;
        $this->assertSame( 90, $cfg['defaultRefresh'] ) ;
        $this->assertSame( 'CALL mysql.rds_kill(:pid)', $cfg['killStatement'] ) ;
        $this->assertSame( 'America/Chicago', $cfg['timeZone'] ) ;
    }

    public function testRoundTripPreservesLdapSettings() : void
    {
        $v1 = $this->v1Minimal( [
            'doLDAPAuthentication' => 'true',
            'ldapHost' => 'ldaps://ad.example.com/',
            'ldapDomainName' => 'DC=example,DC=com',
            'ldapUserGroup' => 'DBAs',
            'ldapUserDomain' => 'EXAMPLE',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'true', $cfg['doLDAPAuthentication'] ) ;
        $this->assertSame( 'ldaps://ad.example.com/', $cfg['ldapHost'] ) ;
        $this->assertSame( 'DC=example,DC=com', $cfg['ldapDomainName'] ) ;
        $this->assertSame( 'DBAs', $cfg['ldapUserGroup'] ) ;
        $this->assertSame( 'EXAMPLE', $cfg['ldapUserDomain'] ) ;
    }

    public function testRoundTripPreservesEnvironments() : void
    {
        $v1 = $this->v1Minimal( [
            'environments' => 'dev,test,uat,production',
            'defaultEnvironment' => 'uat',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'dev,test,uat,production', $cfg['environments'] ) ;
        $this->assertSame( 'uat', $cfg['defaultEnvironment'] ) ;
    }

    public function testRoundTripPreservesDbtypes() : void
    {
        $v1 = $this->v1Minimal( [], [
            [ 'name' => 'redis', 'enabled' => 'true', 'password' => 'redis_pass' ],
            [ 'name' => 'postgresql', 'enabled' => 'true', 'username' => 'pg_mon', 'password' => 'pg_pass' ],
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'true', $cfg['redisEnabled'] ) ;
        $this->assertSame( 'redis_pass', $cfg['redisPassword'] ) ;
        $this->assertSame( 'true', $cfg['postgresqlEnabled'] ) ;
        $this->assertSame( 'pg_mon', $cfg['postgresqlUsername'] ) ;
        $this->assertSame( 'pg_pass', $cfg['postgresqlPassword'] ) ;
    }

    public function testRoundTripUpgradedConfigParsesAsV2() : void
    {
        $v1 = $this->v1Minimal() ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        // Config::parseConfigXml should detect this as v2 and use the grouped parser
        $cfg = Config::parseConfigXml( $v2 ) ;
        // Monitor user keys are only set in v2 mode — proves it took the v2 code path
        $this->assertArrayHasKey( 'monitorUser', $cfg ) ;
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function testUpgradeUsesMysqlDbtypeCredentialsForMonitorUser() : void
    {
        // When mysql dbtype has explicit username/password, those become the
        // monitor user (not the dbUser/dbPass).
        $v1 = $this->v1Minimal( [], [
            [ 'name' => 'mysql', 'enabled' => 'true',
              'username' => 'aql_mysql_mon', 'password' => 'mysql_mon_pass' ],
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'aql_mysql_mon', $cfg['monitorUser'] ) ;
        $this->assertSame( 'mysql_mon_pass', $cfg['monitorPassword'] ) ;
    }

    public function testUpgradeFallsBackToDbUserForMonitorWhenNoMysqlCreds() : void
    {
        // No explicit mysql dbtype creds — monitor user falls back to dbUser/dbPass
        $v1 = $this->v1Minimal() ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'aql_app', $cfg['monitorUser'] ) ;
        $this->assertSame( 'app_secret', $cfg['monitorPassword'] ) ;
    }

    public function testUpgradeHandlesPasswordsWithSpecialXmlCharacters() : void
    {
        $v1 = $this->v1Minimal( [
            'dbPass' => 'p@ss<word>&"chars\'',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        // Output must be valid XML
        $xml = @simplexml_load_string( $v2 ) ;
        $this->assertNotFalse( $xml, 'Output with special chars must still be valid XML' ) ;
        // And the round-trip must preserve the value
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'p@ss<word>&"chars\'', $cfg['dbPass'] ) ;
    }

    public function testUpgradeOmitsRedisElementWhenNoRedisParamsPresent() : void
    {
        $v2 = ConfigUpgrader::upgrade( $this->v1Minimal() ) ;
        // The <redis> connection-tuning element should not appear when there's
        // nothing to put in it
        $this->assertStringNotContainsString( '<redis ', $v2 ) ;
    }

    public function testUpgradeIncludesRedisElementWhenRedisParamsPresent() : void
    {
        $v1 = $this->v1Minimal( [
            'redisConnectTimeout' => '5',
            'redisDatabase' => '2',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $this->assertStringContainsString( '<redis ', $v2 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 5, $cfg['redisConnectTimeout'] ) ;
        $this->assertSame( 2, $cfg['redisDatabase'] ) ;
    }

    public function testUpgradeOmitsTestingElementWhenNoTestParamsPresent() : void
    {
        $v2 = ConfigUpgrader::upgrade( $this->v1Minimal() ) ;
        $this->assertStringNotContainsString( '<testing ', $v2 ) ;
    }

    public function testUpgradeIncludesTestingElementWhenTestParamsPresent() : void
    {
        $v1 = $this->v1Minimal( [
            'testDbUser' => 'aql_test',
            'testDbPass' => 'test_pass',
            'testDbName' => 'aql_test_db',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $this->assertStringContainsString( '<testing ', $v2 ) ;
        $cfg = Config::parseConfigXml( $v2 ) ;
        $this->assertSame( 'aql_test', $cfg['testDbUser'] ) ;
        $this->assertSame( 'test_pass', $cfg['testDbPass'] ) ;
        $this->assertSame( 'aql_test_db', $cfg['testDbName'] ) ;
    }

    public function testUpgradeMarksSpecifiedDefaultEnvironment() : void
    {
        $v1 = $this->v1Minimal( [
            'environments' => 'dev,staging,prod',
            'defaultEnvironment' => 'staging',
        ] ) ;
        $v2 = ConfigUpgrader::upgrade( $v1 ) ;
        $this->assertMatchesRegularExpression(
            '/<environment_type name="staging" default="true"/',
            $v2,
            'staging should be marked as default'
        ) ;
    }
}
