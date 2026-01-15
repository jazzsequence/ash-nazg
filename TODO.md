# Ash-Nazg TODO List

## Current Work-in-Progress

No active work in progress.

## Phase 3: Build Pipeline & Design

### Design Review & Refactoring
- [x] PDS integration: fonts, design tokens, foundations, and branded header
- [ ] Review all cards and interfaces for full PDS component compliance
- [ ] Review and optimize responsive breakpoints
- [ ] Accessibility audit (WCAG compliance)
- [ ] Add JavaScript bundling and minification
- [x] Configure watch mode for development (npm run watch)

## Code Quality

### Testing
- [ ] Add Playwright E2E tests (as mentioned in CLAUDE.md Phase 3)
- [ ] Increase test coverage for git-related functions

## Planned Features

### Authentication & Security
- [ ] User-scoped machine tokens (store in user meta with Pantheon secrets using user ID suffix)
  - Instead of site-wide tokens, each user with manage_options can have their own token
  - Stored as `pantheon_get_secret("ash_nazg_machine_token_{user_id}")`
  - Allows better audit trails and token revocation per user

## Documentation
- [ ] Update README with Phase 3 completion notes
- [ ] Add screenshots to README
- [ ] Update CLAUDE.md with latest patterns and standards
- [x] Document build process and SASS usage (npm scripts in package.json)

---

## ✅ Completed Features

### Phase 1 & 2 Complete
- ✅ Plugin bootstrap and activation
- ✅ Composer setup with dependencies
- ✅ API client with authentication (session token exchange and caching)
- ✅ Pantheon environment detection via $_ENV variables
- ✅ Settings page with machine token configuration
- ✅ Environment status display and comprehensive API endpoint testing
- ✅ Debug log viewer with fetch/clear functionality
- ✅ SFTP/Git mode toggle with polling verification
- ✅ Environment state management with persistent storage
- ✅ Site label inline editing
- ✅ Site addons management (Redis, Solr)
- ✅ Workflows integration (scaffold_extensions, Object Cache Pro installation)
- ✅ Multidev environment creation with AJAX progress bars
- ✅ Multidev merge and delete operations
- ✅ Upstream update detection and display
- ✅ Uncommitted changes display (diffstat in SFTP mode)
- ✅ Commit SFTP changes form
- ✅ Recent commits display
- ✅ Workflow status monitoring with polling
- ✅ Apply upstream updates button with workflow monitoring
- ✅ Merge Dev into Multidev functionality with conditional display
- ✅ Merge from Dev button in multidev management table
- ✅ Auto-clear invalid session tokens on 401/403 errors
- ✅ Manual session token clearing on Settings page
- ✅ Upstream cache invalidation after applying updates
- ✅ Helper functions and refactoring for code quality:
  - `debug_log()` - WP_DEBUG logging wrapper (80 uses throughout codebase)
  - `verify_ajax_request()` - AJAX nonce and capability verification
  - `ensure_site_id()` - Auto-detect site ID or return WP_Error (refactored 6 API functions)
  - `ensure_environment()` - Auto-detect environment or return WP_Error (refactored 6 API functions)
  - `is_local_environment()` - Detect local dev environments
  - `is_multidev_environment()` - Detect Pantheon multidev environments
  - `dev_has_changes_for_env()` - Compare commits between dev and target env
  - `filter_upstream_updates_for_env()` - Filter upstream updates per environment
  - `get_cache_timestamp()` - Get cache timestamp for "Last checked" displays
- ✅ Local URL override for Lando and Pantheon multidev environments (MU-plugin)
- ✅ Per-environment upstream updates filtering (no longer shows updates already applied)
- ✅ Code deployment workflow (deploy to test/live environments)
  - Master control panel approach: both deploy buttons visible from any environment
  - Side-by-side layout with panel-based interaction
  - Change detection: buttons disabled when environments are in sync
  - Deployment notes with larger textareas
  - Optional "sync content from live" for test→live deployments
  - Workflow monitoring with progress modals
  - Cache clearing after successful deployment
- ✅ Build Pipeline & Pantheon Design System Integration
  - npm package.json with build scripts
  - SASS/SCSS support with organized file structure
  - CSS compilation from SASS sources
  - Pantheon Design System (pds-core) integration
    - Imports design tokens from local ~/git/pds-core copy
    - Copies PDS foundations, utilities, and Pantheon logos during build
    - PDS fonts (Poppins, Aleo, Source Code Pro) loaded via Google Fonts
    - PDS design tokens and foundations imported in compiled CSS
    - Pantheon branded header with logo above page title
    - PDS badge styles (success/error) using PDS color tokens
    - Organized SASS structure: _base, _components, _pages, _utilities, _typography
    - Build command: `npm run build` (compiles SASS → CSS)
    - Watch mode: `npm run watch` (auto-compiles SASS on file changes)
- ✅ Backup Management
  - Backups admin page with create/list/download/restore UI
  - Master control center pattern: operate on any environment from anywhere
    - Environment dropdown selector for backup creation
    - Display backups from all environments with clear labels
    - Environment-specific sections with visual separation
  - List available backups: GET /v0/sites/{site_id}/environments/{env}/backups/catalog
  - Create backups: POST /v0/sites/{site_id}/environments/{env}/backups
    - Supports all, code, database, or files elements
    - Configurable retention period (keep_for parameter)
  - Restore backups: POST /v0/sites/{site_id}/environments/{env}/backups/{backup_id}/restore
    - Confirmation dialogs for destructive operations
    - Workflow monitoring with progress modals
  - Download backups: POST /v0/sites/{site_id}/environments/{env}/backups/{backup_id}/{element}/download-url
    - Generates signed URLs for secure downloads
  - Backup catalog grouped by backup set (timestamp)
  - Individual element management (code/database/files)
  - Collapsible backup set UI to reduce vertical space
  - Cache management with 5-minute TTL
  - Full PHPUnit test coverage
  - Tested end-to-end on properly initialized Pantheon environments
- ✅ Clone Content
  - Clone admin page for copying database and/or files between environments
  - Dropdown selectors for source and target environment selection
  - Checkboxes to select database only, files only, or both
  - Database clone: POST /v0/sites/{site_id}/environments/{env}/database/clone
    - Automatic URL search-replace for WordPress (from_url / to_url detection)
    - Optional cache clearing and database updates
  - Files clone: POST /v0/sites/{site_id}/environments/{env}/files/clone
    - Simple file copy from source to target environment
  - Environment validation:
    - Prevents cloning from/to uninitialized environments
    - Prevents same source and target selection
    - Validates at least one option (DB or files) is selected
  - Destructive operation warnings with confirmation dialogs
  - Multi-workflow monitoring (polls both DB and files workflows simultaneously)
  - Cache clearing after successful clone operations
  - Full PHPUnit test coverage
  - Security: nonce verification, capability checks, initialization validation
- ✅ Delete Site (Destructive Operation - Debug Mode Only)
  - Delete Site admin page with big red button (demonstration feature)
  - Only visible when `?debug=1` query parameter is present
  - Red menu text: "⚠️ DO NOT CLICK"
  - 500px circular red button with embossed text and diagonal shadow
  - Type "DELETE" to enable button
  - First confirmation: Modal with danger warnings and "I Understand the Risk" button
  - Second confirmation: JavaScript alert for final chance to cancel
  - "Whew! That was a close one!" message on cancellation
  - Fully functional - actually deletes site via `DELETE /v0/sites/{site_id}`
  - Redirects to Pantheon sites dashboard after deletion
  - Full PHPUnit test coverage (9 tests)
  - Referer-based access control for navigation
- ✅ Domain Management for WordPress Multisite
  - Automatic domain addition when new multisite subsites are created
  - Hooks: `wp_initialize_site` (WP 5.1+) and `wpmu_new_blog` (legacy)
  - Skips local environments (Lando, etc.) using `is_local_environment()`
  - Adds domains to Pantheon live environment via API
  - Synchronous operation (no workflow polling needed)
  - Admin notices for success/failure feedback via transients
  - API functions: `get_domains()`, `add_domain()`, `delete_domain()`
  - Endpoints: GET/POST/DELETE `/v0/sites/{site_id}/environments/{env_id}/domains`
  - Full PHPUnit test coverage (13 tests)
  - Multisite integration module: `includes/multisite.php`
