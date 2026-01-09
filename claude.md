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
- One site-wide machine token (not per-user), typically from a "machine user" or service account in Pantheon
- Machine tokens stored via Pantheon Secrets API (never in database)
- Retrieved using `pantheon_get_secret()` function (natively available on Pantheon)
- Machine tokens are exchanged for session tokens via `/v0/authorize/machine-token` endpoint
- Bearer token authentication for all subsequent API requests
- Session tokens cached in Transients (with appropriate TTL)
- Auto-detect Pantheon environment variables (`$_ENV['PANTHEON_SITE']`, etc.) to minimize manual configuration
- Access gated by `manage_options` capability

**References:**
- Pantheon Secrets: https://docs.pantheon.io/guides/secrets

**Token Lifecycle:**
- On token failure/revocation: clear stored token and prompt admin to re-enter valid token
- No auto-recovery attempts - require manual intervention

**Security Model:**
- Acknowledges that WordPress admin compromise = Pantheon API access (within token scope)
- Mitigation: start by implementing all API capabilities, then restrict based on security review
- **Never allow:** token generation, user management, billing, organization admin, site deletion
- **Allow:** backups, environment info, workflow monitoring, deployment operations, SFTP/Git mode toggle, upstream updates, code deployment

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
   - ⏳ Show launch check status information (planned)

2. **Site Addons Management**
   - ✅ Enable/disable Redis addon via API (PUT to enable, DELETE to disable)
   - ✅ Enable/disable Apache Solr addon via API
   - ✅ Local state tracking in WordPress options (API doesn't provide GET endpoint)
   - ✅ Toggle switches with save button interface
   - ✅ Auto-cache clearing on addon changes

3. **Workflows Integration**
   - ✅ Trigger `scaffold_extensions` workflow type
   - ✅ Object Cache Pro installation workflow (`install_ocp` job)
   - ✅ Environment validation (workflows only on dev/multidev/lando)
   - ✅ Workflow status retrieval after triggering
   - ⏳ Additional workflow types beyond scaffold_extensions (to be discovered)
   - ⏳ Workflow monitoring/polling for long-running operations (planned)

4. **Development Workflow**
   - ✅ Toggle between SFTP mode and Git mode with AJAX interface
   - ✅ Polling verification to ensure mode changes complete before updating UI
   - ✅ Automatic state synchronization after mode changes
   - ✅ Loading indicators during mode switching operations
   - ⏳ Detect available upstream updates (planned)
   - ⏳ Apply upstream updates from WP admin (planned)
   - ⏳ Push code to test/live environments (planned)
   - ⏳ Create multidev environments (planned)

5. **Domain Management** (Experimental/PoC)
   - ⏳ Hook into WordPress multisite subdomain creation
   - ⏳ Automatically add new subdomains to Pantheon via API

6. **Backup Operations** (Planned)
   - ⏳ Backup creation and management
   - ⏳ Backup scheduling
   - ⏳ Restore operations (if safe to expose)

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
  - Dashboard - environment status, site/environment info, connection mode toggle, comprehensive API endpoints testing
  - Logs - debug log viewer with fetch/clear functionality and auto-mode switching
  - Addons - enable/disable Pantheon site addons (Redis, Solr)
  - Workflows - trigger Pantheon workflows (scaffold_extensions for Object Cache Pro installation)
  - Settings - machine token configuration
- Planned submenu pages:
  - Development - upstream updates, code deployment (SFTP/Git toggle now on Dashboard)
  - Backups - backup management
  - Multisite Domains - domain management (experimental, if viable)

**UI Implementation:**
- Traditional WordPress admin HTML/CSS (no React)
- Use Pantheon Design System (PDS Core) for visual design language
  - Package: https://github.com/pantheon-systems/pds-core
- AJAX only for dynamic data that updates over time
  - Not needed for: settings submissions, one-time actions
  - Potentially useful for: ongoing operations, status monitoring
- Follow WordPress admin patterns for forms and tables

#### Data Storage
- **Credentials:** Never stored in database - use Pantheon Secrets API via `pantheon_get_secret()`
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
- **Production**: MUST use Pantheon Secrets API via `pantheon_get_secret('ash_nazg_machine_token')`
  - Secrets are encrypted at rest
  - Never stored in WordPress database
  - Retrieved at runtime when needed
  - Automatically available in Pantheon environments
- **Local Development**: Fallback to WordPress options table (`get_option('ash_nazg_machine_token')`)
  - Only for local development environments
  - Still never committed to git
  - Stored in database which is not tracked in git
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
- `get_api_token()` - Central function that handles:
  - Retrieving machine token from Pantheon Secrets via `pantheon_get_secret()`
  - Exchanging machine token for session token via `/v0/authorize/machine-token`
  - Caching session token in Transients with appropriate TTL
  - Auto-refreshing session tokens when expired

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

### Phase 2 Features - Detailed Documentation

#### Environment State Management

**Purpose**: Track and persist Pantheon environment state in WordPress to minimize API calls and provide consistent data across page loads.

**Implementation** (`includes/api.php`):
- **Storage**: WordPress options table (`ash_nazg_environment_state`)
- **State Structure**:
  ```php
  array(
      'site_id' => 'abc-123-def',
      'environment' => 'dev',
      'connection_mode' => 'git',
      'last_synced' => 1234567890,
  )
  ```
- **Functions**:
  - `get_environment_state()` - Retrieve current state
  - `update_environment_state($updates)` - Update specific fields
  - `sync_environment_state()` - Fetch fresh data from API and update state
  - `get_connection_mode()` - Get current SFTP/Git mode
  - `update_connection_mode($site_id, $env, $mode)` - Change connection mode via API

**Key Patterns**:
- State is only updated after verifying changes complete (e.g., after polling confirms mode switch)
- Local environment names (lando, local, localhost, ddev) map to 'dev' for API queries
- Cache invalidation after state changes to ensure UI reflects current state

#### SFTP/Git Mode Toggle

**Purpose**: Allow WordPress admins to switch between SFTP and Git mode without leaving WordPress admin.

**Implementation** (`includes/admin.php`):
- **Location**: Dashboard page, inline with Connection Mode display
- **UI Pattern**: Button with icon (dashicons) that changes based on current mode
- **AJAX Handler**: `ajax_toggle_connection_mode()`
- **API Endpoint**: `PUT /v0/sites/{site_id}/environments/{env}/connection-mode`
- **Request Body**: `{ "mode": "sftp" }` or `{ "mode": "git" }`

**Verification Pattern**:
- After initiating mode change via API, poll to verify completion
- Poll every 2 seconds, max 10 attempts (20 seconds total)
- Check `on_server_development` field in environment info:
  - `true` = SFTP mode
  - `false` = Git mode
- Only update stored state after verification succeeds
- Show loading indicator during verification
- Display error if verification times out

**Code Flow**:
1. User clicks "Switch to Git Mode" button
2. JavaScript sends AJAX request with nonce and desired mode
3. PHP handler initiates API request to change mode
4. PHP polls API every 2s to verify mode changed
5. After verification, update state and return success
6. JavaScript reloads page to show updated state

**Files**:
- `includes/admin.php` - AJAX handler with verification loop
- `includes/api.php` - API request function
- `assets/js/dashboard.js` - Client-side AJAX handling
- `includes/views/dashboard.php` - UI display

#### Debug Log Viewer

**Purpose**: Read and display WordPress debug.log contents without SSH access, with ability to clear logs.

**Implementation** (`includes/admin.php` and `includes/views/logs.php`):
- **Location**: Logs submenu page
- **File Read**: Direct read from `WP_CONTENT_DIR/debug.log`
- **Cache**: Transient `ash_nazg_debug_logs` (24 hour expiration)
- **Timestamp**: Transient `ash_nazg_debug_logs_timestamp` for "Last fetched" display

**Features**:
1. **Fetch Logs**:
   - AJAX handler: `ajax_fetch_logs()`
   - Switches to SFTP mode if currently in Git mode (file not readable in Git mode)
   - Reads debug.log file
   - Stores in transient cache
   - Switches back to original mode
   - Returns log contents or empty message

2. **Clear Logs**:
   - AJAX handler: `ajax_clear_logs()`
   - Switches to SFTP mode if needed
   - Deletes debug.log file with `unlink()`
   - Verifies deletion with `file_exists()`
   - Switches back to original mode
   - Updates transient with empty string (not deleted)
   - Returns success/error

**Auto-Mode Switching**:
- Debug.log is only accessible in SFTP mode
- If site is in Git mode when fetching/clearing:
  1. Store original mode ('git')
  2. Switch to SFTP mode via API
  3. Wait 2 seconds for mode change
  4. Perform file operation
  5. Switch back to Git mode
  6. Display message indicating mode was temporarily switched

**Files**:
- `includes/admin.php` - AJAX handlers (`ajax_fetch_logs`, `ajax_clear_logs`)
- `assets/js/logs.js` - Client-side AJAX handling
- `includes/views/logs.php` - UI with fetch/clear buttons and log display
- `assets/css/admin.css` - Log display styling (`.ash-nazg-log-contents`)

**Security**:
- Nonce verification on all AJAX requests
- Capability check: `manage_options`
- File path validation (only reads from WP_CONTENT_DIR)
- Output escaping with `esc_html()`

#### JavaScript Organization

**Pattern**: All JavaScript in separate files, enqueued properly with WordPress APIs.

**Files**:
- `assets/js/dashboard.js` - Connection mode toggle functionality
- `assets/js/logs.js` - Fetch/clear logs functionality

**Enqueuing** (`includes/admin.php`):
```php
wp_enqueue_script(
    'ash-nazg-dashboard',
    plugins_url( 'assets/js/dashboard.js', ASH_NAZG_PLUGIN_FILE ),
    array( 'jquery' ),
    ASH_NAZG_VERSION,
    true
);

wp_localize_script(
    'ash-nazg-dashboard',
    'ashNazgDashboard',
    array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'toggleModeNonce' => wp_create_nonce( 'ash_nazg_toggle_connection_mode' ),
        'i18n' => array(
            'toggleError' => __( 'Failed to toggle connection mode.', 'ash-nazg' ),
            'ajaxError' => __( 'AJAX request failed.', 'ash-nazg' ),
        ),
    )
);
```

**Benefits**:
- Clean separation of concerns (PHP handles data, JS handles interactivity)
- Proper nonce passing via `wp_localize_script`
- i18n support through localized strings
- Easier debugging and maintenance
- No inline `<script>` tags in PHP view files

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

### Initial Implementation Priorities

**Development Philosophy:**
- Build PoC (Proof of Concept) with all possible API capabilities
- Test what works and what's useful
- Remove/hide features that grant too much power or prove impractical
- Iterate based on real-world usage and security review

**Phase 1: Foundation & Core Status**
- Plugin bootstrap and activation
- Composer setup with dependencies (coding standards, wpunit-helpers)
- Basic API client with authentication (`get_api_token()` function)
- Pantheon environment detection (`$_ENV` variables)
- Settings page (minimal - may not need much beyond token setup helper)
- Environment status display (dev/test/live/multidev detection)
- Launch check status information display
- Error/debug log viewer (read `$WP_CONTENT_DIR/debug.log`)

**Phase 2: Development Workflow Features**
- SFTP/Git mode toggle
- Upstream update detection and application
- Code deployment (push to test/live)
- Multidev creation
- Backup management (create, list, restore if safe)
- Workflow status monitoring with polling

**Phase 3: Experimental/Advanced**
- Domain management for multisite (PoC/experimental)
  - Hook into `wpmu_new_blog` or similar
  - Test API capability to add domains
  - Evaluate feasibility
- Additional API capabilities as discovered
- Security review and feature restriction
- Performance optimization and caching refinement

**Post-PoC:**
- Playwright E2E tests
- Security hardening based on Phase 3 review
- Documentation
- Feature flags for experimental capabilities

## API Reference

**Base URL:** `https://api.pantheon.io`

**Documentation:** https://api.pantheon.io/docs

**API Version:** All endpoints use `/v0/` (not `/v1/`)

### Authentication

#### `POST /v0/authorize/machine-token`
- **Purpose**: Exchange machine token for session token
- **Method**: POST
- **Request Body**:
  ```json
  {
    "machine_token": "your-machine-token",
    "client": "ash-nazg"
  }
  ```
- **Response**: `{ "session": "session-token-string", ... }`
- **Note**: `client` parameter is required
- **Cache**: Session token cached for 1 hour in transient `ash_nazg_session_token`

### Sites

#### `GET /v0/sites/{site_id}`
- **Purpose**: Get basic site details and metadata
- **Parameters**: `site_id` - Site UUID
- **Cache**: 5 minutes
- **Returns**: Site name, framework, created date, organization info

#### `GET /v0/sites/{site_id}/environments`
- **Purpose**: List all environments (dev, test, live, multidevs)
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of environment objects

#### `GET /v0/sites/{site_id}/memberships/users`
- **Purpose**: Site team members and their roles
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of user membership objects

#### `GET /v0/sites/{site_id}/memberships/organizations`
- **Purpose**: Organizations associated with this site
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of organization membership objects

#### `GET /v0/sites/{site_id}/plan`
- **Purpose**: Current plan and pricing information
- **Parameters**: `site_id` - Site UUID
- **Returns**: Plan details including SKU and features

#### `GET /v0/sites/{site_id}/available-plans`
- **Purpose**: Plans available for upgrade/downgrade
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of available plan objects

#### `PUT /v0/sites/{site_id}/addons/{addon_id}`
- **Purpose**: Enable a specific addon
- **Method**: PUT
- **Parameters**:
  - `site_id` - Site UUID
  - `addon_id` - Addon identifier ('redis', 'solr')
- **Note**: Use PUT to enable, DELETE to disable
- **Known addons**: redis, solr

#### `DELETE /v0/sites/{site_id}/addons/{addon_id}`
- **Purpose**: Disable a specific addon
- **Method**: DELETE
- **Parameters**:
  - `site_id` - Site UUID
  - `addon_id` - Addon identifier ('redis', 'solr')

**Addon State Management**:
- Addon endpoints do NOT support GET requests (return 405)
- Site info response does NOT include addon state
- **Solution**: Track addon state locally in wp_options after each update
- Store state in `ash_nazg_addon_states` option: `array( 'redis' => true, 'solr' => false )`
- Default to disabled (false) if no stored state exists
- Display stored state as "Enabled" or "Disabled"
- After successful update: Store state in options, clear addon cache and endpoint status cache
- **Limitation**: State may be out of sync if addons are changed via Pantheon Dashboard

### Authorization

#### `GET /v0/sites/{site_id}/authorizations`
- **Purpose**: Current user permissions on this site
- **Parameters**: `site_id` - Site UUID
- **Returns**: Authorization object with user permissions

### Code & Git

#### `GET /v0/sites/{site_id}/code-tips`
- **Purpose**: Available Git branches and commits
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of git branch references

#### `GET /v0/sites/{site_id}/code-upstream-updates`
- **Purpose**: Available upstream update commits
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of upstream commits available for merging

#### `GET /v0/sites/{site_id}/environments/{env}/commits`
- **Purpose**: Git commit history for environment
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name (dev, test, live, or multidev name)
- **Returns**: Array of commit objects

#### `GET /v0/sites/{site_id}/environments/{env}/diffstat`
- **Purpose**: Git diff for uncommitted changes (SFTP mode)
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Diffstat object showing file changes

#### `GET /v0/sites/{site_id}/environments/{env}/build/updates`
- **Purpose**: Composer dependencies changes
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Availability**: Only for sites using Integrated Composer (`build_step` enabled)
- **Note**: Check environment settings for `build_step` flag before testing
- **Returns**: Array of composer package updates

### Backups

#### `GET /v0/sites/{site_id}/environments/{env}/backups/catalog`
- **Purpose**: All available backups
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Array of backup objects

#### `GET /v0/sites/{site_id}/environments/{env}/backups/schedule`
- **Purpose**: Automated backup schedule configuration
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Backup schedule configuration

### Domains

#### `GET /v0/sites/{site_id}/environments/{env}/domains`
- **Purpose**: Domains associated with environment
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Array of domain objects

#### `GET /v0/sites/{site_id}/environments/{env}/domains/dns`
- **Purpose**: DNS configuration recommendations
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: DNS recommendation objects

### Environment Settings

#### `GET /v0/sites/{site_id}/environments/{env}/settings`
- **Purpose**: Configuration settings for environment
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Settings object including `build_step` flag
- **Note**: Use to check for Integrated Composer support

#### `GET /v0/sites/{site_id}/environments/{env}/variables`
- **Purpose**: Environment-specific variables
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Array of environment variable objects

### Metrics

#### `GET /v0/sites/{site_id}/environments/{env}/metrics`
- **Purpose**: Traffic metrics (pages served, visits, cache performance)
- **Parameters**:
  - `site_id` - Site UUID
  - `env` - Environment name
- **Returns**: Metrics object with traffic data

### Workflows

#### `GET /v0/sites/{site_id}/workflows`
- **Purpose**: All workflows for this site
- **Parameters**: `site_id` - Site UUID
- **Returns**: Array of workflow objects

### User

#### `GET /v0/users/{user_id}`
- **Purpose**: Current user information
- **Parameters**: `user_id` - User UUID
- **Returns**: User profile object

#### `GET /v0/users/{user_id}/keys`
- **Purpose**: SSH public keys
- **Parameters**: `user_id` - User UUID
- **Returns**: Array of SSH key objects

#### `GET /v0/users/{user_id}/machine-tokens`
- **Purpose**: Active machine tokens
- **Parameters**: `user_id` - User UUID
- **Returns**: Array of machine token objects

#### `GET /v0/users/{user_id}/memberships/sites`
- **Purpose**: Sites where user has membership
- **Parameters**: `user_id` - User UUID
- **Returns**: Array of site membership objects

#### `GET /v0/users/{user_id}/memberships/organizations`
- **Purpose**: Organizations where user has membership
- **Parameters**: `user_id` - User UUID
- **Returns**: Array of organization membership objects

#### `GET /v0/users/{user_id}/upstreams`
- **Purpose**: Upstreams available to user
- **Parameters**: `user_id` - User UUID
- **Returns**: Array of upstream objects

### API Integration Patterns

**Critical Implementation Rules:**

1. **API Version**: All endpoints use `/v0/`, not `/v1/`

2. **Authentication**:
   - Include `client` parameter in machine token exchange request
   - Use `Authorization: Bearer {session-token}` header for all API requests
   - Session tokens expire after 1 hour - auto-refresh when expired

3. **Local Environment Mapping**:
   - Local environment names (lando, local, localhost, ddev) map to 'dev' for API queries
   - Example: If local env is 'lando', query API with 'dev' as environment parameter
   ```php
   $local_env_names = array( 'lando', 'local', 'localhost', 'ddev' );
   if ( $env && in_array( strtolower( $env ), $local_env_names, true ) ) {
       $api_env = 'dev';
   }
   ```

4. **Conditional Endpoint Testing**:
   - Check site capabilities before testing certain endpoints
   - Example: Composer Updates endpoint requires `build_step` flag
   ```php
   $env_settings = api_request( sprintf( '/v0/sites/%s/environments/%s/settings', $site_id, $env ) );
   if ( ! is_wp_error( $env_settings ) && isset( $env_settings['build_step'] ) ) {
       $has_integrated_composer = (bool) $env_settings['build_step'];
   }
   ```

5. **Caching Strategy**:
   - Session tokens: 1 hour (`ash_nazg_session_token`)
   - Site info: 24 hours (`ash_nazg_site_info_{site_id}`)
   - Environment info: 24 hours (`ash_nazg_env_info_{site_id}_{env}`)
   - Endpoints status: 24 hours (`ash_nazg_endpoints_status_{site_id}_{env}`)
   - Addon data: 24 hours (`ash_nazg_site_addons_{site_id}`)
   - All cached data (except session tokens) includes a `cached_at` timestamp
   - Use WordPress Transients API with DAY_IN_SECONDS for data caches
   - Respects Redis object caching if enabled
   - Cache format: `{ data: [...], cached_at: timestamp }` (new format)
   - Backward compatible: handles old format (raw data without timestamp)
   - Display "Last checked: X ago" in admin UI using `human_time_diff()`

6. **Error Handling**:
   - All API functions return data on success, `WP_Error` on failure
   - Log all errors via `error_log()` with sanitized data
   - Handle specific error codes (e.g., `environment_not_found` for local environments)
   - Surface user-friendly error messages in admin notices
   - Never expose sensitive information in error messages

7. **Cache Invalidation**:
   - Clear relevant caches after mutations (create, update, delete)
   - Example: After updating addon, clear both addon cache and endpoint status cache
   - Provide manual cache refresh option in admin interface ("Refresh Data" button)
   - Cache is automatically invalidated on user-triggered actions (addons toggle, workflow trigger)
   - Use `get_cache_timestamp($cache_key)` helper to retrieve when data was cached

8. **Per-Endpoint Cache Invalidation Rule**:
   - **CRITICAL**: Whenever we make a change to an endpoint via the plugin (PUT, POST, DELETE):
     1. Flush/clear the cache for that specific endpoint or category
     2. Pull the latest status from the API to refresh the data
     3. Mark the last update timestamp in the cache
     4. Display the updated "Last Checked" time in the dashboard
   - This ensures the dashboard always reflects the current state after user actions
   - Example workflow:
     ```php
     // User toggles addon
     update_site_addon( $site_id, 'redis', true );
     // Clear both addon cache AND endpoints status cache
     delete_transient( "ash_nazg_site_addons_{$site_id}" );
     delete_transient( "ash_nazg_endpoints_status_{$site_id}_{$env}" );
     // Next dashboard load will fetch fresh data with new timestamp
     ```
   - Future enhancement: Track per-endpoint timestamps instead of global timestamp
   - For now, all endpoints share the same "Last Checked" time (cached together)

**Non-API Data Sources:**
- Debug logs: Read directly from `$WP_CONTENT_DIR/debug.log` (not via API)
- Environment detection: Use `$_ENV['PANTHEON_ENVIRONMENT']`, `$_ENV['PANTHEON_SITE']`, etc.

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
