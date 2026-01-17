# ash-nazg - Claude Development Guide

## Project Overview

**ash-nazg** is a WordPress plugin that integrates the Pantheon Public API into the WordPress admin dashboard. The name comes from the One Ring inscription in Tolkien's works, reflecting the concept of "one ring to rule them all" - allowing site administrators to manage Pantheon platform features without leaving WordPress.

## Core Concept

Site administrators want to log into one place. This plugin brings Pantheon Dashboard functionality directly into WordPress admin using the Pantheon Public API (`api.pantheon.io`).

## Technical Architecture

### Authentication

**Model:** 1:1 relationship - a WordPress site running on Pantheon manages its own Pantheon environment through the API.

**Implementation:**
- Uses Pantheon machine tokens for API authentication
- **Per-user machine tokens**: Each admin with `manage_options` capability has their own token (v0.4.0+)
- Machine tokens stored via:
  - **Pantheon Secrets API** (recommended): `ash_nazg_machine_token_{user_id}` (e.g., `ash_nazg_machine_token_1`)
  - **WordPress user meta** (fallback): Encrypted with AES-256-CBC using WordPress AUTH_SALT
  - **Legacy global token**: Backward compatibility for pre-v0.4.0 installations
- Retrieved using `get_user_machine_token()` with fallback chain:
  1. Per-user Pantheon Secret (`pantheon_get_secret("ash_nazg_machine_token_{user_id}")`)
  2. Per-user encrypted meta (`get_user_meta()` with `decrypt_token()`)
  3. Global Pantheon Secret (`pantheon_get_secret("ash_nazg_machine_token")`)
  4. Global option (`get_option("ash_nazg_machine_token")`)
- Machine tokens exchanged for session tokens via `/v0/authorize/machine-token` endpoint
- Bearer token authentication for all subsequent API requests
- **Per-user session tokens**: Cached in Transients with user ID suffix (`ash_nazg_session_token_{user_id}`)
- Session token TTL: 1 hour (HOUR_IN_SECONDS)
- Token encryption: AES-256-CBC with WordPress salts for database-stored tokens
- Auto-detect Pantheon environment variables (`$_ENV['PANTHEON_SITE']`, etc.) to minimize manual configuration
- Access gated by `manage_options` capability
- Migration system: Admin notices and settings page button to migrate from global to per-user tokens

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

**Implemented Features:**

1. **Environment Information & Status**
   - ✅ Detect current Pantheon environment (dev/test/live/multidev/local)
   - ✅ Display environment status and metrics
   - ✅ Comprehensive API endpoints testing with status indicators
   - ✅ Local environment mapping (lando/local/localhost/ddev → dev for API queries)
   - ✅ Environment state management with persistent storage
   - ✅ Connection mode tracking (SFTP/Git) with automatic synchronization
   - ✅ Debug log viewer with fetch/clear functionality
   - ✅ Automatic mode switching for file operations (switches to SFTP when needed)
   - ⛔ Show launch check status information (not available via API - Terminus only)

2. **Site Information Management**
   - ✅ Inline editing of site label via dashboard
   - ✅ Real-time updates using AJAX with nonce verification
   - ✅ Pencil icon edit link with keyboard support (Enter to save, Escape to cancel)
   - ✅ API endpoint: PUT /v0/sites/{site_id}/label
   - ✅ Automatic cache invalidation after label update

3. **Site Addons Management**
   - ✅ Enable/disable Redis addon via API (PUT to enable, DELETE to disable)
   - ✅ Enable/disable Apache Solr addon via API
   - ✅ Local state tracking in WordPress options (API doesn't provide GET endpoint)
   - ✅ Toggle switches with save button interface
   - ✅ Auto-cache clearing on addon changes

4. **Workflows Integration**
   - ✅ Trigger `scaffold_extensions` workflow type
   - ✅ Object Cache Pro installation workflow (`install_ocp` job)
   - ✅ Environment validation (workflows only on dev/multidev/lando)
   - ✅ Workflow status retrieval after triggering
   - ✅ Workflow monitoring/polling for long-running operations
   - ⏳ Additional workflow types beyond scaffold_extensions (to be discovered)

5. **Development Workflow**
   - ✅ Toggle between SFTP mode and Git mode with AJAX interface
   - ✅ Polling verification to ensure mode changes complete before updating UI
   - ✅ Automatic state synchronization after mode changes
   - ✅ Loading indicators during mode switching operations
   - ✅ Detect available upstream updates with per-environment filtering
   - ✅ Apply upstream updates from WP admin
   - ✅ Push code to test/live environments (code deployment)
   - ✅ Create multidev environments
   - ✅ Merge multidev to dev and dev to multidev
   - ✅ Delete multidev environments
   - ✅ Commit SFTP changes with commit message
   - ✅ View uncommitted changes (diffstat)
   - ✅ Recent commits display

6. **Domain Management** (Multisite)
   - ✅ Hook into WordPress multisite subdomain creation
   - ✅ Automatically add new subdomains to Pantheon via API
   - ✅ Hooks: `wp_initialize_site` (WP 5.1+) and `wpmu_new_blog` (legacy)
   - ✅ Skip local environments automatically

7. **Backup Operations**
   - ✅ Backup creation (all, code, database, files)
   - ✅ Backup listing (catalog across all environments)
   - ✅ Backup restore operations
   - ✅ Backup download via signed URLs
   - ✅ Configurable retention period (keep_for parameter)
   - ⛔ Backup scheduling (not available via API)

8. **Clone Content**
   - ✅ Clone database between environments
   - ✅ Clone files between environments
   - ✅ Automatic WordPress URL search-replace
   - ✅ Environment initialization validation
   - ✅ Multi-workflow monitoring

9. **Delete Site** (Debug Mode Only)
   - ✅ Full site deletion via API
   - ✅ Only visible with `?debug=1` query parameter
   - ✅ Multiple confirmation dialogs
   - ✅ Type "DELETE" to enable button
   - ✅ Demonstration feature showing API capabilities

10. **Environment Metrics Visualization**
   - ✅ API endpoint: GET /v0/sites/{site_id}/environments/{env_id}/metrics
   - ✅ Duration parameters: 7d, 28d, 12w, 12m
   - ✅ Chart.js integration with Pantheon Design System colors
   - ✅ Three interactive line charts: Pages Served, Unique Visits, Cache Performance
   - ✅ Summary statistics with totals and cache hit ratio
   - ✅ Per-chart breakdowns with descriptions
   - ✅ Refresh button to clear cache and reload data
   - ✅ Expandable debug panels for API requests/responses
   - ✅ 1-hour cache TTL for metrics data
   - ✅ Chart.js stored in assets/js/libs/ directory
   - ✅ Responsive design with smooth curves and hover interactions

**Explicitly Excluded:**
- Cache management (handled by Pantheon Advanced Page Cache and Pantheon mu-plugin)
- Organization management
- User management
- Billing
- Administrative Pantheon account functions

### WordPress Integration Patterns

#### Admin Pages
**File:** `includes/admin.php` (namespace: `Pantheon\AshNazg\Admin`)

**Menu Structure:**
- Top-level menu: "Ash Nazg" (slug: ash-nazg)
- Implemented submenu pages:
  - Dashboard - environment status, site/environment info, connection mode toggle, comprehensive API endpoints testing, inline site label editing
  - Addons - enable/disable site addons (Redis, Apache Solr)
  - Workflows - trigger workflows (Object Cache Pro installation via scaffold_extensions)
  - Development - code deployment, upstream updates, multidev management, uncommitted changes, commit SFTP changes
  - Backups - create, restore, download backups across all environments
  - Clone - clone database and/or files between environments
  - Logs - debug log viewer with fetch/clear functionality and auto-mode switching
  - Settings - machine token configuration, session token management
  - Delete Site (debug mode only) - site deletion demonstration feature (visible only with `?debug=1`)

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
- **Recommended: Pantheon Secrets API** via `pantheon_get_secret('ash_nazg_machine_token')`
  - Secrets are encrypted at rest
  - Retrieved at runtime when needed
  - Automatically available in Pantheon environments
  - Set via Terminus: `terminus secret:set <site> ash_nazg_machine_token YOUR_TOKEN --scope=user,web`
- **Alternative: WordPress Database** via WordPress options table (`get_option('ash_nazg_machine_token')`)
  - Less secure - stored in plaintext in database
  - Configurable via Settings page in WordPress admin
  - Suitable for local development or testing
  - Database contents never committed to git
- **Session Tokens**: Cached in WordPress transients
  - Expire automatically after 1 hour
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
- `get_user_machine_token( $user_id = null )` - Retrieves machine token for a specific user:
  - Checks per-user Pantheon Secret (`ash_nazg_machine_token_{user_id}`)
  - Checks per-user encrypted meta (with `decrypt_token()`)
  - Falls back to global token for backward compatibility
  - Returns decrypted token ready for API use
- `encrypt_token( $token )` - Encrypts token with AES-256-CBC using WordPress AUTH_SALT
- `decrypt_token( $encrypted_token )` - Decrypts token from user meta
- `get_api_token( $user_id = null )` - Central authentication function:
  - Retrieves per-user machine token via `get_user_machine_token()`
  - Exchanges machine token for session token via `/v0/authorize/machine-token`
  - Caches session token in per-user Transient (`ash_nazg_session_token_{user_id}`)
  - Auto-refreshing session tokens when expired
  - Auto-clearing invalid tokens on 401/403 errors via `clear_user_session_token()`
- `clear_user_session_token( $user_id = null )` - Clears per-user cached session token

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

**File Location:** `assets/css/admin.css`

**Required Structure** (in this exact order):

1. **Base Layout** - Grid systems, card layouts, fundamental page structure
   - Dashboard grid
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

4. **Tables** - Table-specific styling
   - Table spacing and padding
   - Column widths (th-icon, th-20, th-30, th-15)
   - Table utilities (table-mb)

5. **Page-specific Sections** - Styles for individual admin pages
   - Dashboard (connection mode toggle, mode loading)
   - Logs page (log contents, loading, buttons)
   - Other page-specific elements

6. **Utility Classes** - Single-purpose helper classes
   - Display (inline-block, hidden, flex, flex-between)
   - Spacing (m-0, ml-10, mt-20, mb-10, mb-20, my-10)
   - Alignment (text-center)

7. **Responsive Styles** - Mobile/tablet breakpoints
   - Media queries for screen size adjustments
   - Mobile-specific overrides

**Rules:**
- **NO inline styles** (`style=""` attributes) allowed in PHP view files
- All styling must use CSS classes from admin.css
- New CSS classes must be added to the appropriate section
- Class naming: Use `ash-nazg-` prefix for all custom classes
- Class naming convention: Use descriptive, hyphenated names (e.g., `ash-nazg-card`, `ash-nazg-text-success`)
- Utility classes should be single-purpose (e.g., `ash-nazg-mb-10` only sets margin-bottom)
- Page-specific classes should be prefixed with purpose (e.g., `ash-nazg-logs-container`)

**Example:**
```css
/* WRONG - inline style in PHP */
<div style="margin-bottom: 10px; color: #666;">

/* CORRECT - CSS class */
<div class="ash-nazg-mb-10 ash-nazg-text-muted">
```

**Adding New Styles:**
1. Determine which section the style belongs to (Base Layout, Typography, etc.)
2. Add the class definition to that section in admin.css
3. Use the class in your PHP view file
4. Never add inline styles - always use classes

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
- `test-delete-site-api.php` - Delete site API function and AJAX handlers

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

#### Playwright Tests (Future)
- E2E tests for admin interface workflows
- Test user flows: token setup, environment management, backup operations
- Test error states and user feedback
- Cross-browser compatibility testing

### Implementation Status

**Development Philosophy:**
This is a Hackathon 2026 project that demonstrates the full capabilities of the Pantheon Public API. The approach was to implement comprehensive API functionality while restricting administrative features that grant too much power.

**Phase 1: Foundation & Core Status (Complete)**
- ✅ Plugin bootstrap and activation
- ✅ Composer setup with dependencies (coding standards, wpunit-helpers)
- ✅ Basic API client with authentication (`get_api_token()` function)
- ✅ Pantheon environment detection (`$_ENV` variables)
- ✅ Settings page with machine token configuration and session token management
- ✅ Environment status display (dev/test/live/multidev detection)
- ✅ Comprehensive API endpoints testing with status indicators
- ✅ Error/debug log viewer with fetch/clear functionality
- ⛔ Launch check status information (not available via API - Terminus only)

**Phase 2: Development Workflow Features (Complete)**
- ✅ SFTP/Git mode toggle with polling verification
- ✅ Upstream update detection and application with per-environment filtering
- ✅ Code deployment (deploy to test/live environments)
- ✅ Multidev creation, merge, and deletion
- ✅ Backup management (create, list, restore, download)
- ✅ Workflow status monitoring with polling
- ✅ Clone content between environments (database and/or files)
- ✅ Commit SFTP changes with commit message
- ✅ View uncommitted changes (diffstat)
- ✅ Recent commits display

**Phase 3: Build Pipeline & Design (Complete)**
- ✅ SASS build pipeline with Pantheon Design System (PDS Core) integration
- ✅ PDS fonts, design tokens, and foundations
- ✅ Branded Pantheon header with logo on all admin pages
- ✅ Organized CSS structure with utility classes (no inline styles)
- ✅ JavaScript organization (separate files with proper enqueuing)
- ✅ Build and watch mode npm scripts

**Phase 4: Advanced Features (Complete)**
- ✅ Domain management for WordPress multisite (automatic domain addition on subsite creation)
- ✅ Delete site functionality (debug mode only, demonstration feature)

**Future Enhancements:**
- Accessibility audit (WCAG compliance)
- JavaScript bundling and minification
- Playwright E2E tests
- More screen options integration

## API Reference

**Base URL:** `https://api.pantheon.io` | **Docs:** https://api.pantheon.io/docs | **Version:** `/v0/`

**Key Endpoints Used:**
- Auth: `POST /v0/authorize/machine-token` - Exchange machine token for session (1hr cache)
- Sites: `GET /v0/sites/{site_id}`, `PUT /v0/sites/{site_id}/label`, `GET /v0/sites/{site_id}/environments`
- Addons: `PUT /v0/sites/{site_id}/addons/{addon_id}` (enable), `DELETE` (disable)
- Git: `GET /v0/sites/{site_id}/upstream-updates`, `POST /v0/sites/{site_id}/environments/{env}/upstream/updates`
- Commits: `GET /v0/sites/{site_id}/environments/{env}/commits`, `POST .../code/commit`
- Multidev: `POST /v0/sites/{site_id}/environments`, `DELETE /v0/sites/{site_id}/environments/{env}`
- Merge: `POST /v0/sites/{site_id}/environments/dev/merge` (merge multidev → dev)
- SFTP/Git: `PUT /v0/sites/{site_id}/environments/{env}/connection-mode`
- Workflows: `GET /v0/sites/{site_id}/workflows`, `GET /v0/sites/{site_id}/workflows/{workflow_id}`
- Backups: `GET /v0/sites/{site_id}/environments/{env}/backups/catalog`

**Critical Patterns:**
- All requests: `Authorization: Bearer {session-token}`
- Local envs (lando/local/localhost/ddev) → map to 'dev' for API
- Cache site/env data 24hrs, session tokens 1hr
- Clear cache after mutations
- Per-environment upstream updates: filter site-wide results against env commits
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

- The ONLY source of Pantheon API documentation is: **api.pantheon.io/docs**
- No other documentation exists online
- We are creating the documentation through practice by building this plugin
- Web searches for Pantheon API information are a waste of time
- All API exploration should be done by testing endpoints directly

**API Endpoint Verification Workflow:**

Before using ANY Pantheon API endpoint, you MUST verify the endpoint structure and parameters using the swagger.json file:

1. **Get endpoint definition:**
   ```bash
   curl -s "https://api.pantheon.io/docs/swagger.json" | jq '.paths["/v0/sites/{site_id}/endpoint"]'
   ```

2. **Get request body schema (for POST/PUT requests):**
   ```bash
   curl -s "https://api.pantheon.io/docs/swagger.json" | jq '.definitions.SchemaName'
   ```
   (The schema name is found in the endpoint definition under `parameters[].schema.$ref`)

3. **Verify these details:**
   - HTTP method (GET, POST, PUT, DELETE)
   - Required vs optional parameters
   - Parameter types (string, boolean, integer)
   - Request body structure
   - Expected response format

4. **Never assume parameter names or structure** - always check the swagger.json first

**Example:**
```bash
# Check multidev creation endpoint
curl -s "https://api.pantheon.io/docs/swagger.json" | jq '.paths["/v0/sites/{site_id}/environments"].post'

# Get the CreateMultidevRequest schema
curl -s "https://api.pantheon.io/docs/swagger.json" | jq '.definitions.CreateMultidevRequest'
```

This workflow prevents errors from incorrect parameter names, missing required fields, or wrong data types.

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
- Verify machine token is accessible via `pantheon_get_secret()`
- Verify session token hasn't expired (check Transient cache)
- Test endpoint directly with curl using session token
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
