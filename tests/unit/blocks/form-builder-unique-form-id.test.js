/**
 * Form Builder — a form gets a fresh ID when its ID is already taken.
 *
 * The server resolves email settings and the field schema by form ID. DSGo
 * patterns ship fixed IDs and duplicating a block copies its ID, so without
 * this every copy of a pattern form shares one ID across the site.
 */
import { shouldReplaceFormId } from '../../../src/blocks/form-builder/utils/unique-form-id';

const saved = (formId) =>
	`<!-- wp:designsetgo/form-builder {"formId":"${formId}"} --><div></div><!-- /wp:designsetgo/form-builder -->`;

describe('shouldReplaceFormId', () => {
	it('seeds a form that has no ID yet', () => {
		expect(
			shouldReplaceFormId({
				clientId: 'a',
				formId: '',
				formBlocks: [{ clientId: 'a', formId: '' }],
				savedContent: '',
			})
		).toBe(true);
	});

	it('keeps the ID of a form already saved in this post', () => {
		expect(
			shouldReplaceFormId({
				clientId: 'a',
				formId: 'contact-professional',
				formBlocks: [{ clientId: 'a', formId: 'contact-professional' }],
				savedContent: saved('contact-professional'),
			})
		).toBe(false);
	});

	it('replaces the ID of a form inserted from a pattern', () => {
		expect(
			shouldReplaceFormId({
				clientId: 'a',
				formId: 'contact-professional',
				formBlocks: [{ clientId: 'a', formId: 'contact-professional' }],
				savedContent: '',
			})
		).toBe(true);
	});

	it('replaces the ID of a duplicated form, not the original', () => {
		const formBlocks = [
			{ clientId: 'original', formId: 'f5b0159a' },
			{ clientId: 'copy', formId: 'f5b0159a' },
		];
		const savedContent = saved('f5b0159a');

		expect(
			shouldReplaceFormId({
				clientId: 'original',
				formId: 'f5b0159a',
				formBlocks,
				savedContent,
			})
		).toBe(false);
		expect(
			shouldReplaceFormId({
				clientId: 'copy',
				formId: 'f5b0159a',
				formBlocks,
				savedContent,
			})
		).toBe(true);
	});

	it('recognises an ID WordPress escaped when saving', () => {
		expect(
			shouldReplaceFormId({
				clientId: 'a',
				formId: 'contact--form',
				formBlocks: [{ clientId: 'a', formId: 'contact--form' }],
				savedContent:
					'<!-- wp:designsetgo/form-builder {"formId":"contact\\u002d\\u002dform"} /-->',
			})
		).toBe(false);
	});

	it('only checks for duplicates when the saved content is unknown', () => {
		expect(
			shouldReplaceFormId({
				clientId: 'a',
				formId: 'newsletter',
				formBlocks: [{ clientId: 'a', formId: 'newsletter' }],
				savedContent: null,
			})
		).toBe(false);
	});
});
