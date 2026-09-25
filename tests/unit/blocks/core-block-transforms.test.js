/**
 * Transforms between DSGo blocks and their closest core equivalents:
 * Accordion ↔ Details, Grid ↔ Columns, Fifty Fifty ↔ Media & Text.
 *
 * Targets are minimal stand-ins: WordPress drops any attribute a target does
 * not declare, so only the attribute schema matters here.
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
import accordionMetadata from '../../../src/blocks/accordion/block.json';
import accordionItemMetadata from '../../../src/blocks/accordion-item/block.json';
import accordionTransforms from '../../../src/blocks/accordion/transforms';
import gridMetadata from '../../../src/blocks/grid/block.json';
import gridTransforms from '../../../src/blocks/grid/transforms';
import fiftyMetadata from '../../../src/blocks/fifty-fifty/block.json';
import fiftyTransforms from '../../../src/blocks/fifty-fifty/transforms';

setCategories([
	{ slug: 'designsetgo', title: 'DesignSetGo' },
	{ slug: 'design', title: 'Design' },
	{ slug: 'text', title: 'Text' },
	{ slug: 'media', title: 'Media' },
]);

const save = () => null;

registerBlockType(accordionMetadata.name, {
	...accordionMetadata,
	transforms: accordionTransforms,
	save,
});
registerBlockType(accordionItemMetadata.name, {
	...accordionItemMetadata,
	save,
});
registerBlockType(gridMetadata.name, {
	...gridMetadata,
	// `supports.anchor` becomes an attribute through a block-editor filter
	// that this bare registry does not load.
	attributes: { ...gridMetadata.attributes, anchor: { type: 'string' } },
	transforms: gridTransforms,
	save,
});
registerBlockType(fiftyMetadata.name, {
	...fiftyMetadata,
	transforms: fiftyTransforms,
	save,
});

const stub = (name, attributes, category = 'design') =>
	registerBlockType(name, { title: name, category, attributes, save });

stub('core/paragraph', { content: { type: 'string' } }, 'text');
stub('core/details', {
	summary: { type: 'string' },
	showContent: { type: 'boolean', default: false },
});
stub('core/columns', {
	align: { type: 'string' },
	anchor: { type: 'string' },
});
stub('core/column', {
	style: { type: 'object' },
	backgroundColor: { type: 'string' },
	textColor: { type: 'string' },
	gradient: { type: 'string' },
	className: { type: 'string' },
});
stub('core/group', {
	style: { type: 'object' },
	backgroundColor: { type: 'string' },
	textColor: { type: 'string' },
	gradient: { type: 'string' },
	className: { type: 'string' },
});
stub(
	'core/media-text',
	{
		align: { type: 'string' },
		anchor: { type: 'string' },
		mediaPosition: { type: 'string', default: 'left' },
		mediaId: { type: 'number' },
		mediaUrl: { type: 'string' },
		mediaAlt: { type: 'string', default: '' },
		mediaType: { type: 'string' },
		focalPoint: { type: 'object' },
		imageFill: { type: 'boolean' },
		verticalAlignment: { type: 'string' },
	},
	'media'
);

const paragraph = (content) => createBlock('core/paragraph', { content });

describe('Accordion ↔ core/details', () => {
	test('several Details become one accordion, one item each', () => {
		const details = [
			createBlock('core/details', { summary: 'First' }, [
				paragraph('One'),
			]),
			createBlock(
				'core/details',
				{ summary: '<em>Second</em>', showContent: true },
				[paragraph('Two')]
			),
		];

		const [accordion] = switchToBlockType(details, 'designsetgo/accordion');

		expect(accordion.name).toBe('designsetgo/accordion');
		// Details open independently, so the accordion must not force-close.
		expect(accordion.attributes.allowMultipleOpen).toBe(true);
		expect(accordion.innerBlocks.map((item) => item.name)).toEqual([
			'designsetgo/accordion-item',
			'designsetgo/accordion-item',
		]);
		expect(accordion.innerBlocks[1].attributes).toMatchObject({
			title: '<em>Second</em>',
			isOpen: true,
		});
		expect(accordion.innerBlocks[0].innerBlocks[0].attributes.content).toBe(
			'One'
		);
	});

	test('an accordion becomes one Details per item', () => {
		const accordion = createBlock('designsetgo/accordion', {}, [
			createBlock('designsetgo/accordion-item', { title: 'A' }, [
				paragraph('Alpha'),
			]),
			createBlock(
				'designsetgo/accordion-item',
				{ title: 'B', isOpen: true },
				[paragraph('Beta')]
			),
		]);

		const details = switchToBlockType(accordion, 'core/details');

		expect(details).toHaveLength(2);
		expect(details[1].attributes).toMatchObject({
			summary: 'B',
			showContent: true,
		});
		expect(details[0].innerBlocks[0].attributes.content).toBe('Alpha');
	});
});

describe('Grid ↔ core/columns', () => {
	test('columns become cells, and the column count carries over', () => {
		const columns = createBlock('core/columns', { anchor: 'features' }, [
			createBlock('core/column', {}, [paragraph('Plain')]),
			createBlock('core/column', { backgroundColor: 'accent' }, [
				paragraph('Styled'),
			]),
			createBlock('core/column', {}, [
				paragraph('Two'),
				paragraph('blocks'),
			]),
		]);

		const [grid] = switchToBlockType(columns, 'designsetgo/grid');

		expect(grid.attributes).toMatchObject({
			desktopColumns: 3,
			tabletColumns: 2,
			mobileColumns: 1,
			anchor: 'features',
		});
		// A plain single-block column unwraps; the others keep a Group so
		// their colors and multiple blocks survive.
		expect(grid.innerBlocks.map((cell) => cell.name)).toEqual([
			'core/paragraph',
			'core/group',
			'core/group',
		]);
		expect(grid.innerBlocks[1].attributes.backgroundColor).toBe('accent');
		expect(grid.innerBlocks[2].innerBlocks).toHaveLength(2);
	});

	test('the column count is capped at the Grid maximum', () => {
		const columns = createBlock(
			'core/columns',
			{},
			Array.from({ length: 14 }, (_, i) =>
				createBlock('core/column', {}, [paragraph(String(i))])
			)
		);

		const [grid] = switchToBlockType(columns, 'designsetgo/grid');

		expect(grid.attributes.desktopColumns).toBe(12);
		expect(grid.innerBlocks).toHaveLength(14);
	});

	test('each grid cell becomes its own column', () => {
		const grid = createBlock('designsetgo/grid', {}, [
			paragraph('A'),
			paragraph('B'),
		]);

		const [columns] = switchToBlockType(grid, 'core/columns');

		expect(columns.name).toBe('core/columns');
		expect(columns.innerBlocks.map((c) => c.name)).toEqual([
			'core/column',
			'core/column',
		]);
		expect(columns.innerBlocks[1].innerBlocks[0].attributes.content).toBe(
			'B'
		);
	});
});

describe('Fifty Fifty ↔ core/media-text', () => {
	test('media and content carry over from Media & Text', () => {
		const mediaText = createBlock(
			'core/media-text',
			{
				mediaPosition: 'right',
				mediaId: 12,
				mediaUrl: 'https://example.com/a.jpg',
				mediaAlt: 'A',
				mediaType: 'image',
				verticalAlignment: 'top',
			},
			[paragraph('Copy')]
		);

		const [fifty] = switchToBlockType(mediaText, 'designsetgo/fifty-fifty');

		expect(fifty.attributes).toMatchObject({
			mediaPosition: 'right',
			mediaId: 12,
			mediaUrl: 'https://example.com/a.jpg',
			mediaAlt: 'A',
			verticalAlignment: 'top',
		});
		expect(fifty.innerBlocks[0].attributes.content).toBe('Copy');
	});

	test('a video Media & Text is not offered the transform', () => {
		const mediaText = createBlock('core/media-text', {
			mediaUrl: 'https://example.com/a.mp4',
			mediaType: 'video',
		});

		expect(switchToBlockType(mediaText, 'designsetgo/fifty-fifty')).toBe(
			null
		);
	});

	test('Fifty Fifty goes back to an image-filled Media & Text', () => {
		const fifty = createBlock(
			'designsetgo/fifty-fifty',
			{ mediaUrl: 'https://example.com/a.jpg', mediaId: 3 },
			[paragraph('Copy')]
		);

		const [mediaText] = switchToBlockType(fifty, 'core/media-text');

		expect(mediaText.attributes).toMatchObject({
			mediaUrl: 'https://example.com/a.jpg',
			mediaId: 3,
			mediaType: 'image',
			imageFill: true,
		});
		expect(mediaText.innerBlocks[0].attributes.content).toBe('Copy');
	});
});
