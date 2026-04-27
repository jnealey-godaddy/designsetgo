=== DesignSetGo ===
Contributors: justinnealey
Donate link: https://designsetgoblocks.com/donate
Tags: blocks, gutenberg, form-builder, query-loop, animations
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

53 native blocks + 16 universal extensions for the WordPress block editor. Forms, dynamic post lists, animations, layouts — no page builder needed.

== Description ==

**The power of a page builder, the simplicity of native blocks.**

DesignSetGo brings forms, sliders, dynamic queries, animations, and parallax to the block editor — without the bloat, lock-in, or learning curve. If you know WordPress blocks, you already know how to use it.

[Documentation](https://designsetgoblocks.com/docs/) · [GitHub](https://github.com/designsetgo/designsetgo)

= Why DesignSetGo =

* **Native blocks, not a page builder.** Editor matches frontend. Static content stays put if you deactivate; dynamic blocks need the plugin to render. No proprietary markup, no lock-in.
* **53 blocks replace 5+ plugins.** Forms, sliders, tabs, accordions, modals, maps, breadcrumbs, timelines, comparison tables, and the new Dynamic Query family.
* **16 extensions enhance ANY block** — including core and third-party blocks. Animations, parallax, sticky headers, responsive visibility, hover effects, conditional visibility.
* **Complete form builder built in.** AJAX, spam protection (Cloudflare Turnstile included), email notifications, submission dashboard. No Contact Form 7 required.
* **Performance first.** CSS bundle under 10 KB gzipped, no jQuery, per-block on-demand assets. PageSpeed scores stay high.
* **WordPress-standard everything.** theme.json, FSE, Block Bindings, REST API, WP-CLI, Schema.org markup, WCAG 2.1 AA accessible.

= New in 2.1 =

* **Dynamic Query** — display any posts, users, terms, or relationship fields with filters, search, sort, and pagination (numbered, load-more, or infinite scroll). Faceted result counts, nested loops, group-by, server-rendered with editable templates.
* **Dynamic Tags** — bind any block's text, link, or image to live data: post meta, ACF, Meta Box, Pods, JetEngine, or your own source. Works on DesignSetGo blocks and core blocks via the WordPress 6.9+ Block Bindings API.
* **Conditional Visibility** — show or hide any block by meta, taxonomy, login state, or position in a query loop, with AND/OR rules.
* **Hover Effects** extension, grid column toolbar with row span, per-URL Markdown for AI clients via the `Accept: text/markdown` request header.

= What's Inside =

* **Layout** (3) — Grid, Row, Section with shape dividers
* **Forms** (13) — full builder with 11 field types and admin dashboard
* **Interactive** (15) — Tabs, Accordion, Modal, Modal Trigger, Flip Card, Slider, Scroll Slides, Sticky Sections, Scroll Marquee, Scroll Accordion, Image Accordion, Counter, Progress, Comparison Table, Timeline
* **Dynamic Query** (6) — Query, Pagination, Filter, Results, Group Header, No Results
* **Typography & Navigation** — Advanced Heading, Breadcrumbs (Schema.org), Table of Contents
* **Visual** (9) — 500+ Icons, Icon Button, Icon List, Pills, Cards, Dividers, Countdown, Blobs, Dynamic Image
* **Media & Location** — Fifty Fifty split layout, Map (Google Maps + OpenStreetMap)
* **WooCommerce** — Product Categories Grid, Product Showcase Hero
* **Extensions** (16) — Animations, Parallax, Text Reveal, Expanding Background, Sticky Header, Hover Effects, Clickable Groups, Background Video, Responsive Visibility, Conditional Visibility, Max Width, Custom CSS, Grid Span, Grid Mobile Order, SVG Patterns (25+), Reveal Control
* **Plus** — Text Style inline format, llms.txt + per-URL Markdown for AI, form submissions dashboard, draft mode for published pages

== Installation ==

1. **Plugins → Add New**, search **DesignSetGo**, click **Install**, then **Activate**.
2. Edit any post or page, click **+**, and look for the **DesignSetGo** category.

Manual install: upload the ZIP via **Plugins → Add New → Upload Plugin**.

== Frequently Asked Questions ==

= Will it work with my theme? =

Yes. DesignSetGo respects theme.json colors, spacing, and typography, and is tested with FSE themes including Twenty Twenty-Five.

= Will it slow my site down? =

No. CSS is under 10 KB gzipped, there's no jQuery, and per-block assets load on-demand.

= What happens if I deactivate it? =

Your content stays intact. DesignSetGo uses WordPress standards, so layouts render as standard containers — no broken markup, no lock-in.

= Do I need to know code? =

No. Everything is controlled through the block inspector. Custom CSS per block is supported if you want it.

= Does it work with FSE and WooCommerce? =

Yes to both. All blocks work in the Site Editor, templates, and template parts. Use DesignSetGo blocks on any WooCommerce page; the Sticky Header extension is built for FSE header parts.

= Where do I get support? =

[Documentation](https://designsetgoblocks.com/docs/), the [support forum](https://wordpress.org/support/plugin/designsetgo/), or [GitHub](https://github.com/designsetgo/designsetgo).

== Screenshots ==

1. Container block with responsive grid layout and video background support
2. Tabs block with horizontal orientation, icons, and multiple style options
3. Accordion block with collapsible panels and smooth animations
4. Counter Group block with animated statistics and number formatting
5. Icon block with 500+ icons, shape styles, and customization options
6. Progress Bar block with animated fills and multiple display styles
7. Block animation controls showing entrance effects and timing options
10. Mobile responsive preview in the editor

== Changelog ==

= 2.1.1 - 2026-04-27 =

* **Fix:** Eliminates `_load_textdomain_just_in_time` PHP notices on WordPress 6.7+. Dynamic Tags default group registration now defers to `after_setup_theme`, and Abilities API registrations defer to `init` when those hooks fire before translations are loaded. Recommended for all sites; no content or settings changes required. (Props @ncimbaljevic-godaddy)

= 2.1.0 - 2026-04-24 =

**New Blocks**
* **New:** Dynamic Query — a full-featured query block that iterates Posts, Users, Terms, Manual selections, or the Current archive, with tax_query, meta_query, search, author, date, and offset controls. Renders entirely server-side with an editable template and pluggable sources.
* **New:** Query Pagination — numbered, load-more, or infinite-scroll pagination variations (infinite scroll uses `IntersectionObserver` and auto-pauses after 3 loads, respecting `prefers-reduced-motion`).
* **New:** Query Filter — 6 variations (checkbox, select, search, sort, active-filters, reset) with per-option result counts that update as other filters change.
* **New:** Query No Results — content shown when a query returns zero items.
* **New:** Query Group Header — renders once per group when group-by is enabled, with `designsetgo/groupLabel` + `designsetgo/groupValue` context for bindings.
* **New:** Query Results — the child renderer block split out of Dynamic Query so non-grid layout hosts (Slider, Scroll Slides) can take over rendering while sharing the same source and filters.

**Dynamic Tags — bind any block to dynamic data**
* **New:** Dynamic Tags — an Elementor-style picker on the block toolbar that binds text, titles, URLs, and images to live data (post meta, ACF, Meta Box, Pods, JetEngine, or any custom source). Live preview in the editor, and works on DesignSetGo blocks plus any core block that opts into WordPress 6.9's Block Bindings API.
* **New:** Native Block Bindings support on DesignSetGo blocks for WordPress 6.9+ — Advanced Heading Segment, Breadcrumbs home/prefix text, and Query Pagination labels are now bindable out of the box.
* **New:** Third-party field sources for Meta Box, Pods, and JetEngine — formatted dates, files, and relationships render correctly because each source delegates to the host plugin's own formatting API. Each source only registers when its host plugin is active.
* **New:** DesignSetGo post-meta and ACF binding sources — always available, with an optional `scope` arg (self / parent / root) for nested loops.

**Dynamic Query — filters, grouping, nested loops, and more**
* **New:** Relationship source — point a Dynamic Query at a relationship field (meta or ACF) and it iterates the referenced posts. Configurable fallback when no IDs are resolved.
* **New:** Nested loops with parent context — an outer Query's current item flows into inner Queries via a shared parent stack, so bindings in the inner loop can read the outer item's fields via a new `scope` setting (self / parent / root).
* **New:** Group-by partitioning — split iterated items by taxonomy, meta, or date (year / year-month / year-month-day). Each group is wrapped in its own `<section>` with the new Query Group Header block rendered once per group.
* **New:** Date Query builder — before / after / between filters with relative expressions (`-30 days`, `today`, ISO dates).
* **New:** Multi-level AND/OR filter groups in both the Taxonomy and Meta clause builders.
* **New:** Per-clause "Include children" toggle on taxonomy filters.
* **New:** Filter index powering sub-millisecond per-option counts on Dynamic Query filters (`(N)` counts next to each option, intersection-aware across multiple active filters).
* **New:** Settings → DesignSetGo → Dynamic Query admin dashboard — rebuild filter index and manage ad-hoc filter registrations.
* **New:** WP-CLI commands: `wp dsgo query index rebuild/rebuild-filter/status/drop`.
* **New:** Editor live preview for Dynamic Query — real posts, users, and terms render in the editor with the first item's template editable.
* **New:** Template picker onboarding on fresh Dynamic Query inserts (Minimal, Blog Index, Team, Portfolio, Testimonials, Related Posts, Events).
* **New:** Template export/import as JSON — share a configured Dynamic Query (or template part within one) between sites via REST + inspector buttons.
* **New:** Query-bound Slider and Scroll Slides — both blocks can now iterate Dynamic Query items as slides, with editor/frontend parity.
* **New:** Query Monitor integration — when Query Monitor is active, a "DSGo (N)" panel shows per-render query args, found-posts count, duration, and the actual SQL.
* **New:** CSS-only loading skeletons during filter/pagination refreshes (shown via `aria-busy="true"` state).
* **New:** ItemList schema.org markup for Posts queries (on by default, togglable per block).
* **New:** REST endpoints for headless / AJAX consumption — `/designsetgo/v1/query/render`, `/preview`, `/filter-register`, `/filter-status`, `/filter-rebuild`, `/filters`, `/template`.

**Conditional visibility**
* **New:** Every block now has an Advanced → Visibility panel. Show or hide a block based on meta, taxonomy, the current item's index in a query loop, or whether the visitor is logged in. Combine rules with AND/OR and operators like equals / contains / gt / lt / empty. Editor previews mirror what ships on the frontend.

**Per-URL Markdown**
* **New:** Per-URL Markdown content negotiation — any published page or post URL returns Markdown when a client sends `Accept: text/markdown`. Passes the [acceptmarkdown.com](https://acceptmarkdown.com/) readiness contract. Respects the llms.txt enablement flag, post-type allowlist, per-page exclusion, and password-protected posts.

**New Extension**
* **New:** Hover Effects — animated hover interactions that work on any block, including core.

**Editor UX foundations (Themes 1–6)**
* **Improved:** Unified first-insert placeholder & onboarding across compound blocks (accordion, flip-card, image-accordion, scroll-accordion, slider).
* **Improved:** Flip Card — front/back child blocks consolidated into a single Flip Card Face block with a side attribute and starter colors.
* **Improved:** Inspector IA standardized across the library — every block's sidebar uses the same Settings → Style → Advanced panel structure, with per-control reset-to-default.
* **Improved:** Discoverability polish — block icons, category registration, and naming cleaned up across ~30 blocks.
* **Improved:** Shared tablist keyboard navigation and child block toolbar (Add / Duplicate / Move / Remove) rolled out to Tabs and Slider.

**Editor UX — new controls and polish**
* **New:** Grid column toolbar — pick 1–6 columns directly from the Grid block's toolbar (dropdown above 6).
* **New:** Grid row span — grid children can now span multiple rows alongside the existing column span.
* **Improved:** Dynamic Image — new inspector layout with a sticky footer, live editor preview, and Select-based controls for every finite-option setting.
* **Improved:** Form builder now persists the confirmation message across page reloads, so submitters still see the thank-you after a refresh.
* **Improved:** Distinct titles for taxonomy / meta / date filter panels, visible unchecked checkboxes, optional horizontal orientation, and modern filter inputs that inherit theme.json presets.

**Bug Fixes**
* **Fix:** Heading Segment default gap is now 0 so adjacent segments read as a single heading.
* **Fix:** Section clears its default padding automatically when nested inside another Section.
* **Fix:** Row — inner `flex-direction` flips correctly on mobile stack.
* **Fix:** Sticky header — smooth logo shrink transition in both scroll directions.
* **Fix:** Sticky header — a typo in the custom selector setting no longer breaks frontend JavaScript; invalid selectors silently fall back to the default header detection.
* **Fix:** llms.txt generation now writes reliably on managed hosts (WP Engine, Kinsta, Pantheon) — file writes route through the WordPress filesystem API with a safe fallback.
* **Fix:** Advanced Heading segment appender restored on the canvas.
* **Fix:** Inspector panel controls render full-width correctly; Tabs `activeTab` index clamped defensively on editor and frontend.
* **Fix:** Abilities API add-block output round-tripped through `save()` to prevent block validation failures.
* **Fix:** Abilities JSON Schema — inline `required:true` migrated to JSON Schema compliant form.

**Security**
* **Security:** Form submissions — redirect URL normalized and validated before navigation (blocks `javascript:` and other unsafe protocols).
* **Security:** Draft Mode REST routes now require nonce verification on their permission callbacks.
* **Security:** Dynamic CSS style bindings block dangerous values (`url(`, `expression(`, `javascript:`) and enforce a property allowlist so bindings can't leak behavioral styles.
* **Security:** Global Styles values are validated against a CSS-value allowlist before being saved — every functional CSS context (var, calc, clamp, min, max, rgb, hsl) rejects `url(`, `expression(`, and `javascript:` payloads.
* **Security:** Sticky header custom selector setting rejects HTML angle brackets and known CSS injection patterns (`javascript:`, `expression(`, `url(`, `@import`) before the value reaches the frontend.

**Removed**
* **Removed:** Visual Revision Comparison — WordPress 7.0 ships native visual diffs for revisions, so the custom admin page, block differ, REST endpoints, and associated settings have been removed.

**Developer**
* `designsetgo_register_bindings_source( $slug, $callback, $options )` — public helper to register custom binding sources with DSGo's post-password, viewable, protected-meta, and scope gates built in.
* `designsetgo_resolve_bindings_post_id( $args, $block )` — scope-aware post-ID resolution for callers that use the core binding registration API directly.
* `designsetgo_visibility_rule` filter — add custom visibility rule types.
* `designsetgo_query_partition_items( $post_ids, $group_spec )` — public helper for custom group-by integrations.
* `designsetgo_query_args` + `designsetgo/query/{queryId}/args` — pre-WP_Query filter hooks (scoped or global).
* `designsetgo_query_registered_filters` — programmatic filter registration for the Dynamic Query filter index.
* `designsetgo_block_bindings_supported_attributes` — extend native Block Bindings coverage to additional DSGo block attributes.

= 2.0.51 - 2026-04-16 =
**Editor UX Improvements:**
* Slider: new editor-only slide navigator strip below the track with per-slide duplicate/remove actions and an "Add slide" button
* Slider: the slide "+" appender is pinned to the bottom-center of each slide so it no longer collides with the editor preview arrows
* Form Builder: skippable first-insert template chooser with Blank, Contact, Newsletter, Event Registration, and Lead Capture presets
* Form Builder: "Reply-To Field" is now a structured dropdown populated from the actual form fields (was a raw text input)
* Image Accordion: "Default Expanded Item" is now a named item picker showing each item's heading text (was a 0–10 numeric slider)
* Tabs: inline-editable tab titles in the nav strip, per-tab duplicate/remove on hover, and an "Add tab" button
* Advanced Heading: the segment appender is restored so authors can add more heading segments from the canvas

**Security Hardening:**
* Validate background-video overlay color against an explicit CSS color grammar before assigning to the DOM — blocks url()/expression()/javascript: injection
* Replace innerHTML with DOM APIs (createElement/createElementNS) in slider and modal frontend scripts
* Gate LLMS markdown REST endpoint at feature-disabled check before rate-limiter to prevent post-existence enumeration on disabled installations
* Normalize CSS unicode escapes and null bytes before the custom CSS sanitizer's regex pipeline; add a final defense pass after the filter hook

**Bug Fixes:**
* Fix: Tabs frontend no longer shows "Click the + button below to add content to this tab" — the block.json style asset was pointing at the editor CSS bundle
* Fix: an empty Form Builder (placeholder dismissed without picking a template) no longer renders an orphan submit button on the frontend

= 2.0.50 - 2026-04-14 =
**Bug Fixes:**
* Fix: Form submissions not sending email notifications — server-side block attribute lookup now honors block.json defaults so forms with default settings correctly trigger admin email on submit

= 2.0.49 - 2026-04-12 =
**Bug Fixes:**
* Fix: Form submissions rejected as "too fast" due to timestamp being set at submit time instead of page load time — anti-spam timing check now works correctly

= 2.0.48 - 2026-04-12 =
**Bug Fixes:**
* Fix: Form submissions failing on GoDaddy and Cloudflare-hosted sites with "Unexpected token" JSON error — added admin-ajax.php fallback with three-tier submission (REST API → admin-ajax → native POST)
* Fix: Non-AJAX form submission path was not saving submissions or showing success messages — added admin_post handler
* Fix: Slider navigation arrows and dots not working in block editor — resolved iframe DOM scoping and pointer-events issues
* Fix: Phone field paste handler crash when browser extensions interfere with clipboard events

**Improvements:**
* Enhancement: SMTP plugin compatibility notice in Email Notifications panel
* Enhancement: User-friendly error messages for rate-limited form submissions
* Enhancement: Form status query params cleaned from URL after displaying messages

= 2.0.47 - 2026-04-03 =
**Bug Fixes:**
* Fix: Soft-reload support for sticky header, form builder, and phone field — blocks now re-initialize correctly after soft page navigation
* Fix: Re-initialize icons and scripts on soft page reload
* Fix: Add initialization guards for countdown-timer, counter-group, and progress-bar to prevent double-init
* Fix: Tear down parallax scroll/resize listeners and disconnect orphaned IntersectionObserver before re-init
* Fix: Refresh form timestamp at submit time instead of init time
* Fix: Stop setting default colors on sticky header and allow clearing color controls
* Fix: Harden draft-mode navigateTo() — validate URL protocol before navigation (security)

**Improvements:**
* Enhancement: CI — skip wp plugin delete in lifecycle test, use @wordpress/env, allow wp-env stop to fail gracefully
* Enhancement: Optimize screenshot-1.gif for WordPress.org 10MB limit

= 2.0.46 - 2026-03-23 =
**Bug Fixes:**
* Fix: Flip card inner blocks no longer allow duplicate front/back faces — dynamic allowedBlocks only permits missing face types
* Fix: Flip card now fills parent grid cells equally when align-items is stretch
* Fix: Horizontal tabs nav no longer shows unwanted vertical scrollbar
* Fix: Shape divider line visible on flipped shapes
* Fix: Handle nested JSON braces in draft mode block comment preservation

**New Features:**
* Feat: Scroll slides auto-apply overlay color (#111111) when a slide gets a background image

**Improvements:**
* Enhancement: CI — use npx wp-env stop to survive plugin delete; check transients via DB query

= 2.0.45 - 2026-03-23 =
**Bug Fixes:**
* Fix: Plugin deletion no longer causes a critical error — uninstall cleanup is now fault-tolerant with per-step error handling
* Fix: Deactivation modal no longer auto-scans your site — shows an explanation first and lets you choose to scan or just deactivate
* Fix: Orphan llms.txt file is now cleaned up during plugin deletion
* Fix: Modal focus restored to deactivate link when closed (accessibility)

**Improvements:**
* Enhancement: Added plugin lifecycle smoke test (activate/deactivate/delete) to CI pipeline
* Enhancement: Added PHPUnit integration test for uninstall cleanup logic

= 2.0.44 - 2026-03-19 =
**Bug Fixes:**
* Fix: Frontend assets (responsive visibility, animations, extensions) now load for blocks in template parts (header/footer), not just post content
* Fix: Remove unwanted has-global-padding on grid inner container that caused extra padding in headers
* Fix: Responsive visibility classes (hide desktop/tablet/mobile) now properly override layout display rules on all blocks
* Fix: Remove unused has_dashicon_blocks method (PHPStan)
* Fix: Row block vertical alignment now works correctly when nested inside a grid

= 2.0.43 - 2026-03-19 =
**Bug Fixes:**
* Fix: Icon button blocks in header/footer template parts now display correct styles and icons on all pages, not just pages containing an icon button in post content

= 2.0.42 - 2026-03-19 =
**Enhancements:**
* Feat: Add left/center/right content justification controls to breadcrumbs block

= 2.0.40 - 2026-03-09 =
**Bug Fixes:**
* Fix: Add padding below scroll-driven slider for progress bar clearance
* Fix: Apply slide block gap to content wrapper for editor/frontend parity
* Fix: Increase scroll-driven slider CSS specificity to prevent sticky header override
* Fix: Add bare color slug detection to convertPresetToCSSVar for CSS Color L4 compliance
* Fix: Remove white background from scroll slides nav inputs in editor

= 2.0.39 - 2026-03-08 =
**Bug Fixes:**
* Fix: Resolve React DOM removeChild error in editor on pages with form blocks by moving render-time setAttributes calls to useEffect in all form field blocks
* Fix: Add phone field v3 deprecation for API-generated content with inline country code options
* Fix: Prevent duplicate placeholder option in select field when API includes placeholder in options array

= 2.0.37 - 2026-03-08 =
**New Blocks:**
* New: Scroll Slides block — scroll-pinned slideshow with crossfade transitions, navigation headings, and mobile tap-to-navigate mode
* New: Sticky Sections block — card-stacking scroll effect where sections stack on top of each other as you scroll

**New Features:**
* New: Scroll-driven horizontal mode for Slider block with vertical viewport centering

**Improvements:**
* Enhancement: Unified block inserter icon color to #F25912 orange across all blocks and variations

**Bug Fixes:**
* Fix: Shape divider sub-pixel coverage gaps at certain zoom levels
* Fix: Revert premature block support stabilization and add enableAlpha to color pickers
* Fix: Restore accordion color control UX help text lost during migration
* Fix: Editor overlay stacking context containment for sticky sections

**Tests:**
* Test: Add frontend unit tests for 11 blocks

**Documentation:**
* Docs: Add user guides for Scroll Slides and Sticky Sections blocks

= 2.0.36 - 2026-03-07 =
**New Features:**
* New: Redirect visitors to a custom URL after successful form submission — perfect for thank-you pages, upsells, or next steps
* New: Phone field country code dropdown is now compact and easier to use on all screen sizes

**Improvements:**
* Enhancement: Simplified abilities system from ~110 to 14 focused abilities for faster, more reliable AI-powered block insertion
* Enhancement: WordPress 7.0 compatibility — stabilized block support keys for seamless upgrades
* Enhancement: Removed custom visual revision comparison — WordPress 7.0 now includes native visual diffs, so this feature is no longer needed

**Bug Fixes:**
* Fix: Resolved "unexpected or invalid content" errors that could appear when updating from older plugin versions
* Fix: Maps now show a fallback location instead of a blank map when an address can't be geocoded
* Fix: Shape divider colors now apply correctly in all themes
* Fix: Form submit button no longer flickers when redirect is enabled
* Fix: Form fields properly disable when AJAX submission is turned off
* Fix: Redirect URLs are validated to prevent unsafe protocols

= 2.0.35 - 2026-03-06 =
**New Features:**
* New: Product Categories Grid block — display WooCommerce product categories in a responsive grid with image overlays, text position control, sidebar spacing, manual category selection, and category exclusion
* Fix: Abilities API show_in_rest meta nesting updated for WordPress 6.9 compatibility

**Bug Fixes:**
* Fix: Add isEligible and migrate functions to all block deprecations for silent auto-migration (no more "Attempt Recovery" warnings)
* Fix: Narrow overlapping isEligible conditions in deprecations to prevent false matches

**Dependencies:**
* Bump immutable from 5.1.4 to 5.1.5
* Bump svgo from 3.3.2 to 3.3.3
* Bump @tootallnate/once and @wordpress/scripts

= 2.0.34 - 2026-02-27 =
**Bug Fixes:**
* Fix: Add deprecations for form text, email, select, and textarea field blocks to handle content saved without `aria-required` attribute
* Fix: Add deprecation for form builder block to handle content saved without `aria-hidden` on honeypot and `aria-atomic` on message div
* Fix: Normalize multi-line addresses in map geocoding — replace newline characters with commas before querying Nominatim, with automatic retry stripping the business name line

= 2.0.33 - 2026-02-25 =
**Bug Fixes:**
* Fix: Resolve form block kses validation failures — remove `defaultValue` from select-field and phone-field save output that `wp_kses_post()` strips
* Fix: Phone field country code options now JS-rendered via `data-dsgo-country-code` attribute + view.js hydration, expanding from 13 to 60+ country codes
* Fix: Add deprecations for select-field and phone-field blocks to migrate existing content
* Fix: Expand kses allowlist with `aria-*`/`data-*` wildcards for textarea, button, div
* Fix: Editor select field width not filling container
* Fix: Map block address-based geocoding fallback when lat/lng are both 0, with error handling for null geocode results
* Fix: Make Deactivate the primary action in deactivation modal

= 2.0.32 - 2026-02-25 =
**Bug Fixes:**
* Fix: Register Leaflet CSS as viewStyle so map renders correctly on the frontend

= 2.0.31 - 2026-02-24 =
**Bug Fixes:**
* Fix: Prevent sub-pixel border gaps on shape dividers at small screen sizes
* Fix: Bundle Leaflet locally to fix map in CSP-restricted iframes

= 2.0.30 - 2026-02-23 =
**New Features:**
* New: Product Showcase Hero block for highlighting WooCommerce products with a two-column layout featuring product image, price, ratings, stock status, and add-to-cart button
* New: Overlay header text color setting to control nav link and title colors in the transparent overlay state
* New: Sticky header option to scroll the top bar out of view before the nav row snaps into a sticky position
* New: Click-drag and mouse wheel scroll interactions for the Scroll Marquee block
* New: Starter design patterns for the Advanced Heading block

**Bug Fixes:**
* Fix: Slider block now correctly grows to match its content height instead of clipping content

= 2.0.29 - 2026-02-18 =
**Bug Fixes:**
* Fix: Add form elements (form, input, select, option) to global KSES allowlist so form block content survives wp_kses_post() sanitization during REST API imports
* Fix: Add missing textarea attributes (placeholder, required) to KSES allowlist for form blocks

= 2.0.28 - 2026-02-16 =
**Bug Fixes:**
* Fix: Icon list grid layout now stacks to single column on mobile for proper responsive behavior
* Fix: Declare $block_migrator property to resolve PHP 8.2 dynamic property deprecation warning

= 2.0.27 - 2026-02-15 =
**New Features:**
* New: Fifty Fifty block for 50/50 split layouts with edge-to-edge media and constrained content
* New: Scroll-driven rotation support for parallax extension
* New: Block gap support for Advanced Heading block
* New: Inline button layout styles for form builder
* New: Block transforms to core blocks for graceful plugin deactivation

**Bug Fixes:**
* Fix: Remove default padding on sections nested inside sections
* Fix: Replace JSON.stringify comparison with direct property checks for better performance
* Fix: Respect explicit max-width inside no-width-constraint flex containers
* Fix: Icon list horizontal layout stacking on tablet
* Fix: Slider layout issues in flex/grid containers
* Fix: Remove unused attributes check in max-width HOC

= 2.0.26 - 2026-02-14 =
**New Features:**
* New: Per-page overlay header with transparent-to-sticky transition effect
* New: Header and footer block patterns for quick site building
* New: Enhanced llms.txt with full spec compliance

**Bug Fixes:**
* Fix: Add vertical stretch support for section flex children
* Fix: Replace undefined wp_strlen/wp_substr with mb_strlen/mb_substr for PHP compatibility
* Fix: Preserve inner section width constraints when nested in unconstrained sections
* Fix: Apply same nested section width fix to editor styles for consistent editor/frontend parity

= 2.0.25 - 2026-02-13 =
**Bug Fixes:**
* Fix: Resolve llms.txt 404 error caused by trailing slash redirect
* Fix: Auto-save and generate markdown files when llms.txt is toggled on
* Fix: Harden redirect_canonical filter to prevent query var abuse
* Fix: Correct conflict detection for third-party physical llms.txt files
* Fix: Guard physical file writes to only occur when feature is enabled
* Fix: Add UI rollback when llms.txt toggle save fails

= 2.0.24 - 2026-02-13 =
**Bug Fixes:**
* Fix: Add SVG element allowlist to KSES filters for proper SVG rendering in post content
* Fix: Add color and gradient CSS functions (rgb, rgba, hsl, hsla, linear-gradient, radial-gradient, conic-gradient) to KSES safe style filters

= 2.0.23 - 2026-02-12 =
**New Features:**
* New: Border radius control for form builder input fields

**Bug Fixes:**
* Fix: Unify form field block context namespace for consistent field registration
* Fix: Add global safe_style_css filter for block inline styles
* Fix: Responsive visibility and clickable group class handling

**Maintenance:**
* chore: Bump qs from 6.14.1 to 6.14.2

= 2.0.22 - 2026-02-12 =
**New Features:**
* New: Vertical alignment control for icons in icon-list block
* New: Icon search aliases and canonical icon name resolution for easier icon discovery
* New: Slider height now optional with content-fit fallback for natural sizing

**Improvements:**
* Enhancement: Form builder submit button now inherits Global Styles for consistent theming
* Enhancement: Sticky header scroll colors refactored to use dropdown UI for better UX
* Enhancement: Email configuration moved to server-side for improved form security
* Enhancement: Twitter social links updated to X platform branding
* Enhancement: E2E test improvements for block selection and class detection

**Bug Fixes:**
* Fix: Vertical scroll parallax centering for natural element positioning
* Fix: CSS preset colors now properly resolved in SVG patterns and shape dividers
* Fix: Blob wrapper background reset styles with increased specificity

= 2.0.21 - 2026-02-11 =
**Bug Fixes:**
* Fix: Encode preset colors as WordPress CSS variables instead of raw hex values for consistent theme integration
* Fix: Inherit Global Styles button element styles in icon-button and modal-trigger blocks
* Fix: CSS sanitization hardened with safecss_filter_attr() for Global Styles injection
* Fix: Legacy modal-trigger border-radius now respects Global Styles via CSS variable

= 2.0.2 - 2026-02-11 =
**New Features:**
* New: Server-side SVG pattern rendering for improved performance
* New: Extension attributes exposed in REST API

**Bug Fixes:**
* Fix: Remove blockTypes restriction from patterns to fix multi-plugin visibility
* Fix: Flush rewrite rules when llms.txt feature is toggled in settings

= 2.0.1 - 2026-02-10 =
**New Features:**
* New: Global default hover animation for Icon Button blocks
* New: Alpha channel support on Section block hover and scroll color pickers
* New: Polka-dots SVG pattern with opacity support for all patterns

**Bug Fixes:**
* Fix: Icon list item default template changed from heading to paragraph for better semantics
* Fix: REST content sanitization always applied; pattern cache test reliability improved
* Fix: Theme spacing presets now respected instead of being overridden
* Fix: Parallax effect feedback loop resolved with server-side attribute injection
* Fix: Moroccan pattern removed (replaced by polka-dots)

= 2.0.0 - 2026-02-08 =
**New Blocks:**
* New: Comparison Table block - dynamic columns, checkmark/X/text cells, featured column highlighting, CTA buttons, and responsive layout
* New: Timeline block - vertical and horizontal orientations, alternating layouts, customizable markers, scroll animations, and optional links
* New: Advanced Heading block - create headings with multiple font styles, weights, and colors using independent heading segments

**New Extensions:**
* New: Grid Mobile Order - reorder grid items on mobile without changing the desktop layout or HTML structure
* New: SVG Patterns - add 25+ repeatable SVG background patterns to sections and groups with customizable color, opacity, and scale

**New Features:**
* New: Shape Dividers for Section blocks - 24 decorative shapes (waves, curves, peaks, clouds, and more) with customizable color, height, and width
* New: Frontend Draft Preview Mode - administrators can browse the frontend and see draft content across all pages with a preview/live toggle
* New: 150+ reusable section patterns and 12 complete homepage templates for SaaS, agency, restaurant, real estate, fitness, and more
* New: Tabs hover color controls for custom text and background colors on hover
* New: Sticky Header text color on scroll - switch text colors when header scrolls over content
* New: Modal hash link reopening - modals reopen when clicking anchor links to the same modal ID
* New: 4 new icons added to the icon library (dumbbell, fire, layers, refresh)
* New: Animations now respect prefers-reduced-motion accessibility preference

**Improvements:**
* Enhancement: Row block now supports vertical alignment (top, center, bottom, stretch, space-between)
* Enhancement: Section block vertical alignment now works with min-height
* Enhancement: Modal Trigger inherits theme.json button styles and supports WordPress alignment
* Enhancement: Pattern loading optimized with caching and editor-only registration
* Enhancement: Code splitting with lazy loading for extensions and admin - smaller bundle, faster editor load
* Enhancement: Animation performance optimized with shared observers and reduced overhead
* Enhancement: Section overflow handling improved for better dropdown and sticky element compatibility
* Enhancement: Icon Button link settings now use WordPress LinkControl with search and autocomplete

**Bug Fixes:**
* Fix: Pill, Icon Button, Icon, and Modal Trigger no longer float beside content in Group blocks
* Fix: Grid and Row blocks go edge-to-edge with full-width alignment outside Section blocks
* Fix: Pill alignment carries through Grid > Section nesting
* Fix: Icon block double-layered background color removed
* Fix: Card block overflow in grid layouts
* Fix: Full-width video background alignment in editor
* Fix: Icon Button default focus outline removed
* Fix: Pill block no longer stretches to fill flex and grid containers
* Fix: Buttons and pills no longer stretch vertically in grid layout contexts
* Fix: Text alignment now works in sections with content justification
* Fix: Icon block vertical alignment and SVG rendering in editor
* Fix: Row block overflow when padding or border is applied
* Fix: Background images with URL query parameters now render on frontend
* Fix: Icon block sizing improved in editor
* Fix: Draft mode no longer strips CSS or SVG content from blocks
* Fix: Modal trigger button padding now consistent with WordPress buttons; link-style triggers maintain compact styling on mobile
* Fix: Card block badge and overlay color controls now appear correctly in sidebar
* Fix: Sticky header no longer overrides custom button and element colors in non-navigation areas

**Internationalization:**
* i18n: Updated translation strings for v2.0.0 across all 9 supported languages with new block, extension, and UI strings

**Security:**
* Security: Fixed potential XSS bypass in block attribute sanitization

= 1.4.1 - 2026-01-31 =
**Bug Fixes:**
* Fix: Grid block type safety for WordPress 6.1+ blockGap object format conversion
* Fix: Grid block alignItems default now consistent between editor and frontend (uses 'stretch')
* Fix: Row block preset conversion with proper type checking
* Fix: Icon Button width attribute removed from schema (deprecation handles migration)
* Fix: Divider width no longer overridden by editor styles
* Fix: llms.txt conflict detection now includes dismissable notices with file resolution option

**Improvements:**
* Enhancement: Icon Button now uses WordPress alignfull for full-width display
* Enhancement: llms.txt conflict handling allows renaming conflicting files via admin UI

= 1.4.0 - 2026-02-01 =
**New Features:**
* New: llms.txt Support - Implements the llms.txt standard to help AI language models understand site content, with admin settings and per-page exclusion controls
* New: Draft Mode for Published Pages - Create and manage draft versions of published content without affecting the live page
* New: Visual Revision Comparison - Side-by-side rendered previews of post revisions with color-coded block highlighting
* New: Block Exclusion System - User-configurable system to prevent DSG extensions from being applied to specific third-party blocks

**Breaking Changes:**
* Breaking: Minimum PHP requirement bumped from 7.4 to 8.0 for improved security and performance

**Bug Fixes:**
* Fix: Icon Button border-radius not displaying on frontend while working correctly in editor
* Fix: REST API validation conflicts with server-side rendered blocks like Gravity Forms
* Fix: Restored 14 missing icons to SVG library

**Security:**
* Security: Bumped lodash and lodash-es from 4.17.21 to 4.17.23

= 1.3.2 - 2025-01-30 =
**Bug Fixes:**
* Fix: Icon Button no longer displays double background layer when using rounded corners
* Fix: Stop overriding theme.json color palette, spacing presets, and font families - better theme compatibility
* Fix: Temporarily disable post content alignfull padding fix pending comprehensive solution

**Developer Experience:**
* Enhancement: Migrate commands to modern Claude Code skills format for improved automation
* Enhancement: Add Claude Code GitHub Workflow for CI/CD improvements

= 1.3.1 - 2025-01-09 =
**Bug Fixes:**
* Fix: Slider initialization timing - fixed first-load issues where sliders showed gaps or incorrect positioning before reload
* Fix: Scroll Gallery (Marquee) initialization timing - fixed first-load issues where gallery wouldn't scroll until page reload
* Fix: Both blocks now properly wait for images to load and CSS to apply before calculating dimensions

= 1.3.0 - 2025-12-06 =
**New Features:**
* New: WordPress 6.9 Abilities API support - 50 AI abilities for block insertion, configuration, and section generation
* New: Text Style inline format - apply colors, gradients, font sizes, and highlights to selected text like bold/italic
* New: Scroll Parallax extension - Elementor-style vertical and horizontal parallax effects with per-device controls
* New: Text Reveal extension - scroll-triggered text color animation that simulates natural reading progression
* New: Expanding Background extension - scroll-driven background that expands from a small circle to fill sections
* New: Cloudflare Turnstile integration for form spam protection - modern, privacy-friendly alternative to reCAPTCHA

**WordPress 6.9 Compatibility:**
* Enhancement: Conditionally load Abilities API polyfill only for WordPress < 6.9 (6.9+ includes it natively)
* Enhancement: 26 new abilities added including inserters, configurators, and section generators
* Enhancement: Updated "Tested up to" to WordPress 6.9

**Improvements:**
* Enhancement: Icon Button now respects WordPress width constraints and inherits theme.json button styles
* Enhancement: Icon Button properly integrates with FSE button settings (colors, padding, border-radius)
* Enhancement: Admin settings page now properly displays translations for all supported languages

**Bug Fixes:**
* Fix: Icon Button display and width issues in constrained layouts
* Fix: Admin settings page translation loading with proper JSON translation file generation
* Fix: Added missing wp_set_script_translations() call for admin JavaScript bundle

**Documentation:**
* Docs: Added comprehensive documentation for all new extensions and formats
* Docs: Updated Abilities API documentation with complete reference for all 50 abilities

= 1.2.1 - 2025-11-24 =
**New Features:**
* New: Form submissions admin now displays email delivery status (sent/failed) with visual indicators
* New: Detailed email delivery information in submission sidebar (recipient, date, status)
* New: Data retention enforcement and configurable anti-abuse settings for form submissions
* New: Missing blocks and extensions now properly display in admin Dashboard

**Security Fixes:**
* Security: Added CSRF protection for form submissions to prevent cross-site request forgery attacks
* Security: Restricted form submissions to admin-only access for better data protection
* Security: Implemented trusted proxy IP resolution to prevent IP spoofing in rate limiting

**Performance:**
* Performance: Implemented lazy loading for icon library - critical optimization reducing initial bundle size

**Bug Fixes:**
* Fix: Form email deliverability - changed From address default from admin email to wordpress@{sitedomain} to match WordPress core and prevent SPF/DKIM/DMARC failures
* Fix: Form validation, rate limiting, and email tracking issues resolved
* Fix: Email status display bug in admin dashboard
* Fix: Admin dashboard capability check error preventing proper access control
* Fix: Admin dashboard handling of blocks data preventing crashes

**Enhancements:**
* Enhancement: Added debug logging to track email notification flow and diagnose sending issues
* Enhancement: Updated From Email helper text to reflect new domain-matched email default

= 1.2.0 - 2025-11-21 =
**New Features:**
* New: Breadcrumbs block with Schema.org markup for improved SEO and navigation
* New: Table of Contents block with automatic heading detection, smooth scrolling, and sticky positioning
* New: Modal/Popup block with accessible triggers, animations, and gallery support
* Enhancement: Modal close triggers and improved icon-button UX with better accessibility

**Bug Fixes:**
* Fix: Table of Contents critical production readiness fixes for stable performance
* Fix: Table of Contents sticky positioning and scroll spy highlighting functionality
* Fix: Table of Contents error handling for better reliability
* Fix: Prevent sticky header from affecting footer template parts

**Security:**
* Security: Fixed 3 critical vulnerabilities in Modal block + performance optimizations

**Internationalization:**
* i18n: Added modal block translations to all language files
* i18n: Updated translation strings for modal close functionality

**Maintenance:**
* Maintenance: Optimized screenshot-1.gif (24MB → 5.7MB)
* Maintenance: Updated WordPress.org assets and screenshots

= 1.1.4 - 2025-11-19 =
**Bug Fixes:**
* Fix: Slider initialization on uncached first load - sliders now display correctly on first page visit
* Fix: Critical race condition in image loading detection that could cause 3-second initialization delays
* Fix: Memory leak from uncleaned setTimeout timers in slider initialization
* Fix: Double-counting bug in slider image load detection that could prevent initialization

**Performance:**
* Performance: Eliminated redundant DOM queries in slider initialization
* Performance: Optimized Array.from conversions for better memory efficiency

= 1.1.3 - 2025-11-16 =
**Performance:**
* Performance: Major CSS loading strategy optimization - improved enqueue logic and selective loading
* Performance: Fixed forced reflows in JavaScript and optimized asset loading strategy
* Performance: Eliminated layout thrashing by batching DOM reads/writes and deferring non-critical operations

**Bug Fixes:**
* Fix: Flip card back panel now correctly displays background color and text in editor
* Fix: Added alignment options to countdown timer block for better layout control

= 1.1.2 - 2025-11-15 =
**New Features:**
* New: Added developer filter hooks for advanced Custom CSS customization

**Bug Fixes:**
* Fix: Section hover background now correctly renders behind content instead of over text
* Fix: Resolved z-index stacking issue where hover overlay appeared above section content

**Enhancements:**
* Enhancement: Improved Custom CSS textarea UX with better styling and increased height

= 1.1.1 - 2025-11-15 =
**Security Fixes:**
* Security: Fixed HIGH severity string escaping vulnerability in counter number formatting
* Security: Added escapeReplacement() function to prevent injection via replacement string special sequences

**Bug Fixes:**
* Fix: Escape special characters in separator strings used by Counter and Counter Group blocks

= 1.1.0 - 2025-11-14 =
**New Blocks:**
* New: Card block with multiple layout presets (horizontal, vertical, overlay, compact, featured)
* New: Map block with Google Maps and OpenStreetMap support, privacy mode, and customizable markers

**Admin Interface Overhaul:**
* New: Completely redesigned admin dashboard with stat cards showing blocks, extensions, and form submissions
* New: Enhanced dashboard displays blocks organized by category and extension status pills
* New: Tabbed settings interface organized into Features, Optimization, and Integrations tabs
* New: Google Maps API key management in Settings > Integrations with security guidance
* Enhancement: Two-column grid layouts for improved settings panel space efficiency
* Enhancement: Gradient icon stat cards with hover effects for better visual hierarchy
* Enhancement: Collapsible sections for advanced settings to reduce vertical scroll

**Translations:**
* Enhancement: Added translation support for 9 languages (Spanish, French, German, Italian, Portuguese, Dutch, Russian, Chinese, Japanese)
* Enhancement: Updated POT file with 100% translation coverage for all admin strings

**Security & Bug Fixes:**
* Security: Fixed js-yaml prototype pollution vulnerability (CVE-2023-2251)
* Fix: Added missing ToggleControl import to Card block editor component
* Fix: Google Maps API key now persists correctly after save/reload
* Fix: API key properly exposed to frontend via data attributes with secure referrer-based protection

= 1.0.1 - 2025-11-14 =
* Docs: Streamlined readme.txt with JTBD-focused messaging for better scannability
* Docs: Condensed description from 516 to 339 lines while keeping essential information
* Docs: Reordered FAQ to address user anxiety barriers first

= 1.0.0 - 2025-11-12 =

🚀 **Initial Release**

**43 Professional Blocks:**
* 5 Container blocks (Row, Section, Flex, Grid, Stack)
* 13 Form Builder blocks (complete system with AJAX, spam protection, email notifications)
* 10 Interactive blocks (Tabs, Accordion, Flip Card, Slider, Counters, Progress Bar, Scroll effects)
* 8 Visual blocks (Icons, Icon Button, Icon List, Card, Pill, Divider, Countdown Timer, Blobs)
* 9 Child blocks (Tab, Accordion Item, Slide, Flip Card Front/Back, Icon List Item, Image Accordion Item, Scroll Accordion Item, Counter)

**11 Universal Extensions** (work with ANY block):
* Block Animations (24+ effects with scroll triggers)
* Sticky Header (FSE-optimized with offset controls)
* Clickable Groups (accessible card/container links)
* Background Video (YouTube and self-hosted)
* Responsive Visibility (hide by device)
* Max Width (content width constraints)
* Custom CSS (per-block styling)
* Grid Span (column/row control)
* Reveal Control (advanced hover effects)
* Text Alignment Inheritance (parent-child context)

**Performance & Quality:**
* Built with WordPress core patterns for guaranteed editor/frontend parity
* Optimized bundles, no jQuery, code-splitting
* WCAG 2.1 AA accessible with full keyboard navigation
* FSE compatible with theme.json integration
* Comprehensive documentation and developer guides

== Upgrade Notice ==

= 2.1.1 =
Patch fix for WordPress 6.7+: eliminates `_load_textdomain_just_in_time` PHP notices triggered by early translation function calls. Recommended for all sites.

= 2.1.0 =
Major update: Dynamic Query block family (list any posts/users/terms with filters, pagination, and faceted counts), Dynamic Tags picker for live data, native WordPress 6.9 Block Bindings, field sources for Meta Box / Pods / JetEngine, conditional block visibility, per-URL Markdown, Hover Effects extension, grid column toolbar + row span, and a full editor UX refresh (standardized inspectors, new onboarding). Security hardening for form redirects, Draft Mode REST endpoints, and CSS style bindings. Visual Revision Comparison removed (WordPress 7.0 ships a native replacement).

= 2.0.33 =
Fixes form block kses validation failures for select and phone fields, expands phone field to 60+ country codes via JS hydration, adds map geocoding fallback with error handling, and makes Deactivate the primary action in the deactivation modal.

= 2.0.29 =
Adds form elements to the global KSES allowlist so form block content (inputs, selects, textareas) is preserved when imported via the REST API or other wp_kses_post() code paths.

= 2.0.28 =
Fixes icon list grid responsive stacking on mobile and resolves PHP 8.2 dynamic property deprecation warning that caused header errors in admin.

= 2.0.27 =
New Fifty Fifty block for 50/50 split layouts, scroll-driven rotation for parallax, block gap for Advanced Heading, block transforms for plugin deactivation, and fixes for nested section padding, icon list tablet stacking, slider flex/grid layout, and max-width in flex containers.

= 2.0.26 =
New per-page overlay header with transparent-to-sticky transition, header and footer block patterns, enhanced llms.txt spec compliance, and fixes for section flex children, nested section width constraints, and PHP string function compatibility.

= 2.0.24 =
Adds SVG element allowlist and color/gradient CSS function support to KSES filters for proper rendering of inline SVGs and styled content.

= 2.0.23 =
Adds border radius control for form inputs, fixes form field context namespace, inline style sanitization, responsive visibility, and clickable group class handling.

= 2.0.22 =
New icon-list vertical alignment, icon search aliases, optional slider height, form security improvements, sticky header dropdown UI, parallax centering fix, and SVG pattern color resolution.

= 2.0.1 =
Adds Icon Button hover animations, Section color picker alpha channel, polka-dots pattern with opacity, and fixes for parallax feedback loops, spacing preset overrides, icon list defaults, and REST sanitization.

= 2.0.0 =
Major update: 3 new blocks (Comparison Table, Timeline, Advanced Heading), 2 new extensions (Grid Mobile Order, SVG Patterns with 25+ background patterns), shape dividers for sections, 150+ patterns and 12 homepage templates, frontend draft preview mode, improved Icon Button link settings, lazy loading for faster editor performance, reduced motion accessibility support, plus numerous bug fixes and a security improvement.

= 1.4.1 =
Bug fix release: Fixes Grid block type safety for WordPress 6.1+ spacing presets, Row/Grid alignment consistency, Icon Button width migration, and improved llms.txt conflict handling with admin UI resolution.

= 1.4.0 =
Major update with 4 new features: llms.txt support for AI language models, Draft Mode for published pages, Visual Revision Comparison, and Block Exclusion System. **Breaking change:** Minimum PHP requirement is now 8.0. Includes bug fixes and comprehensive test suite.

= 1.3.1 =
Bug fix release: Fixes slider and scroll gallery initialization timing issues on first page load.

= 1.3.0 =
Major update with WordPress 6.9 Abilities API support (50 AI abilities), 3 new scroll-driven extensions (Scroll Parallax, Text Reveal, Expanding Background), new Text Style inline format for custom text styling, Cloudflare Turnstile spam protection, and Icon Button theme.json integration.

= 1.1.0 =
Major update with new Map and Card blocks, completely redesigned admin dashboard, 11 universal extensions, and 9 language translations. Enhanced security and bug fixes.

= 1.0.0 =
Initial release with 43 professional blocks + 11 universal extensions. Build stunning WordPress sites without page builders—native blocks with the power you need.

== Privacy & Security ==

DesignSetGo respects your privacy:
* No tracking or analytics
* No data collection
* No external server connections
* No cookies or localStorage for tracking
* 100% GDPR compliant

Form submissions are processed on your server and sent via your WordPress email system. No third-party services required.
