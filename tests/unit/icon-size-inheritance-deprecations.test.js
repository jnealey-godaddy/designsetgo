/**
 * Icon / marker sizes are inherited, not baked into stored markup.
 *
 * Counter, Accordion Item, Timeline Item and Timeline used to write a fixed
 * size into save() output — SVG width/height attributes, an inline marker
 * image size, or an always-present `--dsgo-timeline-marker-size: 16px`. The
 * size now comes from the stylesheet (a theme.json token) unless the author
 * set one, so each block gained a deprecation reproducing the old markup.
 *
 * Every case goes through the real parser: legacy markup must migrate
 * silently (a deprecation's save() reproduces it — no "Attempt Recovery") and
 * re-serialize with no size baked in. The Accordion Item, Timeline Item and
 * Timeline samples are real stored markup, taken from the committed
 * ability-attribute-matrix fixture before this change. Counter's PHP mirror
 * never emits an icon, so its legacy markup is rendered from the v1 save().
 *
 * @package
 */

import {
	createBlock,
	getBlockType,
	getSaveContent,
	parse,
	serialize,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import { readFileSync } from 'fs';
import { join } from 'path';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';
import counterDeprecated from '../../src/blocks/counter/deprecated';

const ACCORDION_ITEM_LEGACY =
	'<!-- wp:designsetgo/accordion-item {"isOpen":true,"uniqueId":"accordion-item-00000000-0000-4000-8000-000000000000"} --><div class="wp-block-designsetgo-accordion-item dsgo-accordion-item dsgo-accordion-item--open" data-initially-open="true"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-right" aria-expanded="true" aria-controls="accordion-item-00000000-0000-4000-8000-000000000000-panel" id="accordion-item-00000000-0000-4000-8000-000000000000-header"><span class="dsgo-accordion-item__title">Accordion Item</span><span class="dsgo-accordion-item__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg></span></button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="accordion-item-00000000-0000-4000-8000-000000000000-header" id="accordion-item-00000000-0000-4000-8000-000000000000-panel"><div class="dsgo-accordion-item__content"></div></div></div><!-- /wp:designsetgo/accordion-item -->';

const TIMELINE_ITEM_LEGACY =
	'<!-- wp:designsetgo/timeline-item --><div class="wp-block-designsetgo-timeline-item dsgo-timeline-item"><div class="dsgo-timeline-item__marker" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="var(--wp--preset--color--primary, #2563eb)" stroke="var(--wp--preset--color--primary, #2563eb)" stroke-width="2"></circle></svg></div><div class="dsgo-timeline-item__wrapper"><h3 class="dsgo-timeline-item__title">Probe</h3><div class="dsgo-timeline-item__content"></div></div></div><!-- /wp:designsetgo/timeline-item -->';

/**
 * Timeline markup as save() wrote it before markerSize lost its default.
 *
 * @param {string} comment    Block comment attributes JSON.
 * @param {number} markerSize Marker size in the inline style.
 * @return {string} Serialized timeline.
 */
const timelineMarkup = (comment, markerSize) =>
	`<!-- wp:designsetgo/timeline ${comment} --><div class="wp-block-designsetgo-timeline dsgo-timeline dsgo-timeline--vertical dsgo-timeline--layout-alternating dsgo-timeline--marker-circle dsgo-timeline--animate" style="--dsgo-timeline-line-color:var(--wp--preset--color--contrast, #e5e7eb);--dsgo-timeline-line-thickness:3px;--dsgo-timeline-connector-style:solid;--dsgo-timeline-marker-size:${markerSize}px;--dsgo-timeline-marker-color:var(--wp--preset--color--primary, #2563eb);--dsgo-timeline-marker-border-color:var(--wp--preset--color--primary, #2563eb);--dsgo-timeline-item-spacing:2rem;--dsgo-timeline-animation-duration:600ms" data-animate="true" data-animation-duration="600" data-stagger-delay="100"><div class="dsgo-timeline__line" aria-hidden="true"></div><div class="dsgo-timeline__items"></div></div><!-- /wp:designsetgo/timeline -->`;

beforeAll(() => {
	[
		'designsetgo/accordion-item',
		'designsetgo/comparison-table',
		'designsetgo/counter',
		'designsetgo/timeline',
		'designsetgo/timeline-item',
	].forEach((name) => {
		if (!getBlockType(name)) {
			registerDesignSetGoBlock(name);
		}
	});
});

describe('legacy markup with a baked-in size migrates silently', () => {
	it('accordion item: drops the SVG width/height', () => {
		const [block] = parse(ACCORDION_ITEM_LEGACY);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.isOpen).toBe(true);

		const serialized = serialize(block);
		expect(serialized).toContain('<svg viewBox="0 0 16 16"');
		expect(serialized).not.toMatch(/<svg[^>]*\swidth=/);
	});

	it('timeline item: drops the SVG width/height', () => {
		const [block] = parse(TIMELINE_ITEM_LEGACY);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.title).toBe('Probe');

		const serialized = serialize(block);
		expect(serialized).toContain('<svg viewBox="0 0 24 24"');
		expect(serialized).not.toMatch(/<svg[^>]*\swidth=/);
	});

	it('timeline item: drops a marker image inline size', () => {
		const [v1] = getBlockType('designsetgo/timeline-item').deprecated;
		// getSaveContent() applies no defaults; createBlock() does.
		const { attributes } = createBlock('designsetgo/timeline-item', {
			imageUrl: 'https://example.com/m.png',
		});
		const legacy = `<!-- wp:designsetgo/timeline-item {"imageUrl":"https://example.com/m.png"} -->${getSaveContent(
			{ ...getBlockType('designsetgo/timeline-item'), save: v1.save },
			attributes
		)}<!-- /wp:designsetgo/timeline-item -->`;
		expect(legacy).toContain('width:16px;height:16px');

		const [block] = parse(legacy);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(serialize(block)).not.toContain('width:16px');
	});

	it('timeline at the old default: migrates to an unset markerSize', () => {
		const [block] = parse(timelineMarkup('{"lineThickness":3}', 16));

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.markerSize).toBeUndefined();
		expect(block.attributes.lineThickness).toBe(3);
		expect(serialize(block)).not.toContain('--dsgo-timeline-marker-size');
	});

	it('timeline with an explicit size: still valid, no migration needed', () => {
		const [block] = parse(
			timelineMarkup('{"lineThickness":3,"markerSize":24}', 24)
		);

		expect(block.isValid).toBe(true);
		expect(block.attributes.markerSize).toBe(24);
		expect(serialize(block)).toContain('--dsgo-timeline-marker-size:24px');
	});

	it('counter with an icon: drops the SVG width/height', () => {
		const { attributes } = createBlock('designsetgo/counter', {
			uniqueId: 'counter-1',
			showIcon: true,
		});
		const legacy = `<!-- wp:designsetgo/counter {"uniqueId":"counter-1","showIcon":true} -->${getSaveContent(
			{
				...getBlockType('designsetgo/counter'),
				save: counterDeprecated[0].save,
			},
			attributes
		)}<!-- /wp:designsetgo/counter -->`;
		expect(legacy).toContain('width="48" height="48"');

		const [block] = parse(legacy);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.showIcon).toBe(true);
		expect(block.attributes.iconSize).toBeUndefined();
		expect(serialize(block)).not.toMatch(/<svg[^>]*\swidth=/);
	});
});

describe('current save() writes a size only for an explicit override', () => {
	it.each([
		['designsetgo/counter', { showIcon: true }, '--dsgo-counter-icon-size'],
		['designsetgo/timeline', {}, '--dsgo-timeline-marker-size'],
	])('%s omits the size when unset', (name, attributes, property) => {
		expect(serialize(createBlock(name, attributes))).not.toContain(
			property
		);
	});

	it.each([
		[
			'designsetgo/counter',
			{ showIcon: true, iconSize: 64 },
			'--dsgo-counter-icon-size:64px',
		],
		[
			'designsetgo/timeline',
			{ markerSize: 24 },
			'--dsgo-timeline-marker-size:24px',
		],
	])('%s writes an explicit size', (name, attributes, expected) => {
		expect(serialize(createBlock(name, attributes))).toContain(expected);
	});
});

describe('comparison table saved by main after the header-semantics change', () => {
	// Real stored markup, saved from main's editor before this change: header
	// scope attributes plus width="20" height="20" on every check/cross icon.
	// It never shipped in a release, but sites running main store it.
	const MAIN_MARKUP = readFileSync(
		join(
			__dirname,
			'__fixtures__/icon-size-legacy/comparison-table.main.html'
		),
		'utf8'
	).trim();

	it('migrates silently and drops the icon width/height', () => {
		expect(MAIN_MARKUP).toContain('width="20" height="20"');

		const [block] = parse(MAIN_MARKUP);

		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);

		const serialized = serialize(block);
		expect(serialized).toContain('scope="row"');
		expect(serialized).not.toContain('width="20"');
	});
});
