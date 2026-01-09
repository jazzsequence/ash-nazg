# Ash Nazg

**One ring to rule them all** - Manage your Pantheon hosting environment directly from WordPress.

![One Ring](https://static.wikia.nocookie.net/lotr/images/0/0d/The_One_Ring_on_a_map_of_Middle-earth.jpg)

Ash Nazg is a WordPress plugin that integrates the Pantheon Public API into your WordPress admin dashboard, allowing site administrators to manage critical Pantheon platform features without leaving WordPress.

## Why "Ash Nazg"?

The name comes from the inscription on the One Ring in J.R.R. Tolkien's works. Just as the One Ring unified power, this plugin unifies your workflow by bringing Pantheon's platform management capabilities into the one place you're already working: your WordPress admin.

## Features

This plugin follows a **Proof of Concept (PoC) approach**: implement API capabilities broadly, test what works, then restrict features that grant too much power or prove impractical.

### Phase 1: Foundation & Status (In Development)

- **Environment Detection** - Auto-detect Pantheon environment (dev/test/live/multidev)
- **Site Status** - View Pantheon site details and environment information
- **Launch Check Display** - Show Pantheon launch check status information
- **Debug Log Viewer** - Read and display WordPress debug logs without SSH access

### Phase 2: Development Workflow (Planned)

- **SFTP/Git Mode Toggle** - Switch between SFTP and Git mode without leaving WordPress (enables plugin/theme installation)
- **Upstream Updates** - Detect and apply upstream updates from WordPress admin
- **Code Deployment** - Push code to test/live environments
- **Multidev Management** - Create and manage multidev environments
- **Backup Control** - Create, list, and manage backups
- **Workflow Monitoring** - Track long-running Pantheon operations with status polling

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

After configuration, you'll find a **Pantheon** top-level menu in your WordPress admin with the following features (based on implementation phase):

- **Dashboard/Overview** - Environment status, launch check information, debug log viewer
- **Development** - SFTP/Git mode toggle, upstream updates, code deployment (Phase 2)
- **Backups** - Backup management (Phase 2, if implemented)
- **Settings** - Plugin configuration and token setup helper

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
- [ ] Launch check status information
- [ ] Error/debug log viewer

### Phase 2: Development Workflow Features
- [ ] SFTP/Git mode toggle
- [ ] Upstream update detection and application
- [ ] Code deployment (push to test/live)
- [ ] Multidev creation
- [ ] Backup management (create, list, restore if safe)
- [ ] Workflow status monitoring with polling

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

### 0.1.0 - In Development
- Initial plugin framework and structure
- Pantheon API client with authentication (machine token → session token exchange)
- Settings page with machine token configuration
- Dashboard page displaying:
  - Pantheon environment detection
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
