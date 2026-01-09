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

**Planned Features:**

1. **Environment Information & Status**
   - Detect current Pantheon environment (dev/test/live/multidev)
   - Display environment status and metrics
   - Show launch check status information
   - **Not via API:** Display error/debug logs by reading `$WP_CONTENT_DIR/debug.log` directly

2. **Development Workflow**
   - Toggle between SFTP mode and Git mode (enables plugin/theme installation without leaving WP admin)
   - Detect available upstream updates
   - Apply upstream updates from WP admin
   - Push code to test/live environments
   - Create multidev environments

3. **Domain Management** (Experimental/PoC)
   - Hook into WordPress multisite subdomain creation
   - Automatically add new subdomains to Pantheon via API
   - Test feasibility for initial PoC (may hide/remove later based on results)

4. **Backup Operations**
   - Backup creation and management
   - Backup scheduling
   - Restore operations (if safe to expose)

5. **Workflow Monitoring**
   - Track long-running operations
   - Status polling for deployments, backups, etc.

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
- Top-level menu: "Pantheon"
- Potential submenu pages (to be finalized based on implementation):
  - Dashboard/Overview - environment status, launch check, error logs
  - Development - SFTP/Git toggle, upstream updates, code deployment
  - Backups - backup management (if implemented)
  - Multisite Domains - domain management (if experimental feature proves viable)
  - Settings - token setup helper, plugin configuration

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
- **CRITICAL: Spacing Rule** - Only ONE space after variable names and array keys
  - ✅ Correct: `$variable = value;` and `'key' => value`
  - ❌ Wrong: `$variable  = value;` and `'key'  => value`
  - Never use alignment spacing for variables or array keys

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

#### PHPUnit Tests
- Use `pantheon-systems/wpunit-helpers` for test setup and execution
- Write unit tests for API client functions
- Test error handling and edge cases
- Test with various WordPress versions and PHP versions
- Test token retrieval and session token caching
- Verify security measures (nonce, capabilities, sanitization)

#### Playwright Tests
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

#### `GET /v0/sites/{site_id}/addons/{addon_id}`
- **Purpose**: Get specific addon status
- **Parameters**:
  - `site_id` - Site UUID
  - `addon_id` - Addon identifier ('redis', 'solr')
- **Returns**: Addon object with enabled status
- **Cache**: 5 minutes
- **Note**: No list endpoint exists - must query individual addons
- **Known addons**: redis, solr

#### `PUT /v0/sites/{site_id}/addons/{addon_id}`
- **Purpose**: Enable or disable a specific addon
- **Parameters**:
  - `site_id` - Site UUID
  - `addon_id` - Addon identifier ('redis', 'solr')
- **Request Body**: `{ "enabled": true/false }`
- **Note**: Clear both addon cache and endpoint status cache after successful update
- **Implementation**: Define known addons list, query each individually

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
   - Site info: 5 minutes (`ash_nazg_site_info_{site_id}`)
   - Environment info: 2 minutes (`ash_nazg_env_info_{site_id}_{env}`)
   - Endpoints status: 10 minutes (`ash_nazg_endpoints_status_{site_id}_{env}`)
   - Addon data: 5 minutes (`ash_nazg_site_addons_{site_id}`)
   - Use WordPress Transients API with appropriate TTLs
   - Respects Redis object caching if enabled

6. **Error Handling**:
   - All API functions return data on success, `WP_Error` on failure
   - Log all errors via `error_log()` with sanitized data
   - Handle specific error codes (e.g., `environment_not_found` for local environments)
   - Surface user-friendly error messages in admin notices
   - Never expose sensitive information in error messages

7. **Cache Invalidation**:
   - Clear relevant caches after mutations (create, update, delete)
   - Example: After updating addon, clear both addon cache and endpoint status cache
   - Provide manual cache refresh option in admin interface

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
