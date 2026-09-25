/**
 * Accessibility markup changes - deprecations
 *
 * Three static blocks gained accessibility semantics in save():
 *   - Progress Bar: role="progressbar" + aria-value* (+ aria-label) on the track
 *   - Scroll Marquee: aria-hidden="true" on the five repeat segments
 *   - Comparison Table: scope="col" headers, <th scope="row"> feature labels,
 *     and a <td> (not an empty <th>) in the corner
 *
 * The legacy fixtures under __fixtures__/a11y-legacy/ were serialized by the
 * pre-change save() functions (not by the deprecations under test), so a
 * frozen deprecation copy that drifted from the real old markup fails here.
 *
 * Icon Button gained an optional `ariaLabel` attribute that must not change
 * markup when unset — so existing buttons need no deprecation at all.
 *
 * Deliberately uses the real @wordpress/blocks parser/validator (not mocked)
 * since the thing under test IS the parser's deprecation-matching behavior.
 *
 * @package
 */

import fs from 'fs';
import path from 'path';

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks.
// useBlockProps.save() resolves block supports against THAT copy's registry,
// so registration/parsing here must go through the same instance.
const {
	registerBlockType,
	unregisterBlockType,
	createBlock,
	serialize,
	parse,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

import progressMeta from '../../src/blocks/progress-bar/block.json';
import progressSave from '../../src/blocks/progress-bar/save';
import progressDeprecated from '../../src/blocks/progress-bar/deprecated';
import marqueeMeta from '../../src/blocks/scroll-marquee/block.json';
import marqueeSave from '../../src/blocks/scroll-marquee/save';
import marqueeDeprecated from '../../src/blocks/scroll-marquee/deprecated';
import tableMeta from '../../src/blocks/comparison-table/block.json';
import tableSave from '../../src/blocks/comparison-table/save';
import tableDeprecated from '../../src/blocks/comparison-table/deprecated';
import iconButtonMeta from '../../src/blocks/icon-button/block.json';
import iconButtonSave from '../../src/blocks/icon-button/save';
import iconButtonDeprecated from '../../src/blocks/icon-button/deprecated';

const fixture = (name) =>
	fs.readFileSync(
		path.join(__dirname, '__fixtures__/a11y-legacy', `${name}.html`),
		'utf8'
	);

const BLOCKS = [
	{
		label: 'Progress Bar',
		metadata: progressMeta,
		save: progressSave,
		deprecated: progressDeprecated,
		fixture: 'progress-bar',
		expectCurrent: [
			'role="progressbar"',
			'aria-valuenow="',
			'aria-valuemin="0"',
			'aria-valuemax="100"',
		],
	},
	{
		label: 'Scroll Marquee',
		metadata: marqueeMeta,
		save: marqueeSave,
		deprecated: marqueeDeprecated,
		fixture: 'scroll-marquee',
		expectCurrent: [
			'class="dsgo-scroll-marquee__track-segment" aria-hidden="true"',
		],
	},
	{
		label: 'Comparison Table',
		metadata: tableMeta,
		save: tableSave,
		deprecated: tableDeprecated,
		fixture: 'comparison-table',
		expectCurrent: [
			'<th scope="col"',
			'<th scope="row" class="dsgo-comparison-table__cell dsgo-comparison-table__cell--label"',
			'<td class="dsgo-comparison-table__header-cell dsgo-comparison-table__header-cell--label"></td>',
		],
	},
];

const register = ({ metadata, save, deprecated }) =>
	registerBlockType(metadata.name, {
		...metadata,
		// The custom 'designsetgo' category isn't registered in jest; category
		// is irrelevant to parse/validation.
		category: 'media',
		save,
		deprecated,
	});

describe.each(BLOCKS)('$label - accessibility markup deprecation', (block) => {
	beforeAll(() => register(block));
	afterAll(() => unregisterBlockType(block.metadata.name));

	it('parses every pre-change fixture as valid (silent migration)', () => {
		const parsed = parse(fixture(block.fixture)).filter(
			(b) => b.name === block.metadata.name
		);

		expect(console).toHaveInformed();
		expect(parsed.length).toBeGreaterThan(0);
		parsed.forEach((b) => expect(b.isValid).toBe(true));
	});

	it('re-serializes migrated content with the new semantics', () => {
		const parsed = parse(fixture(block.fixture)).filter(
			(b) => b.name === block.metadata.name
		);
		expect(console).toHaveInformed();

		const markup = serialize(parsed);
		block.expectCurrent.forEach((snippet) =>
			expect(markup).toContain(snippet)
		);
	});

	it('round-trips current content without a deprecation', () => {
		const markup = serialize(createBlock(block.metadata.name));
		const [reparsed] = parse(markup);

		expect(reparsed.isValid).toBe(true);
		expect(serialize(reparsed)).toBe(markup);
	});
});

describe('Progress Bar - accessible name', () => {
	beforeAll(() => register(BLOCKS[0]));
	afterAll(() => unregisterBlockType(progressMeta.name));

	it('names the track with labelText when set', () => {
		const markup = serialize(
			createBlock(progressMeta.name, { labelText: 'Funding goal' })
		);
		expect(markup).toContain('aria-label="Funding goal"');
	});

	it('leaves the fallback name to view.js instead of baking a string', () => {
		const markup = serialize(createBlock(progressMeta.name));
		expect(markup).not.toContain('aria-label=');
	});

	it('clamps aria-valuenow to 0-100', () => {
		const markup = serialize(
			createBlock(progressMeta.name, { percentage: 140 })
		);
		expect(markup).toContain('aria-valuenow="100"');
	});
});

describe('Icon Button - ariaLabel', () => {
	beforeAll(() =>
		register({
			metadata: iconButtonMeta,
			save: iconButtonSave,
			deprecated: iconButtonDeprecated,
		})
	);
	afterAll(() => unregisterBlockType(iconButtonMeta.name));

	it('emits no aria-label when unset, so existing buttons stay valid', () => {
		const markup = serialize(createBlock(iconButtonMeta.name));
		expect(markup).not.toContain('aria-label');
	});

	it('round-trips an icon-only button label through the markup', () => {
		const markup = serialize(
			createBlock(iconButtonMeta.name, {
				text: '',
				ariaLabel: 'Open menu',
			})
		);
		expect(markup).toContain('aria-label="Open menu"');

		const [reparsed] = parse(markup);
		expect(reparsed.isValid).toBe(true);
		expect(reparsed.attributes.ariaLabel).toBe('Open menu');
	});
});
