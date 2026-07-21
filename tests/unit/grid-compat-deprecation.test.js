/**
 * Compatibility deprecation for site-designer grids whose tablet column count
 * lived in a `className` (`dsgo-grid-cols-tablet-N`) while the block comment's
 * `tabletColumns` drifted to a different value, and whose min-width lived only
 * in the inner CSS (`minmax(<w>, 1fr)`) with no `columnMinWidth` attribute.
 *
 * The current save() emits a second, attribute-derived tablet class the stored
 * markup never had, so the block showed "Attempt Recovery". The
 * legacyResponsiveTabletClass deprecation reproduces the stored class set (no
 * attribute tablet class) and the verbatim inner track, then migrate() recovers
 * columnMinWidth + the real tabletColumns and drops the redundant class.
 */
import {
	parse,
	serialize,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

beforeAll(() => {
	registerDesignSetGoBlock('grid');
});

// Captured verbatim from the site-designer page (post 108): tabletColumns drifted
// to the default 2, the real tablet-1 lives in className, columnMinWidth is empty
// but the inner track is minmax(480px, 1fr).
const API_GRID = `<!-- wp:designsetgo/grid {"align":"full","desktopColumns":2,"style":{"spacing":{"blockGap":"var:preset|spacing|50","padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"className":"dsgo-grid-cols-tablet-1"} -->
<div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-2 dsgo-grid-cols-tablet-1 dsgo-grid-cols-mobile-1 dsgo-no-width-constraint" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat(2, minmax(480px, 1fr));align-items:stretch;row-gap:var(--wp--preset--spacing--50);column-gap:var(--wp--preset--spacing--50)"></div></div>
<!-- /wp:designsetgo/grid -->`;

describe('grid site-designer compatibility deprecation (responsive tablet class)', () => {
	it('migrates the drifted-tablet-class / CSS-only-min-width grid without Attempt Recovery', () => {
		const [block] = parse(API_GRID);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);

		// The real tablet count (1) is lifted out of the class; the min width is
		// recovered from the inner track; the redundant class is dropped.
		expect(block.attributes.tabletColumns).toBe(1);
		expect(block.attributes.columnMinWidth).toBe('480px');
		expect(block.attributes.className).toBeUndefined();

		// Re-serializes to consistent current markup: one tablet-1 class, minmax
		// rebuilt from the attribute.
		const out = serialize(block);
		expect(out).toContain('dsgo-grid-cols-tablet-1');
		expect(out).not.toContain('dsgo-grid-cols-tablet-2');
		expect(out).toContain('minmax(480px, 1fr)');
	});
});
