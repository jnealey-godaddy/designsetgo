import {
	createBlock,
	getBlockType,
	parse,
	registerBlockType,
	serialize,
	setCategories,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import advancedHeadingMetadata from '../../src/blocks/advanced-heading/block.json';
import advancedHeadingSave from '../../src/blocks/advanced-heading/save';
import headingSegmentMetadata from '../../src/blocks/heading-segment/block.json';
import headingSegmentSave from '../../src/blocks/heading-segment/save';
import hotspotMetadata from '../../src/blocks/hotspot/block.json';
import hotspotSave from '../../src/blocks/hotspot/save';
import hotspotItemMetadata from '../../src/blocks/hotspot-item/block.json';
import hotspotItemSave from '../../src/blocks/hotspot-item/save';

function registerBlock(metadata, save) {
	if (!getBlockType(metadata.name)) {
		registerBlockType(metadata.name, { ...metadata, save });
	}
}

function createReviewBlocks() {
	return [
		createBlock(
			advancedHeadingMetadata.name,
			{
				level: 2,
				animatedHeadline: {
					mode: 'rotating',
					effect: 'slide',
					shape: '',
					duration: 1200,
					delay: 0,
					loop: true,
				},
			},
			[
				createBlock(headingSegmentMetadata.name, {
					content: 'Build something',
				}),
				createBlock(headingSegmentMetadata.name, {
					headlineRole: 'animated',
					animatedWords: ['better', 'faster', 'brighter'],
				}),
			]
		),
		createBlock(
			hotspotMetadata.name,
			{
				imageUrl: 'https://s.w.org/images/core/5.3/MtBlanc1.jpg',
				imageAlt: 'Mountain landscape',
				trigger: 'click',
				tooltipPosition: 'top',
				tooltipWidth: 240,
				animation: 'pulse',
			},
			[
				createBlock(hotspotItemMetadata.name, {
					uniqueId: 'summit',
					x: 50,
					y: 25,
					label: '1',
					tooltip:
						'Summit marker: click again or press Escape to close.',
				}),
				createBlock(hotspotItemMetadata.name, {
					uniqueId: 'valley',
					x: 72,
					y: 68,
					label: '2',
					tooltip:
						'Valley marker: opening this closes the other marker.',
				}),
			]
		),
	];
}

describe('Visual Effects review serialization', () => {
	beforeAll(() => {
		setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);
		registerBlock(headingSegmentMetadata, headingSegmentSave);
		registerBlock(advancedHeadingMetadata, advancedHeadingSave);
		registerBlock(hotspotItemMetadata, hotspotItemSave);
		registerBlock(hotspotMetadata, hotspotSave);
	});

	it('round-trips the review examples through their current save functions', () => {
		const markup = serialize(createReviewBlocks());

		expect(serialize(parse(markup))).toBe(markup);
	});
});
