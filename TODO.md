# Ash-Nazg TODO List

## Current Work-in-Progress

No active work in progress.

## Critical Fixes (Production Blockers - RESOLVED)

### Environment Initialization Safeguards ✅
- **Issue**: Deploying code to uninitialized test/live environments caused permanent environment corruption
- **Impact**: Lost test/live containers on cxr-ash-nazg-ms site (requires Pantheon Support to fix)
- **Root Cause**: Assumed code deployment would initialize environments (incorrect assumption)
- **Fix**:
  - Added `is_environment_initialized()` helper to check API `initialized` field
  - Added backend validation in `ajax_deploy_code()` to reject uninitialized deploys
  - Added UI warnings and disabled deploy buttons for uninitialized environments
  - Clear error messages directing users to Pantheon Dashboard for initialization
- **API Limitation**: No API endpoint exists to initialize test/live environments (Dashboard only)
- **Status**: ✅ Complete - safeguards in place, tested against uninitialized environments

## Phase 3: Build Pipeline & Design

### Design Review & Refactoring
- [ ] Overall design review with Pantheon Design System
- [ ] Ensure consistent use of PDS components
- [ ] Review and optimize responsive breakpoints
- [ ] Accessibility audit (WCAG compliance)
- [ ] Add JavaScript bundling and minification
- [x] Configure watch mode for development (npm run watch)

## Code Quality

### Refactoring
- [ ] Replace ~60 WP_DEBUG logging patterns with `debug_log()` helper (currently only 4/60 replaced)
- [ ] Create `ensure_site_id()` and `ensure_environment()` helpers
  - Pattern to replace: repeated `if ( ! $site_id ) { return WP_Error... }`

### Testing
- [ ] Add Playwright E2E tests (as mentioned in CLAUDE.md Phase 3)
- [ ] Increase test coverage for git-related functions

## Planned Features

### Authentication & Security
- [ ] User-scoped machine tokens (store in user meta with Pantheon secrets using user ID suffix)
  - Instead of site-wide tokens, each user with manage_options can have their own token
  - Stored as `pantheon_get_secret("ash_nazg_machine_token_{user_id}")`
  - Allows better audit trails and token revocation per user

### Destructive Operations (Future/Experimental)
- [ ] Create "Delete Site" page with big red button
  - Hidden/easter egg admin page with ominous warnings
  - Multiple confirmation dialogs with scary, explicit messages
  - Big red button that actually deletes the site via API
  - Clear warnings about permanent data loss
  - Final confirmation requiring typing site name to proceed

## Future Enhancements
- [ ] Domain management for multisite (experimental/PoC)
- [ ] Custom workflow triggers
- [ ] Audit trail for API actions
- [ ] Performance optimization and caching refinement

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
- ✅ Helper functions created for repeated patterns:
  - `debug_log()` - WP_DEBUG logging wrapper
  - `verify_ajax_request()` - AJAX nonce and capability verification
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
