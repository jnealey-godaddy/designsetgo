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
 * Read an `edit.js` file from `src/blocks/{block}/`.
 *
 * @param {string} blockName Block directory name.
 * @return {string} File contents.
 */
function readEdit(blockName) {
	return fs.readFileSync(
		path.resolve(__dirname, `../../../src/blocks/${blockName}/edit.js`),
		'utf8'
	);
}

/**
 * Read `edit.js` plus every `components/*.js` for a block. Used for blocks
 * that split their inspector across sub-components (currently `modal`) —
 * concatenating the sources lets the structural regex below catch an
 * accidental PanelBody re-introduction anywhere in the block's tree.
 *
 * @param {string} blockName Block directory name.
 * @return {string} Concatenated source of edit.js + all components.
 */
function readEditAndComponents(blockName) {
	const blockDir = path.resolve(
		__dirname,
		`../../../src/blocks/${blockName}`
	);
	const edit = fs.readFileSync(path.join(blockDir, 'edit.js'), 'utf8');
	const componentsDir = path.join(blockDir, 'components');
	if (!fs.existsSync(componentsDir)) {
		return edit;
	}
	const componentSources = fs
		.readdirSync(componentsDir)
		.filter((name) => name.endsWith('.js'))
		.map((name) => fs.readFileSync(path.join(componentsDir, name), 'utf8'));
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
];

// Blocks whose inspector items live in sub-components under
// `src/blocks/{name}/components/*.js`. The structural assertions below
// concatenate those sources before matching so the guard holds even
// though `edit.js` alone contains no DsgoInspectorPanel.Item calls.
const COMPOSITE_INSPECTOR_BLOCKS = new Set(['modal']);

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
			expect(source).toMatch(
				/import\s+\{[^}]*\bDsgoInspectorPanel\b[^}]*\}\s+from\s+['"]\.\.\/\.\.\/components\/shared['"]/
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
