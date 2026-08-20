import {
	createBlock,
	serialize,
	getBlockType,
	registerBlockType,
	setCategories,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import metadata from '../block.json';
import save from '../save';
import { HOTSPOT_ITEM_DUPLICATE_OVERRIDES } from '../constants';
import hotspotMetadata from '../../hotspot/block.json';
import hotspotSave from '../../hotspot/save';
import { getSafeHotspotUrl } from '../utils';

const styleSource = readFileSync(resolve(__dirname, '../style.scss'), 'utf8');
const inspectorSource = readFileSync(
	resolve(__dirname, '../components/HotspotItemInspector.js'),
	'utf8'
);
const hotspotInspectorSource = readFileSync(
	resolve(__dirname, '../../hotspot/components/HotspotInspector.js'),
	'utf8'
);

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

if (!getBlockType(metadata.name)) {
	registerBlockType(metadata.name, { ...metadata, save });
}

if (!getBlockType(hotspotMetadata.name)) {
	registerBlockType(hotspotMetadata.name, {
		...hotspotMetadata,
		save: hotspotSave,
	});
}

describe('hotspot item save', () => {
	test('clamps coordinates and saves CSS variables with stable marker and tooltip ids', () => {
		const html = serialize(
			createBlock(metadata.name, {
				uniqueId: 'marker-one',
				x: 140,
				y: -10,
				trigger: 'click',
			})
		);

		expect(html).toContain('--dsgo-hotspot-x:100%');
		expect(html).toContain('--dsgo-hotspot-y:0%');
		expect(html).toContain('id="dsgo-hotspot-marker-marker-one"');
		expect(html).toContain('id="dsgo-hotspot-tooltip-marker-one"');
		expect(html).toContain('aria-expanded="false"');
		expect(html).toContain(
			'aria-controls="dsgo-hotspot-tooltip-marker-one"'
		);
	});

	test('saves an anchor for a linked marker and a button otherwise', () => {
		const linkHtml = serialize(
			createBlock(metadata.name, {
				uniqueId: 'linked',
				url: 'https://example.test/product',
			})
		);
		const buttonHtml = serialize(
			createBlock(metadata.name, { uniqueId: 'button-only' })
		);

		expect(linkHtml).toContain('<a');
		expect(linkHtml).toContain('href="https://example.test/product"');
		expect(linkHtml).toContain(
			'aria-describedby="dsgo-hotspot-tooltip-linked"'
		);
		expect(linkHtml).not.toContain('aria-controls=');
		expect(linkHtml).not.toContain('aria-expanded=');
		expect(buttonHtml).toContain('<button');
		expect(buttonHtml).not.toContain('href=');
	});

	test('rejects unsafe javascript and data marker URLs', () => {
		for (const url of ['javascript:alert(1)', 'data:text/html,unsafe']) {
			const html = serialize(
				createBlock(metadata.name, { uniqueId: 'unsafe', url })
			);

			expect(html).toContain('<button');
			expect(html).not.toContain('href=');
		}
	});

	test('gives an icon-only marker an accessible fallback label', () => {
		const html = serialize(
			createBlock(metadata.name, {
				uniqueId: 'icon-only',
				label: '',
				icon: '★',
				trigger: 'click',
			})
		);

		expect(html).toContain('aria-label="Hotspot"');
	});

	test('inherits non-default parent behavior without serializing child fallbacks', () => {
		const html = serialize(
			createBlock(
				hotspotMetadata.name,
				{
					trigger: 'hover',
					tooltipPosition: 'right',
					tooltipWidth: 300,
					animation: 'pulse',
				},
				[createBlock(metadata.name, { uniqueId: 'inherited' })]
			)
		);

		expect(html).toContain('data-dsgo-hotspot-trigger="hover"');
		expect(html).toContain('dsgo-hotspot--position-right');
		expect(html).toContain('dsgo-hotspot--animation-pulse');
		expect(html).toContain('--dsgo-hotspot-tooltip-width:300px');
		expect(html).toContain('dsgo-hotspot-item--position-inherit');
		expect(html).toContain('dsgo-hotspot-item--animation-inherit');
		expect(html).not.toContain('data-dsgo-hotspot-trigger="inherit"');
	});

	test('keeps inherited and explicit marker triggers as distinct static contracts', () => {
		const inheritedHoverHtml = serialize(
			createBlock(hotspotMetadata.name, { trigger: 'hover' }, [
				createBlock(metadata.name, { uniqueId: 'inherits-hover' }),
			])
		);
		const explicitClickHtml = serialize(
			createBlock(hotspotMetadata.name, { trigger: 'hover' }, [
				createBlock(metadata.name, {
					uniqueId: 'explicit-click',
					trigger: 'click',
				}),
			])
		);

		expect(inheritedHoverHtml).toContain(
			'data-dsgo-hotspot-trigger="hover"'
		);
		expect(inheritedHoverHtml).not.toContain(
			'data-dsgo-hotspot-trigger="inherit"'
		);
		expect(explicitClickHtml).toContain(
			'data-dsgo-hotspot-trigger="click"'
		);
		expect(explicitClickHtml).toContain('aria-expanded="false"');
	});

	test('rejects unsafe URL schemes in the shared editor/save sanitizer', () => {
		expect(getSafeHotspotUrl('javascript:alert(1)')).toBe('');
		expect(getSafeHotspotUrl('data:text/html,unsafe')).toBe('');
		expect(getSafeHotspotUrl('https://example.test/product')).toBe(
			'https://example.test/product'
		);
	});

	test('omits unsafe color values while preserving a picker color', () => {
		const unsafeHtml = serialize(
			createBlock(hotspotMetadata.name, {
				markerColor: 'url(javascript:alert(1))',
				tooltipBackgroundColor: 'expression(alert(1))',
			})
		);
		const safeHtml = serialize(
			createBlock(hotspotMetadata.name, { markerColor: '#123456' })
		);

		expect(unsafeHtml).not.toContain('--dsgo-hotspot-marker-color:');
		expect(unsafeHtml).not.toContain('--dsgo-hotspot-tooltip-background:');
		expect(safeHtml).toContain('--dsgo-hotspot-marker-color:#123456');
	});

	test('serializes centered origin classes by default', () => {
		const html = serialize(
			createBlock(metadata.name, { uniqueId: 'centered' })
		);

		expect(html).toContain('dsgo-hotspot-item--origin-x-center');
		expect(html).toContain('dsgo-hotspot-item--origin-y-center');
	});

	test('serializes origin classes for right/top and left/bottom markers', () => {
		const rightTopHtml = serialize(
			createBlock(metadata.name, {
				uniqueId: 'top-right',
				originX: 'right',
				originY: 'top',
			})
		);
		const leftBottomHtml = serialize(
			createBlock(metadata.name, {
				uniqueId: 'bottom-left',
				originX: 'left',
				originY: 'bottom',
			})
		);

		expect(rightTopHtml).toContain('dsgo-hotspot-item--origin-x-right');
		expect(rightTopHtml).toContain('dsgo-hotspot-item--origin-y-top');
		expect(leftBottomHtml).toContain('dsgo-hotspot-item--origin-x-left');
		expect(leftBottomHtml).toContain('dsgo-hotspot-item--origin-y-bottom');
	});

	test('maps each origin edge to its positioning translation', () => {
		expect(styleSource).toContain('--dsgo-hotspot-translate-x: -50%');
		expect(styleSource).toContain('--dsgo-hotspot-translate-y: -50%');
		expect(styleSource).toMatch(
			/\.dsgo-hotspot-item--origin-x-left\s*\{\s*--dsgo-hotspot-translate-x:\s*0%/
		);
		expect(styleSource).toMatch(
			/\.dsgo-hotspot-item--origin-x-right\s*\{\s*--dsgo-hotspot-translate-x:\s*-100%/
		);
		expect(styleSource).toMatch(
			/\.dsgo-hotspot-item--origin-y-top\s*\{\s*--dsgo-hotspot-translate-y:\s*0%/
		);
		expect(styleSource).toMatch(
			/\.dsgo-hotspot-item--origin-y-bottom\s*\{\s*--dsgo-hotspot-translate-y:\s*-100%/
		);
	});

	test('keeps tooltips closed until the Task 5 state controller opens them', () => {
		expect(styleSource).not.toMatch(
			/\.dsgo-hotspot-item__marker:hover\s*\+\s*\.dsgo-hotspot-item__tooltip/
		);
		expect(styleSource).not.toMatch(
			/\.dsgo-hotspot-item__marker:focus-visible\s*\+\s*\.dsgo-hotspot-item__tooltip/
		);
	});

	test('saves the tooltip hidden and announced as collapsed by default', () => {
		const html = serialize(
			createBlock(metadata.name, { uniqueId: 'closed-tooltip' })
		);

		expect(html).toMatch(/data-dsgo-hotspot-tooltip="true"[^>]*hidden/);
		expect(html).toContain('aria-hidden="true"');
	});

	test('offers scale and fade animation choices consistently', () => {
		expect(hotspotMetadata.attributes.animation.enum).toEqual([
			'none',
			'pulse',
			'scale',
			'fade',
		]);
		expect(metadata.attributes.animation.enum).toEqual([
			'inherit',
			'none',
			'pulse',
			'scale',
			'fade',
		]);

		for (const value of ['scale', 'fade']) {
			expect(inspectorSource).toContain(`value: '${value}'`);
			expect(hotspotInspectorSource).toContain(`value: '${value}'`);
		}
	});

	test('clears a duplicated item id through the parent toolbar override', () => {
		expect(HOTSPOT_ITEM_DUPLICATE_OVERRIDES).toEqual({
			uniqueId: '',
		});
	});
});
