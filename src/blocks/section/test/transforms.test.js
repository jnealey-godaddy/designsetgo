/**
 * Section Block - Transform Tests
 *
 * `boxWidth` caps the section's OUTER box, and the transforms spread
 * `...attributes` wholesale, so a target block that does not register the
 * attribute drops it silently. These tests pin what each target actually does
 * with it, so a cap that vanishes on transform is a failing test rather than a
 * page that quietly widens.
 *
 * @since 2.7.0
 */

// See save.test.js for why these must come from the NESTED @wordpress/blocks
// copy that @wordpress/block-editor bundles.
import {
	createBlock,
	registerBlockType,
	setCategories,
	switchToBlockType,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import transforms from '../transforms';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, transforms });

// Minimal stand-ins for the transform targets. Only the attribute schema
// matters here — WordPress drops any attribute a target does not declare, and
// that dropping is exactly what these tests are about. `core/group` carries
// `dsgoMaxWidth` because the max-width extension registers it there (Section,
// Row and Grid are in that extension's EXCLUDED_BLOCKS, core/group is not).
registerBlockType('designsetgo/row', {
	title: 'Row',
	category: 'designsetgo',
	attributes: {
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		mobileStack: { type: 'boolean', default: false },
	},
	save: () => null,
});

registerBlockType('designsetgo/grid', {
	title: 'Grid',
	category: 'designsetgo',
	attributes: {
		constrainWidth: { type: 'boolean', default: true },
		contentWidth: { type: 'string', default: '' },
		desktopColumns: { type: 'number', default: 3 },
	},
	save: () => null,
});

registerBlockType('core/group', {
	title: 'Group',
	category: 'design',
	attributes: {
		align: { type: 'string' },
		tagName: { type: 'string', default: 'div' },
		layout: { type: 'object' },
		style: { type: 'object' },
		backgroundColor: { type: 'string' },
		dsgoMaxWidth: { type: 'string', default: '' },
	},
	save: () => null,
});

const transformTo = (section, name) =>
	[switchToBlockType(section, name)].flat()[0];

describe('section transforms - boxWidth', () => {
	test('core/group inherits the cap as dsgoMaxWidth', () => {
		// core/group has no outer width control, but it IS eligible for the
		// max-width extension, so the cap survives the transform intact.
		const group = transformTo(
			createBlock(metadata.name, {
				boxWidth: '430px',
				backgroundColor: 'base',
			}),
			'core/group'
		);
		expect(group.name).toBe('core/group');
		expect(group.attributes.dsgoMaxWidth).toBe('430px');
	});

	test('core/group gets no dsgoMaxWidth when no cap was set', () => {
		const group = transformTo(createBlock(metadata.name, {}), 'core/group');
		expect(group.attributes.dsgoMaxWidth).toBe('');
	});

	test('core/group keeps contentWidth on the layout, independent of the cap', () => {
		// The two must not collapse into one another: contentWidth becomes the
		// group's constrained-layout contentSize, boxWidth becomes its box cap.
		const group = transformTo(
			createBlock(metadata.name, {
				boxWidth: '430px',
				contentWidth: '320px',
			}),
			'core/group'
		);
		expect(group.attributes.layout).toEqual({
			type: 'constrained',
			contentSize: '320px',
		});
		expect(group.attributes.dsgoMaxWidth).toBe('430px');
	});

	test('Row drops the cap (it has no equivalent) but keeps contentWidth', () => {
		const row = transformTo(
			createBlock(metadata.name, {
				boxWidth: '430px',
				contentWidth: '320px',
			}),
			'designsetgo/row'
		);
		expect(row.name).toBe('designsetgo/row');
		expect(row.attributes.boxWidth).toBeUndefined();
		expect(row.attributes.contentWidth).toBe('320px');
	});

	test('Grid drops the cap (it has no equivalent) but keeps contentWidth', () => {
		const grid = transformTo(
			createBlock(metadata.name, {
				boxWidth: '430px',
				contentWidth: '320px',
			}),
			'designsetgo/grid'
		);
		expect(grid.name).toBe('designsetgo/grid');
		expect(grid.attributes.boxWidth).toBeUndefined();
		expect(grid.attributes.contentWidth).toBe('320px');
	});
});
