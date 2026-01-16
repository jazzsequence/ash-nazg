# Ash Nazg

**One ring to rule them all** - Manage your Pantheon hosting environment directly from WordPress.

![One Ring](https://static.wikia.nocookie.net/lotr/images/0/0d/The_One_Ring_on_a_map_of_Middle-earth.jpg)

Ash Nazg integrates the Pantheon Public API into your WordPress admin dashboard. Toggle between SFTP and Git mode, view debug logs, manage addons, trigger workflows, and monitor your Pantheon environment—all without leaving WordPress.

## Why "Ash Nazg"?

The name comes from the One Ring inscription in Tolkien's works. Just as the One Ring unified power, this plugin unifies your workflow by bringing Pantheon platform management into the one place you're already working: WordPress admin.

## Features

### Dashboard & Monitoring

View your Pantheon environment status, site information, and connection mode at a glance. Monitor 26+ API endpoints with live status indicators showing which Pantheon features are available. Toggle between SFTP and Git mode with automatic verification.

_[Screenshot: Dashboard page showing environment status, site info card, connection mode toggle, and API endpoints grid]_

**Features:**
- Auto-detect environment (dev/test/live/multidev/local)
- Real-time API endpoint testing with status indicators (green checkmarks, red X's)
- Site and environment information from Pantheon API
- SFTP/Git mode toggle with automatic polling verification
- Inline site label editing with pencil icon
- Smart caching with "last checked" timestamps and one-click refresh

### Development

Manage code deployment, upstream updates, multidev environments, and uncommitted changes from a single interface. All development operations accessible from one page regardless of your current environment.

_[Screenshot: Development page showing upstream updates, code deployment panels, multidev management table, and uncommitted changes]_

**Code Deployment:**
- Deploy code from dev to test or test to live
- Side-by-side panels with environment sync detection
- Optional "sync content from live" for test→live deployments
- Change detection disables buttons when environments are in sync
- Deployment notes with workflow monitoring

**Upstream Updates:**
- Detect available upstream updates per environment
- Per-environment filtering (only shows updates not yet applied)
- One-click apply with workflow monitoring
- Automatic cache invalidation after updates

**Multidev Management:**
- Create new multidev environments from dev
- Merge multidev into dev or merge dev into multidev
- Delete multidev environments with confirmation
- Environment status and branch information display

**Uncommitted Changes:**
- View git diffstat in SFTP mode
- Commit SFTP changes with commit message
- File count and change type display
- Recent commits history

### Backups

Create, restore, and download backups for any environment. Manage backups across all environments from a single interface.

_[Screenshot: Backups page showing environment selector, backup catalog, and restore/download buttons]_

**Features:**
- Environment dropdown selector for backup creation
- Create backups: all, code only, database only, or files only
- Configurable retention period (1-365 days)
- List backups from all environments with visual separation
- Restore backups with destructive operation warnings
- Download backups via signed URLs (code/database/files)
- Collapsible backup sets to reduce vertical space
- Workflow monitoring for long-running operations

### Clone Content

Copy database and/or files between environments with automatic URL search-replace for WordPress.

_[Screenshot: Clone page showing source/target environment selectors and database/files checkboxes]_

**Features:**
- Source and target environment dropdown selectors
- Clone database, files, or both
- Automatic WordPress URL search-replace (from_url → to_url)
- Environment initialization validation
- Destructive operation warnings with confirmation modals
- Multi-workflow monitoring (polls both DB and files simultaneously)
- Automatic cache clearing after successful clones

### Debug Logs

View and clear WordPress debug logs without SSH access. Automatically switches to SFTP mode if needed to access log files on Pantheon's read-only Git filesystem.

_[Screenshot: Logs page showing debug.log contents and clear logs button]_

**Features:**
- Fetch and display debug.log contents
- One-click log clearing with automatic mode switching
- Skips SFTP switching on local environments
- File stat cache clearing for accurate deletion verification

### Addons

Enable or disable Pantheon site addons (Redis object caching, Apache Solr search) directly from WordPress admin.

_[Screenshot: Addons page showing Redis and Solr toggle switches with save button]_

**Features:**
- Toggle Redis object cache addon
- Toggle Apache Solr search addon
- Persistent state tracking in WordPress options
- Automatic cache clearing after changes

### Workflows

Trigger Pantheon workflows from WordPress, including Object Cache Pro installation via scaffold_extensions workflow.

_[Screenshot: Workflows page showing available workflows and trigger buttons]_

**Features:**
- Trigger Object Cache Pro installation (scaffold_extensions workflow)
- Environment validation (dev/multidev only)
- Workflow status retrieval and monitoring
- Additional workflow types as discovered

### Domains (Multisite Only)

For WordPress multisite installations, automatically add custom domains to Pantheon when new subsites are created.

_[Screenshot: Multisite domain management settings]_

**Features:**
- Automatic domain addition on subsite creation
- Hooks into `wp_initialize_site` (WP 5.1+) and `wpmu_new_blog` (legacy)
- Skips local environments automatically
- Adds domains to live environment by default
- Admin notices for success/failure via transients
- Synchronous operation (no workflow polling)

### Settings

Configure machine token authentication, view/clear session tokens, and manage plugin settings. Tokens stored securely in Pantheon Secrets (production) or WordPress options (local development fallback).

_[Screenshot: Settings page showing machine token configuration and session token management]_

**Features:**
- Machine token configuration with Pantheon Secrets integration
- Session token viewing and manual clearing
- Auto-clears invalid tokens on 401/403 errors
- Development fallback for local environments

### Delete Site (Debug Mode Only)

Demonstration feature showing full Pantheon API capabilities. Only visible when `?debug=1` query parameter is present.

_[Screenshot: Delete site page with big red button and danger warnings]_

**Features:**
- 500px circular red button with embossed text
- Menu item: "⚠️ DO NOT CLICK" (red background)
- Type "DELETE" to enable button
- Two-stage confirmation (modal + JavaScript alert)
- "Whew! That was a close one!" message on cancellation
- Fully functional - actually deletes site via Pantheon API
- Redirects to Pantheon dashboard after deletion

### Excluded for Security

This plugin does **not** provide access to:
- Organization or user management (beyond what's displayed)
- Billing information or plan changes
- Token generation/revocation (only usage)
- Unrestricted site deletion (only via debug mode)

## Requirements

- **Must be hosted on Pantheon** (plugin uses Pantheon-specific environment variables and Secrets API)
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Pantheon machine token stored in Pantheon Secrets ([how to create](https://pantheon.io/docs/machine-tokens))
- User with `manage_options` capability in WordPress

## Installation

### Via Composer (Recommended)

```bash
composer require jazzsequence/ash-nazg
```

Activate the plugin through the WordPress admin or via WP-CLI:

```bash
wp plugin activate ash-nazg
```

### From GitHub Release

1. Download the latest release ZIP file from [GitHub Releases](https://github.com/jazzsequence/ash-nazg/releases)
2. Upload the ZIP file through **WordPress Admin > Plugins > Add New > Upload Plugin**
3. Activate the plugin
4. Navigate to **Ash Nazg** in the WordPress admin menu

## Configuration

### Setting Up Your Pantheon Machine Token

1. **Create a machine token:**
   - Log into your Pantheon Dashboard
   - Go to Account > Machine Tokens
   - Create a new machine token
   - Copy the token (you'll only see it once!)

2. **Store the token (Recommended: Pantheon Secrets):**

   We highly recommend using [Pantheon Secrets](https://docs.pantheon.io/guides/secrets) to securely store your machine token:

   ```bash
   terminus secret:set <site> ash_nazg_machine_token YOUR_TOKEN --scope=user,web
   ```

   The plugin will retrieve the token using `pantheon_get_secret('ash_nazg_machine_token')`.

   **Alternative: WordPress Database**

   You can also configure the token in **Ash Nazg > Settings** in the WordPress admin. This stores the token in the WordPress database in plaintext, which is less secure than using Pantheon Secrets.

3. **Verify setup:**
   - Navigate to **Ash Nazg** in your WordPress admin menu
   - The plugin will auto-detect your Pantheon environment variables

## Development

This plugin is under active development. See [CLAUDE.md](./CLAUDE.md) for technical architecture and development guidelines.

### Architecture

- **Functional programming** with `Pantheon\AshNazg` namespace
- **Traditional WordPress admin** interface using Pantheon Design System (PDS Core)
- **API-first approach** using `api.pantheon.io`
- **Secure credential storage** via Pantheon Secrets (recommended) or WordPress database

### Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Follow [Pantheon WordPress Coding Standards](https://github.com/pantheon-systems/pantheon-wp-coding-standards)
4. Write PHPUnit tests for new functionality
5. Test in a Pantheon environment
6. Submit a pull request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/jazzsequence/ash-nazg.git
cd ash-nazg

# Install dependencies
composer install
npm install  # or yarn install

# Run all checks (lint + tests) - recommended before committing
composer check

# Run all linting (PHP syntax + coding standards)
composer lint

# Run PHP syntax check only
composer lint:php

# Run coding standards checks only
composer lint:phpcs
# or
composer phpcs

# Auto-fix coding standards issues
composer phpcbf

# Run tests only
composer test

# Install test environment (for WordPress integration tests)
composer test:install

# Run Playwright E2E tests (future)
npm test
```

### Development Dependencies

- **Composer:**
  - `pantheon-systems/pantheon-wp-coding-standards` - Coding standards
  - `pantheon-systems/wpunit-helpers` - WordPress testing helpers
  - `phpunit/phpunit` - PHP testing framework

- **npm/yarn:**
  - Pantheon Design System (PDS Core) - UI components

## Future Improvements

- Accessibility audit (WCAG compliance)
- JavaScript bundling and minification
- Playwright E2E tests
- User-scoped machine tokens (per-user authentication)
- MD5 hash machine tokens stored in database

## FAQ

### Is this an official Pantheon plugin?

No, this is a Hackathon 2026 project built by Chris Reynolds, Senior Developer Advocate at Pantheon. It was built independently and is not officially supported by Pantheon.

### Will this work with other hosting providers?

No, this plugin **only works on Pantheon**. It relies on:
- Pantheon environment variables (`$_ENV['PANTHEON_SITE']`, etc.)
- Pantheon Secrets API for credential storage
- Pantheon-specific infrastructure

### Does this require a specific Pantheon plan?

The Pantheon API is available to all Pantheon customers. Some features may vary based on your plan level (e.g., multidev availability).

### Is my machine token secure?

**Yes, if using Pantheon Secrets.** Tokens stored in Pantheon Secrets are encrypted and retrieved at runtime using `pantheon_get_secret()`.

**Less secure if using database storage.** Tokens stored in the WordPress database are stored in plaintext and are only as secure as your database. We highly recommend using Pantheon Secrets instead of database storage.

### What permissions does this plugin grant WordPress admins?

WordPress users with `manage_options` capability can:
- View environment status and information
- Toggle SFTP/Git mode
- Deploy code and apply upstream updates
- Create/manage backups and multidev environments

WordPress admins **cannot** (via this plugin):
- Manage Pantheon users or organizations
- Access billing information
- Generate or revoke machine tokens
- Delete sites (except via debug mode demonstration feature)

## Support

- **Issues:** Report bugs on [GitHub Issues](https://github.com/jazzsequence/ash-nazg/issues)
- **Documentation:** See [CLAUDE.md](./CLAUDE.md) for technical architecture and development guidelines
- **Pantheon API:** [Official API Documentation](https://api.pantheon.io/docs)
- **Pantheon Secrets:** [Pantheon Secrets Guide](https://docs.pantheon.io/guides/secrets)

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Credits

Developed with the power of the Pantheon Public API.

Named after Tolkien's One Ring inscription: "Ash nazg durbatulûk, ash nazg gimbatul, ash nazg thrakatulûk, agh burzum-ishi krimpatul" - One ring to rule them all, one ring to find them, one ring to bring them all, and in the darkness bind them.

## Changelog

### 0.3.2 - Current Release
- **Bug Fixes**: Clear logs false negative with clearstatcache(), SFTP mode switching on local environments
- **API Endpoint Testing**: Corrected upstream-updates endpoint path in dashboard testing
- **Version Bump**: Browser cache busting for modal.js and other JavaScript files

### 0.3.0 - Major Feature Release
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

### 0.2.0 - Development Workflow
- **SFTP/Git Mode Toggle**: Switch connection modes with automatic verification
- **Environment State Management**: Persistent tracking in WordPress options
- **Automatic Mode Switching**: Auto-switch to SFTP for file operations
- **Debug Log Viewer**: View, fetch, and clear debug.log files without SSH
- **JavaScript Organization**: Separate files with proper enqueuing
- **CSS Organization**: Utility classes system, no inline styles
- **Comprehensive Testing**: API, state management, and AJAX test suites

### 0.1.0 - Initial Release
- Pantheon API client with authentication
- Dashboard with environment detection and API endpoint testing
- Site addons management (Redis, Solr)
- Workflows integration (Object Cache Pro installation)
- Smart caching with timestamps
- Settings page with machine token configuration
- WordPress coding standards and PHPUnit testing infrastructure
