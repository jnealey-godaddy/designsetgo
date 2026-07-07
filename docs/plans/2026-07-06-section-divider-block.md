# Section Divider Block Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a standalone `designsetgo/section-divider` block — a placement-flexible, solid-filled shape divider that reuses the section block's shape library and resolves its shape, height, and fill color from theme.json tokens by default.

**Architecture:** The section block paints 23 shapes purely in CSS via `mask-image` off `:root`-scoped `--dsgo-shape--{slug}` vars, with `is-shape-inherit` resolving `--wp--custom--designsetgo--shape-divider--type`. We extract that mask library + slug→class assignment + inherit resolver into shared SCSS partials, then build a new single-layer (solid fill, not knockout) block on top. Blocks auto-register via webpack glob (`src/blocks/*/index.js` + `style.scss`) and PHP `class-loader.php` globbing `build/blocks/*`, so no manual wiring.

**Tech Stack:** WordPress block API v3, `@wordpress/scripts` (webpack), SCSS, Jest (`@wordpress/jest-preset-default`), React (editor).

**Design doc:** `docs/plans/2026-07-06-section-divider-block-design.md`

**Conventions (from CLAUDE.md):** Tabs for JS/SCSS/PHP. `dsgo-` CSS prefix. `apiVersion: 3`, `textdomain: "designsetgo"`. `useBlockProps()`. Theme-3 inspector IA via `<DsgoInspectorPanel>`. Color via `ColorGradientSettingsDropdown`. Frontend styles MUST be imported in both `style.scss` and `editor.scss`. Max 300 lines/file. No `console.log`.

---

## Task 1: Extract the shared shape-mask CSS primitives

Split the block-agnostic parts of the section's shape CSS (mask library, slug list, per-slug `is-shape-{slug}` mask assignment, `is-shape-inherit` resolver) into shared partials that both blocks `@use`. The section-specific painting (`::before` two-layer knockout, positions, bleed, fan special-case, stacking) stays in the section block.

**Files:**
- Create: `src/styles/shared/_shape-masks.scss` (move from `src/blocks/section/styles/_shape-masks.scss`)
- Create: `src/styles/shared/_shape-mask-classes.scss` (new — slug list + `@each` assignment + inherit resolver)
- Modify: `src/blocks/section/styles/_shape-divider.scss` (consume shared partials instead of its inline copies)
- Delete: `src/blocks/section/styles/_shape-masks.scss` (moved)

**Step 1: Move the mask library verbatim**

Move `src/blocks/section/styles/_shape-masks.scss` → `src/styles/shared/_shape-masks.scss` with NO content changes (the `:root { --dsgo-shape--*: url(...) }` block is byte-identical). Update its header comment's file reference note only if it names its own path.

**Step 2: Create the shared class-assignment partial**

Create `src/styles/shared/_shape-mask-classes.scss`. Move the slug list, the `@each` mask-assignment loop, and the `is-shape-inherit` resolver out of `_shape-divider.scss` into here:

```scss
/**
 * Shape mask class assignment — shared, block-agnostic.
 *
 * Maps each `is-shape-<slug>` marker class to its `--dsgo-shape-mask`
 * custom property, and resolves `is-shape-inherit` to the theme.json
 * shape-divider type token. @used by BOTH the section block's shape
 * divider and the standalone section-divider block. The mask data-URIs
 * themselves live in the companion `_shape-masks.scss`.
 */

@use 'shape-masks';

// The ONLY SCSS copy of the slug list. One entry per getShapeDividerNames()
// (src/blocks/section/utils/shape-dividers.js) — keep in sync.
$dsgo-shape-slugs: (
	'wave', 'wave-double', 'wave-layered', 'wave-asymmetric',
	'tilt', 'tilt-reverse', 'curve', 'curve-asymmetric',
	'triangle', 'triangle-asymmetric', 'arrow', 'arrow-wide',
	'peaks', 'peaks-soft', 'zigzag', 'book',
	'clouds', 'drops', 'split', 'fan',
	'steps', 'torn', 'slime'
);

@each $dsgo-shape-slug in $dsgo-shape-slugs {
	:where(.dsgo-shape-divider.is-shape-#{$dsgo-shape-slug}) {
		--dsgo-shape-mask: var(--dsgo-shape--#{$dsgo-shape-slug});
	}
}

// Theme-default shape → theme.json custom token, fallback wave.
:where(.dsgo-shape-divider.is-shape-inherit) {
	--dsgo-shape-mask: var(--wp--custom--designsetgo--shape-divider--type, var(--dsgo-shape--wave));
}
```

**Step 3: Point the section's `_shape-divider.scss` at the shared partials**

In `src/blocks/section/styles/_shape-divider.scss`:
- Replace `@use 'shape-masks';` (relative to the old sibling) with `@use '../../../styles/shared/shape-mask-classes';` (this transitively `@use`s `shape-masks`).
- Remove the now-moved `$dsgo-shape-slugs` list, the `@each` assignment loop, and the `is-shape-inherit` resolver (they now live in the shared partial).
- Keep everything section-specific: `::before` knockout painting, bleed over-paint, position rules, `is-front` z-index, the `is-shape-fan` single-layer override, and `.dsgo-stack--has-shape-divider` stacking.

Verify the relative path depth: `src/blocks/section/styles/` → `src/styles/shared/` is `../../../styles/shared/`.

**Step 4: Build and diff the section CSS**

Run: `npm run build`
Expected: build succeeds, no SCSS errors.

Then confirm the section's compiled shape CSS is unchanged vs. `main`:
```bash
grep -c "dsgo-shape--wave" build/blocks/section/style.css   # masks still present
grep "is-shape-inherit" build/blocks/section/style.css       # inherit resolver present
```
Expected: mask library + inherit resolver still compiled into the section block CSS.

**Step 5: Run the section regression tests**

Run: `npx jest src/blocks/section/`
Expected: PASS — 10 tests, 0 failures (baseline from worktree setup). The CSS move must not change any serialized markup, so `save.test.js` and `deprecated.test.js` stay green.

**Step 6: Commit**

```bash
git add src/styles/shared/ src/blocks/section/styles/
git commit -m "refactor: extract shared shape-mask CSS primitives"
```

---

## Task 2: Scaffold the block (block.json + registration)

**Files:**
- Create: `src/blocks/section-divider/block.json`
- Create: `src/blocks/section-divider/index.js`

**Step 1: Write `block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "designsetgo/section-divider",
	"title": "Section Divider",
	"category": "design",
	"description": "A standalone shape divider you can drop between any two blocks. Shape, height, and color inherit from your theme by default.",
	"keywords": ["divider", "shape", "separator", "wave", "section"],
	"textdomain": "designsetgo",
	"attributes": {
		"shape": { "type": "string", "default": "inherit" },
		"height": { "type": ["number", "null"], "default": null },
		"width": { "type": "number", "default": 100 },
		"flipX": { "type": "boolean", "default": false },
		"flipY": { "type": "boolean", "default": false },
		"fillColor": { "type": "string", "default": "" }
	},
	"supports": {
		"align": ["wide", "full"],
		"anchor": true,
		"html": false,
		"spacing": { "margin": true }
	},
	"align": "full",
	"editorScript": "file:./index.js",
	"style": "file:./style.css",
	"editorStyle": "file:./editor.css"
}
```

Note: `editorScript`/`style`/`editorStyle` `file:` paths point at built assets (`build/blocks/section-divider/`); `class-loader.php` resolves them. Confirm the section block's `block.json` uses the same `file:./index.js` convention and mirror it exactly.

**Step 2: Write `index.js`**

```jsx
/**
 * Section Divider Block Registration
 *
 * Standalone solid-filled shape divider. Shape, height, and fill color
 * inherit from theme.json tokens by default; users override per instance.
 */

import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import save from './save';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M2 15 C7 8 17 8 22 15 L22 20 L2 20 Z" fill="currentColor" />
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	save,
} );
```

**Step 3: Commit** (after Task 3–5 give it working edit/save; scaffold alone won't build without them, so defer the commit — proceed straight to Task 3).

---

## Task 3: `save.js` + attribute serialization (TDD)

Serialize minimal markup: the `__shape` inner div gets a marker class per shape, and emits a custom prop ONLY when the attribute differs from its CSS default (mirrors `ShapeDivider.js:89-101`). Default divider → bare `is-shape-inherit`, no `style`.

**Files:**
- Create: `src/blocks/section-divider/save.js`
- Test: `src/blocks/section-divider/test/save.test.js`

**Step 1: Write the failing test**

```js
/**
 * @jest-environment jsdom
 */
import { registerBlockType, createBlock, serialize } from '@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

beforeAll( () => {
	registerBlockType( metadata.name, { ...metadata, save, edit: () => null } );
} );

const serializeWith = ( attrs ) =>
	serialize( createBlock( metadata.name, attrs ) );

describe( 'section-divider save', () => {
	it( 'serializes a default divider as bare inherit markup (no style)', () => {
		const html = serializeWith( {} );
		expect( html ).toContain( 'dsgo-section-divider__shape' );
		expect( html ).toContain( 'is-shape-inherit' );
		expect( html ).not.toContain( 'style=' );
		expect( html ).not.toContain( '--dsgo-shape-height' );
	} );

	it( 'emits the shape slug class when set', () => {
		const html = serializeWith( { shape: 'wave' } );
		expect( html ).toContain( 'is-shape-wave' );
		expect( html ).not.toContain( 'is-shape-inherit' );
	} );

	it( 'emits height var only when height is a number', () => {
		const html = serializeWith( { height: 140 } );
		expect( html ).toContain( '--dsgo-shape-height:140px' );
	} );

	it( 'emits fill var only when fillColor is set', () => {
		const html = serializeWith( { fillColor: '#ff0000' } );
		expect( html ).toContain( '--dsgo-section-divider-fill:#ff0000' );
	} );

	it( 'emits flip transforms only when flipped', () => {
		expect( serializeWith( { flipX: true } ) ).toContain( '--dsgo-shape-flip-x:-1' );
		expect( serializeWith( { flipY: true } ) ).toContain( '--dsgo-shape-flip-y:-1' );
		expect( serializeWith( {} ) ).not.toContain( '--dsgo-shape-flip' );
	} );

	it( 'emits width var only when width differs from 100', () => {
		expect( serializeWith( { width: 150 } ) ).toContain( '--dsgo-shape-width:150%' );
		expect( serializeWith( {} ) ).not.toContain( '--dsgo-shape-width' );
	} );
} );
```

**Step 2: Run test to verify it fails**

Run: `npx jest src/blocks/section-divider/`
Expected: FAIL — `../save` not found / no matching module.

**Step 3: Write `save.js`**

```jsx
/**
 * Section Divider — save
 *
 * Two-div output: outer carries block-support/align classes; inner
 * `__shape` is the masked, solid-filled shape. Custom props are emitted
 * only when they differ from the CSS default, so a fully-default divider
 * serializes as bare `is-shape-inherit` markup with no inline style.
 */

import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { shape, height, width, flipX, flipY, fillColor } = attributes;

	const style = {};
	if ( fillColor ) {
		style[ '--dsgo-section-divider-fill' ] = fillColor;
	}
	if ( typeof height === 'number' ) {
		style[ '--dsgo-shape-height' ] = `${ height }px`;
	}
	if ( width && width !== 100 ) {
		style[ '--dsgo-shape-width' ] = `${ width }%`;
	}
	if ( flipX ) {
		style[ '--dsgo-shape-flip-x' ] = -1;
	}
	if ( flipY ) {
		style[ '--dsgo-shape-flip-y' ] = -1;
	}

	const shapeClass = shape === 'inherit' ? 'is-shape-inherit' : `is-shape-${ shape }`;

	return (
		<div { ...useBlockProps.save() }>
			<div
				className={ `dsgo-section-divider__shape dsgo-shape-divider ${ shapeClass }` }
				style={ Object.keys( style ).length ? style : undefined }
			/>
		</div>
	);
}
```

**Step 4: Run test to verify it passes**

Run: `npx jest src/blocks/section-divider/`
Expected: PASS — all 6 tests green.

**Step 5: Commit** — deferred until `edit.js` exists (block won't register without it). Proceed to Task 4.

---

## Task 4: `edit.js` + inspector (Theme-3 IA)

Render the same classed `__shape` div so CSS paints it live in the editor. Inspector: a **Settings** `<DsgoInspectorPanel>` (shape picker, height, width, flip X/Y) and fill color in `<InspectorControls group="color">`.

**Files:**
- Create: `src/blocks/section-divider/edit.js`
- Create: `src/blocks/section-divider/utils/index.js` (thin re-export of shape options)

**Step 1: Re-export shape options (DRY — no geometry duplication)**

`src/blocks/section-divider/utils/index.js`:
```js
/**
 * Re-export the section block's shape option helpers so the divider's
 * inspector picker stays in sync with the single source of truth.
 */
export { getShapeDividerOptions, getShapeDivider } from '../../section/utils/shape-dividers';
```

Confirm the exact export names in `src/blocks/section/utils/shape-dividers.js` (`getShapeDividerOptions`, `getShapeDivider`, `getShapeDividerNames`) and re-export whatever the inspector needs.

**Step 2: Write `edit.js`**

Reference the section's `ShapeDividerControls.js` for the "Theme default" (`inherit`) select option (value `'inherit'`, injected after "None") and `ShapePreview` swatch. Build:

```jsx
/**
 * Section Divider — edit
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import {
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import DsgoInspectorPanel from '../../components/shared/DsgoInspectorPanel';
import { getShapeDividerOptions } from './utils';

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { shape, height, width, flipX, flipY, fillColor } = attributes;
	const colorSettings = useMultipleOriginColorsAndGradients();

	const shapeOptions = [
		{ label: __( 'Theme default', 'designsetgo' ), value: 'inherit' },
		...getShapeDividerOptions(),
	];

	const style = {};
	if ( fillColor ) style[ '--dsgo-section-divider-fill' ] = fillColor;
	if ( typeof height === 'number' ) style[ '--dsgo-shape-height' ] = `${ height }px`;
	if ( width && width !== 100 ) style[ '--dsgo-shape-width' ] = `${ width }%`;
	if ( flipX ) style[ '--dsgo-shape-flip-x' ] = -1;
	if ( flipY ) style[ '--dsgo-shape-flip-y' ] = -1;

	const shapeClass = shape === 'inherit' ? 'is-shape-inherit' : `is-shape-${ shape }`;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel title={ __( 'Settings', 'designsetgo' ) } panelName="settings" panelId={ clientId }>
					<DsgoInspectorPanel.Item
						label={ __( 'Shape', 'designsetgo' ) }
						hasValue={ () => shape !== 'inherit' }
						onDeselect={ () => setAttributes( { shape: 'inherit' } ) }
						isShownByDefault
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Shape', 'designsetgo' ) }
							value={ shape }
							options={ shapeOptions }
							onChange={ ( value ) => setAttributes( { shape: value } ) }
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={ __( 'Height', 'designsetgo' ) }
						hasValue={ () => height !== null }
						onDeselect={ () => setAttributes( { height: null } ) }
						isShownByDefault
					>
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Height', 'designsetgo' ) }
							value={ height ?? '' }
							onChange={ ( value ) => setAttributes( { height: value ?? null } ) }
							min={ 10 }
							max={ 500 }
							allowReset
							placeholder={ __( 'Theme default', 'designsetgo' ) }
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={ __( 'Width', 'designsetgo' ) }
						hasValue={ () => width !== 100 }
						onDeselect={ () => setAttributes( { width: 100 } ) }
						isShownByDefault
					>
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Width', 'designsetgo' ) }
							value={ width }
							onChange={ ( value ) => setAttributes( { width: value ?? 100 } ) }
							min={ 100 }
							max={ 300 }
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={ __( 'Flip horizontal', 'designsetgo' ) }
						hasValue={ () => flipX }
						onDeselect={ () => setAttributes( { flipX: false } ) }
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Flip horizontal', 'designsetgo' ) }
							checked={ flipX }
							onChange={ ( value ) => setAttributes( { flipX: value } ) }
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={ __( 'Flip vertical', 'designsetgo' ) }
						hasValue={ () => flipY }
						onDeselect={ () => setAttributes( { flipY: false } ) }
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Flip vertical', 'designsetgo' ) }
							checked={ flipY }
							onChange={ ( value ) => setAttributes( { flipY: value } ) }
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					__experimentalIsRenderedInSidebar
					settings={ [
						{
							label: __( 'Fill', 'designsetgo' ),
							colorValue: fillColor,
							onColorChange: ( value ) => setAttributes( { fillColor: value || '' } ),
							clearable: true,
						},
					] }
					panelId={ clientId }
					{ ...colorSettings }
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div
					className={ `dsgo-section-divider__shape dsgo-shape-divider ${ shapeClass }` }
					style={ style }
				/>
			</div>
		</>
	);
}
```

Verify against the codebase: the exact `DsgoInspectorPanel` import path and its `.Item` API (`src/components/shared/DsgoInspectorPanel`), and how `ShapeDividerControls.js` wires `ColorGradientSettingsDropdown` (imports, `clientId`/`panelId`, `useMultipleOriginColorsAndGradients`). Match the existing pattern exactly rather than the sketch above if they differ.

**Step 3: Build**

Run: `npm run build`
Expected: builds `build/blocks/section-divider/{index.js,style.css,editor.css,block.json}` with no errors.

**Step 4: Commit**

```bash
git add src/blocks/section-divider/
git commit -m "feat: add section-divider block (edit/save/registration)"
```

---

## Task 5: Block SCSS (frontend + editor parity)

Single-layer solid fill. Both stylesheets `@use` the shared mask primitives so the block gets the mask library + `is-shape-*` + inherit resolver.

**Files:**
- Create: `src/blocks/section-divider/style.scss`
- Create: `src/blocks/section-divider/editor.scss`

**Step 1: Write `style.scss`**

```scss
/**
 * Section Divider — frontend
 *
 * Standalone solid-filled shape divider. The `is-shape-*` marker classes
 * and mask library come from the shared primitives; here we just paint the
 * masked box with the fill color (attribute → theme token → base preset).
 */

@use '../../styles/shared/shape-mask-classes';

:where(.wp-block-designsetgo-section-divider) {
	line-height: 0;
}

:where(.dsgo-section-divider__shape) {
	display: block;
	width: var(--dsgo-shape-width, 100%);
	height: var(--dsgo-shape-height,
		var(--wp--custom--designsetgo--shape-divider--height, 100px));
	margin-inline: auto;
	background-color: var(--dsgo-section-divider-fill,
		var(--wp--custom--designsetgo--shape-divider--color, var(--wp--preset--color--base)));
	-webkit-mask: var(--dsgo-shape-mask) no-repeat center / 100% 100%;
	mask: var(--dsgo-shape-mask) no-repeat center / 100% 100%;
	transform: scaleX(var(--dsgo-shape-flip-x, 1)) scaleY(var(--dsgo-shape-flip-y, 1));
}
```

Verify the relative path: `src/blocks/section-divider/` → `src/styles/shared/` is `../../styles/shared/`.

Note `--dsgo-shape-width` is a percentage stretch of the shape box; width >100% intentionally overflows to exaggerate the shape, matching the section renderer's clamp semantics. If overflow needs clipping, add `overflow: hidden` to the outer wrapper — decide during manual test.

**Step 2: Write `editor.scss` (parity — mandatory)**

```scss
/**
 * Section Divider — editor
 *
 * Imports the identical frontend ruleset so the editor preview matches
 * the saved output (CLAUDE.md: style.scss and editor.scss must agree).
 */

@use 'style';
```

If `@use 'style';` triggers a duplicate-`:root` or load-order issue with the shared masks, instead mirror the imports directly (`@use '../../styles/shared/shape-mask-classes';` + copy the two rulesets). Prefer `@use 'style'` if it compiles cleanly.

**Step 3: Build and verify CSS lands in both bundles**

Run: `npm run build`
Then:
```bash
grep -i "dsgo-section-divider__shape" build/blocks/section-divider/style.css
grep -i "dsgo-section-divider__shape" build/blocks/section-divider/editor.css
grep -c "dsgo-shape--wave" build/blocks/section-divider/style.css   # mask lib present
grep "is-shape-inherit" build/blocks/section-divider/style.css       # inherit resolver
grep "wp--preset--color--base" build/blocks/section-divider/style.css # fill fallback chain
```
Expected: all present in both frontend and editor CSS.

**Step 4: Commit**

```bash
git add src/blocks/section-divider/
git commit -m "feat: section-divider styles (frontend + editor parity)"
```

---

## Task 6: Full verification (@ superpowers:verification-before-completion)

**Step 1: Lint**

```bash
npm run lint:js
npm run lint:css
```
Expected: no errors in `src/blocks/section-divider/` or `src/styles/shared/`.

**Step 2: Full test suite**

Run: `npx jest src/blocks/section-divider/ src/blocks/section/`
Expected: new save tests pass; section tests still 10/0 (regression check for the CSS extraction).

**Step 3: Manual — spin up the editor**

```bash
npx wp-env start
```
In the block editor:
1. Insert **Section Divider** from the inserter — confirm it appears with a wave shape (inherit default), full-width, filled with the base color, no `style` attribute in the code view.
2. Change shape → picker updates the live preview.
3. Set an explicit height, width, flip X, flip Y — preview updates; code view shows only the changed vars.
4. Set a fill color — preview + code view show `--dsgo-section-divider-fill`.
5. Clear each control via the ⋮ reset — attribute returns to inherit/default and the inline var disappears.
6. Save, view frontend — divider renders identically to the editor. Check browser console (editor + frontend) for errors.
7. Drop the divider inside a constrained `core/group` and set align full — confirms full-bleed escape.
8. Theme-token override: in a test theme's `theme.json`, set
   `settings.custom.designsetgo.shapeDivider: { type: "var(--dsgo-shape--zigzag)", height: "160px", color: "var(--wp--preset--color--primary)" }`,
   reload — a fully-default divider now renders zigzag / 160px / primary color **without any change to post content**. This proves theme inheritance.

**Step 4: Confirm no `console.log`**

```bash
grep -rn "console.log" src/blocks/section-divider/ src/styles/shared/
```
Expected: no matches.

**Step 5: README (@ README skill)**

If the repo convention is a per-block README or a block entry in a catalog, add one describing the block, its three theme tokens, and the theme-default behavior. Otherwise skip.

---

## Task 7: Finish the branch (@ superpowers:finishing-a-development-branch)

**Step 1:** Confirm all commits are clean and the tree matches the plan.

**Step 2:** Present integration options (merge / PR / cleanup) per the finishing-a-development-branch skill. Branch: `claude/section-divider-block`. Suggested PR title: `feat: add standalone section-divider block`. Body: summarize the block, the three theme tokens, and the shared-CSS extraction; note zero content-migration risk (new block, no deprecations) and the section-block regression coverage.

---

## Risk notes for the executing engineer

- **The one real risk is Task 1** — the shared-CSS extraction touches the shipping section block. The section regression tests (`npx jest src/blocks/section/`) and the compiled-CSS greps are your safety net; run them before moving on.
- **Emit-only-when-differs** is what keeps default content bare and theme-driven. If you emit vars unconditionally, defaults get baked into content and theme overrides stop working — the whole point of the design. The save tests guard this; don't weaken them.
- **`height: null`** is the inherit signal. Keep the attribute type `["number", "null"]` and never coerce `null`→`0`; `0` would emit `--dsgo-shape-height:0px` and collapse the divider.
- **Don't reintroduce the section's `::before` knockout** — this block is a single-layer solid fill. The `fan` shape's tonal opacity lives in its own mask data-URI, so it renders correctly filled without the section's special-case.
