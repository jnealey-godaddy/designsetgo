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

const MIGRATED_BLOCKS = ['grid', 'section'];

describe('Theme 3 — Inspector IA migration', () => {
	describe.each(MIGRATED_BLOCKS)('%s', (blockName) => {
		const source = readEdit(blockName);

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

		test('wraps controls in DsgoInspectorPanel.Item with hasValue + onDeselect', () => {
			expect(source).toMatch(/<DsgoInspectorPanel\.Item/);
			expect(source).toMatch(/hasValue=\{\s*\(\)\s*=>/);
			expect(source).toMatch(/onDeselect=\{/);
		});

		test('marks at least one item as isShownByDefault', () => {
			// Without this, every control hides behind the "+" menu and the
			// Settings panel renders empty by default — defeats the convention.
			expect(source).toMatch(/isShownByDefault/);
		});
	});
});
