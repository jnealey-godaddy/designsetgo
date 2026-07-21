/**
 * Guard for the "label duplicated into a data-* attribute" blocks
 * (form-builder, countdown-timer).
 *
 * Before the fix, each stored its label twice in the markup — as the visible
 * text AND as a `data-*` attribute on the wrapper — so translating the visible
 * text left the data attribute stale and `save()` no longer matched → Attempt
 * recovery. The fix removes the redundant copy and sources the label from the
 * single remaining location, backed by a markup-change deprecation so existing
 * content stays valid.
 *
 * This suite pins:
 *  1. Translating the visible text keeps the block valid (single source of truth).
 *  2. Legacy markup (with the redundant data-* copy) still parses valid via the
 *     deprecation, preserves the label, and re-serializes WITHOUT the redundant
 *     copy.
 */
import {
	parse,
	serialize,
	createBlock,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

beforeAll(() => {
	['form-builder', 'countdown-timer'].forEach(registerDesignSetGoBlock);
});

describe('form-builder submitButtonText (data-submit-text dedup)', () => {
	it('translating the visible button text keeps the block valid', () => {
		const stored = serialize(
			createBlock('designsetgo/form-builder', {
				submitButtonText: 'Submit',
			})
		);
		expect(stored).toContain('>Submit<');
		// New save() no longer duplicates the label into data-submit-text.
		expect(stored).not.toContain('data-submit-text');

		const translated = stored.split('>Submit<').join('>Pošalji<');
		const [block] = parse(translated);
		expect(block.isValid).toBe(true);
		expect(block.attributes.submitButtonText).toBe('Pošalji');
	});

	it('migrates un-translated legacy markup (data-submit-text) off the redundant attribute', () => {
		// Reconstruct pre-fix markup: the label baked into the block comment (it
		// was a static attribute then) + a matching data-submit-text on the
		// wrapper, in the same attribute position the old save() emitted it.
		const base = serialize(
			createBlock('designsetgo/form-builder', {
				submitButtonText: 'Get in touch',
			})
		);
		const legacy = base
			.replace(
				/^<!-- wp:designsetgo\/form-builder (\{.*?\})? ?-->/,
				(full, existing) => {
					const attrs = existing ? JSON.parse(existing) : {};
					attrs.submitButtonText = 'Get in touch';
					return `<!-- wp:designsetgo/form-builder ${JSON.stringify(
						attrs
					)} -->`;
				}
			)
			.replace(
				/(data-error-message="[^"]*")/,
				'$1 data-submit-text="Get in touch"'
			);

		const [block] = parse(legacy);
		// WP logs an info notice when it migrates the block via the v6 deprecation.
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.submitButtonText).toBe('Get in touch');
		// Re-serialization drops the redundant data attribute.
		expect(serialize(block)).not.toContain('data-submit-text');
	});
});
