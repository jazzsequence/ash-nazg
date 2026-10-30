# Ash-Nazg TODO List

## Pantheon API v0 → v1 migration follow-ups

The plugin was migrated to Pantheon's v1 Public API, built against the OpenAPI spec/schema only (no live token at the time). Since then, live-verified against a real site with a real v1 Personal Access Token — findings below.

### Still needs live verification
Built and covered by pattern tests. Items below are what has **not** yet been confirmed on the deployed dev site. What has been confirmed is under Completed Features below, dated and attributed. Per CLAUDE.md, nothing is "working" until it has been.
- [ ] **Cold start**: the page loads and reloads fast with warm caches (confirmed 2026-10-30). Still to time: the first load after a deliberate full cache clear (e.g. a deploy plus `terminus env:clear-cache`), to confirm no request nears Pantheon's ~30s PHP limit.
- [ ] **"Refresh Data"** clears every endpoint cache and the panel re-probes ("caching is working" was confirmed 2026-10-30, but Refresh Data itself was not mentioned).
- [ ] **Endpoint rows, in detail**: that all 190 rows are present (the page was seen to load and probe; no row count was reported), and that the five broken endpoints are the same five found on 2026-10-23 (the count matched; no one-by-one comparison was reported). Placeholder resolution through the plugin is only indirectly supported by this: probe-able rows resolve and none stick on "Checking…".
- [ ] **Status tabs**: with a screen reader, and a row finishing while a filter is active ("filters are fine" was confirmed in a browser 2026-10-30; check whether that covered a filter applied while rows were still loading).
- [ ] **Upstream Updates block gating**: it is hidden on dev (confirmed 2026-10-30). Still to check: that it **shows** when dev really has updates to apply, on a multidev, and with "Updates in Live" or a combined label, none of which has been observed.
- [ ] **Legacy Machine Token notice** rewrite: against a site that really has both a user-specific and a global Secret.
- [ ] **Terminus variables fix**: `get_addon_env_variables()` now sends a v0 session token to `terminus.pantheon.io` (curl-verified: raw PAT → 401, session → 200); not yet run through the plugin on Lando.

### Endpoints the panel reports as broken (live, 2026-10-23)
Found by probing every read in the registry. Not plugin bugs, but worth raising with Pantheon alongside the two platform bugs below.
- [ ] `GET /v1/secrets` → 403 "Missing arguments" (the spec lists no parameters)
- [ ] `GET /v1/sites/{id}/environments/{env}/deployment-summary` → 500
- [ ] `GET /v1/sites/{id}/users/{userId}/membership` → 500 (the plural `.../memberships` works)
- [ ] `GET /v0/sites/{id}/environments/{env}/build/updates` → 404
- [ ] `GET /v0/sites/{id}/upstream-updates` → 404 (`GET .../upstream-updates/check` is a POST in v1)
- [ ] Runtime logs and tenant runtime log (v1) each take ~16s, so they dominate the slowest page-load request.

### Small follow-ups
- [ ] Add the Terminus-host endpoints (`https://terminus.pantheon.io/swag/swagger.json`, 20 operations) to the registry once their real base paths are confirmed; the spec has no host or base path and doesn't match the paths Terminus actually uses.

### Known platform bug (reported upstream, not fixable in this plugin) — HIGH PRIORITY, affects both API versions
- [ ] **Backup restore appears broken platform-wide, on both v0 and v1, as of 2026-10-01 — not a v1-only issue.** Live-tested 7 ways against the real test site:
  - v1 (`POST /sites/{id}/backups/restore`): 5 parameter combinations (top-level `Backup.id`, per-element `id`, `folder` name; `new-multi` cross-env and same-env; `env` vs `environment` query param) — all accepted (`HTTP 200`) but failed asynchronously ~15–25s later with the same generic `"The operation failed to complete."`
  - v0 (`POST .../backups/{backup_id}/restore`, the historically-correct path-based shape): tested against `new-multi`, against `dev` with a 6-month-old backup, and against `dev` with a backup created and restored within the same minute — all three failed the same way (workflow `result: failed`, no further detail in logs)
  - Ruled out: parameter-shape mistakes (v0's shape is confirmed correct from pre-migration code and still fails), multidev-as-target (dev failed too), and stale/expired backups (a fresh backup failed too)
  - **Retested 2026-10-23** on a fresh multidev: v1 (`type=RESTORE_DATABASE_BACKUP`, `key`=Backup id) and v0 (catalog id, `element=database`) were both accepted (HTTP 200) and both workflows ended `FAILED` again. Still broken.
  - **This should be escalated as a live incident, not just a v1-gap** — it blocks a core operational feature on a real account regardless of which API a customer uses.
- [ ] **`merge_multidev_to_dev()` (merging a multidev into `dev`) fails unconditionally.** Every request to `POST .../merges/cloud-to-dev` returns `"MAS response status: 500 Internal Server Error"` / `UNAUTHORIZED`, regardless of request shape (no body, flat body, the `params` body/query shapes — including the one that fixed the reverse `dev-to-cloud` direction, with/without `annotation`). Reproduced on two separate fresh multidevs with the same token that succeeds on every other mutation tested (including the reverse merge direction), ruling out a token-permission or shape issue on our end. **Retested 2026-10-23** on a fresh multidev (empty-body shape only): still fails, but the error changed from the 500 above to HTTP 403 with `MAS response status: 400 Bad Request` / `UNAUTHORIZED`. Should be escalated the same way as the backup-restore bug above.

### Hybrid v0 fallback — revisit once Pantheon ships v1 equivalents
These features have no v1 endpoint yet and fall back to the legacy v0 API (now via the same Personal Access Token in the common case — see above):
- [ ] `update_site_label()` — no v1 PUT/PATCH on `/sites/{id}`
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

---

## ✅ Completed Features

### v1 API migration and dashboard rework (unreleased)

Live-verified items are dated and attributed where the verification was done in a browser.

- ✅ Dashboard no longer makes a slow API call while rendering: the Pantheon user lookup requests plain `/current-user` (~2.5s versus ~21s with 50 workspaces), and the endpoint panel and Screen Options make no API call during the render. Page load and reload verified fast on the deployed dev site by Chris (2026-10-30); the first load after a deliberate full cache clear is not yet timed.
- ✅ API Endpoints panel lists every endpoint from a registry generated from the v0 and v1 specs (`bin/generate-endpoint-registry.py`, 190 operations in 15 groups); reads are probed one request at a time, and writes and ID-needing reads show a plain-words "No request sent" reason. Verified in a browser by Chris (2026-10-30): no probe-able row sticks on "Checking…", the AJAX probing works, five broken endpoints are shown.
- ✅ Status tabs (Needs attention by default, Working, No request sent, All) and an API version filter. Filters verified in a browser by Chris (2026-10-30); not yet checked with a screen reader.
- ✅ Removed the Environment Information card's editable Workspace row (and its 13-workspace background loader); the Site Information card's Workspace row is unchanged.
- ✅ Dashboard shows the real database version (MariaDB, via `SELECT VERSION()`) instead of ProxySQL's emulated 5.5.30; live-verified on dev through `terminus wp`.
- ✅ Pantheon logo URL is versioned to bypass a stale edge-cached 301; fixed on the deployed dev site.
- ✅ Upstream Updates block only shows when dev has updates to apply; hidden on dev, verified by Chris (2026-10-30).
- ✅ Legacy Machine Token notice lists every source and a delete command per Secret; built and tested, not yet checked against real Secrets.
- ✅ Gap analysis across v0, v1 and the Terminus API in `docs/PANTHEON-API-GAP-ANALYSIS.md`.
- ✅ A v1 Personal Access Token authenticates directly as a Bearer token against `/v1/*` — confirmed (`GET /current-user` returns full profile + workspaces).
- ✅ **A v1 Personal Access Token also fully works as a v0 `machine_token`** — confirmed against every v0 endpoint the hybrid-fallback functions use (`/v0/sites/{id}`, `.../metrics`, `.../code-tips`, `.../domains`, `/v0/users/{id}`), not just the exchange itself. This was the opposite of what we'd assumed. `get_v0_session_token()` exchanges the Personal Access Token for a v0 session; the separate Machine Token is no longer used for authentication.
- ✅ Relay-connection pagination: `currentUser_workspacesConnection_first=N` works as documented. `pager[first]=N` (bracket notation) is **invalid** (HTTP 400) — removed; the environments endpoint already returns every environment with no pager param at all.
- ✅ `Backup.date` is a numeric-string Unix timestamp, not an ISO date string — fixed the `strtotime()` call that assumed otherwise.
- ✅ Backup elements (code/database/files) *do* have real `size` and `ttl` fields, and `name` is already a complete filename — fixed `get_backups()` to use the real values instead of hardcoding 0 and double-appending a filename suffix.
- ✅ `get_backup_download_url()`'s assumption that a backup's opaque `id` is timestamp-based was wrong — it's a hash. Fixed to look up the real per-element `timestamp` from the (cached) backups list instead.
- ✅ `clone_database()`/`clone_files()` request body shapes — confirmed live against a disposable multidev (`new-multi`, cloned from `dev`). Both the bare `{"fromEnvironment": "..."}` shape and the `wpReplaceSiteURL: {fromUrl, toUrl}` sub-object completed with `SUCCEEDED`/`FINISHED`. No code change needed.
- ✅ `restore_backup()`'s `type` query param (v1) — **was wrong**, the enum is `RESTORE_{ELEMENT}_BACKUP`, not the bare element name. Moot now — see the Known platform bug section above, `restore_backup()` was switched to v0 entirely since v1 restore is confirmed non-functional.
- ✅ `restore_backup()` switched to the v0 fallback endpoint (`POST /v0/sites/{id}/environments/{env}/backups/{backup_id}/restore`, path-based ID + `{"element": ...}` body) — see the Known platform bug section above for why.
- ✅ `create_multidev()` requires an `annotation` query param — every request failed with a generic 400 without it, even though the spec doesn't flag it any differently from `name`/`cloneFromEnv`. Fixed by always sending a default annotation. Live-tested end to end (create → `get_workflow_status`/`poll_workflow` → `SUCCEEDED`) on a disposable multidev.
- ✅ `update_connection_mode()` (both `on-server` and `git` directions) — confirmed working as implemented, live-tested on a disposable multidev. One gotcha: sending an empty JSON body (`{}`) on these POSTs causes a `500`/`UNAUTHORIZED` — the function's existing behavior of sending no body at all for an empty params array is correct and must stay that way.
- ✅ `commit_sftp_changes()` — confirmed working as implemented against a disposable multidev with a real uncommitted SFTP change.
- ✅ `add_domain()` / `delete_domain()` — confirmed working as implemented against a disposable multidev.
- ✅ `merge_dev_to_multidev()` — `params` must be sent as a JSON-encoded string in the query string (`?params=%7B...%7D`), not as a body field and not as bracket/dot query notation (both tried, both failed the same generic way `merge_multidev_to_dev()` always fails — see the Known platform bug section above). Fixed; live-tested to `SUCCEEDED`.
- ✅ `deploy_code()` — confirmed working as implemented, live-tested dev→test on the real site.
- ✅ `update_site_addon()` — confirmed working as implemented (enable and disable), live-tested on Solr and restored to its original state afterward.
- ✅ `get_workflow_status()` / `poll_workflow()` — confirmed working as implemented across every workflow above (uppercase `result` lowercased correctly, polling reaches `SUCCEEDED`/`FAILED`).
- ✅ `is_environment_initialized()` read `$environments[$env]['initialized']`, but the real v1 field is `isInitialized` — `initialized` doesn't exist and was always null, so the Development page incorrectly showed test/live as never initialized. Fixed by aliasing `isInitialized` to `initialized` in `get_environments()`, matching the existing alias pattern there.
- ✅ `get_upstream_updates()` fabricated its commit list from the environment's own `commitLog` (the same data "Recent Commits" shows) — not a real upstream diff. v1 has no commit-level upstream diff at all; the real signal is a coarse status label (`SiteMembership.upstreamUpdateStatus`, e.g. "Up to Date"/"Updates in Dev"/"Updates in Live") read via the site's membership in its workspace (`GET /workspaces/{id}?...sitesConnection...`, filtered by `upstreamId`). Added `get_site_upstream_status()` and rewired `get_upstream_updates()` to use it; `composerUpdates`-based dependency details are kept as a secondary, usually-empty signal (this site isn't composer-managed). Live-verified against a second real site with genuinely pending updates (`cxr-ash-nazg-ms`, status correctly returned "Updates in Dev", matching `terminus upstream:updates:status` independently).
- ✅ `get_backup_download_url()`'s id-matching loop compared `$backup['id']` (the raw per-event hash) directly against `$backup_id` (the catalog's composite `{id}_{element}` key) — these can never match, so every download attempt failed with "Could not find the requested backup." Fixed to build the same composite key before comparing. Also found and fixed a second bug in the same function: the timestamp it was using came from the per-element sub-object's own `timestamp` field, but the real `backup-download-urls` endpoint needs the Backup's top-level `date` field instead (confirmed live — the element timestamp returns a 500, the top-level date succeeds and returns a real signed URL).
- ✅ Multisite new-site-to-Pantheon-domain workflow (`on_new_site()` / `wp_initialize_site` hook) — live-verified end to end on an actual multisite install (`cxr-ash-nazg-ms`): simulated a real admin form submission (`$_POST['ash_nazg_environments']` + `wp_insert_site()`) and confirmed the domain was actually added to the real Pantheon dev environment, then cleaned up (site deleted, domain removed).
- ✅ `.gitignore`'s `assets/images/` directory-level ignore + file-level negation (`!assets/images/pantheon-logo-*.png`) is a well-known git no-op — once a directory itself is ignored, git prunes traversal into it and never evaluates per-file negations inside. The two logo PNGs were tracked fine in the GitHub repo itself, but `deploy-dev.yml` clones a fresh Pantheon repo copy on every run and does `git add` there, where the files are untracked — so this bug dropped the entire `assets/images/` directory from every deploy, breaking the Pantheon logo on every page of the live site. Fixed by removing the ignore rule entirely (the directory only ever contained these two files).
- ✅ Dashboard's API Endpoints Status diagnostics page was largely unmigrated: the v1/v0 path-prefix display used an empty string for v1 instead of `/v1`, 11 of 19 rows still called v0 endpoints that don't match what the real functions actually use (including "Upstream Updates," which used the old pre-migration v0 path), and a couple of rows were stale (bracket-notation `pager[first]` that's confirmed invalid; "Site Plan" duplicating a field already shown via "Site Information"; "Backup Schedule" testing a capability v1 only supports as a write, not a read; "Machine Tokens" testing a deprecated auth concept with no v1 equivalent, confirmed by full-text search of the schema - PAT management isn't an API capability at all, by design). Fixed the prefix bug, migrated "User Permissions" to the real v1 `/sites/{id}/users/{id}/memberships` endpoint (live-verified), wired "Upstream Updates" to the real `get_site_upstream_status()` check instead of removing it, and removed only the rows with no real v1 equivalent and no real feature backing them.
- ✅ Upgraded `@pantheon-systems/pds-design-tokens` from `1.2.0` (npm `latest` dist-tag) to `2.0.0-beta.0` (npm `next` dist-tag) — an actively-developed v2 (70+ alpha iterations) existed and had never been adopted. Cross-referenced this plugin's actual token usage (14 real `var(--pds-*)` references across all component SCSS, excluding the vendored tokens file) against Pantheon's own `codemods/v1-to-v2/mappings.json` migration data bundled in the v2 package; only 3 tokens were renamed (`--pds-color-badge-indicator-critical-{background,foreground}` → `--pds-color-badge-critical-{background,foreground}`, `--pds-color-text-default` → `--pds-color-foreground-default`), updated at all 4 call sites. Also updated the `copy:tokens` build script for v2's split file structure (separate global/light/dark/typography CSS files replacing v1's single file).
- ✅ **Dashboard endpoint panel, verified in a browser on the deployed dev site (2026-10-30, by Chris):** no probe-able row shows as "Checking…", and the AJAX per-endpoint probing is doing its job; five broken endpoints are shown; the filters are fine.
- ✅ **Dashboard load and reload (2026-10-30, by Chris):** the page loads fine, reload speed is fast, and caching is working.
- ✅ **Upstream Updates block (2026-10-30, by Chris):** hidden on dev.
- ✅ An old-style (pre-unification) machine token does **not** work as a v1 Bearer token — confirmed by the maintainer. A Personal Access Token is required for v1; only the PAT-as-v0-`machine_token` direction works.
- ✅ `get_addon_env_variables()` (local/Lando only) sent the PAT directly as the Bearer token to `terminus.pantheon.io`, which returns 401 (`Invalid or expired session header`) — it only accepts a v0 session token. Live-verified: PAT bearer → 401; session token exchanged from the same PAT → 200 with the env vars. Fixed to use `get_v0_session_token()`.
- ✅ Add Playwright E2E tests — scaffolded with a11y (axe-core WCAG 2.1 AA) and functional tests
- ✅ Multidev lifecycle: create e2e-{pr} on PR open, delete on PR close via Pantheon API
- ✅ Update claude-code-reviewer config to verify it correctly skips the test suite for text/markdown-only changes — confirmed working, no changes needed
- ✅ Endpoint status filters are real `<button>` elements, so Space activates them as well as Enter (f6947c9). The visual match to the link tabs is not yet checked in a browser: the active tab's bottom border, focus ring and hover colour.
- ✅ Fixed the stale `@return` docblock on `get_current_pantheon_user()`, which still mentioned `workspacesConnection` (9c68801).

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
