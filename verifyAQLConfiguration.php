<?php

/*
 * verifyAQLConfiguration.php - Configuration verification and setup guidance
 *
 * This page helps new users verify their AQL configuration and provides
 * actionable guidance for fixing issues. Unlike testAQL.php, this page
 * works even with incomplete configuration.
 *
 * aql - Active Query Listing
 *
 * Copyright (C) 2018 Kevin Benton - kbcmdba [at] gmail [dot] com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\aql ;

// Minimal bootstrap - don't fail if config is broken
error_reporting( E_ALL ) ;
ini_set( 'display_errors', 0 ) ;

$configFile = __DIR__ . '/aql_config.xml' ;
$sampleFile = __DIR__ . '/config_sample.xml' ;

// Status icons
$passIcon = "<span class='status-pass'>&#10004;</span>" ;
$failIcon = "<span class='status-fail'>&#10008;</span>" ;
$warnIcon = "<span class='status-warn'>&#9888;</span>" ;
$infoIcon = "<span class='status-info'>&#9679;</span>" ;

// Track overall status
$criticalErrors = 0 ;
$warnings = 0 ;
$configValues = [] ;
$configLoaded = false ;

// Try to load configuration
if ( file_exists( $configFile ) && is_readable( $configFile ) ) {
    $xml = @simplexml_load_file( $configFile ) ;
    if ( $xml ) {
        foreach ( $xml as $v ) {
            $key = (string) $v['name'] ;
            $configValues[$key] = (string) $v ;
        }
        $configLoaded = true ;
    }
}

// Define all parameters with metadata
$allParams = [
    // Required parameters
    'dbHost' => [
        'required' => true,
        'description' => 'Database server hostname or IP',
        'example' => '127.0.0.1',
        'validate' => 'notEmpty'
    ],
    'dbPort' => [
        'required' => true,
        'description' => 'Database server port',
        'example' => '3306',
        'validate' => 'numeric'
    ],
    'dbUser' => [
        'required' => true,
        'description' => 'Database username for AQL',
        'example' => 'aql_app',
        'validate' => 'notEmpty'
    ],
    'dbPass' => [
        'required' => true,
        'description' => 'Database password',
        'example' => 'YourSecurePassword',
        'validate' => 'notEmpty',
        'sensitive' => true
    ],
    'dbName' => [
        'required' => true,
        'description' => 'AQL database name',
        'example' => 'aql_db',
        'validate' => 'notEmpty'
    ],
    'baseUrl' => [
        'required' => true,
        'description' => 'Full URL to AJAXgetaql.php',
        'example' => 'https://yourserver.com/aql/AJAXgetaql.php',
        'validate' => 'url'
    ],
    'timeZone' => [
        'required' => true,
        'description' => 'PHP timezone identifier',
        'example' => 'America/Chicago',
        'validate' => 'timezone'
    ],
    'issueTrackerBaseUrl' => [
        'required' => true,
        'description' => 'Base URL for your issue tracker',
        'example' => 'https://yourcompany.atlassian.net/',
        'validate' => 'url'
    ],
    'roQueryPart' => [
        'required' => true,
        'description' => 'SQL expression to check read-only status',
        'example' => '@@global.read_only',
        'validate' => 'notEmpty',
        'default' => '@@global.read_only'
    ],

    // Optional parameters - Database
    'dbInstanceName' => [
        'required' => false,
        'description' => 'MS-SQL instance name (if applicable)',
        'example' => 'SQLEXPRESS',
        'validate' => 'none',
        'default' => ''
    ],
    'globalStatusDb' => [
        'required' => false,
        'description' => 'Database containing global_status table',
        'example' => 'performance_schema',
        'validate' => 'notEmpty',
        'default' => 'performance_schema'
    ],
    'killStatement' => [
        'required' => false,
        'description' => 'SQL to kill a query (use :pid placeholder)',
        'example' => 'CALL mysql.rds_kill(:pid)',
        'validate' => 'notEmpty',
        'default' => 'kill :pid'
    ],
    'showSlaveStatement' => [
        'required' => false,
        'description' => 'SQL to check replication status',
        'example' => 'show slave status',
        'validate' => 'notEmpty',
        'default' => 'show slave status'
    ],

    // Optional parameters - Refresh
    'minRefresh' => [
        'required' => false,
        'description' => 'Minimum refresh interval (seconds)',
        'example' => '15',
        'validate' => 'numeric',
        'default' => '15'
    ],
    'defaultRefresh' => [
        'required' => false,
        'description' => 'Default refresh interval (seconds)',
        'example' => '60',
        'validate' => 'numeric',
        'default' => '60'
    ],

    // Optional parameters - LDAP
    'doLDAPAuthentication' => [
        'required' => false,
        'description' => 'Enable LDAP/AD authentication',
        'example' => 'true',
        'validate' => 'boolean',
        'default' => 'false',
        'group' => 'ldap'
    ],
    'ldapHost' => [
        'required' => false,
        'description' => 'LDAP server URL',
        'example' => 'ldaps://ad.yourcompany.com/',
        'validate' => 'url',
        'conditionalOn' => 'doLDAPAuthentication',
        'group' => 'ldap'
    ],
    'ldapDomainName' => [
        'required' => false,
        'description' => 'LDAP domain DN',
        'example' => 'DC=yourcompany,DC=com',
        'validate' => 'notEmpty',
        'conditionalOn' => 'doLDAPAuthentication',
        'group' => 'ldap'
    ],
    'ldapUserGroup' => [
        'required' => false,
        'description' => 'LDAP group for authorized users',
        'example' => 'DBAs',
        'validate' => 'notEmpty',
        'conditionalOn' => 'doLDAPAuthentication',
        'group' => 'ldap'
    ],
    'ldapUserDomain' => [
        'required' => false,
        'description' => 'LDAP user domain prefix',
        'example' => 'YOURCOMPANY',
        'validate' => 'notEmpty',
        'conditionalOn' => 'doLDAPAuthentication',
        'group' => 'ldap'
    ],
    'ldapVerifyCert' => [
        'required' => false,
        'description' => 'Verify LDAP SSL certificate',
        'example' => 'true',
        'validate' => 'boolean',
        'default' => 'true',
        'group' => 'ldap'
    ],
    'ldapDebugConnection' => [
        'required' => false,
        'description' => 'Show LDAP debug output',
        'example' => 'false',
        'validate' => 'boolean',
        'default' => 'false',
        'group' => 'ldap'
    ],

    // Optional parameters - Jira
    'jiraEnabled' => [
        'required' => false,
        'description' => 'Enable Jira integration',
        'example' => 'true',
        'validate' => 'boolean',
        'default' => 'false',
        'group' => 'jira'
    ],
    'jiraProjectId' => [
        'required' => false,
        'description' => 'Jira project ID (numeric)',
        'example' => '10010',
        'validate' => 'numeric',
        'conditionalOn' => 'jiraEnabled',
        'group' => 'jira'
    ],
    'jiraIssueTypeId' => [
        'required' => false,
        'description' => 'Jira issue type ID (numeric)',
        'example' => '10001',
        'validate' => 'numeric',
        'conditionalOn' => 'jiraEnabled',
        'group' => 'jira'
    ],
    'jiraQueryHashFieldId' => [
        'required' => false,
        'description' => 'Custom field ID for query hash',
        'example' => 'customfield_10100',
        'validate' => 'none',
        'group' => 'jira'
    ],

    // Optional parameters - Test Harness
    'testDbUser' => [
        'required' => false,
        'description' => 'Test database username',
        'example' => 'aql_test',
        'validate' => 'notEmpty',
        'group' => 'test'
    ],
    'testDbPass' => [
        'required' => false,
        'description' => 'Test database password',
        'example' => 'TestPassword',
        'validate' => 'notEmpty',
        'sensitive' => true,
        'group' => 'test'
    ],
    'testDbName' => [
        'required' => false,
        'description' => 'Test database name',
        'example' => 'aql_test',
        'validate' => 'notEmpty',
        'group' => 'test'
    ],

    // Optional parameters - Features
    'enableMaintenanceWindows' => [
        'required' => false,
        'description' => 'Enable maintenance window feature',
        'example' => 'true',
        'validate' => 'boolean',
        'default' => 'false'
    ],
    'dbaSessionTimeout' => [
        'required' => false,
        'description' => 'DBA session timeout (seconds)',
        'example' => '86400',
        'validate' => 'numeric',
        'default' => '86400'
    ],
    'enableSpeechAlerts' => [
        'required' => false,
        'description' => 'Announce alerts via speech synthesis',
        'example' => 'true',
        'validate' => 'boolean',
        'default' => 'true'
    ],

    // Optional parameters - Redis
    'redisEnabled' => [
        'required' => false,
        'description' => 'Enable Redis monitoring',
        'example' => 'true',
        'validate' => 'boolean',
        'default' => 'false',
        'group' => 'redis'
    ],
    'redisPassword' => [
        'required' => false,
        'description' => 'Shared password for all Redis hosts',
        'example' => 'YourRedisPassword',
        'validate' => 'none',
        'sensitive' => true,
        'conditionalOn' => 'redisEnabled',
        'group' => 'redis'
    ],
    'redisUsername' => [
        'required' => false,
        'description' => 'Redis username (Redis 6+ ACL)',
        'example' => 'default',
        'validate' => 'none',
        'conditionalOn' => 'redisEnabled',
        'group' => 'redis'
    ],
    'redisConnectTimeout' => [
        'required' => false,
        'description' => 'Redis connection timeout (seconds)',
        'example' => '2',
        'validate' => 'numeric',
        'default' => '2',
        'group' => 'redis'
    ],
    'redisDatabase' => [
        'required' => false,
        'description' => 'Redis database number (0-15)',
        'example' => '0',
        'validate' => 'numeric',
        'default' => '0',
        'group' => 'redis'
    ]
] ;

// Validation helper functions
function validateParam( $value, $type ) {
    if ( $type === 'none' ) return [ true, '' ] ;
    if ( $type === 'notEmpty' ) {
        return [ !empty( $value ), 'Value is required' ] ;
    }
    if ( $type === 'numeric' ) {
        return [ is_numeric( $value ), 'Must be a number' ] ;
    }
    if ( $type === 'url' ) {
        if ( empty( $value ) ) return [ false, 'URL is required' ] ;
        return [ filter_var( $value, FILTER_VALIDATE_URL ) !== false, 'Invalid URL format' ] ;
    }
    if ( $type === 'timezone' ) {
        try {
            new \DateTimeZone( $value ) ;
            return [ true, '' ] ;
        } catch ( \Exception $e ) {
            return [ false, 'Invalid timezone' ] ;
        }
    }
    if ( $type === 'boolean' ) {
        return [ in_array( strtolower( $value ), [ 'true', 'false', '1', '0', '' ] ), 'Must be true or false' ] ;
    }
    return [ true, '' ] ;
}

function isFeatureEnabled( $param, $configValues ) {
    $value = $configValues[$param] ?? 'false' ;
    return strtolower( $value ) === 'true' ;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AQL Configuration Verification</title>
    <style>
        :root {
            --bg-color: #1a1a1a;
            --text-color: #e0e0e0;
            --border-color: #444;
            --header-bg: #2a2a2a;
            --pass-color: #4caf50;
            --fail-color: #f44336;
            --warn-color: #ff9800;
            --info-color: #2196f3;
            --code-bg: #2d2d2d;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1, h2, h3, h4 {
            color: #fff;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        h1 { font-size: 1.8rem; }
        h2 { font-size: 1.4rem; margin-top: 30px; }
        h3 { font-size: 1.2rem; margin-top: 20px; border-bottom: none; }

        .status-pass { color: var(--pass-color); font-weight: bold; }
        .status-fail { color: var(--fail-color); font-weight: bold; }
        .status-warn { color: var(--warn-color); font-weight: bold; }
        .status-info { color: var(--info-color); }

        .summary-box {
            background: var(--header-bg);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--info-color);
        }
        .summary-box.error { border-left-color: var(--fail-color); }
        .summary-box.success { border-left-color: var(--pass-color); }
        .summary-box.warning { border-left-color: var(--warn-color); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9rem;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border: 1px solid var(--border-color);
        }
        th {
            background: var(--header-bg);
            font-weight: 600;
        }
        tr:hover {
            background: rgba(255,255,255,0.03);
        }

        code {
            background: var(--code-bg);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85em;
        }
        pre {
            background: var(--code-bg);
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
            border: 1px solid var(--border-color);
        }

        .param-group {
            margin-top: 25px;
            padding: 15px;
            background: rgba(255,255,255,0.02);
            border-radius: 8px;
        }
        .param-group h3 {
            margin-top: 0;
            color: var(--info-color);
        }

        .test-section {
            margin: 25px 0;
            padding: 20px;
            background: var(--header-bg);
            border-radius: 8px;
        }
        .test-section h3 {
            margin-top: 0;
        }

        .next-steps {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid var(--pass-color);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .next-steps h3 {
            color: var(--pass-color);
            margin-top: 0;
        }
        .next-steps ol {
            margin-bottom: 0;
        }
        .next-steps li {
            margin-bottom: 10px;
        }

        .fix-section {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid var(--fail-color);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .fix-section h3 {
            color: var(--fail-color);
            margin-top: 0;
        }

        a {
            color: var(--info-color);
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-required { background: var(--fail-color); color: white; }
        .badge-optional { background: #666; color: white; }
        .badge-conditional { background: var(--warn-color); color: black; }

        .version-info {
            color: #888;
            font-size: 0.85rem;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .code-block {
            position: relative;
        }
        .code-block pre {
            margin: 0;
        }
        .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--header-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .copy-btn:hover {
            opacity: 1;
            background: var(--border-color);
        }
        .copy-btn.copied {
            background: var(--pass-color);
            color: white;
            opacity: 1;
        }
    </style>
<script>
function copyToClipboard(btn) {
    const codeBlock = btn.parentElement.querySelector('pre');
    const text = codeBlock.textContent;

    navigator.clipboard.writeText(text).then(function() {
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = 'Copy';
            btn.classList.remove('copied');
        }, 2000);
    }).catch(function(err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = 'Copy';
            btn.classList.remove('copied');
        }, 2000);
    });
}
</script>
</head>
<body>
<div class="container">
    <h1>AQL Configuration Verification</h1>

<?php
// ============================================================================
// SECTION 0: PHP Requirements Check
// ============================================================================

// Check required PHP extensions
$requiredExtensions = [
    'mysqli' => [ 'required' => true, 'purpose' => 'MySQL/MariaDB database connectivity' ],
    'simplexml' => [ 'required' => true, 'purpose' => 'Parse aql_config.xml' ],
    'curl' => [ 'required' => true, 'purpose' => 'Jira integration, smoke tests' ],
    'json' => [ 'required' => true, 'purpose' => 'AJAX responses' ],
    'ldap' => [ 'required' => false, 'purpose' => 'LDAP/AD authentication (if enabled)' ],
    'openssl' => [ 'required' => false, 'purpose' => 'HTTPS and LDAPS connections' ],
    'redis' => [ 'required' => false, 'purpose' => 'Redis monitoring (if enabled)' ]
] ;

$missingRequired = [] ;
$missingOptional = [] ;

foreach ( $requiredExtensions as $ext => $info ) {
    if ( !extension_loaded( $ext ) ) {
        if ( $info['required'] ) {
            $missingRequired[] = $ext ;
        } else {
            $missingOptional[] = $ext ;
        }
    }
}

// Only show this section if there are issues
if ( !empty( $missingRequired ) || !empty( $missingOptional ) ) :
?>
    <h2>0. PHP Requirements</h2>

<?php if ( !empty( $missingRequired ) ) : ?>
    <?php $criticalErrors += count( $missingRequired ) ; ?>
    <div class="summary-box error">
        <p><?php echo $failIcon ; ?> <strong>Missing required PHP extensions:</strong>
        <code><?php echo htmlspecialchars( implode( ', ', $missingRequired ) ) ; ?></code></p>
    </div>

    <div class="fix-section">
        <h3>Install Missing Extensions</h3>
        <p>On Debian/Ubuntu:</p>
        <div class="code-block">
            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            <pre>apt-get install <?php
foreach ( $missingRequired as $ext ) {
    echo "php-$ext " ;
}
?></pre>
        </div>
        <p>On RHEL/CentOS/Fedora:</p>
        <div class="code-block">
            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            <pre>dnf install <?php
foreach ( $missingRequired as $ext ) {
    echo "php-$ext " ;
}
?></pre>
        </div>
        <p>Then restart your web server:</p>
        <div class="code-block">
            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            <pre># Apache
systemctl restart apache2    # Debian/Ubuntu
systemctl restart httpd      # RHEL/CentOS/Fedora

# nginx + php-fpm
systemctl restart php-fpm
systemctl restart nginx</pre>
        </div>
    </div>
<?php endif ; ?>

<?php if ( !empty( $missingOptional ) ) : ?>
    <div class="summary-box warning">
        <p><?php echo $warnIcon ; ?> <strong>Missing optional PHP extensions:</strong>
        <code><?php echo htmlspecialchars( implode( ', ', $missingOptional ) ) ; ?></code></p>
        <ul>
<?php foreach ( $missingOptional as $ext ) : ?>
            <li><code><?php echo $ext ; ?></code> - <?php echo htmlspecialchars( $requiredExtensions[$ext]['purpose'] ) ; ?></li>
<?php endforeach ; ?>
        </ul>
    </div>
<?php endif ; ?>

<?php else : ?>
    <!-- All PHP requirements met, show brief confirmation -->
<?php endif ; ?>

<?php
// ============================================================================
// SECTION 1: Configuration File Check
// ============================================================================
?>
    <h2>1. Configuration File</h2>

<?php if ( !file_exists( $configFile ) ) : ?>
    <?php $criticalErrors++ ; ?>
    <div class="summary-box error">
        <p><?php echo $failIcon ; ?> <strong>Configuration file not found!</strong></p>
        <p>Expected location: <code><?php echo htmlspecialchars( $configFile ) ; ?></code></p>
    </div>

    <div class="fix-section">
        <h3>How to Fix</h3>
        <p>Copy the sample configuration file and edit it:</p>
        <div class="code-block">
            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            <pre>cp <?php echo htmlspecialchars( basename( $sampleFile ) ) ; ?> <?php echo htmlspecialchars( basename( $configFile ) ) ; ?>
# Then edit <?php echo htmlspecialchars( basename( $configFile ) ) ; ?> with your settings</pre>
        </div>
    </div>

<?php elseif ( !is_readable( $configFile ) ) : ?>
    <?php $criticalErrors++ ; ?>
    <div class="summary-box error">
        <p><?php echo $failIcon ; ?> <strong>Configuration file is not readable!</strong></p>
        <p>File exists but PHP cannot read it: <code><?php echo htmlspecialchars( $configFile ) ; ?></code></p>
    </div>

    <div class="fix-section">
        <h3>How to Fix</h3>
        <p>Check file permissions:</p>
        <div class="code-block">
            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            <pre>chmod 640 <?php echo htmlspecialchars( basename( $configFile ) ) ; ?>
chown www-data:www-data <?php echo htmlspecialchars( basename( $configFile ) ) ; ?></pre>
        </div>
    </div>

<?php elseif ( !$configLoaded ) : ?>
    <?php $criticalErrors++ ; ?>
    <div class="summary-box error">
        <p><?php echo $failIcon ; ?> <strong>Configuration file has invalid XML!</strong></p>
    </div>

    <div class="fix-section">
        <h3>How to Fix</h3>
        <p>Check for XML syntax errors. Common issues:</p>
        <ul>
            <li>Unescaped special characters in values (use <code>&amp;amp;</code> for &amp;, <code>&amp;lt;</code> for &lt;)</li>
            <li>Missing closing tags</li>
            <li>Invalid characters</li>
        </ul>
        <p>Validate your XML:</p>
        <div class="code-block">
            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
            <pre>xmllint --noout <?php echo htmlspecialchars( basename( $configFile ) ) ; ?></pre>
        </div>
    </div>

<?php else : ?>
    <div class="summary-box success">
        <p><?php echo $passIcon ; ?> Configuration file loaded successfully</p>
        <p>Location: <code><?php echo htmlspecialchars( $configFile ) ; ?></code></p>
    </div>
<?php endif ; ?>

<?php if ( $configLoaded ) : ?>

<?php
// ============================================================================
// SECTION 2: Required Parameters
// ============================================================================
?>
    <h2>2. Required Parameters</h2>
    <p>These parameters must be configured for AQL to function.</p>

    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Current Value</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
<?php
$missingRequired = [] ;
foreach ( $allParams as $name => $info ) :
    if ( !$info['required'] ) continue ;

    $value = $configValues[$name] ?? '' ;
    $displayValue = ( $info['sensitive'] ?? false ) ? ( empty( $value ) ? '(not set)' : '********' ) : $value ;
    if ( empty( $displayValue ) ) $displayValue = '(not set)' ;

    list( $valid, $validMsg ) = validateParam( $value, $info['validate'] ) ;

    if ( empty( $value ) || !$valid ) {
        $criticalErrors++ ;
        $missingRequired[$name] = $info ;
        $statusIcon = $failIcon ;
        $statusText = empty( $value ) ? 'Missing' : $validMsg ;
    } else {
        $statusIcon = $passIcon ;
        $statusText = 'OK' ;
    }
?>
            <tr>
                <td><code><?php echo htmlspecialchars( $name ) ; ?></code></td>
                <td><code><?php echo htmlspecialchars( $displayValue ) ; ?></code></td>
                <td><?php echo $statusIcon . ' ' . htmlspecialchars( $statusText ) ; ?></td>
                <td><?php echo htmlspecialchars( $info['description'] ) ; ?></td>
            </tr>
<?php endforeach ; ?>
        </tbody>
    </table>

<?php if ( !empty( $missingRequired ) ) : ?>
    <div class="fix-section">
        <h3>Missing Required Parameters</h3>
        <p>Add these to your <code>aql_config.xml</code>:</p>
        <pre><?php
foreach ( $missingRequired as $name => $info ) {
    echo '&lt;param name="' . htmlspecialchars( $name ) . '"&gt;' . htmlspecialchars( $info['example'] ) . '&lt;/param&gt;' . "\n" ;
}
?></pre>
    </div>
<?php endif ; ?>

<?php
// ============================================================================
// SECTION 3: Optional Parameters
// ============================================================================
?>
    <h2>3. Optional Parameters</h2>
    <p>These parameters have sensible defaults but can be customized.</p>

    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Current Value</th>
                <th>Default</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
<?php
foreach ( $allParams as $name => $info ) :
    if ( $info['required'] ) continue ;
    if ( isset( $info['group'] ) ) continue ; // Skip grouped params, shown below

    $value = $configValues[$name] ?? '' ;
    $default = $info['default'] ?? '' ;
    $displayValue = ( $info['sensitive'] ?? false ) ? ( empty( $value ) ? '(not set)' : '********' ) : $value ;
    if ( empty( $displayValue ) ) $displayValue = '(using default)' ;

    $statusIcon = !empty( $value ) ? $passIcon : $infoIcon ;
?>
            <tr>
                <td><code><?php echo htmlspecialchars( $name ) ; ?></code></td>
                <td><code><?php echo htmlspecialchars( $displayValue ) ; ?></code></td>
                <td><code><?php echo htmlspecialchars( $default ) ; ?></code></td>
                <td><?php echo htmlspecialchars( $info['description'] ) ; ?></td>
            </tr>
<?php endforeach ; ?>
        </tbody>
    </table>

<?php
// ============================================================================
// SECTION 4: Feature Groups (LDAP, Jira, Test Harness)
// ============================================================================
?>
    <h2>4. Feature Configuration</h2>

<?php
// LDAP Configuration
$ldapEnabled = isFeatureEnabled( 'doLDAPAuthentication', $configValues ) ;
?>
    <div class="param-group">
        <h3>LDAP/Active Directory Authentication</h3>
        <p>Status: <?php echo $ldapEnabled ? "$passIcon <strong>Enabled</strong>" : "$infoIcon Disabled" ; ?></p>

<?php if ( $ldapEnabled ) : ?>
        <table>
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
<?php
    foreach ( $allParams as $name => $info ) :
        if ( ( $info['group'] ?? '' ) !== 'ldap' ) continue ;
        if ( $name === 'doLDAPAuthentication' ) continue ;

        $value = $configValues[$name] ?? '' ;
        $isConditional = isset( $info['conditionalOn'] ) ;

        if ( $isConditional && empty( $value ) ) {
            $warnings++ ;
            $statusIcon = $warnIcon ;
            $statusText = 'Required when LDAP enabled' ;
        } elseif ( !empty( $value ) ) {
            $statusIcon = $passIcon ;
            $statusText = 'OK' ;
        } else {
            $statusIcon = $infoIcon ;
            $statusText = 'Using default' ;
        }
?>
                <tr>
                    <td><code><?php echo htmlspecialchars( $name ) ; ?></code></td>
                    <td><code><?php echo htmlspecialchars( $value ?: '(not set)' ) ; ?></code></td>
                    <td><?php echo $statusIcon . ' ' . $statusText ; ?></td>
                </tr>
<?php endforeach ; ?>
            </tbody>
        </table>
<?php else : ?>
        <p><em>Set <code>doLDAPAuthentication</code> to <code>true</code> to enable LDAP authentication.</em></p>
<?php endif ; ?>
    </div>

<?php
// Jira Configuration
$jiraEnabled = isFeatureEnabled( 'jiraEnabled', $configValues ) ;
?>
    <div class="param-group">
        <h3>Jira Integration</h3>
        <p>Status: <?php echo $jiraEnabled ? "$passIcon <strong>Enabled</strong>" : "$infoIcon Disabled" ; ?></p>

<?php if ( $jiraEnabled ) : ?>
        <table>
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
<?php
    foreach ( $allParams as $name => $info ) :
        if ( ( $info['group'] ?? '' ) !== 'jira' ) continue ;
        if ( $name === 'jiraEnabled' ) continue ;

        $value = $configValues[$name] ?? '' ;
        $isConditional = isset( $info['conditionalOn'] ) ;

        if ( $isConditional && empty( $value ) ) {
            $warnings++ ;
            $statusIcon = $warnIcon ;
            $statusText = 'Required when Jira enabled' ;
        } elseif ( !empty( $value ) ) {
            $statusIcon = $passIcon ;
            $statusText = 'OK' ;
        } else {
            $statusIcon = $infoIcon ;
            $statusText = 'Optional' ;
        }
?>
                <tr>
                    <td><code><?php echo htmlspecialchars( $name ) ; ?></code></td>
                    <td><code><?php echo htmlspecialchars( $value ?: '(not set)' ) ; ?></code></td>
                    <td><?php echo $statusIcon . ' ' . $statusText ; ?></td>
                </tr>
<?php endforeach ; ?>
            </tbody>
        </table>
<?php else : ?>
        <p><em>Set <code>jiraEnabled</code> to <code>true</code> to enable the "File Issue" button.</em></p>
<?php endif ; ?>
    </div>

<?php
// Test Harness Configuration
$testConfigured = !empty( $configValues['testDbUser'] ?? '' ) && !empty( $configValues['testDbPass'] ?? '' ) ;
?>
    <div class="param-group">
        <h3>Test Harness</h3>
        <p>Status: <?php echo $testConfigured ? "$passIcon <strong>Configured</strong>" : "$infoIcon Not configured" ; ?></p>

<?php if ( $testConfigured ) : ?>
        <table>
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
<?php
    foreach ( $allParams as $name => $info ) :
        if ( ( $info['group'] ?? '' ) !== 'test' ) continue ;

        $value = $configValues[$name] ?? '' ;
        $displayValue = ( $info['sensitive'] ?? false ) ? ( empty( $value ) ? '(not set)' : '********' ) : $value ;
        $statusIcon = !empty( $value ) ? $passIcon : $warnIcon ;
?>
                <tr>
                    <td><code><?php echo htmlspecialchars( $name ) ; ?></code></td>
                    <td><code><?php echo htmlspecialchars( $displayValue ?: '(not set)' ) ; ?></code></td>
                    <td><?php echo $statusIcon ; ?></td>
                </tr>
<?php endforeach ; ?>
            </tbody>
        </table>
<?php else : ?>
        <p><em>Configure <code>testDbUser</code>, <code>testDbPass</code>, and <code>testDbName</code> to use the <a href="testAQL.php">Test Harness</a>.</em></p>
<?php endif ; ?>
    </div>

<?php
// Redis Configuration
$redisEnabled = isFeatureEnabled( 'redisEnabled', $configValues ) ;
?>
    <div class="param-group">
        <h3>Redis Monitoring</h3>
        <p>Status: <?php echo $redisEnabled ? "$passIcon <strong>Enabled</strong>" : "$infoIcon Disabled" ; ?></p>

<?php if ( $redisEnabled ) : ?>
<?php if ( !extension_loaded( 'redis' ) ) : ?>
        <div class="summary-box error">
            <p><?php echo $failIcon ; ?> <strong>PHP Redis extension not installed</strong></p>
            <p>Redis monitoring is enabled but the phpredis extension is not available.</p>
        </div>
        <div class="fix-section">
            <h3>Install phpredis</h3>
            <p>On Debian/Ubuntu:</p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre>apt-get install php-redis
systemctl restart apache2</pre>
            </div>
            <p>On RHEL/CentOS/Fedora:</p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre>dnf install php-redis
systemctl restart httpd</pre>
            </div>
        </div>
<?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
<?php
    foreach ( $allParams as $name => $info ) :
        if ( ( $info['group'] ?? '' ) !== 'redis' ) continue ;
        if ( $name === 'redisEnabled' ) continue ;

        $value = $configValues[$name] ?? '' ;
        $displayValue = ( $info['sensitive'] ?? false ) ? ( empty( $value ) ? '(not set)' : '********' ) : $value ;
        $isConditional = isset( $info['conditionalOn'] ) ;

        if ( !empty( $value ) ) {
            $statusIcon = $passIcon ;
            $statusText = 'OK' ;
        } elseif ( isset( $info['default'] ) ) {
            $statusIcon = $infoIcon ;
            $statusText = 'Using default' ;
        } else {
            $statusIcon = $infoIcon ;
            $statusText = 'Optional' ;
        }
?>
                <tr>
                    <td><code><?php echo htmlspecialchars( $name ) ; ?></code></td>
                    <td><code><?php echo htmlspecialchars( $displayValue ?: '(not set)' ) ; ?></code></td>
                    <td><?php echo $statusIcon . ' ' . $statusText ; ?></td>
                </tr>
<?php endforeach ; ?>
            </tbody>
        </table>
<?php endif ; ?>
<?php else : ?>
        <p><em>Set <code>redisEnabled</code> to <code>true</code> to enable Redis monitoring.</em></p>
<?php endif ; ?>
    </div>

<?php
// ============================================================================
// SECTION 5: Connectivity Tests
// ============================================================================
?>
    <h2>5. Connectivity Tests</h2>

<?php
// Database connectivity test
$dbHost = $configValues['dbHost'] ?? '' ;
$dbPort = $configValues['dbPort'] ?? '3306' ;
$dbUser = $configValues['dbUser'] ?? '' ;
$dbPass = $configValues['dbPass'] ?? '' ;
$dbName = $configValues['dbName'] ?? '' ;

if ( !empty( $dbHost ) && !empty( $dbUser ) && !empty( $dbPass ) && !empty( $dbName ) ) :
?>
    <div class="test-section">
        <h3>Database Connection</h3>
<?php
    $dbConnected = false ;
    $dbError = '' ;

    // Track privileges found
    $privileges = [
        'PROCESS' => false,
        'REPLICATION CLIENT' => false,
        'SUPER' => false,
        'ALL PRIVILEGES' => false,
        'perf_schema_select' => false
    ] ;
    $grantStatements = [] ;
    $actualUserHost = '' ;  // Will store 'user'@'host' from GRANT output

    try {
        $mysqli = @new \mysqli( $dbHost, $dbUser, $dbPass, $dbName, $dbPort ) ;
        if ( $mysqli->connect_error ) {
            throw new \Exception( $mysqli->connect_error ) ;
        }
        $dbConnected = true ;

        // Get the actual user@host we connected as
        $result = @$mysqli->query( "SELECT CURRENT_USER()" ) ;
        if ( $result && $row = $result->fetch_row() ) {
            $actualUserHost = $row[0] ;  // Returns 'user@host' format
            $result->free() ;
        }

        // Get actual grants using SHOW GRANTS
        $result = @$mysqli->query( "SHOW GRANTS" ) ;
        if ( $result ) {
            while ( $row = $result->fetch_row() ) {
                $grant = $row[0] ;
                $grantStatements[] = $grant ;

                // Check for global privileges
                if ( preg_match( '/GRANT\s+(.+?)\s+ON\s+\*\.\*/i', $grant, $matches ) ) {
                    $privList = strtoupper( $matches[1] ) ;
                    if ( strpos( $privList, 'ALL PRIVILEGES' ) !== false ) {
                        $privileges['ALL PRIVILEGES'] = true ;
                        $privileges['PROCESS'] = true ;
                        $privileges['REPLICATION CLIENT'] = true ;
                        $privileges['SUPER'] = true ;
                    }
                    if ( strpos( $privList, 'PROCESS' ) !== false ) {
                        $privileges['PROCESS'] = true ;
                    }
                    if ( strpos( $privList, 'REPLICATION CLIENT' ) !== false ) {
                        $privileges['REPLICATION CLIENT'] = true ;
                    }
                    if ( strpos( $privList, 'SUPER' ) !== false ) {
                        $privileges['SUPER'] = true ;
                    }
                }

                // Check for performance_schema access
                if ( preg_match( '/GRANT\s+(.+?)\s+ON\s+[`"]?performance_schema[`"]?\.\*/i', $grant, $matches ) ) {
                    $privList = strtoupper( $matches[1] ) ;
                    if ( strpos( $privList, 'SELECT' ) !== false || strpos( $privList, 'ALL' ) !== false ) {
                        $privileges['perf_schema_select'] = true ;
                    }
                }
            }
            $result->free() ;
        }

        $mysqli->close() ;
    } catch ( \Exception $e ) {
        $dbError = $e->getMessage() ;
    }

    // Fallback if we couldn't get user@host
    if ( empty( $actualUserHost ) ) {
        $actualUserHost = $dbUser . '@localhost' ;
    }

    // Parse user and host for warnings
    $userParts = explode( '@', $actualUserHost ) ;
    $actualUser = $userParts[0] ?? $dbUser ;
    $actualHostMask = $userParts[1] ?? 'localhost' ;
    $isRootUser = ( strtolower( $actualUser ) === 'root' ) ;

    if ( $dbConnected ) :
        // Determine status for each privilege
        $processOk = $privileges['PROCESS'] || $privileges['ALL PRIVILEGES'] ;
        $replOk = $privileges['REPLICATION CLIENT'] || $privileges['ALL PRIVILEGES'] ;
        $perfSchemaOk = $privileges['perf_schema_select'] || $privileges['ALL PRIVILEGES'] ;
        $hasSuper = $privileges['SUPER'] || $privileges['ALL PRIVILEGES'] ;
?>
        <p><?php echo $passIcon ; ?> Connected to <code><?php echo htmlspecialchars( "$dbHost:$dbPort" ) ; ?></code></p>
        <p><?php echo $passIcon ; ?> Database <code><?php echo htmlspecialchars( $dbName ) ; ?></code> accessible</p>
        <p><?php echo $passIcon ; ?> Connected as <code><?php echo htmlspecialchars( $actualUserHost ) ; ?></code></p>

<?php if ( $isRootUser ) : ?>
        <div class="summary-box error">
            <p><?php echo $failIcon ; ?> <strong>Security Warning: Using 'root' user</strong></p>
            <p>Do not use the MySQL root account for applications. Create a dedicated user with only the privileges AQL needs.</p>
        </div>
<?php endif ; ?>

<?php if ( $hasSuper && !$isRootUser ) : ?>
        <div class="summary-box warning">
            <p><?php echo $warnIcon ; ?> <strong>Security Note: SUPER privilege detected</strong></p>
            <p>The user <code><?php echo htmlspecialchars( $actualUser ) ; ?></code> has SUPER privilege, which is more than AQL needs. Consider creating a dedicated user with minimal privileges.</p>
        </div>
<?php endif ; ?>

<?php if ( $actualHostMask === '%' ) : ?>
        <div class="summary-box warning">
            <p><?php echo $warnIcon ; ?> <strong>Security Warning: User allows connections from any host</strong></p>
            <p>The user <code><?php echo htmlspecialchars( $actualUserHost ) ; ?></code> can connect from anywhere (<code>%</code>). Consider restricting to specific hosts:</p>
            <ul>
                <li><code>'<?php echo htmlspecialchars( $actualUser ) ; ?>'@'localhost'</code> - local connections only</li>
                <li><code>'<?php echo htmlspecialchars( $actualUser ) ; ?>'@'192.168.1.%'</code> - specific subnet</li>
                <li><code>'<?php echo htmlspecialchars( $actualUser ) ; ?>'@'aql-server.example.com'</code> - specific host</li>
            </ul>
        </div>
<?php endif ; ?>

        <h4>Required Privileges</h4>
        <table>
            <thead>
                <tr><th>Privilege</th><th>Status</th><th>Purpose</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>PROCESS</code></td>
                    <td><?php echo $processOk ? "$passIcon Granted" : "$failIcon Missing" ; ?></td>
                    <td>See all users' queries (not just your own)</td>
                </tr>
                <tr>
                    <td><code>REPLICATION CLIENT</code></td>
                    <td><?php echo $replOk ? "$passIcon Granted" : "$warnIcon Missing" ; ?></td>
                    <td>Check replica status (SHOW SLAVE STATUS)</td>
                </tr>
                <tr>
                    <td><code>SELECT on performance_schema</code></td>
                    <td><?php echo $perfSchemaOk ? "$passIcon Granted" : "$warnIcon Missing" ; ?></td>
                    <td>Detect blocking/waiting queries</td>
                </tr>
            </tbody>
        </table>

<?php
    // Format user@host for SQL statements: 'user'@'host'
    $sqlUserHost = "'" . htmlspecialchars( $actualUser ) . "'@'" . htmlspecialchars( $actualHostMask ) . "'" ;
?>
<?php if ( !$processOk ) : ?>
<?php $criticalErrors++ ; ?>
        <div class="fix-section">
            <h3>Missing PROCESS Privilege</h3>
            <p>Without PROCESS privilege, AQL can only see its own queries - not queries from other users. This is critical for monitoring.</p>
            <p><em>Run on each monitored database server:</em></p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre>-- Create user if needed (use your password)
CREATE USER IF NOT EXISTS <?php echo $sqlUserHost ; ?> IDENTIFIED BY 'your_password_here' ;

-- Grant required privilege
GRANT PROCESS ON *.* TO <?php echo $sqlUserHost ; ?> ;
FLUSH PRIVILEGES ;</pre>
            </div>
        </div>
<?php endif ; ?>

<?php if ( !$replOk || !$perfSchemaOk ) : ?>
        <div class="fix-section">
            <h3>Recommended Additional Privileges</h3>
            <p><em>Run on each monitored database server:</em></p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre><?php if ( !$replOk ) : ?>-- For replica status monitoring
GRANT REPLICATION CLIENT ON *.* TO <?php echo $sqlUserHost ; ?> ;
<?php endif ; ?>
<?php if ( !$perfSchemaOk ) : ?>-- For lock/blocking detection
GRANT SELECT ON performance_schema.* TO <?php echo $sqlUserHost ; ?> ;
<?php endif ; ?>
FLUSH PRIVILEGES ;</pre>
            </div>
        </div>
<?php endif ; ?>

        <details>
            <summary>View raw GRANT statements</summary>
            <pre><?php echo htmlspecialchars( implode( "\n", $grantStatements ) ) ; ?></pre>
        </details>
<?php else : ?>
        <?php $criticalErrors++ ; ?>
        <p><?php echo $failIcon ; ?> <strong>Connection failed:</strong> <?php echo htmlspecialchars( $dbError ) ; ?></p>

        <div class="fix-section">
            <h3>Troubleshooting</h3>
            <ul>
                <li>Verify MySQL/MariaDB is running on <?php echo htmlspecialchars( "$dbHost:$dbPort" ) ; ?></li>
                <li>Check that user <code><?php echo htmlspecialchars( $dbUser ) ; ?></code> can connect from this server</li>
                <li>Verify the password is correct</li>
                <li>Ensure database <code><?php echo htmlspecialchars( $dbName ) ; ?></code> exists</li>
            </ul>
            <p>Test from command line:</p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre>mysql -h <?php echo htmlspecialchars( $dbHost ) ; ?> -P <?php echo htmlspecialchars( $dbPort ) ; ?> -u <?php echo htmlspecialchars( $dbUser ) ; ?> -p <?php echo htmlspecialchars( $dbName ) ; ?></pre>
            </div>
        </div>
<?php endif ; ?>
    </div>
<?php else : ?>
    <div class="test-section">
        <h3>Database Connection</h3>
        <p><?php echo $warnIcon ; ?> Cannot test - missing required database parameters</p>
    </div>
<?php endif ; ?>

<?php
// Schema verification - check if AQL tables exist
if ( $dbConnected ) :
?>
    <div class="test-section">
        <h3>AQL Database Schema</h3>
<?php
    $expectedTables = [ 'host', 'host_group', 'host_group_map', 'maintenance_window', 'maintenance_window_host_map', 'maintenance_window_host_group_map' ] ;
    $existingTables = [] ;
    $missingTables = [] ;

    try {
        $schemaDbh = @new \mysqli( $dbHost, $dbUser, $dbPass, $dbName, $dbPort ) ;
        if ( !$schemaDbh->connect_error ) {
            foreach ( $expectedTables as $table ) {
                $result = $schemaDbh->query( "SHOW TABLES LIKE '$table'" ) ;
                if ( $result && $result->num_rows > 0 ) {
                    $existingTables[] = $table ;
                } else {
                    $missingTables[] = $table ;
                }
            }

            // Check if host table has any data
            $hostCount = 0 ;
            if ( in_array( 'host', $existingTables ) ) {
                $result = $schemaDbh->query( "SELECT COUNT(*) as cnt FROM host WHERE decommissioned = 0" ) ;
                if ( $result && $row = $result->fetch_assoc() ) {
                    $hostCount = (int) $row['cnt'] ;
                }
            }

            $schemaDbh->close() ;
        }
    } catch ( \Exception $e ) {
        // Ignore - schema check is informational
    }

    if ( empty( $missingTables ) ) :
?>
        <p><?php echo $passIcon ; ?> All required tables exist</p>
        <table>
            <thead><tr><th>Table</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ( $expectedTables as $table ) : ?>
                <tr><td><code><?php echo $table ; ?></code></td><td><?php echo $passIcon ; ?> Exists</td></tr>
<?php endforeach ; ?>
            </tbody>
        </table>

<?php if ( $hostCount === 0 ) : ?>
        <div class="summary-box warning">
            <p><?php echo $warnIcon ; ?> <strong>No monitored hosts configured</strong></p>
            <p>AQL schema is ready, but you haven't added any database hosts to monitor yet.</p>
            <p>Go to <a href="manageData.php">Manage Data</a> to add hosts.</p>
        </div>
<?php else : ?>
        <p><?php echo $passIcon ; ?> <strong><?php echo $hostCount ; ?></strong> monitored host(s) configured</p>
<?php endif ; ?>

<?php else : ?>
        <p><?php echo $warnIcon ; ?> Some tables are missing - run <a href="deployDDL.php">deployDDL.php</a> to create them</p>
        <table>
            <thead><tr><th>Table</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ( $expectedTables as $table ) : ?>
                <tr>
                    <td><code><?php echo $table ; ?></code></td>
                    <td><?php echo in_array( $table, $existingTables ) ? "$passIcon Exists" : "$warnIcon Missing" ; ?></td>
                </tr>
<?php endforeach ; ?>
            </tbody>
        </table>

        <div class="fix-section">
            <h3>Create Missing Tables</h3>
            <p>Run the deployment script:</p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre># Via browser
Open: <?php echo htmlspecialchars( dirname( $configValues['baseUrl'] ?? 'https://yourserver/aql' ) ) ; ?>/deployDDL.php

# Or via command line
php <?php echo htmlspecialchars( __DIR__ ) ; ?>/deployDDL.php</pre>
            </div>
        </div>
<?php endif ; ?>
    </div>
<?php endif ; ?>

<?php
// LDAP connectivity test
if ( $ldapEnabled ) :
    $ldapHost = $configValues['ldapHost'] ?? '' ;
?>
    <div class="test-section">
        <h3>LDAP Connection</h3>
<?php
    if ( !empty( $ldapHost ) ) :
        $ldapConnected = false ;
        $ldapError = '' ;

        if ( !function_exists( 'ldap_connect' ) ) {
            $ldapError = 'PHP LDAP extension not installed' ;
        } else {
            // Parse LDAP URL
            $ldapUrl = $ldapHost ;
            $ldapConn = @ldap_connect( $ldapUrl ) ;

            if ( $ldapConn ) {
                ldap_set_option( $ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3 ) ;
                ldap_set_option( $ldapConn, LDAP_OPT_REFERRALS, 0 ) ;

                // Check certificate verification setting
                $verifyCert = ( $configValues['ldapVerifyCert'] ?? 'true' ) !== 'false' ;
                if ( !$verifyCert ) {
                    ldap_set_option( null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER ) ;
                }

                // Just test if we can reach the server (anonymous bind attempt)
                $bindResult = @ldap_bind( $ldapConn ) ;
                // Note: Anonymous bind may fail but connection is still valid

                // Check if we got a connection error vs auth error
                $errno = ldap_errno( $ldapConn ) ;
                if ( $errno === 0 || $errno === 1 || $errno === 49 ) {
                    // 0 = success, 1 = operations error (server reachable), 49 = invalid credentials (server reachable)
                    $ldapConnected = true ;
                } else {
                    $ldapError = ldap_error( $ldapConn ) . " (error $errno)" ;
                }

                ldap_close( $ldapConn ) ;
            } else {
                $ldapError = 'Could not connect to LDAP server' ;
            }
        }

        if ( $ldapConnected ) :
?>
        <p><?php echo $passIcon ; ?> LDAP server reachable: <code><?php echo htmlspecialchars( $ldapHost ) ; ?></code></p>
<?php
            if ( ( $configValues['ldapVerifyCert'] ?? 'true' ) === 'false' ) :
?>
        <p><?php echo $warnIcon ; ?> SSL certificate verification disabled (ldapVerifyCert=false)</p>
<?php
            endif ;
        else :
            $warnings++ ;
?>
        <p><?php echo $warnIcon ; ?> LDAP connection issue: <?php echo htmlspecialchars( $ldapError ) ; ?></p>
        <p><em>Note: Full LDAP authentication testing requires valid credentials.</em></p>
<?php
        endif ;
    else :
?>
        <p><?php echo $warnIcon ; ?> LDAP enabled but <code>ldapHost</code> not configured</p>
<?php
    endif ;
?>
    </div>
<?php endif ; ?>

<?php
// Jira connectivity test
if ( $jiraEnabled ) :
    $jiraBaseUrl = $configValues['issueTrackerBaseUrl'] ?? '' ;
?>
    <div class="test-section">
        <h3>Jira Connectivity</h3>
<?php
    if ( !empty( $jiraBaseUrl ) ) :
        $jiraReachable = false ;
        $jiraError = '' ;

        // Simple HTTP check to see if Jira is reachable
        $ch = curl_init( rtrim( $jiraBaseUrl, '/' ) . '/rest/api/2/serverInfo' ) ;
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ) ;
        curl_setopt( $ch, CURLOPT_TIMEOUT, 10 ) ;
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true ) ;
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ) ;
        $response = curl_exec( $ch ) ;
        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE ) ;
        $curlError = curl_error( $ch ) ;
        curl_close( $ch ) ;

        if ( $curlError ) {
            $jiraError = $curlError ;
        } elseif ( $httpCode >= 200 && $httpCode < 400 ) {
            $jiraReachable = true ;
        } else {
            $jiraError = "HTTP $httpCode" ;
        }

        if ( $jiraReachable ) :
?>
        <p><?php echo $passIcon ; ?> Jira server reachable: <code><?php echo htmlspecialchars( $jiraBaseUrl ) ; ?></code></p>
<?php
        else :
            $warnings++ ;
?>
        <p><?php echo $warnIcon ; ?> Jira connectivity issue: <?php echo htmlspecialchars( $jiraError ) ; ?></p>
        <p><em>The "File Issue" feature may not work correctly.</em></p>
<?php
        endif ;
    else :
?>
        <p><?php echo $warnIcon ; ?> Jira enabled but <code>issueTrackerBaseUrl</code> not configured</p>
<?php
    endif ;
?>
    </div>
<?php endif ; ?>

<?php
// Redis connectivity test
if ( $redisEnabled && extension_loaded( 'redis' ) ) :
?>
    <div class="test-section">
        <h3>Redis Connectivity</h3>
<?php
    // Get Redis hosts from database
    $redisHosts = [] ;
    try {
        $redisDbh = @new \mysqli( $dbHost, $dbUser, $dbPass, $dbName, $dbPort ) ;
        if ( !$redisDbh->connect_error ) {
            $result = $redisDbh->query( "SELECT hostname, port FROM host WHERE db_type = 'Redis' AND decommissioned = 0 ORDER BY hostname, port" ) ;
            if ( $result ) {
                while ( $row = $result->fetch_assoc() ) {
                    $redisHosts[] = [ 'host' => $row['hostname'], 'port' => (int) $row['port'] ] ;
                }
                $result->free() ;
            }
            $redisDbh->close() ;
        }
    } catch ( \Exception $e ) {
        // Ignore - Redis host lookup is informational
    }

    if ( empty( $redisHosts ) ) :
?>
        <p><?php echo $infoIcon ; ?> No Redis hosts configured in the host table</p>
        <p><em>Add Redis hosts via <a href="manageData.php?data=Hosts">Manage Hosts</a> with db_type = 'Redis'.</em></p>
<?php
    else :
        $redisPassword = $configValues['redisPassword'] ?? '' ;
        $redisUsername = $configValues['redisUsername'] ?? '' ;
        $redisTimeout = (int) ( $configValues['redisConnectTimeout'] ?? 2 ) ;
        $testedCount = 0 ;
        $passedCount = 0 ;
?>
        <p><?php echo $infoIcon ; ?> Found <?php echo count( $redisHosts ) ; ?> Redis host(s) configured</p>
        <table>
            <thead>
                <tr><th>Host</th><th>Status</th><th>Details</th></tr>
            </thead>
            <tbody>
<?php
        foreach ( $redisHosts as $redisHost ) :
            $testedCount++ ;
            $redisError = '' ;
            $redisVersion = '' ;
            $redisConnected = false ;

            try {
                $redis = new \Redis() ;
                $connectResult = @$redis->connect( $redisHost['host'], $redisHost['port'], $redisTimeout ) ;

                if ( $connectResult ) {
                    // Authenticate if password configured
                    if ( !empty( $redisPassword ) ) {
                        if ( !empty( $redisUsername ) ) {
                            // Redis 6+ ACL auth
                            $authResult = @$redis->auth( [ $redisUsername, $redisPassword ] ) ;
                        } else {
                            // Legacy auth
                            $authResult = @$redis->auth( $redisPassword ) ;
                        }
                        if ( !$authResult ) {
                            throw new \Exception( 'Authentication failed' ) ;
                        }
                    }

                    // Try to get server info
                    $info = @$redis->info( 'server' ) ;
                    if ( $info && isset( $info['redis_version'] ) ) {
                        $redisVersion = 'Redis ' . $info['redis_version'] ;
                        $redisConnected = true ;
                        $passedCount++ ;
                    } else {
                        throw new \Exception( 'Could not retrieve server info' ) ;
                    }

                    $redis->close() ;
                } else {
                    throw new \Exception( 'Connection refused' ) ;
                }
            } catch ( \Exception $e ) {
                $redisError = $e->getMessage() ;
            }
?>
                <tr>
                    <td><code><?php echo htmlspecialchars( $redisHost['host'] . ':' . $redisHost['port'] ) ; ?></code></td>
                    <td><?php echo $redisConnected ? "$passIcon OK" : "$warnIcon Failed" ; ?></td>
                    <td><?php echo htmlspecialchars( $redisConnected ? $redisVersion : $redisError ) ; ?></td>
                </tr>
<?php
        endforeach ;
?>
            </tbody>
        </table>
        <p><strong>Summary:</strong> <?php echo $passedCount ; ?>/<?php echo $testedCount ; ?> Redis hosts reachable</p>
<?php
        if ( $passedCount < $testedCount ) :
            $warnings += ( $testedCount - $passedCount ) ;
?>
        <div class="fix-section">
            <h3>Troubleshooting Redis Connections</h3>
            <ul>
                <li>Verify Redis is running on the target host(s)</li>
                <li>Check firewall rules allow connections on the Redis port</li>
                <li>If authentication is required, ensure <code>redisPassword</code> is configured in aql_config.xml</li>
                <li>For Redis 6+ with ACLs, also configure <code>redisUsername</code></li>
            </ul>
            <p>Test from command line:</p>
            <div class="code-block">
                <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                <pre>redis-cli -h HOST -p PORT PING
# Or with authentication:
redis-cli -h HOST -p PORT -a PASSWORD PING</pre>
            </div>
        </div>
<?php
        endif ;
    endif ;
?>
    </div>
<?php endif ; ?>

<?php
// ============================================================================
// SECTION 6: Summary and Next Steps
// ============================================================================
?>
    <h2>6. Summary</h2>

<?php if ( $criticalErrors > 0 ) : ?>
    <div class="summary-box error">
        <p><strong><?php echo $failIcon ; ?> <?php echo $criticalErrors ; ?> critical issue(s) found</strong></p>
        <p>Please fix the issues marked with <?php echo $failIcon ; ?> above before using AQL.</p>
    </div>
<?php elseif ( $warnings > 0 ) : ?>
    <div class="summary-box warning">
        <p><strong><?php echo $warnIcon ; ?> Configuration valid with <?php echo $warnings ; ?> warning(s)</strong></p>
        <p>AQL should work, but review the warnings above for optimal operation.</p>
    </div>
<?php else : ?>
    <div class="summary-box success">
        <p><strong><?php echo $passIcon ; ?> Configuration looks good!</strong></p>
        <p>All required parameters are set and connectivity tests passed.</p>
    </div>
<?php endif ; ?>

    <div class="next-steps">
        <h3>Next Steps</h3>
        <ol>
<?php if ( $criticalErrors > 0 ) : ?>
            <li><strong>Fix critical issues</strong> - Address the errors shown above in your <code>aql_config.xml</code></li>
            <li><strong>Re-run this verification</strong> - Refresh this page after making changes</li>
<?php else : ?>
            <li><strong>Deploy the database schema</strong> - Run <a href="deployDDL.php">deployDDL.php</a> to create/update tables</li>
            <li><strong>Add hosts to monitor</strong> - Use <a href="manageData.php">Manage Data</a> to add database servers</li>
            <li><strong>Open AQL</strong> - Go to <a href="index.php">index.php</a> to start monitoring</li>
<?php if ( $testConfigured ) : ?>
            <li><strong>Run tests</strong> - Use <a href="testAQL.php">testAQL.php</a> to verify functionality</li>
<?php else : ?>
            <li><em>(Optional)</em> Configure <code>testDbUser</code>, <code>testDbPass</code>, <code>testDbName</code> to enable the <a href="testAQL.php">Test Harness</a></li>
<?php endif ; ?>
<?php endif ; ?>
        </ol>
    </div>

<?php endif ; // configLoaded ?>

    <div class="version-info">
        <p>AQL Configuration Verification |
        PHP <?php echo phpversion() ; ?> |
        Server: <?php echo htmlspecialchars( php_uname( 'n' ) ) ; ?> |
        <?php echo date( 'Y-m-d H:i:s T' ) ; ?></p>
    </div>

</div>

</body>
</html>
