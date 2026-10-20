/**
 * Card (always-rendered CTA, plain badge) and Modal Trigger (converted preset
 * padding) save() changes.
 *
 * Each change rewrites saved markup, so stored content from before it has to
 * open as a valid block through the frozen deprecation and re-serialize to the
 * new markup. The helpers register the real blocks, so the real parser and
 * real deprecations run.
 */
import {
	createBlock,
	parse,
	serialize,
	unregisterBlockType,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const CARD = 'designsetgo/card';
const TRIGGER = 'designsetgo/modal-trigger';
const BUTTON = 'designsetgo/icon-button';

beforeAll(() => {
	registerDesignSetGoBlock(BUTTON);
	registerDesignSetGoBlock(CARD);
	registerDesignSetGoBlock(TRIGGER);
});
afterAll(() => {
	unregisterBlockType(TRIGGER);
	unregisterBlockType(CARD);
	unregisterBlockType(BUTTON);
});

const CARD_OPEN =
	'<div class="wp-block-designsetgo-card dsgo-card dsgo-card--standard dsgo-card--style-default has-border-color">';

describe('Card', () => {
	it('keeps the CTA inner blocks when Show CTA is off', () => {
		const saved = serialize(
			createBlock(CARD, { showCta: false }, [
				createBlock(BUTTON, { text: 'Go' }),
			])
		);
		expect(saved).toContain('dsgo-card__cta dsgo-card__cta--hidden');
		expect(saved).toContain('wp:designsetgo/icon-button');

		const [block] = parse(saved);
		expect(block.isValid).toBe(true);
		expect(block.innerBlocks).toHaveLength(1);
		expect(block.attributes.showCta).toBe(false);
	});

	it('writes the CTA unhidden when Show CTA is on', () => {
		const saved = serialize(createBlock(CARD, { showCta: true }));
		expect(saved).toContain('class="dsgo-card__cta"');
		expect(saved).not.toContain('cta--hidden');
	});

	it('migrates stored showCta=false markup (no CTA element) silently', () => {
		const stored =
			'<!-- wp:designsetgo/card {"showCta":false} -->' +
			CARD_OPEN +
			'<div class="dsgo-card__inner"><div class="dsgo-card__content "></div></div></div>' +
			'<!-- /wp:designsetgo/card -->';
		const [block] = parse(stored);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.showCta).toBe(false);
		expect(serialize(block)).toContain('dsgo-card__cta--hidden');
	});

	it('drops the live-region role and "Badge" label from a stored badge', () => {
		const stored =
			'<!-- wp:designsetgo/card {"showCta":false} -->' +
			CARD_OPEN +
			'<span class="dsgo-card__badge dsgo-card__badge--floating dsgo-card__badge--top-right" role="status" aria-label="Badge">Sale</span>' +
			'<div class="dsgo-card__inner"><div class="dsgo-card__content "></div></div></div>' +
			'<!-- /wp:designsetgo/card -->';
		const [block] = parse(stored);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.badgeText).toBe('Sale');
		const saved = serialize(block);
		expect(saved).not.toContain('role="status"');
		expect(saved).not.toContain('aria-label="Badge"');
		expect(saved).toContain('>Sale</span>');
	});
});

describe('Modal Trigger', () => {
	const stored = (padding) =>
		'<!-- wp:designsetgo/modal-trigger {"targetModalId":"m1","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"}}}} -->' +
		'<div class="wp-block-designsetgo-modal-trigger dsgo-justify dsgo-justify--left">' +
		`<button class="dsgo-modal-trigger dsgo-modal-trigger--fill wp-block-button wp-block-button__link wp-element-button" style="${padding}" type="button" data-dsgo-modal-trigger="m1"><span class="dsgo-modal-trigger__text">Open Modal</span></button></div>` +
		'<!-- /wp:designsetgo/modal-trigger -->';

	it('converts a preset padding to a CSS variable', () => {
		const saved = serialize(
			createBlock(TRIGGER, {
				targetModalId: 'm1',
				style: {
					spacing: { padding: { top: 'var:preset|spacing|40' } },
				},
			})
		);
		expect(saved).toContain('padding-top:var(--wp--preset--spacing--40)');
		expect(saved).not.toContain('padding-top:var:preset');
	});

	it('leaves a plain length alone', () => {
		const saved = serialize(
			createBlock(TRIGGER, {
				targetModalId: 'm1',
				style: { spacing: { padding: { top: '12px' } } },
			})
		);
		expect(saved).toContain('padding-top:12px');
	});

	it('migrates stored raw-preset markup silently and fixes it', () => {
		const [block] = parse(stored('padding-top:var:preset|spacing|40'));
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(serialize(block)).toContain(
			'padding-top:var(--wp--preset--spacing--40)'
		);
	});
});
