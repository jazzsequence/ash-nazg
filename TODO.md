# Ash-Nazg TODO List

## Current Work-in-Progress

### Git & Development Features
- [ ] Add "Apply Upstream Updates" button to Development page
  - API endpoint: POST /v0/sites/{site_id}/environments/{env}/code/upstream-updates
  - Add form with confirmation dialog (upstream updates are destructive)
  - Show success/error messages
  - Clear upstream updates cache after applying
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
- [ ] Replace repeated WP_DEBUG logging patterns with helper functions
  - Create `log_debug()` helper (46 instances to replace)
  - Create `ensure_site_id()` and `ensure_environment()` helpers
  - Consolidate cache operations with timestamps

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
