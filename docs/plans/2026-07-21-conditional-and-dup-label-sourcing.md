# Fix: source labels for the 4 deferred blocks (deprecation-based)

Follow-up to PR #482. Those 5 "always-present label" blocks were fixed by adding
`source` to `block.json` with no markup change. The remaining 4 blocks need a
`save.js` markup change **and** a deprecation, in two shapes.

## Shape A — redundant second copy in the markup (plain markup deprecation)

### form-builder / `submitButtonText`
- Today: rendered as the `<button class="dsgo-form__submit">` text AND as
  `data-submit-text` on the wrapper. `data-submit-text` is **unused** (view.js
  restores the label from `submitButton.textContent`, view.js:387).
- Fix: remove `data-submit-text` from `save.js`; source `submitButtonText` from
  `.dsgo-form__submit` (`source: 'text'`).
- Deprecation: snapshot the current save (with `data-submit-text`) + static
  `submitButtonText`; `migrate` identity. `apiVersion: 3` (trap 0). No isEligible
  (markup change → invalid block → save-matching).

### countdown-timer / `completionMessage`
- Today: rendered as `data-completion-message` on the wrapper AND as the text of
  the hidden `.dsgo-countdown-timer__completion-message` div. The div is
  `display:none` and JS overwrites its text from `data-completion-message` on
  completion (view.js:135), so the wrapper's data-attribute copy is redundant.
- Fix (as shipped): keep the message as the div's text and source
  `completionMessage` from it (`source: 'text'`, selector
  `.dsgo-countdown-timer__completion-message`) — a single source of truth that
  stays translatable. Remove the duplicate `data-completion-message` attribute
  and simplify view.js to just reveal the already server-rendered div.
  (An earlier draft sourced from the `data-completion-message` attribute via
  `source: 'attribute'`, but WordPress does not resolve a root-wrapper attribute
  source here, and it would have made the message untranslatable.)
- Deprecation: snapshot the old markup (div text + `data-completion-message`),
  `completionMessage` sourced from the div; `migrate` identity. `apiVersion: 3`.
  No isEligible.

## Shape B — conditionally-rendered label (data-preserving isEligible deprecation)

### card (`title`/`subtitle`/`bodyText`/`badgeText`) + table-of-contents (`titleText`)
- Today: element rendered only when a separate `show*` toggle is true
  (`{showTitle && title && ...}`). `show*` all default `true`.
- Fix: render the element whenever the **text** is non-empty; add an
  `--hidden` class (CSS `display:none`) when the toggle is off. Source the attr.
  - `{ title && <RichText.Content className={clsx('dsgo-card__title', !showTitle && 'dsgo-card__title--hidden')} ... /> }`
- New content is fully fixed (element present when text set → sourced →
  survives toggles).
- Deprecation (preserve existing hidden-but-filled content): the block stays
  VALID under the new def for old `show:false + text` content (element absent →
  sourced ""), so a markup deprecation won't fire — use **isEligible** keyed on
  stored markup: fire when the comment has non-empty text for a field but the
  field's element is absent from `blockNode.innerHTML`. `migrate` reads the text
  from the (static) comment attribute and returns it. `apiVersion: 3`.
  - card has NO `deprecated.js` yet (and `index.js` doesn't import one) — create
    it + wire it up.
  - table-of-contents `deprecated.js` is an empty stub — fill it.

## Verify
- Extend `tests/unit/translation-resilience.test.js`: translation round-trip +
  legacy-heal for all 4; toggle-off round-trip for card/TOC; deprecation
  migration (old hidden content → text preserved) for card/TOC.
- `deprecations-isEligible.test.js` must stay green (no deprecation may claim
  current output; lossless round-trip).
- Build + lint + live editor spot check.
