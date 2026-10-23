=== DesignSetGo ===
Contributors: justinnealey, ziontrooper, vnovakovic
Tags: blocks, gutenberg, form-builder, query-loop, animations
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.11.1
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

1. A hero section with a morphing image blob, a two-tone heading and animated counters
2. Hotspot block: pins on a photo that open product details on click
3. Image Accordion block: photo panels that expand on hover
4. Dynamic Query block: a filterable post grid with topic buttons, search and live results
5. Charts, counters and progress bars in a report layout
6. Flip cards for a team section, with one card turned to show its back
7. Circular text on a path above image rows that slide as you scroll
8. Comparison Table block with a highlighted "Most popular" plan
9. Timeline block with photo markers on alternating sides
10. Form Builder block: a booking form with side-by-side fields next to a photo card
11. Editing chart data right in the block settings sidebar

== Changelog ==

= 2.11.1 - 2026-10-23 =

* **Security:** Section, Row and Grid only use the HTML elements their "HTML Element" setting offers, including when converted to core blocks.
* **Security:** Custom CSS checks read escaped characters the same way browsers do.
* **Fix:** Keyboard users can use modals again: focus moves into the modal, and Tab no longer closes it.
* **Fix:** Opening a page in the editor no longer removes padding from Sections inside other Sections.
* **Fix:** Icon List colour, background, size and icon position now show on your site, not only in the editor. Existing lists update without re-saving.
* **Fix:** Hide on desktop, tablet or mobile and Max Width now work on blocks WordPress builds on the server, such as Latest Posts and Search.
* **Fix:** Dynamic Query: Load more no longer repeats posts after a filter change. "Exclude current post", archive results and shop price and rating filters stay applied when results update. Offsets no longer leave empty pages, category counts include subcategories, and focus stays on the filter you used.
* **Fix:** A malformed filter link no longer shows an error page in place of your page.
* **Fix:** Forms: US and international phone numbers pass validation (international numbers use dashes). A blank number field is no longer saved as 0, and {page_url} in emails is the form's page. "Too many submissions" shows on the form instead of reloading it, and the security check resets after a failed submission.
* **Fix:** Closing and quickly reopening an Accordion item no longer leaves it empty. A Counter ending at 0 shows 0, and its prefix and suffix show when reduced motion is on.
* **Fix:** A duplicated accordion item, tab, hotspot or counter gets its own ID, so screen readers announce the right panel.
* **Fix:** Breadcrumbs show a visible focus outline, hide draft and private parent pages, point the right way on right-to-left sites, and appear on archive, search and 404 pages.
* **Fix:** Six logo and trust-bar patterns, large Blobs and bordered accordions no longer make pages scroll sideways on phones. Logo rows already on your pages wrap on phones and small tablets without re-saving.
* **Fix:** Convert & Deactivate converts every page and keeps quotes and special characters intact.
* **Fix:** Privacy exports and erasures include all of a person's form submissions, not only recent ones.
* **Fix:** llms.txt is no longer rewritten on every save, updates when its settings change, and pages no longer answer some apps and bots with "Not Acceptable".
* **Fix:** Settings saved in part (for example through the API) keep their other defaults.
* **Fix:** Uninstalling DesignSetGo removes all of its data, on every site of a multisite network.
* **Fix:** The Dynamic Image block's editor styling loads again.
* **Fix:** Draft Mode: creating a draft no longer breaks blocks (no more "Attempt Block Recovery" in the draft). A draft copy can't be published as a second live page by Quick Edit or scheduling. Publishing warns you if the live page changed since the draft was made, and carries over template, slug, parent, order and category changes.
* **Fix:** Sliders work on right-to-left sites, autoplaying sliders have a pause button, and scroll-driven sliders become normal sliders when reduced motion is on.
* **Fix:** Pages with a form or slider no longer scroll sideways on right-to-left sites.
* **Fix:** Sticky header settings chosen for an individual header (shadow, shrink, hide on scroll, background) now apply on your site, and a header hidden while scrolling reappears when you jump back to the top.
* **Fix:** Blocks animated "On Hover" or "On Click" are visible before you interact with them, and animated blocks show when printing.
* **Fix:** Timeline marker shapes and colours now show on your site. Existing timelines update without re-saving. Opening a page with an older Timeline no longer marks it as having unsaved changes.
* **Fix:** Countdown Timer adds hidden units to the next one shown, so hiding Days shows 77 hours instead of 5.
* **Fix:** Table of Contents lists every heading, including ones that skip a level, supports non-Latin headings, and no longer jumps to the top of the page when it has nothing to list.
* **Fix:** Card: turning off the button area no longer deletes its buttons, and badges are read as their text. Modal Trigger padding from the spacing presets now applies. Existing pages update without re-saving.
* **Fix:** A Group or Section with a click interaction no longer blocks typing a space in fields inside it.
* **Fix:** Forms: with the country code dropdown shown, phone numbers are saved without an extra "+". Success messages are read once by screen readers, and visitors whose clock is off can still submit.
* **Fix:** Dynamic Query: random order no longer repeats posts when you load more, and a sort option no longer hides posts missing the sorted field. Posts published at the same moment, or sharing a title when sorted by title, are no longer repeated or skipped across pages. Filter counts stay available while the index rebuilds and update when a category is deleted.
* **Fix:** Image Accordion panels read their heading and caption to screen readers, and hover works on touch-screen laptops. Scroll Marquee no longer stops the page scrolling. A map whose address can't be found shows a message instead of a pin in New York.
* **Fix:** Moving Text Path text has a pause button and stops while off screen. Progress bars and Reveal on Hover respect reduced motion and keyboard focus. Sticky Sections stack correctly after rotating a phone and with more than 20 sections.
* **Fix:** Comparison Table tooltips open on tap and are no longer cut off when the table scrolls, Text Reveal keeps links readable by screen readers, and icons without a label are no longer announced by their name.
* **Fix:** The settings screen shows an error with a Retry button if settings fail to load, instead of a form that can't save.
* **Fix:** AI assistants connected through DesignSetGo change the block you asked for, instead of sometimes changing a different block on the page.
* **Improved:** Structured data from a Star Rating block set to Aggregate rating now only appears when the rating count is shown, and the count can go above 1,000.
* **Improved:** Custom CSS no longer allows `@import`. It only ever worked in the first block on a page; load fonts and stylesheets through your theme instead.
* **Security:** The form endpoint limits how often one visitor can submit, including to forms that don't exist, and parallel submissions can no longer get around a form's limit.

= 2.11.0 - 2026-10-14 =

* **Security:** Form webhooks to a link-local or carrier-grade NAT address (169.254.x.x, or 100.64.x.x to 100.127.x.x) are blocked, and the way to allow one depends on your WordPress version. Current WordPress releases (7.1, and 6.7.6, 6.8.7, 6.9.6 and 7.0.3 or later) block these addresses themselves, and WordPress's `http_request_host_is_external` filter allows them. On older versions DesignSetGo blocks them instead, and only the `designsetgo_form_webhook_allow_reserved_host` filter allows them.
* **Fix:** When a page refreshes in place while you edit it, blocks that weren't on the page before load their right-to-left styles on RTL sites. Their scripts also still load when a caching or optimization plugin rewrites the page's script tags.

= 2.10.0 - 2026-10-13 =

* **New:** Multi-step forms. Use "Split into steps" in a form's Settings (or the Multi-step template) to show a long form one step at a time, with Next / Back and a progress indicator. Next matches your Submit button, Submit takes its place on the last step, and you can set a Progress colour and style each step title. Once the form is sent, only your success message shows. Without JavaScript the form shows as one page.
* **New:** Form fields can show or hide based on other answers ("Show Company name when Customer type is Business"). Set it under a field's Settings → Conditional logic. Hidden fields are skipped when the form is submitted, even if they're required, and steps whose fields are all hidden are skipped too.
* **New:** Send each form submission to a webhook (Zapier, Make, a CRM…). Set the URL in the form's Settings panel and a signing secret under DesignSetGo → Settings → Integrations. Failed deliveries retry automatically, each submission shows its delivery status with a Resend option, and the URL field warns you when an address can't receive submissions.
* **New:** Export form submissions to CSV from the Form Submissions screen, filtered by form and date range. Columns use your field labels (submissions received before this update use field names), and the form filter names the page each form is on.
* **New:** Layout overrides for Section, Row and Grid, and for the blocks directly inside them. Under the block's Settings, set its size, spacing, gap, position and layering separately for desktop, tablet and mobile.
* **New:** Grid has Tablet and Mobile Column Templates, so tablets and phones can have uneven column widths too, such as a narrow column beside a wide one.
* **New:** Custom CSS has Desktop, Tablet and Mobile tabs. Tablet CSS applies at 1024px wide and below, and Mobile CSS at 767px and below.
* **Improved:** Grids set to two or more columns on mobile now keep the column spans you set, instead of forcing every item to one column. Check those Grids on a phone after updating. Single-column mobile Grids still stack as before, and a Grid with Align Rows on still gives each card one column so their rows line up.
* **Improved:** The submission details screen shows each field's label alongside its field name, and a ticked checkbox reads "Yes" there and in the CSV export instead of "1".
* **Improved:** Bundled patterns use fictional company and person names instead of real brands.
* **Security:** Your Turnstile secret key and form webhook signing secret are no longer shown to AI assistants connected to your site through DesignSetGo's settings tools. They stay hidden, as they already were on the settings screen.
* **Security:** Form email logs no longer record recipient addresses or subjects, and Map error logs no longer record the address you entered. Both can contain personal details.
* **Fix:** Forms now send when a visitor's browser has JavaScript turned off or blocked, and show your success or error message afterwards. Before, the answers were lost. If a submission fails, the visitor's answers stay filled in and the message says what to fix, such as a required field left empty.
* **Fix:** Typing into a block's empty Custom CSS box no longer jumps out of the box after the first letter. Before, only that letter was kept as CSS and the rest of what you typed went into the block's own text.
* **Fix:** Adding or removing a background video in the editor no longer closes the block's settings and moves you out of the panel you were working in.
* **Fix:** Screen readers no longer announce the decorative drawing in Icon, Icon List and Icon Button blocks on top of their label.
* **Fix:** Hotspot markers in the editor now sit exactly where they appear on your site. Before, every marker after the first was shown lower than its real position.
* **Fix:** An Advanced Heading's font size, line height and letter spacing now apply to the heading, instead of being overridden by your theme's heading styles.
* **Fix:** Form message boxes, date pickers and dropdowns use your theme's font instead of the browser's default.
* **Fix:** Outline buttons in the bundled patterns show their label instead of a solid dark box. This also fixes buttons already on your pages.
* **Fix:** Countdown patterns start 30 days out when inserted, instead of showing "The countdown has ended!".

= 2.9.0 - 2026-09-29 =

* **Security:** Public Markdown, excerpts and generated exports respect visibility for logged-out visitors. Old plugin-owned caches and static exports are invalidated on update. If cleanup cannot write to the filesystem, resolve the administrator notice before regenerating exports.
* **Security:** Dynamic Query refresh templates stay on the server behind signed, viewer-bound opaque references; hidden block content no longer appears in page-source data.
* **Security:** Turnstile-enabled forms accept submissions only after successful Cloudflare verification. Missing keys and verification outages stop submissions; forms without Turnstile are unchanged.
* **Performance:** Load only the icons and extension assets a page uses. Blocks, extensions and icons added by Query load-more or an authorized live refresh initialize without a full page reload.
* **Performance:** Responsive images and image dimensions reduce unnecessary downloads and layout shift. Countdown updates pause in background tabs; Slider drag listeners exist only while dragging.
* **Improved:** More native block transforms preserve content and settings. Section and Row orientation changes no longer replace the block automatically. New Maps start with a location search.
* **Improved:** Clearer block inspectors, a Scroll Accordion Add Item toolbar control, theme-driven icon and marker sizes, and refreshed translations in all nine bundled locales.
* **Fix:** Browser-valid custom form patterns no longer receive a conflicting PHP interpretation. Supported shared patterns remain enforced on the server; unsupported custom syntax stays browser-only, alongside native field limits.
* **Fix:** Dynamic icon requests are split into batches of at most 100 names. Incomplete and failed answers remain retryable, so large updates retain every icon.
* **Fix:** Existing Modals retain their historical white surface and dark default close icon when unset. Editor and frontend use the same explicit background token; authored colors take precedence.
* **Fix:** Multiple Dynamic Query blocks keep their controls scoped independently, and Timeline marker sizes apply on the published page.
* **Fix:** Countdown deadlines honor the selected or WordPress site timezone consistently for all visitors.
* **Packaging:** Include all seven runtime pattern images; exclude development tooling and duplicate marketing assets.
* **Before updating:** Check countdown deadlines/site timezone and Turnstile keys. Saving/importing blocks without the `edit_css` capability can remove their per-block custom CSS. Unset visual values can follow theme defaults.
* **After updating:** Purge page/CDN and Markdown caches. Open WordPress/admin, confirm export cleanup completed, and regenerate static Markdown/llms files if needed. Old cached Query pages need fresh references; keep page-cache lifetimes below 30 days and purge affected pages if Query source transients are cleared.

= 2.8.2 - 2026-09-23 =

* **Fix:** DesignSetGo blocks no longer show as unsupported in the editor after an update. If you had ever switched a block off under DesignSetGo → Blocks & Extensions, blocks added in later versions arrived switched off too, and pages already using one showed "Your site doesn't include support for this block". New blocks and extensions now arrive switched on. Anything that was already off stays off, so if a page still reports a DesignSetGo block as unsupported, turn it back on under DesignSetGo → Blocks & Extensions. Your content is safe either way.
* **Fix:** Switching an extension off under DesignSetGo → Blocks & Extensions now takes effect. Before, only Dynamic Tags respected its switch. A switched-off extension's controls no longer appear in the editor, and switching off Draft Mode turns it off entirely. Content already using an extension keeps working exactly as it was saved. If you switched extensions off in the past, their controls will now disappear from the editor.
* **Fix:** Switching off a block or extension, or removing a post type from llms.txt, sometimes didn't stick after saving. These settings now save exactly as you set them.

= 2.8.1 - 2026-09-21 =

* **Fix:** On a page with an overlay header, the header sits over the top of the hero again, instead of leaving a band of empty space above it. 2.8.0 started adding space above the first section on these pages, which pushed the hero image down and left a strip of plain page background showing under the header.
* **Fix:** A hero's own top spacing now shows from the first moment the page appears. 2.8.0 described this fix, but it wasn't taking effect.

= 2.8.0 - 2026-09-18 =

* **Fix:** Timeline items fade in as you scroll on the published page again. They were appearing all at once, already visible, because a stylesheet meant only for the editor was also being sent to visitors. The same problem put a dashed outline around Image Accordion and a grey border on Scrolling Gallery images. 33 blocks were sending their editor styling to visitors; none are now.
* **Fix:** A page with an overlay header no longer hides the top of the hero behind the header while the page loads. The hero's top spacing is now right from the first moment it appears, instead of being corrected a split second later.
* **Fix:** Form Builder - when two published pages hold forms that share an ID, a submission is now matched to the form that was actually filled in. Forms end up sharing an ID when you insert the same pattern twice or duplicate a form block. Before this fix the notification email could go to the other form's recipient, or a submission could be refused for a required field that wasn't on the form the visitor saw.
* **Fix:** Form Builder - fields laid out in three or four columns fit on one row, and stack properly on phones instead of overflowing.
* **New:** Section - **Content Position** puts the width-constrained content column on the left, center or right of the section, instead of always centering it.
* **New:** Section - **Box Width** caps the width of the section's own box, so a section used as a card or panel narrows its background, border, shadow and shape dividers along with it. Until now only the text inside could be narrowed.
* **New:** Grid - a **Column Template** setting gives uneven columns on desktop, such as a narrow sidebar beside a wide main column. Tablet and mobile keep their own column counts.
* **Fix:** Section - the faint horizontal line that could show across a shape divider's inner edge at certain browser zoom levels is gone.
* **Fix:** Tabs - tab labels take their color from the text around them instead of a theme preset, so they stay readable on a dark or colored background.
* **Improved:** New overlays on Section, Row, Grid and Scroll Accordion items start lighter, and an overlay color that already includes transparency now sets its own strength instead of having more applied on top. Overlays already on your pages keep their current strength, even after you edit them.
* **Fix:** The contact form's "Select an option" placeholder and other bundled text now appear translated on non-English sites. An older installed language pack could hide newer translations included with DesignSetGo.
* **Fix:** More blocks added by an AI assistant come out valid the first time you open them, so you no longer see "Attempt Recovery". This covers images, lists, quotes, headings, captions, icon buttons, modals, cards, counters, form colors and grid ordering.
* **New:** AI and agent tooling - a `designsetgo/add-blocks` ability adds several top-level blocks in one step.

= Earlier releases (2.7.5 and earlier) =

For the full version history, see [CHANGELOG.md](https://github.com/jnealey-godaddy/designsetgo/blob/main/CHANGELOG.md) in the GitHub repository. Highlights:

* **2.7.x** — Chart, Star Rating, Hotspot and Text Path blocks, Animated Headline modes for Advanced Heading, the Interactions extension, Schema.org markup for Accordion and Star Rating, an off-canvas panel for Modal, deeper animations, WooCommerce product queries, a keyless Google Maps option, Loop Carousel layouts for Dynamic Query, and WordPress 7.1 support.
* **2.6.x** — Theme animation defaults (set an entrance animation once per block type), sticky and overlay headers that follow your theme's colors and survive AJAX page updates, size tokens for Section shape dividers, Grid columns that wrap instead of overflowing, and forms that work from cached pages and from footers, template parts and widgets.
* **2.5.1** — Six layered Section shape dividers and a Content Clearance control.
* **2.5.0** — Grid "Align Rows" option, a Button Style control for the Form Builder submit button, a Modal overlay color that follows your theme, working Cloudflare Turnstile tokens, and fixes for "Attempt Recovery" prompts on translated and AI-generated sites.
* **2.4.0** — Section Divider block, Fill / Outline icons, theme-driven defaults for icons, forms, maps and SVG patterns, a new Justify control for Pill, Icon, Icon Button and Modal Trigger, safer form notification emails, and many editor and frontend fixes.
* **2.3.0** — Theme section styles for Section, Row and Grid, site-wide default shape dividers, and redesigned shape dividers.
* **2.2.0** — Column Min Width for Grid and Icon List, Scrolling Gallery image fit and size controls, silent upgrades for older block markup, and PHP 7.4 support.
* **2.1.x** — Dynamic Query block family, Dynamic Tags, Block Bindings, Meta Box/Pods/JetEngine sources, Conditional Visibility, per-URL Markdown, Hover Effects, editor UX refresh, and security hardening.
* **2.0.x** — Comparison Table, Timeline, Advanced Heading, Shape Dividers, Draft Preview Mode, 150+ patterns, WooCommerce blocks, form improvements, and major bug fixes.
* **1.4.x** — llms.txt for AI language models, Draft Mode, Visual Revision Comparison, PHP 8.0+.
* **1.3.x** — Abilities API (50 AI abilities), scroll-driven extensions, Text Style, Cloudflare Turnstile.
* **1.0–1.2** — Initial public release: 43 blocks + 11 extensions, Map and Card blocks, REST API hardening, 9 translations.

== Upgrade Notice ==

= 2.11.1 =
Security fixes and a large batch of bug fixes for forms, Dynamic Query, Draft Mode, sliders, accessibility and right-to-left sites. Custom CSS no longer allows @import; load fonts and stylesheets through your theme instead.

= 2.11.0 =
Fixes for form webhooks and in-place page refreshes. Webhooks to link-local or carrier-grade NAT addresses are blocked; see the changelog for the filter that allows one on your WordPress version.

= 2.10.0 =
Adds multi-step and conditional forms, form webhooks and CSV export, and per-device layout controls. Grids with two or more mobile columns now keep their column spans, so check those on a phone after updating.

= 2.9.0 =
Security, performance and compatibility update. Purge page/CDN and Markdown caches; check export cleanup, countdown timezones and Turnstile keys. Editing without edit_css can remove per-block custom CSS. Regenerate static exports after cleanup.

= 2.8.2 =
Fixes DesignSetGo blocks showing as unsupported in the editor after an update, and makes the extension switches on the Blocks & Extensions screen take effect.

= 2.8.1 =
Fixes overlay headers on 2.8.0 leaving a band of empty space above the hero instead of sitting over it.

= 2.8.0 =
Fixes Timeline/frontend styling and overlay headers covering heroes. Form emails reach the correct recipient when forms share an ID. Adds Section Content Position, Box Width and uneven Grid columns.

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
