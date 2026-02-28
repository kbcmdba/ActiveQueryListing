# AQL Decisions Made

Architectural and design decisions that aren't documented elsewhere.
This file serves as institutional memory for current and future contributors.

## Task Tracking

### todo.php vs rfe.php
- **todo.php**: Changes that are accepted in planning - active, prioritized work
- **rfe.php**: Proposed/requested changes that have not been roadmapped yet - the "someday-maybe" list
- When a feature gets prioritized, it moves from rfe.php to todo.php

## Version Strategy

### v3 - Complete Redis, Polish PHP Codebase
- **Goal**: Finish Redis monitoring capabilities (todo 20-xx series)
- **Goal**: Enhance UI/UX (Settings dropdown, navigation, render times)
- **Goal**: Stabilize and polish the PHP codebase for production use
- **Stack**: PHP + jQuery (current)

### v3.x - Expand DB Platform Support
- **Goal**: Add monitoring for additional database platforms beyond MySQL/Redis
- **Candidates**: MS-SQL, PostgreSQL, Oracle, Oracle RAC, MongoDB, Cassandra, and others
- **Decision**: Not committing to specific platforms yet - depends on cost, access to real environments, and production DBA relevance
- **Constraint**: MySQL and MS-SQL available at work; PostgreSQL available in Proxmox cluster for dev/test but not production-representative
- **Learning goal**: Each new platform teaches us what patterns work and what needs to change for v4

### v4 - Rewrite with TDD/BDD
- **Goal**: Rewrite AQL, potentially in Node.js, using TDD/BDD methodology
- **Approach**: Before starting v4, Claude catalogs all features, decisions, and lessons learned from v2-v3
- **Not the last version**: v4 is not the end of the series - it's a foundation for continued evolution
- **Stack**: Under consideration (Node.js leading candidate, but not committed)

### Server-Side Data Gathering (rfe 010)
- **Decision**: Deferred to v3.9x at earliest, possibly v4.9x
- **Rationale**: Major architectural shift that enables historical tracking (seconds behind master graphs, user count trends, etc.) but needs the right foundation first
- **Benefit**: Reduces N browser AJAX calls to 1 central poller, enables server-side recording and trending

## Architecture Decisions

### Browser-Based Polling (Current, v2-v3)
- Each browser tab independently polls all monitored hosts via AJAX
- Simple architecture, no server-side state beyond MySQL config
- Trade-off: N browsers x M hosts = N*M connections to monitored servers
- Works well for small-to-medium deployments with few concurrent viewers

### Progressive AJAX Loading Pattern
- Fire all AJAX requests independently (not $.when() which blocks on all)
- Each response processed immediately in .done() callback
- Track completion with counter, finalize when all complete (success OR failure)
- Failed hosts show errors without blocking other hosts

### Settings in Navbar Dropdown
- Moved refresh interval, debug options, and auto-refresh toggle into Settings dropdown
- Host selection stays in header table (too large for dropdown)
- Mute controls stay in header table (complex, needs visibility)
- Hidden form fields sync between Settings dropdown and host form

### AQL Navigation with Flyout Submenus
- AQL dropdown organized with Noteworthy/Full as flyout submenus
- Reduces visual overload while providing direct navigation to every section
- Redis sections conditionally included based on redisEnabled config

### tablesorter Summary Rows
- Summary/total rows go in `<tfoot>`, never `<tbody>`
- tablesorter re-sorts `<tbody>` but leaves `<tfoot>` fixed at bottom

### CSS Theme System
- Dark mode default, light mode via `.theme-light` class
- CSS variables for all colors - never hardcode colors
- Gold/yellow for dark mode accent, blue for light mode accent

### Two Auto-Refresh Toggle Functions (Resolved)
- Had duplicate: `togglePageRefresh()` in common.js and `toggleAutoRefresh()` in index.php
- Resolved: removed `togglePageRefresh()`, unified on `toggleAutoRefresh()` in navbar Settings dropdown
