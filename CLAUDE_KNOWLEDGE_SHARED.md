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

## Project Overview

- **Type**: PHP web application for monitoring database servers (AQL - Active Query Listing)
- **Framework**: Custom PHP with jQuery frontend
- **Database**: MySQL for AQL's own data storage

## Supported DB Types

The `db_type` ENUM in the host table: MySQL, InnoDBCluster, MS-SQL, Redis, OracleDB, Cassandra, DataStax, MongoDB, RDS, Aurora

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
  - Use `verifyAQLConfiguration.php` to check environment setup

### CSS Patterns
- Use `rem` units, not `em`
- Alternating row styles (e.g., `level4-alt`) must match base styles (e.g., `level4`) for font-size
- Use CSS variables for theme support: `var(--text-primary)`, `var(--bg-tertiary)`, etc.
- For theme-compatible elements: `color: inherit; background: transparent;`

### JavaScript Patterns
- `friendlyTime()` in JS matches `Tools::friendlyTime()` in PHP
- Use `data-text` attribute for tablesorter numeric sorting with display text
- Local silencing uses `localStorage` (shared across tabs)
- Speech synthesis needs to re-check `checkMuted()` before firing (2-second delay)
- **jQuery $.when() gotcha**: With 2+ promises, results are wrapped as `[data, textStatus, jqXHR]` arrays. With 1 promise, data is passed directly. Handle both cases!
- **Callback dispatch**: When adding new handler functions (e.g., `redisCallback`), must dispatch to them from `myCallback()` - they won't be called automatically
- **Variable scope in callbacks**: Define variables like `hasIssues` before using them - undefined variables cause silent JS failures

### Redis/phpredis Patterns
- `$redis->info()` returns basic sections but NOT commandstats - use `$redis->info('commandstats')` separately
- CLIENT LIST returns array of client objects with keys: id, addr, name, age, idle, db, cmd, flags
- Version strings are just numbers (e.g., "7.2.4") - no "Redis" prefix, unlike MariaDB which includes "MariaDB" in version
- **MEMORY STATS returns strings**: Values like `fragmentation` come back as strings (e.g., `"9.92"`), not numbers. Use `parseFloat()` in JS before calling `.toFixed()` or comparisons fail silently.
- **Fragmentation ratio misleading on small instances**: A 10:1 ratio on 1MB = only 9MB wasted (harmless). Check absolute bytes (`usedMemoryRss - usedMemory > 100MB`) instead of ratio for alerts.
- **phpredis scan() signature**: Doesn't accept associative array options. Use `$redis->rawCommand('SCAN', $cursor, 'TYPE', 'stream', 'COUNT', '100')` instead.

### Version String Patterns
- MySQL: Returns plain version like "8.4.6" (no type indicator)
- MariaDB: Returns version with suffix like "10.5.18-MariaDB" (includes type)
- Redis: Returns plain version like "7.2.4" (no type indicator)
- Use regex `/[a-zA-Z]/` to detect if version already contains a type indicator before prefixing

### User Preferences
- Remove completed todos from `todo.php` (don't keep them marked done)
- Commit messages should be descriptive but concise
- Center-align numeric columns in tables
- Friendly time format: "34052 (9h,27m,32s)"

