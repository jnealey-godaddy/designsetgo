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
// Colour and border attributes come from block supports, which this bare
// registry does not load; declare the ones the transforms carry.
const colorAttributes = {
	backgroundColor: { type: 'string' },
	textColor: { type: 'string' },
	style: { type: 'object' },
};

registerBlockType(accordionItemMetadata.name, {
	...accordionItemMetadata,
	attributes: { ...accordionItemMetadata.attributes, ...colorAttributes },
	save,
});
registerBlockType(gridMetadata.name, {
	...gridMetadata,
	// `supports.anchor` becomes an attribute through a block-editor filter
	// that this bare registry does not load.
	attributes: {
		...gridMetadata.attributes,
		...colorAttributes,
		anchor: { type: 'string' },
	},
	transforms: gridTransforms,
	save,
});
registerBlockType(fiftyMetadata.name, {
	...fiftyMetadata,
	attributes: { ...fiftyMetadata.attributes, ...colorAttributes },
	transforms: fiftyTransforms,
	save,
});

const stub = (name, attributes, category = 'design') =>
	registerBlockType(name, { title: name, category, attributes, save });

stub('core/paragraph', { content: { type: 'string' } }, 'text');
stub('core/details', {
	summary: { type: 'string' },
	showContent: { type: 'boolean', default: false },
	// WordPress 6.9+: Details sharing a name open one at a time.
	name: { type: 'string' },
	...colorAttributes,
});
stub('core/columns', {
	align: { type: 'string' },
	anchor: { type: 'string' },
	...colorAttributes,
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
		href: { type: 'string' },
		...colorAttributes,
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

	test('each Details keeps its colours on its accordion item', () => {
		const [accordion] = switchToBlockType(
			[
				createBlock('core/details', {
					summary: 'Q',
					backgroundColor: 'accent-1',
					style: {
						color: { text: '#111111' },
						border: { radius: '8px' },
						spacing: { margin: { top: '4px' } },
					},
				}),
				createBlock('core/details', { summary: 'R' }),
			],
			'designsetgo/accordion'
		);

		expect(accordion.innerBlocks[0].attributes).toMatchObject({
			backgroundColor: 'accent-1',
			// Items have no margin support, so margin is not carried.
			style: { color: { text: '#111111' }, border: { radius: '8px' } },
		});
		expect(
			accordion.innerBlocks[0].attributes.style.spacing
		).toBeUndefined();
	});

	test('Details that open one at a time make an accordion that does too', () => {
		const [accordion] = switchToBlockType(
			[
				createBlock('core/details', { summary: 'A', name: 'faq' }),
				createBlock('core/details', { summary: 'B', name: 'faq' }),
			],
			'designsetgo/accordion'
		);

		expect(accordion.attributes.allowMultipleOpen).toBe(false);
	});

	test('a one-open-at-a-time accordion becomes Details sharing a name', () => {
		const accordion = createBlock(
			'designsetgo/accordion',
			{ allowMultipleOpen: false },
			[
				createBlock('designsetgo/accordion-item', {
					title: 'A',
					uniqueId: 'abc123',
				}),
				createBlock('designsetgo/accordion-item', { title: 'B' }),
			]
		);

		const details = switchToBlockType(accordion, 'core/details');

		expect(details.map((d) => d.attributes.name)).toEqual([
			'accordion-abc123',
			'accordion-abc123',
		]);
	});

	test('an accordion that allows several open gives Details no name', () => {
		const accordion = createBlock(
			'designsetgo/accordion',
			{ allowMultipleOpen: true },
			[
				createBlock('designsetgo/accordion-item', { title: 'A' }),
				createBlock('designsetgo/accordion-item', { title: 'B' }),
			]
		);

		const details = switchToBlockType(accordion, 'core/details');

		expect(details.map((d) => d.attributes.name)).toEqual([
			undefined,
			undefined,
		]);
	});

	test('an empty accordion is not offered the transform', () => {
		// Zero Details blocks would delete it.
		const accordion = createBlock('designsetgo/accordion', {}, []);

		expect(switchToBlockType(accordion, 'core/details')).toBe(null);
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

	test('the Columns block keeps its colours, and its gap in Grid shape', () => {
		const columns = createBlock(
			'core/columns',
			{
				backgroundColor: 'accent-2',
				style: {
					spacing: {
						padding: { top: '20px' },
						blockGap: { top: '1em', left: '2em' },
					},
				},
			},
			[createBlock('core/column', {}, [paragraph('A')])]
		);

		const [grid] = switchToBlockType(columns, 'designsetgo/grid');

		expect(grid.attributes.backgroundColor).toBe('accent-2');
		expect(grid.attributes.style.spacing).toEqual({
			padding: { top: '20px' },
			// Grid takes one gap value; the column gap is kept.
			blockGap: '2em',
		});
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

	test('a linked Media & Text is not offered the transform', () => {
		// Fifty Fifty has no image link, so the link would be lost.
		const mediaText = createBlock('core/media-text', {
			mediaUrl: 'https://example.com/a.jpg',
			mediaType: 'image',
			href: 'https://example.com',
		});

		expect(switchToBlockType(mediaText, 'designsetgo/fifty-fifty')).toBe(
			null
		);
	});

	test('colours carry over in both directions', () => {
		const mediaText = createBlock('core/media-text', {
			mediaUrl: 'https://example.com/a.jpg',
			mediaType: 'image',
			backgroundColor: 'accent-4',
			style: { color: { text: '#ffffff' } },
		});

		const [fifty] = switchToBlockType(mediaText, 'designsetgo/fifty-fifty');
		const [back] = switchToBlockType(fifty, 'core/media-text');

		expect(fifty.attributes).toMatchObject({
			backgroundColor: 'accent-4',
			style: { color: { text: '#ffffff' } },
		});
		expect(back.attributes).toMatchObject({
			backgroundColor: 'accent-4',
			style: { color: { text: '#ffffff' } },
		});
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
