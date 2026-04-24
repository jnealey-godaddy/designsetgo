/**
 * Theme 3 — Inspector IA migration guards
 *
 * Lightweight structural tests that lock in the Theme 3 migration for the
 * blocks already converted to <DsgoInspectorPanel>. Catches accidental
 * regressions (e.g. someone re-introduces PanelBody in a migrated file)
 * without taking on the cost of fully rendering each block's edit component.
 *
 * NOTE on approach: Step 1.4 of the Theme 3 plan originally specified per-block
 * render tests. We chose static analysis here to avoid the heavy WP block-editor
 * store mocking those would require. Full render coverage is deferred to the
 * screenshot-diff CI workflow scoped in Task 0 of the plan.
 *
 * @package
 */

import fs from 'fs';
import path from 'path';

/**
 * Read the block's edit-component source. Most blocks keep it at
 * `src/blocks/{block}/edit.js`, but a few register the edit function
 * inline inside `index.js` (e.g. `counter-group`). Fall back to the
 * index file so the structural guard still covers those.
 *
 * @param {string} blockName Block directory name.
 * @return {string} File contents.
 */
function readEdit(blockName) {
	const editPath = path.resolve(
		__dirname,
		`../../../src/blocks/${blockName}/edit.js`
	);
	if (fs.existsSync(editPath)) {
		return fs.readFileSync(editPath, 'utf8');
	}
	return fs.readFileSync(
		path.resolve(__dirname, `../../../src/blocks/${blockName}/index.js`),
		'utf8'
	);
}

/**
 * Recursively collect every `.js` file under a directory.
 *
 * @param {string} dir Absolute directory path.
 * @return {string[]} Absolute paths of every .js file found.
 */
function collectJsFiles(dir) {
	if (!fs.existsSync(dir)) {
		return [];
	}
	return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
		const full = path.join(dir, entry.name);
		if (entry.isDirectory()) {
			return collectJsFiles(full);
		}
		return entry.isFile() && entry.name.endsWith('.js') ? [full] : [];
	});
}

/**
 * Read `edit.js` plus every `components/**\/*.js` for a block. Used for
 * blocks that split their inspector across sub-components (currently
 * `modal`, `scroll-slides`, `icon-list`) — concatenating the sources lets
 * the structural regex below catch an accidental PanelBody re-introduction
 * anywhere in the block's tree, including nested sub-component folders
 * like `components/inspector/`.
 *
 * @param {string} blockName Block directory name.
 * @return {string} Concatenated source of edit.js + all nested components.
 */
function readEditAndComponents(blockName) {
	const blockDir = path.resolve(
		__dirname,
		`../../../src/blocks/${blockName}`
	);
	const edit = readEdit(blockName);
	const componentSources = collectJsFiles(
		path.join(blockDir, 'components')
	).map((file) => fs.readFileSync(file, 'utf8'));
	return [edit, ...componentSources].join('\n');
}

const MIGRATED_BLOCKS = [
	'grid',
	'section',
	'row',
	'fifty-fifty',
	'accordion-item',
	'tab',
	'slide',
	'modal-trigger',
	'image-accordion-item',
	'flip-card',
	'flip-card-face',
	'accordion',
	'image-accordion',
	'tabs',
	'modal',
	'form-text-field',
	'form-email-field',
	'form-textarea-field',
	'form-url-field',
	'form-phone-field',
	'form-number-field',
	'form-date-field',
	'form-time-field',
	'form-select-field',
	'form-checkbox-field',
	'form-hidden-field',
	'form-builder',
	'scroll-slide',
	'sticky-sections',
	'scroll-marquee',
	'scroll-slides',
	'divider',
	'blobs',
	'icon-list',
	'icon',
	'icon-button',
	'breadcrumbs',
	'timeline',
	'timeline-item',
	'card',
	'comparison-table',
	'progress-bar',
	'counter-group',
	'product-categories-grid',
	'counter',
	'table-of-contents',
	'product-showcase-hero',
	'map',
	'countdown-timer',
	'dynamic-image',
];

// Blocks whose inspector items live in sub-components under
// `src/blocks/{name}/components/**/*.js`. The structural assertions below
// concatenate those sources before matching so the guard holds even
// though `edit.js` alone contains no DsgoInspectorPanel.Item calls.
const COMPOSITE_INSPECTOR_BLOCKS = new Set([
	'modal',
	'scroll-slides',
	'icon-list',
	'icon-button',
	'breadcrumbs',
	'counter',
	'table-of-contents',
	'product-showcase-hero',
	'map',
	'countdown-timer',
]);

describe('Theme 3 — Inspector IA migration', () => {
	describe.each(MIGRATED_BLOCKS)('%s', (blockName) => {
		const source = COMPOSITE_INSPECTOR_BLOCKS.has(blockName)
			? readEditAndComponents(blockName)
			: readEdit(blockName);
		const itemCount = (source.match(/<DsgoInspectorPanel\.Item\b/g) || [])
			.length;
		const hasValueCount = (source.match(/hasValue=\{/g) || []).length;
		const onDeselectCount = (source.match(/onDeselect=\{/g) || []).length;
		const isShownByDefaultCount = (
			source.match(/\bisShownByDefault(?:\s|=|>)/g) || []
		).length;
		const explicitlyHiddenCount = (
			source.match(/isShownByDefault=\{\s*false\s*\}/g) || []
		).length;

		test('imports DsgoInspectorPanel from shared components', () => {
			// Loose match so the assertion still passes once a future migration
			// co-imports DsgoChildToolbar / DsgoBlockPlaceholder from the same
			// barrel: `import { DsgoInspectorPanel, DsgoChildToolbar } from ...`.
			// Accepts 2 levels up (edit.js importers), 3 levels up (sub-components
			// under src/blocks/{name}/components/), or 4 levels up (nested
			// sub-components like src/blocks/{name}/components/inspector/).
			expect(source).toMatch(
				/import\s+\{[^}]*\bDsgoInspectorPanel\b[^}]*\}\s+from\s+['"](?:\.\.\/){2,4}components\/shared['"]/
			);
		});

		test('does not import PanelBody from @wordpress/components', () => {
			// PanelBody belongs to the legacy convention. Any migrated block
			// must drop it; new sub-panels must adopt DsgoInspectorPanel too.
			expect(source).not.toMatch(/\bPanelBody\b/);
		});

		test('uses the canonical "settings" panelName', () => {
			expect(source).toMatch(/panelName=["']settings["']/);
		});

		test('passes panelId={clientId} so reset state scopes per instance', () => {
			expect(source).toMatch(/panelId=\{clientId\}/);
		});

		test('declares a resetAll handler on the Settings panel', () => {
			expect(source).toMatch(/resetAll=\{/);
		});

		test('wraps every control in DsgoInspectorPanel.Item with hasValue + onDeselect', () => {
			expect(itemCount).toBeGreaterThan(0);
			expect(hasValueCount).toBe(itemCount);
			expect(onDeselectCount).toBe(itemCount);
		});

		test('declares isShownByDefault on every item and leaves at least one item visible by default', () => {
			// Without this, every control hides behind the "+" menu and the
			// Settings panel renders empty by default — defeats the convention.
			expect(isShownByDefaultCount).toBe(itemCount);
			expect(
				isShownByDefaultCount - explicitlyHiddenCount
			).toBeGreaterThan(0);
		});
	});
});
