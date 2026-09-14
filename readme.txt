=== DesignSetGo ===
Contributors: justinnealey, ziontrooper
Tags: blocks, gutenberg, form-builder, query-loop, animations
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.7.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

57 native blocks + 18 universal extensions for the WordPress block editor. Forms, dynamic post lists, animations, layouts — no page builder needed.

== Description ==

**The power of a page builder, the simplicity of native blocks.**

DesignSetGo brings forms, sliders, dynamic queries, animations, and parallax to the block editor — without the bloat or learning curve. If you know WordPress blocks, you already know how to use it.

[Documentation](https://designsetgoblocks.com/docs/) · [GitHub](https://github.com/jnealey-godaddy/designsetgo)

= Why DesignSetGo =

* **Native blocks, not a page builder.** You build with the block editor you already use, and what you see in the editor is what visitors see.
* **57 blocks replace 5+ plugins.** Forms, sliders, tabs, accordions, modals, maps, breadcrumbs, timelines, comparison tables, charts, and the Dynamic Query family.
* **18 extensions enhance ANY block** — including core and third-party blocks. Animations, parallax, sticky headers, responsive visibility, hover effects, conditional visibility.
* **Complete form builder built in.** AJAX submissions, spam protection (honeypot, rate limiting, and optional Cloudflare Turnstile), email notifications, and a submissions dashboard. No Contact Form 7 required.
* **Performance first.** Nothing loads on pages that don't use DesignSetGo. Where its blocks are used, a shared stylesheet under 10 KB gzipped loads, plus only the CSS and JavaScript of the blocks on that page. No jQuery.
* **WordPress-standard everything.** theme.json, Site Editor, Block Bindings, REST API, WP-CLI, and Schema.org markup. Built with keyboard navigation, ARIA attributes, focus management, and colour contrast checks.
* **Dynamic data.** Bind text and styles to post meta, ACF, Meta Box, Pods, JetEngine, and WooCommerce fields.

= What's Inside =

* **Layout** (4) — Grid, Row, Section with shape dividers, Section Divider
* **Forms** (12) — Form Builder plus 11 field types, with a submissions dashboard
* **Interactive** (15) — Tabs, Accordion, Modal, Modal Trigger, Flip Card, Slider, Scroll Slides, Sticky Sections, Scrolling Gallery, Scroll Accordion, Image Accordion, Counter Group, Progress Bar, Comparison Table, Timeline
* **Dynamic Query** (6) — Query, Pagination, Filter, Results, Group Header, No Results
* **Typography & Navigation** (3) — Advanced Heading, Breadcrumbs (Schema.org), Table of Contents
* **Visual** (12) — Icon (160+ icons), Icon Button, Icon List, Pill, Card, Divider, Countdown Timer, Blobs, Dynamic Image, Hotspot, Star Rating, Text Path
* **Data** (1) — Chart (bar, line, donut — no charting library loaded)
* **Media & Location** (2) — Fifty Fifty split layout, Map (OpenStreetMap or Google Maps)
* **WooCommerce** (2) — Product Categories Grid, Product Showcase Hero, plus product bindings and product-aware Dynamic Query
* **Extensions** (18) — Animations, Interactions, Schema.org Markup, Parallax, Text Reveal, Expanding Background, Sticky Header, Hover Effects, Clickable Groups, Background Video, Responsive Visibility, Conditional Visibility, Max Width, Custom CSS, Grid Span, Grid Mobile Order, SVG Patterns (25+), Reveal Control
* **Plus** — Text Style inline format, llms.txt + per-URL Markdown for AI, draft mode for published pages

= External services =

DesignSetGo only contacts outside services for the features below, and only when those features are used.

* **OpenStreetMap** — The Map block's default provider. When a visitor views a page with an OpenStreetMap map, their browser loads map tiles from tile.openstreetmap.org, which receives their IP address. If a map has an address but no saved coordinates, the visitor's browser also sends that address to nominatim.openstreetmap.org to locate it. Searching for an address in the Map block settings uses the same service. Turn on the block's Privacy Mode to load a map only after the visitor clicks. [Tile usage policy](https://operations.osmfoundation.org/policies/tiles/), [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/), [privacy policy](https://osmfoundation.org/wiki/Privacy_Policy).
* **Google Maps** — Used only when you choose a Google provider on a Map block. The keyless option loads a map from maps.google.com with the map's address or coordinates. The API option loads the Maps JavaScript API from maps.googleapis.com with the API key you enter under DesignSetGo → Settings → Integrations. Either way the visitor's browser makes the request, and Privacy Mode applies. [Google Maps Platform terms](https://cloud.google.com/maps-platform/terms), [Google privacy policy](https://policies.google.com/privacy).
* **Cloudflare Turnstile** — Used only when you add Turnstile keys under DesignSetGo → Settings → Integrations and turn Turnstile on for a form. The visitor's browser loads the challenge from challenges.cloudflare.com, and on submission your site sends the Turnstile token and the visitor's IP address to Cloudflare to verify it. [Cloudflare terms](https://www.cloudflare.com/website-terms/), [Cloudflare privacy policy](https://www.cloudflare.com/privacypolicy/).

Two bundled patterns (Video Testimonial and Hero with Video Modal) include a sample YouTube video through WordPress's own embed block. Replace it with your own video, or visitors' browsers will load it from YouTube.

The Slider block's Scroll Carousel variation starts with four sample images hosted by [Lorem Picsum](https://picsum.photos/). Replace them with your own images, or visitors' browsers will load them from picsum.photos. The Scrolling Gallery preview in the block inserter also shows sample images from Lorem Picsum.

= Privacy =

* DesignSetGo has no tracking or analytics, and sends nothing about your site to its developers.
* Form submissions are stored on your own site, together with the submitter's IP address, browser user agent, and the page they submitted from. They are deleted after 30 days by default; change this under DesignSetGo → Settings → Features → Forms. Notifications are sent through WordPress's own email.
* Submissions are included in WordPress's Export Personal Data and Erase Personal Data tools, matched by email address. DesignSetGo also suggests text for your privacy policy.
* Browser storage is used only to make features work: a modal set to show once per session or once per user remembers that it was shown, a form keeps its confirmation message across the page reload after a non-AJAX submission, and Draft Mode sets a cookie for logged-in editors previewing a draft.

== Installation ==

1. **Plugins → Add Plugin**, search **DesignSetGo**, click **Install**, then **Activate**.
2. Edit any post or page, click **+**, and look for the **DesignSetGo** category.

Manual install: upload the ZIP via **Plugins → Add Plugin → Upload Plugin**.

== Frequently Asked Questions ==

= Will it work with my theme? =

Yes. DesignSetGo respects theme.json colors, spacing, and typography, and is tested with block themes including Twenty Twenty-Five.

= Will it slow my site down? =

DesignSetGo loads nothing on pages that don't use its blocks. On pages that do, it loads a shared stylesheet under 10 KB gzipped, plus the CSS and JavaScript of only the blocks on that page. There's no jQuery.

= What happens if I deactivate it? =

Blocks saved as HTML — layouts, tabs, accordions, cards, and similar — stay in your posts, but lose DesignSetGo's styling, animations, and interactive behaviour. Blocks the plugin renders on the server, such as forms, Dynamic Query, sliders, maps, icons, pills, and charts, show nothing until you reactivate it. When you click Deactivate, DesignSetGo offers to convert Section, Row, Grid, and Icon Button blocks to core WordPress blocks first, and saves a revision of each post it changes.

= Do I need to know code? =

No. Everything is controlled through the block settings. Custom CSS per block is available if you want it.

= Does it work with the Site Editor and WooCommerce? =

Yes to both. DesignSetGo blocks work in the Site Editor, templates, and template parts, and the Sticky Header extension is built for header template parts. With WooCommerce active you also get product blocks, product bindings, and Dynamic Query product controls that work with WooCommerce's own filter blocks.

= Where do I get support? =

[Documentation](https://designsetgoblocks.com/docs/), the [support forum](https://wordpress.org/support/plugin/designsetgo/), or [GitHub](https://github.com/jnealey-godaddy/designsetgo).

== Screenshots ==

1. Scrolling Gallery block with rows of images moving across the page
2. Scroll Slides block with a topic list that changes the content as you scroll
3. Grid of feature cards with icons
4. Slider block showing three slides at a time
5. Timeline block laying out steps in order
6. Quick animation controls in the block toolbar
7. Slider with arrows and dots, and a multi-slide slider below it

== Changelog ==

= 2.7.5 - 2026-09-10 =

* **Fix:** On some hosts, DesignSetGo couldn't create the database table that powers Dynamic Query filters, and it tried again on every admin page. That slowed wp-admin down enough for WordPress updates to time out. The table now installs on those hosts, and if it ever can't, DesignSetGo waits a day before trying again instead of retrying on every page.
* **New:** If DesignSetGo can't create that table, administrators now see a notice explaining why, with a **Retry now** link. You can dismiss it. It stays on DesignSetGo's own admin pages until the problem is fixed.
* **Fix:** Shape dividers, SVG patterns, expanding backgrounds and text reveal effects now work on blocks added by an AI assistant. The editor used to flag those blocks as invalid.

= 2.7.4 - 2026-09-10 =

* **Fix:** Dynamic Query — filters, sort, search, Load more and infinite scroll now work for visitors who aren't logged in. On 2.7.3 the request behind them required a login, so for everyone else clicking did nothing.
* **Fix:** Dynamic Query — Load more keeps the active filters, search and sort. It used to fetch the next page of the unfiltered results and add it under the filtered ones.
* **Fix:** Dynamic Query — accordions, counters, flip cards, maps, modals and other interactive blocks inside results added by Load more or infinite scroll now start up. Only the first page's items used to work.
* **Fix:** Dynamic Query — the request behind filters and Load more now only renders a query the site itself put on the page, instead of whatever query settings the browser sent. Grouping by a protected custom field (one whose name starts with an underscore) no longer prints that field's values as headings, and a query on a password-protected page stays locked until the password is entered.
* **Fix:** Dynamic Query's editor preview now requires permission to edit posts. Any logged-in account, a subscriber or a store customer included, could use it to list content that isn't public.
* **Fix:** Dynamic Query — a user directory no longer lets visitors search or sort people by email address, matching WordPress's own rules. People who can manage users still can.
* **Fix:** Dynamic Query filter counts update straight away when a post loses the last category, tag or field value a filter counts. The cached count used to keep including it.
* **Fix:** Form Builder enforces required fields on the server, so a submission that skips the browser's own check is refused instead of being stored with the field empty. Submissions for a form that doesn't exist on the site are refused, and fields that aren't part of the form are ignored rather than stored. Forms on private pages and in previews no longer accept submissions.
* **Improved:** Form Builder — one visitor's IP address can now send at most 20 submissions a minute across all forms on the site, on top of each form's own limit. Adjust it with the `designsetgo_form_global_rate_limit_count` and `designsetgo_form_global_rate_limit_window` filters.
* **Fix:** Tabs — a Tabs block placed inside another one keeps its own panels. The outer block counted the inner block's panels as its own and showed or hid them as you switched tabs.
* **Fix:** Scroll Accordion — an accordion removed from the page, for example when filters replace a Dynamic Query's results, stops listening for scroll and resize. It kept measuring elements that were no longer there on every scroll.
* **Fix:** Map — an address containing quotation marks no longer sends the map to New York. The quotes reached the geocoder as stray `u0022` text, so the lookup failed and the map fell back to its default location.
* **Improved:** Pages that don't use Grid, Row, Icon or Pill no longer carry those blocks' CSS inline in the page head.
* **Fix:** More blocks added by an AI assistant now come out valid. A Section that isn't width-constrained no longer gets an inner max-width, Row and Grid keep their own padding and gap (including zero) instead of reverting to the defaults, Row honours vertical alignment including space-between, Grid honours a minimum column width and matched row heights, and Card keeps its border colour.
* **New:** AI and agent tooling — a `designsetgo/get-design-context` ability returns the theme's resolved settings, global styles and block style variations, so an assistant can build with the site's own palette, fonts and spacing instead of guessing. It is read-only.

= 2.7.3 - 2026-09-04 =

* **Fix:** Blocks added by an AI assistant now come out valid. When a block was built through the plugin's Abilities API rather than typed into the editor, the markup it produced did not always match what the editor writes, so opening the page showed "Attempt Recovery" or silently rewrote content the author never touched. Every block is now checked against its own editor output, including at its plain defaults.
* **Fix:** Section, Row and Grid keep their hover and overlay colours when built this way — the five custom properties behind them were dropped entirely.
* **Fix:** Modal now uses the label you gave it for screen readers instead of always announcing "Modal".
* **Fix:** Form Builder no longer writes email notification settings — recipient, subject, from and reply-to addresses — into the page's public markup.
* **Fix:** Icon Button and Modal Trigger apply padding, colour, typography, border and shadow to the button itself rather than duplicating it on the wrapper.
* **Fix:** Counter Group honours its own column counts, Grid honours its HTML element setting, and Tabs keeps its colour settings.
* **Fix:** Alignment set on a block built this way is now carried into the markup, for blocks that align left/centre/right as well as those offering wide and full.

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

* **New:** Theme animation defaults — set an entrance animation once per block type (e.g. all Buttons fade in) under DesignSetGo → Settings → Features → Animations, or in your theme's `theme.json`. Every block of that type inherits it automatically, and any individual block can override it (Custom) or opt out (Off). One rule can target several block types at once, including `namespace/*` wildcards.
* **Fix:** The per-page Overlay Header is transparent again and no longer pulls page content up by the footer's height. The header height was being measured from the wrong element on themes whose footer also contains a navigation block, which sliced the top off the hero; and the header stayed opaque on themes that paint it from a style variation or `theme.json` rather than a background class. Your first content section now also clears the header while its background still runs behind it.
* **Fix:** Section shape dividers now honour your theme's divider height and width tokens. Previously a theme (or Style Kit) could set the default divider shape but not its size — the Section block's top/bottom dividers ignored both size tokens. Untouched dividers now inherit the theme's size (and reserve matching content clearance), while any size you set explicitly still wins. The Height/Width sliders gain a Reset that returns them to the theme default. Existing content is unchanged.

= 2.5.1 - 2026-07-23 =

* **New:** Section shape dividers — six new layered, tonal divider shapes: Triangle Layered, Triangle Layered Extra, Curvy Triangle Layered, Symmetric Waves Layered, Side Triangle Layered, and Side Triangle Layered Extra. Each paints as a soft two- or three-tone band that inherits your theme's color by default and can be overridden per section.
* **Fix:** Section shape-divider spacing is now author-defined, so patterns that set the content clearance with a theme spacing token no longer show an "Attempt Recovery" prompt. A new "Content Clearance" control sets the gap between your content and the divider; existing dividers migrate silently, and a divider with no clearance set automatically reserves space to match its own height.

= 2.5.0 - 2026-07-21 =

* **New:** Grid — an "Align Rows" option that lines up each row of card content (image, heading, text, button) across columns, so cards with different amounts of text stay aligned with no ragged whitespace. Works with Section, Row, and Group cards, and is off by default so existing grids are unchanged.
* **New:** Form Builder — the submit button now has a Button Style control (Default, Secondary, or Outline), so a form placed on a colored background can use a matching button. Your theme's button style variations apply to it too, and AI-assisted form inserts respect the chosen style.
* **Fix:** Blocks whose on-screen text is changed by a site translation (or other content tools) no longer show an "Attempt Recovery" prompt. Icon Button, Modal Trigger, Accordion, Timeline, Counter, Card, Table of Contents, Form Builder, and Countdown Timer now treat their visible label as the single source of truth, so translating the text keeps the block valid — and existing content migrates silently.
* **Fix:** Forms and responsive grids on AI-generated sites no longer show an "Attempt Recovery" prompt. Their saved markup differed slightly from what the current blocks produce; the affected forms and grids now migrate silently and keep their design.
* **Fix:** Modal — the overlay (backdrop) color now inherits from your theme and can be restyled by a Style Kit, instead of always being baked to black. Modals saved from patterns no longer show an "Attempt Recovery" prompt.
* **Fix:** Form Builder — an inline (side-by-side) submit button now lines up level with the field beside it, in both the editor and on the frontend, and the loading spinner now shows correctly on styled submit buttons.
* **Fix:** Cloudflare Turnstile now accepts its tokens. Previously, turning Turnstile on silently broke the form — every protected submission was rejected before it reached the handler.
* **Fix:** Excluding a third-party block from DesignSetGo's controls now takes effect in the editor. Excluded blocks (such as Gravity Forms) no longer receive DesignSetGo panels or show an "invalid block attributes" error.
* **Fix:** Query Monitor no longer causes a site error on every page load when its debugging panel is active.
* **Fix:** Draft Mode — publishing a draft no longer deletes custom fields that were intentionally kept out of the draft copy, so integrations that store their own bookkeeping data keep it across publishes.
* **Security:** Hardened Draft Mode's post-copy against maliciously deep data and closed a window where a stale, still-open settings form could overwrite a saved API key with its masking placeholder. These strengthen existing protections — no known exploit was involved.

= Earlier releases (2.4.0 and earlier) =

For the full version history, see [CHANGELOG.md](https://github.com/jnealey-godaddy/designsetgo/blob/main/CHANGELOG.md) in the GitHub repository. Highlights:

* **2.4.0** — Section Divider block, Fill / Outline icons, theme-driven defaults for icons, forms, maps and SVG patterns, a new Justify control for Pill, Icon, Icon Button and Modal Trigger, safer form notification emails, and many editor and frontend fixes.
* **2.3.0** — Theme section styles for Section, Row and Grid, site-wide default shape dividers, and redesigned shape dividers.
* **2.2.0** — Column Min Width for Grid and Icon List, Scrolling Gallery image fit and size controls, silent upgrades for older block markup, and PHP 7.4 support.
* **2.1.x** — Dynamic Query block family, Dynamic Tags, Block Bindings, Meta Box/Pods/JetEngine sources, Conditional Visibility, per-URL Markdown, Hover Effects, editor UX refresh, and security hardening.
* **2.0.x** — Comparison Table, Timeline, Advanced Heading, Shape Dividers, Draft Preview Mode, 150+ patterns, WooCommerce blocks, form improvements, and major bug fixes.
* **1.4.x** — llms.txt for AI language models, Draft Mode, Visual Revision Comparison, PHP 8.0+.
* **1.3.x** — Abilities API (50 AI abilities), scroll-driven extensions, Text Style, Cloudflare Turnstile.
* **1.0–1.2** — Initial public release: 43 blocks + 11 extensions, Map and Card blocks, REST API hardening, 9 translations.

== Upgrade Notice ==

= 2.7.5 =
Fixes WordPress updates timing out in wp-admin on hosts where DesignSetGo couldn't create its Dynamic Query database table. Also fixes shape dividers and background effects on blocks added by an AI assistant.

= 2.7.4 =
Dynamic Query filters, sort and Load more now work for logged-out visitors, and Load more keeps the active filters. Closes a hole that let any logged-in account list non-public content. Form Builder now enforces required fields and a per-visitor submission limit on the server.

= 2.7.3 =
Fixes blocks created by an AI assistant through the Abilities API, which could come out invalid. Also restores hover and overlay colours on Section, Row and Grid, uses the Modal's own screen-reader label, and keeps Form Builder email settings out of public markup.

= 2.7.2 =
Fixes four display problems: the Advanced Heading typing effect cutting off the last letter, the clip-based effects shaving the tops and tails off letters, Text Path motion not previewing in the editor, and heading segments running together in the editor. Includes everything in 2.7.1.

= 2.7.1 =
Fixes the Chart block, which could not be inserted on 2.7.0 — it showed "Error loading block" instead of a chart. Also keeps long y-axis labels inside the chart, and adds a value prefix/suffix and thousands grouping. Recommended for anyone on 2.7.0.

= 2.7.0 =
Four new blocks (Chart, Star Rating, Hotspot, Text Path), an Interactions extension, Schema.org markup, animated headlines, an off-canvas Modal mode, WooCommerce product bindings and queries, a keyless Google Maps option, and many Slider fixes. No content migration needed.

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
