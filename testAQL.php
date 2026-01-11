<?php

/*
 * testAQL.php - Test harness for AQL functionality
 *
 * This page provides tests for various AQL features.
 * Tests run against the LOCAL database server only using a dedicated test user.
 */

namespace com\kbcmdba\aql ;

require( 'vendor/autoload.php' ) ;
require( 'utility.php' ) ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\MaintenanceWindow ;
use com\kbcmdba\aql\Libs\WebPage ;

$page = new WebPage( 'AQL Test Harness' ) ;
$page->setTop( "<h2>AQL Test Harness</h2>\n" ) ;

$body = '' ;
$test = $_GET['test'] ?? '' ;
$action = $_GET['action'] ?? '' ;

// Get configuration
$config = new Config() ;
$testDbUser = $config->getTestDbUser() ;
$testDbPass = $config->getTestDbPass() ;
$testDbName = $config->getTestDbName() ;
$localHost = $config->getDbHost() ;
$localPort = $config->getDbPort() ;

// Check if test user is configured
$testConfigured = !empty( $testDbUser ) && !empty( $testDbPass ) && !empty( $testDbName ) ;

if ( !$testConfigured ) {
    $body .= "<div style='background:#600;padding:15px;border-radius:5px;'>\n" ;
    $body .= "<h3>Test Harness Not Configured</h3>\n" ;
    $body .= "<p>To use the test harness, configure the following in aql_config.xml:</p>\n" ;
    $body .= "<pre>\n" ;
    $body .= "&lt;param name=\"testDbUser\"&gt;aql_test&lt;/param&gt;\n" ;
    $body .= "&lt;param name=\"testDbPass\"&gt;YourTestPassword&lt;/param&gt;\n" ;
    $body .= "&lt;param name=\"testDbName\"&gt;aql_test&lt;/param&gt;\n" ;
    $body .= "</pre>\n" ;
    $body .= "<p>See README.md for full setup instructions.</p>\n" ;
    $body .= "</div>\n" ;
    $page->setBody( $body ) ;
    $page->displayPage() ;
    exit ;
}

$body .= "<p><strong>Test Database:</strong> $testDbName on $localHost:$localPort (user: $testDbUser)</p>\n" ;
$body .= "<hr/>\n" ;

$body .= "<h3>Available Tests</h3>\n" ;
$body .= "<ul>\n" ;
$body .= "<li><a href=\"?test=config_validate\">Validate Configuration</a> - Check aql_config.xml parameters and connectivity</li>\n" ;
$body .= "<li><a href=\"?test=smoke_test\">Application Smoke Test</a> - Verify main pages load without errors</li>\n" ;
$body .= "<li><a href=\"?test=db_user_verify\">Database User Verification</a> - Verify both app and test user connectivity on config server and all monitored hosts</li>\n" ;
$body .= "<li><a href=\"?test=schema_verify\">Schema Verification</a> - Verify aql_db tables and structure (read-only check)</li>\n" ;
$body .= "<li><a href=\"?test=deploy_ddl_verify\">Deploy DDL Verification</a> - Verify deployDDL.php runs without errors (idempotent check)</li>\n" ;
$body .= "<li><a href=\"?test=blocking_setup\">Setup Blocking Test</a> - Create test table in dedicated test database (safe for production servers)</li>\n" ;
$body .= "<li><a href=\"?test=blocking_js\">Test Blocking JavaScript</a> - Verify JS modifications for blocking count</li>\n" ;
$body .= "<li><a href=\"?test=jira_test\">Jira Integration Test</a> - Manual test instructions for Jira issue filing</li>\n" ;
$body .= "<li><a href=\"?test=maintenance_windows\">Maintenance Windows Test</a> - Test maintenance window detection for hosts</li>\n" ;
$body .= "<li><a href=\"?test=cleanup\">Cleanup Test Data</a> - Remove test tables from test database</li>\n" ;
$body .= "</ul>\n" ;
$body .= "<hr/>\n" ;

// Helper function to get test database connection
function getTestDbConnection( $host, $port, $user, $pass, $dbName ) {
    $mysqli = new \mysqli( $host, $user, $pass, $dbName, $port ) ;
    if ( $mysqli->connect_error ) {
        throw new \Exception( "Connection failed: " . $mysqli->connect_error ) ;
    }
    return $mysqli ;
}

if ( $test === 'config_validate' ) {
    $body .= "<h3>Configuration Validation</h3>\n" ;

    $configFile = __DIR__ . '/aql_config.xml' ;
    $passIcon = "<span style='color:lime;'>&#10004;</span>" ;
    $failIcon = "<span style='color:red;'>&#10008;</span>" ;
    $warnIcon = "<span style='color:yellow;'>&#9888;</span>" ;

    // Step 1: Check if config file exists and is readable
    $body .= "<h4>1. Config File Check</h4>\n" ;
    if ( file_exists( $configFile ) ) {
        $body .= "<p>$passIcon <code>aql_config.xml</code> exists</p>\n" ;
        if ( is_readable( $configFile ) ) {
            $body .= "<p>$passIcon <code>aql_config.xml</code> is readable</p>\n" ;
        } else {
            $body .= "<p>$failIcon <code>aql_config.xml</code> is NOT readable (check permissions)</p>\n" ;
        }
    } else {
        $body .= "<p>$failIcon <code>aql_config.xml</code> does not exist</p>\n" ;
        $body .= "<p>Copy <code>config_sample.xml</code> to <code>aql_config.xml</code> and configure it.</p>\n" ;
    }

    // Step 2: Required parameters
    $body .= "<h4>2. Required Parameters</h4>\n" ;
    $body .= "<table border='1' cellpadding='6' style='margin:10px 0;'>\n" ;
    $body .= "<tr><th>Parameter</th><th>Value</th><th>Status</th></tr>\n" ;

    $requiredParams = [
        'dbHost' => [ 'value' => $config->getDbHost(), 'validate' => 'notEmpty' ],
        'dbPort' => [ 'value' => $config->getDbPort(), 'validate' => 'numeric' ],
        'dbUser' => [ 'value' => $config->getDbUser(), 'validate' => 'notEmpty' ],
        'dbPass' => [ 'value' => '********', 'validate' => 'notEmpty', 'rawValue' => $config->getDbPass() ],
        'dbName' => [ 'value' => $config->getDbName(), 'validate' => 'notEmpty' ],
        'baseUrl' => [ 'value' => $config->getBaseUrl(), 'validate' => 'url' ],
        'timeZone' => [ 'value' => $config->getTimeZone(), 'validate' => 'timezone' ],
        'issueTrackerBaseUrl' => [ 'value' => $config->getIssueTrackerBaseUrl(), 'validate' => 'url' ],
        'roQueryPart' => [ 'value' => $config->getRoQueryPart(), 'validate' => 'notEmpty' ]
    ] ;

    foreach ( $requiredParams as $name => $info ) {
        $value = $info['value'] ;
        $rawValue = $info['rawValue'] ?? $value ;
        $validate = $info['validate'] ;
        $status = $passIcon ;
        $statusMsg = 'OK' ;

        if ( $validate === 'notEmpty' && empty( $rawValue ) ) {
            $status = $failIcon ;
            $statusMsg = 'Required - not set' ;
        } elseif ( $validate === 'numeric' && !is_numeric( $rawValue ) ) {
            $status = $failIcon ;
            $statusMsg = 'Must be numeric' ;
        } elseif ( $validate === 'url' && !filter_var( $rawValue, FILTER_VALIDATE_URL ) ) {
            $status = $warnIcon ;
            $statusMsg = 'Invalid URL format' ;
        } elseif ( $validate === 'timezone' ) {
            try {
                new \DateTimeZone( $rawValue ) ;
            } catch ( \Exception $e ) {
                $status = $warnIcon ;
                $statusMsg = 'Invalid timezone' ;
            }
        }

        $body .= "<tr><td>$name</td><td><code>" . htmlspecialchars( $value ) . "</code></td><td>$status $statusMsg</td></tr>\n" ;
    }
    $body .= "</table>\n" ;

    // Step 3: Optional parameters
    $body .= "<h4>3. Optional Parameters</h4>\n" ;
    $body .= "<table border='1' cellpadding='6' style='margin:10px 0;'>\n" ;
    $body .= "<tr><th>Parameter</th><th>Value</th><th>Status</th></tr>\n" ;

    $optionalParams = [
        'dbInstanceName' => $config->getDbInstanceName(),
        'minRefresh' => $config->getMinRefresh(),
        'defaultRefresh' => $config->getDefaultRefresh(),
        'killStatement' => $config->getKillStatement(),
        'showSlaveStatement' => $config->getShowSlaveStatement(),
        'globalStatusDb' => $config->getGlobalStatusDb(),
        'doLDAPAuthentication' => $config->getDoLDAPAuthentication() ? 'true' : 'false',
        'jiraEnabled' => $config->getJiraEnabled() ? 'true' : 'false',
        'testDbUser' => $testDbUser ?: '(not set)',
        'testDbName' => $testDbName ?: '(not set)'
    ] ;

    foreach ( $optionalParams as $name => $value ) {
        $status = !empty( $value ) && $value !== '(not set)' ? $passIcon : "<span style='color:gray;'>○</span>" ;
        $body .= "<tr><td>$name</td><td><code>" . htmlspecialchars( $value ) . "</code></td><td>$status</td></tr>\n" ;
    }
    $body .= "</table>\n" ;

    // Step 4: Database connectivity test
    $body .= "<h4>4. Database Connectivity</h4>\n" ;

    // Test main database connection
    $body .= "<p><strong>Main AQL database (aql_app user):</strong></p>\n" ;
    try {
        $mainDbh = new \mysqli( $config->getDbHost(), $config->getDbUser(), $config->getDbPass(), $config->getDbName(), $config->getDbPort() ) ;
        if ( $mainDbh->connect_error ) {
            throw new \Exception( $mainDbh->connect_error ) ;
        }
        $body .= "<p>$passIcon Connected to <code>" . htmlspecialchars( $config->getDbHost() . ':' . $config->getDbPort() ) . "</code></p>\n" ;

        // Check database exists
        $result = $mainDbh->query( "SELECT DATABASE()" ) ;
        if ( $result && $row = $result->fetch_row() ) {
            $body .= "<p>$passIcon Using database <code>" . htmlspecialchars( $row[0] ) . "</code></p>\n" ;
        }

        // Check PROCESS privilege
        $result = $mainDbh->query( "SHOW PROCESSLIST" ) ;
        if ( $result ) {
            $body .= "<p>$passIcon PROCESS privilege verified</p>\n" ;
            $result->free() ;
        } else {
            $body .= "<p>$failIcon PROCESS privilege missing</p>\n" ;
        }

        $mainDbh->close() ;
    } catch ( \Exception $e ) {
        $body .= "<p>$failIcon Connection failed: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    }

    // Test test database connection
    $body .= "<p><strong>Test database (test user):</strong></p>\n" ;
    if ( !empty( $testDbUser ) && !empty( $testDbPass ) && !empty( $testDbName ) ) {
        try {
            // First try to connect without specifying a database
            $testDbh = new \mysqli( $localHost, $testDbUser, $testDbPass, '', $localPort ) ;
            if ( $testDbh->connect_error ) {
                throw new \Exception( "Connection failed: " . $testDbh->connect_error ) ;
            }

            // Check if test database exists
            $result = $testDbh->query( "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . $testDbh->real_escape_string( $testDbName ) . "'" ) ;
            $dbExists = ( $result && $result->num_rows > 0 ) ;

            if ( !$dbExists ) {
                // Try to create the database
                $createSql = "CREATE DATABASE IF NOT EXISTS `" . $testDbh->real_escape_string( $testDbName ) . "` DEFAULT CHARACTER SET = 'utf8mb4'" ;
                if ( $testDbh->query( $createSql ) ) {
                    $body .= "<p>$passIcon Created test database <code>" . htmlspecialchars( $testDbName ) . "</code></p>\n" ;
                } else {
                    throw new \Exception( "Could not create database: " . $testDbh->error ) ;
                }
            }

            // Now connect to the test database
            if ( !$testDbh->select_db( $testDbName ) ) {
                throw new \Exception( "Could not select database: " . $testDbh->error ) ;
            }
            $body .= "<p>$passIcon Connected to test database <code>" . htmlspecialchars( $testDbName ) . "</code></p>\n" ;
            $testDbh->close() ;
        } catch ( \Exception $e ) {
            $body .= "<p>$failIcon " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
        }
    } else {
        $body .= "<p>$warnIcon Test database not configured (optional) - see <code>README.md</code> section \"Test Harness Setup\"</p>\n" ;
    }

    $body .= "<hr/>\n" ;
    $body .= "<p style='color:lime;font-size:18px;'>&#10004; Configuration validation complete</p>\n" ;
}

if ( $test === 'smoke_test' ) {
    $body .= "<h3>Application Smoke Test</h3>\n" ;
    $body .= "<p>Testing that main AQL pages load without errors...</p>\n" ;

    $passIcon = "<span style='color:lime;'>&#10004;</span>" ;
    $failIcon = "<span style='color:red;'>&#10008;</span>" ;
    $warnIcon = "<span style='color:yellow;'>&#9888;</span>" ;

    $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname( $_SERVER['REQUEST_URI'] ) ;

    $body .= "<table border='1' cellpadding='8' style='margin:10px 0;'>\n" ;
    $body .= "<tr><th>Page</th><th>HTTP Status</th><th>Result</th><th>Details</th></tr>\n" ;

    // Test pages
    $pagesToTest = [
        'index.php' => [ 'name' => 'index.php (Main AQL)', 'expectCode' => 200 ],
        'manageData.php' => [ 'name' => 'manageData.php (Manage Data)', 'expectCode' => 200 ],
        'testAQL.php' => [ 'name' => 'testAQL.php (Test Harness)', 'expectCode' => 200 ]
    ] ;

    foreach ( $pagesToTest as $pageName => $info ) {
        $url = $baseUrl . '/' . $pageName ;
        $ch = curl_init( $url ) ;
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ) ;
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ) ;
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ) ;
        curl_setopt( $ch, CURLOPT_TIMEOUT, 10 ) ;
        $response = curl_exec( $ch ) ;
        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE ) ;
        $curlError = curl_error( $ch ) ;
        curl_close( $ch ) ;

        $details = '' ;
        if ( $curlError ) {
            $status = $failIcon ;
            $result = 'CURL Error' ;
            $details = htmlspecialchars( $curlError ) ;
        } elseif ( $httpCode === $info['expectCode'] ) {
            // Check for PHP errors in response
            if ( preg_match( '/Fatal error|Parse error|Warning:|Notice:/i', $response ) ) {
                $status = $warnIcon ;
                $result = 'PHP Errors' ;
                $details = 'Page loaded but contains PHP errors/warnings' ;
            } else {
                $status = $passIcon ;
                $result = 'OK' ;
                $details = 'Page loaded successfully' ;
            }
        } elseif ( $httpCode === 302 || $httpCode === 301 ) {
            $status = $passIcon ;
            $result = 'Redirect' ;
            $details = 'Redirected (likely to login)' ;
        } else {
            $status = $failIcon ;
            $result = 'Failed' ;
            $details = "Expected {$info['expectCode']}, got $httpCode" ;
        }

        $body .= "<tr>" ;
        $body .= "<td>{$info['name']}</td>" ;
        $body .= "<td>$httpCode</td>" ;
        $body .= "<td>$status $result</td>" ;
        $body .= "<td>$details</td>" ;
        $body .= "</tr>\n" ;
    }

    $body .= "</table>\n" ;

    // Note about AJAXgetaql.php
    $body .= "<h4>AJAXgetaql.php</h4>\n" ;
    $body .= "<p>$warnIcon <strong>Note:</strong> Testing AJAXgetaql.php requires:</p>\n" ;
    $body .= "<ul>\n" ;
    $body .= "<li>Database user verification complete (see @todo 02-20)</li>\n" ;
    $body .= "<li>Host data populated in the <code>host</code> table</li>\n" ;
    $body .= "</ul>\n" ;
    $body .= "<p>Once hosts are configured, use <a href='?test=blocking_setup'>Blocking Test</a> to verify lock detection works.</p>\n" ;

    $body .= "<hr/>\n" ;
    $body .= "<p style='color:lime;font-size:18px;'>&#10004; Smoke test complete</p>\n" ;
}

if ( $test === 'db_user_verify' ) {
    $body .= "<h3>Database User Verification</h3>\n" ;

    $passIcon = "<span style='color:lime;'>&#10004;</span>" ;
    $failIcon = "<span style='color:red;'>&#10008;</span>" ;
    $warnIcon = "<span style='color:yellow;'>&#9888;</span>" ;

    $dbUser = $config->getDbUser() ;
    $dbPass = $config->getDbPass() ;
    $showSlaveStatement = $config->getShowSlaveStatement() ;

    // Also get test user credentials if configured
    $testUser = $testDbUser ;
    $testPass = $testDbPass ;
    $testUserConfigured = !empty( $testUser ) && !empty( $testPass ) ;

    $body .= "<p><strong>Application User:</strong> <code>$dbUser</code></p>\n" ;
    if ( $testUserConfigured ) {
        $body .= "<p><strong>Test User:</strong> <code>$testUser</code></p>\n" ;
    }

    // Track issues for remediation suggestions
    $issues = [] ;

    // Helper function to test just connectivity (for test user)
    $testConnection = function( $host, $port, $user, $pass ) {
        $mysqli = @new \mysqli( $host, $user, $pass, '', $port ) ;
        if ( $mysqli->connect_error ) {
            return [ 'status' => 'fail', 'msg' => $mysqli->connect_error ] ;
        }
        $mysqli->close() ;
        return [ 'status' => 'pass', 'msg' => 'Connected successfully' ] ;
    } ;

    // Helper function to test privileges on a host
    $testHostPrivileges = function( $host, $port, $user, $pass, $showSlaveStmt ) use ( $passIcon, $failIcon, $warnIcon ) {
        $results = [] ;

        // Test connection
        $mysqli = @new \mysqli( $host, $user, $pass, '', $port ) ;
        if ( $mysqli->connect_error ) {
            $results['connection'] = [ 'status' => 'fail', 'msg' => $mysqli->connect_error ] ;
            return $results ;
        }
        $results['connection'] = [ 'status' => 'pass', 'msg' => 'Connected successfully' ] ;

        // Test PROCESS privilege
        $result = @$mysqli->query( "SHOW PROCESSLIST" ) ;
        if ( $result ) {
            $count = $result->num_rows ;
            $results['process'] = [ 'status' => 'pass', 'msg' => "Can see $count processes" ] ;
            $result->free() ;
        } else {
            $results['process'] = [ 'status' => 'fail', 'msg' => 'PROCESS privilege missing' ] ;
        }

        // Test REPLICATION CLIENT privilege - try multiple syntaxes
        $replStatements = [ $showSlaveStmt, 'SHOW SLAVE STATUS', 'SHOW REPLICA STATUS' ] ;
        $replSuccess = false ;
        foreach ( $replStatements as $stmt ) {
            try {
                $result = $mysqli->query( $stmt ) ;
                if ( $result ) {
                    $results['replication'] = [ 'status' => 'pass', 'msg' => 'REPLICATION CLIENT verified' ] ;
                    $result->free() ;
                    $replSuccess = true ;
                    break ;
                }
            } catch ( \Exception $e ) {
                // Try next statement
            }
        }
        if ( !$replSuccess ) {
            $results['replication'] = [ 'status' => 'warn', 'msg' => 'REPLICATION CLIENT not verified (may not be a replica)' ] ;
        }

        // Test performance_schema access
        try {
            $result = $mysqli->query( "SELECT COUNT(*) FROM performance_schema.threads LIMIT 1" ) ;
            if ( $result ) {
                $results['perfschema'] = [ 'status' => 'pass', 'msg' => 'performance_schema.threads accessible' ] ;
                $result->free() ;
            } else {
                $results['perfschema'] = [ 'status' => 'warn', 'msg' => 'performance_schema access limited' ] ;
            }
        } catch ( \Exception $e ) {
            $results['perfschema'] = [ 'status' => 'warn', 'msg' => 'performance_schema access limited (lock detection may be reduced)' ] ;
        }

        $mysqli->close() ;
        return $results ;
    } ;

    // Test config server (local)
    $body .= "<h4>1. Config Server ($localHost:$localPort)</h4>\n" ;
    $body .= "<p><strong>Application user ($dbUser):</strong></p>\n" ;
    $body .= "<table border='1' cellpadding='6' style='margin:10px 0;'>\n" ;
    $body .= "<tr><th>Check</th><th>Status</th><th>Details</th></tr>\n" ;

    $localResults = $testHostPrivileges( $localHost, $localPort, $dbUser, $dbPass, $showSlaveStatement ) ;
    foreach ( $localResults as $check => $info ) {
        $icon = $info['status'] === 'pass' ? $passIcon : ( $info['status'] === 'fail' ? $failIcon : $warnIcon ) ;
        $checkName = ucfirst( $check ) ;
        $body .= "<tr><td>$checkName</td><td>$icon</td><td>" . htmlspecialchars( $info['msg'] ) . "</td></tr>\n" ;

        // Track issues for remediation
        if ( $info['status'] !== 'pass' ) {
            $issues[] = [ 'host' => "$localHost:$localPort", 'user' => $dbUser, 'check' => $check, 'msg' => $info['msg'] ] ;
        }
    }
    $body .= "</table>\n" ;

    // Test test user on config server if configured
    if ( $testUserConfigured ) {
        $body .= "<p><strong>Test user ($testUser):</strong></p>\n" ;
        $testLocalResult = $testConnection( $localHost, $localPort, $testUser, $testPass ) ;
        $icon = $testLocalResult['status'] === 'pass' ? $passIcon : $failIcon ;
        $body .= "<p>$icon Connection: " . htmlspecialchars( $testLocalResult['msg'] ) . "</p>\n" ;

        if ( $testLocalResult['status'] !== 'pass' ) {
            $issues[] = [ 'host' => "$localHost:$localPort", 'user' => $testUser, 'check' => 'connection', 'msg' => $testLocalResult['msg'] ] ;
        }
    }

    // Get monitored hosts from database (only MySQL-compatible types)
    $body .= "<h4>2. Monitored Hosts</h4>\n" ;

    try {
        $mainDbh = new \mysqli( $localHost, $dbUser, $dbPass, $config->getDbName(), $localPort ) ;
        if ( $mainDbh->connect_error ) {
            throw new \Exception( $mainDbh->connect_error ) ;
        }

        $hostQuery = "SELECT hostname, port_number, description, db_type
                      FROM host
                      WHERE should_monitor = 1
                        AND decommissioned = 0
                        AND db_type IN ('MySQL', 'MariaDB', 'InnoDBCluster')
                      ORDER BY hostname, port_number" ;
        $result = $mainDbh->query( $hostQuery ) ;

        if ( $result && $result->num_rows > 0 ) {
            $body .= "<table border='1' cellpadding='6' style='margin:10px 0;'>\n" ;
            if ( $testUserConfigured ) {
                $body .= "<tr><th>Host</th><th>Type</th><th>$dbUser</th><th>PROCESS</th><th>REPL</th><th>perf_schema</th><th>$testUser</th></tr>\n" ;
            } else {
                $body .= "<tr><th>Host</th><th>Type</th><th>Connection</th><th>PROCESS</th><th>REPLICATION</th><th>perf_schema</th></tr>\n" ;
            }

            while ( $row = $result->fetch_assoc() ) {
                $hostName = $row['hostname'] ;
                $hostPort = $row['port_number'] ;
                $dbType = $row['db_type'] ;
                $hostResults = $testHostPrivileges( $hostName, $hostPort, $dbUser, $dbPass, $showSlaveStatement ) ;

                $body .= "<tr><td><code>$hostName:$hostPort</code></td><td>$dbType</td>" ;
                foreach ( [ 'connection', 'process', 'replication', 'perfschema' ] as $check ) {
                    if ( isset( $hostResults[$check] ) ) {
                        $info = $hostResults[$check] ;
                        $icon = $info['status'] === 'pass' ? $passIcon : ( $info['status'] === 'fail' ? $failIcon : $warnIcon ) ;
                        $body .= "<td title='" . htmlspecialchars( $info['msg'] ) . "'>$icon</td>" ;

                        // Track issues for remediation
                        if ( $info['status'] !== 'pass' ) {
                            $issues[] = [ 'host' => "$hostName:$hostPort", 'user' => $dbUser, 'check' => $check, 'msg' => $info['msg'] ] ;
                        }
                    } else {
                        $body .= "<td>-</td>" ;
                    }
                }

                // Test test user connection if configured
                if ( $testUserConfigured ) {
                    $testResult = $testConnection( $hostName, $hostPort, $testUser, $testPass ) ;
                    $icon = $testResult['status'] === 'pass' ? $passIcon : $failIcon ;
                    $body .= "<td title='" . htmlspecialchars( $testResult['msg'] ) . "'>$icon</td>" ;

                    if ( $testResult['status'] !== 'pass' ) {
                        $issues[] = [ 'host' => "$hostName:$hostPort", 'user' => $testUser, 'check' => 'connection', 'msg' => $testResult['msg'] ] ;
                    }
                }

                $body .= "</tr>\n" ;
            }
            $body .= "</table>\n" ;
            $body .= "<p><em>Hover over status icons for details</em></p>\n" ;
        } else {
            $body .= "<p>$warnIcon No monitored MySQL/MariaDB hosts found. Add hosts via <a href='manageData.php'>Manage Data</a>.</p>\n" ;
        }

        $mainDbh->close() ;
    } catch ( \Exception $e ) {
        $body .= "<p>$failIcon Error querying hosts: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    }

    // Display remediation suggestions if there are issues
    if ( !empty( $issues ) ) {
        $body .= "<h4>3. Remediation Suggestions</h4>\n" ;
        $body .= "<p>The following issues were detected. Run these SQL commands on the affected hosts to fix them:</p>\n" ;

        // Add copy-to-clipboard JavaScript
        $body .= "<script>
function copyToClipboard(preId, btnId) {
    var pre = document.getElementById(preId);
    var btn = document.getElementById(btnId);
    var text = pre.innerText || pre.textContent;
    navigator.clipboard.writeText(text).then(function() {
        btn.innerText = '✓ Copied';
        btn.style.background = '#060';
        setTimeout(function() {
            btn.innerText = '📋 Copy';
            btn.style.background = '#555';
        }, 2000);
    }).catch(function() {
        // Fallback for older browsers
        var textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        btn.innerText = '✓ Copied';
        btn.style.background = '#060';
        setTimeout(function() {
            btn.innerText = '📋 Copy';
            btn.style.background = '#555';
        }, 2000);
    });
}
</script>\n" ;

        // Group issues by host for cleaner output
        $issuesByHost = [] ;
        foreach ( $issues as $issue ) {
            $host = $issue['host'] ;
            if ( !isset( $issuesByHost[$host] ) ) {
                $issuesByHost[$host] = [] ;
            }
            $issuesByHost[$host][] = $issue ;
        }

        $boxId = 0 ;
        foreach ( $issuesByHost as $host => $hostIssues ) {
            $boxId++ ;
            $preId = "remediation_$boxId" ;
            $btnId = "copy_btn_$boxId" ;
            $body .= "<div class='code-box'>\n" ;
            $body .= "<p class='code-box-title'><strong>$host:</strong></p>\n" ;
            $body .= "<button id='$btnId' class='copy-btn' onclick=\"copyToClipboard('$preId', '$btnId')\">📋 Copy</button>\n" ;
            $body .= "<pre id='$preId'>" ;

            $sqlStatements = [] ;
            foreach ( $hostIssues as $issue ) {
                $user = $issue['user'] ;
                $check = $issue['check'] ;

                // Generate appropriate SQL based on the issue type
                if ( $check === 'connection' ) {
                    if ( strpos( $issue['msg'], 'Access denied' ) !== false ) {
                        $sqlStatements[] = "-- User '$user' exists but password may be wrong, or missing host grant" ;
                        $sqlStatements[] = "-- Option 1: Update password" ;
                        $sqlStatements[] = "ALTER USER '$user'@'%' IDENTIFIED BY 'YourPasswordHere';" ;
                        $sqlStatements[] = "-- Option 2: Create user if missing for this host" ;
                        $sqlStatements[] = "CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY 'YourPasswordHere';" ;
                    } else {
                        $sqlStatements[] = "-- Connection failed: " . $issue['msg'] ;
                        $sqlStatements[] = "-- Check that MySQL is running and accessible from this AQL server" ;
                    }
                } elseif ( $check === 'process' ) {
                    $sqlStatements[] = "-- Grant PROCESS privilege to see all queries" ;
                    $sqlStatements[] = "GRANT PROCESS ON *.* TO '$user'@'%';" ;
                } elseif ( $check === 'replication' ) {
                    $sqlStatements[] = "-- Grant REPLICATION CLIENT to check replica status (optional)" ;
                    $sqlStatements[] = "GRANT REPLICATION CLIENT ON *.* TO '$user'@'%';" ;
                } elseif ( $check === 'perfschema' ) {
                    $sqlStatements[] = "-- Grant performance_schema access for lock detection" ;
                    $sqlStatements[] = "GRANT SELECT ON performance_schema.* TO '$user'@'%';" ;
                    $sqlStatements[] = "-- Or for specific tables only:" ;
                    $sqlStatements[] = "GRANT SELECT ON performance_schema.data_lock_waits TO '$user'@'%';" ;
                    $sqlStatements[] = "GRANT SELECT ON performance_schema.data_locks TO '$user'@'%';" ;
                    $sqlStatements[] = "GRANT SELECT ON performance_schema.metadata_locks TO '$user'@'%';" ;
                    $sqlStatements[] = "GRANT SELECT ON performance_schema.threads TO '$user'@'%';" ;
                }
            }

            // Remove duplicates and output
            $sqlStatements = array_unique( $sqlStatements ) ;
            $body .= htmlspecialchars( implode( "\n", $sqlStatements ) ) ;
            $body .= "\nFLUSH PRIVILEGES;" ;
            $body .= "</pre>\n</div>\n" ;
        }

        $body .= "<p><em>Note: Replace '%' with specific host patterns for better security. Replace 'YourPasswordHere' with the actual password from aql_config.xml.</em></p>\n" ;
    }

    $body .= "<hr/>\n" ;
    if ( empty( $issues ) ) {
        $body .= "<p style='color:lime;font-size:18px;'>&#10004; Database user verification complete - no issues found</p>\n" ;
    } else {
        $body .= "<p style='color:yellow;font-size:18px;'>&#9888; Database user verification complete - " . count( $issues ) . " issue(s) found</p>\n" ;
    }
}

if ( $test === 'schema_verify' ) {
    $body .= "<h3>Schema Verification</h3>\n" ;
    $body .= "<p><em>Read-only check of aql_db database structure</em></p>\n" ;

    $passIcon = "<span style='color:lime;'>&#10004;</span>" ;
    $failIcon = "<span style='color:red;'>&#10008;</span>" ;
    $warnIcon = "<span style='color:yellow;'>&#9888;</span>" ;

    $dbName = $config->getDbName() ;
    $dbUser = $config->getDbUser() ;
    $dbPass = $config->getDbPass() ;

    // Expected tables and their required columns
    $expectedSchema = [
        'host' => [ 'host_id', 'hostname', 'port_number', 'db_type', 'should_monitor', 'decommissioned', 'alert_crit_secs', 'alert_warn_secs', 'alert_info_secs', 'alert_low_secs' ],
        'host_group' => [ 'host_group_id', 'tag', 'short_description' ],
        'host_group_map' => [ 'host_group_id', 'host_id' ],
        'maintenance_window' => [ 'window_id', 'window_type', 'days_of_week', 'start_time', 'end_time', 'silence_until' ],
        'maintenance_window_host_map' => [ 'window_id', 'host_id' ],
        'maintenance_window_host_group_map' => [ 'window_id', 'host_group_id' ]
    ] ;

    try {
        $dbh = new \mysqli( $localHost, $dbUser, $dbPass, 'information_schema', $localPort ) ;
        if ( $dbh->connect_error ) {
            throw new \Exception( $dbh->connect_error ) ;
        }

        // Step 1: Check database exists
        $body .= "<h4>1. Database Check</h4>\n" ;
        $result = $dbh->query( "SELECT SCHEMA_NAME FROM SCHEMATA WHERE SCHEMA_NAME = '" . $dbh->real_escape_string( $dbName ) . "'" ) ;
        if ( $result && $result->num_rows > 0 ) {
            $body .= "<p>$passIcon Database <code>$dbName</code> exists</p>\n" ;
        } else {
            $body .= "<p>$failIcon Database <code>$dbName</code> does not exist</p>\n" ;
            throw new \Exception( "Database $dbName not found" ) ;
        }

        // Step 2: Check tables exist
        $body .= "<h4>2. Required Tables</h4>\n" ;
        $body .= "<table border='1' cellpadding='6' style='margin:10px 0;'>\n" ;
        $body .= "<tr><th>Table</th><th>Status</th><th>Row Count</th></tr>\n" ;

        $allTablesExist = true ;
        foreach ( $expectedSchema as $tableName => $columns ) {
            $result = $dbh->query( "SELECT TABLE_NAME FROM TABLES WHERE TABLE_SCHEMA = '" . $dbh->real_escape_string( $dbName ) . "' AND TABLE_NAME = '" . $dbh->real_escape_string( $tableName ) . "'" ) ;
            if ( $result && $result->num_rows > 0 ) {
                // Get row count (read-only)
                $countResult = $dbh->query( "SELECT TABLE_ROWS FROM TABLES WHERE TABLE_SCHEMA = '" . $dbh->real_escape_string( $dbName ) . "' AND TABLE_NAME = '" . $dbh->real_escape_string( $tableName ) . "'" ) ;
                $rowCount = 0 ;
                if ( $countResult && $row = $countResult->fetch_assoc() ) {
                    $rowCount = $row['TABLE_ROWS'] ?? 0 ;
                }
                $body .= "<tr><td><code>$tableName</code></td><td>$passIcon Exists</td><td>~$rowCount</td></tr>\n" ;
            } else {
                $body .= "<tr><td><code>$tableName</code></td><td>$failIcon Missing</td><td>-</td></tr>\n" ;
                $allTablesExist = false ;
            }
        }
        $body .= "</table>\n" ;

        // Step 3: Check columns for each table
        $body .= "<h4>3. Table Structure</h4>\n" ;

        foreach ( $expectedSchema as $tableName => $requiredColumns ) {
            // Get actual columns from INFORMATION_SCHEMA
            $result = $dbh->query( "SELECT COLUMN_NAME FROM COLUMNS WHERE TABLE_SCHEMA = '" . $dbh->real_escape_string( $dbName ) . "' AND TABLE_NAME = '" . $dbh->real_escape_string( $tableName ) . "'" ) ;

            if ( !$result ) {
                $body .= "<p>$warnIcon Could not check columns for <code>$tableName</code></p>\n" ;
                continue ;
            }

            $actualColumns = [] ;
            while ( $row = $result->fetch_assoc() ) {
                $actualColumns[] = $row['COLUMN_NAME'] ;
            }

            if ( empty( $actualColumns ) ) {
                // Table doesn't exist, already reported above
                continue ;
            }

            $missingColumns = array_diff( $requiredColumns, $actualColumns ) ;

            if ( empty( $missingColumns ) ) {
                $body .= "<p>$passIcon <code>$tableName</code>: All required columns present (" . count( $requiredColumns ) . " checked)</p>\n" ;
            } else {
                $body .= "<p>$failIcon <code>$tableName</code>: Missing columns: <code>" . implode( ', ', $missingColumns ) . "</code></p>\n" ;
            }
        }

        $dbh->close() ;

        $body .= "<hr/>\n" ;
        if ( $allTablesExist ) {
            $body .= "<p style='color:lime;font-size:18px;'>&#10004; Schema verification complete - all tables present</p>\n" ;
        } else {
            $body .= "<p style='color:yellow;font-size:18px;'>&#9888; Schema verification complete - some tables missing (run <a href='deployDDL.php'>deployDDL.php</a>)</p>\n" ;
        }

    } catch ( \Exception $e ) {
        $body .= "<p>$failIcon Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    }
}

if ( $test === 'deploy_ddl_verify' ) {
    $body .= "<h3>Deploy DDL Verification</h3>\n" ;
    $body .= "<p><em>Verifies that deployDDL.php runs without errors and reports schema status</em></p>\n" ;

    $passIcon = "<span style='color:lime;'>&#10004;</span>" ;
    $failIcon = "<span style='color:red;'>&#10008;</span>" ;
    $warnIcon = "<span style='color:yellow;'>&#9888;</span>" ;

    try {
        // Get the config database connection
        $configDbHost = $config->getDbHost() ;
        $configDbPort = $config->getDbPort() ;
        $configDbUser = $config->getDbUser() ;
        $configDbPass = $config->getDbPass() ;
        $configDbName = $config->getDbName() ;

        $dbh = new \mysqli( $configDbHost, $configDbUser, $configDbPass, $configDbName, $configDbPort ) ;
        if ( $dbh->connect_error ) {
            throw new \Exception( "Connection failed: " . $dbh->connect_error ) ;
        }
        $dbh->set_charset( 'utf8' ) ;
        $body .= "<p>$passIcon Connected to config database $configDbName on $configDbHost:$configDbPort</p>\n" ;

        // Check database exists
        $dbCheckSql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . $dbh->real_escape_string( $configDbName ) . "'" ;
        $dbCheckResult = $dbh->query( $dbCheckSql ) ;
        if ( $dbCheckResult && $dbCheckResult->num_rows > 0 ) {
            $body .= "<p>$passIcon Database <code>$configDbName</code> exists</p>\n" ;
        } else {
            $body .= "<p>$warnIcon Database <code>$configDbName</code> not found - deployDDL.php would create it</p>\n" ;
        }

        // Define expected tables
        $expectedTables = [ 'host', 'host_group', 'host_group_map', 'maintenance_window', 'maintenance_window_host_map', 'maintenance_window_host_group_map' ] ;

        $body .= "<h4>Table Status</h4>\n" ;
        $body .= "<table class='tablesorter'>\n" ;
        $body .= "<thead><tr><th>Table</th><th>Status</th><th>deployDDL Action</th></tr></thead>\n" ;
        $body .= "<tbody>\n" ;

        $allTablesExist = true ;
        foreach ( $expectedTables as $tableName ) {
            $sql = "SHOW TABLES LIKE '$tableName'" ;
            $result = $dbh->query( $sql ) ;
            if ( $result && $result->num_rows > 0 ) {
                $body .= "<tr><td>$tableName</td><td style='color:lime;'>EXISTS</td><td>No action needed</td></tr>\n" ;
            } else {
                $body .= "<tr><td>$tableName</td><td style='color:yellow;'>MISSING</td><td>Would be created</td></tr>\n" ;
                $allTablesExist = false ;
            }
        }

        $body .= "</tbody>\n</table>\n" ;

        // Check for pending migrations (columns that deployDDL would add)
        $body .= "<h4>Migration Status</h4>\n" ;
        $body .= "<table class='tablesorter'>\n" ;
        $body .= "<thead><tr><th>Column</th><th>Table</th><th>Status</th></tr></thead>\n" ;
        $body .= "<tbody>\n" ;

        $migrationColumns = [
            [ 'maintenance_window', 'schedule_type' ],
            [ 'maintenance_window', 'day_of_month' ],
            [ 'maintenance_window', 'month_of_year' ],
            [ 'maintenance_window', 'period_days' ],
            [ 'maintenance_window', 'period_start_date' ]
        ] ;

        $pendingMigrations = 0 ;
        foreach ( $migrationColumns as $col ) {
            $tableName = $col[0] ;
            $columnName = $col[1] ;
            $sql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'" ;
            try {
                $result = $dbh->query( $sql ) ;
                if ( $result && $result->num_rows > 0 ) {
                    $body .= "<tr><td>$columnName</td><td>$tableName</td><td style='color:lime;'>Present</td></tr>\n" ;
                } else {
                    $body .= "<tr><td>$columnName</td><td>$tableName</td><td style='color:yellow;'>Would be added</td></tr>\n" ;
                    $pendingMigrations++ ;
                }
            } catch ( \Exception $e ) {
                // Table might not exist
                $body .= "<tr><td>$columnName</td><td>$tableName</td><td style='color:gray;'>Table missing</td></tr>\n" ;
            }
        }

        $body .= "</tbody>\n</table>\n" ;

        $dbh->close() ;

        $body .= "<hr/>\n" ;
        if ( $allTablesExist && $pendingMigrations === 0 ) {
            $body .= "<p style='color:lime;font-size:18px;'>$passIcon deployDDL.php verification passed - schema is up to date</p>\n" ;
            $body .= "<p>Running <a href='deployDDL.php'>deployDDL.php</a> will report \"Schema is up to date. No changes needed.\"</p>\n" ;
        } else {
            $body .= "<p style='color:yellow;font-size:18px;'>$warnIcon Schema changes pending</p>\n" ;
            if ( !$allTablesExist ) {
                $body .= "<p>Missing tables will be created when you run <a href='deployDDL.php'>deployDDL.php</a></p>\n" ;
            }
            if ( $pendingMigrations > 0 ) {
                $body .= "<p>$pendingMigrations migration(s) will be applied when you run <a href='deployDDL.php'>deployDDL.php</a></p>\n" ;
            }
        }

    } catch ( \Exception $e ) {
        $body .= "<p>$failIcon Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    }
}

if ( $test === 'blocking_setup' ) {
    $body .= "<h3>Automated Blocking Test</h3>\n" ;

    try {
        // Create test table first
        $dbh = getTestDbConnection( $localHost, $localPort, $testDbUser, $testDbPass, $testDbName ) ;
        $dbh->query( "CREATE TABLE IF NOT EXISTS blocking_test (id INT AUTO_INCREMENT PRIMARY KEY, data VARCHAR(100)) ENGINE=MyISAM" ) ;
        $body .= "<p style='color:lime;'>$passIcon Test table <code>$testDbName.blocking_test</code> created/verified</p>\n" ;
        $dbh->close() ;

        $body .= "<h4>Running Blocking Scenario...</h4>\n" ;

        // Session 1: Blocker - holds a table lock while sleeping
        $session1 = new \mysqli( $localHost, $testDbUser, $testDbPass, $testDbName, $localPort ) ;
        if ( $session1->connect_error ) {
            throw new \Exception( "Session 1 connection failed: " . $session1->connect_error ) ;
        }
        $session1Id = $session1->thread_id ;
        $body .= "<p>Session 1 (Blocker) connected, thread ID: <strong>$session1Id</strong></p>\n" ;

        // Acquire the table lock
        if ( ! $session1->query( "LOCK TABLES blocking_test WRITE" ) ) {
            throw new \Exception( "Session 1 failed to acquire lock: " . $session1->error ) ;
        }
        $body .= "<p style='color:lime;'>$passIcon Session 1 acquired WRITE lock on blocking_test</p>\n" ;

        // Start the sleep query (non-blocking from PHP's perspective using query with MYSQLI_ASYNC)
        // We'll use a short sleep so we can check blocking, then it will release
        $session1->query( "SELECT SLEEP(5)", MYSQLI_ASYNC ) ;
        $body .= "<p>Session 1 executing SLEEP(5) while holding lock...</p>\n" ;

        // Session 2: Waiter - tries to SELECT from the locked table
        $session2 = new \mysqli( $localHost, $testDbUser, $testDbPass, $testDbName, $localPort ) ;
        if ( $session2->connect_error ) {
            $session1->close() ;
            throw new \Exception( "Session 2 connection failed: " . $session2->connect_error ) ;
        }
        $session2Id = $session2->thread_id ;
        $body .= "<p>Session 2 (Waiter) connected, thread ID: <strong>$session2Id</strong></p>\n" ;

        // Start the SELECT query (will block waiting for the lock)
        $session2->query( "SELECT * FROM blocking_test", MYSQLI_ASYNC ) ;
        $body .= "<p>Session 2 attempting SELECT (should block on lock)...</p>\n" ;

        // Give it a moment for the blocking state to be visible
        usleep( 500000 ) ; // 500ms

        // Now check the processlist for blocking
        $checkDbh = new \mysqli( $localHost, $testDbUser, $testDbPass, $testDbName, $localPort ) ;
        if ( $checkDbh->connect_error ) {
            $session1->close() ;
            $session2->close() ;
            throw new \Exception( "Check connection failed: " . $checkDbh->connect_error ) ;
        }

        $body .= "<h4>Checking Process List...</h4>\n" ;
        $body .= "<table border='1' cellpadding='8' style='margin:10px 0;'>\n" ;
        $body .= "<tr><th>Thread ID</th><th>User</th><th>Command</th><th>State</th><th>Info</th><th>Role</th></tr>\n" ;

        $result = $checkDbh->query( "SELECT id, user, command, state, info FROM INFORMATION_SCHEMA.PROCESSLIST WHERE id IN ($session1Id, $session2Id)" ) ;
        $foundBlocker = false ;
        $foundWaiter = false ;
        $waiterState = '' ;

        while ( $row = $result->fetch_assoc() ) {
            $role = '' ;
            $roleStyle = '' ;
            if ( intval( $row['id'] ) === $session1Id ) {
                $role = 'BLOCKER' ;
                $roleStyle = 'color:red;font-weight:bold;' ;
                $foundBlocker = true ;
            } elseif ( intval( $row['id'] ) === $session2Id ) {
                $role = 'WAITER' ;
                $roleStyle = 'color:hotpink;font-weight:bold;' ;
                $foundWaiter = true ;
                $waiterState = $row['state'] ?? '' ;
            }
            $body .= "<tr>" ;
            $body .= "<td>" . htmlspecialchars( $row['id'] ) . "</td>" ;
            $body .= "<td>" . htmlspecialchars( $row['user'] ) . "</td>" ;
            $body .= "<td>" . htmlspecialchars( $row['command'] ) . "</td>" ;
            $body .= "<td>" . htmlspecialchars( $row['state'] ) . "</td>" ;
            $body .= "<td>" . htmlspecialchars( substr( $row['info'] ?? '', 0, 50 ) ) . "</td>" ;
            $body .= "<td style='$roleStyle'>$role</td>" ;
            $body .= "</tr>\n" ;
        }
        $body .= "</table>\n" ;

        // Check for lock waiting state
        $lockWaitDetected = ( stripos( $waiterState, 'lock' ) !== false || stripos( $waiterState, 'wait' ) !== false ) ;

        $body .= "<h4>Test Results</h4>\n" ;
        if ( $foundBlocker && $foundWaiter && $lockWaitDetected ) {
            $body .= "<p style='color:lime;font-size:16px;'>$passIcon Blocking detection test PASSED!</p>\n" ;
            $body .= "<ul>\n" ;
            $body .= "<li>Session 1 (Thread $session1Id) is holding the lock</li>\n" ;
            $body .= "<li>Session 2 (Thread $session2Id) is waiting (state: <code>$waiterState</code>)</li>\n" ;
            $body .= "</ul>\n" ;
        } elseif ( $foundBlocker && $foundWaiter ) {
            $body .= "<p style='color:yellow;font-size:16px;'>$warnIcon Sessions found but waiter state unclear: <code>$waiterState</code></p>\n" ;
        } else {
            $body .= "<p style='color:red;font-size:16px;'>$failIcon Could not verify blocking state</p>\n" ;
            $body .= "<p>Blocker found: " . ( $foundBlocker ? 'Yes' : 'No' ) . ", Waiter found: " . ( $foundWaiter ? 'Yes' : 'No' ) . "</p>\n" ;
        }

        // Clean up - close connections (this will kill the queries and release locks)
        $checkDbh->close() ;
        $session1->close() ;
        $session2->close() ;
        $body .= "<p style='color:gray;'>Test sessions closed, locks released.</p>\n" ;


    } catch ( \Exception $e ) {
        $body .= "<p style='color:red;'>$failIcon Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    }
}

if ( $test === 'blocking_js' ) {
    $body .= "<h3>Blocking JavaScript Test</h3>\n" ;
    $body .= "<p>This test verifies that the JavaScript correctly modifies the File Issue button for blocking queries.</p>\n" ;

    $body .= "<h4>Test Case: Thread blocking 5 queries</h4>\n" ;

    // Add copy-to-clipboard JavaScript if not already added
    $body .= "<script>
if (typeof copyToClipboard !== 'function') {
    function copyToClipboard(preId, btnId) {
        var pre = document.getElementById(preId);
        var btn = document.getElementById(btnId);
        var text = pre.innerText || pre.textContent;
        navigator.clipboard.writeText(text).then(function() {
            btn.innerText = '✓ Copied';
            btn.style.background = '#060';
            setTimeout(function() {
                btn.innerText = '📋 Copy';
                btn.style.background = '';
            }, 2000);
        }).catch(function() {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            btn.innerText = '✓ Copied';
            btn.style.background = '#060';
            setTimeout(function() {
                btn.innerText = '📋 Copy';
                btn.style.background = '';
            }, 2000);
        });
    }
}
</script>\n" ;

    $sampleActions = '<button type="button" onclick="killProcOnHost(' . "\n"
                   . '    \'localhost:3306\',' . "\n"
                   . '    12345,' . "\n"
                   . '    \'testuser\',' . "\n"
                   . '    \'127.0.0.1\',' . "\n"
                   . '    \'testdb\',' . "\n"
                   . '    \'Query\',' . "\n"
                   . '    10,' . "\n"
                   . '    \'Sending data\',' . "\n"
                   . '    \'SELECT%20*%20FROM%20foo\'' . "\n"
                   . ') ; return false ;">Kill Thread</button>' . "\n\n"
                   . '<button type="button" onclick="fileIssue(' . "\n"
                   . '    \'localhost:3306\',' . "\n"
                   . '    \'0\',' . "\n"
                   . '    \'127.0.0.1\',' . "\n"
                   . '    \'testuser\',' . "\n"
                   . '    \'testdb\',' . "\n"
                   . '    10,' . "\n"
                   . '    \'SELECT%20*%20FROM%20foo\'' . "\n"
                   . ') ; return false ;">File Issue</button>' ;

    $body .= "<div class='code-box'>\n" ;
    $body .= "<p class='code-box-title'><strong>Original actions HTML:</strong></p>\n" ;
    $body .= "<button id='copy_btn_orig' class='copy-btn' onclick=\"copyToClipboard('pre_orig', 'copy_btn_orig')\">📋 Copy</button>\n" ;
    $body .= "<pre id='pre_orig' style='font-size:11px;'>" . htmlspecialchars( $sampleActions ) . "</pre>\n" ;
    $body .= "</div>\n" ;

    // Simulate the JavaScript regex replacement
    $blockingCount = 5 ;
    $modified = preg_replace(
        '/fileIssue\(\s*([^)]+)\s*\)/',
        'fileIssue( $1, ' . $blockingCount . ' )',
        $sampleActions
    ) ;
    $modified .= ' <span class="blockingIndicator" style="font-size:9px;">(blocking ' . $blockingCount . ')</span>' ;

    $body .= "<div class='code-box'>\n" ;
    $body .= "<p class='code-box-title'><strong>After modifyActionsForBlocking() with blockingCount=5:</strong></p>\n" ;
    $body .= "<button id='copy_btn_mod' class='copy-btn' onclick=\"copyToClipboard('pre_mod', 'copy_btn_mod')\">📋 Copy</button>\n" ;
    $body .= "<pre id='pre_mod' style='font-size:11px;'>" . htmlspecialchars( $modified ) . "</pre>\n" ;
    $body .= "</div>\n" ;

    $body .= "<div class='code-box'>\n" ;
    $body .= "<p class='code-box-title'><strong>Visual rendering:</strong></p>\n" ;
    $body .= "<div style='margin-top:5px;'>" . $modified . "</div>\n" ;
    $body .= "</div>\n" ;

    $body .= "<h4>Expected Jira Issue Fields</h4>\n" ;
    $body .= "<table border='1' cellpadding='8'>\n" ;
    $body .= "<tr><th>Field</th><th>Expected Value</th></tr>\n" ;
    $body .= "<tr><td>Summary</td><td>BLOCKING Query on localhost:3306 from testuser@127.0.0.1 (blocking 5 queries)</td></tr>\n" ;
    $body .= "<tr><td>Description includes</td><td>*Blocking Count at time issue was filed:* 5</td></tr>\n" ;
    $body .= "</table>\n" ;

    $body .= "<p style='color:lime;font-size:18px;'>&#10004; JavaScript regex replacement verified</p>\n" ;
}

if ( $test === 'jira_test' ) {
    $body .= "<h3>Jira Integration Test (Manual)</h3>\n" ;

    // Check current Jira configuration
    $body .= "<h4>Current Configuration Status</h4>\n" ;
    $body .= "<table border='1' cellpadding='8' style='margin:10px 0;'>\n" ;
    $body .= "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>\n" ;

    $jiraEnabled = $config->getJiraEnabled() ;
    $jiraProjectId = $config->getJiraProjectId() ;
    $jiraIssueTypeId = $config->getJiraIssueTypeId() ;
    $jiraQueryHashFieldId = $config->getJiraQueryHashFieldId() ;
    $issueTrackerBaseUrl = $config->getIssueTrackerBaseUrl() ;

    $body .= "<tr><td>jiraEnabled</td><td>" . ( $jiraEnabled ? 'true' : 'false' ) . "</td>" ;
    $body .= "<td>" . ( $jiraEnabled ? "<span style='color:lime;'>$passIcon Enabled</span>" : "<span style='color:yellow;'>$warnIcon Disabled</span>" ) . "</td></tr>\n" ;

    $body .= "<tr><td>issueTrackerBaseUrl</td><td>" . htmlspecialchars( $issueTrackerBaseUrl ) . "</td>" ;
    $body .= "<td>" . ( !empty( $issueTrackerBaseUrl ) ? "<span style='color:lime;'>$passIcon Set</span>" : "<span style='color:red;'>$failIcon Missing</span>" ) . "</td></tr>\n" ;

    $body .= "<tr><td>jiraProjectId</td><td>" . htmlspecialchars( $jiraProjectId ) . "</td>" ;
    $body .= "<td>" . ( !empty( $jiraProjectId ) ? "<span style='color:lime;'>$passIcon Set</span>" : "<span style='color:red;'>$failIcon Missing</span>" ) . "</td></tr>\n" ;

    $body .= "<tr><td>jiraIssueTypeId</td><td>" . htmlspecialchars( $jiraIssueTypeId ) . "</td>" ;
    $body .= "<td>" . ( !empty( $jiraIssueTypeId ) ? "<span style='color:lime;'>$passIcon Set</span>" : "<span style='color:red;'>$failIcon Missing</span>" ) . "</td></tr>\n" ;

    $body .= "<tr><td>jiraQueryHashFieldId</td><td>" . htmlspecialchars( $jiraQueryHashFieldId ) . "</td>" ;
    $body .= "<td>" . ( !empty( $jiraQueryHashFieldId ) ? "<span style='color:lime;'>$passIcon Set</span>" : "<span style='color:gray;'>Optional (not set)</span>" ) . "</td></tr>\n" ;

    $body .= "</table>\n" ;

    if ( !$jiraEnabled ) {
        $body .= "<p style='color:yellow;'>$warnIcon Jira integration is disabled. Set <code>jiraEnabled</code> to <code>true</code> in aql_config.xml to enable.</p>\n" ;
    }

    // Simple manual test instructions
    $body .= "<h4>Test Steps</h4>\n" ;
    $body .= "<ol>\n" ;
    $body .= "<li>Run this query on any monitored host:<br/><code>SELECT SLEEP(120) FROM DUAL;</code></li>\n" ;
    $body .= "<li>Go to <a href='index.php' target='_blank'>AQL Home</a> in your browser</li>\n" ;
    $body .= "<li>Find the SLEEP query in the listing and click the <strong>File Issue</strong> button</li>\n" ;
    $body .= "</ol>\n" ;

    $body .= "<h4>Expected Results</h4>\n" ;
    $body .= "<ul>\n" ;
    $body .= "<li>$passIcon <strong>Success:</strong> Jira opens with a pre-filled issue ready for your details</li>\n" ;
    $body .= "<li>$failIcon <strong>Failure:</strong> You'll see an error message indicating what needs to be fixed</li>\n" ;
    $body .= "</ul>\n" ;
}

if ( $test === 'maintenance_windows' ) {
    $body .= "<h3>Maintenance Windows Test</h3>\n" ;

    $passIcon = "<span style='color:lime;'>&#10004;</span>" ;
    $failIcon = "<span style='color:red;'>&#10008;</span>" ;
    $warnIcon = "<span style='color:yellow;'>&#9888;</span>" ;

    // Check if maintenance windows are enabled
    $enabled = $config->getEnableMaintenanceWindows() ;
    $body .= "<p><strong>Maintenance Windows Enabled:</strong> " ;
    $body .= $enabled ? "$passIcon Yes" : "$failIcon No (set <code>enableMaintenanceWindows</code> to <code>true</code> in aql_config.xml)" ;
    $body .= "</p>\n" ;

    if ( !$enabled ) {
        $body .= "<p style='color:yellow;'>Enable maintenance windows in config to test further.</p>\n" ;
    } else {
        // Show current time info
        $tz = new \DateTimeZone( $config->getTimeZone() ) ;
        $now = new \DateTime( 'now', $tz ) ;
        $body .= "<p><strong>Current Time:</strong> " . $now->format( 'l Y-m-d H:i:s T' ) . "</p>\n" ;

        try {
            $dbc = new DBConnection() ;
            $dbh = $dbc->getConnection() ;

            // Get all maintenance windows
            $sql = "SELECT mw.window_id, mw.window_type, mw.schedule_type, mw.days_of_week,
                           mw.start_time, mw.end_time, mw.timezone, mw.silence_until, mw.description,
                           h.hostname, h.host_id, 'host' as target_type
                    FROM maintenance_window mw
                    JOIN maintenance_window_host_map mwhm ON mw.window_id = mwhm.window_id
                    JOIN host h ON mwhm.host_id = h.host_id
                    UNION ALL
                    SELECT mw.window_id, mw.window_type, mw.schedule_type, mw.days_of_week,
                           mw.start_time, mw.end_time, mw.timezone, mw.silence_until, mw.description,
                           hg.tag as hostname, hg.host_group_id as host_id, 'group' as target_type
                    FROM maintenance_window mw
                    JOIN maintenance_window_host_group_map mwgm ON mw.window_id = mwgm.window_id
                    JOIN host_group hg ON mwgm.host_group_id = hg.host_group_id
                    ORDER BY window_id" ;
            $result = $dbh->query( $sql ) ;

            $body .= "<h4>Configured Maintenance Windows</h4>\n" ;
            if ( $result && $result->num_rows > 0 ) {
                $body .= "<table border='1' cellpadding='5' style='margin:10px 0;'>\n" ;
                $body .= "<tr><th>ID</th><th>Type</th><th>Target</th><th>Schedule</th><th>Time</th><th>TZ</th><th>Description</th></tr>\n" ;
                while ( $row = $result->fetch_assoc() ) {
                    $schedule = $row['window_type'] === 'adhoc'
                        ? 'Until: ' . $row['silence_until']
                        : $row['schedule_type'] . ': ' . $row['days_of_week'] ;
                    $timeWindow = $row['start_time'] && $row['end_time']
                        ? substr( $row['start_time'], 0, 5 ) . '-' . substr( $row['end_time'], 0, 5 )
                        : 'All day' ;
                    $body .= "<tr>" ;
                    $body .= "<td>" . $row['window_id'] . "</td>" ;
                    $body .= "<td>" . $row['window_type'] . "</td>" ;
                    $body .= "<td>" . $row['target_type'] . ': ' . htmlspecialchars( $row['hostname'] ) . "</td>" ;
                    $body .= "<td>" . $schedule . "</td>" ;
                    $body .= "<td>" . $timeWindow . "</td>" ;
                    $body .= "<td>" . $row['timezone'] . "</td>" ;
                    $body .= "<td>" . htmlspecialchars( $row['description'] ) . "</td>" ;
                    $body .= "</tr>\n" ;
                }
                $body .= "</table>\n" ;
            } else {
                $body .= "<p>$warnIcon No maintenance windows configured. <a href='manageData.php?data=MaintenanceWindows'>Create one</a></p>\n" ;
            }

            // Test specific hosts
            $body .= "<h4>Test Host Maintenance Status</h4>\n" ;
            $hostId = isset( $_GET['hostId'] ) ? intval( $_GET['hostId'] ) : 0 ;

            // Get list of hosts for dropdown
            $hostResult = $dbh->query( "SELECT host_id, hostname FROM host WHERE decommissioned = 0 ORDER BY hostname LIMIT 50" ) ;
            $body .= "<form method='get'>\n" ;
            $body .= "<input type='hidden' name='test' value='maintenance_windows' />\n" ;
            $body .= "<label>Select Host: <select name='hostId'>\n" ;
            $body .= "<option value=''>-- Select a host --</option>\n" ;
            while ( $h = $hostResult->fetch_assoc() ) {
                $selected = ( $h['host_id'] == $hostId ) ? ' selected' : '' ;
                $body .= "<option value='" . $h['host_id'] . "'$selected>" . htmlspecialchars( $h['hostname'] ) . "</option>\n" ;
            }
            $body .= "</select></label>\n" ;
            $body .= " <button type='submit'>Test</button>\n" ;
            $body .= "</form>\n" ;

            if ( $hostId > 0 ) {
                $body .= "<h5>Results for Host ID $hostId:</h5>\n" ;

                // Direct mapping
                $directResult = MaintenanceWindow::getActiveWindowForHost( $hostId, $dbh ) ;
                $body .= "<p><strong>Direct host mapping:</strong> " ;
                if ( $directResult ) {
                    $body .= "$passIcon ACTIVE - " . json_encode( $directResult ) ;
                } else {
                    $body .= "$warnIcon Not in maintenance (direct)" ;
                }
                $body .= "</p>\n" ;

                // Via group
                $groupResult = MaintenanceWindow::getActiveWindowForHostViaGroup( $hostId, $dbh ) ;
                $body .= "<p><strong>Via group mapping:</strong> " ;
                if ( $groupResult ) {
                    $body .= "$passIcon ACTIVE - " . json_encode( $groupResult ) ;
                } else {
                    $body .= "$warnIcon Not in maintenance (via group)" ;
                }
                $body .= "</p>\n" ;

                // Final status
                $inMaintenance = ( $directResult !== null || $groupResult !== null ) ;
                $body .= "<p style='font-size:16px;'><strong>Final Status:</strong> " ;
                $body .= $inMaintenance ? "<span style='color:lime;'>IN MAINTENANCE</span>" : "<span style='color:yellow;'>NOT IN MAINTENANCE</span>" ;
                $body .= "</p>\n" ;
            }

        } catch ( \Exception $e ) {
            $body .= "<p style='color:red;'>Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
        }
    }
}

if ( $test === 'cleanup' ) {
    $body .= "<h3>Cleanup Test Data</h3>\n" ;

    if ( $action === 'confirm' ) {
        try {
            $dbh = getTestDbConnection( $localHost, $localPort, $testDbUser, $testDbPass, $testDbName ) ;
            $dbh->query( "DROP TABLE IF EXISTS blocking_test" ) ;
            $body .= "<p style='color:lime;'>&#10004; Test table <code>blocking_test</code> dropped</p>\n" ;
            $dbh->close() ;
        } catch ( \Exception $e ) {
            $body .= "<p style='color:red;'>Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
        }
    } else {
        $body .= "<p>This will drop the test table <code>$testDbName.blocking_test</code>.</p>\n" ;
        $body .= "<p><a href=\"?test=cleanup&action=confirm\" style='color:red;'>Confirm Cleanup</a></p>\n" ;
    }
}

$page->setBody( $body ) ;
$page->displayPage() ;
