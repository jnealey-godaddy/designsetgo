/**
 * Guard for the "label rendered only when a show* toggle is on" blocks
 * (table-of-contents, card).
 *
 * These render their label element conditionally on a SEPARATE boolean toggle.
 * Naively sourcing the label from that element would reset it to the default
 * whenever the toggle is off (the element — and therefore the source — is gone).
 * The fix keeps the element in the markup and hides it with a `--hidden` class,
 * so the sourced value survives visibility toggles, backed by a deprecation that
 * preserves text in content saved while the label was hidden.
 *
 * This suite pins:
 *  1. Translating the visible label keeps the block valid.
 *  2. Toggling the label off preserves its text across a save/parse round trip
 *     (the regression this whole change exists to prevent).
 *  3. Legacy markup saved with the label hidden (no element, text in the block
 *     comment) still parses valid via the deprecation and keeps the text.
 */
import {
	parse,
	serialize,
	createBlock,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

beforeAll(() => {
	['table-of-contents', 'card'].forEach(registerDesignSetGoBlock);
});

describe('table-of-contents titleText (show-toggle)', () => {
	it('translating the visible title keeps the block valid', () => {
		const stored = serialize(
			createBlock('designsetgo/table-of-contents', {
				titleText: 'On this page',
			})
		);
		expect(stored).toContain('>On this page</div>');

		const translated = stored
			.split('>On this page</div>')
			.join('>Na ovoj stranici</div>');
		const [block] = parse(translated);
		expect(block.isValid).toBe(true);
		expect(block.attributes.titleText).toBe('Na ovoj stranici');
	});

	it('preserves the title text when the title is toggled off', () => {
		const block = createBlock('designsetgo/table-of-contents', {
			showTitle: false,
			titleText: 'Kept while hidden',
		});
		const [reparsed] = parse(serialize(block));
		expect(reparsed.isValid).toBe(true);
		expect(reparsed.attributes.showTitle).toBe(false);
		expect(reparsed.attributes.titleText).toBe('Kept while hidden');
	});

	it('migrates legacy hidden-title markup (no element, text in the comment)', () => {
		// Reconstruct pre-fix markup: showTitle:false meant NO title element, and
		// titleText lived in the block comment.
		const shown = serialize(
			createBlock('designsetgo/table-of-contents', {
				showTitle: false,
				titleText: 'Legacy heading',
			})
		);
		const legacy = shown
			// Drop the (now always-rendered, hidden) title element.
			.replace(
				/<div class="dsgo-table-of-contents__title[^"]*">Legacy heading<\/div>/,
				''
			)
			// Put titleText back into the block comment, where it used to live.
			.replace(
				/^<!-- wp:designsetgo\/table-of-contents (\{.*?\}) -->/,
				(full, json) => {
					const attrs = JSON.parse(json);
					attrs.titleText = 'Legacy heading';
					return `<!-- wp:designsetgo/table-of-contents ${JSON.stringify(
						attrs
					)} -->`;
				}
			);

		const [block] = parse(legacy);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.titleText).toBe('Legacy heading');
		// Re-serializes with the always-present, hidden title element.
		expect(serialize(block)).toContain(
			'dsgo-table-of-contents__title--hidden'
		);
		expect(serialize(block)).toContain('>Legacy heading</div>');
	});
});
