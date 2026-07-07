/**
 * Modal Block - accessible label attributes in save()
 *
 * Covers the two aria-label fallbacks the recent PR made configurable:
 *  - the dialog wrapper's `modalLabel` (previously a dead attribute reference
 *    that always fell back to "Modal"), and
 *  - the close button's `closeButtonLabel` (previously hardcoded "Close modal").
 *
 * For each: an empty value and a whitespace-only value must fall back to the
 * meaningful default (a blank/space-only aria-label is worse than the default
 * for screen readers), and a real value must pass through verbatim.
 *
 * The existing tests/unit/modal.test.js only exercises the frontend DSGModal
 * interactivity class, not the save() markup — this file covers save().
 *
 * @package
 */

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks.
// useBlockProps.save() (used by the block's save()) resolves block supports
// against THAT copy's registry, so registration/serialization must go through
// the same instance.
const {
	registerBlockType,
	unregisterBlockType,
	createBlock,
	serialize,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import metadata from '../../src/blocks/modal/block.json';
import save from '../../src/blocks/modal/save';

const html = (attributes) => serialize(createBlock(metadata.name, attributes));

describe('Modal save() - accessible labels', () => {
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

	describe('dialog aria-label (modalLabel)', () => {
		it('falls back to the default when empty', () => {
			expect(html({})).toContain('aria-label="Modal"');
		});

		it('falls back to the default when whitespace-only', () => {
			expect(html({ modalLabel: '   ' })).toContain('aria-label="Modal"');
		});

		it('uses a custom label verbatim', () => {
			expect(html({ modalLabel: 'Newsletter signup' })).toContain(
				'aria-label="Newsletter signup"'
			);
		});
	});

	describe('close button aria-label (closeButtonLabel)', () => {
		it('falls back to the default when empty', () => {
			expect(html({})).toContain('aria-label="Close modal"');
		});

		it('falls back to the default when whitespace-only', () => {
			expect(html({ closeButtonLabel: '  ' })).toContain(
				'aria-label="Close modal"'
			);
		});

		it('uses a custom label verbatim', () => {
			expect(html({ closeButtonLabel: 'Dismiss' })).toContain(
				'aria-label="Dismiss"'
			);
		});
	});
});
