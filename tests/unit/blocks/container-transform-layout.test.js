/**
 * Section / Row / Grid transforms set the target's own orientation.
 *
 * The transforms spread the source's attributes, `layout` included, and
 * WordPress stores the whole layout (orientation too) once an author touches
 * any layout control. Copied as-is, a Section's vertical layout made the Row
 * it became stack its items, with no orientation toggle to undo it.
 */

// See src/blocks/section/test/save.test.js for why these must come from the
// NESTED @wordpress/blocks copy that @wordpress/block-editor bundles.
import {
	createBlock,
	registerBlockType,
	setCategories,
	switchToBlockType,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import sectionMetadata from '../../../src/blocks/section/block.json';
import sectionTransforms from '../../../src/blocks/section/transforms';
import rowMetadata from '../../../src/blocks/row/block.json';
import rowTransforms from '../../../src/blocks/row/transforms';
import gridMetadata from '../../../src/blocks/grid/block.json';
import gridTransforms from '../../../src/blocks/grid/transforms';
import { transformLayout } from '../../../src/utils/transform-layout';

setCategories([
	{ slug: 'designsetgo', title: 'DesignSetGo' },
	{ slug: 'design', title: 'Design' },
	{ slug: 'text', title: 'Text' },
]);

// `layout` becomes an attribute through the layout block support, which this
// bare registry does not load.
const withLayout = (metadata, transforms) =>
	registerBlockType(metadata.name, {
		...metadata,
		attributes: { ...metadata.attributes, layout: { type: 'object' } },
		transforms,
		save: () => null,
	});

withLayout(sectionMetadata, sectionTransforms);
withLayout(rowMetadata, rowTransforms);
withLayout(gridMetadata, gridTransforms);
registerBlockType('core/paragraph', {
	title: 'Paragraph',
	category: 'text',
	attributes: { content: { type: 'string' } },
	save: () => null,
});

const kids = () => [createBlock('core/paragraph', { content: 'One' })];
const transform = (name, attributes, to) =>
	switchToBlockType(createBlock(name, attributes, kids()), to)[0];

const VERTICAL = {
	type: 'flex',
	orientation: 'vertical',
	justifyContent: 'left',
};
const HORIZONTAL = {
	type: 'flex',
	orientation: 'horizontal',
	justifyContent: 'center',
	flexWrap: 'wrap',
};

describe('container transforms', () => {
	test('a Section that stored its layout becomes a horizontal Row', () => {
		const row = transform(
			'designsetgo/section',
			{ layout: VERTICAL },
			'designsetgo/row'
		);

		expect(row.attributes.layout).toEqual({
			...VERTICAL,
			orientation: 'horizontal',
		});
	});

	test('a Row that stored its layout becomes a vertical Section', () => {
		const section = transform(
			'designsetgo/row',
			{ layout: HORIZONTAL },
			'designsetgo/section'
		);

		expect(section.attributes.layout).toEqual({
			...HORIZONTAL,
			orientation: 'vertical',
		});
	});

	test('an untouched layout stays unset, so the target default applies', () => {
		expect(
			transform('designsetgo/section', {}, 'designsetgo/row').attributes
				.layout
		).toBeUndefined();
		expect(
			transform('designsetgo/row', {}, 'designsetgo/section').attributes
				.layout
		).toBeUndefined();
	});

	test('Grid never inherits a flex layout, and gives none away', () => {
		expect(
			transform(
				'designsetgo/section',
				{ layout: VERTICAL },
				'designsetgo/grid'
			).attributes.layout
		).toBeUndefined();
		expect(
			transform(
				'designsetgo/row',
				{ layout: HORIZONTAL },
				'designsetgo/grid'
			).attributes.layout
		).toBeUndefined();
		expect(
			transform(
				'designsetgo/grid',
				{ layout: { type: 'default' } },
				'designsetgo/row'
			).attributes.layout
		).toBeUndefined();
	});
});

describe('transformLayout', () => {
	test('drops a non-flex layout rather than relabelling it', () => {
		expect(
			transformLayout({ type: 'grid', columnCount: 3 }, 'horizontal')
		).toBeUndefined();
	});

	test('keeps a type-less layout, as flex', () => {
		expect(
			transformLayout({ justifyContent: 'right' }, 'vertical')
		).toEqual({
			type: 'flex',
			orientation: 'vertical',
			justifyContent: 'right',
		});
	});
});
