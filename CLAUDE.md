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

