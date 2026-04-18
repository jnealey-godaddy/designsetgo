# Codebase Structure

**Analysis Date:** 2026-02-26

## Directory Layout

```
designsetgo/                        # WordPress plugin root
├── designsetgo.php                 # Plugin entry point, constants, activation hooks
├── uninstall.php                   # Cleanup on uninstall
├── package.json                    # Node dependencies, npm scripts
├── webpack.config.js               # Custom webpack extending @wordpress/scripts
├── composer.json                   # PHP dev dependencies (PHPCS, PHPStan)
├── phpcs.xml                       # PHP coding standards config
├── phpstan.neon                    # PHPStan static analysis config
├── jest.config.js                  # JS unit test config
├── playwright.config.js            # E2E test config
│
├── src/                            # All source code (compiled by webpack)
│   ├── index.js                    # Editor bundle entry (extensions + filters)
│   ├── style.scss                  # Frontend global CSS entry
│   ├── frontend.js                 # Frontend JS bundle entry
│   ├── block-category-icon.js      # Editor: branded SVG for designsetgo category
│   │
│   ├── blocks/                     # All custom blocks
│   │   ├── {block-name}/           # One directory per block
│   │   │   ├── block.json          # Block schema, supports, attributes
│   │   │   ├── index.js            # registerBlockType() call
│   │   │   ├── edit.js             # React editor component
│   │   │   ├── save.js             # Serialized HTML component
│   │   │   ├── view.js             # Frontend interactivity (optional)
│   │   │   ├── deprecated.js       # Migration array (optional)
│   │   │   ├── transforms.js       # Block transforms (optional)
│   │   │   ├── editor.scss         # Editor-only styles
│   │   │   ├── style.scss          # Frontend + editor styles
│   │   │   ├── components/         # Block-specific React components
│   │   │   │   └── inspector/      # Inspector panel components (optional)
│   │   │   └── utils/              # Block-specific utilities (optional)
│   │   │
│   │   └── shared/                 # Shared constants across blocks
│   │       └── components/
│   │           └── WidthPanel.js   # Shared width control panel
│   │
│   ├── extensions/                 # Cross-cutting block enhancements
│   │   └── {extension-name}/       # One directory per extension
│   │       ├── index.js            # Extension entry (imports attributes + editor)
│   │       ├── attributes.js       # addFilter('blocks.registerBlockType')
│   │       ├── editor.js           # addFilter('editor.BlockEdit') (inspector UI)
│   │       ├── components/         # Extension-specific React components
│   │       ├── hooks/              # Custom React hooks (optional)
│   │       └── utils/              # Extension utilities (optional)
│   │
│   ├── formats/                    # RichText formats
│   │   └── text-style/             # Inline text color/gradient/size format
│   │       ├── index.js
│   │       ├── editor.scss
│   │       ├── style.scss
│   │       ├── utils.js
│   │       └── components/
│   │
│   ├── components/                 # Global shared React components
│   │   └── ContrastNotice.js       # Accessibility contrast warning
│   │
│   ├── utils/                      # Global shared JS utilities
│   │   ├── index.js
│   │   ├── should-extend-block.js  # Extension allowlist/exclusion logic
│   │   ├── convert-preset-to-css-var.js
│   │   ├── encode-color-value.js   # Encode/decode WP color preset values
│   │   ├── css-generator.js
│   │   ├── contrast-checker.js
│   │   ├── breakpoints.js
│   │   ├── lazy-icon-library.js
│   │   ├── focus-outline.js
│   │   ├── is-valid-image-url.js
│   │   ├── sticky-header.js
│   │   └── sticky-header.scss
│   │
│   ├── styles/                     # Global SCSS partials
│   │   ├── style.scss              # Frontend global stylesheet entry
│   │   ├── editor.scss             # Editor global stylesheet entry
│   │   ├── _variables.scss         # CSS custom property definitions
│   │   ├── _mixins.scss            # SCSS mixins
│   │   ├── _utilities.scss         # Utility classes
│   │   ├── _animations.scss        # Animation definitions
│   │   ├── block-variations.scss   # Block style variation CSS
│   │   └── utilities/              # Additional utility partials
│   │
│   ├── admin/                      # Admin dashboard React app
│   │   ├── index.js                # Admin bundle entry
│   │   ├── assets/                 # Static assets for admin
│   │   ├── components/
│   │   │   └── settings-panels/    # Settings panel components
│   │   └── revisions/              # Visual revision comparison UI
│   │       ├── index.js
│   │       └── components/
│   │
│   ├── frontend/                   # Frontend-only scripts (not editor)
│   │   └── lazy-icon-injector.js   # Injects SVG icons from window.dsgoIcons
│   │
│   ├── llms-txt/                   # llms.txt editor panel
│   │   └── index.js
│   │
│   └── overlay-header/             # Overlay header editor panel
│       └── index.js
│
├── includes/                       # All PHP backend code
│   ├── class-plugin.php            # Singleton bootstrap + KSES allowlists
│   ├── class-assets.php            # Asset enqueueing + performance optimization
│   ├── class-extension-attributes.php # PHP mirror of JS extension attributes
│   ├── class-custom-css-renderer.php  # Renders per-block custom CSS
│   ├── class-section-styles.php    # Section block dynamic styles
│   ├── class-sticky-header.php     # Sticky header feature
│   ├── class-overlay-header.php    # Overlay header feature
│   ├── class-icon-injector.php     # Server-side icon SVG injection
│   ├── class-button-global-styles.php # Global button style defaults
│   ├── class-svg-pattern-renderer.php # SVG background pattern rendering
│   ├── helpers.php                  # Global helper functions
│   ├── icon-svg-library.php         # SVG icon data library
│   ├── svg-pattern-data.php         # SVG pattern definitions
│   ├── block-animation-attributes.php # Animation attribute helpers
│   ├── breadcrumbs-functions.php    # Breadcrumb generation helpers
│   │
│   ├── blocks/                     # Block-specific PHP handlers
│   │   ├── class-loader.php        # Auto-registers blocks from build/
│   │   ├── class-form-handler.php  # REST API form submission endpoint
│   │   ├── class-form-security.php # Honeypot, rate limit, Turnstile
│   │   ├── class-form-submissions.php # Submission storage (custom post type)
│   │   ├── class-modal-hooks.php   # Modal block PHP hooks
│   │   ├── blocks-registry.json    # Block registry metadata
│   │   └── blocks-registry-i18n.php # i18n strings for block registry
│   │
│   ├── admin/                      # Admin-only PHP classes
│   │   ├── class-admin-menu.php    # Admin menu registration
│   │   ├── class-settings.php      # Plugin settings (get/set via wp_options)
│   │   ├── class-block-manager.php # Enable/disable individual blocks
│   │   ├── class-gdpr-compliance.php # GDPR tools (data export/deletion)
│   │   ├── class-global-styles.php # Global styles management
│   │   ├── class-draft-mode.php    # Draft mode coordination
│   │   ├── class-draft-mode-rest.php # Draft mode REST endpoints
│   │   ├── class-draft-mode-admin.php # Draft mode admin UI
│   │   ├── class-draft-mode-preview.php # Draft mode preview handling
│   │   ├── class-revision-comparison.php # Visual revision diff
│   │   ├── class-revision-renderer.php # Renders blocks for diff
│   │   ├── class-revision-rest-api.php # Revision REST endpoints
│   │   ├── class-block-differ.php  # Block-level HTML diff
│   │   ├── class-block-migrator.php # Block attribute migrations
│   │   ├── css/, js/, views/       # Admin static assets and view templates
│   │
│   ├── extension-configs/          # PHP attribute schemas for extensions
│   │   ├── block-animations.php    # Mirrors src/extensions/block-animations attributes
│   │   ├── responsive.php
│   │   ├── max-width.php
│   │   └── ... (one per extension)
│   │
│   ├── abilities/                  # AI-native Abilities API
│   │   ├── class-abilities-registry.php # Auto-discovers + registers abilities
│   │   ├── class-abstract-ability.php   # Base class
│   │   ├── class-abstract-configurator-ability.php
│   │   ├── class-block-inserter.php
│   │   ├── class-block-configurator.php
│   │   ├── class-block-schema-loader.php
│   │   ├── class-css-sanitizer.php
│   │   ├── class-form-field-html-generator.php
│   │   ├── info/                   # Abilities that return information
│   │   ├── inserters/              # Abilities that insert blocks
│   │   ├── configurators/          # Abilities that configure blocks
│   │   └── generators/             # Abilities that generate layouts
│   │
│   ├── patterns/                   # Pattern loader
│   │   └── class-loader.php
│   │
│   ├── llms-txt/                   # llms.txt file management
│   │   ├── class-controller.php
│   │   ├── class-file-manager.php
│   │   ├── class-generator.php
│   │   ├── class-conflict-detector.php
│   │   └── class-rest-controller.php
│   │
│   └── markdown/                   # Markdown conversion for llms.txt
│       ├── class-converter.php
│       ├── class-core-handlers.php
│       └── class-dsgo-handlers.php
│
├── patterns/                       # Block pattern PHP registration files
│   ├── hero/                       # Category subdirectories
│   ├── cta/
│   ├── features/
│   ├── contact/
│   ├── faq/
│   ├── footer/
│   ├── gallery/
│   ├── header/
│   ├── headings/
│   ├── homepage/
│   ├── modal/
│   ├── pricing/
│   ├── services/
│   ├── team/
│   └── testimonials/
│
├── build/                          # Compiled assets (git-ignored, not committed)
│   ├── index.js                    # Editor extensions bundle
│   ├── frontend.js                 # Frontend bundle
│   ├── admin.js                    # Admin dashboard bundle
│   ├── blocks/                     # Per-block compiled assets
│   │   └── {block-name}/
│   │       ├── index.js            # Block editor script
│   │       ├── view.js             # Block frontend script
│   │       ├── style-index.css     # Block frontend CSS
│   │       ├── index.css           # Block editor CSS
│   │       ├── block.json          # Copied from src/
│   │       └── styles/             # Block style variation JSON files
│   ├── ext-*.js                    # Lazy-loaded extension chunks
│   ├── fmt-*.js                    # Lazy-loaded format chunks
│   └── *.asset.php                 # Webpack-generated dependency manifests
│
├── assets/                         # Plugin static assets (images, etc.)
├── languages/                      # Translation .po/.pot files
├── docs/                           # Developer documentation
├── tests/                          # All test files
│   ├── js/                         # JS unit tests
│   ├── unit/                       # PHP unit tests
│   ├── phpunit/                    # PHPUnit test suite
│   ├── e2e/                        # Playwright E2E tests
│   └── __mocks__/                  # JS module mocks
│
├── .claude/                        # Claude AI assistant context
│   └── docs/                       # Developer guides
│
└── .planning/                      # GSD planning documents
    └── codebase/                   # Codebase analysis documents
```

## Directory Purposes

**`src/blocks/`:**
- Purpose: Source for all 52 custom Gutenberg blocks
- Contains: One directory per block, each self-contained with block.json, edit/save/view JS, SCSS
- Key files: `src/blocks/section/`, `src/blocks/accordion/`, `src/blocks/form-builder/`, `src/blocks/grid/`, `src/blocks/row/`

**`src/extensions/`:**
- Purpose: Cross-cutting features injected into all blocks via WordPress hooks filters
- Contains: 16 extensions including `block-animations`, `responsive`, `max-width`, `custom-css`, `clickable-group`, `sticky-header-controls`, `vertical-scroll-parallax`, `draft-mode`
- Key files: `src/extensions/block-animations/attributes.js`, `src/extensions/responsive/index.js`

**`src/utils/`:**
- Purpose: Shared JS utilities used across multiple blocks and extensions
- Contains: `should-extend-block.js` (extension inclusion logic), `encode-color-value.js` (WP preset color encoding), `convert-preset-to-css-var.js`, `css-generator.js`, `breakpoints.js`

**`src/styles/`:**
- Purpose: Global SCSS variables, mixins, and utility classes
- Contains: `_variables.scss` (CSS custom properties), `_mixins.scss`, `_animations.scss`, `editor.scss` (editor global styles), `style.scss` (frontend global styles)

**`includes/`:**
- Purpose: All server-side PHP code
- Contains: Classes for plugin bootstrap, block registration, form handling, admin features, abilities API
- Key files: `includes/class-plugin.php`, `includes/blocks/class-loader.php`, `includes/class-assets.php`

**`includes/extension-configs/`:**
- Purpose: PHP attribute schema definitions that mirror each JS extension
- Contains: One PHP file per extension returning array with `blocks`, `exclude`, `attributes` keys
- Key pattern: Each file mirrors the attributes in the corresponding `src/extensions/{name}/attributes.js`

**`includes/abilities/`:**
- Purpose: AI-native programmatic block operations (WordPress Abilities API)
- Contains: `inserters/` (insert blocks), `configurators/` (set block attributes), `generators/` (generate layouts), `info/` (query block info)

**`patterns/`:**
- Purpose: Pre-built page section templates using DesignSetGo blocks
- Contains: PHP files using `register_block_pattern()`, organized by use-case category

**`build/`:**
- Purpose: Compiled production assets — do not edit directly
- Contains: Webpack output from `npm run build`; `.asset.php` manifests list dependencies and hash versions
- Generated: Yes
- Committed: No (in `.gitignore`)

**`tests/`:**
- Purpose: All automated tests
- Contains: `js/` (Jest unit), `phpunit/` (PHP unit), `e2e/` (Playwright), `__mocks__/` (Jest module mocks)

## Key File Locations

**Entry Points:**
- `designsetgo.php`: PHP plugin entry
- `src/index.js`: Editor JS entry (extensions)
- `src/style.scss`: Frontend CSS entry
- `src/frontend.js`: Frontend JS entry

**Configuration:**
- `webpack.config.js`: Build configuration
- `package.json`: npm scripts (`build`, `start`, `lint:js`, `lint:css`, `lint:php`)
- `phpcs.xml`: PHP coding standards
- `phpstan.neon`: PHPStan configuration
- `.wp-env.json`: Local WordPress environment
- `jest.config.js`: JS test runner configuration
- `playwright.config.js`: E2E test configuration

**Core Logic:**
- `includes/class-plugin.php`: All PHP service instantiation
- `includes/blocks/class-loader.php`: Block auto-registration
- `includes/class-assets.php`: Asset loading + performance optimization
- `includes/class-extension-attributes.php`: Extension attribute PHP mirror
- `src/utils/should-extend-block.js`: Extension inclusion/exclusion logic

**Block Schema:**
- `src/blocks/{name}/block.json`: Block definition (auto-copied to `build/blocks/{name}/block.json`)
- `includes/blocks/blocks-registry.json`: Block registry metadata

**Global Styles:**
- `src/styles/_variables.scss`: All CSS custom property definitions
- `src/styles/_mixins.scss`: SCSS mixins
- `src/styles/style.scss`: Frontend global stylesheet (must import extension frontend styles)
- `src/styles/editor.scss`: Editor global stylesheet (must import extension editor styles)

## Naming Conventions

**PHP Files:**
- Classes: `class-{kebab-name}.php` (e.g., `class-form-handler.php`)
- Helpers: `{kebab-name}-functions.php` or `{kebab-name}.php`
- Classes follow `PascalCase` in namespace: `DesignSetGo\Blocks\Form_Handler`

**PHP Classes:**
- Namespace: `DesignSetGo\` (root), `DesignSetGo\Blocks\`, `DesignSetGo\Admin\`, `DesignSetGo\Abilities\{Inserters|Configurators|Generators|Info}\`
- Class names: `PascalCase_With_Underscores` (WordPress convention)

**JS Files:**
- Utility functions: `kebab-case.js` (e.g., `encode-color-value.js`)
- React components: `PascalCase.js` (e.g., `ShapeDividerControls.js`)
- Block main files: `index.js`, `edit.js`, `save.js`, `view.js` (lowercase)

**CSS/SCSS:**
- Block wrapper class: `.dsgo-{block-name}` (e.g., `.dsgo-accordion`, `.dsgo-stack`)
- Block element class: `.dsgo-{block}__element` (BEM-style)
- Modifier class: `.dsgo-{block}--modifier`
- Extension classes: `.dsgo-hide-on-{device}`, `.dsgo-has-animation`, `.dsgo-has-parallax`
- CSS variables: `--dsgo-{property}` for custom, `--wp--preset--*` for WordPress presets
- Data attributes: `data-dsgo-{feature}-{property}`

**Block Names:**
- Block name in block.json: `designsetgo/{slug}` (e.g., `designsetgo/accordion`)
- Directory: `src/blocks/{slug}/` (e.g., `src/blocks/accordion/`)

## Where to Add New Code

**New Block:**
1. Create `src/blocks/{new-block-name}/block.json` with `"name": "designsetgo/{new-block-name}"`
2. Create `src/blocks/{new-block-name}/index.js`, `edit.js`, `save.js`
3. Create `src/blocks/{new-block-name}/editor.scss`, `style.scss`
4. Create `src/blocks/{new-block-name}/view.js` if frontend interactivity needed
5. Add editor styles import to `src/styles/editor.scss`
6. Add frontend styles import to `src/styles/style.scss`
7. Webpack auto-detects `src/blocks/*/index.js` — no webpack.config.js edit needed
8. PHP auto-discovers `build/blocks/*/block.json` — no PHP edit needed
9. Add tests to `tests/js/` and `tests/e2e/`

**New Extension:**
1. Create `src/extensions/{extension-name}/index.js`, `attributes.js`, `editor.js`
2. Add `import './extensions/{extension-name}';` to `src/index.js`
3. Create `includes/extension-configs/{extension-name}.php` returning attribute schema array
4. Add styles to `src/styles/style.scss` (frontend) and `src/styles/editor.scss` (editor) if needed

**New PHP Service:**
1. Create `includes/class-{service-name}.php` with namespace `DesignSetGo`
2. Add `require_once` in `Plugin::load_dependencies()`
3. Instantiate in `Plugin::init()` and assign to `$this->{property_name}`

**New Admin Feature:**
1. Create `includes/admin/class-{feature-name}.php` with namespace `DesignSetGo\Admin`
2. Add `require_once` in `Plugin::load_dependencies()`
3. Instantiate inside `if ( is_admin() )` block in `Plugin::init()` (unless REST API access needed)

**New Ability:**
1. Create `includes/abilities/{category}/class-{ability-name}.php`
2. Extend `Abstract_Ability` (for inserters/info) or `Abstract_Configurator_Ability` (for configurators)
3. Auto-discovered by `Abilities_Registry` — no registration required

**New Block Pattern:**
1. Create PHP file in appropriate `patterns/{category}/` directory
2. Call `register_block_pattern()` with proper `designsetgo` category reference

**Shared JS Utility:**
- Location: `src/utils/{utility-name}.js`
- Import with: `import { functionName } from '../../utils/{utility-name}';`

**Block-Specific Components:**
- Location: `src/blocks/{block-name}/components/` for sub-components
- Location: `src/blocks/{block-name}/components/inspector/` for inspector panel components
- Location: `src/blocks/{block-name}/utils/` for block-specific utilities

## Special Directories

**`build/`:**
- Purpose: Webpack compiled output
- Generated: Yes (by `npm run build` or `npm start`)
- Committed: No

**`languages/`:**
- Purpose: Translation files (.pot, .po, .mo) for i18n
- Generated: Partially (pot generated by `npm run make-pot`)
- Committed: Yes

**`vendor/`:**
- Purpose: Composer dependencies (WordPress Abilities API polyfill, PHPCS, PHPStan)
- Generated: Yes (by `composer install`)
- Committed: No (in `.gitignore`)

**`.planning/codebase/`:**
- Purpose: GSD codebase analysis documents for AI planning tools
- Generated: Yes (by GSD map-codebase commands)
- Committed: Yes

**`docs/`:**
- Purpose: Developer documentation for blocks, extensions, APIs
- Generated: No
- Committed: Yes

**`.claude/docs/`:**
- Purpose: Claude AI workflow guides (REFACTORING-GUIDE.md, FSE-COMPATIBILITY-GUIDE.md, etc.)
- Generated: No
- Committed: Yes

---

*Structure analysis: 2026-02-26*
