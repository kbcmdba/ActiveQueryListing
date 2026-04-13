<?php

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use PHPUnit\Framework\TestCase ;

/**
 * Tests for Libs/Config.php parser logic.
 *
 * Uses Config::parseConfigXml() to test the parser without touching
 * the real aql_config.xml file.
 */
class ConfigTest extends TestCase
{
    /**
     * Helper: build a minimal v1 (flat <param>) config XML string.
     */
    private function v1Config( array $extraParams = [], array $dbtypes = [] ) : string
    {
        $required = [
            'baseUrl' => 'https://localhost/aql/AJAXgetaql.php',
            'dbHost' => '127.0.0.1',
            'dbPort' => '3306',
            'dbUser' => 'aql_app',
            'dbPass' => 'secret',
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
        // mysql is required
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

    /**
     * Helper: build a v2 grouped-element config XML string.
     */
    private function v2Config( array $overrides = [], array $dbtypes = [] ) : string
    {
        $defaults = [
            'configdb_host' => '127.0.0.1',
            'configdb_port' => '3306',
            'configdb_name' => 'aql_db',
            'admin_name' => 'aql_app',
            'admin_pass' => 'admin_secret',
            'monitor_name' => 'aql_mon',
            'monitor_pass' => 'monitor_secret',
            'baseUrl' => 'https://localhost/aql/AJAXgetaql.php',
            'timeZone' => 'America/Chicago',
            'issueTrackerBaseUrl' => 'https://example.atlassian.net/',
            'roQueryPart' => '@@global.read_only',
        ] ;
        $c = array_merge( $defaults, $overrides ) ;

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<config version=\"2\">\n" ;
        $xml .= "    <configdb type=\"mysql\" host=\"{$c['configdb_host']}\" port=\"{$c['configdb_port']}\" name=\"{$c['configdb_name']}\" />\n" ;
        $xml .= "    <user type=\"admin\" name=\"{$c['admin_name']}\" password=\"{$c['admin_pass']}\" />\n" ;
        $xml .= "    <user type=\"monitor\" name=\"{$c['monitor_name']}\" password=\"{$c['monitor_pass']}\" />\n" ;
        $xml .= "    <monitoring baseUrl=\"{$c['baseUrl']}\" timeZone=\"{$c['timeZone']}\" "
             . "issueTrackerBaseUrl=\"{$c['issueTrackerBaseUrl']}\" roQueryPart=\"{$c['roQueryPart']}\" />\n" ;

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
    // Format detection
    // ========================================================================

    public function testV1FlatFormatParses() : void
    {
        $cfg = Config::parseConfigXml( $this->v1Config() ) ;
        $this->assertSame( '127.0.0.1', $cfg['dbHost'] ) ;
        $this->assertSame( '3306', $cfg['dbPort'] ) ;
        $this->assertSame( 'aql_app', $cfg['dbUser'] ) ;
        $this->assertSame( 'aql_db', $cfg['dbName'] ) ;
    }

    public function testV2GroupedFormatParses() : void
    {
        $cfg = Config::parseConfigXml( $this->v2Config() ) ;
        $this->assertSame( '127.0.0.1', $cfg['dbHost'] ) ;
        $this->assertSame( '3306', $cfg['dbPort'] ) ;
        $this->assertSame( 'aql_app', $cfg['dbUser'] ) ;
        $this->assertSame( 'admin_secret', $cfg['dbPass'] ) ;
        $this->assertSame( 'aql_mon', $cfg['monitorUser'] ) ;
        $this->assertSame( 'monitor_secret', $cfg['monitorPassword'] ) ;
    }

    // ========================================================================
    // Credential resolution chain
    // ========================================================================

    public function testV2AdminUserBecomesDbUserAndDbPass() : void
    {
        $cfg = Config::parseConfigXml( $this->v2Config( [
            'admin_name' => 'super_admin',
            'admin_pass' => 'super_secret',
        ] ) ) ;
        $this->assertSame( 'super_admin', $cfg['dbUser'] ) ;
        $this->assertSame( 'super_secret', $cfg['dbPass'] ) ;
    }

    public function testV2MonitorUserCascadesToMysqlDbtype() : void
    {
        // No explicit username/password on <dbtype name="mysql"> - should
        // inherit from <user type="monitor">.
        $cfg = Config::parseConfigXml( $this->v2Config( [
            'monitor_name' => 'aql_mon',
            'monitor_pass' => 'mon_pass',
        ] ) ) ;
        $this->assertSame( 'aql_mon', $cfg['mysqlUsername'] ) ;
        $this->assertSame( 'mon_pass', $cfg['mysqlPassword'] ) ;
    }

    public function testV2DbtypeOverrideTakesPrecedenceOverMonitorUser() : void
    {
        // PostgreSQL has its own monitor user - per-type override wins.
        $cfg = Config::parseConfigXml( $this->v2Config( [], [
            [ 'name' => 'postgresql', 'enabled' => 'true',
              'username' => 'pg_special', 'password' => 'pg_special_pass' ],
        ] ) ) ;
        $this->assertSame( 'pg_special', $cfg['postgresqlUsername'] ) ;
        $this->assertSame( 'pg_special_pass', $cfg['postgresqlPassword'] ) ;
    }

    // ========================================================================
    // REGRESSION TEST: Redis must NOT inherit monitor credentials.
    // Caught a real bug where Redis got "ERR AUTH called without any
    // password configured for the default user" because the monitor
    // user fallback was being applied to all dbtypes including Redis.
    // ========================================================================

    public function testRedisDoesNotInheritMonitorCredentials() : void
    {
        $cfg = Config::parseConfigXml( $this->v2Config( [
            'monitor_name' => 'aql_mon',
            'monitor_pass' => 'monitor_secret_password',
        ], [
            [ 'name' => 'redis', 'enabled' => 'true' ],
        ] ) ) ;
        // The dbtype-style keys must not be set with the monitor user's creds
        $this->assertArrayNotHasKey( 'redisUsername', $cfg,
            'Redis must NOT inherit monitor username when no creds are explicit' ) ;
        // redisPassword is in defaults as '' - must remain empty, not the monitor pass
        $this->assertSame( '', $cfg['redisPassword'] ?? '',
            'Redis must NOT inherit monitor password when no creds are explicit' ) ;
        // redisUser is NOT in defaults - must not be set to monitor user
        $this->assertSame( '', $cfg['redisUser'] ?? '',
            'redisUser must NOT inherit monitor user' ) ;
    }

    public function testRedisExplicitCredentialsAreUsed() : void
    {
        $cfg = Config::parseConfigXml( $this->v2Config( [], [
            [ 'name' => 'redis', 'enabled' => 'true',
              'username' => 'aql_redis', 'password' => 'redis_pass' ],
        ] ) ) ;
        // Both the dbtype-style key AND the redisUser/redisPassword keys
        // (used by the Redis handler) must be set.
        $this->assertSame( 'aql_redis', $cfg['redisUsername'] ) ;
        $this->assertSame( 'redis_pass', $cfg['redisPassword'] ) ;
        $this->assertSame( 'aql_redis', $cfg['redisUser'],
            '<dbtype name="redis" username="..."> must also set redisUser key (handler reads this)' ) ;
    }

    // ========================================================================
    // Dbtype enabled flags
    // ========================================================================

    public function testDbtypeEnabledFlagsArePopulated() : void
    {
        $cfg = Config::parseConfigXml( $this->v2Config( [], [
            [ 'name' => 'redis', 'enabled' => 'true' ],
            [ 'name' => 'postgresql', 'enabled' => 'true', 'username' => 'pg', 'password' => 'p' ],
            [ 'name' => 'mssql', 'enabled' => 'false' ],
        ] ) ) ;
        $this->assertSame( 'true', $cfg['mysqlEnabled'] ) ;
        $this->assertSame( 'true', $cfg['redisEnabled'] ) ;
        $this->assertSame( 'true', $cfg['postgresqlEnabled'] ) ;
        $this->assertSame( 'false', $cfg['mssqlEnabled'] ) ;
    }

    public function testDbtypeNameNormalizationStripsHyphensAndSpaces() : void
    {
        // <dbtype name="MS-SQL"> should map to mssqlEnabled key
        $cfg = Config::parseConfigXml( $this->v2Config( [], [
            [ 'name' => 'MS-SQL', 'enabled' => 'true' ],
        ] ) ) ;
        $this->assertSame( 'true', $cfg['mssqlEnabled'] ) ;
    }

    // ========================================================================
    // Required parameters
    // ========================================================================

    public function testMissingRequiredParameterThrows() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Missing parameter/' ) ;
        // Missing baseUrl
        $xml = "<?xml version=\"1.0\"?>\n<config>\n"
             . "    <param name=\"dbHost\">localhost</param>\n"
             . "    <param name=\"dbPort\">3306</param>\n"
             . "    <param name=\"dbUser\">u</param>\n"
             . "    <param name=\"dbPass\">p</param>\n"
             . "    <param name=\"dbName\">d</param>\n"
             . "    <param name=\"timeZone\">UTC</param>\n"
             . "    <param name=\"issueTrackerBaseUrl\">http://x</param>\n"
             . "    <param name=\"roQueryPart\">@@global.read_only</param>\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        Config::parseConfigXml( $xml ) ;
    }

    public function testInvalidXmlThrows() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Invalid XML/' ) ;
        Config::parseConfigXml( '<config><not closed' ) ;
    }

    // ========================================================================
    // fromXmlString() — full-instance factory + getters
    // Exercises the trivial passthrough getters in one shot.
    // ========================================================================

    public function testFromXmlStringReturnsConfigInstance() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertInstanceOf( Config::class, $config ) ;
    }

    public function testFromXmlStringPopulatesDatabaseGetters() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( '127.0.0.1', $config->getDbHost() ) ;
        $this->assertSame( '3306', $config->getDbPort() ) ;
        $this->assertSame( 'aql_app', $config->getDbUser() ) ;
        $this->assertSame( 'admin_secret', $config->getDbPass() ) ;
        $this->assertSame( 'aql_db', $config->getDbName() ) ;
    }

    public function testFromXmlStringPopulatesMonitoringGetters() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( 'https://localhost/aql/AJAXgetaql.php', $config->getBaseUrl() ) ;
        $this->assertSame( 'America/Chicago', $config->getTimeZone() ) ;
        $this->assertSame( 'https://example.atlassian.net/', $config->getIssueTrackerBaseUrl() ) ;
        $this->assertSame( '@@global.read_only', $config->getRoQueryPart() ) ;
    }

    public function testFromXmlStringPopulatesIntegerDefaults() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        // Default values from getDefaults()
        $this->assertSame( 15, $config->getMinRefresh() ) ;
        $this->assertSame( 60, $config->getDefaultRefresh() ) ;
    }

    public function testFromXmlStringPopulatesDsn() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $dsn = $config->getDsn() ;
        $this->assertStringContainsString( 'mysql:', $dsn ) ;
        $this->assertStringContainsString( 'host=127.0.0.1', $dsn ) ;
        $this->assertStringContainsString( '3306', $dsn ) ;
        $this->assertStringContainsString( 'dbname=aql_db', $dsn ) ;
    }

    public function testFromXmlStringPopulatesDsnCustomDbType() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertStringStartsWith( 'pgsql:', $config->getDsn( 'pgsql' ) ) ;
    }

    public function testFromXmlStringDefaultLdapDisabled() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        // No <ldap> element means LDAP is disabled
        $this->assertFalse( $config->getDoLDAPAuthentication() ) ;
        $this->assertSame( '', $config->getLDAPHost() ) ;
        $this->assertSame( '', $config->getLDAPDomainName() ) ;
        $this->assertSame( '', $config->getLDAPUserGroup() ) ;
        $this->assertSame( '', $config->getLDAPUserDomain() ) ;
    }

    public function testFromXmlStringLdapEnabled() : void
    {
        $xml = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"localhost\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <ldap enabled=\"true\" host=\"ldap://ad1.hole\" "
             . "domainName=\"DC=hole,DC=ad\" userGroup=\"AQL_Admins\" "
             . "userDomain=\"HOLE\" verifyCert=\"false\" startTls=\"true\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $config = Config::fromXmlString( $xml ) ;
        $this->assertTrue( $config->getDoLDAPAuthentication() ) ;
        $this->assertSame( 'ldap://ad1.hole', $config->getLDAPHost() ) ;
        $this->assertSame( 'DC=hole,DC=ad', $config->getLDAPDomainName() ) ;
        $this->assertSame( 'AQL_Admins', $config->getLDAPUserGroup() ) ;
        $this->assertSame( 'HOLE', $config->getLDAPUserDomain() ) ;
        $this->assertFalse( $config->getLDAPVerifyCert() ) ;
        $this->assertFalse( $config->getLDAPDebugConnection() ) ;
        $this->assertTrue( $config->getLDAPStartTls() ) ;
    }

    public function testFromXmlStringDefaultJiraDisabled() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertFalse( $config->getJiraEnabled() ) ;
        $this->assertSame( '', $config->getJiraProjectId() ) ;
        $this->assertSame( '', $config->getJiraIssueTypeId() ) ;
        $this->assertSame( '', $config->getJiraQueryHashFieldId() ) ;
    }

    public function testFromXmlStringFeatureDefaults() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        // No <features> element - all default to off (except enableSpeechAlerts)
        $this->assertFalse( $config->getEnableMaintenanceWindows() ) ;
        $this->assertSame( 86400, $config->getDbaSessionTimeout() ) ;
        $this->assertTrue( $config->getEnableSpeechAlerts() ) ;
    }

    public function testFromXmlStringFeaturesExplicit() : void
    {
        $xml = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"localhost\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <features enableMaintenanceWindows=\"true\" "
             . "dbaSessionTimeout=\"3600\" enableSpeechAlerts=\"false\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $config = Config::fromXmlString( $xml ) ;
        $this->assertTrue( $config->getEnableMaintenanceWindows() ) ;
        $this->assertSame( 3600, $config->getDbaSessionTimeout() ) ;
        $this->assertFalse( $config->getEnableSpeechAlerts() ) ;
    }

    public function testFromXmlStringRedisDisabled() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertFalse( $config->getRedisEnabled() ) ;
        $this->assertSame( '', $config->getRedisUser() ) ;
        $this->assertSame( '', $config->getRedisPassword() ) ;
        $this->assertSame( 2, $config->getRedisConnectTimeout() ) ;
        $this->assertSame( 0, $config->getRedisDatabase() ) ;
    }

    public function testFromXmlStringPostgresqlDisabled() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertFalse( $config->getPostgresqlEnabled() ) ;
    }

    public function testFromXmlStringTestDbDefaultsEmpty() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( '', $config->getTestDbUser() ) ;
        $this->assertSame( '', $config->getTestDbPass() ) ;
        $this->assertSame( '', $config->getTestDbName() ) ;
    }

    public function testFromXmlStringConfigDbType() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( 'mysql', $config->getConfigDbType() ) ;
    }

    public function testFromXmlStringMonitoringStringFields() : void
    {
        // The killStatement, showSlaveStatement, globalStatusDb default values
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( 'kill :pid', $config->getKillStatement() ) ;
        $this->assertSame( 'show slave status', $config->getShowSlaveStatement() ) ;
        $this->assertSame( 'performance_schema', $config->getGlobalStatusDb() ) ;
    }

    public function testToStringDoesNotLeakAdminPassword() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $str = (string) $config ;
        $this->assertStringNotContainsString( 'admin_secret', $str ) ;
    }

    public function testToStringDoesNotLeakMonitorPassword() : void
    {
        // Monitor user creds aren't exposed via getters but verify defense-in-depth
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $str = (string) $config ;
        $this->assertStringNotContainsString( 'monitor_secret', $str ) ;
    }

    public function testToStringMasksPasswordsWithStars() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $str = (string) $config ;
        // Set passwords show as ********, not the real value
        $this->assertStringContainsString( 'password = ********', $str ) ;
    }

    public function testToStringIncludesNonSensitiveValues() : void
    {
        // The summary IS supposed to expose non-credential values for debugging
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $str = (string) $config ;
        $this->assertStringContainsString( '127.0.0.1', $str, 'host should be visible' ) ;
        $this->assertStringContainsString( '3306', $str, 'port should be visible' ) ;
        $this->assertStringContainsString( 'aql_app', $str, 'username should be visible' ) ;
        $this->assertStringContainsString( 'aql_db', $str, 'database name should be visible' ) ;
        $this->assertStringContainsString( 'America/Chicago', $str, 'timezone should be visible' ) ;
    }

    public function testToStringMentionsSecurityMasking() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $str = (string) $config ;
        $this->assertStringContainsString( 'security', $str ) ;
    }

    public function testToStringShowsNotSetForEmptyOptionalFields() : void
    {
        // Default v2 config has no <ldap> element
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $str = (string) $config ;
        // ldap host should be (not set), not "(not set)" verbatim — at minimum show 'not set'
        $this->assertStringContainsString( '(not set)', $str ) ;
    }

    // ========================================================================
    // isGroupedFormat() — covered indirectly elsewhere, but exercise edge cases
    // ========================================================================

    public function testIsGroupedFormatViaVersionAttribute() : void
    {
        // version="2" alone (no grouped elements) should still be detected
        $xml = simplexml_load_string(
            "<?xml version=\"1.0\"?>\n<config version=\"2\"></config>\n"
        ) ;
        $ref = new \ReflectionMethod( Config::class, 'isGroupedFormat' ) ;
        $ref->setAccessible( true ) ;
        $this->assertTrue( $ref->invoke( null, $xml ) ) ;
    }

    public function testIsGroupedFormatVersion1NoGroupedElements() : void
    {
        $xml = simplexml_load_string(
            "<?xml version=\"1.0\"?>\n<config>"
            . "<param name=\"x\">y</param>"
            . "</config>\n"
        ) ;
        $ref = new \ReflectionMethod( Config::class, 'isGroupedFormat' ) ;
        $ref->setAccessible( true ) ;
        $this->assertFalse( $ref->invoke( null, $xml ) ) ;
    }

    public function testIsGroupedFormatDetectsConfigdbElement() : void
    {
        $xml = simplexml_load_string(
            "<?xml version=\"1.0\"?>\n<config>"
            . "<configdb host=\"x\" port=\"1\" name=\"y\" />"
            . "</config>\n"
        ) ;
        $ref = new \ReflectionMethod( Config::class, 'isGroupedFormat' ) ;
        $ref->setAccessible( true ) ;
        $this->assertTrue( $ref->invoke( null, $xml ) ) ;
    }

    public function testIsGroupedFormatDetectsMonitoringElement() : void
    {
        $xml = simplexml_load_string(
            "<?xml version=\"1.0\"?>\n<config><monitoring baseUrl=\"x\" /></config>\n"
        ) ;
        $ref = new \ReflectionMethod( Config::class, 'isGroupedFormat' ) ;
        $ref->setAccessible( true ) ;
        $this->assertTrue( $ref->invoke( null, $xml ) ) ;
    }

    public function testIsGroupedFormatDetectsUserElement() : void
    {
        $xml = simplexml_load_string(
            "<?xml version=\"1.0\"?>\n<config>"
            . "<user type=\"admin\" name=\"x\" password=\"y\" />"
            . "</config>\n"
        ) ;
        $ref = new \ReflectionMethod( Config::class, 'isGroupedFormat' ) ;
        $ref->setAccessible( true ) ;
        $this->assertTrue( $ref->invoke( null, $xml ) ) ;
    }

    // ========================================================================
    // parseFlatConfig branches
    // ========================================================================

    public function testFlatConfigRejectsUnknownParameter() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Unknown parameter/' ) ;
        $xml = $this->v1Config( [ 'totallyMadeUpKey' => 'oops' ] ) ;
        Config::parseConfigXml( $xml ) ;
    }

    public function testFlatConfigDetectsDuplicateParameter() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Multiply set parameter/' ) ;
        // Hand-build XML with two dbHost entries
        $xml = "<?xml version=\"1.0\"?>\n<config>\n"
             . "    <param name=\"baseUrl\">https://x/y</param>\n"
             . "    <param name=\"dbHost\">a</param>\n"
             . "    <param name=\"dbHost\">b</param>\n"
             . "    <param name=\"dbName\">d</param>\n"
             . "    <param name=\"dbPort\">3306</param>\n"
             . "    <param name=\"dbUser\">u</param>\n"
             . "    <param name=\"dbPass\">p</param>\n"
             . "    <param name=\"timeZone\">UTC</param>\n"
             . "    <param name=\"issueTrackerBaseUrl\">https://x/</param>\n"
             . "    <param name=\"roQueryPart\">@@global.read_only</param>\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        Config::parseConfigXml( $xml ) ;
    }

    public function testFlatConfigDetectsDuplicateDbtype() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Multiply defined dbtype/' ) ;
        $xml = $this->v1Config( [], [
            [ 'name' => 'redis', 'enabled' => 'true' ],
            [ 'name' => 'redis', 'enabled' => 'false' ],
        ] ) ;
        Config::parseConfigXml( $xml ) ;
    }

    public function testDbtypeMissingNameAttributeReportsError() : void
    {
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/dbtype element missing name attribute/' ) ;
        $xml = "<?xml version=\"1.0\"?>\n<config>\n"
             . "    <param name=\"baseUrl\">https://x/y</param>\n"
             . "    <param name=\"dbHost\">a</param>\n"
             . "    <param name=\"dbName\">d</param>\n"
             . "    <param name=\"dbPort\">3306</param>\n"
             . "    <param name=\"dbUser\">u</param>\n"
             . "    <param name=\"dbPass\">p</param>\n"
             . "    <param name=\"timeZone\">UTC</param>\n"
             . "    <param name=\"issueTrackerBaseUrl\">https://x/</param>\n"
             . "    <param name=\"roQueryPart\">@@global.read_only</param>\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "    <dbtype enabled=\"false\" />\n"  // missing name
             . "</config>\n" ;
        Config::parseConfigXml( $xml ) ;
    }

    // ========================================================================
    // DB-backed methods — integration tests using the real aql_db.
    // These require a running MySQL with the AQL schema deployed.
    // If the DB is unavailable, the tests are skipped (not failed).
    // ========================================================================

    /**
     * Helper: get a real mysqli connection to aql_db, or skip the test.
     */
    private function getDbhOrSkip() : \mysqli
    {
        try {
            $dbc = new DBConnection() ;
            $dbh = $dbc->getConnection() ;
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Database not available: ' . $e->getMessage() ) ;
        }
        if ( ! ( $dbh instanceof \mysqli ) ) {
            $this->markTestSkipped( 'DBConnection returned non-mysqli connection' ) ;
        }
        return $dbh ;
    }

    public function testGetDbTypesReturnsNonEmptyArray() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $types = $config->getDbTypes( $dbh ) ;
        $this->assertIsArray( $types ) ;
        $this->assertNotEmpty( $types, 'host table db_type ENUM should have at least one value' ) ;
        $this->assertContains( 'MySQL', $types, 'MySQL should always be in the ENUM' ) ;
    }

    public function testGetDbTypesIncludesPostgreSQL() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $types = $config->getDbTypes( $dbh ) ;
        $this->assertContains( 'PostgreSQL', $types, 'PostgreSQL should be in the ENUM' ) ;
    }

    public function testGetDbTypesIncludesRedis() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $types = $config->getDbTypes( $dbh ) ;
        $this->assertContains( 'Redis', $types, 'Redis should be in the ENUM' ) ;
    }

    public function testGetDbTypePropertiesReturnsArrayKeyedByType() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $props = $config->getDbTypeProperties( $dbh ) ;
        $this->assertIsArray( $props ) ;
        $this->assertNotEmpty( $props ) ;
        // Each type should have enabled/username/password keys
        foreach ( $props as $type => $settings ) {
            $this->assertArrayHasKey( 'enabled', $settings, "$type must have 'enabled' key" ) ;
            $this->assertArrayHasKey( 'username', $settings, "$type must have 'username' key" ) ;
            $this->assertArrayHasKey( 'password', $settings, "$type must have 'password' key" ) ;
        }
    }

    public function testGetDbTypePropertiesMysqlIsEnabled() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $props = $config->getDbTypeProperties( $dbh ) ;
        $this->assertArrayHasKey( 'MySQL', $props ) ;
        $this->assertTrue( $props['MySQL']['enabled'], 'MySQL must always be enabled' ) ;
    }

    public function testGetEnabledDbTypesReturnsMysql() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $enabled = $config->getEnabledDbTypes( $dbh ) ;
        $this->assertIsArray( $enabled ) ;
        $this->assertContains( 'MySQL', $enabled, 'MySQL must always be enabled' ) ;
    }

    public function testGetEnabledDbTypesIsSubsetOfAllTypes() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $allTypes = $config->getDbTypes( $dbh ) ;
        $enabled = $config->getEnabledDbTypes( $dbh ) ;
        foreach ( $enabled as $type ) {
            $this->assertContains( $type, $allTypes,
                "Enabled type '$type' must exist in the ENUM" ) ;
        }
    }

    public function testGetDbTypesInUseReturnsArray() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $inUse = $config->getDbTypesInUse( $dbh ) ;
        $this->assertIsArray( $inUse ) ;
        // May be empty if no hosts are configured with should_monitor=1
        // but at minimum it should be an array
    }

    public function testGetDbTypesInUseIsSubsetOfAllTypes() : void
    {
        $dbh = $this->getDbhOrSkip() ;
        $config = new Config() ;
        $allTypes = $config->getDbTypes( $dbh ) ;
        $inUse = $config->getDbTypesInUse( $dbh ) ;
        foreach ( $inUse as $type ) {
            $this->assertContains( $type, $allTypes,
                "In-use type '$type' must exist in the ENUM" ) ;
        }
    }

    // ========================================================================
    // getDbInstanceName — one remaining uncovered getter
    // ========================================================================

    public function testFromXmlStringDbInstanceNameDefaultsEmpty() : void
    {
        $config = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( '', $config->getDbInstanceName() ) ;
    }

    // ========================================================================
    // getEnvironmentTypes() — reads real aql_config.xml, returns structured data
    // ========================================================================

    public function testGetEnvironmentTypesReturnsStructuredArray() : void
    {
        $config = new Config() ;
        $envTypes = $config->getEnvironmentTypes() ;
        // Our live config has environment_types (v2 format)
        $this->assertIsArray( $envTypes ) ;
        $this->assertNotEmpty( $envTypes ) ;
        // Each entry should have name, default, sort_order
        foreach ( $envTypes as $env ) {
            $this->assertArrayHasKey( 'name', $env ) ;
            $this->assertArrayHasKey( 'default', $env ) ;
            $this->assertArrayHasKey( 'sort_order', $env ) ;
            $this->assertIsBool( $env['default'] ) ;
            $this->assertIsInt( $env['sort_order'] ) ;
        }
    }

    public function testGetEnvironmentTypesHasExactlyOneDefault() : void
    {
        $config = new Config() ;
        $envTypes = $config->getEnvironmentTypes() ;
        if ( $envTypes === null ) {
            $this->markTestSkipped( 'Config is v1 format (no environment_types)' ) ;
        }
        $defaults = array_filter( $envTypes, fn( $e ) => $e['default'] === true ) ;
        $this->assertCount( 1, $defaults, 'Exactly one environment should be marked as default' ) ;
    }

    public function testGetEnvironmentTypesSortOrderAutoAssigned() : void
    {
        $config = new Config() ;
        $envTypes = $config->getEnvironmentTypes() ;
        if ( $envTypes === null ) {
            $this->markTestSkipped( 'Config is v1 format' ) ;
        }
        // Document-order sort: 10, 20, 30, ...
        foreach ( $envTypes as $i => $env ) {
            $this->assertSame( ( $i + 1 ) * 10, $env['sort_order'],
                "Environment '{$env['name']}' at position $i should have sort_order " . ( ( $i + 1 ) * 10 ) ) ;
        }
    }

    // ========================================================================
    // parseFlatConfig: dynamic DB type param branch (e.g., <param name="redisEnabled">)
    // This is the code path for DB type params in v1 format that AREN'T
    // in the paramList but match the /^[a-z]+(?:Enabled|Username|Password)$/ pattern.
    // ========================================================================

    public function testV1FlatConfigDynamicDbTypeParam() : void
    {
        // cassandraEnabled is not in paramList but matches the dynamic pattern
        $cfg = Config::parseConfigXml( $this->v1Config( [
            'cassandraEnabled' => 'true',
            'cassandraUsername' => 'cass_user',
            'cassandraPassword' => 'cass_pass',
        ] ) ) ;
        $this->assertSame( 'true', $cfg['cassandraEnabled'] ) ;
        $this->assertSame( 'cass_user', $cfg['cassandraUsername'] ) ;
        $this->assertSame( 'cass_pass', $cfg['cassandraPassword'] ) ;
    }

    public function testV1FlatConfigDuplicateDynamicDbTypeParam() : void
    {
        // Two <param name="cassandraEnabled"> entries should error
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Multiply set DB type parameter/' ) ;
        $xml = "<?xml version=\"1.0\"?>\n<config>\n"
             . "    <param name=\"baseUrl\">https://x/y</param>\n"
             . "    <param name=\"dbHost\">a</param>\n"
             . "    <param name=\"dbName\">d</param>\n"
             . "    <param name=\"dbPort\">3306</param>\n"
             . "    <param name=\"dbUser\">u</param>\n"
             . "    <param name=\"dbPass\">p</param>\n"
             . "    <param name=\"timeZone\">UTC</param>\n"
             . "    <param name=\"issueTrackerBaseUrl\">https://x/</param>\n"
             . "    <param name=\"roQueryPart\">@@global.read_only</param>\n"
             . "    <param name=\"cassandraEnabled\">true</param>\n"
             . "    <param name=\"cassandraEnabled\">false</param>\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        Config::parseConfigXml( $xml ) ;
    }

    public function testFlatConfigUnknownUserType() : void
    {
        // Note: this is technically a v2-format test since <user> is grouped
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/Unknown user type/' ) ;
        $xml = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"localhost\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"a\" password=\"p\" />\n"
             . "    <user type=\"superduper\" name=\"x\" password=\"y\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        Config::parseConfigXml( $xml ) ;
    }

    // ========================================================================
    // buildConfigValueArray() — the static helper used by getConfigValue()
    // Tests the same parsing logic but through a different code path.
    // ========================================================================

    private function buildFromV1( array $extraParams = [], array $dbtypes = [] ) : array
    {
        $xml = simplexml_load_string( $this->v1Config( $extraParams, $dbtypes ) ) ;
        return Config::buildConfigValueArray( $xml ) ;
    }

    private function buildFromV2( array $overrides = [], array $dbtypes = [] ) : array
    {
        $xml = simplexml_load_string( $this->v2Config( $overrides, $dbtypes ) ) ;
        return Config::buildConfigValueArray( $xml ) ;
    }

    public function testGetConfigValueV1FlatFormat() : void
    {
        $cfg = $this->buildFromV1() ;
        $this->assertSame( '127.0.0.1', $cfg['dbHost'] ) ;
        $this->assertSame( 'aql_app', $cfg['dbUser'] ) ;
    }

    public function testGetConfigValueV2GroupedFormat() : void
    {
        $cfg = $this->buildFromV2() ;
        $this->assertSame( '127.0.0.1', $cfg['dbHost'] ) ;
        $this->assertSame( 'aql_app', $cfg['dbUser'] ) ;
        $this->assertSame( 'aql_mon', $cfg['monitorUser'] ) ;
    }

    public function testGetConfigValueV2EnvironmentTypesParsing() : void
    {
        // Build a v2 config with explicit environment_types
        $xml = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"localhost\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <environment_types>\n"
             . "        <environment_type name=\"dev\" />\n"
             . "        <environment_type name=\"qa\" />\n"
             . "        <environment_type name=\"prod\" default=\"true\" />\n"
             . "    </environment_types>\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $cfg = Config::buildConfigValueArray( simplexml_load_string( $xml ) ) ;
        $this->assertSame( 'dev,qa,prod', $cfg['environments'] ) ;
        $this->assertSame( 'prod', $cfg['defaultEnvironment'] ) ;
    }

    public function testGetConfigValueRedisRegression() : void
    {
        // Same regression as parseConfigXml tests, but through the
        // buildConfigValueArray code path used by getConfigValue().
        $cfg = $this->buildFromV2( [
            'monitor_pass' => 'super_secret_monitor_password',
        ], [
            [ 'name' => 'redis', 'enabled' => 'true' ],
        ] ) ;
        $this->assertSame( '', $cfg['redisUser'] ?? '',
            'getConfigValue path: Redis must NOT inherit monitor user' ) ;
        $this->assertSame( '', $cfg['redisPassword'] ?? '',
            'getConfigValue path: Redis must NOT inherit monitor password' ) ;
    }

    public function testGetConfigValueRedisExplicitCredsSetRedisUserKey() : void
    {
        $cfg = $this->buildFromV2( [], [
            [ 'name' => 'redis', 'enabled' => 'true',
              'username' => 'aql_redis', 'password' => 'redis_pass' ],
        ] ) ;
        $this->assertSame( 'aql_redis', $cfg['redisUser'],
            '<dbtype name="redis" username="..."> must set redisUser key' ) ;
        $this->assertSame( 'redis_pass', $cfg['redisPassword'],
            '<dbtype name="redis" password="..."> must set redisPassword key' ) ;
    }

    public function testGetConfigValueDbtypeEnabledFlags() : void
    {
        $cfg = $this->buildFromV2( [], [
            [ 'name' => 'redis', 'enabled' => 'true' ],
            [ 'name' => 'postgresql', 'enabled' => 'false' ],
        ] ) ;
        $this->assertSame( 'true', $cfg['mysqlEnabled'] ) ;
        $this->assertSame( 'true', $cfg['redisEnabled'] ) ;
        $this->assertSame( 'false', $cfg['postgresqlEnabled'] ) ;
    }

    public function testGetConfigValueMissingKeyReturnsNullOrDefault() : void
    {
        // buildConfigValueArray returns the array; the default behavior is in getConfigValue.
        // We verify that missing keys are simply absent from the array.
        $cfg = $this->buildFromV2() ;
        $this->assertArrayNotHasKey( 'totallyMadeUpKey', $cfg ) ;
    }

    // ========================================================================
    // environment_types parsing — through parseConfigXml (parser code path)
    // ========================================================================

    /**
     * Build a v2 config with custom environment_types XML (raw, so we can
     * test edge cases like missing default, mixed sort_order, etc.).
     */
    private function v2ConfigWithEnvs( string $envXml ) : string
    {
        return "<?xml version=\"1.0\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"localhost\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <user type=\"monitor\" name=\"m\" password=\"mp\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    $envXml\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
    }

    public function testEnvironmentTypesPreservesDocumentOrder() : void
    {
        $cfg = Config::parseConfigXml( $this->v2ConfigWithEnvs(
            "<environment_types>\n"
            . "        <environment_type name=\"sandbox\" />\n"
            . "        <environment_type name=\"dev\" />\n"
            . "        <environment_type name=\"qa\" />\n"
            . "        <environment_type name=\"staging\" />\n"
            . "        <environment_type name=\"prod\" default=\"true\" />\n"
            . "    </environment_types>"
        ) ) ;
        $this->assertSame( 'sandbox,dev,qa,staging,prod', $cfg['environments'] ) ;
        $this->assertSame( 'prod', $cfg['defaultEnvironment'] ) ;
    }

    public function testEnvironmentTypesDefaultMarkerWorksOnAnyPosition() : void
    {
        // default="true" on the first element (not the typical case)
        $cfg = Config::parseConfigXml( $this->v2ConfigWithEnvs(
            "<environment_types>\n"
            . "        <environment_type name=\"prod\" default=\"true\" />\n"
            . "        <environment_type name=\"dev\" />\n"
            . "    </environment_types>"
        ) ) ;
        $this->assertSame( 'prod', $cfg['defaultEnvironment'] ) ;
    }

    public function testEnvironmentTypesWithoutDefault() : void
    {
        // No default attribute on any environment_type
        $cfg = Config::parseConfigXml( $this->v2ConfigWithEnvs(
            "<environment_types>\n"
            . "        <environment_type name=\"dev\" />\n"
            . "        <environment_type name=\"prod\" />\n"
            . "    </environment_types>"
        ) ) ;
        $this->assertSame( 'dev,prod', $cfg['environments'] ) ;
        // No default was set, so the key may not exist or may be empty
        $this->assertEmpty( $cfg['defaultEnvironment'] ?? '' ) ;
    }

    public function testEnvironmentTypesAllOrNothingSortOrderEnforced() : void
    {
        // Mixing explicit and implicit sort_order should error out
        $this->expectException( \Exception::class ) ;
        $this->expectExceptionMessageMatches( '/sort_order/' ) ;
        Config::parseConfigXml( $this->v2ConfigWithEnvs(
            "<environment_types>\n"
            . "        <environment_type name=\"dev\" sort_order=\"10\" />\n"
            . "        <environment_type name=\"qa\" />\n"
            . "        <environment_type name=\"prod\" sort_order=\"30\" default=\"true\" />\n"
            . "    </environment_types>"
        ) ) ;
    }

    public function testEnvironmentTypesAllExplicitSortOrderAllowed() : void
    {
        // All-explicit is fine
        $cfg = Config::parseConfigXml( $this->v2ConfigWithEnvs(
            "<environment_types>\n"
            . "        <environment_type name=\"dev\" sort_order=\"10\" />\n"
            . "        <environment_type name=\"qa\" sort_order=\"20\" />\n"
            . "        <environment_type name=\"prod\" sort_order=\"30\" default=\"true\" />\n"
            . "    </environment_types>"
        ) ) ;
        $this->assertSame( 'dev,qa,prod', $cfg['environments'] ) ;
        $this->assertSame( 'prod', $cfg['defaultEnvironment'] ) ;
    }

    public function testEnvironmentTypesAllImplicitSortOrderAllowed() : void
    {
        // All-implicit is fine (the default case)
        $cfg = Config::parseConfigXml( $this->v2ConfigWithEnvs(
            "<environment_types>\n"
            . "        <environment_type name=\"dev\" />\n"
            . "        <environment_type name=\"qa\" />\n"
            . "        <environment_type name=\"prod\" default=\"true\" />\n"
            . "    </environment_types>"
        ) ) ;
        $this->assertSame( 'dev,qa,prod', $cfg['environments'] ) ;
    }

    // ========================================================================
    // Integer field casting — minRefresh, defaultRefresh, redisConnectTimeout, redisDatabase
    // v1 format casts via parseFlatConfig; v2 via parseGroupedConfig.
    // ========================================================================

    public function testV1IntegerFieldsAreCastToInt() : void
    {
        $cfg = Config::parseConfigXml( $this->v1Config( [
            'minRefresh' => '20',
            'defaultRefresh' => '90',
            'redisConnectTimeout' => '5',
            'redisDatabase' => '3',
        ] ) ) ;
        $this->assertSame( 20, $cfg['minRefresh'], 'minRefresh should be int' ) ;
        $this->assertSame( 90, $cfg['defaultRefresh'], 'defaultRefresh should be int' ) ;
        $this->assertSame( 5, $cfg['redisConnectTimeout'], 'redisConnectTimeout should be int' ) ;
        $this->assertSame( 3, $cfg['redisDatabase'], 'redisDatabase should be int' ) ;
    }

    public function testV2IntegerFieldsAreCastToInt() : void
    {
        // v2: minRefresh and defaultRefresh on <monitoring>, redis* on <redis>
        $xml = "<?xml version=\"1.0\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"localhost\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" "
             . "minRefresh=\"30\" defaultRefresh=\"120\" />\n"
             . "    <redis connectTimeout=\"7\" database=\"4\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $cfg = Config::parseConfigXml( $xml ) ;
        $this->assertSame( 30, $cfg['minRefresh'] ) ;
        $this->assertSame( 120, $cfg['defaultRefresh'] ) ;
        $this->assertSame( 7, $cfg['redisConnectTimeout'] ) ;
        $this->assertSame( 4, $cfg['redisDatabase'] ) ;
    }

    public function testIntegerFieldDefaultsArePreserved() : void
    {
        // No min/defaultRefresh in config — should fall through to defaults (15, 60)
        $cfg = Config::parseConfigXml( $this->v1Config() ) ;
        $this->assertSame( 15, $cfg['minRefresh'] ) ;
        $this->assertSame( 60, $cfg['defaultRefresh'] ) ;
        $this->assertSame( 2, $cfg['redisConnectTimeout'] ) ;
        $this->assertSame( 0, $cfg['redisDatabase'] ) ;
    }

    // ========================================================================
    // connectTimeout / readTimeout — per-host timeout defaults in config
    // ========================================================================

    public function testConnectTimeoutDefaultsTo4WhenAbsent() : void
    {
        $cfg = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( 4, $cfg->getConnectTimeout() ) ;
    }

    public function testReadTimeoutDefaultsTo8WhenAbsent() : void
    {
        $cfg = Config::fromXmlString( $this->v2Config() ) ;
        $this->assertSame( 8, $cfg->getReadTimeout() ) ;
    }

    public function testConnectTimeoutParsedFromV2TimeoutsElement() : void
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"127.0.0.1\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <timeouts connectTimeout=\"10\" readTimeout=\"20\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $cfg = Config::fromXmlString( $xml ) ;
        $this->assertSame( 10, $cfg->getConnectTimeout() ) ;
        $this->assertSame( 20, $cfg->getReadTimeout() ) ;
    }

    public function testConnectTimeoutParsedAsInteger() : void
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"127.0.0.1\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <timeouts connectTimeout=\"15\" readTimeout=\"30\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $parsed = Config::parseConfigXml( $xml ) ;
        $this->assertSame( 15, $parsed['connectTimeout'] ) ;
        $this->assertSame( 30, $parsed['readTimeout'] ) ;
    }

    public function testTimeoutsAppearsInToString() : void
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<config version=\"2\">\n"
             . "    <configdb type=\"mysql\" host=\"127.0.0.1\" port=\"3306\" name=\"aql_db\" />\n"
             . "    <user type=\"admin\" name=\"u\" password=\"p\" />\n"
             . "    <monitoring baseUrl=\"http://x\" timeZone=\"UTC\" "
             . "issueTrackerBaseUrl=\"http://x\" roQueryPart=\"@@global.read_only\" />\n"
             . "    <timeouts connectTimeout=\"6\" readTimeout=\"12\" />\n"
             . "    <dbtype name=\"mysql\" enabled=\"true\" />\n"
             . "</config>\n" ;
        $cfg = Config::fromXmlString( $xml ) ;
        $str = (string) $cfg ;
        $this->assertStringContainsString( 'timeouts:', $str ) ;
        $this->assertStringContainsString( 'connectTimeout = 6', $str ) ;
        $this->assertStringContainsString( 'readTimeout    = 12', $str ) ;
    }

    public function testV1ConnectTimeoutDefaultsTo4() : void
    {
        // v1 flat format has no <timeouts> element — should fall through to defaults
        $cfg = Config::parseConfigXml( $this->v1Config() ) ;
        $this->assertSame( 4, $cfg['connectTimeout'] ) ;
        $this->assertSame( 8, $cfg['readTimeout'] ) ;
    }
}
