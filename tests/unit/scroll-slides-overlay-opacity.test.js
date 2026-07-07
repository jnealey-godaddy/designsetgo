/**
 * Scroll Slides Block - overlay opacity attribute in save()
 *
 * The overlay scrim opacity used to be hardcoded at 0.8 with no control. It is
 * now backed by an `overlayOpacity` attribute (percent, default 80). These tests
 * guard:
 *  - backward compatibility: the default still emits `--dsgo-overlay-opacity:0.8`;
 *  - a custom value maps percent → fraction;
 *  - out-of-range / non-finite values are clamped to [0,100] and fall back to the
 *    80 default respectively, matching the PHP render path (render.php).
 *
 * The overlay custom properties are only emitted when an overlay color is set,
 * so every case sets `overlayColor`.
 *
 * @package
 */

const {
	registerBlockType,
	unregisterBlockType,
	createBlock,
	serialize,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../src/blocks/scroll-slides/block.json';
import save from '../../src/blocks/scroll-slides/save';

const html = (attributes) => serialize(createBlock(metadata.name, attributes));

describe('Scroll Slides save() - overlay opacity', () => {
	beforeAll(() => {
		// The custom 'designsetgo' category isn't registered in the jest
		// environment; category is irrelevant to save serialization, so use a
		// built-in one to avoid an unrelated invalid-category warning.
		registerBlockType(metadata.name, {
			...metadata,
			category: 'design',
			save,
		});
	});

	afterAll(() => {
		unregisterBlockType(metadata.name);
	});

	it('emits the default 0.8 opacity (backward compatible)', () => {
		expect(html({ overlayColor: '#000000' })).toContain(
			'--dsgo-overlay-opacity:0.8'
		);
	});

	it('maps a custom percent value to a fraction', () => {
		expect(html({ overlayColor: '#000000', overlayOpacity: 50 })).toContain(
			'--dsgo-overlay-opacity:0.5'
		);
	});

	it('emits 0 for a zero value (not treated as unset)', () => {
		expect(html({ overlayColor: '#000000', overlayOpacity: 0 })).toContain(
			'--dsgo-overlay-opacity:0'
		);
	});

	it('clamps an above-range value to 1', () => {
		expect(
			html({ overlayColor: '#000000', overlayOpacity: 150 })
		).toContain('--dsgo-overlay-opacity:1');
	});

	it('clamps a below-range value to 0', () => {
		expect(
			html({ overlayColor: '#000000', overlayOpacity: -20 })
		).toContain('--dsgo-overlay-opacity:0');
	});

	it('does not emit overlay custom properties without an overlay color', () => {
		expect(html({ overlayOpacity: 50 })).not.toContain(
			'--dsgo-overlay-opacity'
		);
	});
});
