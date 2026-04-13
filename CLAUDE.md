# Claude Code Instructions for ActiveQueryListing

## Self-Improvement

**Update this file** with lessons learned during each session. When you discover something important about the codebase, patterns, gotchas, or user preferences - add it here. This helps us both work together more effectively over time. When you find something that applies to both this and the other environment, apply that same knowledge to both this file and CLAUDE_KNOWLEDGE_SHARED.md. This makes it possible to keep two versions of the file - one local and one shared in GitHub.

## Git Workflow

**Always pull before committing:**

```bash
git pull
```

This prevents merge conflicts and ensures we have the latest changes from the remote repository.

**Do NOT push to GitHub** - authentication is not configured for this repo. Commits are fine, but the user will handle pushing independently.

**Before every commit, ask yourself:** Is there anything learned worth adding to CLAUDE.md? If so, vet with the user whether it should also go into CLAUDE_KNOWLEDGE_SHARED.md.

**After every git pull, check:** Are there new learnings in CLAUDE_KNOWLEDGE_SHARED.md that should be synced to local CLAUDE.md? If so, vet with the user and incorporate them.

## Project Overview

- **Type**: PHP web application for monitoring database servers (AQL - Active Query Listing)
- **Framework**: Custom PHP with jQuery frontend
- **Database**: MySQL for AQL's own data storage

## Supported DB Types

The `db_type` ENUM in the host table: MySQL, InnoDBCluster, MS-SQL, Redis, OracleDB, Cassandra, DataStax, MongoDB, RDS, Aurora, PostgreSQL

- **MySQL** includes MariaDB (combined - same monitoring code path)
- **OracleDB** named to distinguish from Oracle-owned MySQL
- **mysqlEnabled is required** - AQL's backend is MySQL, so this must be true
- Other DB types are optional and enabled via `{type}Enabled` in config
- Config params like `{type}Enabled`, `{type}Username`, `{type}Password` are validated by pattern matching (not hardcoded)

## Key Files

- `index.php` - Main dashboard
- `AJAXgetaql.php` - AJAX endpoint for fetching server data
- `verifyAQLConfiguration.php` - Configuration verification tool
- `deployDDL.php` - Database schema deployment
- `aql_config.xml` - Configuration file (not tracked)
- `js/common.js` - Main JavaScript functions
- `js/klaxon.js` - Alert sounds and speech synthesis
- `utility.php` - PHP utility functions
- `upgradeConfig.php` - Config format migration tool (v1→v2)
- `todo.php` - Active todo list (see below)
- `rfe.php` - Feature requests for future consideration

## Task Tracking

**Workflow**: Before implementing new features, add them to todo.php first. This ensures:
- Ideas don't get lost if the session ends unexpectedly
- You're communicating with your future self
- The user has visibility into planned work

### todo.php
- Contains active todos using format `// @todo XX description`
- Sub-tasks use format `// @todo XX-YY description`
- **Remove todos when completed** - don't mark as done, just delete them
- Lower numbers = higher priority
- **Leave gaps between numbers** for future insertions (e.g., 10, 15, 20 allows adding 12 or 18 later)
- **Subtasks belong to their parent** - New top-level todo (e.g., 21) goes AFTER all subtasks of previous number (e.g., 20-35, 20-40), not between parent and subtasks
- **User workflow**: `watch -n 10 'tail +27 todo.php'` in a tmux pane shows live todo updates

### rfe.php
- Contains feature requests using format `// @rfe XXX description`
- "Someday-maybe" list - ideas that haven't been prioritized highly enough for todo.php
- When a feature gets prioritized, move it from rfe.php to todo.php
- Example: `// @rfe 326 Apache Hadoop/HBase support`

## Page Structure

Use `Libs/WebPage.php` for all pages - provides navbar, standard CSS/JS, and page wrapper:

```php
$page = new WebPage( 'Page Title' ) ;
$page->setBody( $bodyContent ) ;
$page->displayPage() ;
```

For pages with heavy PHP/HTML mixing, use output buffering:
```php
ob_start() ;
// ... mixed PHP/HTML content ...
$body = ob_get_clean() ;
$page->setBody( $body ) ;
```

## Styling - Light/Dark Mode

All styles go in `css/main.css` - avoid per-file custom styles for consistency.

**Theme system uses CSS variables** defined in `:root` (dark mode default) and `.theme-light` override:

| Variable | Purpose |
|----------|---------|
| `--bg-body`, `--bg-secondary`, `--bg-tertiary`, `--bg-header` | Background colors |
| `--text-primary`, `--text-secondary`, `--text-muted` | Text colors |
| `--border-light`, `--border-medium`, `--border-dark` | Border colors |
| `--link-color` | Link color |
| `--status-error`, `--status-warning`, `--status-success` | Status indicator colors |

**Always use these variables** instead of hardcoded colors like `#f44336` or `#4caf50`.

**Contrast tip**: For informational/accent text that needs to stand out:
- Dark mode: Use gold/yellow (`--info-accent: #ffd700`) - excellent contrast on dark backgrounds
- Light mode: Use blue (`--info-accent: #0066cc`) - excellent contrast on light backgrounds
- Blue (#0254EB) on dark grey has poor contrast and looks washed out!

Example:
```css
.my-element {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-dark);
}
.my-error { color: var(--status-error); }
.my-info { color: var(--info-accent); }  /* Gold in dark, blue in light */
```

## Database Migrations

Migrations in `deployDDL.php` follow this pattern:
1. Check if migration is needed (query current schema state)
2. Update data first (e.g., convert values before changing ENUM)
3. Alter schema (e.g., modify ENUM, add columns)
4. Report results in HTML table

## Testing

- Run `php -l <file>` to check PHP syntax before committing
- Run `php index.php 2>&1 | head` to test page output and see runtime errors
- Test the app via browser at the configured baseUrl
- Use `verifyAQLConfiguration.php` to check environment setup

### Unit Tests (PHPUnit)
- **Framework**: PHPUnit 11.x via composer dev dependency
- **Run all tests**: `composer test` (fast, no coverage)
- **With coverage**: `composer test-coverage` (HTML report at `build/coverage/index.html`)
- **Coverage requires**: `php-pecl-xdebug3` package installed (Fedora) — script sets `XDEBUG_MODE=coverage` automatically
- **Test layout**: `tests/Libs/<ClassName>Test.php` mirrors `Libs/<ClassName>.php`
- **Autoload namespace**: `com\kbcmdba\aql\Tests\` → `tests/` (autoload-dev in composer.json)
- **PHPUnit config**: `phpunit.xml` at repo root, includes `failOnWarning` and `failOnRisky`

### Pure-Function Extraction Pattern (for testability)
When refactoring code to be testable, the pattern is:
1. Identify the file IO / static cache / global state in the existing code
2. Extract the pure transformation logic into a public static method that takes
   inputs (not files or globals) and returns outputs
3. Make the existing code call the new static method
4. Tests call the static method directly with synthetic inputs

Examples already in the codebase:
- `Config::parseConfigXml(string): array` — extracted from constructor's parsing
- `Config::buildConfigValueArray(SimpleXMLElement): array` — extracted from `getConfigValue()`
- `ConfigUpgrader::upgrade(string): string` — extracted from upgradeConfig.php script

This keeps the production code's API stable (constructor, getConfigValue, the upgrade
script) while making the underlying logic testable in isolation.

### Pre-Commit Hook (Advisory)
- **Location**: `.githooks/pre-commit` (tracked in git, unlike `.git/hooks/`)
- **Activation**: Per-clone via `scripts/install-hooks.sh` (sets `core.hooksPath = .githooks`)
- **What it does**: Runs `php -l` on staged `.php` files, then full `composer test`
- **Advisory mode**: Reports failures loudly with banners but **always exits 0**.
  Rationale: when debugging, you most want to commit work-in-progress state
  even if it's broken. Blocking commits risks losing code to power loss or
  distraction. Failing tests during a session are valuable historical data
  for chasing bugs — you can see exactly when something started failing.
- **Output**: Color-coded green (pass) / yellow (running) / red (warning banner)

### Bugs Caught by Tests (TDD wins from this codebase)
The first sweep of unit tests caught **4 real bugs** that had been silently
broken in production. Worth remembering when you wonder if TDD is worth it.

1. **`friendlyTime()` PHP 8.x deprecation** — `($in_seconds / 60) % 60`
   triggered "Implicit conversion from float to int loses precision" because
   `/` returns float in PHP 8.x and `%` on float is deprecated. Fix: use
   `intdiv()`. Lurked silently in error logs.

2. **`ModelBase::validateId(null)` PHP 8.x deprecation** — `preg_match()`
   doesn't accept null as the subject in PHP 8.x. Anywhere a model with a
   null id called `validateForDelete()` would log a deprecation. Fix: explicit
   null guards plus `(string)` casts.

3. **`HostGroupMapModel::validateForAdd` literal-string bug** — checked the
   literal string `'lastAudited'` instead of `Tools::param('lastAudited')`.
   Always-false short-circuit meant adding ANY host-to-group mapping silently
   failed validation. The user just saw "validation failed" with no clue why.
   This was probably broken since the file was first written.

4. **`HostModel::populateFromForm()` undefined method** — called the
   non-existent `setHostId()` instead of `setId()`. Would have crashed with
   "Call to undefined method" the moment anyone called it. The reason it
   never surfaced: nothing outside of tests calls `populateFromForm()`. The
   Model layer is orphaned scaffolding — manageData.php and the AJAX
   endpoints bypass it and call `Tools::param()` directly.

5. **`Config::assignProperties` missing 5 LDAP `??` defaults** — when a v2
   config has no `<ldap>` element, `doLDAPAuthentication`, `ldapHost`,
   `ldapDomainName`, `ldapUserGroup`, `ldapUserDomain` were accessed without
   `??` null coalescing. PHP 8.x warns "Undefined array key". Fix: add `?? 'false'`
   or `?? ''` to each.

6. **`Tools::makeQuotedStringPIISafe` PII leak via escaped quotes** — see above.

7. **All 3 Controllers used `$this->_dbh` instead of `$this->dbh`** — the parent
   `ControllerBase` declares `protected $dbh` but all children referenced
   `$this->_dbh` (underscore convention from PHP 4 era). Every method in every
   controller would fail at runtime. Fixed in HostController, HostGroupController,
   HostGroupMapController.

8. **HostController INSERT used `lastAudited`** instead of `last_audited` (snake_case).

9. **HostController/HostGroupController DELETE/UPDATE used `WHERE id = ?`** but
   the PK columns are `host_id` and `host_group_id`. Would delete/update nothing.

10. **HostController INSERT bind_param order** didn't match column order — description
    and port_number were swapped. Port went into description column and vice versa.

11. **HostGroupController `get()` missing `setTag($tag)`** — tag fetched from DB
    but never set on the returned model. `getSome()` had it; `get()` didn't.

12. **HostGroupController `createTable()` DDL typo** — `full_descripton` (missing 'i').

13. **HostGroupMapController `get()` used wrong model class** — `new HostGroupModel()`
    instead of `new HostGroupMapModel()`. Would crash with "undefined method
    setHostGroupId()".

14. **HostGroupMapController `get()` called `setLastUpdated($lastUpdated)`** — wrong
    method name (should be `setLastAudited`) AND wrong variable (should be `$lastAudited`).

15. **HostGroupMapModel missing `validateForDelete()` override** — composite-key model
    (hostGroupId + hostId) inherited base class `validateForDelete()` which calls
    `$this->getId()` — method doesn't exist on this model. Fixed by overriding to
    validate both key fields.

16. **`ControllerBase::__construct` catch used `$this->dbh->error`** — if
    `DBConnection` throws, `$this->dbh` is never assigned (still null). The catch
    block would then fatal on null dereference before throwing the intended
    ControllerException. Fixed: use `$e->getMessage()` instead.

17. **`HostGroupController` and `HostGroupMapController` `createTable()` missing
    `IF NOT EXISTS`** — `HostController` had it, the other two didn't. Re-running
    `deployDDL.php` after initial setup would fail with "table already exists".
    Fixed by adding `IF NOT EXISTS` to both DDL statements.

### Lesson: the entire Controller layer was broken
Bugs #7-#15 (nine bugs!) were all in the Controller layer. Like the Model layer,
the Controllers are orphaned scaffolding that production code bypasses. Every
single CRUD method in every Controller was broken in at least one way. Without
TDD, these bugs would have persisted indefinitely until someone tried to wire
up the MVC layer — at which point NOTHING would have worked.

6. **`Tools::makeQuotedStringPIISafe` PII leak via escaped quotes** — the old
   regex-based function couldn't handle backslash-escaped quotes inside string
   literals. `'O\'Brien'` became `'OBrien'` which segmented wrong and leaked
   "Brien" in the output (stored in `blocking_history` table — long-term PII
   exposure). Fix: rewrote as a state-machine tokenizer with full UTF-8 support,
   LIKE pattern preservation, backtick/comment handling. Old function deleted.

### Lesson: orphaned scaffolding
The Model layer (`Libs/Models/`) and Controller layer (`Libs/Controllers/`)
look like a clean MVC pattern but in practice they're dead code. The actual
form handlers in `manageData.php` skip both and talk to `Tools::param()`
directly. When working on data validation or persistence, check whether the
Model class is actually wired up before assuming changes there will take
effect. If it's not wired up, you may need to update both the Model AND
the procedural code in `manageData.php`.

### PHP 8.x Gotchas Caught by Tests
- **`intdiv()` instead of `/`**: PHP 8.x deprecates implicit float-to-int conversion.
  `($x / 60) % 60` now triggers a deprecation warning because `/` returns float.
  Use `intdiv($x, 60) % 60` instead.
- **`preg_match(null)` deprecation**: Subject parameter no longer accepts null.
  Add `if (null === $x) return false ;` guards or cast to `(string)` before
  passing to `preg_match()`.

### Test Discovery Patterns
- **Test private static methods via reflection**: For pure functions inside a
  class, `(new ReflectionMethod(MyClass::class, 'method'))->setAccessible(true)`
  then `->invoke(null, ...$args)`. Used in `MaintenanceWindowTest` to test
  the schedule-matching helpers without making them public.
- **Concrete subclass for abstract base testing**: When the class under test
  is abstract, define a `TestableX extends X` class in the test file with
  minimal implementations of the abstract methods. Used in `ModelBaseTest`.
- **`#[DataProvider]` attribute, not `@dataProvider` annotation**: PHPUnit 11
  rejects the old phpdoc-style annotation. Use the attribute and make the
  provider method `public static`.
- **`@codeCoverageIgnore` — use per-line, NOT Start/End blocks inside methods**:
  `@codeCoverageIgnoreStart` / `End` blocks inside a method can leak and
  swallow the entire method or subsequent methods from coverage. Use per-line
  `// @codeCoverageIgnore` on specific untestable lines instead (e.g., `exit()`
  calls, file-missing guards, static-cache branches that depend on live config).
- **`Config::fromXmlString(string): Config`** — static factory for building
  fully-initialized Config instances from synthetic XML strings. Lets tests
  exercise the full assignment pipeline (parsing + property assignment +
  getters) without depending on the live `aql_config.xml`.
- **`Config::__toString()` now shows masked config** — passwords display as
  `********` or `(not set)`. All non-credential values visible for debugging.
  Safe to use in error logs, debug pages, `var_dump()`.
- **Integration tests with `markTestSkipped()`**: For DB/LDAP tests, use a
  helper like `getDbhOrSkip()` that tries to connect and calls
  `$this->markTestSkipped()` if infrastructure is unavailable. This way the
  test suite passes on machines without MySQL/LDAP/Redis.
- **LDAP test users**: `aql_test` (in AQL_Admins) and `aql_test_nogroup`
  (no groups) on Samba AD. Credentials stored in `<testing>` element of config.
  Account lockout threshold set to 5 on Samba AD (safe for test runs).
- **Controller CRUD integration test pattern**: Create prerequisite rows (host,
  group) → test the CRUD operation → clean up in tearDown. Use unique prefixes
  (`__phpunit_test_host_`, `_tgrp_`, `__phpunit_map_`) to avoid colliding with
  real data. For join tables (HostGroupMap), clean up mappings FIRST (FK
  constraints), then groups and hosts.
- **VARCHAR column length matters for test data**: `host_group.tag` is VARCHAR(16).
  Test tag prefixes + `uniqid()` can easily exceed 16 chars. Use `substr(uniqid(), -N)`
  to truncate, and keep prefixes short.
- **Pre-commit hook colors**: Avoid yellow text — it's hard to read on light-green
  terminal backgrounds. Use blue (`\033[1;34m`) for status messages, green for
  success, red for errors/warnings. White can also look yellowish depending on
  the terminal theme.
- **PHPUnit test file ordering**: PHPUnit processes files in a directory before
  descending into subdirectories. So `tests/Libs/*.php` runs BEFORE
  `tests/Libs/Controllers/*.php`. Dirty state left by earlier test classes
  (e.g. LDAPTest) can contaminate later ones (e.g. HostGroupControllerTest).
- **`@` error suppressor leaves dirty error handlers**: PHPUnit 11 detects when
  a test class leaves the PHP error handler stack in a different state than it
  found it and marks those tests as "risky". When you have an explicit guard
  (e.g. `if (session_status() !== PHP_SESSION_ACTIVE)`), the `@` on the call
  is unnecessary — remove it to avoid the contamination.
- **Controller DDL coverage pattern**:
  - `createTable()` with `IF NOT EXISTS` is safe to call in integration tests
    (no-op if table exists) — use it to cover `doDDL()` success path in
    ControllerBase.
  - `dropTable()` method bodies get `// @codeCoverageIgnore` — calling them
    against the live DB is destructive and not safe to test.
  - DB-failure throw lines in `get()`, `getSome()`, `add()`, `delete()` get
    the same `// @codeCoverageIgnore` treatment as `update()` — require the
    DB to malfunction, which can't be triggered without mocking.

### makeQuotedStringPIISafe — State-Machine Tokenizer
The original regex-based PII sanitizer was replaced with a UTF-8-safe
state-machine tokenizer. Key design points:
- Walks input one byte at a time, tracking state (default, in-string, in-comment, in-backtick)
- **Backslash escapes**: `utf8CharLength()` correctly skips the full multi-byte character (1-4 bytes) after a backslash
- **SQL doubled-quote escapes**: `''` inside `'...'` and `""` inside `"..."` handled correctly
- **LIKE patterns preserved**: `'%foo%'` → `'%S%'`, `'foo%bar'` → `'S%S'` etc. so analysts can identify wildcard queries after sanitization
- **Backtick identifiers**: ``\`col_name\``` preserved as-is (they're schema names, not user data)
- **Comments**: `--`, `#`, `/* */` preserved
- **`isWordChar()`** treats any non-ASCII byte (0x80+) as identifier content so `café1` stays intact
- **Empty/unclosed strings**: fail safe, no crash

### DBConnection::myErrorHandler()
Intentionally returns (doesn't throw) to suppress PHP warnings from
`real_connect()`. The more descriptive `$mysqli->connect_error` path
handles the failure after the call returns. Throwing would lose context
from multiple warnings and skip `restore_error_handler()`.

## Input Size Limits (Security)
- **`Tools::DEFAULT_MAX_INPUT_LENGTH = 8192`** — default cap on every `param/get/post` call
- **All `Tools::param/get/post/params/gets/posts` methods accept an optional `$maxLength`** parameter (4th arg)
- **Sensible per-field caps**: hostnames=255, ports=5, passwords=256, usernames=64,
  reasons/descriptions=4096, alert seconds=16, action enums=32
- **Defense-in-depth**: Don't rely on web server limits — CLI users can bypass them
  via `php-cgi`, internal callers may not have a web server in front
- **Fail-closed**: Oversized inputs return the default value AND log to `error_log`
  with the parameter name, length, and remote IP. Attacker gets nothing useful;
  defender sees the attempt
- **Backward compatible**: Existing callers using the default still work; tighter
  caps only apply when explicitly passed

## Lessons Learned

### Config Gotchas
- After `git pull`, new required config parameters can break AQL with 500 errors
- Config file is `./aql_config.xml` (local to each instance, not `/etc/`)
- Test with `php index.php 2>&1 | head` to see runtime errors, not just syntax
  - **Always run `verifyAQLConfiguration.php` before `deployDDL.php`** — verify catches config/PHP issues first
- **baseUrl must match the hostname you access AQL from** — mismatch = AJAX calls go to wrong server or get blocked by CORS
- **Avoid trailing spaces in passwords** — they get trimmed by YAML, Ansible, and most templating tools, causing auth mismatches
- **DTD validation**: `aql_config.dtd` validates config structure. Run `xmllint --valid --noout aql_config.xml` to check.

### Config Format (v2 — Grouped Elements)
- **Config version**: `<config version="2">` — no version attribute = legacy v1
- **Format detection**: Parser auto-detects via `version` attribute or presence of `<configdb>`/`<monitoring>`/`<user>` elements
- **Upgrade tool**: `php upgradeConfig.php` (dry run) or `php upgradeConfig.php --write` (auto-backs up to `.bk`)
- **Legacy v1 flat `<param>` format still fully supported** — backward compatible
- **Key grouped elements**:
  - `<configdb type="mysql" host="..." port="..." name="..." />` — AQL's own database (ONLY host in config)
  - `<user type="admin" name="..." password="..." />` — configdb credentials
  - `<user type="monitor" name="..." password="..." />` — default for all monitored hosts
  - `<monitoring>`, `<authentication>`, `<ldap>`, `<jira>`, `<features>`, `<testing>` — grouped settings
  - `<environment_types>` with `<environment_type name="..." default="true" />` children
  - `<redis>` — connection tuning only (connectTimeout, database)
  - `<timeouts connectTimeout="N" readTimeout="N" />` — site-wide DB timeout defaults (seconds); omit for hardcoded fallbacks (4s/8s)
  - `<dbtype>` elements unchanged — control which types are enabled + per-type credential overrides
- **Credential resolution chain**: admin user → configdb only. monitor user → default for all monitored hosts. `<dbtype username/password>` → per-type override. Per-host → future.
- **All monitored hosts live in the `host` table** (via manageData.php), never in config XML
- **Internal flat-key mapping**: `parseGroupedConfig()` maps grouped attributes back to same internal flat keys (`dbHost`, `ldapHost`, etc.) — all getters and `getConfigValue()` callers unchanged

### Config Testing Pattern
When modifying config parsing, always test all three steps:
1. Old v1 config with new code (backward compat)
2. Run `upgradeConfig.php --write` (migration)
3. New v2 config with new code (post-upgrade)

### Redis Config Keys
- **`redisUser`/`redisPassword`**: Read by `getRedisUser()`/`getRedisPassword()` in AJAXgetaql.php — these are the keys that matter for Redis auth
- **`redisUsername`/`redisPassword`**: Set by `<dbtype name="redis" username="...">` pattern
- **Key mismatch fix**: `parseDbTypes()` special-cases Redis to also set `redisUser` when `username` is on `<dbtype>`
- **`noMonitorFallbackTypes`**: Redis (and future non-relational types) excluded from monitor user credential inheritance — Redis has its own auth model
- **Three Redis auth scenarios**: No auth (no creds), password only (`password="..."` on dbtype), ACL user+password (`username="..." password="..."` on dbtype)

### Per-type Credentials
- Set via `<dbtype>` attributes or inherited from `<user type="monitor">`
- MySQL falls back to admin user (dbUser/dbPass) in v1, monitor user in v2
- Redis does NOT inherit monitor credentials — uses its own auth or none

### Authentication
- **LDAP on**: Authenticates via Active Directory. `adminPassword` is ignored.
- **LDAP off**: Authenticates via `adminPassword` in config. Any username accepted (tracked in session for audit).
- These are mutually exclusive — `verifyAQLConfiguration.php` warns if both are configured.
- **index.php graceful error**: If DB connection fails, shows a friendly error page with links to `verifyAQLConfiguration.php` and `deployDDL.php` instead of a raw 500.
- **Only manageData.php requires login** — index.php (main dashboard) has no auth check
- **Debug gotcha**: `ldapDebugConnection=true` only produces output on the LDAP code path. If `ldap enabled="false"`, the local auth path is taken and debug code is never reached — no output at all, silently.
- **Samba AD requires strong auth**: Plain `ldap://` bind fails with "Strong(er) authentication required". Options:
  1. Use `ldaps://` (requires TLS cert on Samba AD)
  2. Add StartTLS support to LDAP.php (`ldap_start_tls()` on port 389)
  3. Disable on Samba: `ldap server require strong auth = no` in smb.conf (lab only, not production)
- **AJAXKillProc.php is MySQL-only**: PG handler emits kill buttons but they route to MySQL-specific kill code. Needs dbtype-aware dispatch (see @todo 24).

### LDAP/Samba AD Lessons (the saga)
- **Samba AD requires strong auth by default**: Plain `ldap://` bind fails with "Strong(er) authentication required". Three options: LDAPS (`ldaps://`), StartTLS, or `ldap server require strong auth = no` in smb.conf (lab only).
- **StartTLS is the right answer for most setups**: Upgrades port 389 to TLS via `ldap_start_tls()` after `ldap_connect()`. Configured via `<ldap startTls="true" />`.
- **CRITICAL: TLS option order matters**: `ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, ...)` is a GLOBAL option that only applies to connections created AFTER it's set. **Set TLS options BEFORE `ldap_connect()`**, not after. Setting them on the existing handle is too late.
- **Self-signed certs need extra options**: `LDAP_OPT_X_TLS_REQUIRE_CERT = NEVER` alone isn't enough. Also clear `LDAP_OPT_X_TLS_CACERTDIR` and `LDAP_OPT_X_TLS_CACERTFILE` to prevent fallback to `/etc/openldap/ldap.conf` defaults.
- **Cert CN must match the hostname AQL uses**: Even with `verifyCert=false`, mismatch can fail. Cert CN=`ad1.hole.local` doesn't work when AQL connects to `ad1.hole`. Regenerate cert with proper CN/SAN or change AQL's host to match.
- **Avoid `.local` TLDs in AD realms**: Conflicts with mDNS/Bonjour. Use `.ad`, `.lan`, `.internal`, or `.home` instead. We use `hole.ad` (NetBIOS: `HOLE`).
- **NetBIOS domain (used in `userDomain` config)**: Must be uppercase to match Samba's expectation. AQL builds the bind DN as `userDomain\username` (e.g., `HOLE\kbenton`).
- **Debug output gotcha**: `<ldap debugConnection="true" />` only produces output on the LDAP code path. If `ldap enabled="false"`, the local auth path is taken and debug code is never reached — no output, silently. Always set `enabled="true"` first.
- **Useful diagnostic commands**:
  - `nc -zv ad1.hole 389` and `nc -zv ad1.hole 636` — port reachability
  - `echo | openssl s_client -connect ad1.hole:636 2>&1` — see the cert from LDAPS
  - `echo | openssl s_client -connect ad1.hole:389 -starttls ldap` — test StartTLS at the protocol level
  - `openssl x509 -noout -subject -ext subjectAltName` — verify CN and SAN
  - `cat /etc/openldap/ldap.conf` — see what defaults the OpenLDAP client library will use

### Session Timeout (manageData.php)
- **Three session keys required**: `AuthUser`, `AuthCanAccess`, `AuthLoginTime` — all set on login (LDAP success path AND local auth path)
- **`dbaSessionTimeout` config value** controls timeout (was previously ONLY used for `dba_auth` maintenance windows; now also gates the main login)
- **Server-side check**: `doLoginOrDie()` in manageData.php checks `time() - AuthLoginTime > sessionTimeout` and clears the session if expired
- **Pre-fix session handling**: If `AuthUser` is set but `AuthLoginTime` is missing (legacy session before the timeout fix, or external session manipulation), the session is treated as expired and force-cleaned
- **Auto-logout JS timer pattern**: PHP calculates `remainingMs = (loginTime + timeout - now) * 1000`, JS `setTimeout()` fires when expired, alerts user, redirects to `?logout=logout`. If `remainingMs <= 0` at page load, redirects immediately. Handles the "user sat on page for hours" case so they don't discover the timeout on next click.

### Environment System
- `environment` table with TINYINT UNSIGNED PK, name, sort_order
- `host.environment_id` nullable FK (ON DELETE SET NULL)
- **v2 config**: `<environment_types>` with `<environment_type>` children. Document order = sort_order (10, 20, 30...). Optional explicit `sort_order` attribute (all-or-nothing). `default="true"` marks the default.
- **v1 config**: `environments` param (comma-separated list), `defaultEnvironment` param
- `deployDDL.php` uses `getEnvironmentTypes()` for structured data, falls back to comma-separated string
- Environment dropdown in manageData.php host form

### PostgreSQL/pg_connect Patterns
- `pg_connect()` uses space-delimited connection strings — passwords with spaces must be single-quoted: `password='my pass'`
- Escape single quotes and backslashes in passwords before embedding in connection string
- `pg_monitor` role (PG 10+) grants read access to all monitoring views
- Version from `version()` is verbose ("PostgreSQL 16.13 on x86_64...") — extract with regex for display

### Adding New DB Types
- Follow Redis as the wiring template (config, dispatch, handler, JS), but MySQL as the output template for relational DBs
- Handler must return same JSON shape as `handleMySQLHost()`: `result[]`, `overviewData`, `slaveData`, `renderTimeData`
- Add `dbType` field to JSON output for JS scoreboard routing
- Update: `dbTypeStats` in common.js, scoreboard in WebPage.php, overview box in index.php
- Update: `someHostsQuery` and host group query in index.php — these were hardcoded to MySQL types
- Update: `verifyHostPermissions()` in manageData.php — skip for non-MySQL types
- `utility.php` `processHost()` generates the AJAX calls — debug param must pass through fully, not just `debug=1`

### CSS Patterns
- Use `rem` units, not `em`
- Alternating row styles (e.g., `level4-alt`) must match base styles (e.g., `level4`) for font-size
- Use CSS variables for theme support: `var(--text-primary)`, `var(--bg-tertiary)`, etc.
- For theme-compatible elements: `color: inherit; background: transparent;`
- **Right-align class**: Use Bootstrap's `text-right`, not custom `right-align` (no such class exists in our CSS)

### JavaScript Patterns
- `friendlyTime()` in JS matches `Tools::friendlyTime()` in PHP
- Use `data-text` attribute for tablesorter numeric sorting with display text
- **tablesorter and summary rows**: Put summary/total rows in `<tfoot>`, not `<tbody>`. Tablesorter re-sorts `<tbody>` rows but leaves `<thead>` and `<tfoot>` alone, so summary rows in `<tbody>` end up in the middle of the table.
- Local silencing uses `localStorage` (shared across tabs)
- Speech synthesis needs to re-check `checkMuted()` before firing (2-second delay)
- **jQuery $.when() gotcha**: With 2+ promises, results are wrapped as `[data, textStatus, jqXHR]` arrays. With 1 promise, data is passed directly. Handle both cases!
- **Callback dispatch**: When adding new handler functions (e.g., `redisCallback`), must dispatch to them from `myCallback()` - they won't be called automatically
- **Variable scope in callbacks**: Define variables like `hasIssues` before using them - undefined variables cause silent JS failures
- **CSS tooltips vs native title**: Use `data-tooltip` attribute with CSS `::after` pseudo-element for instant tooltips. Native `title` has ~500ms browser delay.
- **Use `<span>` not `<a>` for non-link clickables**: Anchor tags without `href` can cause unexpected navigation in Chrome. Use `<span class="help-link">` with `cursor: pointer` styling instead.
- **sessionStorage for per-session delta tracking**: Use `sessionStorage` to track baselines for cumulative counters. Each tab gets independent tracking, cleared on tab close.
- **noscript inline styles**: Use inline styles in `<noscript>` blocks since CSS variables and external stylesheets may not be available.
- **$.when() blocks on ALL promises**: If one AJAX request hangs, the entire `.then()` callback waits. For multi-host monitoring, use individual AJAX calls with immediate callbacks instead.
- **Progressive loading pattern**: Fire all AJAX requests independently, process each response in `.done()`, track completion with a counter, run finalization when all complete (success OR failure via `.always()`).
- **Track pending requests by name**: Use an object like `pendingHosts[hostname] = true`, delete on completion, display `Object.keys(pendingHosts)` to show what's still loading.

### MySQL/PHP Timeout Patterns
- **Connection timeout**: `MYSQLI_OPT_CONNECT_TIMEOUT` - time to establish connection (default 4 sec)
- **Read timeout**: `MYSQLI_OPT_READ_TIMEOUT` - time for query execution (set to 8 sec)
- **PHP execution limit**: `set_time_limit(10)` as safety net for entire script
- **"MySQL server has gone away"**: This is what mysqli returns when read timeout expires - expected behavior, not a bug
- **Timeout triggers .fail()**: jQuery AJAX `.fail()` handler fires on timeout, so handle errors there (show L9, display error message)
- **Three-level timeout precedence chain**: per-host DB value (NULL = not set) → site-wide `<timeouts>` in XML → hardcoded fallback (4s connect / 8s read). Applies to MySQL, PostgreSQL (connect_timeout in DSN), and Redis (phpredis connect() call). Resolved in AJAXgetaql.php: `$resolved = $hostTimeout ?? $config->getConnectTimeout()`
- **Nullable INT column for per-host overrides**: NULL means "use the site-wide default", not "no timeout". Column COMMENTs should explain WHY null is meaningful (e.g., "set per-host for WAN or slow targets"), not just what null means — this context helps future DBAs and schema reviewers

### Redis/phpredis Patterns
- `$redis->info()` returns basic sections but NOT commandstats - use `$redis->info('commandstats')` separately
- CLIENT LIST returns array of client objects with keys: id, addr, name, age, idle, db, cmd, flags
- Version strings are just numbers (e.g., "7.2.4") - no "Redis" prefix, unlike MariaDB which includes "MariaDB" in version
- **MEMORY STATS returns strings**: Values like `fragmentation` come back as strings (e.g., `"9.92"`), not numbers. Use `parseFloat()` in JS before calling `.toFixed()` or comparisons fail silently.
- **Fragmentation ratio misleading on small instances**: A 10:1 ratio on 1MB = only 9MB wasted (harmless). Check absolute bytes (`usedMemoryRss - usedMemory > 100MB`) instead of ratio for alerts.
- **phpredis scan() signature**: Doesn't accept associative array options. Use `$redis->rawCommand('SCAN', $cursor, 'TYPE', 'stream', 'COUNT', '100')` instead.
- **evicted_keys is cumulative**: Counter since Redis instance start (or CONFIG RESETSTAT). Not useful for real-time alerting - use sessionStorage delta tracking instead.

### Version String Patterns
- MySQL: Returns plain version like "8.4.6" (no type indicator)
- MariaDB: Returns version with suffix like "10.5.18-MariaDB" (includes type)
- PostgreSQL: Handler extracts and prefixes as "PG 16.13" (from verbose `version()` output)
- Redis: Returns plain version like "7.2.4" (no type indicator)
- Use regex `/[a-zA-Z]/` to detect if version already contains a type indicator before prefixing

### AJAX Render Time Patterns
- **renderTimeData structure**: Server-side timing uses a structured object with per-phase breakdown
  - MySQL phases: `dispatch`, `connect`, `globalStatus`, `lockDetection`, `processlist`, `lockResolution`, `replication`, `total`
  - Redis phases: `dispatch`, `connect`, `info`, `commandStats`, `slowlog`, `clients`, `memoryStats`, `latency`, `pubsubStreams`, `total`
- **Client-side timing**: `Date.now()` before AJAX, calculate in `.done()` callback. Network = total - server.
- **Phase timing pattern in PHP**: `$phaseStart = microtime(true)` before phase, `$renderTimeData['name'] = round((microtime(true) - $phaseStart) * 1000, 1)` after

### User Preferences
- Remove completed todos from `todo.php` (don't keep them marked done)
- Commit messages should be descriptive but concise
- Center-align numeric columns in tables
- Friendly time format: "34052 (9h,27m,32s)"

