# Changelog

All notable changes to the DesignSetGo plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### New Features
- **Star Rating block**: show a rating as a row of stars, from a fixed value or from a dynamic source. The block is server-rendered specifically so `rating` and `ratingCount` can be connected through WordPress's own Block Bindings — post meta, ACF, or the `designsetgo/woo-average-rating` source that ships with the WooCommerce surface — including per item inside a Query loop, where each card reads its own rating. A static block could only ever show the number an author typed once.

  The partial star is a CSS clip rather than a second set of icons: two identical rows sit on top of each other and the upper one is clipped to a percentage, so 4, 4.5 and 4.3 out of 5 all render from one markup shape with no half-star asset and no frontend JavaScript. Precision (whole / half / exact) snaps only the drawn icons — the number printed beside them, and the number handed to structured data, stay exact, because rounding 4.4 up to 4.5 for the icons is a display convention while publishing 4.5 to a search engine is a claim.

  Values are clamped on the way in, because a bound source can return anything: the scale caps at ten icons so one bad meta value cannot emit thousands of SVGs, the rating is held inside the scale, and non-numeric input reads as no rating rather than `NaN`. Numeric *strings* are accepted deliberately — WooCommerce returns `'4.00'`. The rating-count format is author-supplied text applied with `str_replace()`, never `sprintf()`, so a template reading "%s of 100%" cannot throw a `ValueError` and take the page down.

  Assistive tech gets one sentence — "Rated 4.5 out of 5, based on 128 ratings" — with every visual part hidden, instead of the disconnected fragments a naive markup order produces. Colour, border and padding are routed to the inner element the same way Icon and Pill route theirs, so a background hugs the stars instead of painting across the content column.

- **Star Rating emits structured data.** The schema extension gains its second block, offering an `AggregateRating` or a `Review` node in the page's JSON-LD graph. Each refuses to emit a node it knows is useless: an aggregate without a rating count is ineligible for rich results while still asserting a rating, and a review with no named author is dropped, so both produce nothing rather than noise. The item rated is a `Thing` named by the block or by the page title — never a `Product`, because the block cannot know what the page is about and relabelling a post to chase a rich result is the structured-data spam this extension's opt-in default exists to prevent.

  A *bound* rating deliberately emits nothing. The collector reads stored post content and `parse_blocks()` does not resolve bindings, so the only number available there is the placeholder the author last typed — publishing that would assert a rating nobody meant. On a WooCommerce product page that is also the correct outcome twice over: Woo already emits a Product node carrying its own `aggregateRating`, and a second one would be duplicate markup.

- **Reviews pattern**: `Reviews with Star Rating` builds review cards on the new block instead of five separate Icon blocks, which could only ever show whole stars and told a screen reader nothing.
- **Loop Carousel: a Dynamic Query can present its results as a carousel, and pagination now knows what that means.** `designsetgo/slider` has been a registered query item host since v2.6 — drop one inside a Dynamic Query and the first slide becomes the per-item template — but every pagination kind past `numbered` was inert inside it, and so was every filter. The lookup that finds a query's item container matched only `designsetgo/query-results`, so a Load more click found nothing to append to and returned early with no error; even past that, new items were collected by the `.dsgo-query__item` class, which only the grid host's `<li>` wrapper carries, so a carousel appended nothing while still advancing its page counter and skipping a page per click. Both carousel hosts (`slider`, `scroll-slides`) now tag their own item container — the track and the panels wrapper respectively — with the same `data-dsgo-query-results-role="container"` pair the grid uses, and appended items are read from that container's children rather than by class, which also stops grouping's `<section class="dsgo-query-group">` wrappers being flattened on append. That last part fixes Load more for grouped grids too, not just carousels.

  **Infinite scroll and carousel presentation are mutually exclusive, and the presentation wins.** Infinite scroll hangs on a sentinel placed after the items: the reader scrolls the document past the list, the sentinel enters the viewport, the next page loads. That signal exists only because the items grow the page vertically. A carousel keeps its items in a fixed-height viewport, so the sentinel's position says nothing about how far through the results anyone has got — it either sits on screen from first paint and pulls page after page until the query is exhausted, or it is below the fold and never reached. No setting reconciles the two. `designsetgo/query-pagination` therefore degrades `infinite` to a Load more button when the host is a carousel, rather than dropping the control and stranding the reader on page one: it is the same Interactivity API action minus the auto-firing sentinel, which is already what infinite scroll falls back to under `prefers-reduced-motion`. The author's `buttonLabelWhenPaused` carries over as the button label, since a block that was never a Load more block has no `labelLoadMore` set. The decision lives in `designsetgo_query_host_supports_infinite_scroll( $host_name )` — true for `designsetgo/query-results` and for a query with no host block at all, false otherwise — and is filterable, so a third-party host registered through `designsetgo_query_item_host_block_names` that *does* grow the page vertically can opt back in. Third parties default to unsupported because the cost of guessing wrong in that direction is a runaway load loop.

  In the editor the pagination block says so up front instead of leaving a control that silently does nothing: a warning naming the layout block, a one-click **Switch to Load more**, a canvas preview of the button that will actually render, and the two sentinel-only settings hidden while the button label stays. The stored attribute is left exactly as the author set it — rewriting it behind their back would make the block disagree with what they chose and would silently rewrite every existing post on first edit.

- **Chart block**: display data as a bar, line, or donut chart. Rows are typed in the inspector or read from a post meta field holding a JSON array of `{label, value}` objects, and each row takes its own colour from the theme palette. The chart is drawn as inline SVG on the server — no charting library, and no JavaScript on the frontend at all.

  Accessibility is built into the output rather than bolted on. The SVG is `aria-hidden` and paired with a visually-hidden data table carrying the same numbers, and colour is never the only channel that identifies a category: bar and line charts name their categories on the axis, donuts name them in a legend that cannot be switched off because a donut has no axis to carry them. Dense axes thin their labels to stay readable, and the otherwise-optional legend returns automatically whenever they do, so every category is always named somewhere a sighted reader can find it.

  The geometry is built to avoid misreporting the data. Bars are drawn from zero rather than from the axis minimum — with a baseline when the axis crosses zero — so a negative value reads as a downward bar instead of a short upward one. Donut rows of zero or less are left out of the chart, the legend, and the data table alike, since a slice is a share of a total and a negative has no share. A chart draws at most 200 rows (filterable via `designsetgo_chart_max_rows`), which is already past the point where a 600-unit-wide plot can render a distinguishable bar; when the cap bites, the data table reports how many rows were left out rather than passing a truncated chart off as a complete one.

  Meta-bound charts read post meta through the same `post_password_required()` / `is_post_publicly_viewable()` / `read_post` gates as `StyleBinding::resolve()` and the block bindings adapter, so a chart inside a Query Loop cannot surface data those paths would withhold. Palette colours are validated against a character allowlist — everything a CSS colour needs (`var()` with fallbacks, `rgb()`, `hsl()`, `color-mix()`, hex, named) and nothing an injection does, so a colour cannot append declarations of its own to the legend swatch's `style` attribute.

### Improved
- **Blocks inside a Dynamic Query come back to life after a filter or a Load more.** A filter or sort action replaces the whole query region's `innerHTML` and then dispatched nothing, so any block in that region with a frontend runtime — a carousel host, but equally counters, flip cards, maps, comparison tables — was replaced with inert markup and stayed dead until a full page reload. The refresh now dispatches `dsgo-content-loaded` on `document` with `detail: { source: 'query-refresh', container: region }`; that is the plugin's existing back/forward-cache re-init signal, which two dozen blocks and extensions already listen for, so they all pick up query refreshes without a line of new code each. A Load more append is the mirror-image case — the host element survives and only its children change, so an element-keyed init guard would skip it — and dispatches `dsgo-query-items-appended` on the item container instead; the slider listens and rebuilds its clones, dots and cached dimensions while keeping the reader on the same slide. The teardown half matters as much: the slider now tracks its live instances and destroys any whose element has left the document on each init pass, where previously every filter change leaked a set of document-level `mousemove` / `mouseup` / `resize` / `scroll` listeners and an IntersectionObserver.

### Compatibility
- **WordPress 7.1: responsive styles now land on the same element as the value they override.** 7.1 lets an author set per-viewport values on any block instance (`style['@mobile']`, `style['@tablet']`). Core renders those as a separate stylesheet keyed to a generated `wp-states-*` class that it puts on the block's *outermost* tag, and it resolves the rest of each rule's selector from the block type's `selectors` in `block.json` — falling back to that outer tag when a block declares none.

  Five blocks relocate visual supports: they skip serialization for a support and paint it onto an inner element instead, because the block's root is a positioning wrapper that spans the whole content column and would smear a background or border across it. Those blocks either declared no `selectors` at all (Pill, Scroll Marquee) or declared a single `root` covering every support (Icon, Icon Button, Modal Trigger), so a mobile-only value went to whichever element the base value did *not* use. Pill and Scroll Marquee sent it to the wrapper while the base value sat on the inner element — a mobile background painting across the full column. Icon, Icon Button and Modal Trigger had the mirror-image problem for margin, which is the one spacing value those blocks still serialize on the wrapper: the base margin stayed on the wrapper and the mobile margin went to the inner element, so at mobile widths both applied and the spacing compounded.

  Each of the five now declares `selectors` that name, per support, the element that actually paints it — including the `spacing.root` / `spacing.padding` and `spacing.root` / `spacing.margin` splits needed where padding and margin land on different elements. Verified against WordPress 7.1's own `wp_get_state_style_groups()` and `wp_build_state_selector()`. This also corrects where `theme.json` styles for these blocks apply, which followed the same selectors and had the same mismatch.

- **WordPress 7.1: plugin abilities stay reachable from AI clients.** 7.1 added `meta.public`, a single flag saying an ability is meant for external clients, which MCP adapters and AI agents read instead of each channel carrying its own switch. `show_in_rest` still decides REST on its own when both are set, so the abilities kept working over REST — but core defaults `public` to `false`, which would have hidden every DesignSetGo ability from exactly the agent clients they exist to serve. Abilities now default `public` to their resolved `show_in_rest`, and an ability can still opt out by declaring `public` itself. Both flags are cast to booleans on the way through: 7.1 throws `InvalidArgumentException` on a non-boolean where 6.9 and 7.0 accepted anything truthy, so one stray value in one ability's config would have taken down registration for all of them. The resolution moved out of `Abstract_Ability::register()` into a testable `Abstract_Ability::normalize_config()`.

### Fixed
- **Keyboard focus was invisible on the Slider, Tabs and Accordion.** All three shipped `outline: none` on `:focus-visible` with nothing in its place — the slider's arrows and dots, every tab plus the mobile dropdown, and the accordion trigger. A keyboard user driving any of them got no indication at all of where they were, on every site running the plugin: a WCAG 2.4.7 (AA) failure, and in the Tabs case on the control whose *primary* interaction is keyboard. Two of the three also killed the indicator on plain `:focus`, which pointer users never see anyway.

  All of them now share a `focus-ring` mixin. It draws two rings rather than one — a white outline inside a dark halo — because these controls sit on backgrounds the plugin does not choose: slider arrows and dots float over author-supplied photography, tabs over whatever the theme paints, and no single colour is guaranteed to contrast with both. Under `forced-colors` the author colours are dropped for the system highlight, which is what that mode expects. Suppressing the ring for pointer users is still deliberate and still happens; `:focus-visible` simply follows it at equal specificity and wins for keyboard.

  `tests/unit/focus-visible-indicator.test.js` reads every stylesheet under `src/` and fails on any `:focus-visible` rule that removes the indicator without providing another, so this cannot come back quietly. A bare `:focus` reset stays allowed, because that is the pointer-only case and is correct.

- **Slider dots were too small to hit reliably.** A 12px dot is under WCAG 2.2 SC 2.5.8's 24px minimum, and the spacing exception did not rescue it either — at an 8px gap, the 24px circles the exception measures overlap. The dots keep their 12px look; an invisible pseudo-element grows only what the pointer and touch actually hit, to the full 24px, and the gap moves to 12px so those targets tile exactly rather than overlapping (12px dot + 12px gap = 24px centres). Overlapping targets would have been the worse bug of the two: a tap between two dots lands on whichever painted last.

- **Slider arrows are drawn rather than typed.** They were the characters `‹` and `›` — single-angle quotation marks, not icons — so their weight, size and optical centring came from whatever font the theme happened to load, and the same slider looked different on every site. They are now an SVG chevron, defined once and consumed by both the frontend builder and the editor's inert placeholder so the two cannot drift.

- **One Load more click fetched the next page twice and appended it twice.** A query interaction is wired up two ways: the Interactivity API store action bound to the control, and a document-level delegated listener that keeps working after a filter refresh has replaced (and so de-hydrated) the region's markup. Only one may do the work, and the guard deciding that was a `WeakSet` of already-handled events — which could never match, because the Interactivity API hands a store action a *Proxy* around the native event, not the event itself. So both paths ran, both fetched the same page before either advanced the page counter, and both appended the result: eight posts arrived as twelve items, with four of them duplicated. Visible in a grid, and worse in a carousel, where the duplicates also inflated the clone and dot counts. The claim is now keyed on the event's target, which is the identical DOM node either side of the proxy, and released on the next task. Blurring a search input to click its own Submit button no longer counts as a second interaction either — the delegated `change` handler stands down for any control inside a form that submits.

- **A carousel inside a nested Dynamic Query previewed the wrong query's posts.** A layout host in query mode reads the enclosing query's `postType`, `perPage` and ordering so the editor previews what the front end will render, but it walked the ancestor chain in the order `getBlockParents()` returns it — root-first — and stopped at the first query it met, which is the *outermost* one. Nest a query inside another query's per-item template and the inner slider previewed the outer query's results, while the server, whose registry is keyed per `queryId`, correctly rendered the inner query's. The lookup now walks nearest-first, matching both the hook's own documented contract and the server's scoping.

- **The slider's responsive slides-per-view was parsed and then thrown away.** `slidesPerViewTablet` and `slidesPerViewMobile` were read off the data attributes into the config object and never referenced again — every JavaScript derivation used the desktop number. On a phone a `3 / 2 / 1` carousel cloned three slides to loop a one-up view and treated three slides as visible for screen-reader purposes, while CSS correctly showed one. The runtime now resolves slides-per-view from the viewport width against the block's own breakpoints, and rebuilds the clones and dots when a breakpoint change alters it.

  Reading the block's breakpoint attributes is only ever a prediction, though, and one that is wrong for any author who moves them: `mobileBreakpoint` and `tabletBreakpoint` are editable in the inspector, but `style.scss` hardcodes 768 and 1024, and a media query cannot read a custom property — so the two cannot be kept in step by pushing the values into CSS. Once the slider has painted, it therefore *measures* how many slides CSS actually laid out and rebuilds if that contradicts the prediction. The attributes still seed the first paint, before there is any layout to measure. A slider narrower than the viewport — in a grid cell, or a sidebar — self-corrects the same way.

- **Dragging a slide followed the link inside it.** The browser fires a click after a drag, on the common ancestor of the mousedown and the mouseup — inside a slide, usually that slide's own link. This never surfaced while drag was broken (the gesture snapped back to the first slide); fixing the drag origin made it reachable. A drag of more than five pixels now swallows exactly the one click that follows it, and nothing else.
- **A multi-slide slider scrolled past its own last slide.** The maximum index was `slides.length - 1` regardless of how many slides were on screen, so a six-slide three-up slider kept advancing to index 5 — scrolling two slots of empty track into view with the next arrow still enabled. Bounds are now `slides.length - ceil(slidesPerView)`, and the dots count resting positions rather than slides, so a five-slide three-up slider shows three dots instead of five with the last two doing nothing.
- **A slider with more slides-per-view than slides threw and never initialised.** The clone loop started at `originalSlides.length - ceil(slidesPerView)`, which is negative for two slides at `slidesPerView: 3`, and `undefined.cloneNode()` threw straight out of the constructor — no arrows, no dots, no drag. The clone count is clamped to the number of slides.
- **Dragging a slider snapped it back to the first slide.** The drag origin was initialised to `0` and never reconciled with the track's actual position, so the first `mousemove` wrote `translateX(delta)` over it: starting a drag on slide 3 jumped to slide 0 on the first pixel of movement. The gesture now starts from the settled offset, and `dragstart` is suppressed mid-gesture so a native image drag can't strand the track.
- **Scrolling a page vertically changed slides.** Swipe handling compared only the horizontal distance against its 50px threshold, and a thumb travelling down a page drifts sideways easily. A swipe now also has to travel further horizontally than vertically before it counts.
- **Autoplay stopped after exactly one slide.** `pauseOnInteraction` — on by default — was checked inside the shared navigation method, which autoplay's own advance calls, so enabling autoplay produced a slider that moved once and then sat still for the rest of the page's life. Only genuine user entry points (arrows, dots, keyboard, swipe, drag) count as interaction now.
- **Autoplay kept running in a background tab and under `prefers-reduced-motion`.** The reduced-motion path called the stop method, but the visibility IntersectionObserver restarted the timer on the next intersection, so the setting was honoured for a fraction of a second. Nothing watched `visibilitychange` at all, so a slider in a background tab raced through its slides and the reader returned to an arbitrary position. Autoplay is now a controller holding a set of suspend reasons — off screen, tab hidden, hovered, focused, reduced motion — so releasing one cannot resume over another, and it reacts to the OS motion setting changing rather than reading it once at init. Keyboard users get the same pause hovering already gave mouse users.
- **Keyboard focus could land inside slides hidden from screen readers.** Every slide but the current one was marked `aria-hidden` — wrong on its face for a slider showing three at a time — and given `tabindex="-1"`, which does nothing for the links inside it, so Tab walked straight into an `aria-hidden` subtree. The whole visible window is now exposed, and off-screen slides use `inert` so their contents leave the tab order with them. Loop clones stay hidden from assistive tech for their whole life and drop their descendants' `id` attributes as well as their own, rather than duplicating them into the document.
- **Any page containing a slider was ineligible for the back/forward cache.** `view.js` registered a `beforeunload` listener to clean up on navigation; registering that listener at all disqualifies the page from the browser's back/forward cache — which is precisely the navigation the plugin's own `pageshow` re-init path exists to serve, so the slider was disabling the feature it was built to support. The cleanup moved to `pagehide`, and skips teardown when the page is entering the cache so a restore finds its instances live.
- **A slider transition authored in milliseconds froze the slider.** `transitionDuration` is a CSS time string, and the runtime read it as `parseFloat(value) * 1000` — correct for `0.5s`, wrong by three orders of magnitude for `500ms`, which left the "animating" guard latched for eight minutes and ignored every navigation attempt in between. The value is parsed by unit now.
- **A slider's `destroy()` left most of its listeners attached** — hover, focus, keyboard, visibility, and the IntersectionObserver — and `Home`/`End` navigated to loop clones rather than to the first and last real slides. A `ResizeObserver` on the viewport also catches width changes that never produce a window resize, such as a slider in a collapsing sidebar or a reflowing grid, which previously left the cached slide width stale and the track positioned against it.
- **`ServerSideRender` now works for any block.** `dsgoVisibility` and `dsgoStyleBinding` were registered on the client only, via `blocks.registerBlockType` filters. `ServerSideRender` re-expands the attributes it is given against the *server's* registered schema and core validates that with `additionalProperties: false`, so a block carrying either attribute had every preview rejected with a 400 — and no amount of client-side filtering could fix it, because the expansion happens after the payload leaves the editor. Both attributes are now mirrored server-side through the existing extension-attribute registry, with block exclusion lists byte-identical to the JS ones.

## [2.6.2] - 2026-08-05

### Fixed
- **A sticky or overlay header keeps fading in its scrolled background after an AJAX content swap, instead of going dead until a full page reload.** `utils/sticky-header.js` bound one `scroll` listener per header but gated every one of them behind a single module-scoped `ticking` flag: the first listener registered claimed the requestAnimationFrame gate on each scroll event and released it only after its own callback had run, so every listener registered later was starved permanently. Nothing unbound the listener belonging to a header that a DOM swap had detached — and that dead listener, being the oldest, was exactly the one holding the gate, so the live header never received `dsgo-scrolled` and the scrolled background never appeared. Confirmed against a live page: after the swap the *detached* header kept picking up the class on every scroll while the header actually on screen never did. This is reachable from any AJAX/soft-navigation host that replaces the header — the reported case was a page whose template renders none of the content-wrapper selectors the host looks for, which drops it onto a whole-`<body>` replacement. The window listeners are now bound once and iterate a set of headers that drops any element no longer in the document, so a swapped-out header stops being serviced and the swapped-in one starts. `lastScrollY` advances once per batch rather than inside the per-header handler — updating it per header would leave every header after the first comparing against the already-updated position, so hide-on-scroll-down would never resolve a direction — and the refresh paths (resize, `load`, soft navigation) resync it up front, before evaluating any header, so a refresh resolves to the visible state instead of judging direction against wherever the viewport sat before the swap and sliding the header out of view. That preserves the old invariant, where every `handleScroll()` call left the direction reference current by the time a refresh read it. `setupOverlayHeaderHeight()` no longer binds its own `resize`/`load` listeners, which leaked a pair per swap and left a stale one measuring a detached element as zero; that measurement moves into the shared handlers, which also re-apply the overlay hero clearance after a content swap — the block it is written to lives in the swapped region and previously came back without it, dropping the first section under the header. (#500)
- **The footer no longer paints the sticky header's shadow across its own top on scroll.** The default header selector matches `.wp-block-template-part:has(.wp-block-navigation)`, which is the footer as well on any theme that puts a navigation there, so the footer was handed `dsgo-scrolled` — and the shadow and shrink-logo rules act on that class regardless of which template part carries it. The bug was invisible until now only because the shared rAF gate above starved the footer's listener before it could ever run; fixing that surfaced it. The three footer-reachable clauses of the selector gain `:not(footer)`, mirroring the exclusion `_sticky-header.scss` already applies to every sticky rule. (#500)

## [2.6.1] - 2026-08-04

### Fixed
- **"Background on scroll" with no colour configured now paints the theme's background instead of nothing at all.** `applyCustomProperties()` wrote `--dsgo-sticky-scroll-bg-color: transparent` and `--dsgo-sticky-scroll-text-color: inherit` whenever the feature was on but no explicit colour was set. Because the stylesheet reads those as `var(--dsgo-sticky-scroll-bg-color, <fallback>)`, a written sentinel *satisfies* the `var()` — so the fallback chain never evaluated, and enabling a feature called "background on scroll" produced a fully transparent scrolled header. It also made the theme-preset cascade above unreachable on this path: the rule requires the `dsgo-sticky-bg-on-scroll` class, and that class is itself one of the two conditions that trigger the sentinel write. Both branches now call `removeProperty()` and leave the cascade to the stylesheet. The light-mode rule additionally pairs its foreground with the background — `base-2` is theme-defined and carries no guarantee of lightness, so text left on `inherit` would sit at near-1:1 contrast on any palette whose `base-2` is dark; it now follows `contrast`, which is guaranteed to oppose the `base` family. Themes defining neither slug resolve to exactly the previous near-white-on-inherited. (#497)
- **A form on a page served from a stale full-page cache no longer fails with "Security verification failed."** Nonces last roughly 24 hours; full-page cache TTLs routinely exceed that, so cached markup can carry a nonce that has already expired. `handle_form_submission()` verifies a nonce only when one is *present* — deliberately, so anonymous visitors can submit — which means a **stale** nonce is rejected where an **absent** one is accepted. Stale was therefore strictly worse than absent, and `view.js` treated any non-429 REST error as terminal, with no retry and no fallthrough to the admin-ajax transport (which fails the same way). The REST branch now inspects a 403 for a nonce-rejection code and, on a match, retries once with the header omitted. It matches `rest_cookie_invalid_nonce` as well as the plugin's own `invalid_nonce`: verified against a live endpoint, the code that actually fires is core's — `rest_cookie_check_errors()` rejects a bad `X-WP-Nonce` during REST authentication, so the request never reaches `handle_form_submission()` at all. This concedes nothing: the endpoint is public by design, so an attacker could always have submitted anonymously, and the honeypot, submission-timing, IP rate-limiting and optional Turnstile controls all still apply. This bug predates 2.6.1 but its blast radius grew with the localization fix below — a footer newsletter form now works on every page, so every page's cached nonce matters.
- **The scrolled sticky/overlay header now takes its background from the theme's palette instead of a hardcoded near-white.** `--dsgo-sticky-scroll-bg-color` is only written by `utils/sticky-header.js` when `backgroundOnScroll` is enabled or the block carries `dsgo-sticky-bg-on-scroll`; a page using only the per-page Overlay Header enters neither branch, so the CSS fallback in `_sticky-header.scss` was the value that actually rendered — and it was a literal `rgba(255, 255, 255, 0.95)`, which reads as an out-of-palette white strip on any theme that isn't light. All three fallback sites now cascade through a theme preset before the literal: the light and overlay rules prefer `--wp--preset--color--base-2` (the house pattern for a secondary surface, already used by `accordion-item`, `timeline-item` and `query-results`), while the `prefers-color-scheme: dark` rule pairs `contrast` for the background with `base` for the foreground — that rule hardcodes a light foreground in three places, and `contrast`/`base` are guaranteed to oppose each other in any `theme.json` palette, so the pairing stays readable where `base-2` could have put white text on an off-white surface. The cascade is applied by redefining a `--dsgo-sticky-scrolled-bg` custom property inside an `@supports (background-color: color-mix(…))` query rather than by putting `color-mix()` directly in the `var()` fallback: a declaration containing `var()` isn't syntax-checked until after substitution, so on a browser without `color-mix()` it would be invalid at computed-value time and reset `background-color` to `transparent` — leaving the scrolled header with no background at all — and ordering the literal first doesn't help, because IACVT falls back to the initial value, not to an earlier declaration. A theme defining none of these slugs resolves to exactly the previous literals, and a set `--dsgo-sticky-scroll-bg-color` still wins, including its explicit `transparent`. (#497)
- **Forms outside post content — a newsletter signup in the footer, most commonly — no longer fail every submission with "designsetgoForm is not defined."** The script that carries the form's REST URL and nonces was localized behind a `has_block( 'designsetgo/form-builder' )` guard, and that check only inspects the current post's content. A form living in a template part, a synced pattern, a block widget, or anything rendered through `do_blocks()` never matched it, so the guard returned early and the payload was never attached — while the block itself still rendered and WordPress still enqueued its `viewScript`. The form therefore looked completely fine right up until someone pressed Submit, at which point `view.js` hit an undefined `designsetgoForm` and the submission died client-side. The guard is removed rather than taught to scan template parts, which would still have missed synced patterns, widgets and `do_blocks()` while adding per-request cost. Removing it is free: `wp_localize_script()` only attaches data to a *registered* handle, and that data is printed only when the script is actually enqueued — which happens at block render time — so a page with no form still emits nothing, leaking neither nonce nor payload. (#496)

## [2.6.0] - 2026-07-29

### New Features
- **Theme animation defaults**: set an entrance animation per block type (e.g. all Buttons fade in) once, in Settings → DesignSetGo → Animations or in theme.json (`settings.custom.designsetgo.blockAnimations`). Every block of that type inherits it automatically; individual blocks can override (Custom) or opt out (Off). The settings panel exposes block types, entrance, trigger and duration; the advanced options the same data model supports — exit animation, delay, easing and animate-once — are authorable in theme.json only for this release. One rule can target several block types at once — search and pick them from the block list, or choose a `namespace/*` wildcard.

### Fixed
- **Overlay header is transparent again, and no longer pulls page content up by the footer's height.** Two independent bugs made the per-page Overlay Header unusable on Twenty Twenty-Five. The content pull-up was sized from the wrong element: `setupOverlayHeaderHeight()` ran for every element matched by the sticky-header selector, which includes `.wp-block-template-part:has(.wp-block-navigation)` — that matches the footer on most themes — and each match wrote the shared `--dsgo-overlay-header-height` custom property, so the last writer in DOM order won and content was pulled up by the footer's height (410px) instead of the header's (92px), slicing the top off the hero. Measurement is now restricted to the single template part that is a direct child of `.wp-site-blocks`, which is what the CSS pull-up actually consumes. Separately, the header never went transparent, because the background-strip rule only matched `.has-background`: a theme can paint a container from a block style variation (Twenty Twenty-Five's `is-style-header-section`) or from `styles.blocks` in `theme.json`, neither of which adds that class. The rule now matches container block classes as well, with a zero-specificity `:where()` exclusion list that preserves backgrounds on buttons, submenu dropdowns, and the mobile menu overlay — all unreadable over a hero image without one. The first content section also gains the header height as top padding, so its content clears the header while its background still runs behind it; the authored padding is preserved as a CSS string rather than a pixel snapshot, so inline fluid presets keep responding to viewport changes, and `--dsgo-overlay-hero-clearance: 0px` opts a section out. (#491)
- **Section shape dividers now honour the theme's height and width tokens.** A theme (or Style Kit) could already set the default divider *shape* through `settings.custom.designsetgo.shapeDivider.type`, and the standalone Section Divider block already read the `.height` token — but the Section block's own top/bottom dividers ignored both size tokens entirely. Their stylesheet hard-coded `var(--dsgo-shape-height, 100px)` / `var(--dsgo-shape-width, 100%)` with no token in the fallback chain, and `shapeDivider{Top,Bottom}{Height,Width}` defaulted to `100`, so "untouched" was indistinguishable from "explicitly 100" and there was nothing for a token to fill in. Both attributes are now nullable, and the size cascade — inline attribute → `settings.custom.designsetgo.shapeDivider.{height,width}` → `100px` / `100%` — lives in a shared `_shape-size.scss` partial used by the Section block and the Section Divider block alike, so `.width` is a real token everywhere rather than a name only one block understood. The content clearance that keeps content from sitting under a divider follows the same chain, so a theme that sets a 200px divider also reserves 200px. An explicit value still wins and is always serialized (including an explicit `100`, which is how an author pins a divider against a theme that says otherwise), and the inspector's Height/Width sliders gain a Reset that returns them to the theme default. No markup change for existing content: WordPress never wrote either attribute to the block comment while it sat at the old default, and `save()` emitted no size property at that value either, so pre-change sections stay valid without a deprecation and simply begin inheriting. Deprecations `v7`–`v9` now render a frozen copy of the divider component rather than the live one, so sections saved at the old default size keep byte-matching their historical output.

## [2.5.1] - 2026-07-23

### New Features
- **Section Shape Divider: six new layered/tonal dividers** — The Section block's Shape Divider control gains six options that paint as single-layer band overlays (band colour at the mask's graduated alpha) rather than see-through knockouts: `triangle-layered` and `triangle-layered-extra` (a centered peak with one or two half-tone back layers), `curvy-triangle-layered` (a soft curved peak with a half-tone curved layer behind it), `symmetric-waves-layered` (stacked rows of symmetric wave crests at 0.3 / 0.5 / solid), and `side-triangle-layered` / `side-triangle-layered-extra` (an asymmetric diagonal band anchored at the top-right corner, in two- and three-tone). Each is wired through the shared mask library, the section block's tonal single-layer group, the inspector preview + option label, and the "Configure Shape Divider" ability's allowed shape values. (#489)

### Fixed
- **Section shape-divider clearance is author-defined, so preset-spacing patterns no longer show "Attempt Recovery."** The inner content clearance that keeps content from sitting under a shape divider was derived from the divider height and serialized as a hardcoded `padding-top:${height}px`, so a pattern that expressed that clearance as a theme spacing token (`var(--wp--preset--spacing--NN)`) could never byte-match `save()` and failed block validation. Clearance is now an explicit `shapeDivider{Top,Bottom}Spacing` attribute driven by a "Content Clearance" inspector control (theme spacing presets), serialized as inner padding only when set. A `v9` deprecation reproduces the old height-derived output and migrates it into the new attribute; the height→spacing carry-over is shared by every shape-divider-era deprecation (`v3`–`v9`), not just the newest, so a section matching an older signature (e.g. a divider that also carries an overlay/hover style variation) still migrates silently instead of losing its clearance. A brand-new divider with no explicit clearance falls back to a CSS default that matches the divider's own height (its height is exposed on the wrapper as `--dsgo-shape-clearance-{top,bottom}` and read by a sibling-keyed `:where()` rule, so a 300px divider clears 300px, not a flat amount, and only the present position reserves space); the same height-matched fallback covers AI-inserted dividers from the Abilities API "Configure Shape Divider" flow, which stores no clearance snapshot of its own. Migrated raw-px values surface as a "Custom (…)" option in the control so a preserved value is never silently overwritten.

## [2.5.0] - 2026-07-21

### New Features
- **Grid: Align Rows (CSS subgrid)** — A new Grid option that lines up each row of card content (images, headings, dividers, buttons) across columns using CSS subgrid, so cards with different amounts of text stay aligned with no wasted whitespace. Works with Section, Row, and Group cards; the per-card row count is detected at runtime and the whole thing is `@supports`-guarded so browsers without subgrid fall back to the normal stacked layout. Off by default, so existing grids serialize byte-identically (no deprecation). (#477)
- **Form Builder: submit button style (Secondary / Outline)** — The submit button gains a Button Style control (`default` / `secondary` / `outline`) that emits an `is-style-{variation}` class, so a form placed on an alternate background can use a secondary/outline button and section/group style variations can restyle it. The class lives in the `is-style-*` namespace (matching the Icon Button), deliberately separate from the `dsgo-form__submit--*` layout/state/animation modifiers so a variation can never collide with a like-named modifier — which also lets `Button_Global_Styles` project every registered `core/button` variation onto the submit button with no reserved-slug bookkeeping. Ships a self-contained ghost fallback (transparent + current-color border) scoped above the theme's filled-button rule; Style Kits repaint the same class. Default output is byte-identical, so existing forms are untouched and need no migration. (#469, #472)
- **Submit button honors its style variation when AI-inserted** — The Abilities API `Block_Inserter` now reads `submitButtonVariation` when it builds a form, so an AI-assisted insert produces the same `is-style-*` output as authoring the form by hand. Backed by parity + enum-validation tests. (#468)

### Changed
- **DSGo button style variations render at winning specificity** — `Button_Global_Styles` now projects every registered `core/button` style variation (primary/secondary/outline/…) onto DSGo's button primitives at a specificity that beats the theme's filled-button rule, so a chosen variation always paints. (#470)

### Fixed
- **Translated (and otherwise content-edited) blocks no longer show "Attempt Recovery."** Several blocks stored their visible label in the block comment *and* rendered it into the HTML, so editing only the visible text — as a site translation does — left the comment copy stale and `save()` no longer matched the stored markup. Each label now has a single source of truth in the HTML, matching `core/button`: Icon Button, Modal Trigger, Accordion Item, Timeline Item, and Counter source their text directly, with no markup change and no migration (#482); Form Builder and Countdown Timer drop the redundant `data-*` copy of their label, and Card and Table of Contents keep their title/subtitle/body/badge elements in the markup — hidden via a `--hidden` modifier when a visibility toggle is off — so the sourced text survives being toggled off. Each of those four ships a deprecation that migrates existing content silently, and the change is mirrored in the bundled patterns and the Abilities-API block inserter so AI-inserted blocks match too (#483).
- **Forms and responsive grids generated by the site-designer API no longer show "Attempt Recovery."** The generator emitted markup that never matched any shipped `save()`: its forms strip the honeypot `aria-hidden` and message-div `aria-atomic` while writing a custom error message into `data-error-message` without mirroring it into the block comment, and its responsive grids carried the tablet column count in a `className` (`dsgo-grid-cols-tablet-N`) with the min-width living only in the inner `minmax()` track and no `columnMinWidth` attribute. Two compatibility deprecations reproduce those exact shapes so the affected blocks migrate silently: the Form Builder entry sources both messages from the wrapper's `data-*` attributes and drops the baked spacing/sizing tokens so the form inherits the theme; the Grid entry recovers `columnMinWidth` from the stored track and lifts the real `tabletColumns` out of the class. Regression tests are built from the actual captured page markup, including a guard that genuinely-old (non-generator) content still routes to its original deprecation rather than the new one. (#484)
- **Query Monitor no longer fatals every request when its debug panel is active.** DSGo registers a Query Monitor output panel via the `qm/outputter/html` filter, but the callback type-hinted its second parameter as `array $collectors`. QM's dispatcher passes the `QM_Collectors` singleton *object* (`apply_filters( 'qm/outputter/html', array(), $collectors )`), so PHP threw a `TypeError` at argument binding on every front-end and admin request while Query Monitor was enabled. A second latent fatal sat behind it: `QM_Collectors` implements only `IteratorAggregate`, not `ArrayAccess`, so the callback's `isset( $collectors['dsgo_queries'] )` / `$collectors['dsgo_queries']` array access would itself have fatalled once the type hint was corrected. The callback now uses the `\QM_Collectors` type hint and the collector's canonical static accessor (`\QM_Collectors::get( 'dsgo_queries' )`) with an `instanceof \QM_Collector` guard. Pre-existing since the Query Monitor integration shipped in 2.4.0, and backed by a stub-based regression test that dispatches the real filter callback and fails against the unpatched code. (#473)
- **Cloudflare Turnstile no longer rejects every submission it protects.** Turnstile was unusable on any site that enabled it: the form endpoint validated `turnstile_token` against `/^[a-zA-Z0-9_-]+$/`, which has no `.` in the character class, but real Turnstile tokens are dot-delimited. The widget rendered and issued its token normally, then every submission failed REST parameter validation with `400 Invalid parameter(s): turnstile_token` before reaching the handler — so enabling Turnstile silently broke the form instead of protecting it. The token is opaque (Cloudflare guarantees only a 2048-character ceiling and does not contract its alphabet), so the validator no longer tries to parse its structure: it bounds the length and rejects only what a token can never contain — whitespace, newlines, and other non-printable bytes — before forwarding the value to `siteverify`. Non-string input is now rejected cleanly rather than reaching `preg_match()` as a PHP 8 `TypeError`. Pre-existing in the shipped 2.4.0 tag, and backed by a regression test that dispatches the real route and fails against the unpatched code. (#466)
- **Modal overlay color inherits the theme instead of baking in black.** The backdrop always serialized `background-color:#000000` from the `overlayColor` default, so modal patterns authored without the inline color failed block validation ("Attempt Recovery") and Style Kits could not retheme the scrim. `overlayColor` no longer has a default; `save()` writes the backdrop color only when it is explicitly set, and the stylesheet owns the scrim otherwise (`--wp--custom--designsetgo--modal--overlay-color` → `#000`, in both the frontend and editor preview). A deprecation reproduces the old always-baked markup so existing modals migrate silently, dropping a default `#000000` while preserving explicit colors; the migration also strips a legacy-sourced `anchor` attribute that core would otherwise have written over the modal's id. (#475)
- **Form Builder inline submit lines up with its field.** An inline (side-by-side) submit button carried a `margin-bottom` "label-gap compensation" that lifted it out of alignment on the frontend, and in the editor it dropped to its own row because the last-field auto-size rule stopped matching once the block-list appender was present. Both are fixed, so an inline submit sits level with the input in the editor and on the frontend. (#475)
- **Loading spinner shows on styled submit buttons.** The loading state hid the button label with `color: transparent`, which the Global-Styles button rule and the new style variations out-specified — so on a styled button the label stayed visible and overlapped the spinner. The label is now hidden with `text-indent`, which nothing else sets, so `currentColor` stays real and the spinner paints. Also fixes the pre-existing case where a plain button hid its text but blanked its own spinner. (#469)
- **Excluding a third-party block from DesignSetGo now works in the editor.** The `excludedBlocks` / `enabledExtensions` payload was localized on `enqueue_block_editor_assets`, but the `designsetgo-extensions` handle it guarded on is only enqueued on `enqueue_block_assets` — the sole hook whose scripts WordPress prints into the iframed editor canvas. The guard therefore always failed, the settings never reached the iframe, and every excluded third-party block (e.g. `gravityforms/form`) still received DSG attributes and panels and threw "invalid block attributes." The payload is now localized alongside the editor enqueue so it rides into the iframe. (#464)
- **Draft Mode no longer deletes excluded meta on publish.** `copy_post_meta()` skips keys in the `designsetgo_draft_excluded_meta_keys` filter, but the publish-time `sync_post_meta()` did not know about that list — so an excluded key present on the original post but (correctly) absent from the draft was read as "the author deleted it" and removed. A third-party integration keeping its own bookkeeping key off the draft therefore lost it on every publish. Both paths now read the excluded list from one shared helper, so an excluded key's absence is recognized as "never copied" rather than "removed." The object-scan depth cap is also filterable now for sites with legitimately deep, object-free meta. (#468)
- **Rate-limit and localized-script keys are DSGo-prefixed.** The form rate-limit transient key and the localized `dsgoIntegrations`/`dsgo_integrations` script object are now `dsgo_`-prefixed to avoid collisions with other plugins. (#465)

### Security
- **Ported hardening that was stranded on the 2.2.0 line.** (#467) All strengthen existing protections; no known exploit was involved.
  - **Draft Mode meta copy fails closed on deep payloads.** `contains_object()` recursion is capped at a bounded depth; a payload nested deeper is treated as object-bearing and skipped, removing any stack-overflow risk from a maliciously deep serialized value.
  - **Settings secret-overwrite window closed.** A browser holding a pre-upgrade settings form could echo the old bullet (`••••••••`) placeholder back on submit; the write path compared only against the new sentinel, so that stale submit would overwrite a saved API key with the bullets. The write path now recognizes both the current and legacy placeholders as "unchanged," closing the window in code rather than relying on a release note. The redaction sentinel is also now pure-ASCII so it is byte-identical across encodings and opcode caches.

### Internal
- **SCSS build warnings cleared.** Form-field editor styles migrated from Sass `@import` to `@use`, and a nested `var()` inside `calc()` in Image Accordion Item was flattened to a single custom property to clear a postcss-calc parse warning. (#474)

## [2.4.0] - 2026-07-12

### Security
All four were pre-existing (present in the shipped 2.3.0 tag), verified by a full audit of the release diff, and each is backed by a regression test confirmed to fail against the unpatched code.
- **Form notification emails escape submitted values.** Notifications are sent `Content-Type: text/html`, and per-field merge tags (`{message}`, `{name}`) were interpolated raw while the `{all_fields}` aggregate escaped — so a template using the per-field tags let an anonymous submitter put markup into the site owner's inbox. Body and subject now use separate maps: the body is `esc_html()`'d, the subject (a plain-text header) is newline-stripped only, and `{site_name}` round-trips through `wp_specialchars_decode()` so it is not double-escaped. The Reply-To extraction was routed through the same field-flattening helper, closing a PHP 8 `TypeError` when a multi-value field was the Reply-To source. (#463)
- **CSS escape-sequence bypass in the dynamic-style sanitizers.** A browser resolves CSS escapes before applying a declaration, so `\75\72\6c(…)` *is* `url(…)`; `StyleBinding` and `Abilities\CSS_Sanitizer` matched the raw text and blocked neither. The escape/entity normalization (previously private to `Custom_CSS_Renderer`) is now the shared `designsetgo_normalize_css_escapes()`, called before every dangerous-pattern check; `CSS_Sanitizer` decodes to a fixed point and rejects a value that will not converge rather than emit it half-decoded. (#463)
- **Attribute breakout in AI-assisted block insertion.** `Block_Inserter` concatenated `textAlign`/`align` into a `class="…"` attribute; those are now allowlisted to the four alignment keywords core emits. Reaching it required the add-block ability plus `unfiltered_html`, so it was not an escalation past an existing boundary. (#463)
- **Tabs deep-link crash.** `window.location.hash` was passed straight to `querySelector`, so an ordinary anchor like `#2024` threw `SyntaxError` and disabled every Tabs block on the page. The fragment is now `CSS.escape()`d, with a throw treated as "no match." (#463)

### Fixed
- **Deprecations no longer reclaim current content (and silently drop its attributes).** WordPress calls a deprecation's `isEligible` as `isEligible( attributes, innerBlocks, { blockNode, block } )` — there is no `innerHTML` key on that third argument — and it only consults it for a block that is still VALID (`if ( block.isValid && ! isEligible( ... ) ) continue;` in `@wordpress/blocks` → `api/parser/apply-block-deprecated-versions.js`). All 67 of our guards destructured `{ innerHTML }`, so every one silently returned `false` forever; that went unnoticed because the invalid-block path skips `isEligible` entirely and matches on `save()` output instead, which carried every migration we had. Fixing the contract exposed guards that claimed CURRENT content — two of them destructively: a Tab was migrated through a schema predating `strokeWidth` and a Blob through one predating `height`, dropping those attributes on parse. The remaining over-broad guards keyed on an attribute being absent from (or present in) the block comment, which cannot distinguish old content from new — WordPress never serializes an attribute equal to its default. `getDeprecatedBlockHTML()` (`src/utils/deprecated-block-html.js`) now owns the contract in one place, and `tests/unit/deprecations-isEligible.test.js` pins the invariant across ~479 attribute variants: no deprecation may claim a block's own current `save()` output, and every block must round-trip `createBlock → serialize → parse` losslessly. (#462)
- **Block assets now cache-bust on release.** WordPress versions a block's own CSS/JS with the `version` field from its `block.json` (`wp-includes/blocks.php`), and nearly every block here still carried the scaffolded `"version": "1.0.0"` — so `build/blocks/{block}/index.css?ver=1.0.0` was byte-identical across every release and browsers/CDNs kept serving the copy they had cached from an older version of the plugin. Any CSS-only fix to any block therefore never reached existing users. `Blocks\Loader::sync_asset_version()` now forces every DesignSetGo block's asset version to `DESIGNSETGO_VERSION` via the `block_type_metadata` filter. The bug was invisible in development because WP uses a `filemtime()` when `SCRIPT_DEBUG` is on and only falls back to the block.json version in production.

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
- **Pill renders dynamically** — The Pill block is now server-rendered, completing the icon/divider/map/form conversion. A fresh pill serializes to a single self-closing block comment with no baked-in `aligncenter` / `has-small-font-size` classes, so pills stay portable across patterns and AI-assisted (Abilities API) authoring and always reflect the current theme. Existing pills migrate silently via a new deprecation; the default centered alignment and small text size are now CSS-driven and unchanged. (#439, #457)
- **Form Builder fields inherit theme spacing and sizing.**
- **Map markers can inherit their color from the theme.**
- **Section styles extend to more container blocks** — Card, Fifty/Fifty, Modal, Slide, Scroll Slide, Tab, Accordion Item, Scroll Accordion Item, Image Accordion Item, Timeline Item, Counter, and Flip Card Face now pick up theme-registered section styles (e.g. core's Style 1–5), matching Section, Row, and Grid. (#440)
- **Progress Bar no longer bakes a default color into saved markup** — An unset bar/track color now inherits the theme instead of a fixed hex value. (#440)
- **Icon Button icon gap and size are themeable** — The icon↔text gap is no longer baked inline: it resolves from a kit-controllable CSS custom property (`--dsgo-icon-button-gap` → the `--wp--custom--designsetgo--icon-button--gap` token → `8px`) and is omitted entirely when the button has no icon. Icon size gained the same kit hook (`--dsgo-icon-button-size`) ahead of the existing theme token. Buttons with an icon now carry a `dsgo-icon-button--has-icon` class; an explicit author gap is still written inline and wins. Existing buttons migrate automatically.
- **Icon List Item gaps and icon size are themeable** — The item icon↔content gap, the content gap, and the inherited icon-box size are no longer baked as raw inline pixels. They resolve from kit-controllable CSS custom properties (`--dsgo-icon-list-gap` / `--dsgo-icon-list-gap-top` / `--dsgo-icon-list-content-gap` / `--dsgo-icon-list-icon-size`, each over its `--wp--custom--designsetgo--icon-list--*` token), so a Style Kit can retheme them. Explicit values still win; existing items migrate automatically.
- **Image Accordion height and gap default to theme tokens** — The panel height (was a fixed `500px`) and gap (was `4px`) now default to themeable values (`--dsgo-image-accordion-height` over a custom token; `--dsgo-image-accordion-gap` over `--wp--preset--spacing--20`) instead of baked-in pixels, so patterns no longer override them with magic numbers. Explicit author values still win; existing accordions migrate automatically.
- **Scroll Marquee images can size from their aspect ratio** — `Image Width` now offers an "Auto width (from aspect ratio)" option, which is the new default, so authors normally set only a single height and each image's intrinsic ratio drives its width (the frontend already measures rendered width at runtime). Existing marquees keep their stored width and are unchanged.
- **Blobs has a native, themeable max-width** — Blob width now uses a native `Max Width` control that resolves from a kit-controllable custom property (`--dsgo-blob-max-width` over the `--wp--custom--designsetgo--blobs--max-width` token) instead of the generic max-width extension's baked-in inline value. Existing blobs migrate automatically.
- **Pill, Icon, Icon Button, and Modal Trigger position with a new `justification` control instead of `align`** — These blocks previously used WordPress's `align: left | center | right` to position themselves horizontally, but that's a misuse of `align`: in WordPress, `align: left|right` means "float out toward the page edge", and core's constrained layout explicitly excludes `.alignleft` / `.alignright` from its content-size cap (`wp-includes/block-supports/layout.php`), so an aligned block escaped the theme's content column and pinned itself to the edge of its container. All four blocks now expose a **Justification** toolbar control (Left / Center / Right) that positions the visible element inside a block-level wrapper WordPress correctly constrains and centers — the same model core's Buttons block uses. This comes with three behavior changes upgrading authors should know about:
  - **Icon Button and Modal Trigger now stack instead of sitting side-by-side.** Both were previously `inline-flex`, so two placed one after another could end up sharing a line by accident. As block-level wrappers they now each take their own line; use a Row block to place them side-by-side intentionally.
  - **`align: "full"` on Icon Button / Modal Trigger became a `fullWidth` attribute.** It used to mean "stretch the button to 100% at the page's top level." It now means "the button fills the content column" rather than bleeding edge-to-edge. Inside a Section — where nearly all usage lives — the rendered result is identical.
  - **Icon backgrounds and borders now paint on the inner icon box** rather than the block root, so they hug the icon instead of spanning the full content column.

  Published content is unaffected until it is next re-saved: existing posts and pages keep rendering exactly as they did before this change (Pill and Icon Button/Modal Trigger have safety nets for their respective static/dynamic un-migrated-content cases), and fully switch to the new positioning wrapper the first time the post is re-opened and re-saved in the editor — at that point it also stops showing an "Attempt Recovery" prompt. The 83 bundled patterns using these blocks were regenerated, including Pill's and Icon's (both dynamic/server-rendered, so their patterns needed the block's real serialization path rather than a markup find/replace — see `tools/regenerate-patterns.js`). (#457)

### Bug Fixes
- **Map marker color tracks the theme palette** — Choosing a theme color (e.g. Accent 2) for a Map marker now stores a theme-palette reference (`var:preset|color|{slug}`) instead of a baked-in hex, so the marker follows Style Kit color changes. It resolves to a concrete color at render time, falling back to the block default when a palette color is missing. (#449)
- **Modal: custom accessible label now works** — The dialog's `aria-label` read a `modalLabel` attribute that was never registered, so a custom label could never take effect and it always fell back to "Modal". The attribute is now registered and wired to the new "Accessible Label" control.
- **Icon List frontend parity** — Items show their fill / outline and stroke on the frontend, matching the editor.
- **SVG Patterns / Form Builder color baking** — No longer bake default colors into saved markup, so they inherit the theme's colors.
- **Abilities API quote / backslash handling** — Content with quotes or backslashes saved through AI-assisted edits is no longer altered.
- **Form submissions no longer lose backslashes** — Stored submissions ran through WordPress's automatic meta unslashing twice, so any backslash in a submitted value was silently dropped — a message containing `C:\Users\me` was saved as `C:Usersme`, and code snippets, regexes, and file paths were corrupted the same way. Submitted values are now stored verbatim. (Same defect class as the Abilities API fix above.) (#457)
- **Scrolling Gallery legacy migration** — Blocks saved by older versions or patterns (image rows stored in the markup rather than the block comment) keep migrating silently instead of showing "Attempt Recovery."
- **Image Accordion overlay now reaches the frontend** — A parent Image Accordion's overlay color/opacity previously never rendered on its items on the frontend; it now applies correctly. (#440)
- **Section style customizations preview live in the editor** — Border, radius, and other section-style tweaks made in Global Styles now show up immediately on Section, Row, Grid, and the rest of the container family in the editor canvas, matching what already rendered on the frontend. (#443)
- **Section overlay style variations now render** — Sections using an `is-style-overlay-*` Style Kit variation (instead of the overlay color picker) now correctly show the overlay. (#445)
- **Scroll Accordion: removed a stray editor-only border** — A gradient bar shown down the left edge of Scroll Accordion items in the editor, with no frontend equivalent, has been removed. (#442)
- **Blobs / Icon List Item migrations no longer scan nested block markup** — Both blocks accept arbitrary nested content, and their new deprecations' eligibility checks previously scanned the entire block subtree for an old-format signature. A nested block emitting similar-looking markup (e.g. the still-active generic max-width extension on a non-excluded child, or `align-items`/`gap` from an unrelated nested block) could false-positive the check and silently drop a valid block's attributes on re-parse. Both checks are now scoped to the block's own wrapper tag.

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
