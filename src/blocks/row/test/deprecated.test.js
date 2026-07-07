/**
 * Row Block - Style-Variation Deprecation Migration Tests
 *
 * Verifies OLD rows saved before style-kit overlay/hover variation detection
 * existed still parse cleanly against the CURRENT save() + deprecations
 * pipeline instead of showing WordPress's "unexpected or invalid content /
 * Attempt Recovery" warning. Mirrors section/test/deprecated.test.js's v7/v8
 * coverage.
 */

import {
	registerBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

describe('row deprecations - style-kit overlay variation migration', () => {
	// deprecated.js exports newest-first: [v5, v4, v3, v2, v1].
	const [v5Deprecation] = deprecated;

	const canonicalOverlayMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-overlay-dark' })
	);
	const OLD_OVERLAY_VARIATION_MARKUP = canonicalOverlayMarkup.replace(
		' dsgo-flex--has-overlay',
		''
	);

	test('canonical markup carries the overlay class', () => {
		expect(canonicalOverlayMarkup).toContain('dsgo-flex--has-overlay');
		expect(OLD_OVERLAY_VARIATION_MARKUP).not.toContain(
			'dsgo-flex--has-overlay'
		);
	});

	test('old is-style-overlay-dark row (no overlay class) migrates silently against current save()', () => {
		const [block] = parse(OLD_OVERLAY_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/row');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-overlay-dark');
		expect(getBlockContent(block)).toContain('dsgo-flex--has-overlay');
	});

	test('isEligible detects an overlay variation lacking the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-row is-style-overlay-dark dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: 'is-style-overlay-dark' }, [], {
				innerHTML: html,
			})
		).toBe(true);
	});

	test('isEligible ignores rows that already carry the overlay class', () => {
		const html =
			'<div class="wp-block-designsetgo-row is-style-overlay-dark dsgo-flex dsgo-flex--has-overlay"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: 'is-style-overlay-dark' }, [], {
				innerHTML: html,
			})
		).toBe(false);
	});

	test('isEligible ignores rows without an overlay variation', () => {
		const html =
			'<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: '' }, [], { innerHTML: html })
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = { className: 'is-style-overlay-dark', overlayColor: '' };
		expect(v5Deprecation.migrate(attrs)).toBe(attrs);
	});
});

describe('row deprecations - style-kit hover variation migration', () => {
	const [v5Deprecation] = deprecated;

	const canonicalHoverMarkup = serialize(
		createBlock(metadata.name, { className: 'is-style-hover-text-light' })
	);
	const OLD_HOVER_VARIATION_MARKUP = canonicalHoverMarkup.replace(
		' dsgo-flex--has-hover-text',
		''
	);

	test('canonical markup carries the hover-text activation class', () => {
		expect(canonicalHoverMarkup).toContain('dsgo-flex--has-hover-text');
		expect(OLD_HOVER_VARIATION_MARKUP).not.toContain(
			'dsgo-flex--has-hover-text'
		);
	});

	test('old is-style-hover-text-light row (no activation class) migrates silently against current save()', () => {
		const [block] = parse(OLD_HOVER_VARIATION_MARKUP);

		expect(console).toHaveInformed();

		expect(block.name).toBe('designsetgo/row');
		expect(block.isValid).toBe(true);
		expect(block.attributes.className).toBe('is-style-hover-text-light');
		expect(getBlockContent(block)).toContain('dsgo-flex--has-hover-text');
	});

	test('isEligible detects a hover-icon variation lacking its activation class', () => {
		const html =
			'<div class="wp-block-designsetgo-row is-style-hover-icon-blue dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible(
				{ className: 'is-style-hover-icon-blue' },
				[],
				{ innerHTML: html }
			)
		).toBe(true);
	});

	test('isEligible ignores rows without a hover variation', () => {
		const html =
			'<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner"></div></div>';
		expect(
			v5Deprecation.isEligible({ className: '' }, [], { innerHTML: html })
		).toBe(false);
	});

	test('migrate is a passthrough', () => {
		const attrs = {
			className: 'is-style-hover-text-light',
			hoverTextColor: '',
		};
		expect(v5Deprecation.migrate(attrs)).toBe(attrs);
	});
});
