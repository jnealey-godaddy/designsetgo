=== DesignSetGo ===
Contributors: justinnealey, ziontrooper
Donate link: https://designsetgoblocks.com/donate
Tags: blocks, gutenberg, form-builder, query-loop, animations
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

58 native blocks + 18 universal extensions for the WordPress block editor. Forms, dynamic post lists, animations, layouts — no page builder needed.

== Description ==

**The power of a page builder, the simplicity of native blocks.**

DesignSetGo brings forms, sliders, dynamic queries, animations, and parallax to the block editor — without the bloat, lock-in, or learning curve. If you know WordPress blocks, you already know how to use it.

[Documentation](https://designsetgoblocks.com/docs/) · [GitHub](https://github.com/jnealey-godaddy/designsetgo)

= Why DesignSetGo =

* **Native blocks, not a page builder.** Editor matches frontend. Static content stays put if you deactivate; dynamic blocks need the plugin to render. No proprietary markup, no lock-in.
* **58 blocks replace 5+ plugins.** Forms, sliders, tabs, accordions, modals, maps, breadcrumbs, timelines, comparison tables, and the new Dynamic Query family.
* **18 extensions enhance ANY block** — including core and third-party blocks. Animations, parallax, sticky headers, responsive visibility, hover effects, conditional visibility.
* **Complete form builder built in.** AJAX, spam protection (Cloudflare Turnstile included), email notifications, submission dashboard. No Contact Form 7 required.
* **Performance first.** CSS bundle under 10 KB gzipped, no jQuery, per-block on-demand assets. PageSpeed scores stay high.
* **WordPress-standard everything.** theme.json, FSE, Block Bindings, REST API, WP-CLI, Schema.org markup, WCAG 2.1 AA accessible.

= What's Inside =

* **Layout** (4) — Grid, Row, Section with shape dividers, Section Divider
* **Forms** (13) — full builder with 11 field types and admin dashboard
* **Interactive** (15) — Tabs, Accordion, Modal, Modal Trigger, Flip Card, Slider, Scroll Slides, Sticky Sections, Scroll Marquee, Scroll Accordion, Image Accordion, Counter, Progress, Comparison Table, Timeline
* **Dynamic Query** (6) — Query, Pagination, Filter, Results, Group Header, No Results
* **Typography & Navigation** — Advanced Heading, Breadcrumbs (Schema.org), Table of Contents
* **Visual** (12) — 160+ Icons, Icon Button, Icon List, Pills, Cards, Dividers, Countdown, Blobs, Dynamic Image, Hotspot, Star Rating, Text Path
* **Data** (1) — Chart (bar, line, donut — no charting library loaded)
* **Media & Location** — Fifty Fifty split layout, Map (Google Maps + OpenStreetMap)
* **WooCommerce** — Product Categories Grid, Product Showcase Hero, product bindings, product-aware Dynamic Query
* **Extensions** (18) — Animations, Interactions, Schema.org Markup, Parallax, Text Reveal, Expanding Background, Sticky Header, Hover Effects, Clickable Groups, Background Video, Responsive Visibility, Conditional Visibility, Max Width, Custom CSS, Grid Span, Grid Mobile Order, SVG Patterns (25+), Reveal Control
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

[Documentation](https://designsetgoblocks.com/docs/), the [support forum](https://wordpress.org/support/plugin/designsetgo/), or [GitHub](https://github.com/jnealey-godaddy/designsetgo).

== Screenshots ==

1. Container block with responsive grid layout and video background support
2. Tabs block with horizontal orientation, icons, and multiple style options
3. Accordion block with collapsible panels and smooth animations
4. Counter Group block with animated statistics and number formatting
5. Icon block with 160+ icons, shape styles, and customization options
6. Progress Bar block with animated fills and multiple display styles
7. Block animation controls showing entrance effects and timing options
10. Mobile responsive preview in the editor

== Changelog ==

= 2.7.2 - 2026-08-26 =

* **Fix:** Advanced Heading — the typing effect no longer hides the last letter of each word. It also now types one character at a time, so short words no longer appear in twelve tiny slivers.
* **Fix:** Advanced Heading — the typing, clip, and blinds effects no longer shave the tops and tails off letters. Descenders on g, j, p, q, y and accented capitals were being cut.
* **Fix:** Text Path — turning on Motion now animates in the editor, not only on the published page. The frontend was always animating; the editor just never previewed it, which made the setting look broken.
* **Fix:** Advanced Heading — segments no longer run together in the editor. Plain segments were missing the spacing the published page gets, so the editor showed them squished while the frontend looked correct.

= 2.7.1 - 2026-08-26 =

* **Fix:** The Chart block works again. On 2.7.0, inserting a Chart showed "Error loading block" instead of the chart — the block's preview is drawn on the server, and the request that draws it was being rejected. Charts already saved on a page were unaffected; only the editor preview failed.
* **Fix:** Chart y-axis labels stay inside the chart. Longer labels — anything past about seven characters, which includes any formatted currency — were painted outside the block's own area, over whatever sat to the left of them. The space reserved for them is now measured from the labels themselves.
* **New:** Chart — value prefix, suffix, and thousands grouping. Put a $ in front of every value or a % after it, and turn on grouping to read 1,234,567 instead of 1234567. These apply to the value labels, the y-axis, and the data table screen readers use, so the axis matches the bars. Donut slices keep showing their share of the total.

= 2.7.0 - 2026-08-25 =

* **New:** Chart — bar, line, and donut charts drawn as plain SVG, with a colour control per bar or slice. Type the data in by hand or read it from a post meta field. No charting library is loaded, and every chart also emits a screen-reader data table so the numbers are readable without the picture.
* **New:** Star Rating — show a rating as stars, either a fixed value or pulled from post meta, ACF, or a product's WooCommerce average rating. Ships with a Reviews pattern, and can feed its value into review structured data.
* **New:** Hotspot — place interactive markers over an image, each with its own tooltip. Fully keyboard accessible.
* **New:** Text Path — flow a line of text along a wave, arc, circle, oval, spiral, straight line, or an SVG path of your own, with controls for arc size, rotation, word spacing, and padding, plus an optional motion mode that travels the text along the path.
* **New:** Animated Headline — Advanced Heading gains two headline modes. Rotating cycles a list of phrases through one of nine effects (typing, clip, flip, swirl, blinds, drop-in, wave, slide, slide-down), forward or in reverse, with duration, delay, and loop controls. Highlighted draws a hand-drawn mark behind or through a phrase — circle, curly, underline, double underline, zigzag, diagonal, strikethrough, or an X.
* **New:** Interactions — a new extension on every block. Bind a trigger (click, hover, scroll into view, exit intent, keypress) to an action on any other block: toggle a class, set an attribute, scroll to it, open or close a modal, show or hide something, play or pause media, focus a form field, or copy to clipboard. Edited in a modal with a point-and-click target picker on the canvas.
* **New:** Schema.org markup — opt an Accordion into FAQ or How-to structured data, and a Star Rating into Review or Aggregate rating. Nothing is emitted unless you deliberately choose a type, and password-protected content is never exposed.
* **New:** Off-canvas panel — Modal gains a panel mode that slides in from any edge, available as its own block variation, with a size control. It keeps the modal's focus trap, Escape handling, scroll lock, and triggers.
* **New:** Deeper animations — four additions to the Animations extension: stagger a container's children, scroll-linked animation that tracks the scroll position rather than firing once, SVG path drawing, and per-word or per-character text reveal with fade and rise. All CSS-driven, no animation library, and every one is fully disabled under "reduce motion".
* **New:** WooCommerce — Dynamic Query gains product controls (catalog visibility, featured, on sale, stock status) and reads the URL parameters WooCommerce's own filter blocks emit, so Woo's filter UI can drive a DesignSetGo loop. Six new binding sources expose raw product values — price, regular price, discount percent, stock quantity, average rating, and Woo's formatted price — for text and for dynamic CSS, so a stock bar or a discount badge is just a bound value.
* **New:** Map — a keyless Google Maps option that needs no API key at all, and loads no JavaScript on the page. The existing keyed Google and OpenStreetMap options are unchanged, and privacy mode still holds the map back until the visitor clicks Load Map.
* **New:** Loop Carousel — Slider and Scroll Slides now work properly as Dynamic Query layouts. Load more and filters work inside a carousel; infinite scroll, which cannot work inside a fixed-height viewport, degrades to a Load more button, and the editor says so up front with a one-click switch instead of leaving readers stranded on page one.
* **Fix:** Slider — a batch of fixes that apply to any slider, not just query-driven ones: responsive slides-per-view was read and then ignored; a multi-slide slider scrolled past its own last slide; asking for more slides per view than there were slides broke the block; dragging snapped back to the first slide; a vertical scroll changed slides; autoplay stopped after one tick, and kept running in background tabs and under "reduce motion"; keyboard focus could land on an off-screen slide; a duration entered in milliseconds froze the slider; and every page containing a slider was excluded from the browser's back/forward cache.
* **Fix:** One click on Load more no longer fetches and appends the next page twice — eight new posts could arrive as twelve items.
* **Fix:** A Loop Carousel placed inside another query's template previewed the outer query's posts in the editor while the frontend correctly showed the inner one's.
* **Improved:** WordPress 7.1 support. 7.1 lets you set per-viewport (mobile/tablet) style values on any block; those values now land on the same element as the desktop value for Pill, Scroll Marquee, Icon, Icon Button, and Modal Trigger, instead of a mobile background smearing across the content column or a mobile margin doubling up. This also corrects where theme.json styles for those blocks apply. Tested up to WordPress 7.1.
* **Improved:** AI and agent tooling — the Abilities API discovery endpoints now describe the plugin accurately. Categories, block groupings, and the WooCommerce binding group were all reported wrongly, so filtering returned partial or empty results; all 20 abilities now also declare whether they read or write, and the documentation covers all of them.
* **Fix:** Advanced Heading and Hotspot editing is smoother on long pages, duplicating a Text Path no longer breaks the original's shape, and hotspot tooltips sit on their markers instead of drifting away from them.
* **Fix:** Translations — removed a JavaScript translation catalog whose files were keyed so that WordPress could never load them, and regenerated the translation template.

= 2.6.3 - 2026-08-11 =

* **Fix:** Grid columns with a minimum width set no longer run past the edge of the page. On a theme with a narrower content width, a grid asking for more columns than could fit at that minimum pushed its columns straight out of the content area. The grid now drops to fewer columns and wraps the extra items onto the next row, and still shows your chosen number of columns whenever there's room for them. Existing grids are unchanged.
* **Fix:** The Overlay Header panel no longer causes an error when editing a template part in the Site Editor. The panel now appears only where the setting actually applies.

= 2.6.2 - 2026-08-05 =

* **Fix:** Sticky and overlay headers keep working on sites that replace page content without a full browser reload — an AI page builder or an AJAX-driven theme, for example. After one of those updates the header stayed where it belonged but stopped fading in its background as you scrolled, and only a full page refresh brought the behaviour back.
* **Fix:** The footer no longer paints the sticky header's drop shadow across its own top edge when you scroll. Most themes put a navigation menu in the footer, and that alone was enough for the footer to be treated as a second header.

= 2.6.1 - 2026-08-04 =

* **Fix:** Turning on "background on scroll" without choosing a color now gives you your theme's background, rather than no background at all. The header also darkens or lightens its text to match, so it stays readable whichever way your theme's palette runs.
* **Fix:** Forms on pages served from a full-page cache no longer fail with "Security verification failed. Please refresh the page and try again." A cached page can outlive the security token baked into it, and the form had no way to recover; it now quietly retries and submits successfully.
* **Fix:** The scrolled sticky/overlay header now picks up your theme's colors instead of always fading in to a near-white bar. Pages using the per-page Overlay Header never reached the code that applies your chosen scroll background, so they fell back to a hardcoded white that looked out of place on darker palettes. The header now prefers your theme's secondary surface color, and in dark mode pairs your palette's contrast and base colors so the text stays readable. Themes that don't define those colors look exactly as they did before, and any scroll background you set explicitly still wins.
* **Fix:** Forms placed outside the main post content — a newsletter signup in the footer, a form in a template part, synced pattern, or block widget — no longer fail every submission. The form looked and behaved normally right up until Submit, then silently failed because the data it needs to talk to the server was only attached when the form lived in the page's own content.

= 2.6.0 - 2026-07-29 =

* **New:** Theme animation defaults — set an entrance animation once per block type (e.g. all Buttons fade in) under Settings → DesignSetGo → Animations, or in your theme's `theme.json`. Every block of that type inherits it automatically, and any individual block can override it (Custom) or opt out (Off). One rule can target several block types at once, including `namespace/*` wildcards.
* **Fix:** The per-page Overlay Header is transparent again and no longer pulls page content up by the footer's height. The header height was being measured from the wrong element on themes whose footer also contains a navigation block, which sliced the top off the hero; and the header stayed opaque on themes that paint it from a style variation or `theme.json` rather than a background class. Your first content section now also clears the header while its background still runs behind it.
* **Fix:** Section shape dividers now honour your theme's divider height and width tokens. Previously a theme (or Style Kit) could set the default divider shape but not its size — the Section block's top/bottom dividers ignored both size tokens. Untouched dividers now inherit the theme's size (and reserve matching content clearance), while any size you set explicitly still wins. The Height/Width sliders gain a Reset that returns them to the theme default. Existing content is unchanged.

= 2.5.1 - 2026-07-23 =

* **New:** Section shape dividers — six new layered, tonal divider shapes: Triangle Layered, Triangle Layered Extra, Curvy Triangle Layered, Symmetric Waves Layered, Side Triangle Layered, and Side Triangle Layered Extra. Each paints as a soft two- or three-tone band that inherits your theme's color by default and can be overridden per section.
* **Fix:** Section shape-divider spacing is now author-defined, so patterns that set the content clearance with a theme spacing token no longer show an "Attempt Recovery" prompt. A new "Content Clearance" control sets the gap between your content and the divider; existing dividers migrate silently, and a divider with no clearance set automatically reserves space to match its own height.

= 2.5.0 - 2026-07-21 =

* **New:** Grid — an "Align Rows" option that lines up each row of card content (image, heading, text, button) across columns, so cards with different amounts of text stay aligned with no ragged whitespace. Works with Section, Row, and Group cards, and is off by default so existing grids are unchanged.
* **New:** Form Builder — the submit button now has a Button Style control (Default, Secondary, or Outline), so a form placed on a colored background can use a matching button. Your theme's button style variations apply to it too, and AI-assisted form inserts respect the chosen style.
* **Fix:** Blocks whose on-screen text is changed by a site translation (or other content tools) no longer show an "Attempt Recovery" prompt. Icon Button, Modal Trigger, Accordion, Timeline, Counter, Card, Table of Contents, Form Builder, and Countdown Timer now treat their visible label as the single source of truth, so translating the text keeps the block valid — and existing content migrates silently.
* **Fix:** Forms and responsive grids on sites built with the AI site designer no longer show an "Attempt Recovery" prompt. Their saved markup differed slightly from what the current blocks produce; the affected forms and grids now migrate silently and keep their design.
* **Fix:** Modal — the overlay (backdrop) color now inherits from your theme and can be restyled by a Style Kit, instead of always being baked to black. Modals saved from patterns no longer show an "Attempt Recovery" prompt.
* **Fix:** Form Builder — an inline (side-by-side) submit button now lines up level with the field beside it, in both the editor and on the frontend, and the loading spinner now shows correctly on styled submit buttons.
* **Fix:** Cloudflare Turnstile now accepts its tokens. Previously, turning Turnstile on silently broke the form — every protected submission was rejected before it reached the handler.
* **Fix:** Excluding a third-party block from DesignSetGo's controls now takes effect in the editor. Excluded blocks (such as Gravity Forms) no longer receive DesignSetGo panels or show an "invalid block attributes" error.
* **Fix:** Query Monitor no longer causes a site error on every page load when its debugging panel is active.
* **Fix:** Draft Mode — publishing a draft no longer deletes custom fields that were intentionally kept out of the draft copy, so integrations that store their own bookkeeping data keep it across publishes.
* **Security:** Hardened Draft Mode's post-copy against maliciously deep data and closed a window where a stale, still-open settings form could overwrite a saved API key with its masking placeholder. These strengthen existing protections — no known exploit was involved.

= 2.4.0 - 2026-07-12 =

* **New:** Section Divider — a new block for dropping a full-width shape divider between any two blocks. Shape, height, and color default to your theme's Style Kit setting and can be overridden per instance.
* **New:** The Icon block has a Fill / Outline style toggle and inherits a theme-defined default size, so icons match your design out of the box.
* **New:** Icon Button now inherits the core Button block's style variations (Fill, Outline, and any your theme adds).
* **New:** Scrolling Gallery now uses WordPress's native border controls (width, style, color, and radius) in place of the old single border-radius field.
* **New:** SVG Patterns can inherit a "Theme default" preset set at the theme level, so a Style Kit can restyle every pattern across your site at once — each block can still opt out and set its own.
* **New:** Row and Grid now support the same background-overlay and hover-state style options as Section, so a Style Kit's overlay and hover styling applies consistently across all three layout blocks.
* **Improved:** Icon, Divider, Map, and all form field blocks now render dynamically, so they always reflect your current theme — change a design token once and every instance updates, with cleaner saved markup.
* **Improved:** The Pill block now renders dynamically — saved pills no longer bake in a fixed size or alignment, so they stay portable across patterns and AI-assisted edits and always reflect your theme.
* **Improved:** Form Builder fields inherit spacing and sizing from your theme, so forms match the rest of your site automatically.
* **Improved:** Map markers can now take their color from your theme.
* **Improved:** Section style options (like core's Style 1–5) now also apply to Card, Fifty/Fifty, Modal, Slide, Scroll Slide, Tab, Accordion Item, Scroll Accordion Item, Image Accordion Item, Timeline Item, Counter, and Flip Card Face — matching Section, Row, and Grid.
* **Fix:** Icon List items now show their fill / outline and stroke on the frontend, matching the editor.
* **Fix:** SVG Patterns and Form Builder no longer bake default colors into saved markup, so they inherit your theme's colors.
* **Fix:** Content with quotes or backslashes saved through AI-assisted edits (Abilities API) is no longer altered.
* **Fix:** Form submissions no longer drop backslashes — a message containing a file path like C:\Users\me or a code snippet was previously saved with the backslashes stripped. Submitted values are now stored exactly as entered.
* **Fix:** Scrolling Gallery blocks saved by older versions or patterns (image rows stored in the markup rather than the block comment) keep migrating silently instead of showing "Attempt Recovery."
* **Fix:** An Image Accordion's overlay color and opacity now correctly reach the frontend — previously they only showed in the editor. Turning "Enable Overlay" off now also removes the overlay on the frontend, not just in the editor.
* **Improved:** An Image Accordion's overlay color and opacity now inherit from your theme's Style Kit (with per-block overrides) instead of being baked into each block, so a Style Kit can restyle every accordion's scrim at once. Note: existing accordions left at the old default overlay will follow a theme overlay token once one is set, so their scrim can change appearance when a Style Kit defines one — with no edit to the page.
* **Fix:** Section-style customizations made in your Style Kit (like a custom border or corner radius) now preview live in the editor, matching what already appeared on the published page.
* **Fix:** Sections using a Style Kit overlay variation now actually show the overlay.
* **Fix:** Scroll Accordion no longer shows a stray colored bar down the left edge of items in the editor.
* **Security:** Hardened form notification emails, dynamic CSS handling, and AI-assisted block insertion. Submitted form values are now escaped before they appear in notification emails, so a submission can no longer inject markup into your inbox; dynamic style values are checked more strictly; and a Tabs block with an unusual page anchor in the URL no longer stops working. These strengthen existing protections — no known exploit was involved.
* **Fix:** Tab and Blobs blocks no longer quietly lose a setting when a page is opened in the editor. A Tab's icon stroke width and a Blob's height could be reset to their defaults simply by opening the post, because the block was being run through an out-of-date upgrade path that predated those settings. Both now survive.
* **Fix:** Block styles now refresh properly when you update the plugin. Each block's stylesheet was being served from your browser's cache with the same address every release, so a visual fix to a block could keep showing the old styling until the cache happened to clear itself. If you saw an oversized icon inside an Icon Button after updating, a hard refresh cleared it — and it will not come back.
* **Fix:** Pill, Icon, Icon Button, and Modal Trigger no longer escape your page's content column when positioned left, right, or center — they previously could align to the edge of the outer container instead of your theme's content width.
* **Change:** Left, center, and right positioning for Pill, Icon, Icon Button, and Modal Trigger has moved from the toolbar's Align control to a new Justify control in the same toolbar. Align now offers only Wide and Full, matching how WordPress's own Buttons block works. Your existing positioning carries over automatically.
* **Change:** Icon Button and Modal Trigger now stack instead of sitting side-by-side by default. Fixing the positioning bug above required making them block-level elements, so two placed one after another now each take their own line; use a Row block to place them side-by-side intentionally.
* **Change:** Icon Button and Modal Trigger's "stretch full width" option is now a dedicated Full Width setting instead of reusing WordPress's Full Width alignment. Inside a Section — where nearly all usage lives — the rendered result is unchanged.
* **Change:** Icon background and border colors now hug the icon itself instead of spanning the full content column.
* **Note:** Existing Icon, Divider, Map, and form blocks migrate automatically — no action required. Existing Pill, Icon Button, and Modal Trigger content keeps rendering exactly as before and switches to the new positioning wrapper the next time the post is opened and re-saved in the editor.

= 2.3.0 - 2026-07-01 =

* **New:** Theme "section style" variations now also apply to DesignSetGo Section, Row, and Grid blocks (matching the core Group / Columns / Column blocks).
* **New:** Sections can inherit a site-wide default shape divider set at the theme level, and each section can still override it.
* **New:** The plugin dashboard now links to designsetgo.dev — a runtime for hosting the apps you build with AI on your WordPress site.
* **Change:** Shape dividers now use CSS masks instead of an inline SVG — the shape inherits the section's background, default height/width are omitted from markup, and the drops/fan/steps/slime shapes were redesigned.
* **Tweak:** Refreshed the admin dashboard logo and menu icon to the current DesignSetGo brand mark.
* **Fix:** A theme-level default shape divider now resolves correctly instead of falling back to Wave.
* **Fix:** Sections saved with drops, fan, steps, or slime dividers before this release keep migrating silently instead of showing "Attempt Recovery."

= 2.2.0 - 2026-06-29 =

* **New:** Grid & Icon List — a "Column Min Width" control so columns never get narrower than a set minimum (Grid uses `minmax(value, 1fr)` and keeps its responsive column counts; Icon List uses `auto-fit` to flow as many columns as fit).
* **New:** Scrolling Gallery — a per-image "Image Fit" (object-fit) control, plus image height and width controls.
* **Fix:** Grid and Icon List blocks from older AI-generated patterns (CSS-only responsive grid, no attribute) now migrate to Column Min Width automatically instead of showing "Attempt Recovery."
* **Fix:** Added deprecations for older Accordion, Pill, Section, Slider, Form Builder, and Phone Field markup — legacy content auto-migrates silently.
* **Fix:** Form Builder no longer renders an accidental black border around the whole form (WordPress core's `[style*="border-color"]` rule was matching the form's CSS custom property).
* **Fix:** Max-width blocks (such as a constrained heading) now follow their text alignment instead of always centering.
* **Fix:** Form Builder editor no longer triggers a `useSelect` re-render warning, reducing unnecessary inspector re-renders.
* **Compat:** Lowered the minimum PHP requirement from 8.0 to 7.4.
* **Security:** Hardened six medium-severity findings (S1–S6).
* **Internal:** PCP compliance pass (`error_log()` → `wp_trigger_error()`, global prefixes, direct-DB and alternative-function warnings resolved), `includes/` concern-based reorganization (file moves only), and new happy-path E2E test sweeps.

= Earlier releases (2.1.x and earlier) =

For the full version history, see [CHANGELOG.md](https://github.com/jnealey-godaddy/designsetgo/blob/main/CHANGELOG.md) in the GitHub repository. Highlights:

* **2.1.x** — Dynamic Query block family, Dynamic Tags, Block Bindings, Meta Box/Pods/JetEngine sources, Conditional Visibility, per-URL Markdown, Hover Effects, editor UX refresh, and security hardening.
* **2.0.x** — Comparison Table, Timeline, Advanced Heading, Shape Dividers, Draft Preview Mode, 150+ patterns, WooCommerce blocks, form improvements, and major bug fixes.
* **1.4.x** — llms.txt for AI language models, Draft Mode, Visual Revision Comparison, PHP 8.0+.
* **1.3.x** — Abilities API (50 AI abilities), scroll-driven extensions, Text Style, Cloudflare Turnstile.
* **1.0–1.2** — Initial public release: 43 blocks + 11 extensions, Map and Card blocks, REST API hardening, 9 translations.

== Upgrade Notice ==

= 2.7.2 =
Fixes four display problems: the Advanced Heading typing effect cutting off the last letter, the clip-based effects shaving the tops and tails off letters, Text Path motion not previewing in the editor, and heading segments running together in the editor. Includes everything in 2.7.1.

= 2.7.1 =
Fixes the Chart block, which could not be inserted on 2.7.0 — it showed "Error loading block" instead of a chart. Also keeps long y-axis labels inside the chart, and adds a value prefix/suffix and thousands grouping. Recommended for anyone on 2.7.0.

= 2.7.0 =
Four new blocks (Chart, Star Rating, Hotspot, Text Path), an Interactions extension for wiring any block to any other, Schema.org markup, animated headlines, an off-canvas panel mode for Modal, deeper animations, WooCommerce product bindings and product-aware queries, a keyless Google Maps option, and a large batch of Slider fixes. Tested up to WordPress 7.1. No content migration required.

= 2.4.0 =
Pill, Icon, Icon Button, and Modal Trigger now stay inside the page content column when positioned. Adjacent Icon Buttons and Modal Triggers now stack instead of sitting side-by-side. Existing content renders as before until each post is re-saved. See the changelog.

= 2.2.0 =
Responsive Column Min Width for Grid and Icon List, Scrolling Gallery image-fit control, automatic migration of legacy block markup (no more "Attempt Recovery"), form-border and max-width alignment fixes, PHP 7.4 support, and a Plugin Check + security pass.

= 2.1.1 =
Patch fix for WordPress 6.7+: eliminates `_load_textdomain_just_in_time` PHP notices triggered by early translation function calls. Recommended for all sites.

= 2.1.0 =
Major update: Dynamic Query block family (posts/users/terms with filters, pagination, faceted counts), Dynamic Tags, native WP 6.9 Block Bindings, Meta Box/Pods/JetEngine field sources, conditional visibility, per-URL Markdown, Hover Effects, editor UX refresh, and security hardening.

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
Major update: 3 new blocks (Comparison Table, Timeline, Advanced Heading), 2 new extensions (Grid Mobile Order, SVG Patterns), shape dividers, 150+ patterns and 12 homepage templates, frontend draft preview, lazy editor loading, reduced motion support, plus bug fixes and a security improvement.

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
