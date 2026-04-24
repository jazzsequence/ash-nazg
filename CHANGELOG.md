# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
