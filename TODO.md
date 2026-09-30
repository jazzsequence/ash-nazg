# Ash-Nazg TODO List

## Pantheon API v0 → v1 migration follow-ups

The plugin was migrated to Pantheon's v1 Public API, built against the OpenAPI spec/schema only (no live token at the time). Since then, live-verified against a real site with a real v1 Personal Access Token — findings below.

### Live-verified and fixed
- [x] A v1 Personal Access Token authenticates directly as a Bearer token against `/v1/*` — confirmed (`GET /current-user` returns full profile + workspaces).
- [x] **A v1 Personal Access Token also fully works as a v0 `machine_token`** — confirmed against every v0 endpoint the hybrid-fallback functions use (`/v0/sites/{id}`, `.../metrics`, `.../code-tips`, `.../domains`, `/v0/users/{id}`), not just the exchange itself. This was the opposite of what we'd assumed. `get_v0_session_token()` now tries the Personal Access Token first, falling back to the separate legacy Machine Token only for installs whose stored token predates the unified Personal Access Token system (untested — no old-style token available to verify that direction).
- [x] Relay-connection pagination: `currentUser_workspacesConnection_first=N` works as documented. `pager[first]=N` (bracket notation) is **invalid** (HTTP 400) — removed; the environments endpoint already returns every environment with no pager param at all.
- [x] `Backup.date` is a numeric-string Unix timestamp, not an ISO date string — fixed the `strtotime()` call that assumed otherwise.
- [x] Backup elements (code/database/files) *do* have real `size` and `ttl` fields, and `name` is already a complete filename — fixed `get_backups()` to use the real values instead of hardcoding 0 and double-appending a filename suffix.
- [x] `get_backup_download_url()`'s assumption that a backup's opaque `id` is timestamp-based was wrong — it's a hash. Fixed to look up the real per-element `timestamp` from the (cached) backups list instead.
- [x] `clone_database()`/`clone_files()` request body shapes — confirmed live against a disposable multidev (`new-multi`, cloned from `dev`). Both the bare `{"fromEnvironment": "..."}` shape and the `wpReplaceSiteURL: {fromUrl, toUrl}` sub-object completed with `SUCCEEDED`/`FINISHED`. No code change needed.
- [x] `restore_backup()`'s `type` query param — **was wrong**, fixed. The v1 enum is `RESTORE_{ELEMENT}_BACKUP` (e.g. `RESTORE_DATABASE_BACKUP`), not the bare element name (`DATABASE`) the code was sending. Fixed in `includes/api.php`; regression test added in `tests/test-backups-api.php`.

### Known v1 platform bug (reported upstream, not fixable in this plugin)
- [ ] **`restoreSiteBackup` (`POST /sites/{id}/backups/restore`) appears non-functional in v1 as of 2026-09-30.** Live-tested against the disposable `new-multi` multidev with 5 different parameter combinations — every combination accepted the request (`HTTP 200`, workflow created) but the workflow itself always failed asynchronously ~15–25s later with the same generic `"The operation failed to complete."` and no further detail:
  1. `key` = top-level `Backup.id`, cross-environment (`dev` backup → `new-multi`)
  2. `key` = the database element's own `BackupDownload.id`, cross-environment
  3. `key` = the element's `folder` value (e.g. `1780592488_backup`), cross-environment
  4. `key` = a same-environment backup's own `Backup.id` (created and restored entirely within `new-multi`)
  5. Same as #4 but with `env=` instead of `environment=` as the query param name
  All 5 failed identically regardless of `key` format or same-vs-cross-environment target, which rules out a parameter-shape mistake on our end — this looks like a genuine backend defect or an unannounced restriction, not a docs/usage issue. Since `key`'s correct format could not be confirmed as a result, this needs to be re-verified once Pantheon fixes/clarifies the endpoint. **Flagged for the Public API team** — see the v1-gaps summary.
- [ ] Confirm whether a genuinely old-style (pre-unification) machine token works as a v1 Bearer token directly — only the reverse (new PAT working for v0) has been confirmed; no old-style token was available to test

### Hybrid v0 fallback — revisit once Pantheon ships v1 equivalents
These features have no v1 endpoint yet and fall back to the legacy v0 API (now via the same Personal Access Token in the common case — see above):
- [ ] `update_site_label()` — no v1 PUT/PATCH on `/sites/{id}`
- [ ] `delete_site()` — no v1 `DELETE /sites/{id}`
- [ ] `get_environment_metrics()` / `clear_metrics_cache()` — no v1 metrics endpoint (schema types exist, nothing serves them)
- [ ] `get_domains()` — no v1 domain-listing endpoint (only create/delete/set-primary/verify)
- [ ] `get_code_tips()` — no v1 equivalent found
- [ ] `trigger_workflow()` (Install Object Cache Pro on the Workflows page) — no generic v1 workflow-trigger endpoint

### Known data gaps (not fixable without a v1 API change)
- [ ] `create_backup()` can no longer create a single-element (code-only/database-only/files-only) backup — v1's `createSiteBackup` only creates a full code+database+files combo; the `$element` UI selector is currently a no-op for creation (still respected for restore/download)

## Planned Features

### UI/UX Improvements
- [ ] Accessibility audit (WCAG compliance)
- [ ] Add JavaScript bundling and minification

### Site Selection
- [ ] Allow users to manually set/override the Pantheon site ID from the plugin dashboard — enables non-Pantheon-hosted sites (e.g. decoupled frontends, local installs) to connect to and manage a Pantheon site via the plugin

### Testing — E2E (follow-on after PR merge)
- [x] Add Playwright E2E tests — scaffolded with a11y (axe-core WCAG 2.1 AA) and functional tests
- [x] Multidev lifecycle: create e2e-{pr} on PR open, delete on PR close via Pantheon API
- [ ] Full user-journey coverage: destructive/slow operations (apply upstream updates, deploy code, clone content, multidev create/delete, SFTP commit) — stub tests exist, need actual trigger + assertion
- [ ] E2E error-state coverage: invalid token, API failure, uninitialized environment

### Testing — PHPUnit (follow-on work, scoped separately)
- [ ] PHPUnit coverage for all API functions (currently structural/pattern tests only — no integration coverage of actual return values, error paths, or data transformation)
- [ ] Tests for addon status detection logic (get_addon_env_variables, live vs fallback path)
- [ ] Tests for git functions: effective environment resolution, multidev branch detection
- [ ] Tests for environment state management: update, get, migration from old option format
- [ ] Tests for token encryption/decryption round-trip
- [ ] Tests for all AJAX handler response shapes (success and error)
- [ ] Increase git API test coverage (parse_git_porcelain_lines edge cases, upstream filtering)

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
