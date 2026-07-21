/**
 * Compatibility deprecation for forms emitted by the Airo site-designer API.
 *
 * The generator produces v4-era form markup (baked `--dsgo-form-*` tokens, inline
 * submit sizing, `data-submit-text`) but strips the honeypot `aria-hidden` and
 * the message div `aria-atomic` — attributes present in every real plugin
 * version's save() — so no existing deprecation matched and the block showed
 * "Attempt Recovery". The v7 deprecation reproduces that exact shape so those
 * forms migrate silently, and sources the messages from the wrapper's data-*
 * attributes (the generator writes a custom error message into the HTML without
 * mirroring it into the block comment).
 */
import {
	parse,
	serialize,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

beforeAll(() => {
	registerDesignSetGoBlock('form-builder');
});

// Exactly the markup captured from a site-designer-generated page (temp.txt):
// baked tokens + inline submit sizing + data-submit-text, ARIA attributes
// stripped, and a custom data-error-message that is NOT in the block comment.
const API_FORM = `<!-- wp:designsetgo/form-builder {"formId":"cf17bd2a","successMessage":"Thanks! Your message has been sent.","className":"dsgo-form","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left dsgo-form" style="margin-top:var(--wp--preset--spacing--40);--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-border-color:#d1d5db" data-form-id="cf17bd2a" data-ajax-submit="true" data-success-message="Thanks! Your message has been sent." data-error-message="Please check the form and try again." data-submit-text="Send Message"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="cf17bd2a"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Send Message</button></div><div class="dsgo-form__message" role="status" aria-live="polite" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder -->`;

describe('form-builder site-designer compatibility deprecation (v7)', () => {
	it('migrates the ARIA-stripped, token-baked API form without Attempt Recovery', () => {
		const [block] = parse(API_FORM);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);

		// Author-visible values are preserved (submit text + both messages,
		// including the custom error message that was only in the HTML).
		expect(block.attributes.submitButtonText).toBe('Send Message');
		expect(block.attributes.successMessage).toBe(
			'Thanks! Your message has been sent.'
		);
		expect(block.attributes.errorMessage).toBe(
			'Please check the form and try again.'
		);

		// Re-serializes to clean current markup: ARIA restored, redundant
		// data-submit-text gone, baked tokens dropped (inherit the theme).
		const out = serialize(block);
		expect(out).toContain('aria-hidden="true"');
		expect(out).toContain('aria-atomic="true"');
		expect(out).not.toContain('data-submit-text');
		expect(out).not.toContain('--dsgo-form-field-spacing');
		expect(out).not.toContain('--dsgo-form-border-color');
	});

	// Guards the v7-vs-v2 ordering: v7 is registered first and has no
	// isEligible, so in principle it could shadow the older v2 deprecation
	// (also an ARIA-stripped site-designer shape). It does not, because the two
	// resolve on different save() output: v7 sources the messages from the
	// wrapper's data-* attributes (the generator's HTML-only shape), while v2
	// sources errorMessage from the block comment. Genuinely-old content that
	// mirrors the error message into the comment therefore still byte-matches
	// v2 — whose migrate() is a passthrough that preserves the baked spacing
	// tokens — rather than being re-claimed (and re-migrated) by v7.
	it('does not let v7 shadow genuinely-old v2-era content (error message in the comment)', () => {
		// Same ARIA-stripped shape as the generator form, but the custom error
		// message is mirrored into the block comment (as real, hand-edited old
		// content would be) and the baked spacing token is present.
		const V2_ERA_FORM = `<!-- wp:designsetgo/form-builder {"formId":"cf17bd2a","successMessage":"Thanks! Your message has been sent.","errorMessage":"Please check the form and try again.","className":"dsgo-form","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left dsgo-form" style="margin-top:var(--wp--preset--spacing--40);--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem;--dsgo-form-border-color:#d1d5db" data-form-id="cf17bd2a" data-ajax-submit="true" data-success-message="Thanks! Your message has been sent." data-error-message="Please check the form and try again." data-submit-text="Send Message"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="cf17bd2a"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Send Message</button></div><div class="dsgo-form__message" role="status" aria-live="polite" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder -->`;

		const [block] = parse(V2_ERA_FORM);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);

		// Values survive either way, but the tell that v2 (not v7) claimed it is
		// that v2's passthrough migrate() leaves the baked spacing token intact,
		// whereas v7's migrate() strips it to '' (theme inheritance).
		expect(block.attributes.errorMessage).toBe(
			'Please check the form and try again.'
		);
		expect(block.attributes.fieldSpacing).toBe('1.5rem');
	});
});
