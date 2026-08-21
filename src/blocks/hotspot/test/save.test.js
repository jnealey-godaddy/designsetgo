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
const editorSource = readFileSync(resolve(__dirname, '../edit.js'), 'utf8');
const saveSource = readFileSync(resolve(__dirname, '../save.js'), 'utf8');
const editorStyleSource = readFileSync(
	resolve(__dirname, '../editor.scss'),
	'utf8'
);
const inspectorSource = readFileSync(
	resolve(__dirname, '../components/HotspotInspector.js'),
	'utf8'
);
const itemEditorSource = readFileSync(
	resolve(__dirname, '../../hotspot-item/edit.js'),
	'utf8'
);
const itemEditorStyleSource = readFileSync(
	resolve(__dirname, '../../hotspot-item/editor.scss'),
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

	test('can focus the editor canvas on the selected hotspot without changing saved output', () => {
		expect(editorSource).toContain('showOnlySelected');
		expect(editorSource).toContain('Show only selected hotspot');
		expect(editorSource).toContain('dsgo-hotspot--editor-selected-only');
		expect(editorStyleSource).toContain(
			'dsgo-hotspot--editor-selected-only'
		);
		expect(saveSource).not.toContain('editor-selected-only');
	});

	test('keeps editor tooltips closed until their marker is clicked', () => {
		expect(itemEditorSource).toContain('isTooltipOpen');
		expect(itemEditorSource).toContain('setTooltipOpen');
		expect(itemEditorStyleSource).toContain(
			'.dsgo-hotspot-item__tooltip.is-open'
		);
		expect(itemEditorStyleSource).toContain('display: none');
	});

	test('does not add an empty custom Advanced inspector panel', () => {
		expect(inspectorSource).not.toContain('panelName="advanced"');
	});
});
