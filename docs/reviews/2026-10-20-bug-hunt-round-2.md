# Bug hunt, round 2 — 2026-10-20

Branch `claude/bug-hunt-fixes` @ 7808b79a (PR #652), WP 7.1.2, TT5, wp-env on :9451.
Follows [2026-10-19-bug-hunt.md](2026-10-19-bug-hunt.md); nothing listed there is repeated here.

Method: six read-only reviewers on the areas round 1 didn't reach (interactive blocks II,
content blocks + editor UX, extensions II, admin / Draft Mode / Abilities, a cross-cutting
deprecation / i18n / a11y sweep, and forms + query leftovers including a regression review of
this branch's own fixes), plus live testing with Playwright. Tags as before:

- **[live]** reproduced on the running site
- **[code]** confirmed by reading the code path myself
- **[review]** reported by a reviewer from a code trace; not independently verified

Checked clean this round: all 51 deprecation files (apiVersion, isEligible, supports), block.json
`style`/build targets, `type` on every saved `<button>`, `alt` on every saved `<img>`, double-init
guards in view scripts, no `innerHTML` with attribute data, the editor opening the three baseline
pages with 0 invalid blocks and no dirty state, form header injection / email escaping / webhook
SSRF, Abilities permission callbacks and `edit_css` gating, Custom CSS kses, layout hash parity,
Chart, Flip Card, Hotspot and countdown timezone/DST maths. No regressions found in this
branch's fixes **except** Tier 1 #10.

## Status — fixed on `claude/bug-hunt-fixes`

Every Tier 1 item and nearly all of Tier 2/3 are fixed. Build, eslint, stylelint, phpcs and
PHPStan are clean (apart from errors already on main); 4,743 Jest and 2,423 PHPUnit tests
pass. Re-verified in the browser on WP 7.1.2 / TT5 with three QA pages: one saved with the
old `save()` output (2911), one with the new (2914), and motion/tooltips (2924).

Verified live:
- **Draft Mode:** a draft of page 1711 has 0 invalid blocks (was 16). Publishing the copy through core REST leaves it a draft. A changed live page gets a 409 conflict.
- **Slider:**
  - In RTL every slide comes into view, it wraps correctly, and the arrows are mirrored.
  - The autoplay pause button holds through hover and resumes once focus leaves the slider.
  - Clones are `inert`, and Tab never lands in a clone or hidden slide.
  - Scroll-driven mode under reduced motion becomes an ordinary slider.
- **Sticky Header:** a per-part header is sticky with the global setting off. It hides on scroll down, shows on scroll up, and the Home key clears the hidden state.
- **Animations:** hover and click triggers are visible at rest and after interacting, and every animated block shows in print.
- **Timeline:** existing content renders red diamond markers without a re-save.
- **Countdown:** with Days hidden it shows 70 hours, in the editor and on the front end.
- **TOC:** an H4 after an H2 is kept and nested, Cyrillic and accented ids work, headings inside a header Group are listed, and links carry no aria-label.
- **Card and Modal Trigger:**
  - Old markup opens with 0 invalid blocks.
  - A hidden CTA keeps its button through a save.
  - A legacy Card no longer dirties the post on open.
  - On existing pages the preset padding and badge are repaired at render time.
- **Interactions:** a space typed into a field inside the clickable group is kept, and Enter on an inner link navigates.
- **Text Reveal:** links keep their accessible names.
- **Phone field:** `20-7946-0958` is stored next to `+1`. A successful submit announces its message once.
- **Form throttle:** unknown form ids return 404 sixty times, then 429.
- **Query random order:** Load more yields all 22 posts exactly once, and each page view reshuffles.
- **Image Accordion:** panels are groups named by their headings.
- **Marquee:** a vertical wheel scrolls the page.
- **Map:** a failed geocode shows a message, not a New York pin, and a double click on privacy mode loads once.
- **Text Path:** the pause button works.
- **Comparison Table:** the tooltip opens on tap at 390px and is not clipped.
- **Progress bar:** honours reduced motion.
- **Sticky Sections:** stacking applies after resizing across the breakpoint.
- **CSS defer:** exact handle matches only.
- **Settings page:** shows an error state with Retry, and saving sends only the changed section.

Found and fixed during validation:
- **Slider pause button:** it sat on the Next arrow in short sliders. It now sits just inside the arrow.
- **RTL horizontal scroll:** RTL pages with a form or slider were about 10,000px wide, because the honeypot and the slider announcer used `left:-9999px`.
- **Random-order Load more:** it still repeated posts, because `designsetgo_query_render_region()` dropped the seed.
- **Form success message:** screen readers announced it twice. This was already the case on main.

Deliberately left:
- **Frontend asset manifest for editors:** it is needed for soft navigation.
- **Divider `align` → justification:** this is a migration. Do it as its own PR.
- **`Layout_Renderer`:** it still targets the first tag. The other three injectors share the mirror's `root_processor()`.
- **Nominatim `User-Agent`:** a browser can't set it. Address-only maps would need server-side or edit-time geocoding.
- **Repeated group headers across pages:** inherent to grouping each page separately.

Behaviour changes to note:
- **Random order:** it is fixed for one page view, across its Load mores.
- **Custom CSS:** `@import` is stripped everywhere.
- **Star Rating:** AggregateRating needs the count shown.
- **Users preview:** editors without `list_users` see only users with published posts.

## Tier 1 — fix first

| # | Area | Finding | Evidence |
|---|------|---------|----------|
| 1 | Draft Mode | **Creating a draft breaks blocks, and publishing it ships the damage.** `sanitize_block_content()` runs on the editor's content for every user and strips attributes the blocks' `save()` emits: `aria-modal`, `aria-atomic`, `aria-valuenow/min/max`, `aria-roledescription`, the honeypot's `aria-hidden`, and more. The draft opens with "Attempt Recovery", and merging it writes the stripped markup to the live page. `includes/admin/class-draft-mode-rest.php:291, 534-538` | [live] smoke page 1711 run through the sanitizer and parsed by `wp.blocks.parse`: **0 → 16 invalid blocks** (slider ×2, slide ×5, timeline-item ×3, modal, form-builder, progress-bar, text-path, comparison-table) |
| 2 | Slider | **Blank on RTL sites.** `applySlideTransition` always writes `translateX(-n·w)`, and the track starts at the right edge in RTL, so no slide is ever in view. Drag/swipe directions aren't mirrored either. There's no RTL handling anywhere in the slider. `slider/view.js:476-486`, `view/gestures.js:47-60, 128-132` | [live] `dir="rtl"`: every slide 0% visible on load and after each Next |
| 3 | Sticky Header | **Per-header controls do nothing.** Shadow, shrink, hide-on-scroll, background and skip-top-bar are applied only through `blocks.getSaveContent.extraProps`. `core/template-part` is dynamic (saves `null`), so they never reach markup. Nothing in PHP reads `dsgoSticky*`, and the new dynamic-block mirror doesn't cover them. Only the global setting works. `src/extensions/sticky-header-controls/index.js:106-160` | [code] |
| 4 | Block Animations | **Hover and Click triggers hide the block at rest.** `.has-dsgo-animation:not(.dsgo-entrance-active)` is `opacity:0`. Hover adds the class on `mouseenter` and removes it on `mouseleave`; click removes it after `duration+100ms`. A card set to "On Hover" is invisible until hovered and never shows on touch. Separately, every animated block is invisible without JS and in print. `animations.scss:70-77`, `frontend.js:289-325` | [code] |
| 5 | Timeline | **Marker style and marker/border colour never reach the front end.** `timeline-item/save.js:108-123` reads them from context, which `save()` doesn't get, so every stored marker is a primary-blue circle while the editor shows diamond/square and custom colours. Fix like Icon List: a `render_block` pass using `$instance->context`, with no deprecation. | [code] |
| 6 | Countdown | **Hidden units don't roll up.** Turn Days off with 3d 5h left and the timer reads "05 Hours", not 77. `calculateTimeRemaining` always mods by the next unit, and nothing reads the `data-show-*` attributes `save.js:67-70` writes. `utils/time-calculator.js:76-82`, `view.js:19-36` | [code] |
| 7 | Table of Contents | **Hierarchical mode silently drops headings that skip a level** (H2 → H4, or H3 off in the settings), and drops a leading heading deeper than the shallowest one. `buildHierarchy` recurses at `level+1` and its `else { i++ }` discards anything deeper. Skipped levels are common in real content. `utils/hierarchy.js:22-60` | [code] |
| 8 | Card | **Turning "Show CTA" off deletes the CTA's inner blocks on save.** `{showCta && <div {...innerBlocksProps}/>}` in `save.js:238` and `edit.js:340` means the inner blocks aren't serialized. Toggling it back on after a reload gives an empty CTA. Title, body and badge use `--hidden` classes; the CTA should too (needs a deprecation). | [code] |
| 9 | Interactions | **A click interaction swallows Space and Enter inside the block.** The container gets `role="button"`, and the document `keydown` handler resolves `closest('[data-dsgo-interactions]')` and `preventDefault()`s Enter/Space. A Group with a click interaction that contains a field or link can't take a typed space, and Enter on the link doesn't navigate. Act only when `e.target` is the element itself. `interactions/frontend.js:182-190` | [code] |
| 10 | Phone field (**this branch**) | **International format now always prefixes "+", even with the country-code select shown (the default).** A national `555 123 4567` is stored as `+555-123-4567` next to a `+1` select. Before #652 the same input failed validation; now it saves bad data silently. Skip the `+` (or don't enforce it) when `showCountryCode` is on. `form-phone-field/view.js` `formatInternational`, `render.php:43, 81-85` | [code] — **resolve before merging #652** |
| 11 | Query relationship | **PHP object injection path.** `maybe_unserialize()` on a `get_post_meta()` string, with no `allowed_classes`. Core double-serializes a serialized-looking string a contributor stores, so `get_post_meta` returns the raw `O:…` string and this call instantiates it. Exploitability needs a gadget class. Use `unserialize( $v, [ 'allowed_classes' => false ] )` or drop the branch. `src/blocks/query/render-relationship.php:118` | [code] |
| 12 | Forms | **Unauthenticated full-table `LIKE` scan per request.** A random `formId` misses the cache and rescans `wp_posts`, and this runs before the honeypot, timing and rate-limit checks. The per-form limiter never sees unknown ids. Move the global rate limit ahead of the lookup, or cache misses briefly. `class-form-handler.php:330-379, 1498-1507` | [code] |

## Tier 2 — real user-facing bugs

### Interactive blocks
- **Slider clones are `aria-hidden` but focusable.** Clones are skipped when `inert` is toggled, and only their ids are stripped (the comment claims that removes them from the tab order; it doesn't). Any link or button in a slide gets a focusable copy inside an `aria-hidden` subtree. `slider/view.js:126-138, 638-649` — [code]
- **Slider autoplay has no pause/stop control** (WCAG 2.2.2). Hover pause does nothing on touch, and with Pause on hover off there's no control at all. `view/autoplay.js`, `view/chrome.js` — [code]
- **Scroll-driven slider with reduced motion** still builds the tall pinned spacer, but `updatePosition()` returns early, so the visitor scrolls through a long section and only ever sees slide 1. It also calls `matchMedia` on every scroll frame. `view/scroll-driven.js:28-55, 108-110` — [code]
- **Scroll Marquee hijacks vertical page scroll.** A mostly-vertical trackpad swipe with `|deltaX| ≥ 1` is captured and `preventDefault`ed. Capture only when `|deltaX| > |deltaY|`. `scroll-marquee/view.js:244-265` — [code]
- **Image Accordion panels are `role="button"` named "Image panel N".** That replaces the panel's heading and caption for screen readers, and a link inside a button isn't exposed. `image-accordion/view.js:115-127` — [live] a11y tree shows `button "Image panel 1"`
- Image Accordion: `isTouchDevice` (any `maxTouchPoints`) disables hover expand on touch laptops and iPads with a mouse; the condition `(hover && !isTouch) || hover` is dead logic. `view.js:130-160` — [review]
- **Map shows New York when geocoding fails.** On a Nominatim 429, an error or no match, it falls back to NYC with a normal-looking pin. The visitor's browser also geocodes on every view, and the browser ignores the `User-Agent` header set for Nominatim's policy. Geocode at edit time, or show an error. `map/handlers/DSGMap.js:98-116`, `utils/geocoding.js` — [review]
- Map: a double click in privacy mode during the geocode `await` initialises Leaflet twice ("container already initialized"), and the error string is untranslated. `DSGMap.js:62, 83-143` — [review]
- Slider: loop-off autoplay stalls on the last slide forever. `aria-roledescription="slider"` collides with the ARIA slider role ("carousel" is the APG term), and `render.php:184` could translate its fallback label but doesn't. — [review]
- Text Path: perpetual rAF motion with no pause and no off-screen stop; the text length is measured before web fonts load. `text-path/motion.js` — [review]
- Sticky Sections: init is decided once (narrow or reduced-motion load never wires up later), and the z-index CSS covers only 20 sections. — [review]

### Content blocks
- **Modal Trigger: preset padding written raw** (`padding-top:var:preset|spacing|40`, which the browser drops). Icon Button converts the same values; Modal Trigger doesn't. `modal-trigger/save.js:57-75`, `edit.js:137-147` — [code] (needs a deprecation)
- **Product Showcase Hero: a password-protected product renders nothing.** `render.php:64` *returns* the password form from an included file whose bottom-line call isn't echoed. Backorder products also read "In stock", and the editor preview shows ¥15.00 for ¥1500 (`currency_minor_unit || 2`). — [code] for the password form, [review] for the rest
- **Card badge** is `role="status"` with `aria-label="Badge"` by default, so a "Sale" badge is announced as "Badge" and is a live region. `card/save.js:138-143` — [review]
- **Progress Bar** has no reduced-motion handling (infinite stripe animation, inline width transition), and an animate-on-scroll bar is empty without JS. — [review]
- **Star Rating JSON-LD** emits `ratingCount` even when Show count is off, and the count control is capped at 1000. — [review]
- **Table of Contents**: any URL hash scrolls to top and nothing scrolls back when the TOC has no headings. Generated ids can collide with later existing ids. Non-Latin headings all become `section-N`. Headings inside `header`/`footer`/`aside` Groups are excluded. — [review]
- **Divider** still uses `supports.align: left/center/right` (the positioning anti-pattern in CLAUDE.md). — [review]
- Comparison Table: tooltip text is announced twice (name and description) and is unreachable on touch. — [review]
- Product Categories Grid: counts use raw `$term->count`, not WooCommerce's visibility-aware, children-padded count. — [review]
- Icon: an unlabelled meaningful icon is announced by its slug ("Circle Check"). Icon-only Icon Button has no accessible-name guard, and Icon Gap accepts negative values. — [review]
- Grid: `repeat(N, 1fr)` instead of `minmax(0,1fr)` lets long content force a phone-width overflow. — [review]

### Extensions
- **Text Reveal** wraps every text node in `aria-hidden` spans, including link text, and puts `aria-label` on a `<p>`. Links lose their names. `text-reveal/splitter.js` — [review]
- **Reveal on Hover**: no `:focus-within` (keyboard focus lands on invisible children), the collapse clips at 1000px, it never applies to dynamic children, and it's registered on all blocks with no allowlist. — [review]
- **Sticky header** leaves a 46px gap on phones ≤600px when logged in (the admin bar isn't fixed there). Per-header hide-on-scroll is gated on the global setting, and a jump to the top can leave the header hidden. — [review]
- **Draft Mode publishes after a failed save.** `await savePost()` resolves on failure, so stale draft content is merged and the editor navigates away. `usePublishIntercept.js:58`, `useDraftModePanel.js:178` — [review]
- Custom CSS `\bselector\b` corrupts `.my-selector-list`, `--selector-gap` and similar. `@import` is stripped in the editor preview but allowed on the front end. — [review]
- Animations: elements shorter than the 100px root-margin offset at the very top or bottom of a page never intersect and stay invisible. — [review]
- Bindings: a stored `__dsgo_post_id` arg is honoured from post content, so an author can point a binding at any post (the viewer-based gates still apply). — [review]
- Schema JSON-LD ignores `dsgoVisibility`, so hidden FAQ items are still in `FAQPage`. — [review]

### Forms / Query
- **Query result-count announcement is English-only.** `view-helpers.js:255-259` has no `__()`, and it's the only text in the `role="status"` region. — [code]
- Query sort: a URL sort override on a meta-ordered block keeps `meta_key`, so posts without the key vanish. A meta sort option on a non-meta block silently falls back to date. `rand` combined with offset pagination duplicates or skips items. `render-posts.php:355-367, 570-580` — [review]
- Query `groupBy`: grouped items render after `wp_reset_postdata()` (the global `$post` is the page). Groups split across pages repeat their headers, and multi-term posts render twice. — [review]
- Filter index: rebuild TRUNCATEs first (empty counts during the rebuild), the 5-minute lock TTL is shorter than a big rebuild, CLI reports "locked" as success, and there's no `delete_term` hook. — [review]
- Forms: the rate limit is check-then-increment-on-success (parallel requests bypass it, failures never count). An empty `timestamp` skips the fill-time check, and client-vs-server clock skew over 3s 429s every submit. — [review]
- `/query/preview` gives any contributor every user's `display_name` and non-public taxonomy terms. — [review]
- Hard-coded English: modal gallery "Next"/"Previous" visible text (`modal/view.js:1193`; label-in-name mismatch), phone "Country Code" label, checkbox `aria-label="required"`, form fallback error, comparison table "Yes"/"No". — [review]

### Admin / Draft Mode / Abilities
- **Draft Mode publish has no server-side guard.** Quick Edit, bulk edit, REST or scheduling publishes the draft as a second live page. Publishing is last-write-wins (live-page edits and meta made after the draft are overwritten or deleted, with no warning). Template, slug, parent, menu order and terms changed in a draft are silently dropped. — [review]
- **Settings page**: if `GET /settings` fails, the UI renders, toggles throw, and Save POSTs `null` and reports "Settings saved successfully!". It also sends the whole blob (clobbering other tabs and abilities) and ignores the sanitized response. `src/admin/components/Settings.js:26-71` — [review]
- **CSS defer uses substring matching**: `tab` matches table-of-contents and comparison-table, `divider` matches section-divider, `slide` matches scroll-slides. These load `media=print onload`, causing FOUC and failing under strict CSP. `includes/core/class-assets.php:459-480` — [code]
- Abilities: `batch-update-blocks` skips nested matches under a non-matching parent, can't match default-valued attributes, and rewrites the post even when 0 blocks changed. `block_client_id` targeting can never match. `find-blocks` silently scans only the newest 100 posts. — [review]
- Pattern category transient isn't keyed by locale (titles are cached in whichever locale warmed it). — [review]
- Abilities CSS sanitizer decodes escapes (`.md\:flex`, `content:"a\"b"`) and `str_replace('selector')`s the whole string. — [review]
- Draft preview requires only `edit_pages`, not `edit_post` on that page, and silently caps at 100 pages. — [review]
- Dead settings `draft_mode.auto_save_*`; numeric settings have no server-side max. — [review]
- Editors get `designsetgo-frontend` plus the asset manifest JSON on every page, even ones with no DSGo blocks. — [review]

## Tier 3 — low / hygiene
- Slide's `editorStyle` 404s and `slide/editor.scss` is dead (never imported); `blobs/editor.scss` is likewise orphaned. — [review]
- Listener leaks on soft nav: image-accordion `hashchange`, marquee `scroll`/`resize`, countdown `visibilitychange` and per-timer observers, text-reveal `scroll`. — [review]
- Four render-time injectors (animation defaults, interactions, style binding, layout) still target the first tag without the dynamic-block mirror's single-root and non-rendering-tag guards. — [review]
- The new mirror's `has_single_root()` bails on any HTML API "unsupported" result. On WP 6.7/6.8 that may skip `core/search`, `core/calendar` and others, so recheck on 6.7. — [review]

## Notes
- The logo-row overflow fix in #652 changed the pattern source only. Pages already built from
  those patterns (e.g. smoke page 1711: 465px document at a 375px viewport) still scroll
  sideways until the row is re-inserted or its `nowrap` turned off. [live]
- The TOC rewriting the URL hash while scrolling is deliberate (tied to Smooth Scroll), not a bug.
- Corrected reviewer claim: Draft Mode does **not** strip `type`/`name`/`value` from form inputs
  (verified); the real damage is the ARIA and other attributes in Tier 1 #1.
