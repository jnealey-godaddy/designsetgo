/**
 * Guard: visible text stored by DSGo blocks must survive an HTML-only content
 * edit (the shape a translation pass produces) without invalidating the block.
 *
 * Root cause this pins: blocks used to store their label as a plain attribute in
 * the block-comment JSON *and* render it into the HTML. When the site-designer
 * translation path rewrote the visible HTML text but left the comment JSON stale
 * (English), save() regenerated the old label and no longer matched the stored
 * markup → "Block contains unexpected or invalid content" → Attempt recovery.
 *
 * The fix sources each label from the HTML (source: 'html' | 'text'), matching
 * core/button. With a single source of truth, translating the visible text keeps
 * the block valid, and — because the sourced value overrides the comment — this
 * also HEALS content already broken on translated sites.
 */
import {
	parse,
	createBlock,
	serialize,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

// [ block, attribute, English value, translated value ]
const CASES = [
	// Only blocks whose label element is ALWAYS present when the text is set —
	// i.e. rendered unconditionally, or gated solely on the text's own presence
	// (`{title && …}`). For those, an absent element means empty text, so the
	// sourced value can never silently diverge from what the author typed.
	['designsetgo/icon-button', 'text', 'View Classes', 'Pogledaj časove'],
	['designsetgo/modal-trigger', 'text', 'Open Modal', 'Otvori'],
	['designsetgo/accordion-item', 'title', 'Question', 'Pitanje'],
	['designsetgo/timeline-item', 'title', 'Founded', 'Osnovano'],
	['designsetgo/timeline-item', 'date', 'March 2010', 'Mart 2010.'],
	['designsetgo/counter', 'label', 'Members', 'Članovi'],
	// EXCLUDED — must NOT be sourced without a companion save.js/deprecation fix:
	//
	// - card (title/subtitle/bodyText/badgeText) and table-of-contents (titleText)
	//   render their label ONLY when a SEPARATE boolean toggle is on
	//   (`{showTitle && title && …}`). A sourced attribute is recomputed from the
	//   HTML, so toggling visibility off removes the element and resets the value
	//   to its default — silent data loss. Fixing them means always rendering the
	//   element (hidden via class) + a deprecation to preserve existing hidden
	//   content, so they belong with the group below.
	//
	// - form-builder (submitButtonText) and countdown-timer (completionMessage)
	//   duplicate the label into a `data-*` attribute on the wrapper AND the
	//   visible text; a partial translation desyncs the two copies. The redundant
	//   data attribute must be removed — a markup change requiring a deprecation.
	//
	// All four are tracked for one consolidated deprecation-based follow-up.
];

const BLOCKS = [...new Set(CASES.map(([name]) => name))];

beforeAll(() => {
	BLOCKS.forEach((name) =>
		registerDesignSetGoBlock(name.replace('designsetgo/', ''))
	);
});

describe('translation resilience (HTML-only text edit)', () => {
	it.each(CASES)(
		'%s: translating the visible %s in the HTML keeps the block valid',
		(name, attr, english, translated) => {
			// 1. Author a block with the English label.
			const stored = serialize(createBlock(name, { [attr]: english }));
			expect(stored).toContain(english);

			// 2. Simulate the translation edit: rewrite the visible text in the
			//    HTML body only. (The label is now HTML-sourced, so it is not in
			//    the comment JSON — exactly the single-source-of-truth we want.)
			const translatedMarkup = stored.split(english).join(translated);

			// 3. Re-parse as WordPress would on page load.
			const [block] = parse(translatedMarkup);
			expect(block.isValid).toBe(true);
			expect(block.attributes[attr]).toBe(translated);
		}
	);

	it.each(CASES)(
		'%s: HEALS legacy content where the comment %s is stale English but HTML is translated',
		(name, attr, english, translated) => {
			// Reconstruct pre-fix content: a stale English attribute baked into
			// the block comment, with translated visible HTML.
			const base = serialize(createBlock(name, { [attr]: english }));
			const legacy = base
				.replace(
					/^<!-- wp:([\w/-]+) (\{.*?\})? ?-->/,
					(full, blockName, existing) => {
						const attrs = existing ? JSON.parse(existing) : {};
						attrs[attr] = english; // stale English in the comment
						return `<!-- wp:${blockName} ${JSON.stringify(
							attrs
						)} -->`;
					}
				)
				.split(`>${english}<`)
				.join(`>${translated}<`);

			const [block] = parse(legacy);
			expect(block.isValid).toBe(true);
			// Sourced value wins over the stale comment.
			expect(block.attributes[attr]).toBe(translated);
		}
	);
});
