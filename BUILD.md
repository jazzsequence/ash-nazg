# Build Documentation

## SASS Build Pipeline

This project uses SASS for CSS styling with the Pantheon Design System (PDS) for design tokens.

### Directory Structure

```
assets/
├── css/
│   └── admin.css          # Compiled CSS (generated, do not edit directly)
├── sass/
│   ├── admin.scss         # Main SASS entry point
│   ├── _variables.scss    # Variables using PDS tokens
│   ├── _base/
│   │   └── _layout.scss   # Base layout styles
│   ├── _typography/
│   │   ├── _text.scss     # Text styling
│   │   └── _icons.scss    # Icon colors
│   ├── _components/
│   │   ├── _badges.scss           # Badge components
│   │   ├── _notices.scss          # Notice styles
│   │   ├── _edit-link.scss        # Inline edit link
│   │   ├── _workflow-cards.scss   # Workflow cards
│   │   └── _addon-toggles.scss    # Toggle switches
│   ├── _tables/
│   │   ├── _tables.scss   # Table styling
│   │   └── _avatars.scss  # Avatar images
│   ├── _pages/
│   │   ├── _dashboard.scss       # Dashboard page
│   │   ├── _logs.scss            # Logs page
│   │   ├── _development.scss     # Development page
│   │   └── _progress-modal.scss  # Progress modal
│   ├── _utilities/
│   │   ├── _display.scss   # Display utilities
│   │   ├── _spacing.scss   # Spacing utilities
│   │   └── _alignment.scss # Alignment utilities
│   └── _responsive/
│       └── _mobile.scss    # Mobile/tablet breakpoints
└── pds-design-tokens/
    └── pds-design-tokens-light-mode.css  # PDS tokens (generated, do not edit)
```

### Build Commands

```bash
# Install dependencies
npm install

# Build CSS (development mode - expanded)
npm run build

# Build CSS (production mode - compressed)
npm run build:css:compressed

# Watch for changes and rebuild automatically
npm run watch:css

# Copy PDS design tokens (runs automatically before build)
npm run copy:pds
```

### How It Works

1. **PDS Integration**: Design tokens are copied from `../pds-core/public/design-tokens/` before each build
2. **SASS Compilation**: SASS files are compiled to CSS using Dart Sass
3. **Design Tokens**: CSS custom properties from PDS are imported and used via SASS variables
4. **Output**: Compiled CSS is written to `assets/css/admin.css`

### Editing Styles

**DO NOT** edit `assets/css/admin.css` directly - it is generated from SASS files.

To modify styles:

1. Edit the appropriate SASS partial file in `assets/sass/`
2. Run `npm run build` to compile
3. Check the output in `assets/css/admin.css`

### SASS Module System

This project uses the modern SASS module system (`@use` instead of `@import`):

- Each partial imports variables with `@use '../variables' as *;`
- Variables are defined once in `_variables.scss` and reference PDS tokens
- Main file (`admin.scss`) imports all partials with `@use`

### PDS Design Tokens

The project uses Pantheon Design System tokens for:

- **Colors**: `--pds-color-*` (semantic, backgrounds, borders, text)
- **Elevation**: `--pds-elevation-*` (shadows)
- **Semantic tokens**: Success, error, warning, info, neutral

Custom values are used for:

- Environment badge colors (dev, test, live, multidev)
- WordPress admin compatibility colors
- Spacing values
- Font sizes
- Border radius values

### Git Workflow

1. Edit SASS files
2. Run `npm run build` to compile
3. **Run `composer check`** (runs PHPCS and PHPUnit)
4. Commit both SASS sources AND compiled CSS:
   ```bash
   git add assets/sass/ assets/css/admin.css
   git commit -m "Update styles: [description]"
   ```

### Ignored Files

The following are NOT tracked in git (see `.gitignore`):

- `node_modules/` - npm packages
- `package-lock.json` - npm lock file
- `assets/pds-design-tokens/` - generated PDS CSS file
