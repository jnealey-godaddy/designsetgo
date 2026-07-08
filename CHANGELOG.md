# Changelog

All notable changes to the DesignSetGo plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.0] - Unreleased

### New Features
- **Section Divider block** — New standalone block for dropping a full-width shape divider between any two blocks, independent of Section's own divider. Shape, height, and fill color default to the theme's Style Kit setting and can be overridden per instance. (#441)
- **Icon block: fill / outline + theme default size** — The Icon block gained a Fill / Outline style toggle and inherits a theme-defined default size, so icons match your design out of the box. (#438)
- **Icon Button inherits core Button style variations** — Fill, Outline, and any variations the theme registers.
- **Scrolling Gallery: native border controls** — Width, style, color, and radius replace the old single border-radius field.
- **SVG Patterns: theme-inheritable default** — A pattern can inherit a "Theme default" preset (type, color, opacity, scale) set at the theme level, so a Style Kit can restyle every pattern site-wide; each block can still override it. (#446)
- **Row & Grid: Style Kit overlay and hover-state variations** — Row and Grid now support the same background-overlay and hover-state style variations as Section, so a Style Kit's overlay/hover styling applies consistently across all three layout blocks. (#445, #447)
- **Scroll Slides: overlay opacity control** — The color overlay above each slide's background is now adjustable via an "Overlay Opacity" slider instead of a fixed 80%, so contrast can be tuned when the background image is bright. Existing blocks are unchanged (they keep the 80% default).
- **Modal: accessible label controls** — New "Accessible Label" and "Close Button Label" fields let authors customize the dialog's and close button's `aria-label` for screen readers. Both default to the previous English strings ("Modal" / "Close modal") when left empty, so existing modals are unchanged.

### Changed
- **Icon, Divider, Map, and form field blocks render dynamically** — They always reflect the current theme and store cleaner markup; existing blocks migrate automatically.
- **Pill renders dynamically** — The Pill block is now server-rendered, completing the icon/divider/map/form conversion. A fresh pill serializes to a single self-closing block comment with no baked-in `aligncenter` / `has-small-font-size` classes, so pills stay portable across patterns and AI-assisted (Abilities API) authoring and always reflect the current theme. Existing pills migrate silently via a new deprecation; the default centered alignment is now CSS-driven and unchanged. (#439)
- **Existing pills inherit their context font size** — The old `fontSize: "small"` default is no longer baked into saved markup, so published pills that relied on the default small size now render at their surrounding text size. Set an explicit font size on any pill that should keep the smaller look; alignment is unaffected. (#439)
- **Form Builder fields inherit theme spacing and sizing.**
- **Map markers can inherit their color from the theme.**
- **Section styles extend to more container blocks** — Card, Fifty/Fifty, Modal, Slide, Scroll Slide, Tab, Accordion Item, Scroll Accordion Item, Image Accordion Item, Timeline Item, Counter, and Flip Card Face now pick up theme-registered section styles (e.g. core's Style 1–5), matching Section, Row, and Grid. (#440)
- **Progress Bar no longer bakes a default color into saved markup** — An unset bar/track color now inherits the theme instead of a fixed hex value. (#440)
- **Icon Button icon gap and size are themeable** — The icon↔text gap is no longer baked inline: it resolves from a kit-controllable CSS custom property (`--dsgo-icon-button-gap` → the `--wp--custom--designsetgo--icon-button--gap` token → `8px`) and is omitted entirely when the button has no icon. Icon size gained the same kit hook (`--dsgo-icon-button-size`) ahead of the existing theme token. Buttons with an icon now carry a `dsgo-icon-button--has-icon` class; an explicit author gap is still written inline and wins. Existing buttons migrate automatically.
- **Icon List Item gaps and icon size are themeable** — The item icon↔content gap, the content gap, and the inherited icon-box size are no longer baked as raw inline pixels. They resolve from kit-controllable CSS custom properties (`--dsgo-icon-list-gap` / `--dsgo-icon-list-gap-top` / `--dsgo-icon-list-content-gap` / `--dsgo-icon-list-icon-size`, each over its `--wp--custom--designsetgo--icon-list--*` token), so a Style Kit can retheme them. Explicit values still win; existing items migrate automatically.
- **Image Accordion height and gap default to theme tokens** — The panel height (was a fixed `500px`) and gap (was `4px`) now default to themeable values (`--dsgo-image-accordion-height` over a custom token; `--dsgo-image-accordion-gap` over `--wp--preset--spacing--20`) instead of baked-in pixels, so patterns no longer override them with magic numbers. Explicit author values still win; existing accordions migrate automatically.
- **Scroll Marquee images can size from their aspect ratio** — `Image Width` now offers an "Auto width (from aspect ratio)" option, which is the new default, so authors normally set only a single height and each image's intrinsic ratio drives its width (the frontend already measures rendered width at runtime). Existing marquees keep their stored width and are unchanged.
- **Blobs has a native, themeable max-width** — Blob width now uses a native `Max Width` control that resolves from a kit-controllable custom property (`--dsgo-blob-max-width` over the `--wp--custom--designsetgo--blobs--max-width` token) instead of the generic max-width extension's baked-in inline value. Existing blobs migrate automatically.

### Bug Fixes
- **Modal: custom accessible label now works** — The dialog's `aria-label` read a `modalLabel` attribute that was never registered, so a custom label could never take effect and it always fell back to "Modal". The attribute is now registered and wired to the new "Accessible Label" control.
- **Icon List frontend parity** — Items show their fill / outline and stroke on the frontend, matching the editor.
- **SVG Patterns / Form Builder color baking** — No longer bake default colors into saved markup, so they inherit the theme's colors.
- **Abilities API quote / backslash handling** — Content with quotes or backslashes saved through AI-assisted edits is no longer altered.
- **Scrolling Gallery legacy migration** — Blocks saved by older versions or patterns (image rows stored in the markup rather than the block comment) keep migrating silently instead of showing "Attempt Recovery."
- **Image Accordion overlay now reaches the frontend** — A parent Image Accordion's overlay color/opacity previously never rendered on its items on the frontend; it now applies correctly. (#440)
- **Section style customizations preview live in the editor** — Border, radius, and other section-style tweaks made in Global Styles now show up immediately on Section, Row, Grid, and the rest of the container family in the editor canvas, matching what already rendered on the frontend. (#443)
- **Section overlay style variations now render** — Sections using an `is-style-overlay-*` Style Kit variation (instead of the overlay color picker) now correctly show the overlay. (#445)
- **Scroll Accordion: removed a stray editor-only border** — A gradient bar shown down the left edge of Scroll Accordion items in the editor, with no frontend equivalent, has been removed. (#442)

## [2.3.0] - 2026-07-01

### New Features
- **Section styles on layout blocks** — Theme "section style" variations registered for the core Group / Columns / Column blocks now also apply to DesignSetGo Section, Row, and Grid, so those blocks offer the same style options in the editor Styles panel. (#434)
- **Theme-inheritable shape dividers** — Sections can inherit a site-wide default divider shape defined at the theme level (`settings.custom.designsetgo.shapeDivider.type`), and each section can still override it. (#434)
- **designsetgo.dev callout** — The plugin dashboard now links to [designsetgo.dev](https://designsetgo.dev), the runtime for hosting the apps you build with AI on your WordPress site.

### Changed
- **Class-based, see-through shape dividers** — Shape dividers now render with CSS masks instead of an inline SVG. The shape region is transparent and reveals the section's own background — solid, gradient, or image — and the default height/width are left out of the saved markup unless customized. The drops, fan, steps, and slime shapes were redesigned for this see-through model. (#434)
- **Refreshed admin branding** — Updated the plugin dashboard logo and the WordPress admin-menu icon to the current DesignSetGo brand mark.

### Bug Fixes
- **Theme default divider now resolves** — Shape mask definitions moved to `:root` so a theme/Style-Kit default resolves correctly; previously an inheriting divider silently fell back to Wave. (#434)
- **Legacy divider content keeps migrating** — Froze the pre-redesign geometry the block deprecations use, so sections saved before this release with drops, fan, steps, or slime dividers still migrate silently instead of showing "Attempt Recovery." (#434)

## [2.2.0] - 2026-06-29

### New Features
- **Grid & Icon List: Column Min Width** — New control so columns never get narrower than a set minimum. Grid uses `repeat(N, minmax(<value>, 1fr))` and keeps its responsive desktop/tablet/mobile column counts; Icon List uses `repeat(auto-fit, minmax(min(100%, <value>), 1fr))` to flow as many columns as fit. Icon List also gains the matching editor control it previously lacked. (#431)
- **Scrolling Gallery: image fit** — Per-image `object-fit` ("Image Fit") control plus image height/width controls. (#428)

### Bug Fixes
- **Auto-migrate legacy markup** — Added deprecations so older Accordion, Pill, Section, Slider, Form Builder, and Phone Field content (saved by earlier versions and patterns) silently migrates instead of showing "Attempt Recovery." (#427)
- **Grid & Icon List: migrate AI-pattern grid markup** — Older gd-pattern-library patterns hard-coded a responsive grid in inline CSS with no backing attribute (Grid: `repeat(N, minmax(<width>, 1fr))`; Icon List: `repeat(auto-fit, minmax(min(100%, <width>), 1fr))`). Block deprecations now capture that inline style, recover the width into the new `columnMinWidth` attribute, and re-render cleanly instead of failing validation — automatically, with no pattern edits and including already-published content. (#431)
- **Form Builder: accidental border** — The form no longer renders a black border around its wrapper; WordPress core's `html :where([style*="border-color"])` rule was matching the block's `--dsgo-form-border-color` custom property. (#426)
- **Max-width alignment** — Constrained (max-width) blocks now anchor to their text alignment instead of always centering. (#429)
- **Form Builder: useSelect stability** — The inspector no longer returns an unstable `useSelect` result, removing the re-render warning and avoidable re-renders. (#430)

### Security
- **Medium findings S1–S6** — Hardened six medium-severity security findings. (#424)

### Compatibility
- **PHP 7.4** — Lowered the minimum PHP requirement from 8.0 to 7.4. (#411)

### Internal
- **Plugin Check (PCP) compliance** — Replaced `error_log()` with `wp_trigger_error()`, prefixed all globals, and resolved DirectDatabaseQuery, AlternativeFunctions, code-offloading, and WP query-params warnings. (#412–#422)
- **`includes/` reorganization** — Reorganized `includes/` into a concern-based directory layout (file moves only). (#423)
- **E2E test sweeps** — Added happy-path end-to-end sweeps across all blocks and patterns, with automatic cleanup. (#425)

## [2.1.2] - 2026-05-13

### New Features
- **Abilities API: global CSS** — `designsetgo/get-global-css` and `designsetgo/update-global-css` for reading and writing the active theme's Additional CSS (the WordPress Customizer's "Additional CSS" panel) via `wp-abilities/v1`.
- **`designsetgo_llms_txt_extra_sections` filter** — Lets sibling plugins inject extra sections into the generated `llms.txt`.

### Bug Fixes
- **Modal: storage fallback** — Modal block now falls back to in-memory state when `localStorage`/`sessionStorage` is blocked (private mode, sandboxed iframe, storage-access denial), so "shown once" / "dismissed" modals no longer throw.
- **Abilities: get-global-css empty body** — `designsetgo/get-global-css` now accepts empty/missing JSON input on GET requests instead of returning `rest_invalid_param`. (#404)

### Performance
- **Block-detection cache** — Simplified cache key, trimmed redundant frontend enqueues, and cleared the cache before post deletion so stale entries no longer survive trash → delete cycles.

### Internal
- **Version-constant fix** — `DESIGNSETGO_VERSION` is now in sync with the plugin header (had drifted to `2.1.0` while the header was on `2.1.1`).

## [2.1.1] - 2026-04-27

### Bug Fixes
- **Eliminate `_load_textdomain_just_in_time` notices on WordPress 6.7+** — Defer Dynamic Tags default group registration to `after_setup_theme`, and defer Abilities API category/ability registration to `init` when those hooks fire before translations are loaded. The previous code path called `__()` before WordPress had finished loading translations, which surfaced as PHP notices on 6.7 and grew louder on 6.9. (#391, thanks @ncimbaljevic-godaddy)

## [2.1.0] - 2026-04-24

### New Blocks
- **Dynamic Query** — Container block that iterates Posts, Users, Terms, Manual selections, or the Current archive, with `tax_query`, `meta_query`, search, author, date, and offset controls. Server-rendered with an editable template and pluggable sources.
- **Query Pagination** — Sibling block with numbered, load-more, and infinite-scroll variations (infinite scroll uses `IntersectionObserver`, auto-pauses after 3 loads, respects `prefers-reduced-motion`).
- **Query Filter** — Sibling block with 6 variations: checkbox, select, search, sort, active-filters, and reset. Per-option counts that stay intersection-aware across multiple active filters.
- **Query No Results** — Sibling block for zero-result states.
- **Query Group Header** — Sibling block that renders once per group when group-by is enabled; receives `designsetgo/groupLabel` + `designsetgo/groupValue` context so bindings can display the current group.
- **Query Results** — Child of `designsetgo/query` that renders the iterated items; split out so non-grid hosts (Slider, Scroll Slides) can take over rendering while sharing the same source + filters.

### New Features
- **Dynamic Tags** — Inline toolbar picker for binding any block's text, title, URL, or image to dynamic data (DesignSetGo, core, ACF, Meta Box, Pods, JetEngine, or any custom source). Live preview in the editor; works on DesignSetGo blocks and any core block that opts into the WordPress 6.9 Block Bindings API.
- **Native Block Bindings support** — DesignSetGo blocks now participate in the WordPress 6.9 `block_bindings_supported_attributes` filter so their attributes can be driven by any Block Bindings source. Initial coverage includes Advanced Heading Segment, Breadcrumbs home/prefix text, and Query Pagination labels. Extensible via the `designsetgo_block_bindings_supported_attributes` filter. Inert on WordPress < 6.9.
- **DesignSetGo post-meta + ACF binding sources** — Always available (ACF source auto-registers when ACF is active). Both accept an optional `scope` arg (`self` / `parent` / `root`) for nested loop scenarios.
- **Third-party field sources — Meta Box, Pods, and JetEngine** — Three new Block Bindings sources (`designsetgo/metabox`, `designsetgo/pods`, `designsetgo/jetengine`) that delegate to each plugin's formatting API (`rwmb_meta()`, `pods_field()`, `jet_engine()->listings->data->get_meta()`) so formatted dates, files, and relationships render correctly. Each registers only when the host plugin is active.
- **Conditional block visibility** — Every block carries a `dsgoVisibility` attribute with an Advanced → Visibility inspector panel. Rule types: meta / taxonomy / index / auth, combined with AND/OR relations and ops (`equals`, `not_equals`, `contains`, `gt`, `lt`, `empty`, `not_empty`, `has`, `not_has`). Editor previews mirror server-side evaluation.
- **Per-URL Markdown content negotiation** — Any published page/post URL returns Markdown when the request sends `Accept: text/markdown` (or outranks `text/html` via q-values). Responds with `Content-Type: text/markdown; charset=utf-8` and `Vary: Accept`. Reuses the existing per-post Markdown files (falls back to live conversion), respects the llms.txt enablement flag, post-type allowlist, exclusion meta, and password-protected posts. Passes the [acceptmarkdown.com](https://acceptmarkdown.com/) readiness contract.
- **Hover Effects extension** — New universal extension for animated hover interactions (works with any block, including core).
- **Grid: column toolbar buttons** — Pick 1–6 columns directly from the Grid block's toolbar (switches to a dropdown above 6).
- **Grid: row span control** — Grid children can now span multiple rows alongside the existing column span.
- **Form builder: persist confirmation across reloads** — Submitters see the confirmation message even after refreshing the page.

### Dynamic Query
- **Filter index with per-option counts** — Persistent `{$wpdb->prefix}dsgo_query_filter_index` table auto-maintained on `save_post` / taxonomy / meta changes. Renders `(N)` counts next to each filter option; counts are intersection-aware across active filters.
- **Admin dashboard** — Settings → DesignSetGo → Dynamic Query for rebuilding the filter index and managing ad-hoc filter registrations.
- **WP-CLI** — `wp dsgo query index rebuild/rebuild-filter/status/drop`.
- **Editor live preview** — Posts use `useEntityRecords`; users and terms go through a new `/designsetgo/v1/query/preview` REST route. The first item wraps an editable `InnerBlocks`; items 2..N render server-rendered HTML (exact editor/frontend parity).
- **Template picker onboarding** — Fresh Dynamic Query inserts open a template picker (Minimal, Blog Index, Team, Portfolio, Testimonials, Related Posts, Events) instead of a grid of inserter variations.
- **Relationship source** — `source: 'relationship'` reads a relationship field (meta or ACF) on the parent post and iterates the referenced posts via `post__in`. Configurable fallback (`'empty'` / `'all'` / `'parent'`).
- **Nested loops with parent context** — Outer Query's current item flows into inner Queries via `designsetgo/parentItem` block context and `$GLOBALS['designsetgo_parent_stack']`.
- **Group-by partitioning** — `groupBy` attribute partitions iterated items by taxonomy / meta / date (year / year-month / year-month-day precision). Server wraps each group in `<section class="dsgo-query-group">` and renders the new Query Group Header block once per group.
- **Date Query builder** — Inspector UI for `before` / `after` / `between` date filters with relative expression support (`-30 days`, `today`, ISO dates).
- **Multi-level AND/OR filter groups** — Taxonomy and Meta clause builders support nested `{relation, clauses}` groups at any depth.
- **Include-children toggle** — Per-clause control on taxonomy clauses (defaults `true`).
- **Query Monitor debug panel** — When Query Monitor is active, a "DSGo (N)" panel shows per-render query args, found-posts count, duration, and SQL.
- **Template export/import** — REST routes `GET /designsetgo/v1/query/template` and `POST /designsetgo/v1/query/template` plus Export/Import buttons in the Template I/O section. JSON `schemaVersion: 1`, attribute allowlist enforced via `WP_Block_Type_Registry`, fresh `queryId` generated on import.
- **Dynamic CSS style bindings** — `dsgoStyleBinding` attribute maps CSS property names (including CSS custom properties) to a DSGo binding source + key. Values injected as inline styles on the block root via `render_block`, with `url(`, `expression(`, `javascript:` rejected.
- **Query-bound Slider and Scroll Slides** — Both blocks can act as item hosts, iterating query items as slides with exact editor-to-frontend parity.
- **CSS-only loading skeletons** — Shown via `aria-busy="true"` state during filter/pagination refreshes.
- **Interactivity API load-more + URL state** — Filter state synchronized to URL params (`q`, `sort`, `filter_<taxonomy>`). Extensible via `designsetgo_query_url_params` filter.
- **ItemList schema.org markup** for Posts queries (emitSchema attribute, default on).
- **Filter UX polish** — Distinct titles for taxonomy / meta / date filter panels, visible unchecked checkboxes, optional horizontal orientation, and modern inputs that inherit theme.json presets.

### Editor UX foundations (Themes 1–6)
- **Theme 1** — First-insert placeholder & onboarding parity: `DsgoBlockPlaceholder` wizard rolled out to accordion, flip-card, image-accordion, scroll-accordion, and slider (#356).
- **Theme 2** — Flip Card front/back child blocks consolidated into a single `designsetgo/flip-card-face` with a `side` attribute; starter colors + i18n polish (#357).
- **Theme 3** — Inspector IA standardization: every block's sidebar migrated to the Settings → Style → Advanced three-panel convention via `DsgoInspectorPanel`, with per-control reset-to-default (#360 plus rollout PRs).
- **Theme 4** — Discoverability polish: block icons, category registration, and naming cleaned up across ~30 blocks (#358, #362).
- **Theme 5** — Shared tablist keyboard hook + child toolbar: `useTablistKeyboard` and `DsgoChildToolbar` rolled out to tabs and slider with full test coverage (#359).
- **Theme 6** — Shared authoring primitives: `DsgoBlockPlaceholder`, `DsgoChildToolbar`, `DsgoInspectorPanel`, `useBlockColors`, `useTablistKeyboard`, and `useUniqueBlockId` extracted into canonical `src/hooks/` and `src/components/shared/` homes (#354).

### Improvements
- **Dynamic Image** — Theme 3 inspector with sticky footer, live editor preview, and Select-based controls for every finite-option setting.
- **Sticky header** — Smooth logo shrink transition in both scroll directions.
- **Heading Segment** — Default segment gap is now 0 so adjacent segments read as a single heading.
- **Section** — Clears default padding automatically when nested inside another Section.
- **Row** — Inner `flex-direction` now flips correctly on mobile stack.
- **Advanced Heading** — Segment appender restored on the canvas so authors can add more segments without digging into the inspector.
- **Image Accordion** — "Default Expanded Item" picker now shows item headings (no more 0–10 numeric slider).
- **Grid** — Empty appender width fix so the "+" target isn't squeezed.
- **Inspector panel controls** render full-width correctly; Tabs `activeTab` index clamped defensively on editor and frontend (#361).

### Fixed
- Form submissions: redirect URL normalized before navigation (blocks `javascript:` and other unsafe protocols).
- Dynamic Query style bindings: CSS value injection blocked (`url(`, `expression(`, `javascript:` rejected) and an explicit property BLOCKED set prevents behavioral style leaks.
- Form builder: stale confirmation cleared when the form is re-opened with a different unique key.
- Patterns: removed leftover `id="contact-professional"` on the form-builder root in starter patterns; aligned form block markup with current `save.js` output.
- Abilities API add-block output round-tripped through `save()` to prevent block validation failures (#355).
- Abilities JSON Schema — inline `required:true` migrated to JSON Schema compliant form (#352).
- llms.txt generation now writes reliably on managed hosts (WP Engine, Kinsta, Pantheon) that don't define FTP constants — file writes go through the WordPress filesystem API with a safe fallback for environments where it's unavailable.
- Sticky header: a typo in the Settings → DesignSetGo custom selector field no longer throws an exception that breaks all frontend JavaScript; an invalid selector silently falls back to the default header detection.

### Removed
- **Visual Revision Comparison** — Removed in favor of WordPress 7.0's native visual diff for revisions. Also removed the associated admin page, block differ, revision renderer, REST endpoints (`/designsetgo/v1/revisions/*`), and settings (`revisions.enable_visual_comparison`, `revisions.default_to_visual`).

### Security
- **Draft Mode REST permission callbacks** — Added nonce verification to all Draft Mode REST routes.
- **Form submissions** — Redirect URLs normalized and validated against an allowlist of safe protocols before navigation.
- **Dynamic Query style bindings** — CSS value injection blocked; dangerous CSS functions and protocols cannot be passed through a binding.
- **Global Styles sanitization** — Stored Global Styles values are now validated against a CSS-value allowlist (numeric+units, `var()`/`calc()`/`clamp()`/`min()`/`max()`, hex/rgb/hsl colors, named keywords, font-family lists). Every functional form rejects `url(`, `expression(`, `javascript:`, `<`, and `;` regardless of the wrapping function.
- **Sticky header custom selector** — Settings input now rejects HTML angle brackets, `javascript:`, `expression(`, `url(`, and `@import` patterns before the value reaches the frontend.

### Changed
- `designsetgo/post-meta` and `designsetgo/acf` binding sources accept an optional `scope` arg — defaults to `'self'`; existing bindings unchanged.
- `designsetgo/post-meta` and `designsetgo/acf` internally refactored to use the new `designsetgo_register_bindings_source()` helper — single source of truth for security gates and scope resolution; externally observable behavior is identical.

### Developer
- `designsetgo_register_bindings_source( $slug, callable $callback, array $options )` — public PHP helper wrapping `register_block_bindings_source()` with DSGo's shared post-password / viewable / protected-meta gates and the `scope` arg.
- `designsetgo_resolve_bindings_post_id( $args, $block )` — free function exposing the scope-aware post-ID resolution for callers that register via `register_block_bindings_source()` directly.
- `designsetgo_visibility_rule` filter — add custom visibility rule types by returning a bool from `($match, $rule, $context) → bool|null`.
- `designsetgo_query_args` + `designsetgo/query/{queryId}/args` — pre-`WP_Query` filter hooks (global + per-queryId).
- `designsetgo_query_partition_items( $post_ids, $group_spec )` — public PHP helper for custom group-by integrations.
- `designsetgo_query_registered_filters` filter — programmatic filter registration for the Dynamic Query filter index.
- `designsetgo_query_url_params` filter — extend the URL params the Dynamic Query IAPI store syncs to.
- `designsetgo_block_bindings_supported_attributes` filter — map block names to attribute names for native Block Bindings support.
- `$GLOBALS['designsetgo_parent_stack']` — ordered list of `{ postId, postType }` contexts available during any `render_block` hook fired inside a query item.
- REST endpoints: `/designsetgo/v1/query/render`, `/preview`, `/filter-register`, `/filter-status`, `/filter-rebuild`, `/filters`, `/template` (GET + POST).

## [2.0.51] - 2026-04-16

### Added
- Slider: editor-only slide navigator strip below the track with per-slide duplicate/remove actions and an "Add slide" button
- Slider: slide "+" appender pinned to the bottom-center of each slide so it no longer collides with the preview arrows
- Form Builder: skippable first-insert template chooser with Blank, Contact, Newsletter, Event Registration, and Lead Capture presets
- Form Builder: "Reply-To Field" is now a structured dropdown populated from actual form fields (was a raw text input)
- Image Accordion: "Default Expanded Item" is now a named item picker showing each item's heading text (was a 0–10 numeric slider)
- Tabs: inline-editable tab titles in the nav strip, per-tab duplicate/remove on hover, and an "Add tab" button
- Advanced Heading: segment appender restored so authors can add more heading segments from the canvas

### Security
- Background-video overlay color validated against an explicit CSS color grammar before assigning to the DOM — blocks `url()` / `expression()` / `javascript:` injection
- Replaced `innerHTML` with DOM APIs (`createElement` / `createElementNS`) in slider and modal frontend scripts
- LLMS Markdown REST endpoint gated at feature-disabled check before the rate-limiter to prevent post-existence enumeration on disabled installations
- Normalized CSS unicode escapes and null bytes before the custom CSS sanitizer's regex pipeline; added a final defense pass after the filter hook

### Fixed
- Tabs frontend no longer showed "Click the + button below to add content to this tab" — the `block.json` style asset was pointing at the editor CSS bundle
- An empty Form Builder (placeholder dismissed without picking a template) no longer renders an orphan submit button on the frontend

## [2.0.50] - 2026-04-14

### Fixed
- Form submissions not sending email notifications — server-side block attribute lookup now honors `block.json` defaults so forms with default settings correctly trigger admin email on submit

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
