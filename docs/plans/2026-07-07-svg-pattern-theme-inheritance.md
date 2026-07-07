# SVG Pattern — Theme Inheritance Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Let the SVG-pattern background extension inherit a full pattern preset (type + color + opacity + scale) from theme.json / FSE global styles, selectable per block via a new "Theme default" choice, falling back to an in-plugin default when no theme value is set — mirroring the shape-divider theme-inheritance work.

**Architecture:** The SVG pattern is a block extension on `core/group` + `designsetgo/section`. It is **server-rendered**: `save()` writes lean data attributes (`data-dsgo-svg-pattern`, `-color`, `-opacity`, `-scale`) and PHP (`SVG_Pattern_Renderer`) generates the SVG data URI at render time. We add a new attribute *value* `'inherit'` for `dsgoSvgPatternType` (not a schema change — no deprecation). When a block's type is `'inherit'`, both the PHP renderer and the editor resolve the pattern preset from `settings.custom.designsetgo.svgPattern` (`type`/`color`/`opacity`/`scale`), each field falling back to an in-plugin default (`dot-grid`, `#9c92ac`, `0.4`, `1`). The Style-Kit value that populates those settings lives in the external `native-ui` repo (out of scope here); the in-plugin fallback guarantees it works with no kit.

**Tech Stack:** WordPress block editor (`@wordpress/hooks`, `@wordpress/block-editor` `useSettings`), Jest (`wp-scripts test-unit-js`) for save/attribute unit tests, PHP (`wp_get_global_settings`, `WP_HTML_Tag_Processor`) for server render.

**Decisions already locked (from brainstorming):**
- Resolve the inherited slug/preset **server-side (PHP) + editor JS** — keep the existing server render; do NOT rewrite to CSS masks. Preserves `opacity`/`scale`/`fixed` exactly.
- Theme provides a **full preset**: `type`, `color`, `opacity`, `scale`. `fixed` stays a per-block toggle (not themed).
- Opt-in is an **explicit "Theme default" tile** in the pattern picker → sets `dsgoSvgPatternType: 'inherit'`.
- In-plugin fallback slug: **`dot-grid`** (the neutral equivalent of shape-divider's `wave`).
- When a block inherits, it adopts the theme preset **wholesale**; the per-block Color / Opacity / Scale controls are hidden while inheriting (Enable / Fixed / Clear stay). Choosing an explicit pattern restores them.

**Key reference files (read before starting):**
- `src/extensions/svg-patterns/constants.js` — `SUPPORTED_BLOCKS`, `DEFAULTS`, `RANGES`.
- `src/extensions/svg-patterns/editor.js` — `addSvgPatternEditorStyles` (editor preview), `addSvgPatternSaveProps` (save), `stripLegacySvgPatternStyles`.
- `src/extensions/svg-patterns/attributes.js` — attribute registration + existing `legacyColorDeprecation`.
- `src/extensions/svg-patterns/components/SvgPatternsPanel.js` — inspector UI + picker.
- `src/extensions/svg-patterns/patterns.js` + `pattern-data.js` — `PATTERNS`, `PATTERN_IDS`, `getPatternBackground`.
- `includes/features/class-svg-pattern-renderer.php` — server render + `resolve_color_value`.
- `src/hooks/useIconDefaults.js` — canonical `useSettings('custom.designsetgo.…')` editor read.
- `includes/features/class-icon-injector.php:134` + `src/blocks/map/render.php:45` — canonical `wp_get_global_settings(['custom','designsetgo',…])` PHP read.
- `docs/plans/2026-07-01-shape-divider-theme-inheritance.md` — the precedent this mirrors.
- `.claude/CLAUDE.md` → "Deprecations", "Style Imports (MANDATORY)", inspector IA notes.

---

## Task 0: Confirm clean baseline

The worktree is already created (`.worktrees/svg-pattern-theme-inheritance`, branch `claude/svg-pattern-theme-inheritance`, off `origin/main`) with deps installed and a green unit baseline (2072 tests).

**Step 1:** Re-confirm green before touching anything:

```bash
npx wp-scripts test-unit-js 2>&1 | tail -5
```
Expected: `Tests: 2072 passed`.

**Step 2:** Commit this plan:

```bash
git add docs/plans/2026-07-07-svg-pattern-theme-inheritance.md
git commit -m "docs: svg-pattern theme-inheritance implementation plan"
```

---

## Task 1: Shared inherit constants + preset resolver (JS)

Introduce the `'inherit'` sentinel and a single JS resolver that turns the theme preset (+ fallbacks) into a concrete `{ type, color, opacity, scale }`. Both the editor preview and the panel consume it, so it must be DRY.

**Files:**
- Modify: `src/extensions/svg-patterns/constants.js`
- Create: `src/extensions/svg-patterns/utils/resolve-inherited-pattern.js`
- Test: `tests/unit/extensions/svg-patterns/resolve-inherited-pattern.test.js`

**Step 1: Add constants.** Append to `constants.js`:

```js
/**
 * Sentinel value for dsgoSvgPatternType meaning "inherit the theme's
 * SVG pattern preset from settings.custom.designsetgo.svgPattern".
 */
export const INHERIT = 'inherit';

/**
 * In-plugin fallback preset used when a block inherits but the theme
 * (Style Kit) sets nothing. Each field falls back independently.
 */
export const INHERIT_FALLBACK = {
	type: 'dot-grid',
	color: DEFAULTS.color,
	opacity: DEFAULTS.opacity,
	scale: DEFAULTS.scale,
};
```

**Step 2: Write the failing test.**

```js
// tests/unit/extensions/svg-patterns/resolve-inherited-pattern.test.js
import { resolveInheritedPattern } from '../../../../src/extensions/svg-patterns/utils/resolve-inherited-pattern';
import { INHERIT_FALLBACK } from '../../../../src/extensions/svg-patterns/constants';
import { PATTERNS } from '../../../../src/extensions/svg-patterns/pattern-data';

test( 'falls back to in-plugin defaults when theme preset is empty', () => {
	expect( resolveInheritedPattern( undefined ) ).toEqual( INHERIT_FALLBACK );
	expect( resolveInheritedPattern( {} ) ).toEqual( INHERIT_FALLBACK );
} );

test( 'uses theme values when present', () => {
	const themed = resolveInheritedPattern( {
		type: 'waves',
		color: '#123456',
		opacity: 0.2,
		scale: 2,
	} );
	expect( themed ).toEqual( {
		type: 'waves',
		color: '#123456',
		opacity: 0.2,
		scale: 2,
	} );
} );

test( 'each field falls back independently', () => {
	const partial = resolveInheritedPattern( { type: 'grain' } );
	expect( partial.type ).toBe( 'grain' );
	expect( partial.color ).toBe( INHERIT_FALLBACK.color );
	expect( partial.opacity ).toBe( INHERIT_FALLBACK.opacity );
	expect( partial.scale ).toBe( INHERIT_FALLBACK.scale );
} );

test( 'rejects an unknown theme pattern slug and falls back', () => {
	const bad = resolveInheritedPattern( { type: 'not-a-real-pattern' } );
	expect( PATTERNS[ bad.type ] ).toBeDefined();
	expect( bad.type ).toBe( INHERIT_FALLBACK.type );
} );
```

**Step 3: Run it — verify FAIL** (module not found):

```bash
npx wp-scripts test-unit-js tests/unit/extensions/svg-patterns/resolve-inherited-pattern.test.js
```

**Step 4: Implement the resolver.** Create `utils/resolve-inherited-pattern.js`:

```js
/**
 * SVG Patterns Extension - Inherited-preset resolver
 *
 * Turns a theme.json preset (settings.custom.designsetgo.svgPattern) into a
 * concrete { type, color, opacity, scale }, applying in-plugin fallbacks per
 * field. Shared by the editor preview and the inspector panel.
 *
 * @package
 */

import { INHERIT_FALLBACK } from '../constants';
import { PATTERNS } from '../pattern-data';

/**
 * @param {?Object} preset Raw theme preset object, or nullish.
 * @return {{type: string, color: string, opacity: number, scale: number}}
 *         Fully-resolved pattern config.
 */
export function resolveInheritedPattern( preset ) {
	const p = preset && typeof preset === 'object' ? preset : {};

	const type =
		typeof p.type === 'string' && PATTERNS[ p.type ]
			? p.type
			: INHERIT_FALLBACK.type;

	const color =
		typeof p.color === 'string' && p.color !== ''
			? p.color
			: INHERIT_FALLBACK.color;

	const opacity =
		typeof p.opacity === 'number' ? p.opacity : INHERIT_FALLBACK.opacity;

	const scale =
		typeof p.scale === 'number' ? p.scale : INHERIT_FALLBACK.scale;

	return { type, color, opacity, scale };
}
```

**Step 5: Run — verify PASS.**

```bash
npx wp-scripts test-unit-js tests/unit/extensions/svg-patterns/resolve-inherited-pattern.test.js
```

**Step 6: Commit.**

```bash
git add src/extensions/svg-patterns/constants.js \
        src/extensions/svg-patterns/utils/resolve-inherited-pattern.js \
        tests/unit/extensions/svg-patterns/resolve-inherited-pattern.test.js
git commit -m "feat(svg-patterns): add inherit sentinel + theme-preset resolver"
```

---

## Task 2: Save props — allow `inherit`, omit baked values (JS)

Let `'inherit'` through the save guard and, when inheriting, emit only the type marker + class (the renderer supplies color/opacity/scale from the theme).

**Files:**
- Modify: `src/extensions/svg-patterns/editor.js` (`addSvgPatternSaveProps`)
- Test: `tests/unit/extensions/svg-patterns/save-props.test.js`

**Step 1: Write the failing test.** `addSvgPatternSaveProps` is not exported today — export it first (see Step 3), then:

```js
// tests/unit/extensions/svg-patterns/save-props.test.js
import { addSvgPatternSaveProps } from '../../../../src/extensions/svg-patterns/editor';

const blockType = { name: 'designsetgo/section' };

test( 'explicit pattern writes color/opacity/scale data attrs', () => {
	const props = addSvgPatternSaveProps( {}, blockType, {
		dsgoSvgPatternEnabled: true,
		dsgoSvgPatternType: 'waves',
		dsgoSvgPatternColor: '#123456',
		dsgoSvgPatternOpacity: 0.3,
		dsgoSvgPatternScale: 2,
	} );
	expect( props[ 'data-dsgo-svg-pattern' ] ).toBe( 'waves' );
	expect( props[ 'data-dsgo-svg-pattern-opacity' ] ).toBe( '0.3' );
	expect( props[ 'data-dsgo-svg-pattern-scale' ] ).toBe( '2' );
	expect( props.className ).toContain( 'has-dsgo-svg-pattern' );
} );

test( 'inherit writes only the type marker, omits color/opacity/scale', () => {
	const props = addSvgPatternSaveProps( {}, blockType, {
		dsgoSvgPatternEnabled: true,
		dsgoSvgPatternType: 'inherit',
	} );
	expect( props[ 'data-dsgo-svg-pattern' ] ).toBe( 'inherit' );
	expect( props ).not.toHaveProperty( 'data-dsgo-svg-pattern-color' );
	expect( props ).not.toHaveProperty( 'data-dsgo-svg-pattern-opacity' );
	expect( props ).not.toHaveProperty( 'data-dsgo-svg-pattern-scale' );
	expect( props.className ).toContain( 'has-dsgo-svg-pattern' );
} );

test( 'disabled pattern is untouched', () => {
	const props = addSvgPatternSaveProps( {}, blockType, {
		dsgoSvgPatternEnabled: false,
		dsgoSvgPatternType: 'inherit',
	} );
	expect( props ).not.toHaveProperty( 'data-dsgo-svg-pattern' );
} );
```

**Step 2: Run — verify FAIL** (`addSvgPatternSaveProps` not exported / inherit rejected).

```bash
npx wp-scripts test-unit-js tests/unit/extensions/svg-patterns/save-props.test.js
```

**Step 3: Implement.** In `editor.js`:

1. Add `import { INHERIT } from './constants';` (extend the existing constants import).
2. Change `function addSvgPatternSaveProps(...)` to `export function addSvgPatternSaveProps(...)`.
3. Update the guard so `inherit` is allowed:

```js
	const isInherit = dsgoSvgPatternType === INHERIT;

	if (
		!SUPPORTED_BLOCKS.includes(blockType.name) ||
		!dsgoSvgPatternEnabled ||
		!dsgoSvgPatternType ||
		(!isInherit && !PATTERN_IDS.includes(dsgoSvgPatternType))
	) {
		return extraProps;
	}
```

4. Short-circuit the inherit branch (emit marker + class only) before the existing explicit-pattern return:

```js
	if (isInherit) {
		return {
			...extraProps,
			className: [extraProps.className, 'has-dsgo-svg-pattern']
				.filter(Boolean)
				.join(' '),
			style: extraProps.style || {},
			'data-dsgo-svg-pattern': INHERIT,
		};
	}
```

Leave the existing explicit-pattern block (color/opacity/scale data attrs) exactly as-is below it.

**Step 4: Run — verify PASS.**

```bash
npx wp-scripts test-unit-js tests/unit/extensions/svg-patterns/save-props.test.js
```

**Step 5: Commit.**

```bash
git add src/extensions/svg-patterns/editor.js tests/unit/extensions/svg-patterns/save-props.test.js
git commit -m "feat(svg-patterns): emit inherit marker in save props, omit baked preset"
```

---

## Task 3: Editor live preview resolves inherit (JS)

`addSvgPatternEditorStyles` must render the correct preview when a block inherits: resolve the theme preset via `useSettings`, then feed the resolved `{type,color,opacity,scale}` to `getPatternBackground`.

**Files:**
- Modify: `src/extensions/svg-patterns/editor.js` (`addSvgPatternEditorStyles`)

> No new unit test — this HOC renders editor chrome (hooks + `BlockListBlock`); it's covered by the manual editor smoke test in Task 6. Keep the change minimal and obviously correct.

**Step 1:** Add imports to `editor.js`:

```js
import { useSettings } from '@wordpress/block-editor';
import { INHERIT } from './constants';
import { resolveInheritedPattern } from './utils/resolve-inherited-pattern';
```

**Step 2:** Inside the returned component, before the existing `resolvedColor` `useSelect`, read the theme preset and compute the effective config. `useSettings` must be called unconditionally (Rules of Hooks):

```js
	const isInherit = dsgoSvgPatternType === INHERIT;

	// Theme preset lives at settings.custom.designsetgo.svgPattern.
	// useSettings reads one leaf at a time; pull the four fields we need.
	const [themeType, themeColor, themeOpacity, themeScale] = useSettings(
		'custom.designsetgo.svgPattern.type',
		'custom.designsetgo.svgPattern.color',
		'custom.designsetgo.svgPattern.opacity',
		'custom.designsetgo.svgPattern.scale'
	);

	const inherited = useMemo(
		() =>
			resolveInheritedPattern({
				type: themeType,
				color: themeColor,
				opacity: themeOpacity,
				scale: themeScale,
			}),
		[themeType, themeColor, themeOpacity, themeScale]
	);
```

**Step 3:** Update `isActive` so `inherit` counts as active even though it isn't in `PATTERNS`:

```js
	const isActive =
		dsgoSvgPatternEnabled &&
		dsgoSvgPatternType &&
		(isInherit || PATTERNS[dsgoSvgPatternType]);
```

**Step 4:** Compute the effective pattern id / color / opacity / scale, preferring the inherited preset when inheriting. The existing `resolvedColor` `useSelect` already resolves preset slugs → hex for the *block* color; when inheriting, the color comes from `inherited.color`, which may itself be a `var:preset|color|slug` or `var(--wp--preset--color--slug)` string. Normalize it through the same resolver the block color uses. Simplest correct approach: feed the effective raw color into the existing resolver by making `resolvedColor`'s dependency the effective color.

Refactor the `resolvedColor` `useSelect` to resolve `effectiveRawColor` instead of `dsgoSvgPatternColor`:

```js
	const effectiveRawColor = isInherit ? inherited.color : dsgoSvgPatternColor;

	const resolvedColor = useSelect(
		(select) => {
			if (!effectiveRawColor || typeof effectiveRawColor !== 'string') {
				return DEFAULTS.color;
			}
			// Accept both var:preset|color|slug and var(--wp--preset--color--slug).
			const presetMatch = effectiveRawColor.match(
				/^var:preset\|color\|(.+)$/
			);
			const cssVarMatch = effectiveRawColor.match(
				/^var\(--wp--preset--color--(.+)\)$/
			);
			const slug = presetMatch?.[1] || cssVarMatch?.[1];
			if (!slug) {
				return effectiveRawColor;
			}
			const settings = select(blockEditorStore).getSettings();
			const colors = settings.colors || [];
			const found = colors.find((c) => c.slug === slug);
			return found?.color || DEFAULTS.color;
		},
		[effectiveRawColor]
	);
```

**Step 5:** Update the `bg` `useMemo` to use the effective type/opacity/scale:

```js
	const bg = useMemo(() => {
		if (!isActive) {
			return null;
		}
		const effType = isInherit ? inherited.type : dsgoSvgPatternType;
		const effOpacity = isInherit
			? inherited.opacity
			: dsgoSvgPatternOpacity ?? DEFAULTS.opacity;
		const effScale = isInherit
			? inherited.scale
			: dsgoSvgPatternScale ?? DEFAULTS.scale;
		return getPatternBackground(
			effType,
			resolvedColor,
			effOpacity,
			effScale
		);
	}, [
		isActive,
		isInherit,
		inherited,
		dsgoSvgPatternType,
		resolvedColor,
		dsgoSvgPatternOpacity,
		dsgoSvgPatternScale,
	]);
```

The rest of the HOC (wrapperProps assembly, `dsgoSvgPatternFixed`) is unchanged.

**Step 6: Build to confirm no syntax/lint break.**

```bash
npm run build 2>&1 | tail -5 && npx wp-scripts test-unit-js 2>&1 | tail -5
```
Expected: build succeeds; all unit tests still pass.

**Step 7: Commit.**

```bash
git add src/extensions/svg-patterns/editor.js
git commit -m "feat(svg-patterns): resolve inherited theme preset in editor preview"
```

---

## Task 4: Panel — "Theme default" tile + hide baked controls (JS)

Add the opt-in UI: a "Theme default" tile at the top of the picker (previewing the resolved theme pattern), and hide Color / Opacity / Scale while inheriting.

**Files:**
- Modify: `src/extensions/svg-patterns/components/SvgPatternsPanel.js`

**Step 1:** Add imports:

```js
import { useSettings } from '@wordpress/block-editor';
import { INHERIT } from '../constants';
import { resolveInheritedPattern } from '../utils/resolve-inherited-pattern';
```

**Step 2:** Inside the component, compute the inherited preset (same `useSettings` read as Task 3) and `const isInherit = dsgoSvgPatternType === INHERIT;`.

**Step 3:** Render a "Theme default" tile as the first item in the picker (before the category groups). Reuse `PatternThumbnail`-style markup but preview the *resolved* theme pattern:

```jsx
{/* Theme default (inherit) tile */}
<div className="dsgo-svg-pattern-picker__group">
	<div className="dsgo-svg-pattern-picker__group-label">
		{__('Theme', 'designsetgo')}
	</div>
	<div className="dsgo-svg-pattern-picker__grid">
		<PatternThumbnail
			patternId={inherited.type}
			isActive={isInherit}
			onClick={() =>
				setAttributes({ dsgoSvgPatternType: INHERIT })
			}
			label={__('Theme default', 'designsetgo')}
		/>
	</div>
</div>
```

Extend `PatternThumbnail` to accept an optional `label` prop overriding `pattern.label` (default to `pattern.label` when omitted) so the tooltip reads "Theme default".

**Step 4:** Update the "Selected:" line so inherit shows a friendly label:

```jsx
<strong>
	{isInherit
		? __('Theme default', 'designsetgo')
		: PATTERNS[dsgoSvgPatternType]?.label}
</strong>
```

**Step 5:** Gate the baked controls. Wrap the **Color** `InspectorControls group="color"` block and the **Opacity** + **Scale** `RangeControl`s so they only render when `!isInherit`. When inheriting, show a short note in their place inside the main panel:

```jsx
{isInherit && (
	<p className="dsgo-svg-pattern-picker__inherit-note">
		{__(
			'Pattern, color, opacity and scale are inherited from your theme. Choose a pattern above to customize them.',
			'designsetgo'
		)}
	</p>
)}
```

Keep the **Enable**, **Fixed**, and **Clear** controls available in all states. (Fixed stays per-block.) The color panel condition becomes `dsgoSvgPatternEnabled && dsgoSvgPatternType && !isInherit`.

**Step 6: Build + lint.**

```bash
npm run build 2>&1 | tail -5 && npm run lint:js 2>&1 | tail -15
```
Expected: clean build, no new lint errors in the touched files.

**Step 7: Commit.**

```bash
git add src/extensions/svg-patterns/components/SvgPatternsPanel.js
git commit -m "feat(svg-patterns): add Theme default tile, hide baked controls while inheriting"
```

---

## Task 5: PHP renderer resolves inherit from global settings

Mirror the JS resolver server-side: when `data-dsgo-svg-pattern` is `inherit`, pull the preset from `wp_get_global_settings(['custom','designsetgo','svgPattern'])` and apply the same fallbacks.

**Files:**
- Modify: `includes/features/class-svg-pattern-renderer.php`

**Step 1:** Add a private fallback constant + resolver method. Near the top of the class add:

```php
	/**
	 * In-plugin fallback preset for inherited SVG patterns (mirrors the JS
	 * INHERIT_FALLBACK in constants.js). Used when the theme sets nothing.
	 *
	 * @var array{type:string,color:string,opacity:float,scale:float}
	 */
	private const INHERIT_FALLBACK = array(
		'type'    => 'dot-grid',
		'color'   => '#9c92ac',
		'opacity' => 0.4,
		'scale'   => 1.0,
	);
```

Add the resolver method (mirrors `resolveInheritedPattern`):

```php
	/**
	 * Resolve the inherited SVG pattern preset from theme global settings,
	 * applying per-field in-plugin fallbacks.
	 *
	 * @param array $patterns Known pattern definitions (allowlist for type).
	 * @return array{type:string,color:string,opacity:float,scale:float}
	 */
	private function resolve_inherited_pattern( $patterns ) {
		$preset = wp_get_global_settings( array( 'custom', 'designsetgo', 'svgPattern' ) );
		if ( ! is_array( $preset ) ) {
			$preset = array();
		}

		$type = isset( $preset['type'] ) && is_string( $preset['type'] ) && isset( $patterns[ $preset['type'] ] )
			? $preset['type']
			: self::INHERIT_FALLBACK['type'];

		$color = isset( $preset['color'] ) && is_string( $preset['color'] ) && '' !== $preset['color']
			? $preset['color']
			: self::INHERIT_FALLBACK['color'];

		$opacity = isset( $preset['opacity'] ) && is_numeric( $preset['opacity'] )
			? (float) $preset['opacity']
			: self::INHERIT_FALLBACK['opacity'];

		$scale = isset( $preset['scale'] ) && is_numeric( $preset['scale'] )
			? (float) $preset['scale']
			: self::INHERIT_FALLBACK['scale'];

		return array(
			'type'    => $type,
			'color'   => $color,
			'opacity' => $opacity,
			'scale'   => $scale,
		);
	}
```

> Note: theme `color` may be `var(--wp--preset--color--slug)` — the existing `resolve_color_value()` already converts that to hex, and the inject flow runs the color through it (Step 2), so no extra handling is needed.

**Step 2:** In `inject_svg_pattern()`, branch on `inherit` right after validating `$pattern_type` is non-empty and BEFORE the `isset( $patterns[ $pattern_type ] )` allowlist check (because `inherit` is not a pattern key):

```php
		$pattern_type = $processor->get_attribute( 'data-dsgo-svg-pattern' );
		if ( empty( $pattern_type ) ) {
			return $block_content;
		}

		$patterns = $this->get_patterns();

		if ( 'inherit' === $pattern_type ) {
			$preset       = $this->resolve_inherited_pattern( $patterns );
			$pattern_type = $preset['type'];
			$color        = $preset['color'];
			$opacity      = $preset['opacity'];
			$scale        = $preset['scale'];
		} else {
			// Validate pattern type against known patterns (allowlist).
			if ( ! isset( $patterns[ $pattern_type ] ) ) {
				return $block_content;
			}
			$color   = $processor->get_attribute( 'data-dsgo-svg-pattern-color' );
			$color   = $color ? $color : '#9c92ac';
			$opacity = (float) ( $processor->get_attribute( 'data-dsgo-svg-pattern-opacity' ) ?? 0.4 );
			$scale   = (float) ( $processor->get_attribute( 'data-dsgo-svg-pattern-scale' ) ?? 1 );
		}

		// Shared normalization for both paths.
		$color   = sanitize_text_field( $this->resolve_color_value( sanitize_text_field( $color ) ) );
		$opacity = max( 0.05, min( 1, $opacity ) );
		$scale   = max( 0.25, min( 4, $scale ) );
```

Remove the now-duplicated `$color`/`$opacity`/`$scale`/clamp lines that previously sat below (they're folded into the branch above). The rest of the method (`$cache_key`, SVG build, style injection, `dsgoSvgPatternFixed` from `$block['attrs']`) is unchanged and works for both paths.

> `dsgoSvgPatternFixed` is still read from `$block['attrs']`, so the per-block Fixed toggle keeps working even while inheriting. Good.

**Step 3: Lint PHP.**

```bash
npm run lint:php 2>&1 | tail -20
```
Expected: no new errors in `class-svg-pattern-renderer.php`.

**Step 4: Commit.**

```bash
git add includes/features/class-svg-pattern-renderer.php
git commit -m "feat(svg-patterns): resolve inherited theme preset in PHP renderer"
```

---

## Task 6: Editor + frontend verification (manual)

**Prereqs:** `npm run build` done; wp-env running (`npx wp-env start`).

**Step 1: No-kit fallback (editor).** Insert a Section (or core Group). SVG Pattern panel → Enable. Confirm a **"Theme default"** tile appears first and previews the `dot-grid` fallback. Select it → confirm the live editor preview shows dots and the Color / Opacity / Scale controls disappear, replaced by the inherit note. Enable / Fixed / Clear remain.

**Step 2: No-kit fallback (frontend).** Save + view the post. Confirm the pattern renders (server injects `dot-grid` at `#9c92ac`, opacity `0.4`, scale `1`). View source: the block carries `data-dsgo-svg-pattern="inherit"` and no baked color/opacity/scale attrs, and the rendered `style` has `--dsgo-svg-pattern-image`/`--dsgo-svg-pattern-size`.

**Step 3: Themed preset.** Temporarily add to the active theme's `theme.json` (or a child) under `settings.custom`:

```json
"designsetgo": { "svgPattern": { "type": "waves", "color": "var(--wp--preset--color--primary)", "opacity": 0.15, "scale": 2 } }
```

Reload editor: the "Theme default" tile + inheriting blocks now preview **waves** in the primary color at the themed opacity/scale. Frontend matches. Remove the temp theme.json edit after.

**Step 4: Explicit still works.** Pick an explicit pattern (e.g. Hexagons) on the same block → controls reappear, baked data attrs return, renders independently of the theme preset.

**Step 5: Fixed while inheriting.** With Theme default selected, toggle Fixed → confirm `--dsgo-svg-pattern-attachment:fixed` appears on the frontend element.

**Step 6: Record results** in the PR description / commit notes (editor + frontend, both `core/group` and `designsetgo/section`).

---

## Task 7: Full verification + finish

**Step 1: Full gate (per CLAUDE.md pre-commit).**

```bash
npm run build && npm run lint:js && npm run lint:css && npm run lint:php
npx wp-scripts test-unit-js 2>&1 | tail -5
```
Expected: all green; unit count = 2072 + new tests.

**Step 2: Confirm no deprecation regressions.** Open an existing post that used an explicit SVG pattern saved before this change → confirm it still renders and shows no "Attempt Recovery" (the existing `legacyColorDeprecation` is untouched; `inherit` only affects new content).

**Step 3:** Finish per superpowers:finishing-a-development-branch (PR or merge). PR body should note the companion `native-ui` change that sets `settings.custom.designsetgo.svgPattern` is a separate repo/PR.

---

## Risks & Notes

- **`useSettings` multi-return:** `useSettings('a','b',...)` returns an array in argument order. Keep the destructure order aligned with the paths.
- **Color format from theme:** the theme `color` may be a raw hex, a `var(--wp--preset--color--slug)`, or a `var:preset|color|slug`. PHP `resolve_color_value()` handles the `var(--wp--preset--color--slug)` form; the editor resolver (Task 3, Step 4) handles both `var:` and `var(--wp--…)`. Raw hex passes through both. If a kit stores a bare slug (no `var()`), it will fall through as an invalid color and the SVG builder swaps in `#9c92ac` — acceptable; document that kits should use `var(--wp--preset--color--slug)`.
- **No schema change / no new deprecation:** `inherit` is a new *value* of an existing `string` attribute. Existing content is byte-identical; the existing `legacyColorDeprecation` stays. Do NOT add a deprecation.
- **`isActive` gate:** `inherit` is deliberately not in `PATTERNS`/`PATTERN_IDS`, so every `PATTERNS[type]` / `PATTERN_IDS.includes(type)` guard must special-case it (Tasks 2–4). Grep for those guards before finishing.
- **Style imports:** no new CSS classes are introduced (reuse `has-dsgo-svg-pattern` + existing `--dsgo-svg-pattern-*` vars), so the MANDATORY style-import check is N/A — but the tiny `dsgo-svg-pattern-picker__inherit-note` styling, if any, goes in the extension's existing `editor.scss` (editor-only; the note never renders on the frontend).
- **Scope:** the Style-Kit value under `settings.custom.designsetgo.svgPattern` lives in the external `native-ui` repo — a separate PR, exactly like shape-divider Task 8.
