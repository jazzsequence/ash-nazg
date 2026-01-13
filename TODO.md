# Ash-Nazg TODO List

## Phase 3: Build Pipeline & Design

### Build Pipeline
- [ ] Add build pipeline (webpack or similar)
- [ ] Add SASS/SCSS support for stylesheets
- [ ] Set up CSS compilation and minification
- [ ] Add JavaScript bundling and minification
- [ ] Configure watch mode for development

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
- [ ] Investigate and fix failing PHPUnit tests
- [ ] Add Playwright E2E tests (as mentioned in CLAUDE.md Phase 3)
- [ ] Increase test coverage for git-related functions

## Features

### Git & Development
- [ ] Investigate upstream updates count discrepancy (shows 3, should show 1)
- [ ] Implement git reset functionality (restore to previous commit)
- [ ] Add "Apply Upstream Updates" button
- [ ] Add upstream update details (changelog, release notes)
- [ ] Implement multidev environment creation

### Additional Features (from CLAUDE.md Phase 2)
- [ ] Upstream update detection and application
- [ ] Code deployment (push to test/live)
- [ ] Backup management (create, list, restore if safe)
- [ ] Workflow status monitoring with polling

### Authentication & Security
- [ ] User-scoped machine tokens (store in user meta with Pantheon secrets using user ID suffix)
  - Instead of site-wide tokens, each user with manage_options can have their own token
  - Stored as `pantheon_get_secret("ash_nazg_machine_token_{user_id}")`
  - Allows better audit trails and token revocation per user

### Destructive Operations
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
