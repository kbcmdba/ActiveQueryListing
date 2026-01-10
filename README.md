# MySQL Active Query Listing Tool (AQL)

Welcome to the MySQL Active Query Listing (AQL) tool. This tool provides
users with a listing of active queries on a (set of) MySQL server(s). This
PHP-based tool shows the list of queries running on a set of servers in a
color-coded format showing the longest-running queries at the top of the
list.

Please note that this software is somewhere between alpha and beta quality at
this time.

## Before using this tool:

Before using this tool, you'll need to install PHP 7.2 or greater. You'll
also need to run the following composer command:

```
composer install
```

If you don't have a xAMP (like WAMP, MAMP, or LAMP) stack or Symphony /
composer installed, Google is your friend.  :-) There are lots of good guides
out there on how to install these tools. I won't reproduce that excellent work
here. I personally like XAMPP by ApacheFriends. For more information, see
https://www.apachefriends.org/download.html

## Configuring AQL

Your installation comes with a config_sample.xml file. This file needs to be
installed in /etc/aql_config.xml. It should be readable only by the web server
in order to protect it from prying eyes. Protecting this file from prying eyes
is browser-dependent so that's the reason for asking it be put in /etc.

There are three parts to this configuration file that you need to pay special
attention to. DbPass is the password you're giving AQL in order to access
servers. In the code above, it's the "SomethingComplicated" bit. This will need
to be run on all your MySQL servers to AQL can access the server and get the
output of SHOW PROCESSLIST.

The next thing you'll need to pay special attention to is the
issueTrackerBaseUrl. Configuring this can be a bit of a project, but once it's
set up properly, it makes filing issues against a particular query very simple.

Finally, roQueryPart needs to be configured to detect when a MySQL server is in
read-only mode. For most installations, you can leave this alone. If you run an
older version of MySQL, you may need to adjust this to suit your installation's
needs.

## Setting up the MySQL database on the "configuration" server

In order to use AQL, you'll need to set up the database that tells AQL where
all the hosts will live as well as what the acceptable query thresholds are.
To do this, simply play the setup_db.sql file into your configuration master
database. It will wipe out the database named aql_db, then re-create it with
template data.

The user and password you'll set up in /etc/aql_config.xml should be consistent
across all your MySQL instances. The user will need the following on all the
instances that AQL will manage (I'll assume you'll use aql_app for the user):

```
-- You should adjust these lines to meet your needs.
-- Minimally, you shoud at least change the password. I recommend also changing
-- the user and host mask (%) so you don't leave yourself overly vulnerable to
-- a denial of service attack. Note that some more recent systems require
-- SUPER privilege in order to kill processes that are owned by others. This
-- doesn't prevent the application from killing processes when the authorized
-- user has the appropriate privileges.
CREATE USER 'aql_app'@'%' IDENTIFIED BY 'SomethingComplicated' ; 
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'aql_app'@'%' ;
GRANT ALL ON aql_db.* TO 'aql_app'@'%' ;
CREATE DATABASE IF NOT EXISTS aql_db ;
```

## Note about the "second" instance

During testing, we use row four from the host table as a replication slave of
row 1.

While setting up a replication instance is beyond the scope of these
instructions, row four of the host database assumes that you have a
replication slave set up on port 3307. If you want to ignore that "system,"
simply change the decommissioned setting to 1 and should_monitor to 0.

## Blocked/Blocking Query Detection

AQL can detect and display blocked and blocking queries. When a query is waiting
for a lock held by another query, you'll see:

- **BLOCKED** (hotpink) - Query is waiting for a lock
- **BLOCKING** (red) - Query is holding a lock that others are waiting for

Hover over the indicator to see details including the blocking thread ID and query.

### Blocking Cache (Optional Redis Setup)

AQL caches blocking relationships for 60 seconds so you can see "who was blocking"
even after the blocker finishes. This is especially useful for transient MyISAM
table-level locks.

**Cache backends (automatic detection):**

1. **Redis (recommended)** - Faster, automatic TTL expiry, no file permission issues
2. **File-based (fallback)** - Uses `/cache` directory if Redis unavailable

**To enable Redis caching:**

```bash
# Install Redis server (if not already installed)
sudo apt install redis-server

# Install PHP Redis extension
sudo apt install php-redis

# Restart Apache to load the extension
sudo systemctl restart apache2
```

The cache directory (`/cache`) must be writable by the web server if using
file-based caching:

```bash
sudo chown www-data:www-data /path/to/aql/cache
```

### Lock Detection Debug Mode

Add `&debugLocks=1` to the URL to enable lock detection debugging. This shows:
- Cache type (redis/file)
- Lock wait count
- Open tables with locks
- Blocking cache contents

### Additional MySQL Permissions for Lock Detection

For enhanced lock detection on MySQL 8.0+, grant these permissions to the AQL user:

```sql
-- Optional: for InnoDB row-level and metadata lock detection
GRANT SELECT ON performance_schema.data_lock_waits TO 'aql_app'@'%';
GRANT SELECT ON performance_schema.data_locks TO 'aql_app'@'%';
GRANT SELECT ON performance_schema.metadata_locks TO 'aql_app'@'%';
GRANT SELECT ON performance_schema.threads TO 'aql_app'@'%';
```

Note: Basic table-level lock detection works without these additional permissions.

### Blocking History Logging

AQL automatically logs blocking queries to the `blocking_history` table for pattern analysis.
This helps identify repeat offender queries that frequently cause lock contention.

**Features:**
- Queries are normalized (strings/numbers replaced with placeholders) to avoid storing sensitive data
- Deduplication via query hash - each unique query pattern stored once per host
- Tracks how many times a query was seen blocking and total blocked queries
- Auto-purges entries older than 90 days (runs on ~1% of requests)

**Table schema:** Run `deployDDL.php` to create the `blocking_history` table.

**Viewing history:** Query the table directly:
```sql
-- Top 10 most frequent blocking queries
SELECT h.hostname, bh.user, bh.blocked_count, bh.total_blocked, bh.query_text, bh.last_seen
FROM aql_db.blocking_history bh
JOIN aql_db.host h ON h.host_id = bh.host_id
ORDER BY bh.blocked_count DESC
LIMIT 10;
```

## Test Harness (testAQL.php)

AQL includes a test harness (`testAQL.php`) for validating your configuration and
testing AQL features. The test harness is safe to run on production servers as it
uses a dedicated test database and does not modify production data.

### Available Tests

- **Validate Configuration** - Checks `aql_config.xml` parameters, validates values
  (URLs, ports, timezones), and tests database connectivity. Run this first to ensure
  AQL is properly configured.

- **Application Smoke Test** - Fetches main AQL pages (index.php, manageData.php,
  testAQL.php) and verifies they return HTTP 200 without PHP errors. Useful for
  verifying the application is properly installed.

- **Database User Verification** - Tests both the application user (usually `aql_app`) and
  test user (usually `aql_test`) connectivity on the config server and all monitored
  MySQL/MariaDB hosts. For the app user, also checks PROCESS, REPLICATION CLIENT, and
  performance_schema privileges.

- **Schema Verification** - Read-only check that verifies the aql_db database exists,
  all required tables are present (host, host_group, maintenance_window, etc.), and
  required columns exist in each table.

- **Deploy DDL Verification** - Verifies that `deployDDL.php` will run without errors.
  Shows which tables exist vs would be created, and which migrations are pending vs
  already applied. For up-to-date installs, confirms schema is current.

- **Automated Blocking Test** - Creates a test table, runs two MySQL sessions in parallel
  (one holding a lock, one waiting), and verifies lock detection works correctly.

- **Test Blocking JavaScript** - Verifies that the JavaScript modifications for the
  "File Issue" button work correctly when a query is blocking others.

- **Jira Integration Test** - Manual test with step-by-step instructions for verifying
  Jira issue filing works. Shows current Jira configuration status and simple test steps.

- **Cleanup Test Data** - Removes test tables created during testing.

### Test Database Setup (Optional but Recommended)

To use tests that require database operations, create a dedicated test user and database:

```sql
-- Create a database for testing
CREATE DATABASE IF NOT EXISTS aql_test;

-- Create the test user with privileges on the test database
CREATE USER 'aql_test'@'localhost' IDENTIFIED BY 'YourTestPassword';
GRANT ALL PRIVILEGES ON aql_test.* TO 'aql_test'@'localhost';

-- The test user also needs PROCESS privilege to see other connections
GRANT PROCESS ON *.* TO 'aql_test'@'localhost';
```

Then add the test configuration to `aql_config.xml`:

```xml
<param name="testDbUser">aql_test</param>
<param name="testDbPass">YourTestPassword</param>
<param name="testDbName">aql_test</param>
```

### Access the Test Harness

Navigate to `https://your-server.your-company.com/ActiveQueryListing/testAQL.php`

**Note:** The test harness only operates on the local configuration database server
and the dedicated test database. It will not affect production database servers or data.

### Command Line Test Runner

For bash users, a command-line test runner is available:

```bash
# Run all CLI-compatible tests
./run_tests.sh

# Run a specific test
./run_tests.sh config_validate
./run_tests.sh schema_verify

# Show available tests
./run_tests.sh --help
```

Some tests require web context and are skipped when run from CLI (marked with `*` in help).
Run these tests via browser instead.

## SELinux Installation Tips for Fedora/Redhat/CentOS

In order to allow this program to run under Fedora-based systems, it's
important to either turn off SELinux completely (yuck), or to make it possible
for programs running under your web server to connect to the database and read
files stored in the web server's directory. Older versions of Fedora Linux
required these commands.

```
sudo setsebool -P httpd_can_network_connect_db 1
sudo setsebool -P httpd_read_user_content 1
```

For more information on ActiveQueryListing, please see:

https://github.com/kbcmdba/ActiveQueryListing/

