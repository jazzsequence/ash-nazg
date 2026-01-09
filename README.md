# Ash Nazg

**One ring to rule them all** - Manage your Pantheon hosting environment directly from WordPress.

![One Ring](https://static.wikia.nocookie.net/lotr/images/0/0d/The_One_Ring_on_a_map_of_Middle-earth.jpg)

Ash Nazg is a WordPress plugin that integrates the Pantheon Public API into your WordPress admin dashboard, allowing site administrators to manage critical Pantheon platform features without leaving WordPress.

## Why "Ash Nazg"?

The name comes from the inscription on the One Ring in J.R.R. Tolkien's works. Just as the One Ring unified power, this plugin unifies your workflow by bringing Pantheon's platform management capabilities into the one place you're already working: your WordPress admin.

## Features

This plugin follows a **Proof of Concept (PoC) approach**: implement API capabilities broadly, test what works, then restrict features that grant too much power or prove impractical.

### Currently Implemented

#### Core Features
- **Environment Detection** - Auto-detect Pantheon environment (dev/test/live/multidev/local)
- **Site Status Dashboard** - View Pantheon site details and environment information
- **API Endpoints Testing** - Comprehensive display of available Pantheon API endpoints with status
- **Smart Caching** - 24-hour cache with "last checked" timestamps to minimize API calls
- **Settings Management** - Configure machine tokens and plugin settings

#### Development Workflow
- **SFTP/Git Mode Toggle** - Switch between SFTP and Git mode with one click, includes automatic polling verification
- **Environment State Management** - Persistent tracking of site state (connection mode, environment) in WordPress
- **Automatic Mode Switching** - Automatically switches to SFTP mode for file operations, then switches back
- **Debug Log Viewer** - Read, display, and clear WordPress debug.log files without SSH access

#### Site Management
- **Site Addons Management** - Enable/disable Pantheon addons (Redis, Solr) directly from WordPress
- **Workflows** - Trigger Pantheon workflows from WordPress admin (e.g., Object Cache Pro installation)

### Phase 2: Development Workflow (In Progress)

- **Upstream Updates** - Detect and apply upstream updates from WordPress admin
- **Code Deployment** - Push code to test/live environments
- **Multidev Management** - Create and manage multidev environments
- **Backup Control** - Create, list, and manage backups
- **Workflow Monitoring** - Monitor and poll status of long-running workflow operations

### Phase 3: Experimental (Evaluation)

- **Multisite Domain Management** - Automatically add new subdomains to Pantheon on multisite subdomain creation (experimental, may be removed)
- Additional API capabilities as discovered and security-reviewed

### Explicitly Excluded

- **Cache Management** - Handled by Pantheon Advanced Page Cache and Pantheon mu-plugin
- **Organization Management** - Too much administrative power for WordPress admins
- **User Management** - Security risk
- **Billing** - Administrative function only
- **Token Generation** - Security risk

## Requirements

- **Must be hosted on Pantheon** (plugin uses Pantheon-specific environment variables and Secrets API)
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Pantheon machine token stored in Pantheon Secrets ([how to create](https://pantheon.io/docs/machine-tokens))
- User with `manage_options` capability in WordPress

## Installation

### From Source (Development)

1. Clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/jazzsequence/ash-nazg.git
   cd ash-nazg
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install  # or yarn install
   ```

3. Configure your Pantheon machine token in Pantheon Secrets (see Configuration below)

4. Activate the plugin through the WordPress admin

5. Navigate to **Pantheon** in the WordPress admin menu

### Future: WordPress Plugin Directory

Once stable, this plugin will be available through the WordPress plugin directory for one-click installation.

## Configuration

### Setting Up Your Pantheon Machine Token

This plugin uses [Pantheon Secrets](https://docs.pantheon.io/guides/secrets) to securely store your machine token. The token is **never stored in the database**.

1. **Create a machine token:**
   - Log into your Pantheon Dashboard
   - Go to Account > Machine Tokens
   - Create a new machine token (ideally from a "machine user" service account)
   - Copy the token (you'll only see it once!)

2. **Store the token in Pantheon Secrets:**
   - SSH into your Pantheon environment or use Terminus
   - Store the token using the Pantheon Secrets API with the label `ash_nazg_machine_token`:
     ```bash
     # Example using terminus
     terminus secret:set ash_nazg_machine_token <your-token-here> --scope=site --env=live
     ```
   - The plugin will retrieve the token using `pantheon_get_secret('ash_nazg_machine_token')`

   **For Development/Testing Only:**
   - If you don't have access to Pantheon Secrets (local development), you can configure the token in the WordPress admin under **Pantheon > Settings**
   - This stores the token in WordPress options (less secure, not recommended for production)

3. **Activate and configure:**
   - The plugin will auto-detect your Pantheon environment variables
   - Navigate to **Pantheon** in your WordPress admin menu to verify setup

### Security Notes

- **Production:** Machine tokens should be stored in **Pantheon Secrets**, not the WordPress database
- Tokens are retrieved from Pantheon Secrets at runtime via `pantheon_get_secret('ash_nazg_machine_token')`
- **Development fallback:** For local development, tokens can be stored in WordPress options (less secure)
- Consider using a dedicated "machine user" Pantheon account for the token
- Never share your token or commit it to version control
- WordPress admin compromise = Pantheon API access (within operational scope only)

## Usage

After configuration, you'll find an **Ash Nazg** top-level menu in your WordPress admin with the following pages:

- **Dashboard** - Environment status, site information, connection mode toggle, API endpoints testing with status indicators
- **Logs** - View and clear WordPress debug.log files (automatically switches to SFTP mode if needed)
- **Addons** - Enable/disable Pantheon site addons (Redis, Apache Solr)
- **Workflows** - Trigger Pantheon workflows (currently: Object Cache Pro installation)
- **Settings** - Machine token configuration and plugin settings

Each page displays "Last checked" timestamps showing when cached data was last fetched from the Pantheon API. Use the "Refresh Data" button to manually clear caches and fetch fresh data.

## Development

This plugin is under active development. See [CLAUDE.md](./CLAUDE.md) for technical architecture and development guidelines.

### Architecture

- **Functional programming** with `Pantheon\AshNazg` namespace
- **Traditional WordPress admin** interface using Pantheon Design System (PDS Core)
- **API-first approach** using `api.pantheon.io`
- **Secure by design** - no database storage of credentials

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

## Roadmap

### Phase 1: Foundation & Core Status (Complete)
- [x] Plugin bootstrap and activation
- [x] Composer setup with dependencies
- [x] Basic API client with authentication (`get_api_token()`)
- [x] Pantheon environment detection
- [x] Settings page with machine token setup
- [x] Environment status display via API
- [x] API connection testing interface
- [x] Comprehensive API endpoints testing and status display
- [x] Site addons management (Redis, Solr)
- [x] Workflows integration (trigger scaffold_extensions workflows)
- [x] Smart caching with timestamps (24-hour cache)
- [~] Launch check status information (not available via API - terminus only)
- [~] Error/debug log viewer (files may not exist due to read-only filesystem)

### Phase 2: Development Workflow Features
- [x] SFTP/Git mode toggle with polling verification
- [x] Environment state management and persistence
- [x] Automatic mode switching for file operations
- [x] Debug log viewer with fetch/clear functionality
- [x] JavaScript organization (separate files with proper enqueuing)
- [x] CSS organization standards (utility classes, no inline styles)
- [x] Comprehensive testing suite (API, state management, AJAX handlers)
- [ ] Upstream update detection and application
- [ ] Code deployment (push to test/live)
- [ ] Multidev creation
- [ ] Backup management (create, list, restore if safe)
- [ ] Workflow status monitoring with polling for long-running operations
- [ ] Additional workflow types beyond scaffold_extensions

### Phase 3: Experimental/Advanced
- [ ] Domain management for multisite (PoC)
- [ ] Additional API capabilities exploration
- [ ] Security review and feature restriction
- [ ] Performance optimization and caching refinement

### Post-PoC
- [ ] Playwright E2E tests
- [ ] Security hardening
- [ ] Documentation
- [ ] Feature flags for experimental capabilities
- [ ] Accessibility improvements
- [ ] Internationalization
- [ ] Plugin directory submission (if appropriate)

## FAQ

### Is this an official Pantheon plugin?

No, this is a community-developed plugin that uses the public Pantheon API. It is not officially supported by Pantheon.

### Will this work with other hosting providers?

No, this plugin **only works on Pantheon**. It relies on:
- Pantheon environment variables (`$_ENV['PANTHEON_SITE']`, etc.)
- Pantheon Secrets API for credential storage
- Pantheon-specific infrastructure

### Does this require a specific Pantheon plan?

The Pantheon API is available to all Pantheon customers. Some features may vary based on your plan level (e.g., multidev availability).

### Is my machine token secure?

Yes, tokens are stored in **Pantheon Secrets**, not in the WordPress database. The plugin retrieves tokens at runtime using `pantheon_get_secret()`. However, be aware:
- WordPress admin compromise = Pantheon API access (within token scope)
- The plugin only exposes operational features, not administrative/billing functions
- Consider using a dedicated "machine user" account with limited permissions

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
- Delete sites or perform other destructive administrative actions

### Why the "PoC approach" of implementing broadly then restricting?

We're exploring the full capabilities of the Pantheon API to understand what's possible and useful. Features that grant too much power or prove impractical will be removed or gated. This approach helps us build the most useful tool while maintaining security.

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

### 0.2.0 - In Development (Phase 2 Complete)
- **SFTP/Git Mode Toggle**: Switch connection modes with one click, includes automatic polling verification
- **Environment State Management**: Persistent tracking of site state in WordPress options
- **Automatic Mode Switching**: Automatically switches to SFTP mode for file operations, then reverts
- **Debug Log Viewer**: View, fetch, and clear WordPress debug.log files without SSH access
- **JavaScript Organization**: All JS in separate files with proper WordPress enqueuing and localization
- **CSS Organization Standards**: Comprehensive utility classes system, eliminated all inline styles
- **Comprehensive Testing**: Added test suites for API patterns, state management, and AJAX handlers
- **Enhanced Dashboard**: Connection mode display and toggle integrated into dashboard page
- **Auto-Sync**: State automatically synchronized after mode changes with verification

### 0.1.1 - Released
- **Site Addons Management**: Enable/disable Pantheon addons (Redis, Solr) from WordPress admin
- **Workflows**: Trigger Pantheon workflows (Object Cache Pro installation via scaffold_extensions)
- **Enhanced Caching**: 24-hour cache timeout with "last checked" timestamps on all data
- **API Endpoints Testing**: Comprehensive display of 26+ Pantheon API endpoints organized by category
- **Environment Mapping**: Local development environments (lando, local) automatically map to dev for API queries
- **Cache Timestamps**: Display when cached data was last fetched with human-readable time diffs
- **Auto Cache Clearing**: Caches automatically cleared when triggering workflows or toggling addons

### 0.1.0 - Initial Release
- Initial plugin framework and structure
- Pantheon API client with authentication (machine token → session token exchange)
- Settings page with machine token configuration
- Dashboard page displaying:
  - Pantheon environment detection (dev/test/live/multidev/local)
  - Site information from API
  - Current environment details from API
  - SFTP/Git mode indicator
  - API connection status
- Connection testing interface
- Composer setup with WordPress coding standards and testing tools
- Development/testing token storage fallback in WordPress options
- PHPUnit test suite with basic plugin tests
- Comprehensive linting infrastructure:
  - PHP syntax checking (`composer lint:php`)
  - WordPress coding standards (`composer lint:phpcs`)
  - Combined lint command (`composer lint`)
  - Pre-commit check command (`composer check`)
