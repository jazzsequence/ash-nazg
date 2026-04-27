# Ash-Nazg TODO List

## Planned Features

### UI/UX Improvements
- [ ] Accessibility audit (WCAG compliance)
- [ ] Add JavaScript bundling and minification

### Site Selection
- [ ] Allow users to manually set/override the Pantheon site ID from the plugin dashboard — enables non-Pantheon-hosted sites (e.g. decoupled frontends, local installs) to connect to and manage a Pantheon site via the plugin

### Testing
- [ ] Add Playwright E2E tests
- [ ] Increase test coverage for git-related functions

### Tooling
- [x] Update claude-code-reviewer config to verify it correctly skips the test suite for text/markdown-only changes — confirmed working, no changes needed

---

## ✅ Completed Features

### v0.6.0

- ✅ WP admin dashboard widget: cache hit ratio line chart, links to Metrics page
- ✅ Dashboard: 50/50 two-column layout, Environment card (Pantheon user, org selector, machine token copy), Screen Options for endpoint groups
- ✅ WP Updates page: Pantheon upstream updates surfaced on update-core.php with progress modal
- ✅ Development page: uncommitted local changes, unpushed commits, Refresh button, Screen Options (Environments/Multidev), WP Admin links per env, all-envs Clone From, canonical env ordering
- ✅ Addons page: Screen Options (Redis/Solr/Elasticsearch), Elasticsearch Coming Soon, live status detection via \$_ENV / Terminus API
- ✅ Backups page: tabbed dev/test/live UI, Screen Options age filter, wider Element column
- ✅ Metrics page: Summary Stats above filters, per-chart Screen Options, localStorage persistence
- ✅ Menu reorder: Metrics, Development, Addons, Workflows, Backups, Clone, Logs, Settings
- ✅ Release pipeline: GitHub Actions release workflow, build:dist script, .distignore, dependabot
- ✅ CSS: _code-blocks.scss, _dashboard-widget.scss, standalone dashboard-widget.css entry point
- ✅ Documentation: CHANGELOG.md, updated CLAUDE.md and README.md for v0.6.0

### Phase 1–3 & v0.1.0–v0.5.0

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
- ✅ Merge Dev into Multidev and from Dev button in multidev table
- ✅ Auto-clear invalid session tokens on 401/403 errors
- ✅ Manual session token clearing on Settings page
- ✅ Upstream cache invalidation after applying updates
- ✅ Helper functions: debug_log(), verify_ajax_request(), ensure_site_id(), ensure_environment(), is_local_environment(), is_multidev_environment(), dev_has_changes_for_env(), filter_upstream_updates_for_env(), get_cache_timestamp()
- ✅ Local URL override for Lando and Pantheon multidev environments (MU-plugin)
- ✅ Per-environment upstream updates filtering
- ✅ Code deployment workflow (deploy to test/live, side-by-side layout, workflow monitoring)
- ✅ Build Pipeline & Pantheon Design System integration (SASS, PDS tokens, branded header)
- ✅ Backup management (create, list, restore, download across all environments)
- ✅ Clone content between environments (database and/or files)
- ✅ Domain management for WordPress multisite
- ✅ Delete site (debug mode only, demonstration feature)
- ✅ Per-user machine token storage with Pantheon Secrets and AES-256-CBC encryption
- ✅ Environment metrics visualization with Chart.js (Pages Served, Unique Visits, Cache Performance)
