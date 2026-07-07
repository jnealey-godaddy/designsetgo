# Row/Grid Overlay + Hover-Variation Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `designsetgo/row` and `designsetgo/grid` detect style-kit overlay/hover block-style variations (`is-style-overlay-*`, `is-style-hover-{text,icon,button}-*`) and emit the matching activation class, exactly like `designsetgo/section` already does — and give Grid full overlay support (attribute + CSS + detection), which it never had.

**Architecture:** Extract Section's existing `hasOverlayStyleClass`/`hoverVariationClasses` detection into a shared, block-agnostic util (`src/utils/style-variation-classes.js`), wire it into Row's and Grid's `edit.js`/`save.js`, add matching CSS (including new overlay CSS for Grid), add a migration deprecation per block for already-saved content, and extend the icon/icon-button hover CSS to recognize the new Row/Grid activation classes.

**Tech Stack:** WordPress Gutenberg block API (`@wordpress/blocks`, `@wordpress/block-editor`), SCSS, Jest.

**Design doc:** `docs/plans/2026-07-07-row-grid-overlay-hover-parity-design.md`

## Global Constraints

- Tabs for JS/SCSS/PHP indentation.
- Text domain `designsetgo` for any new user-facing strings (this plan adds one: "Overlay Color" label in Grid's inspector, already used verbatim by Row — reuse the same string).
- Every deprecation MUST have `isEligible`, `save`, and `migrate` (per project convention) — silent migration, no "Attempt Recovery" prompts.
- `npm run build` must succeed and `grep` for new CSS selectors in `build/style-index.css` before considering a CSS task done (per project's Style Imports pre-commit check).
- No `console.log` statements.
- Run `npm run lint:js` and `npm run lint:css` before the final commit of each task that touches JS/SCSS.

---

### Task 1: Extract shared style-variation-classes utility

**Files:**
- Create: `src/utils/style-variation-classes.js`
- Create: `src/utils/test/style-variation-classes.test.js`
- Modify: `src/blocks/section/utils/has-overlay-style.js` (full rewrite — becomes a thin wrapper)

**Interfaces:**
- Produces: `hasOverlayStyleClass(className: string|undefined): boolean` and `hoverVariationClasses(className: string|undefined, blockClassName: string): string[]`, exported from `src/utils/style-variation-classes.js`. Every later task imports from this path with `../../utils/style-variation-classes` (two levels up from `src/blocks/{row,grid}/`).

- [ ] **Step 1: Write the failing test**

Create `src/utils/test/style-variation-classes.test.js`:

```js
/**
 * Shared style-variation → activation-class detection tests.
 */
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../style-variation-classes';

describe('hasOverlayStyleClass', () => {
	test('returns false for no className', () => {
		expect(hasOverlayStyleClass(undefined)).toBe(false);
		expect(hasOverlayStyleClass('')).toBe(false);
	});

	test('detects is-style-overlay-* variations', () => {
		expect(hasOverlayStyleClass('is-style-overlay-dark')).toBe(true);
		expect(hasOverlayStyleClass('is-style-overlay-light')).toBe(true);
	});

	test('ignores unrelated classNames', () => {
		expect(hasOverlayStyleClass('is-style-rounded')).toBe(false);
	});
});

describe('hoverVariationClasses', () => {
	test('returns empty array for no className', () => {
		expect(hoverVariationClasses(undefined, 'dsgo-flex')).toEqual([]);
	});

	test('maps hover-text variation to the given block prefix', () => {
		expect(
			hoverVariationClasses('is-style-hover-text-light', 'dsgo-flex')
		).toEqual(['dsgo-flex--has-hover-text']);
	});

	test('maps hover-icon variation to the given block prefix', () => {
		expect(
			hoverVariationClasses('is-style-hover-icon-blue', 'dsgo-grid')
		).toEqual(['dsgo-grid--has-hover-icon']);
	});

	test('maps hover-button variation to the given block prefix', () => {
		expect(
			hoverVariationClasses('is-style-hover-button-accent', 'dsgo-stack')
		).toEqual(['dsgo-stack--has-hover-button']);
	});

	test('returns multiple activation classes when multiple families are present', () => {
		expect(
			hoverVariationClasses(
				'is-style-hover-text-light is-style-hover-icon-blue',
				'dsgo-flex'
			)
		).toEqual(['dsgo-flex--has-hover-text', 'dsgo-flex--has-hover-icon']);
	});

	test('ignores unrelated classNames', () => {
		expect(hoverVariationClasses('is-style-rounded', 'dsgo-flex')).toEqual(
			[]
		);
	});
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx jest src/utils/test/style-variation-classes.test.js`
Expected: FAIL with "Cannot find module '../style-variation-classes'"

- [ ] **Step 3: Write the shared utility**

Create `src/utils/style-variation-classes.js`:

```js
/**
 * Style-variation → activation-class detection shared by DesignSetGo's
 * layout container blocks (Section, Row, Grid).
 *
 * Style kits express overlay/hover effects as block-style variations
 * (`is-style-overlay-*`, `is-style-hover-{text,icon,button}-*`) whose
 * stylesheet supplies the color via CSS rather than an inline custom
 * property. `Section_Styles` (`includes/features/class-section-styles.php`)
 * mirrors any such variation registered for `core/group`/`core/columns`/
 * `core/column` onto Section, Row, and Grid alike — making a variation
 * selectable in the editor Styles panel for all three — so each block must
 * still emit a stable activation class so its own (and, for hover-icon/
 * hover-button, a child block's) CSS can key off it. An inline
 * `[style*=…]` attribute selector can't see a variation stylesheet's vars.
 *
 * Used by both edit.js and save.js for each consuming block so the editor
 * preview and saved markup stay byte-identical.
 */

/**
 * @param {string} [className] The block's `className` attribute value.
 * @return {boolean} True when an overlay style variation is present.
 */
export function hasOverlayStyleClass(className) {
	if (!className || typeof className !== 'string') {
		return false;
	}

	return className
		.split(/\s+/)
		.some((token) => token.startsWith('is-style-overlay-'));
}

/**
 * Hover style-variation family prefixes and the activation-class suffix each
 * maps to. Each family activates exactly one effect (so a variation that
 * sets only some of the hover vars never clobbers the others).
 */
const HOVER_VARIATION_FAMILIES = [
	{ prefix: 'is-style-hover-text-', suffix: 'has-hover-text' },
	{ prefix: 'is-style-hover-icon-', suffix: 'has-hover-icon' },
	{ prefix: 'is-style-hover-button-', suffix: 'has-hover-button' },
];

/**
 * Resolve the hover activation classes a block should emit for the style
 * variations present on its `className`.
 *
 * @param {string} [className]    The block's `className` attribute value.
 * @param {string} blockClassName The block's own class prefix (e.g.
 *                                 `dsgo-stack`, `dsgo-flex`, `dsgo-grid`) —
 *                                 activation classes are emitted as
 *                                 `${blockClassName}--${suffix}`.
 * @return {string[]} Activation classes to add (possibly empty).
 */
export function hoverVariationClasses(className, blockClassName) {
	if (!className || typeof className !== 'string') {
		return [];
	}

	const tokens = className.split(/\s+/);

	return HOVER_VARIATION_FAMILIES.filter(({ prefix }) =>
		tokens.some((token) => token.startsWith(prefix))
	).map(({ suffix }) => `${blockClassName}--${suffix}`);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx jest src/utils/test/style-variation-classes.test.js`
Expected: PASS (9 tests)

- [ ] **Step 5: Rewrite Section's util as a thin wrapper**

Replace the entire contents of `src/blocks/section/utils/has-overlay-style.js` with:

```js
/**
 * Section overlay/hover style-variation detection.
 *
 * Thin Section-specific wrapper around the shared
 * `src/utils/style-variation-classes.js` detection, pinned to Section's own
 * `dsgo-stack` class prefix. See that module for the full behavior
 * description; kept here so Section's existing imports
 * (`./utils/has-overlay-style`) don't need to change.
 *
 * Used by both edit.js and save.js so the editor preview and saved markup stay
 * byte-identical.
 */

import {
	hasOverlayStyleClass as sharedHasOverlayStyleClass,
	hoverVariationClasses as sharedHoverVariationClasses,
} from '../../../utils/style-variation-classes';

export const hasOverlayStyleClass = sharedHasOverlayStyleClass;

/**
 * @param {string} [className] The block's `className` attribute value.
 * @return {string[]} Activation classes to add (possibly empty).
 */
export function hoverVariationClasses(className) {
	return sharedHoverVariationClasses(className, 'dsgo-stack');
}
```

- [ ] **Step 6: Run the full Section test suite to confirm zero regressions**

Run: `npx jest src/blocks/section --silent`
Expected: PASS, 35 tests (identical count/names to the pre-change baseline)

- [ ] **Step 7: Commit**

```bash
git add src/utils/style-variation-classes.js src/utils/test/style-variation-classes.test.js src/blocks/section/utils/has-overlay-style.js
git commit -m "refactor(section): extract style-variation class detection into a shared util"
```

---

### Task 2: Row — wire overlay/hover style-variation detection

**Files:**
- Modify: `src/blocks/row/save.js`
- Modify: `src/blocks/row/edit.js`
- Modify: `src/blocks/row/style.scss`
- Modify: `src/blocks/row/editor.scss`
- Test: `src/blocks/row/test/save.test.js` (new)

**Interfaces:**
- Consumes: `hasOverlayStyleClass`, `hoverVariationClasses` from Task 1 (`src/utils/style-variation-classes.js`).
- Produces: Row's `save()`/`edit.js` now emit `dsgo-flex--has-overlay` for a style-variation className (in addition to the existing `overlayColor`-driven path) and emit `dsgo-flex--has-hover-{text,icon,button}` activation classes. Task 3 (Row deprecation) needs today's PRE-this-task `save()` output frozen — see that task's save() body, copied verbatim from the current file before this task's edits.

- [ ] **Step 1: Write the failing test**

Create `src/blocks/row/test/save.test.js`:

```js
/**
 * Row Block - save.js Tests
 *
 * Verifies overlay and hover style-kit variations (`is-style-overlay-*`,
 * `is-style-hover-{text,icon,button}-*`) emit the matching activation class,
 * mirroring Section's behavior.
 */

// See section/test/save.test.js for why these must come from the NESTED
// `@wordpress/blocks` copy bundled by `@wordpress/block-editor`.
import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save });

describe('row save - overlay class', () => {
	test('no overlay by default', () => {
		const html = serialize(createBlock(metadata.name));
		expect(html).not.toContain('dsgo-flex--has-overlay');
	});

	test('overlayColor emits overlay class + inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { overlayColor: '#000000' })
		);
		expect(html).toContain('dsgo-flex--has-overlay');
		expect(html).toContain('--dsgo-overlay-color');
	});

	test('is-style-overlay-dark className emits overlay class without inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-dark' })
		);
		expect(html).toContain('dsgo-flex--has-overlay');
		expect(html).not.toContain('--dsgo-overlay-color');
	});

	test('unrelated is-style-* variation does not enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-rounded' })
		);
		expect(html).not.toContain('dsgo-flex--has-overlay');
	});
});

describe('row save - hover variation activation classes', () => {
	test('no hover activation classes by default', () => {
		const html = serialize(createBlock(metadata.name));
		expect(html).not.toContain('dsgo-flex--has-hover-text');
		expect(html).not.toContain('dsgo-flex--has-hover-icon');
		expect(html).not.toContain('dsgo-flex--has-hover-button');
	});

	test('is-style-hover-text-* emits only the hover-text activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-text-light',
			})
		);
		expect(html).toContain('dsgo-flex--has-hover-text');
		expect(html).not.toContain('dsgo-flex--has-hover-icon');
		expect(html).not.toContain('dsgo-flex--has-hover-button');
	});

	test('is-style-hover-icon-* emits only the hover-icon activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-icon-blue',
			})
		);
		expect(html).toContain('dsgo-flex--has-hover-icon');
		expect(html).not.toContain('dsgo-flex--has-hover-text');
	});

	test('is-style-hover-button-* emits only the hover-button activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-button-accent',
			})
		);
		expect(html).toContain('dsgo-flex--has-hover-button');
		expect(html).not.toContain('dsgo-flex--has-hover-text');
	});

	test('setting a hover attribute alone does NOT add an activation class (inline gate handles it)', () => {
		const html = serialize(
			createBlock(metadata.name, { hoverTextColor: '#ffffff' })
		);
		expect(html).not.toContain('dsgo-flex--has-hover-text');
		expect(html).toContain('--dsgo-hover-text-color');
	});
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx jest src/blocks/row/test/save.test.js`
Expected: FAIL — the `is-style-overlay-dark`, `is-style-hover-text-light`, `is-style-hover-icon-blue`, `is-style-hover-button-accent` cases fail (today's save.js doesn't derive these classes).

- [ ] **Step 3: Wire detection into `save.js`**

In `src/blocks/row/save.js`, find:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
```

Replace with:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
```

Then find:

```js
	// Build className with conditional classes
	const className = [
		'dsgo-flex',
		mobileStack && 'dsgo-flex--mobile-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		overlayColor && 'dsgo-flex--has-overlay',
	]
		.filter(Boolean)
		.join(' ');
```

Replace with:

```js
	// Overlay is enabled by an explicit overlayColor OR by a style-kit overlay
	// variation (is-style-overlay-*) applied via className. In the variation
	// case the color is supplied by the variation's stylesheet, so no inline
	// --dsgo-overlay-color is emitted below.
	const hasOverlay =
		!!overlayColor || hasOverlayStyleClass(attributes.className);

	// Build className with conditional classes. Hover activation classes are
	// emitted for hover style variations so their class-gated CSS can activate
	// (the inline-`style` gate can't see a variation stylesheet's vars).
	const className = [
		'dsgo-flex',
		mobileStack && 'dsgo-flex--mobile-stack',
		!constrainWidth && 'dsgo-no-width-constraint',
		hasOverlay && 'dsgo-flex--has-overlay',
		...hoverVariationClasses(attributes.className, 'dsgo-flex'),
	]
		.filter(Boolean)
		.join(' ');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx jest src/blocks/row/test/save.test.js`
Expected: PASS (11 tests)

- [ ] **Step 5: Mirror the wiring into `edit.js`**

In `src/blocks/row/edit.js`, find:

```js
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
```

Replace with:

```js
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
```

Then find:

```js
	// Block wrapper props - outer div stays full width (must match save.js EXACTLY)
	const blockClassName = [
		'dsgo-flex',
		mobileStack && 'dsgo-flex--mobile-stack',
		overlayColor && 'dsgo-flex--has-overlay',
	]
		.filter(Boolean)
		.join(' ');
```

Replace with:

```js
	// Block wrapper props - outer div stays full width (must match save.js EXACTLY)
	const hasOverlay = !!overlayColor || hasOverlayStyleClass(className);
	const blockClassName = [
		'dsgo-flex',
		mobileStack && 'dsgo-flex--mobile-stack',
		hasOverlay && 'dsgo-flex--has-overlay',
		...hoverVariationClasses(className, 'dsgo-flex'),
	]
		.filter(Boolean)
		.join(' ');
```

(`className` is already destructured from `attributes` at the top of `RowEdit`, so no destructure change is needed.)

- [ ] **Step 6: Add the hover-text class-gated CSS rule to `style.scss`**

In `src/blocks/row/style.scss`, find:

```scss
	// Hover background and text colors using CSS custom properties
	// CRITICAL: Only apply hover when variables are actually set on THIS element
	// This prevents hover bleeding to adjacent containers that don't have hover settings
	&:hover {
		// Only apply background if hover background is set
		// !important needed to override WordPress color classes and gradients
		// Use 'background' instead of 'background-color' to override gradients
		&[style*="--dsgo-hover-bg-color"] {
			background: var(--dsgo-hover-bg-color) !important;
		}

		// Only apply text color if hover text color is set
		// Use !important to override custom text colors set by users
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			// Apply text color to all text elements for consistency
			// !important needed to override custom color classes/inline styles
			h1,
			h2,
			h3,
			h4,
			h5,
			h6,
			p,
			a,
			span,
			li,
			td,
			th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}
}
```

Replace with:

```scss
	// Hover background and text colors using CSS custom properties
	// CRITICAL: Only apply hover when variables are actually set on THIS element
	// This prevents hover bleeding to adjacent containers that don't have hover settings
	&:hover {
		// Only apply background if hover background is set
		// !important needed to override WordPress color classes and gradients
		// Use 'background' instead of 'background-color' to override gradients
		&[style*="--dsgo-hover-bg-color"] {
			background: var(--dsgo-hover-bg-color) !important;
		}

		// Only apply text color if hover text color is set
		// Use !important to override custom text colors set by users
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			// Apply text color to all text elements for consistency
			// !important needed to override custom color classes/inline styles
			h1,
			h2,
			h3,
			h4,
			h5,
			h6,
			p,
			a,
			span,
			li,
			td,
			th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}

	// Hover text color activation class — for style-kit `is-style-hover-text-*`
	// variations, whose stylesheet supplies the var (the inline `[style*=…]`
	// gate can't see a variation stylesheet's var).
	&--has-hover-text:hover {
		color: var(--dsgo-hover-text-color) !important;

		h1,
		h2,
		h3,
		h4,
		h5,
		h6,
		p,
		a,
		span,
		li,
		td,
		th,
		.wp-block-heading,
		.wp-block-paragraph {
			color: var(--dsgo-hover-text-color) !important;
		}
	}
}
```

- [ ] **Step 7: Mirror the same rule into `editor.scss`**

In `src/blocks/row/editor.scss`, find:

```scss
	// CRITICAL: Must duplicate from style.scss for editor/frontend parity
	// Hover background and text colors using CSS custom properties
	&:hover {
		// Only apply background if hover background is set
		// !important needed to override WordPress color classes and gradients
		// Use 'background' instead of 'background-color' to override gradients
		&[style*="--dsgo-hover-bg-color"] {
			background: var(--dsgo-hover-bg-color) !important;
		}

		// Only apply text color if hover text color is set
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			h1,
			h2,
			h3,
			h4,
			h5,
			h6,
			p,
			a,
			span,
			li,
			td,
			th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}
}
```

Replace with:

```scss
	// CRITICAL: Must duplicate from style.scss for editor/frontend parity
	// Hover background and text colors using CSS custom properties
	&:hover {
		// Only apply background if hover background is set
		// !important needed to override WordPress color classes and gradients
		// Use 'background' instead of 'background-color' to override gradients
		&[style*="--dsgo-hover-bg-color"] {
			background: var(--dsgo-hover-bg-color) !important;
		}

		// Only apply text color if hover text color is set
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			h1,
			h2,
			h3,
			h4,
			h5,
			h6,
			p,
			a,
			span,
			li,
			td,
			th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}

	// Hover text color activation class — for style-kit `is-style-hover-text-*`
	// variations, whose stylesheet supplies the var (the inline `[style*=…]`
	// gate can't see a variation stylesheet's var).
	&--has-hover-text:hover {
		color: var(--dsgo-hover-text-color) !important;

		h1,
		h2,
		h3,
		h4,
		h5,
		h6,
		p,
		a,
		span,
		li,
		td,
		th,
		.wp-block-heading,
		.wp-block-paragraph {
			color: var(--dsgo-hover-text-color) !important;
		}
	}
}
```

- [ ] **Step 8: Build and verify CSS output**

Run: `npm run build`
Expected: build succeeds with no SCSS errors.

Run: `grep -c "dsgo-flex--has-hover-text" build/style-index.css build/index.css`
Expected: at least 1 match in each file.

- [ ] **Step 9: Run the full Row test suite**

Run: `npx jest src/blocks/row --silent`
Expected: PASS (11 tests)

- [ ] **Step 10: Lint**

Run: `npm run lint:js -- src/blocks/row/save.js src/blocks/row/edit.js`
Run: `npm run lint:css -- src/blocks/row/style.scss src/blocks/row/editor.scss`
Expected: no errors.

- [ ] **Step 11: Commit**

```bash
git add src/blocks/row/save.js src/blocks/row/edit.js src/blocks/row/style.scss src/blocks/row/editor.scss src/blocks/row/test/save.test.js
git commit -m "feat(row): detect style-kit overlay/hover variations, mirroring section"
```

---

### Task 3: Row — deprecation for pre-detection content

**Files:**
- Modify: `src/blocks/row/deprecated.js`
- Test: `src/blocks/row/test/deprecated.test.js` (new)

**Interfaces:**
- Consumes: `hasOverlayStyleClass`, `hoverVariationClasses` from Task 1; Row's current `block.json` schema (unchanged by this task).
- Produces: a new deprecation entry `v5`, prepended to `src/blocks/row/deprecated.js`'s exported array, so already-saved Row content with a style-variation className but no matching activation class in stored HTML migrates silently instead of showing "Attempt Recovery".

- [ ] **Step 1: Write the failing test**

Create `src/blocks/row/test/deprecated.test.js`:

```js
/**
 * Row Block - Style-Variation Deprecation Migration Tests
 *
 * Verifies OLD rows saved before style-kit overlay/hover variation detection
 * existed still parse cleanly against the CURRENT save() + deprecations
 * pipeline instead of showing WordPress's "unexpected or invalid content /
 * Attempt Recovery" warning. Mirrors section/test/deprecated.test.js's v7/v8
 * coverage.
 */

import {
	registerBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

describe('row deprecations - style-kit overlay variation migration', () => {
	// deprecated.js exports newest-first: [v5, v4, v3, v2, v1].
	const [v5Deprecation] = deprecated;

	const canonicalOverlayMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-overlay-dark' })
	);
	const OLD_OVERLAY_VARIATION_MARKUP = canonicalOverlayMarkup.replace(
		' dsgo-flex--has-overlay',
		''
	);

	test('canonical markup carries the overlay class', () => {
		expect(canonicalOverlayMarkup).toContain('dsgo-flex--has-overlay');
		expect(OLD_OVERLAY_VARIATION_MARKUP).not.toContain(
			'dsgo-flex--has-overlay'
		);
	});

	test('old is-style-overlay-dark row (no overlay class) migrates silently against current save()', () => {
		const [block] = parse(OLD_OVERLAY_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/row');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-overlay-dark');
		expect(getBlockContent(block)).toContain('dsgo-flex--has-overlay');
	});

	test('isEligible detects an overlay variation lacking the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-row is-style-overlay-dark dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: 'is-style-overlay-dark' }, [], {
				innerHTML: html,
			})
		).toBe(true);
	});

	test('isEligible ignores rows that already carry the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-row is-style-overlay-dark dsgo-flex dsgo-flex--has-overlay"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: 'is-style-overlay-dark' }, [], {
				innerHTML: html,
			})
		).toBe(false);
	});

	test('isEligible ignores rows without an overlay variation', () => {
		const html =
			'<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: '' }, [], { innerHTML: html })
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = { className: 'is-style-overlay-dark', overlayColor: '' };
		expect(v5Deprecation.migrate(attrs)).toBe(attrs);
	});
});

describe('row deprecations - style-kit hover variation migration', () => {
	const [v5Deprecation] = deprecated;

	const canonicalHoverMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-hover-text-light' })
	);
	const OLD_HOVER_VARIATION_MARKUP = canonicalHoverMarkup.replace(
		' dsgo-flex--has-hover-text',
		''
	);

	test('canonical markup carries the hover-text activation class', () => {
		expect(canonicalHoverMarkup).toContain('dsgo-flex--has-hover-text');
		expect(OLD_HOVER_VARIATION_MARKUP).not.toContain(
			'dsgo-flex--has-hover-text'
		);
	});

	test('old is-style-hover-text-light row (no activation class) migrates silently against current save()', () => {
		const [block] = parse(OLD_HOVER_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/row');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-hover-text-light');
		expect(getBlockContent(block)).toContain('dsgo-flex--has-hover-text');
	});

	test('isEligible detects a hover-icon variation lacking its activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-row is-style-hover-icon-blue dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible(
				{ className: 'is-style-hover-icon-blue' },
				[],
				{ innerHTML: html }
			)
		).toBe(true);
	});

	test('isEligible ignores rows without a hover variation', () => {
		const html =
			'<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: '' }, [], { innerHTML: html })
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = {
			className: 'is-style-hover-text-light',
			hoverTextColor: '',
		};
		expect(v5Deprecation.migrate(attrs)).toBe(attrs);
	});
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx jest src/blocks/row/test/deprecated.test.js`
Expected: FAIL with "Cannot destructure property 'isEligible' of 'undefined'" or similar — `deprecated[0]` doesn't exist as `v5` yet (array is currently `[v4, v3, v2, v1]`).

- [ ] **Step 3: Add the `v5` deprecation**

In `src/blocks/row/deprecated.js`, find the import block:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
```

Replace with:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
import metadata from './block.json';
```

Then find:

```js
// Version 4: Before flex-wrap fallback was aligned with block.json default.
```

Insert immediately before it (i.e. right after the closing `};` of the `getAlignItemsValue` function and the `sharedSupports` constant, before the `// Version 4` comment):

```js
// Version 5: Before style-kit overlay/hover variation detection. The current
// save() also emits `dsgo-flex--has-overlay` when a style-kit overlay
// variation (`is-style-overlay-*`) is present on className, and emits
// `dsgo-flex--has-hover-{text,icon,button}` activation classes for the
// matching `is-style-hover-*` variation families — both mirroring Section's
// behavior. Rows saved with such a variation but no matching class in their
// stored HTML fail validation against the new save().
//
// isEligible targets exactly that signature (a variation on className with
// no matching class in the stored HTML) so those rows migrate SILENTLY.
// save() reproduces this block's pre-change output (overlay class from
// overlayColor only, no hover activation classes) so it also byte-matches on
// WP versions that still validate the deprecation's save() before migrating.
// migrate() is a passthrough — only the serialised class differs, not the
// attribute values; the current save() then re-renders with the classes
// derived from the variation.
const v5 = {
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	isEligible(attributes, innerBlocks, { innerHTML }) {
		if (!innerHTML || !innerHTML.includes('dsgo-flex')) {
			return false;
		}

		const overlayMismatch =
			hasOverlayStyleClass(attributes.className) &&
			!innerHTML.includes('dsgo-flex--has-overlay');

		const hoverMismatch = hoverVariationClasses(
			attributes.className,
			'dsgo-flex'
		).some((activationClass) => !innerHTML.includes(activationClass));

		return overlayMismatch || hoverMismatch;
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			overlayColor,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			mobileStack,
			layout,
		} = attributes;

		// Pre-change className: overlay class from overlayColor ONLY, no hover
		// activation classes.
		const className = [
			'dsgo-flex',
			mobileStack && 'dsgo-flex--mobile-stack',
			!constrainWidth && 'dsgo-no-width-constraint',
			overlayColor && 'dsgo-flex--has-overlay',
		]
			.filter(Boolean)
			.join(' ');

		const TagName = tagName || 'div';
		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertColorToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertColorToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
				...(overlayColor && {
					'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}),
			},
		});

		const rawGapValue = attributes.style?.spacing?.blockGap;
		const gapValue = convertPresetToCSSVar(rawGapValue);

		if (blockProps.style?.gap) {
			delete blockProps.style.gap;
		}

		const alignItems = getAlignItemsValue(layout?.verticalAlignment);
		const innerStyle = {
			display: 'flex',
			justifyContent: layout?.justifyContent || 'left',
			...(alignItems && { alignItems }),
			flexWrap: layout?.flexWrap || 'nowrap',
			...(gapValue && { gap: gapValue }),
		};

		if (constrainWidth) {
			innerStyle.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyle.marginLeft = 'auto';
			innerStyle.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-flex__inner',
			style: innerStyle,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		// Only the serialised class differs; the current save() derives it
		// from the style variation on className, so no attribute change.
		return attributes;
	},
};

```

Finally, find:

```js
export default [v4, v3, v2, v1];
```

Replace with:

```js
export default [v5, v4, v3, v2, v1];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx jest src/blocks/row/test/deprecated.test.js`
Expected: PASS (11 tests)

- [ ] **Step 5: Run the full Row test suite**

Run: `npx jest src/blocks/row --silent`
Expected: PASS (22 tests total across save.test.js + deprecated.test.js)

- [ ] **Step 6: Lint**

Run: `npm run lint:js -- src/blocks/row/deprecated.js`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/blocks/row/deprecated.js src/blocks/row/test/deprecated.test.js
git commit -m "fix(row): add v5 deprecation for style-variation activation classes"
```

---

### Task 4: Grid — add overlay support and wire hover-variation detection

**Files:**
- Modify: `src/blocks/grid/block.json`
- Modify: `src/blocks/grid/edit.js`
- Modify: `src/blocks/grid/save.js`
- Modify: `src/blocks/grid/style.scss`
- Modify: `src/blocks/grid/editor.scss`
- Test: `src/blocks/grid/test/save.test.js` (new)

**Interfaces:**
- Consumes: `hasOverlayStyleClass`, `hoverVariationClasses` from Task 1.
- Produces: Grid gains an `overlayColor` attribute (default `''`), an "Overlay Color" inspector control, `dsgo-grid--has-overlay` CSS, and `dsgo-grid--has-hover-{text,icon,button}` activation classes. Task 5 (Grid deprecation) needs today's PRE-this-task `save()` output frozen (no overlay logic, no hover activation classes at all) — see that task's save() body.

- [ ] **Step 1: Write the failing test**

Create `src/blocks/grid/test/save.test.js`:

```js
/**
 * Grid Block - save.js Tests
 *
 * Verifies overlay support (new: attribute + style-kit `is-style-overlay-*`
 * variation) and hover style-kit variations
 * (`is-style-hover-{text,icon,button}-*`) emit the matching activation
 * class, mirroring Section/Row's behavior.
 */

import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save });

describe('grid save - overlay class', () => {
	test('no overlay by default', () => {
		const html = serialize(createBlock(metadata.name));
		expect(html).not.toContain('dsgo-grid--has-overlay');
	});

	test('overlayColor emits overlay class + inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { overlayColor: '#000000' })
		);
		expect(html).toContain('dsgo-grid--has-overlay');
		expect(html).toContain('--dsgo-overlay-color');
	});

	test('is-style-overlay-dark className emits overlay class without inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-dark' })
		);
		expect(html).toContain('dsgo-grid--has-overlay');
		expect(html).not.toContain('--dsgo-overlay-color');
	});

	test('unrelated is-style-* variation does not enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-rounded' })
		);
		expect(html).not.toContain('dsgo-grid--has-overlay');
	});
});

describe('grid save - hover variation activation classes', () => {
	test('no hover activation classes by default', () => {
		const html = serialize(createBlock(metadata.name));
		expect(html).not.toContain('dsgo-grid--has-hover-text');
		expect(html).not.toContain('dsgo-grid--has-hover-icon');
		expect(html).not.toContain('dsgo-grid--has-hover-button');
	});

	test('is-style-hover-text-* emits only the hover-text activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-text-light',
			})
		);
		expect(html).toContain('dsgo-grid--has-hover-text');
		expect(html).not.toContain('dsgo-grid--has-hover-icon');
		expect(html).not.toContain('dsgo-grid--has-hover-button');
	});

	test('is-style-hover-icon-* emits only the hover-icon activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-icon-blue',
			})
		);
		expect(html).toContain('dsgo-grid--has-hover-icon');
		expect(html).not.toContain('dsgo-grid--has-hover-text');
	});

	test('is-style-hover-button-* emits only the hover-button activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-button-accent',
			})
		);
		expect(html).toContain('dsgo-grid--has-hover-button');
		expect(html).not.toContain('dsgo-grid--has-hover-text');
	});

	test('setting a hover attribute alone does NOT add an activation class (inline gate handles it)', () => {
		const html = serialize(
			createBlock(metadata.name, { hoverTextColor: '#ffffff' })
		);
		expect(html).not.toContain('dsgo-grid--has-hover-text');
		expect(html).toContain('--dsgo-hover-text-color');
	});
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx jest src/blocks/grid/test/save.test.js`
Expected: FAIL — `overlayColor` isn't a registered attribute yet and no overlay/hover classes are derived.

- [ ] **Step 3: Add `overlayColor` to `block.json`**

In `src/blocks/grid/block.json`, find:

```json
		"hoverButtonBackgroundColor": {
			"type": "string",
			"default": ""
		},
		"constrainWidth": {
```

Replace with:

```json
		"hoverButtonBackgroundColor": {
			"type": "string",
			"default": ""
		},
		"overlayColor": {
			"type": "string",
			"default": ""
		},
		"constrainWidth": {
```

- [ ] **Step 4: Wire detection into `save.js`**

In `src/blocks/grid/save.js`, find:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
```

Replace with:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
```

Then find:

```js
	const {
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
	} = attributes;

	// Build className with conditional classes
	const className = [
		'dsgo-grid',
		`dsgo-grid-cols-${desktopColumns}`,
		`dsgo-grid-cols-tablet-${tabletColumns}`,
		`dsgo-grid-cols-mobile-${mobileColumns}`,
		!constrainWidth && 'dsgo-no-width-constraint',
	]
		.filter(Boolean)
		.join(' ');

	// Block wrapper props - outer div stays full width
	const TagName = tagName || 'div';
	const blockProps = useBlockProps.save({
		className,
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
		},
	});
```

Replace with:

```js
	const {
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		overlayColor,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
	} = attributes;

	// Overlay is enabled by an explicit overlayColor OR by a style-kit overlay
	// variation (is-style-overlay-*) applied via className. In the variation
	// case the color is supplied by the variation's stylesheet, so no inline
	// --dsgo-overlay-color is emitted below.
	const hasOverlay =
		!!overlayColor || hasOverlayStyleClass(attributes.className);

	// Build className with conditional classes. Hover activation classes are
	// emitted for hover style variations so their class-gated CSS can activate
	// (the inline-`style` gate can't see a variation stylesheet's vars).
	const className = [
		'dsgo-grid',
		`dsgo-grid-cols-${desktopColumns}`,
		`dsgo-grid-cols-tablet-${tabletColumns}`,
		`dsgo-grid-cols-mobile-${mobileColumns}`,
		!constrainWidth && 'dsgo-no-width-constraint',
		hasOverlay && 'dsgo-grid--has-overlay',
		...hoverVariationClasses(attributes.className, 'dsgo-grid'),
	]
		.filter(Boolean)
		.join(' ');

	// Block wrapper props - outer div stays full width
	const TagName = tagName || 'div';
	const blockProps = useBlockProps.save({
		className,
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': '0.8',
			}),
		},
	});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `npx jest src/blocks/grid/test/save.test.js`
Expected: PASS (11 tests)

- [ ] **Step 6: Wire the same logic + inspector control into `edit.js`**

In `src/blocks/grid/edit.js`, find:

```js
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
```

Replace with:

```js
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
```

Then find:

```js
	const {
		align,
		className,
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		textAlign,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
	} = attributes;
```

Replace with:

```js
	const {
		align,
		className,
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		textAlign,
		overlayColor,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
	} = attributes;
```

Then find:

```js
	// Block wrapper props - outer div stays full width (must match save.js EXACTLY)
	const TagName = tagName || 'div';
	const blockProps = useBlockProps({
		className: `dsgo-grid dsgo-grid-cols-${desktopColumns} dsgo-grid-cols-tablet-${tabletColumns} dsgo-grid-cols-mobile-${mobileColumns}`,
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
		},
	});
```

Replace with:

```js
	// Block wrapper props - outer div stays full width (must match save.js EXACTLY)
	const hasOverlay = !!overlayColor || hasOverlayStyleClass(className);
	const TagName = tagName || 'div';
	const blockProps = useBlockProps({
		className: [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-tablet-${tabletColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			hasOverlay && 'dsgo-grid--has-overlay',
			...hoverVariationClasses(className, 'dsgo-grid'),
		]
			.filter(Boolean)
			.join(' '),
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': '0.8',
			}),
		},
	});
```

Then add the "Overlay Color" inspector control. Find:

```js
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover Settings', 'designsetgo')}
					settings={[
						{
							label: __('Hover Background Color', 'designsetgo'),
```

Replace with:

```js
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover Settings', 'designsetgo')}
					settings={[
						{
							label: __('Overlay Color', 'designsetgo'),
							colorValue: decodeColorValue(
								overlayColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									overlayColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Hover Background Color', 'designsetgo'),
```

- [ ] **Step 7: Add the overlay CSS block + hover-text class gate to `style.scss`**

In `src/blocks/grid/style.scss`, find:

```scss
	// CRITICAL: Ensure padding is included in the total width to prevent overflow
	box-sizing: border-box;

	// Smooth transition for hover background color
	transition: background-color 0.3s ease;

	// Default padding using WP standard spacing slugs
```

Replace with:

```scss
	// CRITICAL: Ensure padding is included in the total width to prevent overflow
	box-sizing: border-box;

	// Positioning context for the overlay ::before pseudo-element below.
	position: relative;

	// Smooth transition for hover background color
	transition: background-color 0.3s ease;

	/**
	 * Background overlay
	 * Only rendered when overlay color is set (attribute or style variation)
	 */
	&.dsgo-grid--has-overlay {
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			inset: 0;
			background-color: var(--dsgo-overlay-color);
			opacity: var(--dsgo-overlay-opacity, 0.8);
			pointer-events: none;
			z-index: 1;
		}

		> .dsgo-grid__inner {
			position: relative;
			z-index: 2;
		}
	}

	// Default padding using WP standard spacing slugs
```

Then find:

```scss
		// Only apply text color if hover text color is set
		// Use !important to override custom text colors set by users
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			// Apply text color to all text elements for consistency
			// !important needed to override custom color classes/inline styles
			h1, h2, h3, h4, h5, h6, p, a, span, li, td, th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}
}

// ===================================================================
// PERFORMANCE OPTIMIZATION (2025-11-11)
```

Replace with:

```scss
		// Only apply text color if hover text color is set
		// Use !important to override custom text colors set by users
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			// Apply text color to all text elements for consistency
			// !important needed to override custom color classes/inline styles
			h1, h2, h3, h4, h5, h6, p, a, span, li, td, th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}

	// Hover text color activation class — for style-kit `is-style-hover-text-*`
	// variations, whose stylesheet supplies the var (the inline `[style*=…]`
	// gate can't see a variation stylesheet's var).
	&--has-hover-text:hover {
		color: var(--dsgo-hover-text-color) !important;

		h1, h2, h3, h4, h5, h6, p, a, span, li, td, th,
		.wp-block-heading,
		.wp-block-paragraph {
			color: var(--dsgo-hover-text-color) !important;
		}
	}
}

// ===================================================================
// PERFORMANCE OPTIMIZATION (2025-11-11)
```

- [ ] **Step 8: Add the same to `editor.scss` (with correct placement for CSS specificity)**

`editor.scss` already sets `.dsgo-grid { position: relative; }` (line 49) and `.dsgo-grid__inner { position: static; ... }` inside a large block. The new `&.dsgo-grid--has-overlay > .dsgo-grid__inner { position: relative; }` rule has the SAME specificity as that `position: static` rule (both are two classes deep), so it must appear LATER in the file to win the cascade. Add it immediately AFTER that large `.dsgo-grid__inner { ... }` block closes, not before.

In `src/blocks/grid/editor.scss`, find:

```scss
		// Also handle the [data-block] wrapper itself for proper sizing
		> [data-block]:has(> .dsgo-card) {
			min-width: 0;
			max-width: 100%;
		}
	}

	// CRITICAL: When grid has content, position appender at outer container corner
```

Replace with:

```scss
		// Also handle the [data-block] wrapper itself for proper sizing
		> [data-block]:has(> .dsgo-card) {
			min-width: 0;
			max-width: 100%;
		}
	}

	/**
	 * Background overlay
	 * Only rendered when overlay color is set (attribute or style variation)
	 * Placed AFTER the `.dsgo-grid__inner` block above so its
	 * `position: relative` here wins over that block's `position: static`
	 * (same class-selector specificity — source order decides).
	 */
	&.dsgo-grid--has-overlay {
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			inset: 0;
			background-color: var(--dsgo-overlay-color);
			opacity: var(--dsgo-overlay-opacity, 0.8);
			pointer-events: none;
			z-index: 1;
		}

		> .dsgo-grid__inner {
			position: relative;
			z-index: 2;
		}
	}

	// CRITICAL: When grid has content, position appender at outer container corner
```

Then find:

```scss
		// Only apply text color if hover text color is set
		// Use !important to override custom text colors set by users
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			// Apply text color to all text elements for consistency
			// !important needed to override custom color classes/inline styles
			h1, h2, h3, h4, h5, h6, p, a, span, li, td, th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}
}

// ===================================================================
// PERFORMANCE OPTIMIZATION (2025-11-11)
```

Replace with:

```scss
		// Only apply text color if hover text color is set
		// Use !important to override custom text colors set by users
		&[style*="--dsgo-hover-text-color"] {
			color: var(--dsgo-hover-text-color) !important;

			// Apply text color to all text elements for consistency
			// !important needed to override custom color classes/inline styles
			h1, h2, h3, h4, h5, h6, p, a, span, li, td, th,
			.wp-block-heading,
			.wp-block-paragraph {
				color: var(--dsgo-hover-text-color) !important;
			}
		}
	}

	// Hover text color activation class — for style-kit `is-style-hover-text-*`
	// variations, whose stylesheet supplies the var (the inline `[style*=…]`
	// gate can't see a variation stylesheet's var).
	&--has-hover-text:hover {
		color: var(--dsgo-hover-text-color) !important;

		h1, h2, h3, h4, h5, h6, p, a, span, li, td, th,
		.wp-block-heading,
		.wp-block-paragraph {
			color: var(--dsgo-hover-text-color) !important;
		}
	}
}

// ===================================================================
// PERFORMANCE OPTIMIZATION (2025-11-11)
```

(Verified: `// PERFORMANCE OPTIMIZATION (2025-11-11)` appears exactly once in `editor.scss`, so this anchor is unique.)

- [ ] **Step 9: Build and verify CSS output**

Run: `npm run build`
Expected: build succeeds with no SCSS errors.

Run: `grep -c "dsgo-grid--has-overlay" build/style-index.css build/index.css`
Run: `grep -c "dsgo-grid--has-hover-text" build/style-index.css build/index.css`
Expected: at least 1 match in each file for both greps.

- [ ] **Step 10: Run the full Grid test suite**

Run: `npx jest src/blocks/grid --silent`
Expected: PASS (11 tests)

- [ ] **Step 11: Lint**

Run: `npm run lint:js -- src/blocks/grid/save.js src/blocks/grid/edit.js`
Run: `npm run lint:css -- src/blocks/grid/style.scss src/blocks/grid/editor.scss`
Expected: no errors.

- [ ] **Step 12: Commit**

```bash
git add src/blocks/grid/block.json src/blocks/grid/save.js src/blocks/grid/edit.js src/blocks/grid/style.scss src/blocks/grid/editor.scss src/blocks/grid/test/save.test.js
git commit -m "feat(grid): add overlay support and detect style-kit hover variations"
```

---

### Task 5: Grid — deprecation for pre-overlay/pre-detection content

**Files:**
- Modify: `src/blocks/grid/deprecated.js`
- Test: `src/blocks/grid/test/deprecated.test.js` (new)

**Interfaces:**
- Consumes: `hasOverlayStyleClass`, `hoverVariationClasses` from Task 1; `metadata` (already imported in this file from `./block.json`) — now includes `overlayColor` after Task 4.
- Produces: a new deprecation entry `styleVariationClasses`, prepended to `src/blocks/grid/deprecated.js`'s exported array.

- [ ] **Step 1: Write the failing test**

Create `src/blocks/grid/test/deprecated.test.js`:

```js
/**
 * Grid Block - Style-Variation Deprecation Migration Tests
 *
 * Verifies OLD grids saved before overlay support existed (and before hover
 * style-kit variation detection existed) still parse cleanly against the
 * CURRENT save() + deprecations pipeline instead of showing WordPress's
 * "unexpected or invalid content / Attempt Recovery" warning.
 */

import {
	registerBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

describe('grid deprecations - style-kit overlay variation migration', () => {
	// deprecated.js exports newest-first: [styleVariationClasses, legacyMinWidth, v1].
	const [styleVariationClassesDeprecation] = deprecated;

	const canonicalOverlayMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-overlay-dark' })
	);
	const OLD_OVERLAY_VARIATION_MARKUP = canonicalOverlayMarkup.replace(
		' dsgo-grid--has-overlay',
		''
	);

	test('canonical markup carries the overlay class', () => {
		expect(canonicalOverlayMarkup).toContain('dsgo-grid--has-overlay');
		expect(OLD_OVERLAY_VARIATION_MARKUP).not.toContain(
			'dsgo-grid--has-overlay'
		);
	});

	test('old is-style-overlay-dark grid (no overlay class) migrates silently against current save()', () => {
		const [block] = parse(OLD_OVERLAY_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/grid');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-overlay-dark');
		expect(getBlockContent(block)).toContain('dsgo-grid--has-overlay');
	});

	test('isEligible detects an overlay variation lacking the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-grid is-style-overlay-dark dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ innerHTML: html }
			)
		).toBe(true);
	});

	test('isEligible ignores grids that already carry the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-grid is-style-overlay-dark dsgo-grid dsgo-grid--has-overlay"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: 'is-style-overlay-dark' },
				[],
				{ innerHTML: html }
			)
		).toBe(false);
	});

	test('isEligible ignores grids without an overlay variation', () => {
		const html =
			'<div class="wp-block-designsetgo-grid dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: '' },
				[],
				{ innerHTML: html }
			)
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = { className: 'is-style-overlay-dark', overlayColor: '' };
		expect(styleVariationClassesDeprecation.migrate(attrs)).toBe(attrs);
	});
});

describe('grid deprecations - style-kit hover variation migration', () => {
	const [styleVariationClassesDeprecation] = deprecated;

	const canonicalHoverMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-hover-text-light' })
	);
	const OLD_HOVER_VARIATION_MARKUP = canonicalHoverMarkup.replace(
		' dsgo-grid--has-hover-text',
		''
	);

	test('canonical markup carries the hover-text activation class', () => {
		expect(canonicalHoverMarkup).toContain('dsgo-grid--has-hover-text');
		expect(OLD_HOVER_VARIATION_MARKUP).not.toContain(
			'dsgo-grid--has-hover-text'
		);
	});

	test('old is-style-hover-text-light grid (no activation class) migrates silently against current save()', () => {
		const [block] = parse(OLD_HOVER_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/grid');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-hover-text-light');
		expect(getBlockContent(block)).toContain('dsgo-grid--has-hover-text');
	});

	test('isEligible detects a hover-icon variation lacking its activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-grid is-style-hover-icon-blue dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: 'is-style-hover-icon-blue' },
				[],
				{ innerHTML: html }
			)
		).toBe(true);
	});

	test('isEligible ignores grids without a hover variation', () => {
		const html =
			'<div class="wp-block-designsetgo-grid dsgo-grid"><div class="dsgo-grid__inner"></div></div>';
		expect(
			styleVariationClassesDeprecation.isEligible(
				{ className: '' },
				[],
				{ innerHTML: html }
			)
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = {
			className: 'is-style-hover-text-light',
			hoverTextColor: '',
		};
		expect(styleVariationClassesDeprecation.migrate(attrs)).toBe(attrs);
	});
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx jest src/blocks/grid/test/deprecated.test.js`
Expected: FAIL — `deprecated[0]` is currently `legacyMinWidth`, not the new deprecation.

- [ ] **Step 3: Add the `styleVariationClasses` deprecation**

In `src/blocks/grid/deprecated.js`, find:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import metadata from './block.json';
```

Replace with:

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
import metadata from './block.json';
```

Then find:

```js
// Version 1: Before align attribute - used className for alignment
const v1 = {
```

Insert immediately before it:

```js
// Before style-kit overlay/hover variation detection (and, for overlay,
// before the `overlayColor` attribute existed at all). The current save()
// emits `dsgo-grid--has-overlay` when a style-kit overlay variation
// (`is-style-overlay-*`) is present on className (or `overlayColor` is set),
// and emits `dsgo-grid--has-hover-{text,icon,button}` activation classes for
// the matching `is-style-hover-*` variation families — mirroring Section's
// behavior. Grids saved with such a variation but no matching class in their
// stored HTML fail validation against the new save().
//
// Note the asymmetry with Row's equivalent deprecation: the overlay branch
// here can ONLY ever be reached via a className variation, never via
// `overlayColor` — that attribute didn't exist on Grid before this change, so
// no stored Grid content could have set it. That's intentional, not a gap to
// "fix" for symmetry.
//
// isEligible targets exactly that signature (a variation on className with
// no matching class in the stored HTML) so those grids migrate SILENTLY.
// save() reproduces this file's pre-change output (no overlay logic at all,
// no hover activation classes) so it also byte-matches on WP versions that
// still validate the deprecation's save() before migrating. migrate() is a
// passthrough — only the serialised class differs, not the attribute
// values; the current save() then re-renders with the classes derived from
// the variation (and, for overlay, the new overlayColor attribute default
// of '').
const styleVariationClasses = {
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	isEligible(attributes, innerBlocks, { innerHTML }) {
		if (!innerHTML || !innerHTML.includes('dsgo-grid')) {
			return false;
		}

		const overlayMismatch =
			hasOverlayStyleClass(attributes.className) &&
			!innerHTML.includes('dsgo-grid--has-overlay');

		const hoverMismatch = hoverVariationClasses(
			attributes.className,
			'dsgo-grid'
		).some((activationClass) => !innerHTML.includes(activationClass));

		return overlayMismatch || hoverMismatch;
	},
	save({ attributes }) {
		const {
			tagName = 'div',
			constrainWidth,
			contentWidth,
			desktopColumns,
			tabletColumns,
			mobileColumns,
			rowGap,
			columnGap,
			alignItems,
			columnMinWidth,
			hoverBackgroundColor,
			hoverTextColor,
			hoverIconBackgroundColor,
			hoverButtonBackgroundColor,
			style,
		} = attributes;

		// Pre-change className: no overlay support at all, no hover
		// activation classes.
		const className = [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-tablet-${tabletColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			!constrainWidth && 'dsgo-no-width-constraint',
		]
			.filter(Boolean)
			.join(' ');

		const TagName = tagName || 'div';
		const blockProps = useBlockProps.save({
			className,
			style: {
				...(hoverBackgroundColor && {
					'--dsgo-hover-bg-color':
						convertColorToCSSVar(hoverBackgroundColor),
				}),
				...(hoverTextColor && {
					'--dsgo-hover-text-color':
						convertColorToCSSVar(hoverTextColor),
				}),
				...(hoverIconBackgroundColor && {
					'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
						hoverIconBackgroundColor
					),
				}),
				...(hoverButtonBackgroundColor && {
					'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
						hoverButtonBackgroundColor
					),
				}),
			},
		});

		const blockGapValue = style?.spacing?.blockGap;
		const isBlockGapObject =
			typeof blockGapValue === 'object' && blockGapValue !== null;
		const blockGapRow = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.top : blockGapValue
		);
		const blockGapColumn = convertPresetToCSSVar(
			isBlockGapObject ? blockGapValue?.left : blockGapValue
		);
		const defaultGap = 'var(--wp--preset--spacing--50)';

		const innerStyles = {
			display: 'grid',
			gridTemplateColumns: columnMinWidth
				? `repeat(${desktopColumns || 3}, minmax(${columnMinWidth}, 1fr))`
				: `repeat(${desktopColumns || 3}, 1fr)`,
			alignItems: alignItems || 'stretch',
			rowGap: blockGapRow || rowGap || defaultGap,
			columnGap: blockGapColumn || columnGap || defaultGap,
		};

		if (constrainWidth) {
			innerStyles.maxWidth =
				contentWidth ||
				'var(--wp--style--global--content-size, 1140px)';
			innerStyles.marginLeft = 'auto';
			innerStyles.marginRight = 'auto';
		}

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-grid__inner',
			style: innerStyles,
		});

		return (
			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		);
	},
	migrate(attributes) {
		// Only the serialised class differs; the current save() derives it
		// from the style variation (and the new overlayColor attribute,
		// which defaults to '' for this old content) so no attribute change.
		return attributes;
	},
};

```

Finally, find:

```js
export default [legacyMinWidth, v1];
```

Replace with:

```js
export default [styleVariationClasses, legacyMinWidth, v1];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx jest src/blocks/grid/test/deprecated.test.js`
Expected: PASS (11 tests)

- [ ] **Step 5: Run the full Grid test suite**

Run: `npx jest src/blocks/grid --silent`
Expected: PASS (22 tests total across save.test.js + deprecated.test.js)

- [ ] **Step 6: Lint**

Run: `npm run lint:js -- src/blocks/grid/deprecated.js`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/blocks/grid/deprecated.js src/blocks/grid/test/deprecated.test.js
git commit -m "fix(grid): add deprecation for style-variation activation classes"
```

---

### Task 6: Icon / Icon Button — recognize Row/Grid activation classes

**Files:**
- Modify: `src/blocks/icon/style.scss`
- Modify: `src/blocks/icon/editor.scss`
- Modify: `src/blocks/icon-button/style.scss`
- Modify: `src/blocks/icon-button/editor.scss`

**Interfaces:**
- Consumes: the `dsgo-flex--has-hover-icon`/`dsgo-grid--has-hover-icon`/`dsgo-flex--has-hover-button`/`dsgo-grid--has-hover-button` classes produced by Tasks 2 and 4.
- No test file — this is pure CSS with no corresponding JS behavior to unit test; verified via build output grep (Step 5) and the manual editor/frontend check in Task 7.

- [ ] **Step 1: Update `icon/style.scss`**

Find:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-stack__inner > .dsgo-icon,
.dsgo-stack--has-hover-icon:hover .dsgo-stack__inner > .dsgo-icon {
	background-color: var(--dsgo-parent-hover-icon-bg) !important;
	transition: background-color 0.3s ease;
}
```

Replace with:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-stack__inner > .dsgo-icon,
.dsgo-flex--has-hover-icon:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid--has-hover-icon:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack--has-hover-icon:hover .dsgo-stack__inner > .dsgo-icon {
	background-color: var(--dsgo-parent-hover-icon-bg) !important;
	transition: background-color 0.3s ease;
}
```

- [ ] **Step 2: Update `icon/editor.scss`**

Find:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-stack__inner > .dsgo-icon,
.dsgo-stack--has-hover-icon:hover .dsgo-stack__inner > .dsgo-icon {
	background-color: var(--dsgo-parent-hover-icon-bg) !important;
	transition: background-color 0.3s ease;
}
```

Replace with:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-stack__inner > .dsgo-icon,
.dsgo-flex--has-hover-icon:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid--has-hover-icon:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack--has-hover-icon:hover .dsgo-stack__inner > .dsgo-icon {
	background-color: var(--dsgo-parent-hover-icon-bg) !important;
	transition: background-color 0.3s ease;
}
```

- [ ] **Step 3: Update `icon-button/style.scss`**

Find:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-flex__inner > .dsgo-icon-button,
.dsgo-grid[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-grid__inner > .dsgo-icon-button,
.dsgo-stack[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-stack__inner > .dsgo-icon-button,
.dsgo-stack--has-hover-button:hover .dsgo-stack__inner > .dsgo-icon-button {
	background-color: var(--dsgo-parent-hover-button-bg) !important;
	transition: background-color 0.3s ease;
}
```

Replace with:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-flex__inner > .dsgo-icon-button,
.dsgo-grid[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-grid__inner > .dsgo-icon-button,
.dsgo-stack[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-stack__inner > .dsgo-icon-button,
.dsgo-flex--has-hover-button:hover .dsgo-flex__inner > .dsgo-icon-button,
.dsgo-grid--has-hover-button:hover .dsgo-grid__inner > .dsgo-icon-button,
.dsgo-stack--has-hover-button:hover .dsgo-stack__inner > .dsgo-icon-button {
	background-color: var(--dsgo-parent-hover-button-bg) !important;
	transition: background-color 0.3s ease;
}
```

- [ ] **Step 4: Update `icon-button/editor.scss`**

Find:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-flex__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-grid[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-grid__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-stack[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-stack__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-stack--has-hover-button:hover .dsgo-stack__inner > .dsgo-icon-button .dsgo-icon-button__wrapper {
	background-color: var(--dsgo-parent-hover-button-bg) !important;
	transition: background-color 0.3s ease;
}
```

Replace with:

```scss
.dsgo-flex[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-flex__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-grid[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-grid__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-stack[style*="--dsgo-parent-hover-button-bg"]:hover .dsgo-stack__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-flex--has-hover-button:hover .dsgo-flex__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-grid--has-hover-button:hover .dsgo-grid__inner > .dsgo-icon-button .dsgo-icon-button__wrapper,
.dsgo-stack--has-hover-button:hover .dsgo-stack__inner > .dsgo-icon-button .dsgo-icon-button__wrapper {
	background-color: var(--dsgo-parent-hover-button-bg) !important;
	transition: background-color 0.3s ease;
}
```

- [ ] **Step 5: Build and verify CSS output**

Run: `npm run build`
Expected: build succeeds with no SCSS errors.

Run:
```bash
grep -c "dsgo-flex--has-hover-icon" build/style-index.css
grep -c "dsgo-grid--has-hover-icon" build/style-index.css
grep -c "dsgo-flex--has-hover-button" build/style-index.css
grep -c "dsgo-grid--has-hover-button" build/style-index.css
```
Expected: at least 1 match each.

- [ ] **Step 6: Lint**

Run: `npm run lint:css -- src/blocks/icon/style.scss src/blocks/icon/editor.scss src/blocks/icon-button/style.scss src/blocks/icon-button/editor.scss`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/blocks/icon/style.scss src/blocks/icon/editor.scss src/blocks/icon-button/style.scss src/blocks/icon-button/editor.scss
git commit -m "feat(icon,icon-button): recognize row/grid hover-variation activation classes"
```

---

### Task 7: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Full JS test suite**

Run: `npx jest --silent`
Expected: all suites pass, including the pre-existing Section suite (35 tests unchanged) plus the new Row/Grid/shared-util suites added in Tasks 1–5.

- [ ] **Step 2: Full build**

Run: `npm run build`
Expected: success, no errors or new warnings.

- [ ] **Step 3: Full lint**

Run: `npm run lint:js`
Run: `npm run lint:css`
Expected: no errors introduced by this branch (pre-existing unrelated warnings, if any, are out of scope).

- [ ] **Step 4: Manual editor + frontend spot check**

Per this project's pre-commit checklist, manually verify in a running `wp-env` instance (or the project's dev environment):
1. Insert a Row block, apply an "Overlay Dark" (or equivalent) style variation from the Styles panel (if a style kit is active) — confirm the overlay renders in the editor AND on the frontend.
2. Insert a Grid block, set an explicit Overlay Color via the new inspector control — confirm it renders in the editor AND on the frontend, and that grid items remain visible above the overlay (z-index).
3. Insert a Grid block with a nested Icon block whose parent has `hoverIconBackgroundColor` set — hover the Grid and confirm the icon's background changes (regression check for Task 6's icon CSS change, unrelated to variations).
4. Open the browser console in both the editor and frontend — confirm no errors.

- [ ] **Step 5: Confirm no regressions in Section**

Run: `npx jest src/blocks/section --silent`
Expected: still 35 tests passing, identical to the Task 1 baseline.

- [ ] **Step 6: Final review of the branch diff**

Run: `git log --oneline claude/section-overlay-style-class..HEAD`
Expected: 6 commits, one per Task 1–6 (Task 7 has no commit — verification only).

At this point the branch is ready to open as a PR against `main`, to land as a fast-follow once `claude/section-overlay-style-class` (PR #445) merges. If PR #445 merges to `main` before this branch is opened as a PR, rebase this branch onto `main` first — the rebase should be conflict-free since this branch was built directly on top of PR #445's tip.
