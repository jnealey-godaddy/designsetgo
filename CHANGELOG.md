# Changelog

All notable changes to the DesignSetGo plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.49] - 2026-04-12

### Fixed
- Form submissions rejected as "too fast" due to timestamp being set at submit time instead of page load time — anti-spam timing check now works correctly

## [2.0.48] - 2026-04-12

### Fixed
- Form submissions failing on GoDaddy and Cloudflare-hosted sites with "Unexpected token" JSON error — added admin-ajax.php fallback with three-tier submission (REST API → admin-ajax → native POST)
- Non-AJAX form submission path was not saving submissions or showing success messages — added admin_post handler
- Slider navigation arrows and dots not working in block editor — resolved iframe DOM scoping and pointer-events issues
- Phone field paste handler crash when browser extensions interfere with clipboard events

### Added
- SMTP plugin compatibility notice in Email Notifications panel
- User-friendly error messages for rate-limited form submissions
- Form status query params cleaned from URL after displaying messages

## [Unreleased]

### Removed
- **Visual Revision Comparison** - Deprecated and removed in favor of WordPress 7.0's native visual diff for revisions. Removed the visual comparison admin page, block differ, revision renderer, REST API endpoints (`/designsetgo/v1/revisions/*`), and associated settings (`revisions.enable_visual_comparison`, `revisions.default_to_visual`)

### New Blocks
- **Fifty Fifty** - Full-width 50/50 split layout with edge-to-edge media on one side and constrained content on the other — includes media position toggle (left/right), focal point picker, configurable min height, content vertical alignment, and mobile-responsive stacking
- **Query Group Header** - Sibling block for the Dynamic Query that renders once per group when group-by is enabled; hosts an InnerBlocks area for a heading or any custom content, and receives `designsetgo/groupLabel` + `designsetgo/groupValue` context so bindings can display the current group's name

### Added
- **Dynamic Query — Relationship source** - New `source: 'relationship'` reads a parent-context field (meta or ACF relationship) and iterates the referenced posts via `post__in`; configurable fallback when no IDs are resolved (render nothing, all posts, or the parent item)
- **Dynamic Query — Nested loops with parent context** - Outer Query's current item flows into inner Queries via the `designsetgo/parentItem` block context; DSGo bindings (`designsetgo/post-meta`, `designsetgo/acf`) accept a new optional `scope` arg of `'self'` (default), `'parent'`, or `'root'` — reading from a parent-stack maintained by `designsetgo_query_render_item()`
- **Conditional inner-block visibility** - Every block now carries a `dsgoVisibility` attribute with an inspector panel under Advanced → Visibility. Rule types: meta / taxonomy / index / auth, combined with AND/OR relations and ops (`equals`, `not_equals`, `contains`, `gt`, `lt`, `empty`, `not_empty`, `has`, `not_has`). Editor previews mirror server-side evaluation so authors see what will ship
- **Dynamic Query — Group-by partitioning** - New `groupBy` attribute on the Query block partitions iterated items by taxonomy / meta / date (with year / year-month / year-month-day precision); server wraps each group in `<section class="dsgo-query-group">` and renders the new Query Group Header block once per group
- **Dynamic Query — Meta Box, Pods, JetEngine binding sources** - Three new Block Bindings sources (`designsetgo/metabox`, `designsetgo/pods`, `designsetgo/jetengine`) that delegate to each plugin's canonical formatting API (`rwmb_meta()`, `pods_field()`, `jet_engine()->listings->data->get_meta()`) so formatted dates, files, and relations render correctly. Each registers only when the host plugin is active
- **`designsetgo_register_bindings_source()` helper** - Public PHP function wrapping `register_block_bindings_source()` with DSGo's shared post-password / viewable / protected-meta gates and the `scope` arg (`self`/`parent`/`root`). Lets child themes and mu-plugins ship custom binding sources without reimplementing the security gates
- **Dynamic Query — Template export/import** - REST routes `GET /designsetgo/v1/query/template` (export) and `POST /designsetgo/v1/query/template` (import) plus inspector Export/Import buttons in the Settings panel's Template I/O section. JSON format with `schemaVersion: 1`, attribute allowlist enforced via `WP_Block_Type_Registry`, and a fresh `queryId` generated on import so sibling bindings never collide

### Changed
- `designsetgo/post-meta` and `designsetgo/acf` binding sources accept an optional `scope` arg — defaults to `'self'`; existing bindings unchanged
- `designsetgo/post-meta` and `designsetgo/acf` internally refactored to use the new `designsetgo_register_bindings_source()` helper — single source of truth for security gates and scope resolution; externally observable behavior is identical

### Extension points
- `designsetgo_visibility_rule` filter — add custom rule types by returning a bool from `($match, $rule, $context) → bool|null`
- `$GLOBALS['designsetgo_parent_stack']` — ordered list of `{ postId, postType }` contexts available during any `render_block` hook fired inside a query item
- `designsetgo_query_partition_items( $post_ids, $group_spec )` — public PHP helper for custom group-by integrations
- `designsetgo_register_bindings_source( $slug, callable $callback, array $options )` — public PHP helper for registering custom DSGo-compatible binding sources with inherited gates and `scope` support
- `designsetgo_resolve_bindings_post_id( $args, $block )` — free function exposing the scope-aware post-ID resolution used by the helper, for callers that register via `register_block_bindings_source()` directly

## [2.0.0] - 2026-02-08

### New Blocks
- **Comparison Table** - Flexible product/plan comparison with 2–6 dynamic columns, checkmark/X/text cells, featured column highlighting, CTA buttons, and responsive horizontal scroll or stack layout
- **Timeline** - Chronological content display with vertical and horizontal orientations, alternating/left/right layouts, customizable markers (circle/square/diamond), scroll-triggered animations, and optional links
- **Advanced Heading** - Create headings with multiple font styles, weights, and colors using independent heading segments — each segment supports independent typography, color, and rich text formatting within a single semantic heading element (H1–H6)

### New Extensions
- **Grid Mobile Order** - Reorder grid items on mobile without changing the desktop layout or HTML structure — solves the zigzag stacking problem for alternating row designs
- **SVG Patterns** - Add 25+ repeatable SVG background patterns (simple, geometric, organic, decorative, architectural, technical) to sections and groups with customizable color, opacity, and scale — rendered as pure CSS for zero-JavaScript frontend performance

### Added
- **Shape Dividers for Sections** - 24 decorative shapes (waves, curves, triangles, peaks, clouds, zigzag, torn paper, and more) for top and bottom of Section blocks with customizable color, height, width, and flip options
- **Frontend Draft Preview Mode** - Administrators can browse the frontend and see draft content across all pages with a floating banner to toggle between preview and live views
- **Pattern Library** - 150+ reusable section patterns (hero, CTA, FAQ, features, gallery, team, testimonials, pricing, contact, and more) plus 12 complete homepage templates for SaaS, agency, restaurant, real estate, fitness, non-profit, events, education, and portfolio sites
- **Tabs hover colors** - Custom text and background color controls for tab hover states
- **Sticky Header text color on scroll** - Change text and link colors when the header scrolls, so transparent headers with light text can switch to dark text on a solid background
- **Modal hash link reopening** - Modals can now be reopened by clicking anchor links pointing to the same modal ID
- **6 new AI Abilities** - Configure any block's attributes, configure shape dividers, insert blocks into existing containers, and discover all available abilities via the Abilities API
- **4 new icons** - Dumbbell, fire, layers, and refresh icons added to the icon library
- **Reduced motion support** - Animations now respect the `prefers-reduced-motion` accessibility preference

### Improved
- Row block now supports vertical alignment (top, center, bottom, stretch, space-between)
- Section block vertical alignment now works properly when min-height is set
- Modal Trigger inherits WordPress button styles from theme.json and supports left/center/right/full alignment
- Icon Button link settings moved to inline toolbar with WordPress LinkControl for URL search and autocomplete, matching core Button block pattern
- Pattern loading optimized with caching and editor-only registration for faster page loads
- Code splitting with lazy loading for extensions and admin pages reduces initial bundle size and speeds up editor load
- Animation frontend performance optimized with shared IntersectionObserver instances and automatic garbage collection
- Section overflow handling simplified for better compatibility with navigation dropdowns and sticky elements

### Fixed
- Pill, Icon Button, Icon, and Modal Trigger blocks no longer float beside content in Group blocks
- Grid and Row blocks now properly go edge-to-edge with full-width alignment outside Section blocks
- Pill alignment now carries through Grid > Section nesting correctly
- Icon block no longer shows double-layered background color
- Card blocks no longer overflow in grid layouts
- Full-width video background alignment fixed in the editor
- Icon Button default focus outline removed
- Pill block no longer stretches to fill flex and grid containers
- Buttons and pills no longer stretch vertically in grid layout contexts
- Text alignment now works correctly in sections with content justification enabled
- Icon block vertical alignment and SVG rendering fixed in the editor
- Row block no longer overflows when padding or border is applied inside constrained containers
- Background images with URL query parameters now render correctly on the frontend
- Icon block sizing improved in editor with reduced min-height and min-width
- Draft mode no longer strips CSS display properties, SVG content, or accessibility attributes from block markup
- Modal trigger button padding now consistent with WordPress buttons; link-style triggers maintain compact styling on mobile
- Card block badge and overlay color controls now appear correctly in sidebar
- Sticky header no longer overrides custom button and element colors in non-navigation areas

### Internationalization
- Updated translation strings for v2.0.0 across all 9 supported languages (German, Spanish, French, Italian, Japanese, Dutch, Portuguese, Russian, Chinese) — includes new Advanced Heading, Heading Segment, SVG Patterns, pattern categories, and form field type strings

### Security
- Fixed potential XSS bypass in block attribute sanitization with double-encoding defense

## [1.4.1] - 2026-01-31

### Fixed
- Grid block type safety for WordPress 6.1+ blockGap object format conversion
- Grid block alignItems default now consistent between editor and frontend (uses 'stretch')
- Row block preset conversion with proper type checking
- Icon Button width attribute removed from schema (deprecation handles migration)
- Divider width no longer overridden by editor styles
- llms.txt conflict detection now includes dismissable notices with file resolution option

### Improved
- Icon Button now uses WordPress alignfull for full-width display
- llms.txt conflict handling allows renaming conflicting files via admin UI

## [1.4.0] - 2026-02-01

### Added
- **llms.txt Support** - Implements the [llms.txt standard](https://llmstxt.org/) to help AI language models understand site content
  - Serves a dynamic llms.txt file at the site root (e.g., `example.com/llms.txt`)
  - Admin settings in Features tab to enable/disable and select which post types to include
  - Per-page exclusion control via "AI & LLMs" panel in the block editor sidebar
  - Automatic markdown conversion of block content with smart block-type handlers
  - Static file generation option for improved performance
  - Conflict detection for existing llms.txt files
- **Draft Mode for Published Pages** - Create and manage draft versions of published content without affecting the live page
  - Auto-creates draft when editing published pages - no manual action needed
  - Captures unsaved edits and transfers them to the draft
  - Header bar controls for publishing or discarding changes
  - Sidebar panel showing draft status with link to live page
  - Confirmation modals for all destructive actions (create, publish, discard)
  - Full accessibility support with proper ARIA labels and keyboard navigation
- **Visual Revision Comparison** - Side-by-side rendered previews of post revisions
  - Visual comparison view with "Before" and "After" preview panels
  - Color-coded block highlighting: green (added), red (removed), yellow (modified)
  - WordPress-style revision slider with tick marks for navigation
  - Tab navigation between "Code Changes" and "Visual Comparison" views
  - Diff summary showing change counts
  - "Restore This Revision" works from both views
  - Admin settings to enable/disable and control default view
- **Block Exclusion System** - User-configurable system to prevent DSG extensions from being applied to specific third-party blocks
  - Added `shouldExtendBlock()` utility with memoization for performance (supports exact match and namespace wildcards like `gravityforms/*`)
  - New "Exclusions" admin UI tab for managing excluded blocks with validation and helpful examples
  - Smart defaults: Fresh installations exclude known problematic blocks (Gravity Forms, MailPoet, WooCommerce, Jetpack)
  - Existing installations maintain current behavior (no automatic exclusions)
  - All 13 extensions updated to check exclusion list before adding attributes

### Changed
- **Breaking**: Minimum PHP requirement bumped from 7.4 to 8.0 for improved security and performance

### Fixed
- Icon Button border-radius not displaying on frontend while working correctly in editor
- REST API validation conflicts with server-side rendered blocks like Gravity Forms
- Restored 14 missing icons to SVG library (blocks, checkbox, countdown, dropdown, flex, form, lightning, marquee, radio, reveal, send, slider, stack, toggle)

### Testing
- Added comprehensive test suite for forms, blocks, and utilities (1,695+ lines of tests)
  - Form Handler Security Tests: field validation, sanitization, IP extraction, rate limiting
  - Block Schema Validation Tests: naming conventions, attributes, supports, context relationships
  - Breakpoint Utilities Tests: media query generation, device detection
  - CSS Generator Utilities Tests: responsive CSS, spacing, unique IDs
  - Extension System Tests: attribute injection, excluded block handling

### Dependencies
- Bumped lodash from 4.17.21 to 4.17.23 (security)
- Bumped lodash-es from 4.17.21 to 4.17.23 (security)

## [1.3.2] - 2025-01-30

### Fixed
- Icon Button no longer displays double background layer when using rounded corners
- Stop overriding theme.json color palette, spacing presets, and font families - better theme compatibility
- Temporarily disable post content alignfull padding fix pending comprehensive solution

### Developer Experience
- Migrate commands to modern Claude Code skills format for improved automation
- Add Claude Code GitHub Workflow for CI/CD improvements

## [1.3.1] - 2025-01-09

### Fixed
- Slider initialization timing - fixed first-load issues where sliders showed gaps or incorrect positioning before reload
- Scroll Gallery (Marquee) initialization timing - fixed first-load issues where gallery wouldn't scroll until page reload
- Both blocks now properly wait for images to load and CSS to apply before calculating dimensions

## [1.3.0] - 2025-12-06

### New Features - WordPress Abilities API
- New: **50 AI abilities** for programmatic block manipulation via WordPress 6.9 Abilities API
- New: First WordPress block plugin to fully integrate with the Abilities API

### Abilities API - Discovery (1)
- `designsetgo/list-blocks` - List all available blocks with schemas

### Abilities API - Inserters (29)
**Containers:** insert-flex-container, insert-grid-container, insert-stack-container
**Visual:** insert-icon, insert-icon-button
**Dynamic:** insert-progress-bar, insert-counter-group
**Interactive:** insert-tabs, insert-accordion, insert-flip-card, insert-reveal, insert-scroll-accordion
**Content:** insert-icon-list, insert-icon-list-item, insert-scroll-marquee
**Modal:** insert-modal, insert-modal-trigger
**Media:** insert-slider, insert-card, insert-image-accordion
**Page Structure:** insert-section, insert-divider, insert-breadcrumbs, insert-table-of-contents
**Data Display:** insert-counter, insert-countdown-timer, insert-map
**UI Elements:** insert-pill, insert-form-builder

### Abilities API - Configurators (10)
**Animations:** apply-animation, configure-counter-animation
**Scroll Effects:** apply-scroll-parallax, apply-text-reveal, apply-expanding-background
**Extensions:** configure-background-video, configure-clickable-group, configure-custom-css, configure-responsive-visibility, configure-max-width

### Abilities API - Generators (10)
- generate-hero-section, generate-feature-grid, generate-stats-section, generate-faq-section, generate-contact-section
- generate-pricing-section, generate-team-section, generate-testimonial-section, generate-cta-section, generate-gallery-section

### New Extensions
- New: Scroll Parallax extension - Elementor-style vertical/horizontal parallax effects with per-device controls
- New: Text Reveal extension - scroll-triggered text color animation that simulates natural reading progression
- New: Expanding Background extension - scroll-driven background that expands from a small circle to fill sections

### New Features
- New: Text Style inline format - apply colors, gradients, font sizes, and highlights to selected text
- New: Cloudflare Turnstile integration for form spam protection

### WordPress 6.9 Compatibility
- Enhancement: Conditionally load Abilities API polyfill only for WordPress < 6.9
- Enhancement: Updated "Tested up to" to WordPress 6.9

### Improvements
- Enhancement: Icon Button now respects WordPress width constraints and inherits theme.json button styles
- Enhancement: Icon Button properly integrates with FSE button settings (colors, padding, border-radius)
- Enhancement: Admin settings page now properly displays translations for all supported languages

### Bug Fixes
- Fix: Icon Button display and width issues in constrained layouts
- Fix: Admin settings page translation loading
- Fix: Added missing wp_set_script_translations() call for admin JavaScript bundle
- Fix: Correct form submissions link URL and post type prefix

### Documentation
- Docs: Added comprehensive documentation for all new extensions and formats
- Docs: Updated Abilities API documentation with complete reference for all 50 abilities

## [1.2.1] - 2025-11-24

### New Features
- New: Form submissions admin now displays email delivery status (sent/failed) with visual indicators
- New: Detailed email delivery information in submission sidebar (recipient, date, status)
- New: Data retention enforcement and configurable anti-abuse settings for form submissions
- New: Missing blocks and extensions now properly display in admin Dashboard

### Security Fixes
- Security: Added CSRF protection for form submissions to prevent cross-site request forgery attacks
- Security: Restricted form submissions to admin-only access for better data protection
- Security: Implemented trusted proxy IP resolution to prevent IP spoofing in rate limiting

### Performance
- Performance: Implemented lazy loading for icon library - critical optimization reducing initial bundle size

### Bug Fixes
- Fix: Form email deliverability - changed From address default from admin email to wordpress@{sitedomain} to match WordPress core and prevent SPF/DKIM/DMARC failures
- Fix: Form validation, rate limiting, and email tracking issues resolved
- Fix: Email status display bug in admin dashboard
- Fix: Admin dashboard capability check error preventing proper access control
- Fix: Admin dashboard handling of blocks data preventing crashes

### Enhancements
- Enhancement: Added debug logging to track email notification flow and diagnose sending issues
- Enhancement: Updated From Email helper text to reflect new domain-matched email default

## [1.2.0] - 2025-11-21

### New Features
- New: Breadcrumbs block with Schema.org markup for improved SEO and navigation
- New: Table of Contents block with automatic heading detection, smooth scrolling, and sticky positioning
- New: Modal/Popup block with accessible triggers, animations, and gallery support
- Enhancement: Modal close triggers and improved icon-button UX with better accessibility

### Bug Fixes
- Fix: Table of Contents critical production readiness fixes for stable performance
- Fix: Table of Contents sticky positioning and scroll spy highlighting functionality
- Fix: Table of Contents error handling in view.js for better reliability
- Fix: Prevent sticky header from affecting footer template parts

### Security
- Security: Fixed 3 critical vulnerabilities in Modal block + performance optimizations

### Internationalization
- i18n: Added modal block translations to all language files
- i18n: Updated translation strings for modal close functionality

### Documentation
- Docs: Reorganized and created comprehensive block/extension documentation

### Maintenance
- Maintenance: Updated dependencies (glob 10.4.5 → 10.5.0)
- Maintenance: Optimized screenshot-1.gif (24MB → 5.7MB)
- Maintenance: Updated WordPress.org assets and screenshots

## [1.1.4] - 2025-11-19

### Bug Fixes
- Fix: Slider initialization on uncached first load - sliders now display correctly on first page visit
- Fix: Critical race condition in image loading detection that could cause 3-second initialization delays
- Fix: Memory leak from uncleaned setTimeout timers in slider initialization
- Fix: Double-counting bug in slider image load detection that could prevent initialization

### Performance Improvements
- Performance: Eliminated redundant DOM queries in slider initialization
- Performance: Optimized Array.from conversions for better memory efficiency

### Code Quality
- Docs: Added comprehensive JSDoc documentation for slider initialization functions
- Docs: Enhanced inline comments explaining race condition prevention and error handling

## [1.1.3] - 2025-11-16

### Performance Improvements
- Performance: Major CSS loading strategy optimization - improved enqueue logic and selective loading (#93)
- Performance: Fixed forced reflows in JavaScript and optimized asset loading strategy (#91)
- Performance: Eliminated layout thrashing by batching DOM reads/writes and deferring non-critical operations

### Bug Fixes
- Fix: Flip card back panel now correctly displays background color and text in editor (#94)
- Fix: Added alignment options to countdown timer block for better layout control (#95)

### Documentation
- Docs: Updated WordPress.org screenshots to reflect current plugin features

## [1.1.2] - 2025-11-15

### New Features
- New: Added five comprehensive filter hooks for Custom CSS customization
  - `designsetgo/custom_css_block` - Modify CSS per block before processing
  - `designsetgo/custom_css_class_name` - Customize CSS class name generation
  - `designsetgo/custom_css_sanitize` - Additional sanitization rules
  - `designsetgo/custom_css_processed` - Post-process CSS after sanitization
  - `designsetgo/custom_css_output` - Control final CSS output
- New: Comprehensive developer documentation with 16+ practical examples in docs/CUSTOM-CSS-FILTERS.md

### Bug Fixes
- Fix: Section hover background now correctly renders behind content instead of over text
- Fix: Resolved z-index stacking issue where hover overlay appeared above section content

### Enhancements
- Enhancement: Improved Custom CSS textarea UX with better styling and increased height
- Enhancement: Added block name tracking to Custom CSS data structure for better debugging
- Documentation: Enhanced PHPDoc comments with detailed filter hook usage examples

## [1.1.1] - 2025-11-15

### Security Fixes
- Security: Fixed HIGH severity string escaping vulnerability in counter number formatting (CVE alerts #15-18)
- Security: Added escapeReplacement() function to prevent injection via replacement string special sequences
- Security: Enhanced GitHub Actions workflows with explicit permissions following principle of least privilege

### Changes
- Fix: Escape special characters in separator strings used by Counter and Counter Group blocks
- Enhancement: Added explicit permissions blocks to all GitHub Actions workflows for improved security posture

## [1.1.0] - 2025-11-14

### New Blocks
- New: Card block with multiple layout presets (horizontal, vertical, overlay, compact, featured)
- New: Map block with Google Maps and OpenStreetMap support, privacy mode, and customizable markers

### Admin Interface Overhaul
- New: Completely redesigned admin dashboard with stat cards showing blocks, extensions, and form submissions
- New: Enhanced dashboard displays blocks organized by category and extension status pills
- New: Tabbed settings interface organized into Features, Optimization, and Integrations tabs
- New: Google Maps API key management in Settings > Integrations with security guidance
- Enhancement: Two-column grid layouts for improved settings panel space efficiency
- Enhancement: Gradient icon stat cards with hover effects for better visual hierarchy
- Enhancement: Collapsible sections for advanced settings to reduce vertical scroll

### Translations
- Enhancement: Added translation support for 9 languages (Spanish, French, German, Italian, Portuguese, Dutch, Russian, Chinese, Japanese)
- Enhancement: Updated POT file with 100% translation coverage for all admin strings

### Security & Bug Fixes
- Security: Fixed js-yaml prototype pollution vulnerability (CVE-2023-2251)
- Fix: Added missing ToggleControl import to Card block editor component
- Fix: Google Maps API key now persists correctly after save/reload
- Fix: API key properly exposed to frontend via data attributes with secure referrer-based protection

## [1.0.1] - 2025-11-14

### Documentation
- Docs: Streamlined readme.txt with JTBD-focused messaging for better scannability
- Docs: Condensed description from 516 to 339 lines while keeping essential information
- Docs: Reordered FAQ to address user anxiety barriers first

## [1.0.0] - 2025-11-12

🚀 **Initial Release**

### 43 Professional Blocks
- 5 Container blocks (Row, Section, Flex, Grid, Stack)
- 13 Form Builder blocks (complete system with AJAX, spam protection, email notifications)
- 10 Interactive blocks (Tabs, Accordion, Flip Card, Slider, Counters, Progress Bar, Scroll effects)
- 8 Visual blocks (Icons, Icon Button, Icon List, Card, Pill, Divider, Countdown Timer, Blobs)
- 9 Child blocks (Tab, Accordion Item, Slide, Flip Card Front/Back, Icon List Item, Image Accordion Item, Scroll Accordion Item, Counter)

### 11 Universal Extensions
(work with ANY block)
- Block Animations (24+ effects with scroll triggers)
- Sticky Header (FSE-optimized with offset controls)
- Clickable Groups (accessible card/container links)
- Background Video (YouTube and self-hosted)
- Responsive Visibility (hide by device)
- Max Width (content width constraints)
- Custom CSS (per-block styling)
- Grid Span (column/row control)
- Reveal Control (advanced hover effects)
- Text Alignment Inheritance (parent-child context)

### Performance & Quality
- Built with WordPress core patterns for guaranteed editor/frontend parity
- Optimized bundles, no jQuery, code-splitting
- WCAG 2.1 AA accessible with full keyboard navigation
- FSE compatible with theme.json integration
- Comprehensive documentation and developer guides

### Requirements
- WordPress 6.0 or higher
- PHP 8.0 or higher
- Modern browser with JavaScript enabled

---

[2.0.0]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v2.0.0
[1.4.1]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.4.1
[1.4.0]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.4.0
[1.3.2]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.3.2
[1.3.1]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.3.1
[1.3.0]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.3.0
[1.2.1]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.2.1
[1.2.0]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.2.0
[1.1.4]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.1.4
[1.1.3]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.1.3
[1.1.2]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.1.2
[1.1.1]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.1.1
[1.1.0]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.1.0
[1.0.1]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.0.1
[1.0.0]: https://github.com/jnealey-godaddy/designsetgo/releases/tag/v1.0.0
