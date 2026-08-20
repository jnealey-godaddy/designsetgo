import {
	createBlock,
	serialize,
	getBlockType,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

describe('hotspot save', () => {
	beforeAll(() => {
		const {
			registerBlockType,
			setCategories,
		} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');
		setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);
		if (!getBlockType(metadata.name)) {
			registerBlockType(metadata.name, { ...metadata, save });
		}
	});

	test('only permits hotspot items and provides the shared defaults', () => {
		expect(metadata.allowedBlocks).toEqual(['designsetgo/hotspot-item']);
		expect(metadata.providesContext).toMatchObject({
			'designsetgo/hotspot/trigger': 'trigger',
			'designsetgo/hotspot/tooltipPosition': 'tooltipPosition',
			'designsetgo/hotspot/tooltipWidth': 'tooltipWidth',
		});
	});

	test('saves the selected media image and its alternative text', () => {
		const html = serialize(
			createBlock(metadata.name, {
				imageUrl: 'https://example.test/hotspot.jpg',
				imageAlt: 'A product marked with hotspots',
			})
		);

		expect(html).toContain('src="https://example.test/hotspot.jpg"');
		expect(html).toContain('alt="A product marked with hotspots"');
	});
});
