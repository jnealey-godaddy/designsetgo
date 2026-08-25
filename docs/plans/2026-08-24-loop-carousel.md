# Loop Carousel — pagination contract and carousel hardening

**Date:** 2026-08-24
**Branch:** `claude/loop-carousel-improvements-6cv6rt`
**Spec:** Elementor Gap Roadmap (2026-08-19), Plan 5
**Predecessor:** [Query-capable layout blocks](2026-04-21-query-capable-layout-blocks-design.md)

## Where this starts

Plan 5 asks for "a presentation mode on the Query block that reuses `slider`'s
frontend module rather than a second carousel implementation — if the plan ends
up writing new carousel JS, it has gone wrong."

Most of that already shipped in v2.6. `designsetgo/slider` is a registered query
item host: `designsetgo_query_render_container()` hands it the iterated items,
its `render.php` wraps them in slider chrome, its `edit.js` previews them, and a
`featured-carousel` inserter variation composes the whole thing. No new carousel
JS is needed, and none is written here.

What did not ship is the part the roadmap called out explicitly:

> Interaction with `query-pagination` needs an explicit answer: infinite scroll
> and carousel presentation are mutually exclusive, and the plan should say
> which wins and what the editor shows when both are set.

The 2026-04 design deferred it — "Numbered pagination works, but UX clashes with
slider's own arrows. No guard; author's call" — and then asserted the opposite
of what it recommended: "Infinite (v2.2) — recommended pairing for slider."

Neither statement survived contact with the code. Every pagination kind past
`numbered` was inert inside a carousel, and so was every filter:

- `dsgoGetQueryContainer()` finds the item container by
  `[data-dsgo-query-results-role="container"]`. Only `query-results` emitted
  that pair, so with a carousel host the lookup returned null and load-more
  returned early — button click, no error, nothing happens.
- Even past that, `dsgoLoadMorePlain()` collected new items with
  `.dsgo-query__item`, a class only the grid host's `<li>` wrapper carries. A
  carousel's items are bare `designsetgo/slide` renders, so the selector matched
  nothing while `ctx.page` still advanced — click twice and page 2 is skipped.
- A filter refresh swaps the region's `innerHTML` and dispatched nothing
  afterwards, so the replacement carousel never initialised: no arrows, no dots,
  no drag, until a full page reload. The detached instance's `document` and
  `window` listeners leaked, one set per filter change.

So the decision below is the deliverable, and making the supported kinds
actually work is the rest of it.

## D1 — Carousel presentation wins over infinite scroll

**Infinite scroll degrades to Load more when the item host is a carousel.**

Infinite scroll is defined by a sentinel placed after the items: the reader
scrolls the document down past the list, the sentinel enters the viewport, the
next page loads. That signal only exists because the items grow the page
vertically.

A carousel lays its items out inside a fixed-height viewport. The sentinel sits
just below the whole carousel, where its position says nothing about how far
through the results the reader has got. It is on screen from first paint on any
page where the carousel is above the fold — firing page after page until the
query is exhausted — or it is below the fold and never reached. Neither is
pagination. There is no parameter that fixes this; the two mechanisms want
incompatible layouts.

Given they cannot coexist, the presentation is the author's deliberate design
choice and the pagination kind is a mechanism serving it, so the presentation
wins.

**Degrade rather than drop.** Rendering nothing would strand the reader on page
one with no way forward and no clue why. The Load more button is the same IAPI
action without the auto-firing sentinel, so it is what infinite scroll already
falls back to when `prefers-reduced-motion` is set — the fallback path exists
and is tested. The author's `buttonLabelWhenPaused` carries over as the button's
label, since a block that was never a Load more block has no `labelLoadMore` set.

**Implementation.** `designsetgo_query_render_container()` records the resolved
host in a per-request registry (`designsetgo_query_set_item_host()`), mirroring
`designsetgo_query_set_last_state()`. Sibling blocks render after the host, so
`query-pagination/render.php` can read it.
`designsetgo_query_host_supports_infinite_scroll()` answers for a host — `true`
for `designsetgo/query-results` and for a query with no host block at all
(a legacy tree, which still renders a vertical list), `false` otherwise, and
filterable so a third-party host that does grow the page vertically can opt in.

Third parties default to unsupported rather than supported: a block registered
through `designsetgo_query_item_host_block_names` is non-grid by definition, and
the failure mode of guessing wrong in that direction is a runaway load loop.

## D2 — What the editor shows

`designsetgo/query-pagination` resolves the same decision client-side via
`useQueryItemHost()`, which scans the enclosing query's direct children exactly
as the server scans its parsed innerBlocks. When infinite scroll is set inside a
carousel it:

- shows a warning naming the host block, with a one-click **Switch to Load
  more** that writes `paginationKind` and `mode` together;
- previews the Load more button (with the resolved label) instead of a sentinel,
  plus an inline note under it so the block does not read as a plain load-more
  block someone configured by hand;
- hides **Auto-pause after** and **Sentinel offset**, which configure a sentinel
  that will not exist, and keeps **Button label**, which is used.

The attribute is left as the author set it. Rewriting `paginationKind` behind
their back would make the block's stored state disagree with what they chose,
and would silently rewrite every existing post on first edit.

## D3 — Numbered and Load more

`numbered` is a full page navigation — the carousel re-renders from scratch with
the next page's items, so the presentation is irrelevant. Supported, unchanged.
The arrows-plus-page-numbers UX clash the 2026-04 design worried about is real
but it is a design question, not a correctness one; it stays the author's call.

`loadmore` is the pairing that actually reads well in a carousel — swipe to the
end, click, more slides append — so this plan makes it work:

- Both carousel hosts tag their item container with
  `data-dsgo-query-results-role="container"` + `data-dsgo-query-id`: the track
  for `slider`, the panels wrapper for `scroll-slides`. This is now part of the
  documented host contract.
- `extractRenderedItems()` reads the returned region's item container children
  rather than matching `.dsgo-query__item`, which works for both host shapes and
  additionally stops grouping's `<section class="dsgo-query-group">` wrappers
  being flattened on append.
- The append dispatches `dsgo-query-items-appended` on the container. A host
  whose element survives the append would otherwise be skipped by its own
  element-keyed init guard. The slider rebuilds its clones, dots and cached
  dimensions, then advances onto the first new slide — not for flourish:
  `view.js` moves focus there for the screen-reader handoff, and an off-screen
  slide is `inert`, which refuses focus. Advancing is also what the reader asked
  for by pressing a button below a carousel they had worked through.

## D4 — Re-initialisation after a query refresh

A filter refresh replaces the region's `innerHTML`. Every block with a frontend
runtime inside that region — a carousel host, but equally counters, flip cards,
maps — comes back inert.

The refresh now dispatches `dsgo-content-loaded` on `document` with
`detail: { source: 'query-refresh', container: region }`. That is the plugin's
existing bfcache re-init signal, which 25 blocks and extensions already listen
for, so they all pick up query refreshes without a line of new code each.

The mirror-image half is teardown: `slider/view.js` keeps strong references to
its live instances and, on each init pass, destroys any whose element has left
the document. Without it every filter change leaks a set of document-level
`mousemove` / `mouseup` / `resize` / `scroll` listeners plus an
IntersectionObserver.

## D5 — Carousel hardening

Reviewing `slider/view.js` for the Loop Carousel path surfaced defects that
affect authored sliders identically. They are fixed here because a Loop Carousel
is a slider with `slidesPerView: 3` and a variable number of items — precisely
the configuration most of them need to be wrong.

**Responsive `slidesPerView` was parsed and then ignored.** The tablet and
mobile values were read off the data attributes and never used; every JS
derivation took the desktop number. On a phone, a `3 / 2 / 1` carousel cloned
three slides for a one-up loop and treated three slides as visible.

**A multi-slide carousel scrolled past its own end.** The maximum index was
`slides.length - 1` regardless of how many slides were on screen, so a six-slide
three-up carousel kept advancing to index 5, scrolling two slots of blank track
into view with the next arrow still enabled. Bounds are now
`slides.length - ceil(slidesPerView)`, and the dots count resting positions
rather than slides.

**`slidesPerView` above the slide count killed the slider.** The clone loop
started at `originalSlides.length - ceil(slidesPerView)`, which goes negative
with two slides and `slidesPerView: 3`, and `undefined.cloneNode()` threw out of
the constructor. Clamped to the slide count.

**Drag jumped to the first slide.** `previousTranslate` started at `0` and was
never reconciled with the track's real position, so the first `mousemove` wrote
`translateX(delta)` over it. Dragging from slide 3 snapped to slide 0 on the
first pixel. The gesture now starts from the settled offset.

**A vertical scroll changed slides.** Swipe compared only the horizontal delta
against a 50px threshold; a thumb travelling down the page drifts sideways
easily. It now also requires the horizontal delta to beat the vertical one.

**Autoplay stopped after exactly one slide.** `pauseOnInteraction` was checked
inside `goToSlide()`, which autoplay's own advance calls — so with both defaults
on (`autoplay` off by default, but on the moment an author enables it) the
slider advanced once and stopped for good. Only genuine user entry points count
as interaction now.

**Autoplay ran in a background tab and under `prefers-reduced-motion`.** The
reduced-motion path called `stopAutoplay()`, but `observeVisibility()`'s
IntersectionObserver restarted it on the next intersection. Autoplay is now a
controller holding a set of suspend reasons — off-screen, tab hidden, hovered,
focused, reduced motion — so releasing one cannot resume over another, and it
reacts to the OS setting changing rather than only reading it once at init.

**Focus could land inside `aria-hidden` content.** `updateARIA()` marked every
slide but `currentIndex` as `aria-hidden` — wrong on its face for a multi-slide
carousel, where several are visible — and set `tabindex` on the slide, which
does nothing for the links inside it. It now marks the whole visible window and
uses `inert` to take hidden slides' contents out of the tab order with them.

**`beforeunload` made every page with a slider bfcache-ineligible.** Registering
that listener at all disqualifies the page — and the cleanup it ran exists to
serve soft navigation, which is the very thing bfcache does. Moved to `pagehide`,
skipping teardown when the page is entering the cache so the restore finds its
instances live.

**`500ms` became an eight-minute transition.** `transitionDuration` is a CSS
time string; `parseFloat(value) * 1000` is correct for `0.5s` and wrong by three
orders of magnitude for `500ms`, which left `isAnimating` latched and the slider
frozen. Parsed by unit now.

Also: `destroy()` reaches the listeners it never removed (hover, keyboard,
visibility, the IntersectionObserver); `Home`/`End` land on real slides rather
than clones; a `ResizeObserver` on the viewport catches width changes that never
produce a window resize; and clones drop descendant `id` attributes, not just
their own.

`view.js` splits into `view/config.js`, `view/chrome.js`, `view/gestures.js`,
`view/autoplay.js` and `view/scroll-driven.js`, each of which is a module this
plan rewrites anyway. The bundle is one entry as before —
`webpack.config.js` globs `src/blocks/*/view.js`, so the subdirectory is
followed as imports, not emitted separately.

## Out of scope

- **Load more inside `scroll-slides`.** Its panels wrapper is tagged as the item
  container like the slider's track, so an append lands in the right element and
  `aria-busy` is correct — but its pin-spacer height and nav are built once at
  init with no teardown path, so it cannot handle
  `dsgo-query-items-appended` and appended panels would be unreachable. Pinned
  full-viewport scroll plus a Load more button below it is not a composition
  anyone builds; documented rather than built. A host whose layout derives from
  the item count must handle that event, and that is now part of the contract.
- **Grouping inside a carousel.** Group sections now survive a load-more append
  intact rather than being flattened, but interleaved group headers in a
  horizontal track remain a bad shape. Not blocked; documented.
- **Numbered pagination + arrows.** Two navigation systems for one list is a
  design smell, not a defect. No guard.
