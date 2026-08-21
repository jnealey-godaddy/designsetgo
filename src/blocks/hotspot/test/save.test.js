import {
	createBlock,
	serialize,
	getBlockType,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import metadata from '../block.json';
import save from '../save';

const canvasSource = readFileSync(
	resolve(__dirname, '../components/HotspotCanvas.js'),
	'utf8'
);
const editorStyleSource = readFileSync(
	resolve(__dirname, '../editor.scss'),
	'utf8'
);

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

	test('uses direct marker placement without numeric coordinate controls', () => {
		expect(canvasSource).toContain('onPointerDown={handlePointerDown}');
		expect(canvasSource).toContain('onKeyDown={handleKeyDown}');
		expect(canvasSource).not.toContain('RangeControl');
		expect(canvasSource).not.toContain('dsgo-hotspot__coordinate-controls');
		expect(editorStyleSource).not.toContain(
			'dsgo-hotspot__coordinate-controls'
		);
	});
});
