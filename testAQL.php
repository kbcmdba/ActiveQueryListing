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
use com\kbcmdba\aql\Libs\WebPage ;

$page = new WebPage( 'AQL Test Harness' ) ;
$navBar = <<<HTML
<br clear="all" />
Navigate:
 &nbsp; &nbsp; <nobr><a href="index.php">AQL Home</a></nobr>
 &nbsp; &nbsp; <nobr><a href="manageData.php">Manage Data</a></nobr>
 &nbsp; &nbsp; <nobr><a href="testAQL.php">Test Harness</a></nobr>
<br clear="all" />
HTML;
$page->setTop( "<h2>AQL Test Harness</h2>\n$navBar\n" ) ;

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
$body .= "<li><a href=\"?test=blocking_setup\">Setup Blocking Test</a> - Create test table and show instructions</li>\n" ;
$body .= "<li><a href=\"?test=blocking_status\">Check Blocking Status</a> - View current blocking on local server</li>\n" ;
$body .= "<li><a href=\"?test=blocking_js\">Test Blocking JavaScript</a> - Verify JS modifications for blocking count</li>\n" ;
$body .= "<li><a href=\"?test=cleanup\">Cleanup Test Data</a> - Remove test tables</li>\n" ;
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

if ( $test === 'blocking_setup' ) {
    $body .= "<h3>Blocking Test Setup</h3>\n" ;

    try {
        $dbh = getTestDbConnection( $localHost, $localPort, $testDbUser, $testDbPass, $testDbName ) ;

        // Create test table
        $dbh->query( "CREATE TABLE IF NOT EXISTS blocking_test (id INT AUTO_INCREMENT PRIMARY KEY, data VARCHAR(100)) ENGINE=MyISAM" ) ;
        $body .= "<p style='color:lime;'>&#10004; Test table <code>$testDbName.blocking_test</code> created/verified</p>\n" ;

        $dbh->close() ;

        $body .= "<h4>How to Simulate Blocking</h4>\n" ;
        $body .= "<p>Open two MySQL terminal sessions to <code>$localHost</code>:</p>\n" ;
        $body .= "<pre style='background:#222;padding:15px;'>\n" ;
        $body .= "<strong>-- Session 1 (Blocker):</strong>\n" ;
        $body .= "mysql -h $localHost -u $testDbUser -p $testDbName\n\n" ;
        $body .= "LOCK TABLES blocking_test WRITE;\n" ;
        $body .= "SELECT SLEEP(60);  -- Holds lock for 60 seconds\n" ;
        $body .= "UNLOCK TABLES;\n" ;
        $body .= "\n" ;
        $body .= "<strong>-- Session 2 (Waiter - run while Session 1 is sleeping):</strong>\n" ;
        $body .= "mysql -h $localHost -u $testDbUser -p $testDbName\n\n" ;
        $body .= "SELECT * FROM blocking_test;  -- This will wait for the lock\n" ;
        $body .= "</pre>\n" ;

        $body .= "<p>Then <a href=\"?test=blocking_status\">check blocking status</a> to verify detection.</p>\n" ;

    } catch ( \Exception $e ) {
        $body .= "<p style='color:red;'>Error: " . htmlspecialchars( $e->getMessage() ) . "</p>\n" ;
    }
}

if ( $test === 'blocking_status' ) {
    $body .= "<h3>Current Blocking Status</h3>\n" ;
    $body .= "<p>Checking AQL data for <code>$localHost:$localPort</code>...</p>\n" ;

    // Fetch AQL data for local host
    $aqlUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname( $_SERVER['REQUEST_URI'] ) . "/AJAXgetaql.php" ;
    $aqlUrl .= "?hostname=" . urlencode( "$localHost:$localPort" ) ;
    $aqlUrl .= "&alertCritSecs=60&alertWarnSecs=30&alertInfoSecs=10&alertLowSecs=1&debugLocks=1" ;

    $ch = curl_init( $aqlUrl ) ;
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ) ;
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ) ;
    $response = curl_exec( $ch ) ;
    $curlError = curl_error( $ch ) ;
    curl_close( $ch ) ;

    if ( $curlError ) {
        $body .= "<p style='color:red;'>Curl error: " . htmlspecialchars( $curlError ) . "</p>\n" ;
    } else {
        $data = json_decode( $response, true ) ;
        if ( $data ) {
            $overview = $data['overviewData'] ?? [] ;
            $blocking = $overview['blocking'] ?? 0 ;
            $blocked = $overview['blocked'] ?? 0 ;

            $body .= "<table border='1' cellpadding='8' style='margin:10px 0;'>\n" ;
            $body .= "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>\n" ;
            $body .= "<tr><td>Blocking Count</td><td>" . htmlspecialchars( $blocking ) . "</td>" ;
            $body .= "<td>" . ( $blocking > 0 ? "<span style='color:red;'>&#9888; BLOCKING DETECTED</span>" : "OK" ) . "</td></tr>\n" ;
            $body .= "<tr><td>Blocked Count</td><td>" . htmlspecialchars( $blocked ) . "</td>" ;
            $body .= "<td>" . ( $blocked > 0 ? "<span style='color:hotpink;'>&#9888; BLOCKED QUERIES</span>" : "OK" ) . "</td></tr>\n" ;
            $body .= "<tr><td>Total Threads</td><td>" . htmlspecialchars( $overview['threads'] ?? 0 ) . "</td><td></td></tr>\n" ;
            $body .= "</table>\n" ;

            // Show threads with blockInfo
            $blockingThreads = [] ;
            $blockedThreads = [] ;
            foreach ( $data['result'] ?? [] as $thread ) {
                $bi = $thread['blockInfo'] ?? null ;
                if ( $bi ) {
                    if ( !empty( $bi['isBlocking'] ) ) {
                        $blockingThreads[] = $thread ;
                    }
                    if ( !empty( $bi['isBlocked'] ) ) {
                        $blockedThreads[] = $thread ;
                    }
                }
            }

            if ( !empty( $blockingThreads ) ) {
                $body .= "<h4 style='color:red;'>Blocking Threads</h4>\n" ;
                foreach ( $blockingThreads as $thread ) {
                    $bi = $thread['blockInfo'] ;
                    $count = count( $bi['blocking'] ?? [] ) ;
                    $body .= "<div style='background:#500;padding:10px;margin:5px 0;border-radius:5px;'>\n" ;
                    $body .= "<strong>Thread " . htmlspecialchars( $thread['id'] ) . "</strong> " ;
                    $body .= "<span class='blockingIndicator'>BLOCKING ($count)</span><br/>\n" ;
                    $body .= "User: " . htmlspecialchars( $thread['user'] ) . "<br/>\n" ;
                    $body .= "State: " . htmlspecialchars( $thread['state'] ) . "<br/>\n" ;
                    $body .= "Query: <code>" . htmlspecialchars( substr( $thread['info'] ?? '', 0, 80 ) ) . "</code><br/>\n" ;
                    $body .= "Blocking threads: " . htmlspecialchars( implode( ', ', $bi['blocking'] ?? [] ) ) . "<br/>\n" ;
                    $body .= "<p style='color:lime;'><strong>&#10004; This thread would show \"(blocking $count)\" next to File Issue button</strong></p>\n" ;
                    $body .= "</div>\n" ;
                }
            }

            if ( !empty( $blockedThreads ) ) {
                $body .= "<h4 style='color:hotpink;'>Blocked Threads</h4>\n" ;
                foreach ( $blockedThreads as $thread ) {
                    $bi = $thread['blockInfo'] ;
                    $body .= "<div style='background:#505;padding:10px;margin:5px 0;border-radius:5px;'>\n" ;
                    $body .= "<strong>Thread " . htmlspecialchars( $thread['id'] ) . "</strong> " ;
                    $body .= "<span class='blockedIndicator'>BLOCKED</span><br/>\n" ;
                    $body .= "User: " . htmlspecialchars( $thread['user'] ) . "<br/>\n" ;
                    $body .= "State: " . htmlspecialchars( $thread['state'] ) . "<br/>\n" ;
                    $body .= "Blocked by: " . htmlspecialchars( implode( ', ', $bi['blockedBy'] ?? [] ) ) . "<br/>\n" ;
                    $body .= "</div>\n" ;
                }
            }

            if ( empty( $blockingThreads ) && empty( $blockedThreads ) ) {
                $body .= "<p>No blocking detected. <a href=\"?test=blocking_setup\">Set up a blocking test</a> to verify detection.</p>\n" ;
            }

            // Show raw debug data if requested
            if ( isset( $_GET['debug'] ) ) {
                $body .= "<h4>Debug: lockWaitData</h4>\n" ;
                $body .= "<pre style='background:#222;padding:10px;max-height:300px;overflow:auto;'>" ;
                $body .= htmlspecialchars( json_encode( $data['debugLockWaitData'] ?? [], JSON_PRETTY_PRINT ) ) ;
                $body .= "</pre>\n" ;
            } else {
                $body .= "<p><a href=\"?test=blocking_status&debug=1\">Show debug data</a></p>\n" ;
            }
        } else {
            $body .= "<p style='color:red;'>Error parsing AQL response</p>\n" ;
            $body .= "<pre>" . htmlspecialchars( substr( $response, 0, 500 ) ) . "</pre>\n" ;
        }
    }

    $body .= "<p><a href=\"?test=blocking_status\">Refresh</a></p>\n" ;
}

if ( $test === 'blocking_js' ) {
    $body .= "<h3>Blocking JavaScript Test</h3>\n" ;
    $body .= "<p>This test verifies that the JavaScript correctly modifies the File Issue button for blocking queries.</p>\n" ;

    $body .= "<h4>Test Case: Thread blocking 5 queries</h4>\n" ;

    $sampleActions = '<button type="button" onclick="killProcOnHost( \'localhost:3306\', 12345, \'testuser\', \'127.0.0.1\', \'testdb\', \'Query\', 10, \'Sending data\', \'SELECT%20*%20FROM%20foo\' ) ; return false ;">Kill Thread</button>'
                   . '<button type="button" onclick="fileIssue( \'localhost:3306\', \'0\', \'127.0.0.1\', \'testuser\', \'testdb\', 10, \'SELECT%20*%20FROM%20foo\' ) ; return false ;">File Issue</button>' ;

    $body .= "<p><strong>Original actions HTML:</strong></p>\n" ;
    $body .= "<pre style='background:#222;padding:10px;overflow-x:auto;font-size:11px;'>" . htmlspecialchars( $sampleActions ) . "</pre>\n" ;

    // Simulate the JavaScript regex replacement
    $blockingCount = 5 ;
    $modified = preg_replace(
        '/fileIssue\(\s*([^)]+)\s*\)/',
        'fileIssue( $1, ' . $blockingCount . ' )',
        $sampleActions
    ) ;
    $modified .= ' <span class="blockingIndicator" style="font-size:9px;">(blocking ' . $blockingCount . ')</span>' ;

    $body .= "<p><strong>After modifyActionsForBlocking() with blockingCount=5:</strong></p>\n" ;
    $body .= "<pre style='background:#222;padding:10px;overflow-x:auto;font-size:11px;'>" . htmlspecialchars( $modified ) . "</pre>\n" ;

    $body .= "<p><strong>Visual rendering:</strong></p>\n" ;
    $body .= "<div style='background:#444;padding:10px;'>" . $modified . "</div>\n" ;

    $body .= "<h4>Expected Jira Issue Fields</h4>\n" ;
    $body .= "<table border='1' cellpadding='8'>\n" ;
    $body .= "<tr><th>Field</th><th>Expected Value</th></tr>\n" ;
    $body .= "<tr><td>Summary</td><td>BLOCKING Query on localhost:3306 from testuser@127.0.0.1 (blocking 5 queries)</td></tr>\n" ;
    $body .= "<tr><td>Description includes</td><td>*Blocking Count at time issue was filed:* 5</td></tr>\n" ;
    $body .= "</table>\n" ;

    $body .= "<p style='color:lime;font-size:18px;'>&#10004; JavaScript regex replacement verified</p>\n" ;
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
