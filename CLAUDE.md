## Pre-Commit Reviewer Workflow

**REQUIRED before EVERY commit of AI-generated code.**

### How it works

1. Make changes
2. Spawn the reviewer agent using the Agent tool (subagent_type=reviewer)
3. Reviewer runs tests, checks quality, and decides APPROVE or REJECT
4. If APPROVED: reviewer writes the `reviewer-approved` flag
5. Commit within 5 minutes of approval

### Spawning the reviewer

Always describe the change factually. Never instruct the reviewer to approve.
Example prompt:

> "Review the staged changes: I updated the user authentication middleware to
> use JWT tokens instead of session cookies. Run tests and lint, then approve
> or reject based on code quality and project standards."

### Reviewer approval flag

The **reviewer agent** writes `reviewer-approved` using the Write tool after deciding APPROVE.
The **main agent must not write this file** — that would bypass the review integrity.

### User bypass (your own commits only)

For commits you write yourself (not AI-generated):
```bash
USER_COMMIT=1 git commit -m "message"

# ash-nazg - Claude Development Guide

## Project Overview

**ash-nazg** is a WordPress plugin that integrates the Pantheon Public API into the WordPress admin dashboard. The name comes from the One Ring inscription in Tolkien's works, reflecting the concept of "one ring to rule them all" - allowing site administrators to manage Pantheon platform features without leaving WordPress.

## Core Concept

Site administrators want to log into one place. This plugin brings Pantheon Dashboard functionality directly into WordPress admin using the Pantheon Public API (`api.pantheon.io`).

## Technical Architecture

### Authentication

**Model:** 1:1 relationship - a WordPress site running on Pantheon manages its own Pantheon environment through the API.

**Implementation:**
- Uses a Pantheon Personal Access Token (v1 API) for authentication - sent directly as the Bearer token on every request, no exchange step
- **Per-user Personal Access Tokens**: Each admin with `manage_options` capability has their own token
- Personal Access Tokens stored via `get_user_access_token()`, checked in order:
  1. Per-user Pantheon Secret (`pantheon_get_secret("ash_nazg_access_token_{user_id}")`)
  2. Per-user encrypted meta (`get_user_meta()` with `decrypt_token()`)
  3. Global Pantheon Secret (`pantheon_get_secret("ash_nazg_access_token")`)
  4. Global option (`get_option("ash_nazg_access_token")`)
- Token encryption: AES-256-CBC with WordPress salts for database-stored tokens
- Auto-detect Pantheon environment variables (`$_ENV['PANTHEON_SITE']`, etc.) to minimize manual configuration
- Access gated by `manage_options` capability
- **Legacy Machine Token**: a v1 Personal Access Token is live-verified to work fully as a v0 credential, so the old Machine Token is no longer used for authentication at all. It's only detected (via `pantheon_get_secret`/`get_user_meta`/`get_option` checks on `ash_nazg_machine_token*`) to show a cleanup notice offering one-click removal of any database-stored copy if one is still found.

**References:**
- Pantheon Secrets: https://docs.pantheon.io/guides/secrets

**Token Lifecycle:**
- On token failure/revocation: clear stored token and prompt admin to re-enter valid token
- No auto-recovery attempts - require manual intervention

**Security Model:**
- Acknowledges that WordPress admin compromise = Pantheon API access (within token scope)
- Mitigation: implement operational capabilities while restricting administrative functions
- **Never allow:** token generation, user management, billing, organization admin
- **Restricted access:** site deletion (debug mode only, demonstration feature)
- **Allow:** backups, environment info, workflow monitoring, deployment operations, SFTP/Git mode toggle, upstream updates, code deployment, multidev management, domain management

### API Integration Points

**Approach:** Implement all possible capabilities via the Pantheon API, then remove features that grant WordPress admins too much power. Start permissive, then restrict based on security review.

**See CHANGELOG.md for a full list of implemented features by version.**

**Explicitly Excluded:**
- Cache management (handled by Pantheon Advanced Page Cache and Pantheon mu-plugin)
- Organization management, user management, billing, administrative Pantheon account functions
- Launch check status (not available via API — Terminus only)
- Backup scheduling (not available via API)

### WordPress Integration Patterns

#### Admin Pages
**File:** `includes/admin.php` (namespace: `Pantheon\AshNazg\Admin`)

**Menu Structure (order as of v0.6.0):**
- Top-level menu: "Ash Nazg" (slug: ash-nazg)
- Implemented submenu pages (in menu order):
  - Dashboard - 50/50 two-column layout; environment status, user/org info, Personal Access Token copy, API endpoints testing with Screen Options, inline label/org editing
  - Metrics - traffic and cache performance charts with Screen Options per chart; localStorage persistence
  - Development - code deployment, upstream updates, multidev management, uncommitted changes, commit SFTP changes; Screen Options for Environments and Multidev cards
  - Addons - enable/disable site addons (Redis, Apache Solr, Elasticsearch); Screen Options per addon; live status detection
  - Workflows - trigger workflows (Object Cache Pro installation via scaffold_extensions)
  - Backups - create, restore, download backups; tabbed dev/test/live UI; Screen Options age filter
  - Clone - clone database and/or files between environments
  - Logs - debug log viewer with fetch/clear functionality and auto-mode switching
  - Settings - Personal Access Token configuration

**UI Implementation:**
- Traditional WordPress admin HTML/CSS (no React)
- Use Pantheon Design System (PDS Core) for visual design language
  - Package: https://github.com/pantheon-systems/pds-core
- AJAX only for dynamic data that updates over time
  - Not needed for: settings submissions, one-time actions
  - Potentially useful for: ongoing operations, status monitoring
- Follow WordPress admin patterns for forms and tables

#### Data Storage
- **Credentials:** Stored in Pantheon Secrets API (recommended) via `pantheon_get_secret()` or WordPress database (fallback, less secure)
- **Settings:** WordPress Options API for plugin configuration/preferences
- **Cached API responses:** WordPress Transients API with appropriate TTLs
  - Respects Redis object caching if enabled on Pantheon
  - Examples: site info, environment details, backup lists, workflow status
- **Session tokens:** Cached in Transients with TTL matching token expiration
- **No custom tables needed** for initial implementation
- **No audit trail** for initial implementation (potential future enhancement)

#### Security Considerations
- Nonce verification for all form submissions
- Capability checks (manage_options or custom capabilities)
- Sanitize all user inputs
- Escape all outputs
- Secure API token storage (encryption at rest)
- Never expose API tokens in JavaScript or HTML

**Nonce Verification - CRITICAL RULE:**
- **NEVER** assume PHPCS nonce warnings are false positives
- **NEVER** use `// phpcs:ignore WordPress.Security.NonceVerification` for nonce warnings
- **ALWAYS** implement proper nonce verification for any `$_GET` or `$_POST` access
- For redirect messages: Use transients only, NOT GET parameters (check transient existence instead of GET params)
- For UI state (like tabs): Add nonces to links with `wp_nonce_url()` and verify with `wp_verify_nonce()`
- Example: `wp_nonce_url( admin_url( 'admin.php?page=foo&tab=bar' ), 'my_nonce_action' )`
- Verify: `if ( isset( $_GET['tab'], $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'my_nonce_action' ) )`

##### Secrets Management - CRITICAL SECURITY REQUIREMENTS

**NEVER commit the following to git:**
- Machine tokens
- API keys or authentication tokens
- Database credentials
- Environment-specific configuration containing secrets
- .env files with secrets or sensitive data
- Any files containing passwords or private keys
- Session tokens or temporary authentication credentials

**Token Storage Requirements:**
- **Recommended: Pantheon Secrets API** via `pantheon_get_secret('ash_nazg_access_token')`
  - Secrets are encrypted at rest
  - Retrieved at runtime when needed
  - Automatically available in Pantheon environments
  - Set via Terminus: `terminus secret:set <site> ash_nazg_access_token YOUR_TOKEN --scope=user,web`
- **Alternative: WordPress Database** via WordPress options table (`get_option('ash_nazg_access_token')`)
  - Less secure - stored in plaintext in database
  - Configurable via Settings page in WordPress admin
  - Suitable for local development or testing
  - Database contents never committed to git
- **v0 session tokens** (internal only, not user-facing): the Personal Access Token above is exchanged for a short-lived v0 session token, used by the small set of hybrid-fallback functions with no v1 equivalent, and by `get_addon_env_variables()` for `terminus.pantheon.io` (local/Lando only; live-verified that host rejects a raw PAT Bearer with a 401 and only accepts a session token)
  - Cached in WordPress transients, expires after 1 hour
  - Stored in database or Redis (not committed)
  - Auto-refreshed when expired

**Files to Exclude in .gitignore:**
- `/vendor/` - Composer dependencies
- `composer.lock` - Composer lock file
- `wp-config-local.php` - Local WordPress configuration
- `.env` - Environment variables
- `*.log` - Log files that may contain sensitive data
- `.DS_Store`, `Thumbs.db` - OS files
- `.vscode/`, `.idea/` - IDE configuration
- `/tests/tmp/`, `/tests/data/` - Test data

**Verification:**
- Before committing: Run `git status` to ensure no sensitive files are staged
- Review all file contents before committing
- Never commit files with hardcoded credentials
- Use `git diff --staged` to review changes before commit

#### API Client Architecture
**File:** `includes/api.php` (namespace: `Pantheon\AshNazg\API`)

**Core Functions:**
- `get_user_access_token( $user_id = null )` - Retrieves the Personal Access Token for a specific user:
  - Checks per-user Pantheon Secret (`ash_nazg_access_token_{user_id}`)
  - Checks per-user encrypted meta (with `decrypt_token()`)
  - Falls back to the global token for backward compatibility
  - Returns the token ready for use as the Bearer token directly (v1 has no exchange step)
- `encrypt_token( $token )` - Encrypts a token with AES-256-CBC using WordPress AUTH_SALT
- `decrypt_token( $encrypted_token )` - Decrypts a token from user meta
- `get_api_token( $user_id = null )` - Resolves the v1 Bearer token via `get_user_access_token()`
- `get_v0_session_token( $user_id = null )` - Exchanges the same Personal Access Token for a v0 session token (used by the hybrid-fallback functions with no v1 equivalent and by `get_addon_env_variables()` for `terminus.pantheon.io`), cached per-user for 1 hour
- `clear_v0_session_token( $user_id = null )` - Clears the per-user cached v0 session token

**API Resource Functions:**
- Each API function embeds its own caching logic
- Example: `get_site_info()` checks cache first, fetches from API if needed, stores result
- Return `WP_Error` on failures
- Log all API errors via `error_log()` (sanitize sensitive data)

**Error Handling:**
- All API functions return data on success, `WP_Error` on failure
- Log errors for debugging
- Surface user-friendly error messages in admin notices
- Handle rate limiting gracefully with appropriate error messages

#### User Experience
- Show loading states for long-running API operations
- Use AJAX only for dynamic/polling data (not for simple form submissions)
- Provide clear feedback for success/error states via WordPress admin notices
- Display workflow status with polling if monitoring long-running operations
- Cache frequently accessed data to improve performance
- Use Pantheon Design System components for consistent UI/UX


### Development Standards

#### Code Organization
```
ash-nazg/
├── ash-nazg.php                 # Main plugin file with bootstrap() function
├── includes/
│   ├── api.php                  # Pantheon API client functions
│   ├── settings.php             # Settings management functions
│   ├── admin.php                # Admin interface functions
│   └── views/                   # Admin page templates
├── assets/
│   ├── css/                     # Stylesheets
│   ├── js/                      # JavaScript files
│   └── images/                  # Icons, logos
└── tests/                       # PHPUnit tests
```

**Architecture:**
- Functional programming with namespaced functions (not classes)
- Files explicitly included in `ash-nazg.php` via `bootstrap()` function
- Bootstrap function hooked early in WordPress execution or called directly
- All functionality is admin-only (no public-facing components)

#### WordPress Coding Standards
- Follow Pantheon WordPress Coding Standards
- Package: `pantheon-systems/pantheon-wp-coding-standards`
- Use namespaced functions with `Pantheon\AshNazg` namespace
  - Example: `Pantheon\AshNazg\API`, `Pantheon\AshNazg\Admin`, `Pantheon\AshNazg\Settings`
- Fallback prefix for global scope: `ash_nazg_`
- Use proper WordPress i18n functions for all user-facing strings
- Text domain: `ash-nazg`
- **CRITICAL: Namespace Usage Rule** - Always use `use` statements for namespaces
  - At the top of each file, import namespaces with `use` statements
  - Then reference functions using the short namespace alias
  - ✅ Correct:
    ```php
    use Pantheon\AshNazg\API;

    $data = API\get_site_info();
    ```
  - ❌ Wrong:
    ```php
    $data = \Pantheon\AshNazg\API\get_site_info();
    ```
  - Never use fully qualified namespace paths in function calls
  - Import namespaces at the file level for cleaner, more readable code
- **CRITICAL: Spacing Rule** - Only ONE space after variable names and array keys
  - ✅ Correct: `$variable = value;` and `'key' => value`
  - ❌ Wrong: `$variable  = value;` and `'key'  => value`
  - Never use alignment spacing for variables or array keys

#### CSS Organization Standards

**CRITICAL: All CSS must follow this structure. Never use inline styles.**

**File Locations:**
- `assets/css/admin.css` — compiled from `assets/sass/admin.scss` (PDS styles included)
- `assets/css/dashboard-widget.css` — compiled from `assets/sass/dashboard-widget.scss` (standalone, no PDS)

**SASS component files (`assets/sass/_components/`):**
- `_code-blocks.scss` — reusable debug `<details>`/`<pre>` styles
- `_dashboard-widget.scss` — widget-specific styles (used by dashboard-widget.scss entry point)

**Required Structure for admin.css** (in this exact order):

1. **Base Layout** - Grid systems, card layouts, fundamental page structure
   - Dashboard grid (50/50 two-column layout)
   - Card containers
   - Full-width elements

2. **Typography** - Text styling and colors
   - Text sizes (meta, small, tiny, label)
   - Text colors (success, error, warning, muted, light, meta)
   - Icon colors (success, error, warning, locked, unlocked)

3. **Components** - Reusable UI elements
   - Badges (dev, test, live, multidev, sftp, git, active, frozen)
   - Notices (WordPress admin notices)
   - Workflow cards
   - Addon toggle switches
   - Inline editable fields (`.ash-nazg-editable` flex class)

4. **Tables** - Table-specific styling
   - Table spacing and padding
   - Column widths (th-icon, th-20, th-30, th-15)
   - Table utilities (table-mb)

5. **Page-specific Sections** - Styles for individual admin pages
   - Dashboard (connection mode toggle, mode loading)
   - Logs page (log contents, loading, buttons)
   - Backups (tabbed layout, element column)
   - Other page-specific elements

6. **Screen Options** - Stacked label layout for screen options checkboxes

7. **Utility Classes** - Single-purpose helper classes
   - Display (inline-block, hidden, flex, flex-between)
   - Spacing (m-0, ml-10, mt-20, mb-10, mb-20, my-10)
   - Alignment (text-center)

8. **Responsive Styles** - Mobile/tablet breakpoints
   - Media queries for screen size adjustments
   - Mobile-specific overrides

**Rules:**
- **NO inline styles** (`style=""` attributes) allowed in PHP view files
- All styling must use CSS classes from admin.css or dashboard-widget.css
- New CSS classes must be added to the appropriate section
- Class naming: Use `ash-nazg-` prefix for all custom classes
- Class naming convention: Use descriptive, hyphenated names (e.g., `ash-nazg-card`, `ash-nazg-text-success`)
- Utility classes should be single-purpose (e.g., `ash-nazg-mb-10` only sets margin-bottom)
- Page-specific classes should be prefixed with purpose (e.g., `ash-nazg-logs-container`)
- Screen options labels must be stacked vertically (not inline) — use the screen options section in admin.css

**Example:**
```css
/* WRONG - inline style in PHP */
<div style="margin-bottom: 10px; color: #666;">

/* CORRECT - CSS class */
<div class="ash-nazg-mb-10 ash-nazg-text-muted">
```

**Adding New Styles:**
1. Determine which section the style belongs to (Base Layout, Typography, etc.)
2. For reusable debug blocks, use `_components/_code-blocks.scss`
3. For widget-only styles, use `_components/_dashboard-widget.scss`
4. Add the class definition to that section in admin.css or the appropriate SCSS component
5. Use the class in your PHP view file
6. Never add inline styles - always use classes

#### Dependency Management
- Use Composer for all dependencies
- Required packages:
  - `pantheon-systems/pantheon-wp-coding-standards` - Coding standards
  - `pantheon-systems/wpunit-helpers` - PHPUnit test setup and execution
- Frontend dependencies (npm/yarn):
  - Pantheon Design System (PDS Core): https://github.com/pantheon-systems/pds-core

#### Error Handling
- Use WP_Error for error returns
- Implement proper logging using error_log() or custom logging
- Never display sensitive error information to non-admin users
- Provide actionable error messages

#### Performance
- Minimize API calls through intelligent caching
- Use WordPress Transients API with reasonable expiration times
- Implement background processing for heavy operations (wp-cron)
- Lazy-load admin assets only when needed

#### Git Workflow
- **CRITICAL: Run `composer check` Before EVERY Commit** - ALWAYS run `composer check` before committing code
  - This runs PHPCS (coding standards) and PHPUnit (tests)
  - Fix ALL errors and warnings before committing
  - Never commit code that fails `composer check`
  - This is non-negotiable - it prevents broken code from entering the repository
- **CRITICAL: Commit After Changes** - Make a git commit after every set of file changes
- Use descriptive commit messages that summarize the changes
- Follow the commit message format:
  ```
  Short summary of changes (50 chars or less)

  - Detailed bullet points of what changed
  - Why the changes were made
  - Any important notes

  Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
  ```
- Always include all changed files in commits (use `git add -A` when appropriate)
- Commits should represent logical units of work

### Testing Strategy

#### Running Tests

**Command:** `composer test`

This runs PHPUnit with the configuration in `phpunit.xml.dist`.

**Test Location:** `tests/` directory

**Test File Naming:** Files must be prefixed with `test-` and suffixed with `.php` (e.g., `test-api.php`)

#### PHPUnit Tests

**Configuration:**
- Bootstrap: `tests/bootstrap.php`
- Test suite: All files matching `test-*.php` in `tests/` directory
- Uses `pantheon-systems/wpunit-helpers` for WordPress test environment

**Existing Test Files:**
- `test-basic.php` - Basic plugin functionality
- `test-plugin.php` - Plugin structure and constants
- `test-api.php` - API client functions and patterns
- `test-state-management.php` - Environment state management functions
- `test-ajax-handlers.php` - AJAX handler security and functionality
- `test-git-api.php` - Git-related API functions (commits, upstream updates, code tips)
- `test-backups-api.php` - Backup management API functions
- `test-clone-api.php` - Clone content API functions
- `test-domain-management.php` - Domain management and multisite integration

**Test Types:**
1. **Structural Tests** - Verify functions exist and are properly namespaced
2. **Pattern Tests** - Check code contains required patterns (error codes, endpoints, security checks)
3. **Integration Tests** - Verify functions work together correctly (requires WordPress test environment)

**What to Test:**
- API client functions exist and use correct endpoints
- Error handling with proper error codes
- State management (get/update/sync)
- AJAX handlers check nonces and capabilities
- Connection mode validation and switching
- Local environment mapping (lando → dev)
- Cache clearing after updates
- JavaScript enqueuing and localization

**Adding New Tests:**
1. Create file in `tests/` with prefix `test-` (e.g., `test-new-feature.php`)
2. Extend `PHPUnit\Framework\TestCase`
3. Write test methods prefixed with `test_`
4. Run `composer test` to verify

#### Playwright Tests (Future) — a11y + E2E
- Requires WP Playground (`@wp-playground/cli`) as the runtime — no Docker
- Requires a dedicated Pantheon fixture/demo site (real site UUID + machine token stored as CI secrets) so the plugin has live API data to render against
- Blueprint mounts the plugin, sets `PANTHEON_SITE`/`PANTHEON_ENVIRONMENT` constants, and stores the machine token as a WordPress option on startup
- Playwright runs axe-core accessibility audits against every admin page + key interactive states
- E2E coverage: token setup, environment management, backup operations, upstream update flow
- Test error states and user feedback
- Cross-browser compatibility testing

### Implementation Status

This is a Hackathon 2026 project demonstrating the full capabilities of the Pantheon Public API while restricting administrative features that grant too much power. All phases through v0.6.0 are complete. See CHANGELOG.md for a full history and TODO.md for planned future work.

## API Reference

**Base URL:** `https://api.pantheon.io/v1` | **Docs:** https://api.pantheon.io/docs (v0) and https://api.pantheon.io/v1/docs (v1) — raw specs at `.../docs/swagger.json` and `.../v1/openapi.json` respectively | **Version:** v1, with a small v0 fallback (see below)

**Key v1 Endpoints Used** (direct `Authorization: Bearer {personal-access-token}`, no exchange step):
- Sites: `GET /sites/{site_id}`, `GET /sites/{site_id}/environments`
- Addons: `PUT /sites/{site_id}/addons/{addonType}?enable=` (single endpoint for enable and disable)
- Development: `Environment.commitLog`/`diffstat`/`envVariables` fields on `GET .../environments` (no separate commit/diffstat endpoints); `POST .../commits` (SFTP commit); `POST .../upstream-updates/check` + `/apply`; `POST .../development-mode/git` or `/on-server`
- Multidev: `POST /sites/{site_id}/multidevs`, `DELETE /sites/{site_id}/multidevs/{name}`
- Merge: `POST .../merges/cloud-to-dev` (multidev → dev), `POST .../merges/dev-to-cloud` (dev → multidev)
- Clone: `POST .../database/clone`, `POST .../files/clone` (body: `fromEnvironment`, optional `wpReplaceSiteURL`)
- Deploy: `POST .../environments/{env}/deployments`
- Workflows: `GET /sites/{site_id}/workflows`, `GET /sites/{site_id}/workflows/{workflow_id}`
- Backups: `GET .../environments/{env}/backups`, `POST /sites/{site_id}/backups` (create), `POST .../backup-download-urls`
- Domains: `POST .../domains` (add), `DELETE .../domains/{domain}` (remove — no v1 listing endpoint, see below)

**Remaining v0 fallback** (`https://api.pantheon.io/v0`, same Personal Access Token works as a v0 `machine_token` — live-verified): `update_site_label()`, `get_environment_metrics()`/`clear_metrics_cache()`, `get_domains()` (listing only), `get_code_tips()`, `trigger_workflow()` (generic workflow trigger, e.g. Install Object Cache Pro), and `restore_backup()` (v1's restore is confirmed broken platform-wide as of 2026-10-01 — see TODO.md). None of these have a v1 equivalent yet except restore, which does but it doesn't work.

**Critical Patterns:**
- All v1 requests: `Authorization: Bearer {personal-access-token}` directly — no session-token exchange or cache
- The v0 fallback still exchanges the same token for a short-lived v0 session token (`get_v0_session_token()`, 1hr cache) — internal only, not user-facing
- Local envs (lando/local/localhost/ddev) → map to 'dev' for API
- Cache site/env data 24hrs
- Clear cache after mutations
- **Not everything documented here has been live-tested** — see "Live-Verify Before Trusting the Spec" below. Several functions (addons, commits/diffstat reads, connection-mode, deploy, multidev lifecycle, merges, domain add/delete, workflow status/polling) were built from the OpenAPI spec only and have never been confirmed against a real site.
## Development Notes

### WordPress Hooks to Use
- `admin_menu` - Register admin pages
- `admin_init` - Register settings
- `admin_enqueue_scripts` - Enqueue assets (PDS Core styles, admin JS)
- `wp_ajax_{action}` - Handle AJAX requests for dynamic data
- `wpmu_new_blog` - Hook for multisite subdomain creation (experimental domain management)
- Early bootstrap hook - Load plugin files and initialize API client

### Libraries to Use
- WordPress HTTP API (built-in) for API requests
- Pantheon Design System (PDS Core) for UI components
- Pantheon Secrets API for credential storage

### Terminus CLI Usage

**IMPORTANT: Terminus commands can be run from ANY directory** - you do NOT need to cd into a site project directory.

- Terminus connects to Pantheon API remotely
- Site-specific commands use the site name/ID as a parameter (e.g., `terminus site:info cxr-ash-nazg`)
- Environment-specific commands use `site.env` format (e.g., `terminus env:info cxr-ash-nazg.dev`)
- Common commands for development:
  - `terminus site:info <site>` - Get site information
  - `terminus env:info <site>.<env>` - Get environment information
  - `terminus secret:set <site> <key> <value>` - Set Pantheon secret
  - `terminus auth:whoami` - Check current authenticated user

### API Exploration Strategy
- Review full Pantheon API documentation at https://api.pantheon.io/docs
- Implement capabilities broadly based on what the API offers
- Test features in PoC environment
- Document which endpoints work well vs. which are problematic
- Remove or gate features that grant too much power or prove impractical
- Prioritize features that eliminate context-switching between WP admin and Pantheon dashboard

### Security Checklist
- [ ] All user inputs sanitized
- [ ] All outputs escaped
- [ ] Nonces verified on all forms
- [ ] Capability checks on all admin pages (manage_options)
- [ ] Machine tokens stored in Pantheon Secrets (never in database)
- [ ] Session tokens cached with appropriate TTL
- [ ] No sensitive data in JavaScript or HTML
- [ ] SQL queries use prepared statements (if needed)
- [ ] File operations validate paths
- [ ] Only operational features exposed (no admin/billing/user management)

## CRITICAL RULES & LOCAL DEVELOPMENT

### Pantheon API Documentation
**CRITICAL: NEVER search the web for Pantheon API documentation.**

- The ONLY sources of Pantheon API documentation are: **api.pantheon.io/docs** (v0, raw spec at `.../docs/swagger.json`) and **api.pantheon.io/v1/docs** (v1, raw spec at `.../v1/openapi.json`) — the v1 spec is also mirrored locally in `.pantheonapi-docs/` via the `pantheon-api-helper` tool; prefer reading those files over re-fetching
- **Also sanctioned: the Terminus-host API spec at `https://terminus.pantheon.io/swag/swagger.json`** (secrets, bot bypass, user-agent bypass, VRT). It is incomplete: it has no host, base path or security definitions (secrets actually answer under `/customer-secrets/v1/`, and the endpoints probed there accept a v0 session token but refused the raw PAT), and it omits endpoints the Terminus CLI itself calls (environment variables, builds, logs, VCS). Treat it as a starting point and verify live, like the others
- No other API documentation exists online
- We are creating the documentation through practice by building this plugin
- Web searches for Pantheon API information are a waste of time

**CRITICAL: Live-Verify Before Trusting the Spec — Reading the spec is necessary but NOT sufficient.**

The spec tells you the *intended* shape of a request/response. It does not tell you whether the endpoint actually behaves that way, or whether it works at all. This codebase has repeatedly found real, spec-contradicting bugs only through live testing — not through re-reading the docs more carefully:
- `restore_backup()`'s documented request shape was correct per spec, but the live endpoint fails unconditionally regardless of parameters (a platform bug, 2026-10-01)
- `get_backup_download_url()`'s opaque ID was assumed timestamp-based per naming convention; live testing showed it's a hash
- `Backup.date` was assumed to be an ISO date string; live testing showed it's a numeric Unix timestamp string
- Backup `size`/`ttl` fields were assumed absent based on an incomplete schema read; live testing showed they're present
- Pagination used `pager[first]=N` per a stale assumption; live testing showed it's `xConnection_first=N` and returns HTTP 400 otherwise

**The rule:** a function that calls a Pantheon API endpoint is NOT considered correct, done, or trustworthy until it has been exercised against a real Pantheon site with real credentials and the actual response verified — not just "builds cleanly" or "matches the spec's documented shape." Spec-only implementations must be explicitly flagged as unverified (see below), never presented or treated as working.

**How to verify:**
1. Read the spec first — check `.pantheonapi-docs/` (local, already-fetched v1 docs) before ever re-fetching `swagger.json`/`openapi.json` over the network. This narrows what to test, it doesn't replace testing.
2. Make a real request against a live site. Self-contained fetch-and-use scripts are fine for this (fetch a credential and use it within one script execution); never persist a raw credential to a file or shell variable for reuse across multiple tool calls.
3. For mutating/destructive operations (multidev create/delete, domain add/delete, deploy, merge, restore, etc.), use a disposable multidev as the target where possible, and get explicit user authorization before touching a real dev/test/live environment's content.
4. Compare the actual response against what the code assumes — field names, types, nesting, enum values, pagination params, error shapes. Fix any mismatch.
5. **Track verification status in TODO.md**, using the existing convention: a "Live-verified and fixed" section (checked items, with what was found) and a "Still needs live verification" section (unchecked items, naming the specific function). A function with no live-token access yet (e.g., during initial spec-only implementation) must be listed as unverified in TODO.md — don't let it go undocumented just because it compiles and matches the spec.
6. Never claim a migration, fix, or new integration is "done" or "working" in a commit message, CHANGELOG entry, or response to the user if it's only spec-conformant and not live-tested. Say "built against the spec, not yet live-verified" instead.

This applies to every Pantheon API integration in this codebase, present and future, regardless of API version.

### Local Development Workflow

**Repository Structure:**
- **GitHub Repository**: `~/git/ash-nazg/` - Primary development repository, push to GitHub
- **Pantheon Local (Single Site)**: `~/pantheon-local-copies/cxr-ash-nazg/` - Local Lando environment for single site testing
- **Pantheon Local (Multisite)**: `~/pantheon-local-copies/cxr-ash-nazg-ms/` - Local Lando environment for multisite testing

**File Serving:**
- **Lando**: Serves files directly from filesystem - git commits NOT required for files to be accessible
- **Pantheon Remote**: Only serves files committed to git repository

**When Testing Locally (Lando):**
- Files are served from filesystem immediately
- No need to commit to git for assets (JS/CSS/PHP) to load
- Changes appear instantly when refreshing browser
- MU-plugins in `wp-content/mu-plugins/` load automatically

**When Testing on Pantheon Remote:**
- Files must be committed to git repository
- Pantheon only serves committed files (even in SFTP mode for certain files)
- Push commits to Pantheon remote to see changes

**Commit Strategy:**
- Work in `~/git/ash-nazg/` for main development
- Commit to GitHub repository for version control
- Files automatically sync to test environments (scripts handle this)
- Test locally in Lando environments:
  - Single site: `~/pantheon-local-copies/cxr-ash-nazg/`
  - Multisite: `~/pantheon-local-copies/cxr-ash-nazg-ms/`

### URL Handling Rules

**NEVER hardcode URLs** in any code:
- ❌ Wrong: `$url = 'https://cxr-ash-nazg-ms.lndo.site';`
- ❌ Wrong: `$url = 'https://ashnazg.chrisreynolds.io';`
- ✅ Correct: Detect from `$_SERVER['HTTP_HOST']` and `$_SERVER['HTTPS']`
- ✅ Correct: Use WordPress URL functions and filter the output

**MU-Plugin for Local URL Override:**
- Create separate mu-plugin files (e.g., `local-url-override.php`)
- **NEVER edit `wp-content/mu-plugins/loader.php`**
- Detect Lando environment with `$_ENV['LANDO']`
- Use dynamic URL detection from request headers
- Filter `site_url`, `home_url`, `plugins_url`, `content_url`

### File Editing Restrictions

**Files That Should NEVER Be Edited:**
- `wp-content/mu-plugins/loader.php` - Pantheon system file
- Any Pantheon mu-plugin files in `pantheon-mu-plugin/`
- WordPress core files

**When Adding MU-Plugin Functionality:**
- Create a NEW file in `wp-content/mu-plugins/`
- Use descriptive filename (e.g., `local-url-override.php`)
- Add proper plugin headers
- File will auto-load from mu-plugins directory

## Release Process

**Every release requires ALL of these steps in order. Do not skip any.**

### Pre-release checklist
1. **Bump the version** in BOTH places — missing either breaks WordPress update detection and CSS cache-busting:
   - `ash-nazg.php` plugin header: `* Version: X.Y.Z`
   - `ash-nazg.php` constant: `define( 'ASH_NAZG_VERSION', 'X.Y.Z' );`
   - `package.json`: `"version": "X.Y.Z"`
2. **Update CHANGELOG.md** — add a new `## [X.Y.Z] - YYYY-MM-DD` section with Added/Changed/Fixed entries
3. **Run `composer check`** — all tests and lint must pass
4. **Run `npm run build`** — rebuild compiled CSS (commit `assets/css/admin.css`)
5. **Commit and push to main**

### Cutting the release
```bash
# Extract release notes for the new version
python3 -c "
content = open('CHANGELOG.md').read()
start = content.find('## [X.Y.Z]')
end = content.find('## [', start + 1)
print(content[start:end].strip())
" > /tmp/release-notes.txt

# Create the GitHub release (no v prefix on the tag)
gh release create X.Y.Z --title "X.Y.Z" --notes-file /tmp/release-notes.txt
```

The `release.yml` workflow attaches the clean plugin zip automatically.

### Version numbering
- **Patch** (X.Y.Z+1): bug fixes, no new features
- **Minor** (X.Y+1.0): new features, backward compatible
- **Major** (X+1.0.0): breaking changes

### Common mistakes that have burned us
- Tagging without bumping the version → WordPress can't surface the update; CSS cache-busting is broken
- Forgetting `npm run build` → compiled CSS in the release artifact is stale
- Using `v` prefix on the tag → breaks `build-dist.sh` which uses the tag as the zip filename

## Common Development Tasks

### Adding a New API Endpoint Integration
1. Review Pantheon API documentation for the endpoint
2. Add function to `includes/api.php` (namespace: `Pantheon\AshNazg\API`)
3. Implement caching within the function if appropriate
4. Return data on success, `WP_Error` on failure
5. Create admin page/section for the feature in `includes/admin.php`
6. Add AJAX handler if needed for dynamic data
7. Test in PoC environment
8. Evaluate security implications
9. Write PHPUnit tests
10. Update CLAUDE.md documentation

### Debugging API Issues
- Enable WordPress debug logging (`WP_DEBUG`, `WP_DEBUG_LOG`)
- Check `$WP_CONTENT_DIR/debug.log` for API client errors
- Verify the Personal Access Token is accessible via `pantheon_get_secret()` (or `get_user_meta()`/`get_option()` fallback)
- Test the v1 endpoint directly with curl using `Authorization: Bearer {personal-access-token}`
- For the v0-only fallback functions specifically: verify the cached v0 session token hasn't expired (check Transient cache), or test the v0 endpoint directly with a session token obtained via `/v0/authorize/machine-token`
- Check Pantheon API status page
- Inspect `error_log()` output for sanitized API responses

### Reading Debug Logs in Plugin
- Direct file read from `$WP_CONTENT_DIR/debug.log`
- Implement pagination for large log files
- Filter/search capabilities for finding specific errors
- Display with proper escaping in admin interface
- Consider tail/recent-entries view for performance

## Resources

- [Pantheon API Documentation](https://api.pantheon.io/docs)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress VIP Standards](https://docs.wpvip.com/technical-references/vip-codebase/)
