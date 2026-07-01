# Shape Divider — Theme Inheritance & Class-Based Rendering Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Re-architect the section block's shape dividers so the SVG lives in CSS (class-based, no serialized markup), the shape can inherit a theme/Style-Kit default with per-section override, and the fill/band colors default sensibly — eliminating the per-visual-change deprecation treadmill.

**Architecture:** Shape dividers stay as `designsetgo/section` attributes (not a separate block). `save()` stops serializing `<svg>` and instead emits an empty, classed placeholder (`is-shape-<slug>`) plus numeric knobs as CSS custom properties; all 23 shapes move into the stylesheet as `mask-image` data-URIs. A new `'inherit'` slug resolves purely in CSS via `var(--wp--custom--designsetgo--shape-divider--type, var(--dsgo-shape--wave))`, which the Style Kit sets in `settings.custom.designsetgo.shapeDivider.type`. Shape fill defaults to the section's background color; the band background defaults to `base`. A new section deprecation (v7) reproduces the old inline-SVG output so existing content migrates silently, and the 9 shipped patterns that embed dividers are rewritten to the new markup.

**Tech Stack:** WordPress block editor (`@wordpress/blocks`, `@wordpress/block-editor`), SCSS (`wp-scripts` build → `build/style-index.css`), Jest (`wp-scripts test-unit-js`) for save/deprecation unit tests, PHP for pattern registration, and the external `native-ui` Style Kit repo for the theme-level default.

**Decisions already locked (from brainstorming):**
- Keep dividers on the section (attributes), not a separate block.
- Class-based rendering; SVG in CSS, not in `save()`.
- Type model: kit sets **one** default shape (`settings.custom.designsetgo.shapeDivider.type`); section inherits it via `'inherit'` or overrides with an explicit slug. (Designed so semantic "Type 1..N" slots can be layered on later without another migration.)
- Color defaults: **shape fill** → section background color; **band background** → `base`. Both remain per-section overridable.

**Key reference files (read before starting):**
- `src/blocks/section/save.js` — current save (inline SVG).
- `src/blocks/section/edit.js:500-647` — editor color controls + ShapeDivider render.
- `src/blocks/section/components/ShapeDivider.js` — current renderer (to snapshot into deprecation).
- `src/blocks/section/components/ShapeDividerControls.js` — inspector control.
- `src/blocks/section/utils/shape-dividers.js` — the 23 SVG shape definitions (source for the CSS masks).
- `src/blocks/section/deprecated.js` — existing `[v6..v1]`; follow this pattern for v7.
- `src/blocks/section/block.json:130-215` — the 16 `shapeDivider*` attributes + descriptions.
- `src/blocks/section/style.scss:523-593` + `editor.scss` — current divider CSS.
- `.claude/CLAUDE.md` → "Deprecations" (isEligible + save + migrate all required) and "Style Imports (MANDATORY)".

---

## Task 0: Branch + commit in-progress work

The working tree already contains completed, verified work from this session (Section_Styles rewrite, dead-CSS removal, `designsetgo/container` default deletion). Get it onto a branch before starting the divider work.

**Step 1:** Create the branch (carries current uncommitted changes along):

```bash
git checkout -b claude/shape-divider-theme-inheritance
```

**Step 2:** Commit the section-styles + cleanup work as its own commit:

```bash
git add includes/features/class-section-styles.php includes/blocks/class-loader.php \
        includes/admin/class-global-styles.php src/styles/block-variations.scss
git commit -m "fix(section-styles): mirror container section styles onto layout blocks; drop dead container styling"
```

**Step 3:** Commit this plan:

```bash
git add docs/plans/2026-07-01-shape-divider-theme-inheritance.md
git commit -m "docs: shape divider theme-inheritance implementation plan"
```

---

## Task 1: Shape mask CSS library (foundation)

Convert the 23 JSX `<path>`/`<ellipse>` shapes in `src/blocks/section/utils/shape-dividers.js` into CSS `mask-image` data-URIs, exposed as `--dsgo-shape--<slug>` custom properties. This is what lets `save()` drop the inline SVG.

**Files:**
- Create: `src/blocks/section/styles/_shape-masks.scss` (the 23 `--dsgo-shape--<slug>` custom properties, each an `url("data:image/svg+xml,...")` built from the existing path data with `viewBox='0 0 1200 120'`).
- Modify: `src/blocks/section/style.scss` — `@use './styles/shape-masks';` (frontend).
- Modify: `src/blocks/section/editor.scss` — same import (editor parity — MANDATORY per CLAUDE.md).

**Step 1:** For each entry in `SHAPE_DIVIDERS`, hand-translate its JSX element(s) to an SVG string and wrap as a mask. Example (`wave`):

```scss
:where(.dsgo-shape-divider) {
	--dsgo-shape--wave: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath d='M0,0 C300,120 900,0 1200,80 L1200,120 L0,120 Z'/%3E%3C/svg%3E");
	/* ...one custom property per shape: tilt, tilt-reverse, curve, peaks, ...23 total... */
}
```

> Use the EXACT path `d` strings from `shape-dividers.js` so shapes render identically. URL-encode `<`,`>`,`#`,`"` (`%3C`,`%3E`,`%23`, `'`).

**Step 2:** Verify the import resolves into the build:

```bash
npm run build && grep -c "dsgo-shape--wave" build/style-index.css
```
Expected: `>= 1`.

**Step 3:** Commit:

```bash
git add src/blocks/section/styles/_shape-masks.scss src/blocks/section/style.scss src/blocks/section/editor.scss
git commit -m "feat(section): add CSS mask library for shape dividers"
```

---

## Task 2: Class-based divider CSS (mask + colors + inherit + flip/front)

Rewrite the divider CSS to paint the shape from the mask library, apply the color defaults, and resolve `inherit`.

**Files:**
- Modify: `src/blocks/section/style.scss:523-593` (the `.dsgo-shape-divider` block) — replace `svg { fill: ... }` approach with mask-based painting.
- Modify: `src/blocks/section/editor.scss` — mirror it.

**Step 1:** New CSS shape/colors model (frontend + editor identical):

```scss
:where(.dsgo-shape-divider) {
	position: absolute;
	left: 0;
	width: var(--dsgo-shape-width, 100%);
	height: var(--dsgo-shape-height, 100px);
	/* band background = the part adjacent to the neighbouring section */
	background-color: var(--dsgo-shape-band, var(--wp--preset--color--base, #fff));
	pointer-events: none;

	/* the shape itself = section background colour, painted through the mask */
	&::before {
		content: "";
		position: absolute;
		inset: 0;
		background-color: var(--dsgo-shape-fill, currentColor);
		-webkit-mask: var(--dsgo-shape-mask) center / 100% 100% no-repeat;
		mask: var(--dsgo-shape-mask) center / 100% 100% no-repeat;
	}
}

/* explicit shapes: one line per slug */
:where(.dsgo-shape-divider.is-shape-wave) { --dsgo-shape-mask: var(--dsgo-shape--wave); }
/* ...22 more... */

/* inherit: theme/kit default, fallback wave */
:where(.dsgo-shape-divider.is-shape-inherit) {
	--dsgo-shape-mask: var(--wp--custom--designsetgo--shape-divider--type, var(--dsgo-shape--wave));
}

:where(.dsgo-shape-divider--top) { top: 0; }
:where(.dsgo-shape-divider--bottom) { bottom: 0; }
:where(.dsgo-shape-divider.is-flip-x)::before { transform: scaleX(-1); }
:where(.dsgo-shape-divider.is-flip-y)::before { transform: scaleY(-1); }
:where(.dsgo-shape-divider.is-flip-x.is-flip-y)::before { transform: scale(-1, -1); }
:where(.dsgo-shape-divider.is-front) { z-index: 2; }
```

> Note: top dividers historically use `scaleY(-1)` to orient the shape. Preserve current orientation by keeping the same default transform semantics the old renderer used (see `ShapeDivider.js` flip logic) so migrated blocks look unchanged.

**Step 2:** Build and confirm the mask + inherit rules landed:

```bash
npm run build && grep -o "is-shape-inherit[^}]*}" build/style-index.css | head
```

**Step 3:** Commit:

```bash
git add src/blocks/section/style.scss src/blocks/section/editor.scss
git commit -m "feat(section): paint shape dividers from CSS masks with fill/band color defaults"
```

---

## Task 3: Attribute schema — add `inherit`, keep the rest

**Files:**
- Modify: `src/blocks/section/block.json:130-215`.

**Step 1:** No new attributes are strictly required — `shapeDividerTop`/`shapeDividerBottom` stay `string` (default `""`). Their value space simply gains `'inherit'` (a runtime value, not a schema change). Update the two `schemaMetadata.attributeDescriptions` entries to note the new accepted values: `"" (off), "inherit" (theme default), or a shape slug`.

**Step 2:** Confirm block still registers:

```bash
npm run build && npx wp-env run cli wp eval 'echo WP_Block_Type_Registry::get_instance()->is_registered("designsetgo/section") ? "OK" : "MISSING";'
```
Expected: `OK`.

**Step 3:** Commit:

```bash
git add src/blocks/section/block.json
git commit -m "docs(section): document inherit value for shape divider attributes"
```

---

## Task 4: New class-based renderer + save.js + edit.js

Replace inline-SVG rendering with the classed placeholder in BOTH edit and save (shared component).

**Files:**
- Rewrite: `src/blocks/section/components/ShapeDivider.js` — return an empty, classed `<div>` (no `<svg>`).
- Modify: `src/blocks/section/save.js:33-163`.
- Modify: `src/blocks/section/edit.js:500-647`.
- Test: `src/blocks/section/test/save.test.js` (new).

**Step 1 (TDD): Write the failing save test.** Jest via `wp-scripts test-unit-js`.

```js
// src/blocks/section/test/save.test.js
import { createBlock, serialize, registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

registerBlockType( metadata.name, { ...metadata, save } );

test( 'save emits class-based divider, no inline SVG', () => {
	const block = createBlock( metadata.name, {
		shapeDividerTop: 'wave',
		shapeDividerTopHeight: 80,
	} );
	const html = serialize( block );
	expect( html ).toContain( 'dsgo-shape-divider--top' );
	expect( html ).toContain( 'is-shape-wave' );
	expect( html ).not.toContain( '<svg' );
} );

test( 'save emits is-shape-inherit for inherit value', () => {
	const block = createBlock( metadata.name, { shapeDividerBottom: 'inherit' } );
	expect( serialize( block ) ).toContain( 'is-shape-inherit' );
} );
```

**Step 2:** Run, verify it FAILS:

```bash
npm run test:unit -- src/blocks/section/test/save.test.js
```
Expected: FAIL (`<svg` still present / no `is-shape-*`).

**Step 3: Implement the new renderer.** `ShapeDivider.js` returns:

```jsx
export default function ShapeDivider( { shape, position, height, width, flipX, flipY, front, fillColor, bandColor } ) {
	if ( ! shape ) return null;
	const className = [
		'dsgo-shape-divider',
		`dsgo-shape-divider--${ position }`,
		`is-shape-${ shape }`,
		flipX && 'is-flip-x',
		flipY && 'is-flip-y',
		front && 'is-front',
	].filter( Boolean ).join( ' ' );
	const style = {
		'--dsgo-shape-height': `${ height || 100 }px`,
		'--dsgo-shape-width': `${ width || 100 }%`,
		...( fillColor && { '--dsgo-shape-fill': fillColor } ),
		...( bandColor && { '--dsgo-shape-band': bandColor } ),
	};
	return <div className={ className } style={ style } aria-hidden="true" />;
}
```

Wire `save.js` and `edit.js` to compute `fillColor` (explicit `shapeDivider*Color` || section background color) and `bandColor` (explicit `shapeDivider*BackgroundColor` || leave unset so CSS falls back to `base`). Remove the inner-padding "clear" hack only if the new CSS handles spacing; otherwise keep it (verify visually).

**Step 4:** Run, verify PASS:

```bash
npm run test:unit -- src/blocks/section/test/save.test.js
```
Expected: PASS.

**Step 5:** Commit:

```bash
git add src/blocks/section/components/ShapeDivider.js src/blocks/section/save.js src/blocks/section/edit.js src/blocks/section/test/save.test.js
git commit -m "feat(section): render shape dividers class-based (no serialized SVG)"
```

---

## Task 5: Editor control — add "Theme default (inherit)" option

**Files:**
- Modify: `src/blocks/section/components/ShapeDividerControls.js`.
- Modify: `src/blocks/section/edit.js:500-599` (the color controls block — relabel to Fill / Band; new defaults are visual, no attribute change).

**Step 1:** Add `{ label: __( 'Theme default', 'designsetgo' ), value: 'inherit' }` as the first option (after "None") in the top and bottom shape `SelectControl`s (options come from `getShapeDividerOptions()`).

**Step 2:** Relabel the two color controls: `shapeDivider*Color` → "Shape fill", `shapeDivider*BackgroundColor` → "Band background", and update the help text to state the defaults (section background / base).

**Step 3:** Build + smoke test in the editor:

```bash
npm run build
```
Then in wp-env editor: insert a Section, enable a top divider, confirm the "Theme default" option and that selecting a shape renders live. (Manual — note result.)

**Step 4:** Commit:

```bash
git add src/blocks/section/components/ShapeDividerControls.js src/blocks/section/edit.js
git commit -m "feat(section): add theme-default (inherit) option + fill/band color labels"
```

---

## Task 6: Deprecation v7 (silent migration of existing content)

Existing posts/patterns carry the old inline-SVG markup. Add a deprecation that reproduces it so WP migrates silently (no "Attempt Recovery").

**Files:**
- Modify: `src/blocks/section/deprecated.js` — add `v7` as the newest entry; export `[v7, v6, v5, v4, v3, v2, v1]`.
- Test: `src/blocks/section/test/deprecated.test.js` (new).

**Step 1:** Snapshot the CURRENT (pre-Task-4) `save()` output and `ShapeDivider` into a local `V7ShapeDivider` inside `deprecated.js` (mirror how v4/v5/v6 embed `V4ShapeDivider`). The v7 object:

```js
const v7 = {
	supports: sharedSupports,
	attributes: { /* current attribute snapshot incl. all 16 shapeDivider* */ },
	isEligible( attributes, innerBlocks, { innerHTML } ) {
		// Old markup serialized an <svg> inside .dsgo-shape-divider.
		return !! innerHTML && innerHTML.includes( 'dsgo-shape-divider' ) && innerHTML.includes( '<svg' );
	},
	save( { attributes } ) { /* verbatim old inline-SVG save */ },
	migrate( attributes ) {
		return attributes; // names unchanged; markup-only change
	},
};
```

**Step 2 (TDD):** Test that old markup is eligible and migrates to valid new output:

```js
// src/blocks/section/test/deprecated.test.js
import { parse } from '@wordpress/blocks';
// register block with current save + deprecations, parse a known-old fixture
// string (copy the serialized markup from patterns/contact/contact-consultation-form.php),
// assert the parsed block is valid (no isInvalid) and has shapeDividerTop === 'wave'.
```

**Step 3:** Run:

```bash
npm run test:unit -- src/blocks/section/test/deprecated.test.js
```
Expected: PASS (block valid, attribute preserved, no recovery prompt).

**Step 4:** Commit:

```bash
git add src/blocks/section/deprecated.js src/blocks/section/test/deprecated.test.js
git commit -m "feat(section): add v7 deprecation to migrate inline-SVG dividers to class-based"
```

---

## Task 7: Rewrite the 9 shipped patterns to new markup

These `.php` patterns contain the full old markup (inline SVG). Update each section's serialized HTML to the new class-based output so fresh inserts don't rely on the deprecation.

**Files (each: replace the divider markup in the serialized `content` string):**
- `patterns/contact/contact-consultation-form.php:16-17`
- `patterns/homepage/homepage-luxury-realestate.php:80,346`
- `patterns/homepage/homepage-modern-saas.php:16,180,432`
- `patterns/cta/cta-saas-transform.php:16`
- `patterns/cta/cta-gradient.php:16`
- `patterns/cta/cta-banner-gradient.php:16`
- `patterns/gallery/gallery-property-showcase.php:16`
- `patterns/content/content-team-stats.php:16`
- `patterns/hero/hero-modern-saas.php:16`

**Step 1:** For each, replace the `<div class="dsgo-shape-divider ..."><svg>...</svg></div>` with the new empty classed div, e.g.:

```html
<div class="dsgo-shape-divider dsgo-shape-divider--top is-shape-wave is-flip-y" style="--dsgo-shape-height:80px;--dsgo-shape-width:100%" aria-hidden="true"></div>
```

Keep the block-comment JSON attributes as-is (they already carry `shapeDividerTop:"wave"` etc.).

**Step 2:** Verify each pattern parses valid (insert in editor OR):

```bash
npm run build
npx wp-env run cli wp eval 'do_action("init"); $r = WP_Block_Patterns_Registry::get_instance()->is_registered("designsetgo/contact/contact-consultation-form"); echo $r ? "OK" : "MISSING";'
```

**Step 3:** Commit:

```bash
git add patterns/
git commit -m "refactor(patterns): update shape-divider markup to class-based rendering"
```

---

## Task 8: Style Kit default (external repo — `native-ui`)

Give kits a default divider shape. **This is in `/Users/jnealey/github-local/airo-wp/wp-site-designer-mu-plugins/packages/native-ui` — a separate repo/PR.** No plugin change needed; the CSS already reads `--wp--custom--designsetgo--shape-divider--type`.

**Files:**
- Modify: chosen `src/data/styleKitFragments/*.ts` fragments — add under `settings.custom.designsetgo`:

```ts
shapeDivider: { type: 'var(--dsgo-shape--wave)' }, // kebab-cased → --wp--custom--designsetgo--shape-divider--type
```

> The value is a reference to a plugin-owned shape var, so the plugin keeps ownership of the SVG geometry. Boxy kits can point at a subtle shape (`var(--dsgo-shape--tilt)`); if a kit omits it, the section CSS falls back to `wave`. No `types.ts` change (the `custom` record is open-ended) and no `class-style-kit.php` change (generic `array_replace_recursive` passes it through).

**Step 1:** Add to 1 fragment first (e.g. `modern.ts`), build native-ui, apply the kit in wp-env, and confirm a Section with `shapeDividerTop:"inherit"` renders that shape.

**Step 2:** Commit in that repo separately.

---

## Task 9: Full verification (pre-PR)

**Step 1:** Build + all linters (per CLAUDE.md pre-commit):

```bash
npm run build && npm run lint:js && npm run lint:css && npm run lint:php
```

**Step 2:** Unit tests:

```bash
npm run test:unit -- src/blocks/section
```

**Step 3:** Confirm dead code is gone + new CSS present:

```bash
grep -c "<svg" build/blocks/section/style-index.css || true   # divider CSS should not need inline svg
grep -c "dsgo-shape--wave" build/style-index.css               # >= 1
```

**Step 4:** Manual matrix (record results): editor + frontend, top-only / bottom-only / both, explicit shape vs `inherit`, flipX/Y, front/back, fill+band overrides, and — critically — **open an existing post that used the old divider and confirm silent migration** (no "Attempt Recovery"). Also confirm background-video mutual-exclusivity notice still fires.

**Step 5:** Confirm reduced-motion / overlay coexistence: a section with both an overlay and a divider still layers correctly.

**Step 6:** Finish per superpowers:finishing-a-development-branch (PR or merge).

---

## Risks & Notes

- **Overlay pseudo-element:** the section overlay and the divider both use absolute/pseudo layers — verify z-index ordering (`is-front` vs overlay). Confirmed the divider is a real child element (not `::before` on the section), so no pseudo-element collision, but check stacking.
- **Gradient/image section backgrounds:** shape fill defaults to the section's *solid* background color; if the section uses a gradient/image, fill falls back to `currentColor`. Acceptable for v1 — note in the UI help text.
- **`mask` browser support:** modern browsers support `mask`/`-webkit-mask`; matches CoBlocks' baseline. No IE.
- **Do NOT** model divider shape as an `is-style-*` block style variation — the section already uses `is-style-section-*` (from the section-styles work) and needs independent top+bottom shapes, which a single variation class can't express.
- **Deprecation ordering:** v7 must be newest (first in the exported array). Its `isEligible` must be specific (`dsgo-shape-divider` + `<svg`) so it only claims genuinely-old blocks.
