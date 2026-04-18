# Theme 6 — Shared Authoring Primitives Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up `src/hooks/` and `src/components/shared/` with the six foundation primitives that Themes 1, 3, and 5 depend on, then migrate the duplicated call-sites to consume them.

**Architecture:** Each primitive is one PR. Hooks land first (pure, easy to test, low risk). Components land in dependency order. After each primitive ships, a small migration PR updates the existing duplicate call-sites and deletes the dead boilerplate. Tests live in `tests/unit/` per the existing Jest convention.

**Tech Stack:** WordPress block editor (`@wordpress/block-editor`, `@wordpress/components`, `@wordpress/element`, `@wordpress/data`), Jest 29 (V8 coverage) via `@wordpress/scripts`, React Testing Library through Jest setup at `tests/unit/setup.js`. Coverage threshold is 50% global; new primitives should clear that.

**Source-survey findings driving the plan:**

| Doc estimate | Survey reality |
|---|---|
| `useUniqueBlockId` replaces 5 duplicates | 3 clientId-derived (tabs, form-builder, modal); 2 random-UUID outliers (accordion-item, counter) — out of scope |
| `useBlockColors` ~10 blocks | 26 blocks use the encode/decode/dropdown trio |
| `cssVars` ~15 blocks | Pattern is dominant in `save.js` files; consumers are mostly save-time |
| `src/hooks/`, `src/components/shared/` | Neither exists today; this plan creates them |

**Branching:** Single feature branch per PR (e.g. `claude/theme-6-use-unique-block-id`). Each PR merges to main independently.

**Out of scope for this plan:**
- Theme 3 screenshot-diff CI (separate prereq for the inspector rollout, not Theme 6).
- Migrating accordion-item / counter from `Math.random()` to clientId-derived ids (behavior change; leave for a separate PR).
- Adopting the new primitives in *all* 26 color blocks. Migration PRs in this plan cover the 3 representative blocks per primitive to prove the API; bulk migration is deferred to follow-up PRs that the consuming Themes (1/3/5) will sweep through.

---

## File Structure

**New directories:**
- `src/hooks/` — canonical home for shared React hooks
- `src/components/shared/` — canonical home for shared editor components

**New files (one per task):**

| File | Responsibility |
|---|---|
| `src/hooks/index.js` | Barrel re-exports for all hooks |
| `src/hooks/useUniqueBlockId.js` | Stable id derived from `clientId` |
| `src/hooks/useBlockColors.js` | Wraps encode/decode + `useMultipleOriginColorsAndGradients` |
| `src/hooks/useTablistKeyboard.js` | ArrowLeft/Right/Home/End handler factory for tablist children |
| `src/utils/css-vars.js` | Pure `cssVars(attributes, map)` mapper |
| `src/components/shared/index.js` | Barrel re-exports for shared components |
| `src/components/shared/DsgoInspectorPanel/index.js` | `ToolsPanel`-backed wrapper enforcing naming + reset affordance |
| `src/components/shared/DsgoBlockPlaceholder/index.js` | `<Placeholder>`-based first-insert wizard |
| `src/components/shared/DsgoBlockPlaceholder/style.scss` | Placeholder visual styles (matches existing modal/form-builder look) |
| `src/components/shared/DsgoChildToolbar/index.js` | `BlockControls`-based Add/Duplicate/Move/Remove toolbar |
| `tests/unit/hooks/useUniqueBlockId.test.js` | Hook tests |
| `tests/unit/hooks/useBlockColors.test.js` | Hook tests |
| `tests/unit/hooks/useTablistKeyboard.test.js` | Hook tests |
| `tests/unit/utils/css-vars.test.js` | Pure-function tests |
| `tests/unit/components/shared/DsgoInspectorPanel.test.js` | Component tests |
| `tests/unit/components/shared/DsgoBlockPlaceholder.test.js` | Component tests |
| `tests/unit/components/shared/DsgoChildToolbar.test.js` | Component tests |

**Modified files (per migration sub-task):** see individual tasks. Each migration touches only the proven call-sites that already match the surveyed pattern.

**Documentation update (lands with Task 6):**
- `.claude/CLAUDE.md` — add the contribution rule: "Before adding a pattern to a block, check `src/hooks/` and `src/components/shared/`. If it's the second time you're writing a pattern, extract it." Also add the variation-vs-block guideline from Theme 2 forward-looking rule.
- `.claude/skills/add-block/SKILL.md` — back-reference the contribution rule so new blocks consult shared primitives first. (Confirmed location, 63 lines.)

---

## Task 1 — `useUniqueBlockId` hook

**PR title:** `feat(hooks): add useUniqueBlockId for stable block ids`

**Files:**
- Create: `src/hooks/useUniqueBlockId.js`
- Create: `src/hooks/index.js`
- Create: `tests/unit/hooks/useUniqueBlockId.test.js`
- Modify: `src/blocks/tabs/edit.js` (replace lines 71–75)
- Modify: `src/blocks/form-builder/edit.js` (replace lines 133–137)
- Modify: `src/blocks/modal/edit.js` (replace lines 52–56)

### Step 1.1: Write failing tests

- [ ] Create `tests/unit/hooks/useUniqueBlockId.test.js`:

```javascript
/**
 * useUniqueBlockId Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useUniqueBlockId } from '../../../src/hooks/useUniqueBlockId';

describe('useUniqueBlockId', () => {
	test('seeds attribute with clientId substring when empty', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: undefined,
				setAttributes,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({ uniqueId: 'abcdef12' });
	});

	test('does not call setAttributes when value already set', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: 'existing',
				setAttributes,
			})
		);
		expect(setAttributes).not.toHaveBeenCalled();
	});

	test('honors prefix option (full clientId, with prefix)', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'modalId',
				value: undefined,
				setAttributes,
				prefix: 'dsgo-modal-',
				length: null,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({
			modalId: 'dsgo-modal-abcdef1234567890',
		});
	});

	test('honors custom length option', () => {
		const setAttributes = jest.fn();
		renderHook(() =>
			useUniqueBlockId({
				clientId: 'abcdef1234567890',
				attributeName: 'uniqueId',
				value: undefined,
				setAttributes,
				length: 6,
			})
		);
		expect(setAttributes).toHaveBeenCalledWith({ uniqueId: 'abcdef' });
	});

	test('does not re-seed when clientId changes after value is set', () => {
		const setAttributes = jest.fn();
		const { rerender } = renderHook(
			({ clientId, value }) =>
				useUniqueBlockId({
					clientId,
					attributeName: 'uniqueId',
					value,
					setAttributes,
				}),
			{ initialProps: { clientId: 'aaaaaaaa11111111', value: undefined } }
		);
		expect(setAttributes).toHaveBeenCalledTimes(1);
		setAttributes.mockClear();
		rerender({ clientId: 'bbbbbbbb22222222', value: 'aaaaaaaa' });
		expect(setAttributes).not.toHaveBeenCalled();
	});
});
```

- [ ] Run tests to verify failure:

```bash
npx jest tests/unit/hooks/useUniqueBlockId.test.js
```

Expected: FAIL with `Cannot find module '../../../src/hooks/useUniqueBlockId'`.

### Step 1.2: Implement the hook

- [ ] Create `src/hooks/useUniqueBlockId.js`:

```javascript
/**
 * useUniqueBlockId
 *
 * Seeds a block attribute with a stable id derived from clientId on first render.
 * Replaces the duplicated `useEffect(() => { if (!attr) setAttributes({ attr: clientId.substring(0, 8) }); }, [])`
 * pattern that lived in tabs, form-builder, and modal.
 *
 * @param {Object}      params
 * @param {string}      params.clientId      The block clientId.
 * @param {string}      params.attributeName Name of the attribute to seed.
 * @param {string|undefined} params.value    Current value of the attribute.
 * @param {Function}    params.setAttributes The block's setAttributes callback.
 * @param {string}      [params.prefix='']   Optional prefix prepended to the id.
 * @param {number|null} [params.length=8]    Substring length, or null to use the full clientId.
 */
import { useEffect } from '@wordpress/element';

export function useUniqueBlockId({
	clientId,
	attributeName,
	value,
	setAttributes,
	prefix = '',
	length = 8,
}) {
	useEffect(() => {
		if (value) {
			return;
		}
		const base = length === null ? clientId : clientId.substring(0, length);
		setAttributes({ [attributeName]: `${prefix}${base}` });
		// We intentionally exclude clientId/setAttributes from deps:
		// re-seeding on clientId change would overwrite saved ids.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [value, attributeName]);
}
```

- [ ] Create `src/hooks/index.js`:

```javascript
export { useUniqueBlockId } from './useUniqueBlockId';
```

### Step 1.3: Run tests, expect pass

- [ ] Run:

```bash
npx jest tests/unit/hooks/useUniqueBlockId.test.js
```

Expected: 5 tests PASS.

### Step 1.4: Migrate consumers

- [ ] In `src/blocks/tabs/edit.js`, replace lines 71–75:

Before:
```javascript
useEffect(() => {
	if (!uniqueId) {
		setAttributes({ uniqueId: clientId.substring(0, 8) });
	}
}, [uniqueId, clientId, setAttributes]);
```

After:
```javascript
useUniqueBlockId({
	clientId,
	attributeName: 'uniqueId',
	value: uniqueId,
	setAttributes,
});
```

Add the import at the top of the file:
```javascript
import { useUniqueBlockId } from '../../hooks';
```

Remove the now-unused `useEffect` import if no other `useEffect` remains in the file. Check first with `grep -n "useEffect" src/blocks/tabs/edit.js`.

- [ ] In `src/blocks/form-builder/edit.js`, replace lines 133–137:

Before:
```javascript
useEffect(() => {
	if (!formId) {
		setAttributes({ formId: clientId.substring(0, 8) });
	}
}, [formId, clientId, setAttributes]);
```

After:
```javascript
useUniqueBlockId({
	clientId,
	attributeName: 'formId',
	value: formId,
	setAttributes,
});
```

Add the import:
```javascript
import { useUniqueBlockId } from '../../hooks';
```

- [ ] In `src/blocks/modal/edit.js`, replace lines 52–56:

Before:
```javascript
useEffect(() => {
	if (!modalId) {
		setAttributes({ modalId: `dsgo-modal-${clientId}` });
	}
}, [modalId, clientId, setAttributes]);
```

After:
```javascript
useUniqueBlockId({
	clientId,
	attributeName: 'modalId',
	value: modalId,
	setAttributes,
	prefix: 'dsgo-modal-',
	length: null,
});
```

Add the import:
```javascript
import { useUniqueBlockId } from '../../hooks';
```

### Step 1.5: Build, lint, smoke test

- [ ] Run:

```bash
npm run build
```

Expected: build succeeds with no errors.

- [ ] Run:

```bash
npm run lint:js -- src/blocks/tabs/edit.js src/blocks/form-builder/edit.js src/blocks/modal/edit.js src/hooks/
```

Expected: 0 errors.

- [ ] Manual smoke test (cannot be automated):
  - Insert a Tabs block in a fresh post; confirm `uniqueId` attribute is set in the block inspector "Advanced" panel (or via post-meta inspection).
  - Insert a Form block; same check for `formId`.
  - Insert a Modal block; same check for `modalId` (should match `dsgo-modal-<full-clientId>`).
  - Reload the editor; confirm the ids persist (no re-seeding).

### Step 1.6: Commit and open PR

- [ ] Run:

```bash
git add src/hooks/ tests/unit/hooks/useUniqueBlockId.test.js \
        src/blocks/tabs/edit.js src/blocks/form-builder/edit.js src/blocks/modal/edit.js
git commit -m "feat(hooks): add useUniqueBlockId for stable block ids

Extracts the duplicated 'seed attribute from clientId on first render'
pattern from tabs, form-builder, and modal into a single hook.
Foundation for Theme 6 (shared authoring primitives).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin HEAD
gh pr create --title "feat(hooks): add useUniqueBlockId for stable block ids" --body "..."
```

---

## Task 2 — `useBlockColors` hook

**PR title:** `feat(hooks): add useBlockColors wrapping ColorGradientSettingsDropdown plumbing`

**Files:**
- Create: `src/hooks/useBlockColors.js`
- Modify: `src/hooks/index.js` (add re-export)
- Create: `tests/unit/hooks/useBlockColors.test.js`
- Modify: `src/blocks/section/edit.js` (one panel as proof; lines ~385–410)
- Modify: `src/blocks/slider/edit.js` (one panel as proof; lines ~1086–1127)
- Modify: `src/blocks/card/edit.js` (one panel as proof; lines ~763–786)

### Step 2.1: Write failing tests

- [ ] Create `tests/unit/hooks/useBlockColors.test.js`:

```javascript
/**
 * useBlockColors Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useBlockColors } from '../../../src/hooks/useBlockColors';

const mockColorSettings = {
	colors: [
		{
			colors: [
				{ slug: 'primary', color: '#ff0000', name: 'Primary' },
				{ slug: 'secondary', color: '#00ff00', name: 'Secondary' },
			],
		},
	],
	gradients: [],
	disableCustomColors: false,
	disableCustomGradients: false,
};

jest.mock('@wordpress/block-editor', () => ({
	useMultipleOriginColorsAndGradients: () => mockColorSettings,
}));

describe('useBlockColors', () => {
	test('returns settings array with decoded colorValue for each entry', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: 'var:preset|color|primary', text: '#abcdef' },
				setAttributes,
				entries: [
					{ label: 'Background', attribute: 'bg' },
					{ label: 'Text', attribute: 'text' },
				],
			})
		);
		expect(result.current.settings).toHaveLength(2);
		expect(result.current.settings[0].label).toBe('Background');
		expect(result.current.settings[0].colorValue).toBe('#ff0000');
		expect(result.current.settings[1].colorValue).toBe('#abcdef');
	});

	test('onColorChange encodes preset hex back to var:preset format', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		result.current.settings[0].onColorChange('#ff0000');
		expect(setAttributes).toHaveBeenCalledWith({
			bg: 'var:preset|color|primary',
		});
	});

	test('onColorChange stores custom hex unchanged when no preset matches', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		result.current.settings[0].onColorChange('#123456');
		expect(setAttributes).toHaveBeenCalledWith({ bg: '#123456' });
	});

	test('onColorChange clears attribute to empty string when value is undefined', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: '#ff0000' },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		result.current.settings[0].onColorChange(undefined);
		expect(setAttributes).toHaveBeenCalledWith({ bg: '' });
	});

	test('exposes colorGradientSettings for spreading into dropdown', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: {},
				setAttributes,
				entries: [],
			})
		);
		expect(result.current.colorGradientSettings).toBe(mockColorSettings);
	});

	test('defaults each entry to enableAlpha=true and clearable=true', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		expect(result.current.settings[0].enableAlpha).toBe(true);
		expect(result.current.settings[0].clearable).toBe(true);
	});

	test('per-entry options override the alpha/clearable defaults', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [
					{
						label: 'Background',
						attribute: 'bg',
						enableAlpha: false,
						clearable: false,
					},
				],
			})
		);
		expect(result.current.settings[0].enableAlpha).toBe(false);
		expect(result.current.settings[0].clearable).toBe(false);
	});
});
```

- [ ] Run tests to verify failure:

```bash
npx jest tests/unit/hooks/useBlockColors.test.js
```

Expected: FAIL with module-not-found.

### Step 2.2: Implement the hook

- [ ] Create `src/hooks/useBlockColors.js`:

```javascript
/**
 * useBlockColors
 *
 * Wraps the encode/decode boilerplate that surrounds every
 * ColorGradientSettingsDropdown in the plugin (~26 blocks today).
 * Returns a `settings` array shaped for direct passing to
 * ColorGradientSettingsDropdown's `settings` prop, plus the spreadable
 * colorGradientSettings object.
 *
 * Usage:
 *
 * const { settings, colorGradientSettings } = useBlockColors({
 *   attributes,
 *   setAttributes,
 *   entries: [
 *     { label: __('Background', 'designsetgo'), attribute: 'backgroundColor' },
 *     { label: __('Text', 'designsetgo'), attribute: 'textColor' },
 *   ],
 * });
 *
 * <ColorGradientSettingsDropdown
 *   panelId={clientId}
 *   title={__('Colors', 'designsetgo')}
 *   settings={settings}
 *   {...colorGradientSettings}
 * />
 *
 * @param {Object}   params
 * @param {Object}   params.attributes    Block attributes.
 * @param {Function} params.setAttributes Block setAttributes.
 * @param {Array}    params.entries       Color entries: { label, attribute, enableAlpha?, clearable? }.
 * @return {Object} { settings, colorGradientSettings }
 */
import { useMultipleOriginColorsAndGradients } from '@wordpress/block-editor';
import {
	encodeColorValue,
	decodeColorValue,
} from '../utils/encode-color-value';

export function useBlockColors({ attributes, setAttributes, entries }) {
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	const settings = entries.map(
		({ label, attribute, enableAlpha = true, clearable = true }) => ({
			label,
			colorValue: decodeColorValue(
				attributes[attribute],
				colorGradientSettings
			),
			onColorChange: (color) =>
				setAttributes({
					[attribute]:
						encodeColorValue(color, colorGradientSettings) || '',
				}),
			enableAlpha,
			clearable,
		})
	);

	return { settings, colorGradientSettings };
}
```

- [ ] Update `src/hooks/index.js`:

```javascript
export { useUniqueBlockId } from './useUniqueBlockId';
export { useBlockColors } from './useBlockColors';
```

### Step 2.3: Run tests, expect pass

- [ ] Run:

```bash
npx jest tests/unit/hooks/useBlockColors.test.js
```

Expected: 7 tests PASS.

### Step 2.4: Migrate three proof-of-concept call-sites

**Important:** This task migrates only **one panel per file** (the simplest one) so the diff is reviewable. Bulk migration of all 26 blocks is deferred to follow-up PRs in Theme 3.

- [ ] In `src/blocks/section/edit.js`, replace the `colorGradientSettings = useMultipleOriginColorsAndGradients();` line plus the "Hover Settings" `<ColorGradientSettingsDropdown>` block (lines 385–410):

Before (showing one settings entry as illustration):
```jsx
const colorGradientSettings = useMultipleOriginColorsAndGradients();
// ... later ...
<ColorGradientSettingsDropdown
	panelId={clientId}
	title={__('Hover Settings', 'designsetgo')}
	settings={[
		{
			label: __('Overlay Color', 'designsetgo'),
			colorValue: decodeColorValue(overlayColor, colorGradientSettings),
			onColorChange: (color) =>
				setAttributes({
					overlayColor:
						encodeColorValue(color, colorGradientSettings) || '',
				}),
			enableAlpha: true,
			clearable: true,
		},
	]}
	{...colorGradientSettings}
/>
```

After:
```jsx
const { settings: hoverColorSettings, colorGradientSettings } = useBlockColors({
	attributes,
	setAttributes,
	entries: [
		{ label: __('Overlay Color', 'designsetgo'), attribute: 'overlayColor' },
	],
});
// ... later ...
<ColorGradientSettingsDropdown
	panelId={clientId}
	title={__('Hover Settings', 'designsetgo')}
	settings={hoverColorSettings}
	{...colorGradientSettings}
/>
```

Add import:
```javascript
import { useBlockColors } from '../../hooks';
```

Remove the now-unused `useMultipleOriginColorsAndGradients`, `encodeColorValue`, `decodeColorValue` imports **only if** no other panel in the file still needs them. Verify with grep first.

- [ ] Same migration pattern for `src/blocks/slider/edit.js` "Arrow Colors" panel (lines ~1086–1127). The settings array maps each color attribute to a `{ label, attribute }` entry.

- [ ] Same migration pattern for `src/blocks/card/edit.js` "Border" panel (lines ~763–786).

### Step 2.5: Build, lint, smoke test

- [ ] Run:

```bash
npm run build && npm run lint:js -- src/hooks/ src/blocks/section/ src/blocks/slider/ src/blocks/card/
```

Expected: build succeeds, lint passes.

- [ ] Manual smoke test:
  - Insert a Section block; pick a preset color in "Hover Settings → Overlay Color"; reload editor; confirm the swatch still shows the preset highlighted (not a custom hex).
  - Pick a custom hex; confirm it round-trips.
  - Repeat for Slider arrow color and Card border color.

### Step 2.6: Commit and open PR

```bash
git add src/hooks/ tests/unit/hooks/useBlockColors.test.js \
        src/blocks/section/edit.js src/blocks/slider/edit.js src/blocks/card/edit.js
git commit -m "feat(hooks): add useBlockColors wrapping ColorGradientSettingsDropdown plumbing

Replaces the encode/decode/dropdown boilerplate (currently duplicated
across 26 blocks) with a single hook returning a ready-to-spread settings
array. Migrates one panel each in section, slider, card as proof; bulk
migration deferred to Theme 3 inspector rollout.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin HEAD
gh pr create --title "feat(hooks): add useBlockColors wrapping ColorGradientSettingsDropdown plumbing" --body "..."
```

---

## Task 3 — `cssVars` pure function

**PR title:** `feat(utils): add cssVars helper for attribute → CSS-var mapping`

**Files:**
- Create: `src/utils/css-vars.js`
- Create: `tests/unit/utils/css-vars.test.js`
- Modify: `src/blocks/accordion/save.js` (lines 33–43, the open/hover/border CSS-var block)
- Modify: `src/blocks/form-builder/save.js` (lines 56–66, the form-color CSS-var block)
- Modify: `src/blocks/slider/save.js` (lines 71–87, the slider color CSS-var block)

### Step 3.1: Write failing tests

- [ ] Create `tests/unit/utils/css-vars.test.js`:

```javascript
/**
 * cssVars Tests
 *
 * @package
 */

import { cssVars } from '../../../src/utils/css-vars';

describe('cssVars', () => {
	test('maps attributes through convertColorToCSSVar by default', () => {
		const result = cssVars(
			{ bg: 'var:preset|color|accent-3', text: '#fff' },
			{
				'--dsgo-bg': 'bg',
				'--dsgo-text': 'text',
			}
		);
		expect(result).toEqual({
			'--dsgo-bg': 'var(--wp--preset--color--accent-3)',
			'--dsgo-text': '#fff',
		});
	});

	test('omits keys whose attribute value is undefined or empty string', () => {
		const result = cssVars(
			{ bg: '', text: undefined, border: '#000' },
			{
				'--dsgo-bg': 'bg',
				'--dsgo-text': 'text',
				'--dsgo-border': 'border',
			}
		);
		expect(result).toEqual({ '--dsgo-border': '#000' });
	});

	test('honors custom converter via { attribute, convert } shape', () => {
		const px = (v) => (typeof v === 'number' ? `${v}px` : v);
		const result = cssVars(
			{ pad: 12, gap: 'var:preset|spacing|md' },
			{
				'--dsgo-pad': { attribute: 'pad', convert: px },
				'--dsgo-gap': { attribute: 'gap', convert: (v) => v },
			}
		);
		expect(result).toEqual({
			'--dsgo-pad': '12px',
			'--dsgo-gap': 'var:preset|spacing|md',
		});
	});

	test('returns empty object when no entries map', () => {
		const result = cssVars({}, { '--dsgo-bg': 'bg' });
		expect(result).toEqual({});
	});

	test('passes through 0 (falsy but valid) when convert is identity', () => {
		const result = cssVars(
			{ z: 0 },
			{ '--dsgo-z': { attribute: 'z', convert: (v) => v } }
		);
		expect(result).toEqual({ '--dsgo-z': 0 });
	});
});
```

- [ ] Run tests, expect failure:

```bash
npx jest tests/unit/utils/css-vars.test.js
```

Expected: FAIL with module-not-found.

### Step 3.2: Implement `cssVars`

- [ ] Create `src/utils/css-vars.js`:

```javascript
/**
 * cssVars
 *
 * Pure helper that builds an inline-style object of CSS custom properties
 * from a block's attributes. Replaces the hand-rolled
 *
 *   style={{
 *     '--dsgo-bg': convertColorToCSSVar(attrs.bg),
 *     '--dsgo-text': convertColorToCSSVar(attrs.text),
 *   }}
 *
 * pattern in ~15 save.js files.
 *
 * Usage:
 *
 *   const styles = cssVars(attributes, {
 *     '--dsgo-bg': 'backgroundColor',
 *     '--dsgo-text': 'textColor',
 *     '--dsgo-pad': { attribute: 'padding', convert: (v) => `${v}px` },
 *   });
 *
 * Empty / undefined values are omitted so they don't override CSS defaults.
 *
 * @param {Object} attributes The block attributes.
 * @param {Object} map        Map of CSS-variable name → attribute name (string)
 *                            or { attribute, convert } for non-color values.
 * @return {Object} Inline-style object suitable for React's `style` prop.
 */
import { convertColorToCSSVar } from './convert-preset-to-css-var';

export function cssVars(attributes, map) {
	const styles = {};
	for (const [cssVar, spec] of Object.entries(map)) {
		const isShorthand = typeof spec === 'string';
		const attribute = isShorthand ? spec : spec.attribute;
		const convert = isShorthand ? convertColorToCSSVar : spec.convert;
		const raw = attributes[attribute];
		if (raw === undefined || raw === '') {
			continue;
		}
		const value = convert(raw);
		if (value === undefined || value === '') {
			continue;
		}
		styles[cssVar] = value;
	}
	return styles;
}
```

### Step 3.3: Run tests, expect pass

- [ ] Run:

```bash
npx jest tests/unit/utils/css-vars.test.js
```

Expected: 5 tests PASS.

### Step 3.4: Migrate three proof-of-concept call-sites

- [ ] In `src/blocks/accordion/save.js` (lines 33–43), replace:

Before:
```javascript
const styles = {
	'--dsgo-accordion-open-bg': convertColorToCSSVar(openBg),
	'--dsgo-accordion-open-text': convertColorToCSSVar(openText),
	'--dsgo-accordion-hover-bg': convertColorToCSSVar(hoverBg),
	'--dsgo-accordion-hover-text': convertColorToCSSVar(hoverText),
	'--dsgo-accordion-border-color': convertColorToCSSVar(borderColor),
};
```

After:
```javascript
const styles = cssVars(attributes, {
	'--dsgo-accordion-open-bg': 'openBg',
	'--dsgo-accordion-open-text': 'openText',
	'--dsgo-accordion-hover-bg': 'hoverBg',
	'--dsgo-accordion-hover-text': 'hoverText',
	'--dsgo-accordion-border-color': 'borderColor',
});
```

Replace the import:
```javascript
import { cssVars } from '../../utils/css-vars';
```

(Remove the `convertColorToCSSVar` import if no longer used in the file — verify with grep.)

- [ ] Same migration pattern for `src/blocks/form-builder/save.js` lines 56–66.

- [ ] Same migration pattern for `src/blocks/slider/save.js` lines 71–87.

**Note for the implementer:** because `save.js` output is part of block validity, a changed `style` object would trigger "Invalid block content" warnings. `cssVars` produces the **same** object as the hand-rolled mapping (omitting empty/undefined keys), so output is byte-identical and no deprecation is needed. Verify with the smoke test below before merging.

### Step 3.5: Build, lint, validate save output

- [ ] Run:

```bash
npm run build && npm run lint:js -- src/utils/ src/blocks/accordion/ src/blocks/form-builder/ src/blocks/slider/
```

Expected: build succeeds, lint passes.

- [ ] Critical save-output check — load each migrated block in a post that already contains it (or insert fresh), open the editor, and confirm **no "Block contains unexpected or invalid content" warnings appear**. If any warning shows up, the migration changed the rendered HTML and a deprecation is required (which would invalidate the "no deprecation needed" claim above — stop and reassess).

### Step 3.6: Commit and open PR

```bash
git add src/utils/css-vars.js tests/unit/utils/css-vars.test.js \
        src/blocks/accordion/save.js src/blocks/form-builder/save.js src/blocks/slider/save.js
git commit -m "feat(utils): add cssVars helper for attribute -> CSS-var mapping

Pure function that takes (attributes, map) and returns an inline-style
object of CSS custom properties. Replaces the hand-rolled
convertColorToCSSVar boilerplate in save.js across three blocks; output
is byte-identical so no deprecation needed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin HEAD
gh pr create --title "feat(utils): add cssVars helper for attribute -> CSS-var mapping" --body "..."
```

---

## Task 4 — `<DsgoInspectorPanel>` component

**PR title:** `feat(components): add DsgoInspectorPanel ToolsPanel wrapper`

**Files:**
- Create: `src/components/shared/DsgoInspectorPanel/index.js`
- Create: `src/components/shared/index.js`
- Create: `tests/unit/components/shared/DsgoInspectorPanel.test.js`

**No block migrations in this task.** Migrating the ~30 affected blocks belongs to Theme 3 (the big-bang inspector rollout). This task ships the primitive only, so Theme 3 has it ready.

### Step 4.1: Write failing tests

- [ ] Create `tests/unit/components/shared/DsgoInspectorPanel.test.js`:

```javascript
/**
 * DsgoInspectorPanel Tests
 *
 * @package
 */

import { render, screen } from '@testing-library/react';
import { DsgoInspectorPanel } from '../../../../src/components/shared/DsgoInspectorPanel';

describe('DsgoInspectorPanel', () => {
	test('renders with the provided title', () => {
		render(
			<DsgoInspectorPanel
				title="Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(screen.getByText('Settings')).toBeInTheDocument();
	});

	test('renders children', () => {
		render(
			<DsgoInspectorPanel
				title="Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div data-testid="child">Hello</div>
			</DsgoInspectorPanel>
		);
		expect(screen.getByTestId('child')).toBeInTheDocument();
	});

	test('throws (or warns) when title is not one of the canonical names', () => {
		const consoleWarn = jest.spyOn(console, 'warn').mockImplementation();
		render(
			<DsgoInspectorPanel
				title="Custom Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(consoleWarn).toHaveBeenCalledWith(
			expect.stringContaining(
				'DsgoInspectorPanel: title "Custom Settings" is not one of the canonical panel names'
			)
		);
		consoleWarn.mockRestore();
	});

	test('does not warn for canonical Settings/Style titles', () => {
		const consoleWarn = jest.spyOn(console, 'warn').mockImplementation();
		render(
			<DsgoInspectorPanel
				title="Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		render(
			<DsgoInspectorPanel
				title="Style"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(consoleWarn).not.toHaveBeenCalled();
		consoleWarn.mockRestore();
	});
});
```

- [ ] Run, expect failure:

```bash
npx jest tests/unit/components/shared/DsgoInspectorPanel.test.js
```

Expected: FAIL with module-not-found.

### Step 4.2: Implement the component

- [ ] Create `src/components/shared/DsgoInspectorPanel/index.js`:

```javascript
/**
 * DsgoInspectorPanel
 *
 * Wraps WordPress's __experimentalToolsPanel to enforce the plugin's
 * 3-panel inspector convention (Settings, Style, Advanced) and to give
 * users a consistent reset-to-default affordance on every control.
 *
 * Theme 3 of the editor UX design migrates ~30 blocks onto this primitive.
 * This component is intentionally thin — it warns (does not throw) when
 * given a non-canonical title so existing blocks can adopt it incrementally
 * without breaking renders.
 *
 * Usage:
 *
 *   <InspectorControls>
 *     <DsgoInspectorPanel
 *       title={__('Settings', 'designsetgo')}
 *       panelId={clientId}
 *       resetAll={() => setAttributes({ ... })}
 *     >
 *       <DsgoInspectorPanel.Item ... />
 *     </DsgoInspectorPanel>
 *   </InspectorControls>
 */
import {
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

const CANONICAL_TITLES = ['Settings', 'Style', 'Advanced'];

export function DsgoInspectorPanel({
	title,
	panelId,
	resetAll,
	children,
	...rest
}) {
	if (!CANONICAL_TITLES.includes(title)) {
		// eslint-disable-next-line no-console
		console.warn(
			`DsgoInspectorPanel: title "${title}" is not one of the canonical panel names (${CANONICAL_TITLES.join(', ')}). See docs/plans/2026-04-16-blocks-editor-ux-design.md Theme 3.`
		);
	}
	return (
		<ToolsPanel
			label={title}
			panelId={panelId}
			resetAll={resetAll}
			hasInnerWrapper
			shouldRenderPlaceholderItems
			{...rest}
		>
			{children}
		</ToolsPanel>
	);
}

DsgoInspectorPanel.Item = ToolsPanelItem;
```

- [ ] Create `src/components/shared/index.js`:

```javascript
export { DsgoInspectorPanel } from './DsgoInspectorPanel';
```

### Step 4.3: Run tests, expect pass

- [ ] Run:

```bash
npx jest tests/unit/components/shared/DsgoInspectorPanel.test.js
```

Expected: 4 tests PASS.

### Step 4.4: Build and lint

- [ ] Run:

```bash
npm run build && npm run lint:js -- src/components/shared/
```

Expected: build succeeds, lint passes.

**No smoke test in this task** — the component has no consumers yet. Theme 3's first migration PR will exercise it in the editor.

### Step 4.5: Commit and open PR

```bash
git add src/components/shared/ tests/unit/components/shared/DsgoInspectorPanel.test.js
git commit -m "feat(components): add DsgoInspectorPanel ToolsPanel wrapper

Foundation for Theme 3 inspector IA standardization. Wraps
__experimentalToolsPanel and __experimentalToolsPanelItem with a
canonical-title guardrail so the planned 3-panel convention
(Settings / Style / Advanced) doesn't drift block-to-block.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin HEAD
gh pr create --title "feat(components): add DsgoInspectorPanel ToolsPanel wrapper" --body "..."
```

---

## Task 5 — `<DsgoBlockPlaceholder>` component

**PR title:** `feat(components): add DsgoBlockPlaceholder for first-insert wizards`

**Files:**
- Create: `src/components/shared/DsgoBlockPlaceholder/index.js`
- Create: `src/components/shared/DsgoBlockPlaceholder/style.scss`
- Modify: `src/components/shared/index.js` (add re-export)
- Create: `tests/unit/components/shared/DsgoBlockPlaceholder.test.js`
- Modify: `src/styles/style.scss` and `src/styles/editor.scss` (import the placeholder styles per CLAUDE.md "Style Imports (MANDATORY)" rule)

**No block migrations.** Modal and form-builder already implement the pattern; refactoring them onto the shared component is the natural Theme 1 starting point and belongs to that PR sequence.

### Step 5.1: Write failing tests

- [ ] Create `tests/unit/components/shared/DsgoBlockPlaceholder.test.js`:

```javascript
/**
 * DsgoBlockPlaceholder Tests
 *
 * @package
 */

import { render, screen, fireEvent } from '@testing-library/react';
import { DsgoBlockPlaceholder } from '../../../../src/components/shared/DsgoBlockPlaceholder';

const variations = [
	{
		name: 'horizontal',
		title: 'Horizontal',
		description: 'Side-by-side',
		icon: 'align-center',
	},
	{
		name: 'vertical',
		title: 'Vertical',
		description: 'Stacked',
		icon: 'align-left',
	},
];

describe('DsgoBlockPlaceholder', () => {
	test('renders label and instructions', () => {
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				variations={variations}
				onSelect={jest.fn()}
			/>
		);
		expect(screen.getByText('Tabs')).toBeInTheDocument();
		expect(
			screen.getByText('Pick a starting layout')
		).toBeInTheDocument();
	});

	test('renders one button per variation', () => {
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				variations={variations}
				onSelect={jest.fn()}
			/>
		);
		expect(screen.getByRole('button', { name: /Horizontal/ })).toBeInTheDocument();
		expect(screen.getByRole('button', { name: /Vertical/ })).toBeInTheDocument();
	});

	test('invokes onSelect with the chosen variation', () => {
		const onSelect = jest.fn();
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				variations={variations}
				onSelect={onSelect}
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Vertical/ }));
		expect(onSelect).toHaveBeenCalledWith(variations[1]);
	});

	test('renders nothing for variations when array is empty', () => {
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick"
				variations={[]}
				onSelect={jest.fn()}
			/>
		);
		expect(screen.queryByRole('button')).not.toBeInTheDocument();
	});
});
```

- [ ] Run, expect failure:

```bash
npx jest tests/unit/components/shared/DsgoBlockPlaceholder.test.js
```

Expected: FAIL with module-not-found.

### Step 5.2: Implement the component

- [ ] Create `src/components/shared/DsgoBlockPlaceholder/index.js`:

```javascript
/**
 * DsgoBlockPlaceholder
 *
 * First-insert wizard for compound blocks. Modeled directly on the proven
 * pattern in src/blocks/modal/components/ModalPlaceholder.js and
 * src/blocks/form-builder/components/FormBuilderPlaceholder.js.
 *
 * Theme 1 of the editor UX design migrates ~8 compound blocks onto this
 * component to give every block a consistent onboarding experience.
 *
 * Usage:
 *
 *   <DsgoBlockPlaceholder
 *     icon="block-default"
 *     label={__('Tabs', 'designsetgo')}
 *     instructions={__('Choose a starting layout.', 'designsetgo')}
 *     variations={[
 *       { name: 'horizontal', title: 'Horizontal', description: '...', icon: 'align-center' },
 *     ]}
 *     onSelect={(variation) => {
 *       setAttributes(variation.attributes);
 *       replaceInnerBlocks(clientId, createBlocksFromInnerBlocksTemplate(variation.innerBlocks));
 *     }}
 *   />
 */
import { Placeholder, Button, Icon } from '@wordpress/components';

export function DsgoBlockPlaceholder({
	icon,
	label,
	instructions,
	variations,
	onSelect,
	className = '',
}) {
	return (
		<Placeholder
			icon={icon}
			label={label}
			instructions={instructions}
			className={`dsgo-block-placeholder ${className}`.trim()}
		>
			{variations.length > 0 && (
				<div className="dsgo-block-placeholder__variations">
					{variations.map((variation) => (
						<Button
							key={variation.name}
							className={`dsgo-block-placeholder__variation dsgo-block-placeholder__variation--${variation.name}`}
							onClick={() => onSelect(variation)}
							variant="secondary"
						>
							{variation.icon && (
								<Icon icon={variation.icon} size={32} />
							)}
							<span className="dsgo-block-placeholder__variation-title">
								{variation.title}
							</span>
							{variation.description && (
								<span className="dsgo-block-placeholder__variation-description">
									{variation.description}
								</span>
							)}
						</Button>
					))}
				</div>
			)}
		</Placeholder>
	);
}
```

- [ ] Create `src/components/shared/DsgoBlockPlaceholder/style.scss`:

```scss
.dsgo-block-placeholder {
	&__variations {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
		gap: 12px;
		width: 100%;
		margin-top: 16px;
	}

	&__variation {
		display: flex !important;
		flex-direction: column;
		align-items: center;
		justify-content: flex-start;
		text-align: center;
		padding: 16px !important;
		height: auto !important;
		min-height: 120px;

		&-title {
			font-weight: 600;
			margin-top: 8px;
		}

		&-description {
			font-size: 12px;
			color: var(--wp-admin-theme-color-darker-10, #757575);
			margin-top: 4px;
		}
	}
}
```

- [ ] Update `src/components/shared/index.js`:

```javascript
export { DsgoInspectorPanel } from './DsgoInspectorPanel';
export { DsgoBlockPlaceholder } from './DsgoBlockPlaceholder';
```

- [ ] Add the SCSS import per the project's MANDATORY style import rule. In `src/styles/style.scss` AND `src/styles/editor.scss`, add:

```scss
@import '../components/shared/DsgoBlockPlaceholder/style.scss';
```

(Place near other component imports; if no shared imports exist yet, add a `// Shared components` section header for clarity.)

### Step 5.3: Run tests, expect pass

- [ ] Run:

```bash
npx jest tests/unit/components/shared/DsgoBlockPlaceholder.test.js
```

Expected: 4 tests PASS.

### Step 5.4: Build, lint, verify CSS lands in build

- [ ] Run:

```bash
npm run build && npm run lint:js -- src/components/shared/DsgoBlockPlaceholder/ \
                && npm run lint:css -- 'src/components/shared/DsgoBlockPlaceholder/**/*.scss'
```

Expected: all pass.

- [ ] Verify the placeholder CSS made it into both build outputs (per the CLAUDE.md "Common Pitfalls" checklist):

```bash
grep -i "dsgo-block-placeholder" build/style-index.css build/index.css
```

Expected: matches in both files.

### Step 5.5: Commit and open PR

```bash
git add src/components/shared/DsgoBlockPlaceholder/ src/components/shared/index.js \
        src/styles/style.scss src/styles/editor.scss \
        tests/unit/components/shared/DsgoBlockPlaceholder.test.js
git commit -m "feat(components): add DsgoBlockPlaceholder for first-insert wizards

Foundation for Theme 1 placeholder & onboarding parity. Generalizes the
proven ModalPlaceholder / FormBuilderPlaceholder pattern into a single
component that the ~8 compound blocks currently lacking onboarding can
adopt with a single import.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin HEAD
gh pr create --title "feat(components): add DsgoBlockPlaceholder for first-insert wizards" --body "..."
```

---

## Task 6 — `<DsgoChildToolbar>` + `useTablistKeyboard`

**PR title:** `feat(components,hooks): add DsgoChildToolbar and useTablistKeyboard`

**Files:**
- Create: `src/components/shared/DsgoChildToolbar/index.js`
- Modify: `src/components/shared/index.js` (add re-export)
- Create: `src/hooks/useTablistKeyboard.js`
- Modify: `src/hooks/index.js` (add re-export)
- Create: `tests/unit/components/shared/DsgoChildToolbar.test.js`
- Create: `tests/unit/hooks/useTablistKeyboard.test.js`
- Modify: `.claude/CLAUDE.md` (add the contribution rule + variation-vs-block guideline)

**No block migrations.** Theme 5 will sweep these into tabs, slider, scroll-slides, accordion, image-accordion in its own PR sequence.

### Step 6.1: Write failing tests for `useTablistKeyboard`

- [ ] Create `tests/unit/hooks/useTablistKeyboard.test.js`:

```javascript
/**
 * useTablistKeyboard Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useTablistKeyboard } from '../../../src/hooks/useTablistKeyboard';

describe('useTablistKeyboard', () => {
	const makeEvent = (key) => ({ key, preventDefault: jest.fn() });

	test('ArrowRight advances index', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 0, onChange })
		);
		const evt = makeEvent('ArrowRight');
		result.current.onKeyDown(evt);
		expect(evt.preventDefault).toHaveBeenCalled();
		expect(onChange).toHaveBeenCalledWith(1);
	});

	test('ArrowRight wraps from last to first', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 2, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowRight'));
		expect(onChange).toHaveBeenCalledWith(0);
	});

	test('ArrowLeft decrements index', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 1, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowLeft'));
		expect(onChange).toHaveBeenCalledWith(0);
	});

	test('ArrowLeft wraps from first to last', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 0, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowLeft'));
		expect(onChange).toHaveBeenCalledWith(2);
	});

	test('Home jumps to 0, End jumps to last', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 5, activeIndex: 2, onChange })
		);
		result.current.onKeyDown(makeEvent('Home'));
		expect(onChange).toHaveBeenLastCalledWith(0);
		result.current.onKeyDown(makeEvent('End'));
		expect(onChange).toHaveBeenLastCalledWith(4);
	});

	test('vertical orientation swaps Arrow keys', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({
				count: 3,
				activeIndex: 0,
				onChange,
				orientation: 'vertical',
			})
		);
		result.current.onKeyDown(makeEvent('ArrowDown'));
		expect(onChange).toHaveBeenCalledWith(1);
		result.current.onKeyDown(makeEvent('ArrowUp'));
		expect(onChange).toHaveBeenCalledWith(2);
	});

	test('does nothing for unrelated keys', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 3, activeIndex: 1, onChange })
		);
		result.current.onKeyDown(makeEvent('Enter'));
		expect(onChange).not.toHaveBeenCalled();
	});

	test('does nothing when count is 0', () => {
		const onChange = jest.fn();
		const { result } = renderHook(() =>
			useTablistKeyboard({ count: 0, activeIndex: 0, onChange })
		);
		result.current.onKeyDown(makeEvent('ArrowRight'));
		expect(onChange).not.toHaveBeenCalled();
	});
});
```

### Step 6.2: Implement `useTablistKeyboard`

- [ ] Create `src/hooks/useTablistKeyboard.js`:

```javascript
/**
 * useTablistKeyboard
 *
 * Returns a keydown handler implementing ARIA Authoring Practices tablist
 * keyboard navigation (ArrowLeft/Right or ArrowUp/Down + Home/End with
 * wraparound). Generalized from src/blocks/tabs/edit.js lines 141-176.
 *
 * Theme 5 uses this for tabs, slider, scroll-slides, accordion,
 * image-accordion.
 *
 * @param {Object}   params
 * @param {number}   params.count        Number of children.
 * @param {number}   params.activeIndex  Currently active index.
 * @param {Function} params.onChange     Called with new index.
 * @param {'horizontal'|'vertical'} [params.orientation='horizontal']
 * @return {{ onKeyDown: Function }}
 */
export function useTablistKeyboard({
	count,
	activeIndex,
	onChange,
	orientation = 'horizontal',
}) {
	const onKeyDown = (event) => {
		if (count === 0) {
			return;
		}
		const prev = orientation === 'horizontal' ? 'ArrowLeft' : 'ArrowUp';
		const next = orientation === 'horizontal' ? 'ArrowRight' : 'ArrowDown';

		let newIndex = activeIndex;
		if (event.key === next) {
			newIndex = activeIndex < count - 1 ? activeIndex + 1 : 0;
		} else if (event.key === prev) {
			newIndex = activeIndex > 0 ? activeIndex - 1 : count - 1;
		} else if (event.key === 'Home') {
			newIndex = 0;
		} else if (event.key === 'End') {
			newIndex = count - 1;
		} else {
			return;
		}
		event.preventDefault();
		if (newIndex !== activeIndex) {
			onChange(newIndex);
		}
	};

	return { onKeyDown };
}
```

- [ ] Update `src/hooks/index.js`:

```javascript
export { useUniqueBlockId } from './useUniqueBlockId';
export { useBlockColors } from './useBlockColors';
export { useTablistKeyboard } from './useTablistKeyboard';
```

### Step 6.3: Run tablist tests, expect pass

- [ ] Run:

```bash
npx jest tests/unit/hooks/useTablistKeyboard.test.js
```

Expected: 8 tests PASS.

### Step 6.4: Write failing tests for `<DsgoChildToolbar>`

- [ ] Create `tests/unit/components/shared/DsgoChildToolbar.test.js`:

```javascript
/**
 * DsgoChildToolbar Tests
 *
 * @package
 */

import { render, screen, fireEvent } from '@testing-library/react';

const insertBlock = jest.fn();
const removeBlock = jest.fn();
const moveBlocksUp = jest.fn();
const moveBlocksDown = jest.fn();
const replaceBlock = jest.fn();
const useDispatch = jest.fn(() => ({
	insertBlock,
	removeBlock,
	moveBlocksUp,
	moveBlocksDown,
	replaceBlock,
}));
const createBlock = jest.fn((name, attrs) => ({
	clientId: 'new',
	name,
	attributes: attrs,
}));
const cloneBlock = jest.fn((block) => ({ ...block, clientId: 'cloned' }));

jest.mock('@wordpress/data', () => ({
	useDispatch: (...args) => useDispatch(...args),
	useSelect: (cb) =>
		cb(() => ({
			getBlockRootClientId: () => 'parent-id',
			getBlock: () => ({
				clientId: 'child-id',
				name: 'designsetgo/tab',
				attributes: {},
				innerBlocks: [],
			}),
			getBlockIndex: () => 1,
		})),
}));
jest.mock('@wordpress/blocks', () => ({
	createBlock: (...args) => createBlock(...args),
	cloneBlock: (...args) => cloneBlock(...args),
}));
jest.mock('@wordpress/block-editor', () => ({
	BlockControls: ({ children }) => <div data-testid="block-controls">{children}</div>,
}));

import { DsgoChildToolbar } from '../../../../src/components/shared/DsgoChildToolbar';

describe('DsgoChildToolbar', () => {
	beforeEach(() => {
		insertBlock.mockClear();
		removeBlock.mockClear();
		moveBlocksUp.mockClear();
		moveBlocksDown.mockClear();
		replaceBlock.mockClear();
		createBlock.mockClear();
		cloneBlock.mockClear();
	});

	test('renders inside BlockControls slot', () => {
		render(<DsgoChildToolbar clientId="child-id" childBlockName="designsetgo/tab" />);
		expect(screen.getByTestId('block-controls')).toBeInTheDocument();
	});

	test('Add button inserts a new sibling at index+1', () => {
		render(<DsgoChildToolbar clientId="child-id" childBlockName="designsetgo/tab" />);
		fireEvent.click(screen.getByRole('button', { name: /Add/i }));
		expect(createBlock).toHaveBeenCalledWith('designsetgo/tab', {});
		expect(insertBlock).toHaveBeenCalledWith(
			expect.objectContaining({ name: 'designsetgo/tab' }),
			2,
			'parent-id',
			false
		);
	});

	test('Duplicate button clones the current block at index+1', () => {
		render(<DsgoChildToolbar clientId="child-id" childBlockName="designsetgo/tab" />);
		fireEvent.click(screen.getByRole('button', { name: /Duplicate/i }));
		expect(cloneBlock).toHaveBeenCalled();
		expect(insertBlock).toHaveBeenCalledWith(
			expect.objectContaining({ clientId: 'cloned' }),
			2,
			'parent-id',
			false
		);
	});

	test('Move Up calls moveBlocksUp with the clientId', () => {
		render(<DsgoChildToolbar clientId="child-id" childBlockName="designsetgo/tab" />);
		fireEvent.click(screen.getByRole('button', { name: /Move up/i }));
		expect(moveBlocksUp).toHaveBeenCalledWith(['child-id'], 'parent-id');
	});

	test('Move Down calls moveBlocksDown with the clientId', () => {
		render(<DsgoChildToolbar clientId="child-id" childBlockName="designsetgo/tab" />);
		fireEvent.click(screen.getByRole('button', { name: /Move down/i }));
		expect(moveBlocksDown).toHaveBeenCalledWith(['child-id'], 'parent-id');
	});

	test('Remove button calls removeBlock with the clientId', () => {
		render(<DsgoChildToolbar clientId="child-id" childBlockName="designsetgo/tab" />);
		fireEvent.click(screen.getByRole('button', { name: /Remove/i }));
		expect(removeBlock).toHaveBeenCalledWith('child-id', false);
	});
});
```

### Step 6.5: Implement `<DsgoChildToolbar>`

- [ ] Create `src/components/shared/DsgoChildToolbar/index.js`:

```javascript
/**
 * DsgoChildToolbar
 *
 * Drop-in BlockControls toolbar for child blocks of compound parents
 * (tab, slide, accordion-item, etc.). Provides Add / Duplicate / Move /
 * Remove actions wired to core/block-editor.
 *
 * Theme 5 uses this to consolidate the three different "add child"
 * affordances currently scattered across the codebase (inline canvas
 * buttons in tabs/scroll-marquee, default appender in accordion, none
 * in reveal).
 *
 * Usage:
 *
 *   // inside child block edit():
 *   <DsgoChildToolbar
 *     clientId={clientId}
 *     childBlockName="designsetgo/tab"
 *     newAttributes={{ label: __('New Tab', 'designsetgo') }}
 *   />
 */
import { __ } from '@wordpress/i18n';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, cloneBlock } from '@wordpress/blocks';
import {
	plus,
	copy,
	chevronUp,
	chevronDown,
	trash,
} from '@wordpress/icons';

export function DsgoChildToolbar({
	clientId,
	childBlockName,
	newAttributes = {},
}) {
	const { insertBlock, removeBlock, moveBlocksUp, moveBlocksDown } =
		useDispatch('core/block-editor');

	const { rootClientId, block, index } = useSelect(
		(select) => {
			const store = select('core/block-editor');
			const root = store.getBlockRootClientId(clientId);
			return {
				rootClientId: root,
				block: store.getBlock(clientId),
				index: store.getBlockIndex(clientId),
			};
		},
		[clientId]
	);

	const onAdd = () => {
		const newBlock = createBlock(childBlockName, newAttributes);
		insertBlock(newBlock, index + 1, rootClientId, false);
	};
	const onDuplicate = () => {
		if (!block) {
			return;
		}
		insertBlock(cloneBlock(block), index + 1, rootClientId, false);
	};
	const onMoveUp = () => moveBlocksUp([clientId], rootClientId);
	const onMoveDown = () => moveBlocksDown([clientId], rootClientId);
	const onRemove = () => removeBlock(clientId, false);

	return (
		<BlockControls>
			<ToolbarGroup>
				<ToolbarButton
					icon={plus}
					label={__('Add', 'designsetgo')}
					onClick={onAdd}
				/>
				<ToolbarButton
					icon={copy}
					label={__('Duplicate', 'designsetgo')}
					onClick={onDuplicate}
				/>
				<ToolbarButton
					icon={chevronUp}
					label={__('Move up', 'designsetgo')}
					onClick={onMoveUp}
				/>
				<ToolbarButton
					icon={chevronDown}
					label={__('Move down', 'designsetgo')}
					onClick={onMoveDown}
				/>
				<ToolbarButton
					icon={trash}
					label={__('Remove', 'designsetgo')}
					onClick={onRemove}
					isDestructive
				/>
			</ToolbarGroup>
		</BlockControls>
	);
}
```

- [ ] Update `src/components/shared/index.js`:

```javascript
export { DsgoInspectorPanel } from './DsgoInspectorPanel';
export { DsgoBlockPlaceholder } from './DsgoBlockPlaceholder';
export { DsgoChildToolbar } from './DsgoChildToolbar';
```

### Step 6.6: Run all new tests

- [ ] Run:

```bash
npx jest tests/unit/components/shared/DsgoChildToolbar.test.js \
         tests/unit/hooks/useTablistKeyboard.test.js
```

Expected: all PASS.

### Step 6.7: Update CLAUDE.md and the add-block skill with the contribution rule

- [ ] In `.claude/CLAUDE.md`, find the **Architecture** section and append a new subsection right after the existing bullets:

```markdown
### Shared Primitives First

Before adding a pattern to a block, check `src/hooks/` and `src/components/shared/`. If it's the second time you're writing a pattern, extract it. Available primitives:

- `useUniqueBlockId({ clientId, attributeName, value, setAttributes, prefix?, length? })` — seeds a stable id attribute from clientId.
- `useBlockColors({ attributes, setAttributes, entries })` — wraps `ColorGradientSettingsDropdown` boilerplate.
- `useTablistKeyboard({ count, activeIndex, onChange, orientation? })` — ARIA tablist keyboard nav.
- `cssVars(attributes, map)` — pure attribute → CSS-var inline-style mapper (in `src/utils/`).
- `<DsgoInspectorPanel>` — `ToolsPanel` wrapper enforcing the 3-panel inspector convention (Settings / Style / Advanced).
- `<DsgoBlockPlaceholder>` — first-insert wizard for compound blocks.
- `<DsgoChildToolbar>` — Add/Duplicate/Move/Remove for child blocks of compound parents.

### Variation vs New Block

If a new block differs from an existing one only by 1–3 attributes **and shares the same `save()` output structure**, register a `block.json` variation, not a new block. The `save()` constraint is the technical blocker: variations cannot carry differing markup, so differing output forces a new block + deprecations.
```

- [ ] In `.claude/skills/add-block/SKILL.md`, append a new section right before the existing "Critical Patterns to Follow" section:

```markdown
## Before Scaffolding — Check Shared Primitives

Before generating any block code, check `src/hooks/` and `src/components/shared/` for primitives that already cover the patterns you're about to write. The plugin maintains shared building blocks specifically to keep new blocks consistent with the rest of the codebase. See the **Shared Primitives First** and **Variation vs New Block** sections of `.claude/CLAUDE.md` for the full list and the variation-vs-block decision rule.

If a new block differs from an existing one only by 1–3 attributes and shares the same `save()` output, register a variation in the existing block's `block.json` instead of creating a new block.
```

### Step 6.8: Build, lint, full test suite

- [ ] Run:

```bash
npm run build && npm run lint:js && npm run lint:css && npx jest
```

Expected: all green. The full Jest run catches any unintended fallout from the small migration changes earlier in the plan.

### Step 6.9: Commit and open PR

```bash
git add src/components/shared/DsgoChildToolbar/ src/hooks/useTablistKeyboard.js \
        src/hooks/index.js src/components/shared/index.js \
        tests/unit/components/shared/DsgoChildToolbar.test.js \
        tests/unit/hooks/useTablistKeyboard.test.js \
        .claude/CLAUDE.md .claude/skills/add-block/SKILL.md
git commit -m "feat(components,hooks): add DsgoChildToolbar and useTablistKeyboard

Foundation for Theme 5 editor interaction patterns. Codifies the toolbar
controls (Add/Duplicate/Move/Remove) for child blocks of compound parents
and extracts the ARIA tablist keyboard nav from tabs/edit.js into a
reusable hook. Documents all six primitives in CLAUDE.md so new blocks
consult them first.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin HEAD
gh pr create --title "feat(components,hooks): add DsgoChildToolbar and useTablistKeyboard" --body "..."
```

---

## Summary of PR Sequence

| # | PR | Type | Risk | Tests | Migrations |
|---|---|---|---|---|---|
| 1 | `useUniqueBlockId` | Hook | Low | 5 | tabs, form-builder, modal |
| 2 | `useBlockColors` | Hook | Low–Med | 7 | section, slider, card (1 panel each) |
| 3 | `cssVars` | Utility | Low–Med | 5 | accordion, form-builder, slider (1 styles block each) |
| 4 | `<DsgoInspectorPanel>` | Component | Low | 4 | None — Theme 3 will sweep |
| 5 | `<DsgoBlockPlaceholder>` | Component | Low | 4 | None — Theme 1 will sweep |
| 6 | `<DsgoChildToolbar>` + `useTablistKeyboard` | Component + Hook + Docs | Low | 14 | None — Theme 5 will sweep |

**Total: 6 PRs, 39 new unit tests, 9 block files migrated as proof-of-concept.**

The big-bang migrations of all 26 color blocks, ~30 inspector blocks, ~8 placeholder blocks, and ~10 toolbar blocks are explicitly **deferred** to Themes 1, 3, and 5. This plan ships the primitives plus enough proof-of-concept migrations to validate each API before downstream themes adopt them at scale.
