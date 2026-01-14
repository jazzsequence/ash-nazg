# Ash-Nazg TODO List

## Current Work-in-Progress

### Known Issues
- [ ] MU-plugin URL override needs to be committed in multisite repo
  - File: `wp-content/mu-plugins/local-url-override.php`
  - Location: `~/pantheon-local-copies/cxr-ash-nazg-ms/`
  - Fixes: Pantheon multidev environments now properly override `plugins_url()` to use current request domain
  - Required for: Development.js and other assets to load correctly on multidev environments

### Git & Development Features
- [ ] Code deployment workflow (deploy to test/live environments)
  - API endpoint: POST /v0/sites/{site_id}/environments/{env}/deploys
  - Deploy from dev → test or test → live
  - Include workflow monitoring with progress bars
  - Clear environment cache after deployment

## Phase 3: Build Pipeline & Design

### Build Pipeline
- [ ] Add build pipeline (webpack or similar)
- [ ] Add SASS/SCSS support for stylesheets
- [ ] Set up CSS compilation and minification
- [ ] Add JavaScript bundling and minification
- [ ] Configure watch mode for development
- [ ] Integrate Pantheon Design System (pds-core) npm package
  - Private npm package, reference local copy at ~/git/pds-core
  - Import PDS components and styles into build pipeline
  - Replace custom styles with PDS utilities where applicable

### Design Review & Refactoring
- [ ] Overall design review with Pantheon Design System
- [ ] Ensure consistent use of PDS components
- [ ] Review and optimize responsive breakpoints
- [ ] Accessibility audit (WCAG compliance)

## Code Quality

### Refactoring
- [ ] Replace ~60 WP_DEBUG logging patterns with `debug_log()` helper (currently only 4/60 replaced)
- [ ] Create `ensure_site_id()` and `ensure_environment()` helpers
  - Pattern to replace: repeated `if ( ! $site_id ) { return WP_Error... }`

### Testing
- [ ] Add Playwright E2E tests (as mentioned in CLAUDE.md Phase 3)
- [ ] Increase test coverage for git-related functions

## Planned Features

### Backup Management
- [ ] Create Backups admin page
- [ ] List available backups with API endpoint: GET /v0/sites/{site_id}/environments/{env}/backups/catalog
- [ ] Create backup button with API endpoint: POST /v0/sites/{site_id}/environments/{env}/backups/create
- [ ] Download backup links (if API provides download URLs)
- [ ] Restore backup functionality (evaluate security implications)

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
- [ ] Document build process and SASS usage
- [ ] Add screenshots to README
- [ ] Update CLAUDE.md with latest patterns and standards

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
