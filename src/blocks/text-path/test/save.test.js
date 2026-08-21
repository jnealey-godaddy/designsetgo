import {
	createBlock,
	getBlockType,
	registerBlockType,
	serialize,
	setCategories,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import { TEXT_PATH_PRESETS } from '../../../utils/svg-paths';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

if (!getBlockType(metadata.name)) {
	registerBlockType(metadata.name, { ...metadata, save });
}

describe('text path save', () => {
	test.each(Object.entries(TEXT_PATH_PRESETS))(
		'saves the %s preset with its own path reference',
		(pathType, path) => {
			const html = serialize(
				createBlock(metadata.name, {
					pathType,
					uniqueId: `preset-${pathType}`,
					startOffset: 25,
					direction: 'rtl',
				})
			);

			expect(html).toContain(`id="dsgo-text-path-preset-${pathType}"`);
			expect(html).toContain(`href="#dsgo-text-path-preset-${pathType}"`);
			expect(html).toContain(`d="${path.d}"`);
			expect(html).toContain('startOffset="25%"');
			expect(html).toContain('direction="rtl"');
		}
	);

	test('hides the guide path unless explicitly enabled', () => {
		const hiddenHtml = serialize(createBlock(metadata.name));
		const visibleHtml = serialize(
			createBlock(metadata.name, { showPath: true })
		);

		expect(hiddenHtml).not.toContain('dsgo-text-path__guide');
		expect(visibleHtml).toContain('dsgo-text-path__guide');
	});

	test('saves independent guide-line color, opacity, width, and path width', () => {
		const html = serialize(
			createBlock(metadata.name, {
				showPath: true,
				guideColor: '#123456',
				guideOpacity: 0,
				guideStrokeWidth: 5,
				pathWidth: 60,
			})
		);

		expect(html).toContain('--dsgo-text-path-guide-color:#123456');
		expect(html).toContain('--dsgo-text-path-guide-opacity:0');
		expect(html).toContain('--dsgo-text-path-guide-stroke-width:5');
		expect(html).toContain('--dsgo-text-path-width:60%');
	});

	test('keeps a circle background inside the SVG and aligns a narrower path', () => {
		const html = serialize(
			createBlock(metadata.name, {
				pathType: 'circle',
				circleBackgroundColor: '#fef3c7',
				pathWidth: 60,
				pathAlignment: 'center',
			})
		);

		expect(html).toContain('dsgo-text-path--align-center');
		expect(html).toContain('--dsgo-text-path-circle-background:#fef3c7');
		expect(html).toContain('dsgo-text-path__circle-background');
		expect(html).toMatch(
			/<svg[^>]*><circle class="dsgo-text-path__circle-background"/
		);
	});

	test('keeps the SVG font size independent from typography support classes', () => {
		const html = serialize(createBlock(metadata.name));

		expect(html).not.toContain('has-54-font-size');
		expect(html).toContain('font-size:54px');
	});

	test('falls back from malformed custom data without saving raw SVG', () => {
		const html = serialize(
			createBlock(metadata.name, {
				pathType: 'custom',
				customPath: {
					viewBox: '0 0 100 100',
					d: '<script>alert(1)</script>',
				},
			})
		);

		expect(html).toContain(`d="${TEXT_PATH_PRESETS.wave.d}"`);
		expect(html).not.toContain('<script>');
	});

	test('wraps only valid links around the SVG', () => {
		const validHtml = serialize(
			createBlock(metadata.name, {
				url: 'https://example.test/path',
				target: true,
			})
		);
		const invalidHtml = serialize(
			createBlock(metadata.name, { url: 'javascript:alert(1)' })
		);

		expect(validHtml).toContain('href="https://example.test/path"');
		expect(validHtml).toContain('target="_blank"');
		expect(validHtml).toContain('rel="noopener noreferrer"');
		expect(invalidHtml).not.toContain('href="javascript:');
		expect(invalidHtml).not.toContain('<a ');
	});

	test('saves a bounded motion contract for the frontend controller', () => {
		const html = serialize(
			createBlock(metadata.name, {
				motion: true,
				motionDuration: 16,
				motionDirection: 'reverse',
				startOffset: 8,
			})
		);

		expect(html).toContain('data-dsgo-text-path-motion="true"');
		expect(html).toContain('data-dsgo-text-path-motion-duration="16"');
		expect(html).toContain(
			'data-dsgo-text-path-motion-direction="reverse"'
		);
		expect(html).toContain('data-dsgo-text-path-offset="8"');
	});
});
