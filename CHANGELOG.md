# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

## [1.0.0] - 2026-10-30

### Changed

- **Migrated from the Pantheon Public API v0 to v1**: `includes/api.php` now calls `https://api.pantheon.io/v1` directly. The biggest change is auth — v1 has no token-exchange step, so a Personal Access Token is now sent as the Bearer token on every request instead of being exchanged for a short-lived session token. All session-token caching/clearing machinery has been removed.
- **New Personal Access Token setting**: a Personal Access Token is required for v1; live-verified, it also works as a v0 `machine_token` for the full v0 API surface, so it also covers the handful of features still on v0 (see below) — only one token is needed. See Removed below for the legacy Machine Token.
- Read-heavy functions (`get_environment_commits`, `get_diffstat`, `get_environment_info`) now pull from richer nested v1 responses (e.g. `Environment.commitLog`, `Environment.diffstat`) instead of dedicated v0 endpoints, reducing the number of API calls for the same data. (`get_site_addons()` is unrelated to the API version — it always read `$_ENV`/Terminus directly, not a Public API endpoint.)
- `update_site_addon()` now uses a single v1 `PUT .../addons/{addonType}?enable=` call for both enabling and disabling an addon, instead of v0's PUT-to-enable/DELETE-to-disable pair.
- `update_connection_mode()` now calls one of two distinct v1 endpoints (`development-mode/git` or `development-mode/on-server`) instead of v0's single endpoint with a `mode` body parameter.
- Upgraded `@pantheon-systems/pds-design-tokens` from `1.2.0` to `2.0.0-beta.0` — picks up Pantheon's actively-developed v2 design tokens (new color primitives, `oklch()` color functions). Of the 14 PDS tokens this plugin actually uses, 3 were renamed upstream and updated here; the `copy:tokens` build step was updated for v2's split CSS file structure.
- **The dashboard no longer makes a slow API call while it renders.** A deploy that cleared the caches could push a cold dashboard load past Pantheon's ~30s PHP limit, and a request killed at the limit never cached anything, so each retry hit the same wall. Live timing: `GET /v1/current-user` takes ~2.5s but ~21s (and over 30s from the CLI) when asked for 50 workspaces with `currentUser_workspacesConnection_first=50`, so the user lookup now requests plain `/current-user`. The API Endpoints panel and Screen Options also stopped probing during the render. Verified on the deployed dev site: the page loads fine, reloads fast, and caching is working. The first load after a deliberate full cache clear has not been timed.
- **The API Endpoints panel now lists every endpoint the Pantheon API offers**, not 15 hand-written probes. See Added.

### Removed

- **Delete Site** (debug-mode-only demonstration feature): removed entirely — it only ever existed to show off API capability, required the legacy v0 API (no v1 equivalent), and wasn't a real operational feature.
- **Legacy Machine Token as an authentication credential**: live-verified, a Personal Access Token fully covers everything the Machine Token did, including the v0-only fallback features, so the Machine Token is no longer used for authentication at all. Removed: the Settings page's Machine Token field, the Dashboard's Machine Token copy button, and the old per-user-vs-global Machine Token migration notice system. If a Machine Token is still found stored (Secret, user meta, or global option), a new notice offers one-click removal of any database-stored copy once a Personal Access Token is also configured — Pantheon Secrets must still be deleted via Terminus, shown as a copy-paste command.
- **The Workspace row in the Environment Information card (directly below the "Pantheon Account" row; it had a pencil icon, shown when there was more than one workspace, for choosing a "primary workspace").** The Workspace row in the Site Information card is a different row and is unchanged; it was never editable. There is no operation for moving a site between workspaces (v1 has only a read-only `transfer-destinations` list and `promote-to-owner`, and organization admin is excluded), so it only saved a display preference that nothing else read, and loading the 13 workspaces for it took ~60s of sequential background requests that never reliably completed. The site-info card already shows the site's workspace with no API call. Orphaned `ash_nazg_primary_org_*` user meta is left in place, harmless.
- The API Endpoints panel's Site / User / All tabs, replaced by status tabs (see Added). Hiding every group in Screen Options now hides the panel.

### Added

- **Elasticsearch addon support**: v0 never supported Elasticsearch at all, which is why the Addons page showed it as a permanent "Coming Soon" placeholder. v1 exposes it like any other addon (`settings.addons.elasticsearchAddon` on the Site API, and `PUT /sites/{id}/addons/ELASTICSEARCH` to toggle it — already wired in `update_site_addon()`, just never connected to detection). Live status detection now reads the `IS_EPIO_ENVIRONMENT` environment variable (Pantheon's hosted Elasticsearch is branded "ElasticPress.io" internally), the same `$_ENV`-based mechanism already used for Redis/Solr — not an API call, so the toggle state always reflects what's actually running rather than a cached/stale read.
- **Every Pantheon API endpoint, listed and checked on the dashboard.** `bin/generate-endpoint-registry.py` generates `includes/endpoints-registry.php` from the v0 and v1 specs (190 operations, 95 per version, in 15 groups); regenerate it when Pantheon changes either API. The page renders every row with no API call. Reads are probed one request at a time after the page loads, so no request is longer than the slowest endpoint (~16s measured). Writes, deletes and reads needing an ID the plugin can't supply are listed with a plain-words reason ("No request sent: this DELETE request deletes something, so sending it would change your site…"); only GET requests are ever sent. Broken and inaccessible endpoints are reported, not hidden. Verified in a browser on the deployed dev site: no probe-able row sticks on "Checking…", the per-endpoint probing works, and five broken endpoints are shown (no row count was reported, and the five were not reported as compared one by one against the 2026-10-23 list).
- Status tabs on that panel (Needs attention by default, Working, No request sent, All) with live counts, an API version filter (v1 / v0), and an accessible live region. Filtering is client-side. Filters verified in a browser. Accessibility is covered by Axe and keyboard Playwright tests (no screen reader is available), run by Chris against the deployed dev site on 2026-10-30: all passed.

### Fixed

- `get_addon_env_variables()` (local/Lando addon detection) sent the Personal Access Token directly to `terminus.pantheon.io`, which rejects it with a 401 — that service only accepts a v0 session token. Now uses `get_v0_session_token()`. Verified through the plugin's own function on the deployed dev site by forcing a local environment name (2026-10-30): it reached `terminus.pantheon.io` with the session token. Not run on an actual Lando install.
- Removed the "Clear Session Token" Settings feature — meaningless under v1, which has no session tokens to clear.
- Dashboard's API Endpoints Status diagnostics no longer hardcode ~15 rows as "unavailable" for v0-only features. They're now live-tested through the v0 client like the real hybrid-fallback functions already do, so the page reflects actual connectivity instead of a static assumption. Rows that were purely redundant with data already shown elsewhere (commit history, diffstat, env variables/settings, SSH keys, user organizations) were removed instead of faked.
- `restore_backup()` switched to the v0 fallback — live testing found v1's restore endpoint fails unconditionally regardless of parameters (a Pantheon platform bug, not a client-side issue; confirmed v0 fails the same way right now too, so this is ready to work once Pantheon fixes it).
- `create_multidev()` was missing a required `annotation` query param — every call failed with a generic 400. Fixed.
- `merge_dev_to_multidev()`'s `params` was sent as a body field; the API only accepts it as a JSON-encoded string in the query string. Fixed.
- `merge_multidev_to_dev()` (merging a multidev into `dev`) fails unconditionally regardless of request shape — see TODO.md, another Pantheon platform bug.
- Dashboard's Site Information table and workspace auto-detection were built against v0-only field names (`created`, `framework`, `organization`, `plan_name`, `frozen`, `region`, `max_num_multidevs`, `holder_type`, `holder_id`, `owner`) that don't exist in v1's actual API response. Most of the table silently showed nothing; the workspace auto-detection always fell back to the user's first workspace membership instead of the one that actually owns the site. `normalize_site_info()` now backfills the real v1 fields under the names the rest of the codebase expects; `holder_type`/`holder_id`/`owner` have no v1 equivalent and are no longer shown.
- `is_environment_initialized()` read a v0-shaped `initialized` field that doesn't exist in v1 (the real field is `isInitialized`), so the Development page always showed test/live as uninitialized. Fixed by aliasing it in `get_environments()`.
- `get_upstream_updates()` showed the environment's entire ordinary commit history as if it were a list of pending upstream updates. v1 has no commit-level upstream diff at all; rewired to the real signal — a coarse status label (`SiteMembership.upstreamUpdateStatus`) read from the site's workspace membership, plus composer dependency details where applicable.
- `get_backup_download_url()` compared the wrong id shape when looking up a backup's timestamp (the catalog's composite `{id}_{element}` key against the raw per-event hash), so every download attempt failed with "Could not find the requested backup." Also fixed a second bug in the same function: it read the wrong timestamp field for the actual download-url request (the per-element `timestamp` instead of the Backup's top-level `date`).
- The Pantheon logo was missing on every page of the live site. `.gitignore`'s `assets/images/` directory ignore plus a file-level negation for the two logo PNGs is a git no-op — once a directory itself is ignored, git never evaluates per-file negations inside it. The files were tracked fine in this repo, but the deploy workflow clones a fresh copy of the Pantheon site repo on every run and stages files there, where they were never tracked — so every deploy silently dropped the whole directory. Fixed by removing the ignore rule; it only ever contained these two files.
- Dashboard's API Endpoints Status diagnostics were still largely unmigrated: v1 endpoints displayed with no version prefix at all (`/v0` showed correctly, v1 showed nothing), and 11 of 19 rows still called v0 endpoints that don't match what the real functions now use — including "Upstream Updates," which used the old pre-migration v0 path and has been rewired to the real status check. "User Permissions" migrated to the real v1 membership endpoint. Removed rows with no real v1 equivalent and no real feature behind them (a duplicate of data already shown via Site Information, a v1-write-only capability with no read endpoint for this read-only page, and a deprecated token-management check with no API equivalent by design).
- The dashboard showed the database version as 5.5.30. `$wpdb->db_version()` reads the connection handshake, which on Pantheon comes from ProxySQL and is a fixed emulated value; `SELECT VERSION()` is answered by the real database (live-verified on dev: MariaDB 10.4.34). The row now shows the product name and real version.
- The Pantheon logo stayed broken after the missing-files fix: Pantheon's edge had cached a week-long 301 for the bare logo URL from when the file was missing. The URL now carries a `?ver=` query string, which is a fresh cache key. Confirmed fixed on the dev site after deploy.
- The Development page's Upstream Updates block, with its Apply button, showed "Updates in Test" on dev after the updates had already been applied. Any status other than "Up to Date" counted as updates; only a status mentioning dev (or pending dependency changes), and only on dev or a multidev, does now. Labels seen live: "Up to Date", "Updates in Dev", "Updates in Test"; "Updates in Live" is inferred, not observed. A multidev whose site-wide label is "Updates in Test" now hides the block. Verified hidden on dev on the deployed site. The showing path can't be reproduced on demand; it is covered by a unit test for the "Updates in Dev" label seen live earlier, and is not verified on a multidev or with an "Updates in Live" or combined label.
- The "Legacy Machine Token Found" notice printed a delete command for only one Secret, so a site with both the user-specific and global Secret deleted one and still saw it. It now lists every source it found, gives a command per Secret, names the environment it checked, and notes that a deleted Secret can keep showing up for a while (seen on dev). Not verified against a real stale Secret beyond that observation; no available site has one to test on.

### Developer tooling

- Fixed the PHPUnit suite to actually load WordPress — `tests/bootstrap.php` checked for a bootstrap file that never existed in the `wpunit-helpers` package, so WordPress was never loaded in any test run and the suite relied entirely on source-text pattern matching. Also added the `yoast/phpunit-polyfills` dependency it required but never had.
- Wired the Playwright e2e suite into the pre-commit review gate (`REVIEWER_E2E_CMD` was empty) and added `dotenv` so test credentials load automatically from a local `.env` file. Fixed a Playwright config bug along the way: an explicit `storageState: undefined` meant to override a parent value for the auth-setup project was being dropped across Playwright's config/worker process boundary, causing every e2e run to fail.
- `.env` is now actually gitignored — it was documented as required in CLAUDE.md but never added.

### Known limitations (tracked in TODO.md)

- Four features (`site label editing`, `Metrics`, `domain listing`, `Install Object Cache Pro`) have no v1 API equivalent yet and fall back to the legacy v0 API (via the same Personal Access Token in the common case).
- `create_backup()` can no longer create a single-element (code/database/files-only) backup — v1 only supports full combo backups.
- Backup restore (both v0 and v1) is currently broken platform-wide as of 2026-10-01 — see TODO.md.
- `merge_multidev_to_dev()` (merging a multidev into `dev`) still fails on Pantheon's side (a 403 `MAS ... 400 Bad Request`, previously a 500) — see TODO.md. Five read endpoints in the API Endpoints panel also report as broken by Pantheon and are listed in TODO.md.
- Released with two checks not yet done: the first dashboard load after a deliberate full cache clear has not been timed, and the "Refresh Data" button's clearing of the endpoint caches has not been confirmed. The page loads and reloads fast with warm caches.
- The rewritten legacy Machine Token notice and the Upstream Updates block's showing path could not be tested on any available site; they are covered by tests only.
- `get_upstream_updates()`'s v1 check endpoint is Composer-only, unlike v0's synchronous git-aware response — a real capability regression for standard git-mode sites; see TODO.md.

This migration was initially built against the v1 OpenAPI spec without a live Personal Access Token, then live-verified against a real site once one became available — several corrections came out of that (removed an invalid `pager[first]=N` query param that returned HTTP 400, fixed `Backup.date` timestamp parsing, and backup `size`/`ttl`/filename fields turned out to be present after all, contrary to what the schema doc implied).

## [0.6.3] - 2026-07-09

### Added

- **Real PDS design tokens compiled into the build**: The plugin now depends on the public `@pantheon-systems/pds-design-tokens` npm package (exact-pinned) instead of a private `../pds-core` sibling checkout. `npm run copy:tokens` vendors the published light-mode token CSS to `assets/sass/_pds-tokens.scss` (committed, like `assets/js/libs/chart.umd.js`), which `admin.scss` `@use`s so the real `:root{--pds-*}` definitions compile into `admin.css`. The CSS build no longer depends on a private repo and works in CI, and the shipped artifact is fully self-contained.

### Fixed

- **WordPress 7.0 styling breakage**: WordPress 7.0's "Modern" design system exposed a latent bug — the shipped `admin.css` referenced `var(--pds-*)` tokens that were never defined anywhere (the token source was gitignored, distignored, and never imported), so modals lost their background/shadow and colors broke on real installs. Tokens are now defined in the compiled CSS (see Added), so every reference resolves.
- **WordPress 7.0 button and icon alignment**: WP 7 enlarged core buttons (40px) and restyled dashicon metrics, which made the plugin's icon buttons balloon and pushed dashicons off-center. Buttons now use flexbox centering (no `:has()`, so it works in every browser) with a matching-specificity override of core's `.wp-core-ui .button .dashicons` line-height, keeping the plugin's buttons blended with the new design system.
- **Development page fatal error**: The page fataled with "Cannot use object of type WP_Error as array" when the Pantheon API returned a `WP_Error` (e.g. an invalid or expired token). The environment list is now guarded before use.
- **Metrics filter row alignment**: The Load Metrics / Refresh buttons now match the 48px height of the adjacent WP 7 select controls so the row aligns.
- **Site Label input width**: The inline-edit input for the site label was constrained (WordPress's `.regular-text` was wider than the field needs).
- **License metadata mismatch**: `package.json` and the plugin header declared `MIT` while the bundled `LICENSE` file and README are GPL v2 or later. Both now declare GPL-2.0-or-later to match.

### Changed

- **Development asset cache-busting**: Plugin CSS/JS are enqueued with a `filemtime()`-based version in development (WP_DEBUG or a local environment) so rebuilt assets are not served stale from the browser or Pantheon edge cache; production continues to use the stable plugin version.

---

## [0.6.2] - 2026-04-28

### Fixed

- **Version constant missing from 0.6.1**: The plugin header and `ASH_NAZG_VERSION` constant were not bumped for the 0.6.1 patch release, so WordPress could not surface the update to existing installs and CSS cache-busting continued using the stale `0.6.0` query string.
- **Release process documented**: Added an explicit Release Process checklist to CLAUDE.md so version bumping, CHANGELOG updates, and build steps are not missed in future releases.

---

## [0.6.1] - 2026-04-28

### Fixed

- **Pantheon logos missing from fresh installs**: `assets/images/` was gitignored so the Pantheon logo PNGs (used in the plugin header and dashboard widget) were absent from release artifacts and any clean checkout. The two logo files are now tracked in git.
- **Icon colors missing from fresh installs**: Checkmark and X status icons used PDS CSS custom properties that were not defined in the compiled stylesheet. Icon colors are now hardcoded with WCAG AA-safe values.

---

## [0.6.0] - 2026-04-24

### Added

**Dashboard**
- 50/50 two-column card layout replacing the single-column dashboard
- Environment card displaying Pantheon user info, organization selector (with inline editing), and a machine token copy button
- Screen Options panel for the Dashboard page: show/hide individual API endpoint groups plus a master toggle for all groups
- Wider Path column in the API endpoint testing table
- Standardized `.ash-nazg-editable` flex class used for all inline edit UIs (site label, org name)

**WP Updates Page Integration**
- Pantheon upstream updates are now surfaced on the native WordPress Updates page (`update-core.php`)
- "Apply Updates" button opens the same progress modal used on the Development page and polls workflow status until complete

**Development Page**
- Show uncommitted local git changes using `git status --porcelain` with `safe.directory` support
- Show unpushed local commits via `git log @{u}..`
- Refresh button on the Recent Commits card
- Screen Options: toggle visibility of Environments card and Multidev Management card independently
- WP Admin links in the Environments table for all non-current environments (links to `https://{env}-{site}.pantheonsite.io/wp-admin/`)
- Clone From dropdown in Create Multidev now lists all environments (dev, all multidevs, test, live) instead of dev and multidevs only
- Consistent environment ordering throughout: dev → multidevs (sorted alphabetically) → test → live
- Commit table column widths standardized: Hash 10%, Author 20%, Date 15%, Message takes remainder
- Debug sections (Raw API Response) hidden by default

**Addons Page**
- Screen Options: show/hide Redis, Solr, and Elasticsearch addon sections independently
- Elasticsearch "Coming Soon" badge (no API endpoint available yet; placeholder for future support)
- Live addon status detection: reads `$_ENV` directly on Pantheon; queries Terminus internal API (`terminus.pantheon.io/api/sites/{id}/environments/{env}/variables`) on local environments

**Backups Page**
- Tabbed UI (dev / test / live) replacing stacked full-width sections; tabs are JS-driven; default tab matches the current environment
- Screen Options: age filter with options all / last 7 days / last 30 days / last 1 year, stored per user in user meta
- Element column widened to 55% so filenames fit on one line without wrapping; overflow shown with `text-overflow: ellipsis`

**Metrics Page**
- Summary Statistics card moved above the Metrics Filters section for quicker reference
- Screen Options: show/hide Pages Served, Unique Visits, and Cache Performance charts independently
- Chart filter controls automatically hidden when all charts are hidden via Screen Options
- Environment and duration selections persisted in `localStorage` and restored on page load
- API request and response debug panels hidden by default (were previously always visible)

**WP Admin Dashboard Widget**
- New "Cache Performance" widget on the main WordPress admin dashboard
- Displays a Pantheon-branded line chart of cache hit ratio % over the last 28 days using Chart.js
- Shows summary stats: average cache hit ratio and pages served
- Includes a direct link to the plugin's Metrics page
- Only shown to users with `manage_options` capability on Pantheon-hosted sites
- Uses a standalone `dashboard-widget.css` stylesheet (no PDS styles on the WP dashboard)

**Release Pipeline**
- `.github/workflows/release.yml`: triggers on GitHub release published, runs `build:dist`, and attaches the zip artifact to the release
- `bin/build-dist.sh`: uses rsync and zip with `.distignore` to produce a clean distribution artifact
- `.distignore`: excludes SASS source files, tests, docs, config files, and other dev-only files from the release zip
- `npm run build:dist` script wired into `package.json`
- `.github/dependabot.yml`: weekly automated dependency updates for Composer, npm, and GitHub Actions

**CSS/SCSS Architecture**
- `assets/sass/_components/_code-blocks.scss`: reusable styles for debug `<details>`/`<pre>` blocks
- `assets/sass/_components/_dashboard-widget.scss`: styles specific to the WP admin dashboard widget
- `assets/sass/dashboard-widget.scss`: standalone SCSS entry point that compiles without PDS imports
- `assets/css/dashboard-widget.css`: compiled standalone widget stylesheet

### Changed

- **Menu order** updated to: Ash Nazg (Dashboard) → Metrics → Development → Addons → Workflows → Backups → Clone → Logs → Settings
- Backups: stacked environment sections replaced with tabbed layout (dev/test/live tabs)
- Metrics: Summary Statistics card repositioned above Metrics Filters
- Development: Clone From dropdown now includes all environments (previously dev and multidevs only)
- Removed "Back to Dashboard" button from Workflows page (redundant with admin menu)
- Removed redundant current environment display from Workflows page
- Screen options labels are now stacked vertically instead of inline for better readability

### Fixed

- Metrics debug panels (API request/response) no longer render on every page load
- Backups catalog no longer collapses all environments into one scrollable section; tab-based navigation makes individual environment catalogs accessible without scrolling past unrelated data

---

## [0.5.0]

- **Environment Metrics Visualization**: New "Metrics" admin page showing traffic and performance analytics
- **Chart.js Integration**: Interactive line charts with Pantheon Design System colors and styling
- **Multiple Time Periods**: View metrics for 7 days, 28 days, 12 weeks, or 12 months
- **Three Chart Types**: Pages Served, Unique Visits, and Cache Performance (hits vs misses)
- **Summary Statistics**: Overall totals and average cache hit ratio with per-chart breakdowns
- **Refresh Functionality**: Clear cache and reload current metrics data
- **Responsive Design**: Mobile-friendly charts with smooth curves and hover interactions
- **Debug Panels**: Expandable API request/response panels for troubleshooting
- **Comprehensive Tests**: 24 PHPUnit tests for metrics API and UI components
- **Chart.js in libs/**: Renamed vendor directory to libs for better clarity

## [0.4.0]

- **Per-User Token Storage**: Machine tokens now stored per-user instead of site-wide
- **Token Encryption**: AES-256-CBC encryption for database-stored tokens using WordPress salts
- **Pantheon Secrets Integration**: Per-user secret keys with user ID suffix (`ash_nazg_machine_token_{user_id}`)
- **Migration System**: Backward-compatible migration from global to per-user tokens
- **Migration UI**: Admin notice with progressive nag (1 week → 24 hours) and settings page migration button
- **Per-User Session Tokens**: Separate session token caching per user for better audit trails
- **Security Enhancement**: Better security and audit trails with individual token revocation
- **User ID Display**: Prominent user ID display in settings for Pantheon Secrets setup
- **PHPUnit Tests**: Comprehensive test suite for user token functionality

## [0.3.2]

- **Bug Fixes**: Clear logs false negative with `clearstatcache()`, SFTP mode switching on local environments
- **API Endpoint Testing**: Corrected upstream-updates endpoint path in dashboard testing
- **Version Bump**: Browser cache busting for `modal.js` and other JavaScript files

## [0.3.0]

- **Code Deployment**: Deploy to test/live environments with panel-based UI and sync content option
- **Multidev Management**: Create, merge, and delete multidev environments
- **Backup Management**: Create, list, restore, and download backups from any environment
- **Clone Content**: Copy database and/or files between environments with automatic URL search-replace
- **Domain Management**: Automatic domain addition for WordPress multisite subsites
- **Delete Site**: Demonstration feature (debug mode only) with big red button
- **Build Pipeline**: SASS compilation with Pantheon Design System integration
- **PDS Integration**: Fonts, design tokens, foundations, and branded Pantheon header
- **Upstream Updates**: Detection and filtering per environment
- **Workflow Monitoring**: Polling for long-running operations with progress modals

## [0.2.0]

- **SFTP/Git Mode Toggle**: Switch connection modes with automatic verification
- **Environment State Management**: Persistent tracking in WordPress options
- **Automatic Mode Switching**: Auto-switch to SFTP for file operations
- **Debug Log Viewer**: View, fetch, and clear debug.log files without SSH
- **JavaScript Organization**: Separate files with proper enqueuing
- **CSS Organization**: Utility classes system, no inline styles
- **Comprehensive Testing**: API, state management, and AJAX test suites

## [0.1.0]

- Pantheon API client with authentication
- Dashboard with environment detection and API endpoint testing
- Site addons management (Redis, Solr)
- Workflows integration (Object Cache Pro installation)
- Smart caching with timestamps
- Settings page with machine token configuration
- WordPress coding standards and PHPUnit testing infrastructure
