# Project Instructions for Claude Code

## Git Workflow
- Do NOT push to GitHub - authentication is not configured for this repo
- Commits are fine, but never mention or suggest pushing - the user will handle that independently
- Never use `git push` commands
- Don't ask the user to push either - just commit and move on

## Todo Management
- The project uses `todo.php` to track tasks
- Done items are removed (not kept) to keep the file small
- Items are kept until completed, then deleted

## Project Overview
ActiveQueryListing2 (AQL) - PHP-based MySQL query monitoring tool that displays active queries across multiple servers in a color-coded format.

## Self-Improvement
**Update this file** with lessons learned during each session. When you discover something important about the codebase, patterns, gotchas, or user preferences - add it here. This helps us both work together more effectively over time.

### Lessons Learned

**Config Gotchas**
- After `git pull`, new required config parameters can break AQL with 500 errors
- Config file is `./aql_config.xml` (local to each instance, not `/etc/`)
- Test with `php index.php 2>&1 | head` to see runtime errors, not just syntax

**CSS Patterns**
- Use `rem` units, not `em`
- Alternating row styles (e.g., `level4-alt`) must match base styles (e.g., `level4`) for font-size
- Use CSS variables for theme support: `var(--text-primary)`, `var(--bg-tertiary)`, etc.
- For theme-compatible elements: `color: inherit; background: transparent;`

**JavaScript Patterns**
- `friendlyTime()` in JS matches `Tools::friendlyTime()` in PHP
- Use `data-text` attribute for tablesorter numeric sorting with display text
- Local silencing uses `localStorage` (shared across tabs)
- Speech synthesis needs to re-check `checkMuted()` before firing (2-second delay)

**User Preferences**
- Remove completed todos from `todo.php` (don't keep them marked done)
- Commit messages should be descriptive but concise
- Center-align numeric columns in tables
- Friendly time format: "34052 (9h,27m,32s)"
