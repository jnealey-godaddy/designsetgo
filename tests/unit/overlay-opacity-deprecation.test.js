/**
 * Overlay opacity deprecations for Section, Row, Grid and Scroll Accordion Item.
 *
 * Every one of these blocks used to write a fixed `--dsgo-overlay-opacity:0.8`
 * beside `--dsgo-overlay-color`. For NEW blocks save() now writes 0.65 for
 * opaque colours and presets, and 1 for colours that carry their own alpha.
 * Content stored with the old value must stay valid, migrate without an
 * "Attempt Recovery" prompt, and keep its 0.8 strength: migration pins
 * `overlayOpacity: 80`, so editing an existing page never lightens its overlay.
 */

// Registered on the nested @wordpress/blocks copy block-editor uses; see
// src/blocks/section/test/save.test.js for why.
import {
	createBlock,
	getBlockContent,
	getSaveContent,
	parse,
	registerBlockType,
	serialize,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';

import sectionMetadata from '../../src/blocks/section/block.json';
import sectionSave from '../../src/blocks/section/save';
import sectionDeprecated from '../../src/blocks/section/deprecated';
import rowMetadata from '../../src/blocks/row/block.json';
import rowSave from '../../src/blocks/row/save';
import rowDeprecated from '../../src/blocks/row/deprecated';
import gridMetadata from '../../src/blocks/grid/block.json';
import gridSave from '../../src/blocks/grid/save';
import gridDeprecated from '../../src/blocks/grid/deprecated';
import itemMetadata from '../../src/blocks/scroll-accordion-item/block.json';
import itemSave from '../../src/blocks/scroll-accordion-item/save';
import itemDeprecated from '../../src/blocks/scroll-accordion-item/deprecated';
import fixture from '../fixtures/overlay-opacity-cases.json';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

const BLOCKS = [
	[sectionMetadata, sectionSave, sectionDeprecated],
	[rowMetadata, rowSave, rowDeprecated],
	[gridMetadata, gridSave, gridDeprecated],
	[itemMetadata, itemSave, itemDeprecated],
];

BLOCKS.forEach(([metadata, save, deprecated]) =>
	registerBlockType(metadata.name, { ...metadata, save, deprecated })
);

/**
 * Wrap stored HTML in its block comment.
 *
 * @param {string} name  Block name.
 * @param {Object} attrs Comment attributes.
 * @param {string} html  Stored HTML.
 * @return {string} Serialized block.
 */
function wrap(name, attrs, html) {
	return `<!-- wp:${name} ${JSON.stringify(attrs)} -->\n${html}\n<!-- /wp:${name} -->`;
}

describe.each(BLOCKS.map((entry) => [entry[0].name, ...entry]))(
	'%s overlay opacity',
	(name, metadata, save, deprecated) => {
		const [frozen] = deprecated;

		/**
		 * Current save() HTML for the given attributes.
		 *
		 * @param {Object} attrs Attributes.
		 * @return {string} HTML.
		 */
		const currentHTML = (attrs) =>
			getSaveContent(
				{ ...metadata, save },
				createBlock(name, attrs).attributes,
				[]
			);

		test.each([
			['var:preset|color|contrast', '0.65'],
			['#121212', '0.65'],
			['#1212127D', '1'],
			['rgba(0,0,0,.4)', '1'],
		])('save() writes %s at opacity %s', (overlayColor, opacity) => {
			const html = currentHTML({ overlayColor });
			expect(html).toContain(`--dsgo-overlay-opacity:${opacity}`);
			expect(html).not.toContain('--dsgo-overlay-opacity:0.8');
		});

		test('no overlay colour writes no opacity', () => {
			expect(currentHTML({})).not.toContain('--dsgo-overlay-opacity');
		});

		test.each(['var:preset|color|contrast', '#1212127D'])(
			'the newest deprecation reproduces the previous 0.8 save() for %s',
			(overlayColor) => {
				const attributes = createBlock(name, {
					overlayColor,
				}).attributes;
				const previous = currentHTML({ overlayColor }).replace(
					/--dsgo-overlay-opacity:[\d.]+/,
					'--dsgo-overlay-opacity:0.8'
				);

				expect(
					getSaveContent({ ...metadata, ...frozen }, attributes, [])
				).toBe(previous);
			}
		);

		test.each(['var:preset|color|contrast', '#1212127D'])(
			'stored 0.8 markup with %s validates, migrates and keeps its 0.8 strength',
			(overlayColor) => {
				const stored = currentHTML({ overlayColor }).replace(
					/--dsgo-overlay-opacity:[\d.]+/,
					'--dsgo-overlay-opacity:0.8'
				);
				const [block] = parse(wrap(name, { overlayColor }, stored));
				expect(console).toHaveInformed();

				expect(block.name).toBe(name);
				expect(block.isValid).toBe(true);
				expect(block.attributes.overlayColor).toBe(overlayColor);
				expect(block.attributes.overlayOpacity).toBe(80);
				expect(getBlockContent(block)).toBe(stored);
			}
		);

		test('an explicit overlayOpacity is written as a fraction and round-trips', () => {
			const markup = serialize(
				createBlock(name, {
					overlayColor: 'var:preset|color|contrast',
					overlayOpacity: 80,
				})
			);
			expect(markup).toContain('"overlayOpacity":80');
			expect(markup).toContain('--dsgo-overlay-opacity:0.8');

			const [block] = parse(markup);
			expect(block.isValid).toBe(true);
			expect(serialize(block)).toBe(markup);
		});

		test('a new block leaves overlayOpacity unset', () => {
			expect(
				createBlock(name, { overlayColor: '#121212' }).attributes
					.overlayOpacity
			).toBeUndefined();
		});

		test('current markup round-trips without a deprecation', () => {
			const markup = serialize(
				createBlock(name, { overlayColor: '#1212127D' })
			);
			const [block] = parse(markup);

			expect(block.isValid).toBe(true);
			expect(serialize(block)).toBe(markup);
		});
	}
);

describe('section overlay opacity - unconstrained 0.8 content', () => {
	test('an unconstrained section with a 0.8 overlay still migrates', () => {
		const attrs = {
			overlayColor: '#1212127D',
			className: 'dsgo-no-width-constraint',
		};
		const stored = getSaveContent(
			{ ...sectionMetadata, save: sectionSave },
			createBlock(sectionMetadata.name, attrs).attributes,
			[]
		)
			.replace(
				/--dsgo-overlay-opacity:[\d.]+/,
				'--dsgo-overlay-opacity:0.8'
			)
			.replace(/(<div class="dsgo-stack__inner")[^>]*>/, '$1>');

		const [block] = parse(wrap(sectionMetadata.name, attrs, stored));
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.constrainWidth).toBe(false);
		expect(block.attributes.overlayOpacity).toBe(80);
		expect(getBlockContent(block)).toContain('--dsgo-overlay-opacity:0.8');
	});

	test('v10 content, saved with the colour-aware opacity, is not pinned to 0.8', () => {
		// v10 reuses the current save(): an unconstrained section whose comment
		// lacks constrainWidth, written with today's opacity. It must keep that
		// opacity rather than being stamped with the legacy strength.
		const attrs = {
			overlayColor: 'var:preset|color|contrast',
			className: 'dsgo-no-width-constraint',
		};
		const stored = getSaveContent(
			{ ...sectionMetadata, save: sectionSave },
			createBlock(sectionMetadata.name, {
				...attrs,
				constrainWidth: false,
			}).attributes,
			[]
		);
		expect(stored).toContain('--dsgo-overlay-opacity:0.65');

		const [block] = parse(wrap(sectionMetadata.name, attrs, stored));
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.constrainWidth).toBe(false);
		expect(block.attributes.overlayOpacity).toBeUndefined();
		expect(getBlockContent(block)).toContain('--dsgo-overlay-opacity:0.65');
	});

	test('the Abilities fixture markup stored before the change stays valid', () => {
		const stored =
			'<!-- wp:designsetgo/section {"overlayColor":"var:preset|color|base"} --><div class="wp-block-designsetgo-section alignfull dsgo-stack dsgo-stack--has-overlay" style="--dsgo-overlay-color:var(--wp--preset--color--base);--dsgo-overlay-opacity:0.8;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--30)"><div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto"></div></div><!-- /wp:designsetgo/section -->';
		const [block] = parse(stored);
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.overlayOpacity).toBe(80);
		expect(getBlockContent(block)).toContain('--dsgo-overlay-opacity:0.8');
	});
});

describe('section overlay opacity - inserter parity fixture', () => {
	test.each(Object.entries(fixture.sectionSaveMarkup))(
		'save() for %s matches the markup Block_Inserter is pinned to',
		(overlayColor, html) => {
			expect(
				getSaveContent(
					{ ...sectionMetadata, save: sectionSave },
					createBlock(sectionMetadata.name, { overlayColor })
						.attributes,
					[]
				)
			).toBe(html);
		}
	);
});

describe('scroll accordion item - legacy inserter raw preset colour', () => {
	const overlayColor = 'var:preset|color|contrast';
	const stored =
		'<!-- wp:designsetgo/scroll-accordion-item {"overlayColor":"var:preset|color|contrast"} -->\n<div class="wp-block-designsetgo-scroll-accordion-item dsgo-scroll-accordion-item dsgo-scroll-accordion-item--has-overlay" style="--dsgo-overlay-color:var:preset|color|contrast;--dsgo-overlay-opacity:0.8"></div>\n<!-- /wp:designsetgo/scroll-accordion-item -->';

	test('the old inserter markup validates and re-saves converted, keeping 0.8', () => {
		const [block] = parse(stored);
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.overlayColor).toBe(overlayColor);
		expect(block.attributes.overlayOpacity).toBe(80);
		expect(getBlockContent(block)).toBe(
			getSaveContent(
				{ ...itemMetadata, save: itemSave },
				createBlock(itemMetadata.name, {
					overlayColor,
					overlayOpacity: 80,
				}).attributes,
				[]
			)
		);
		expect(getBlockContent(block)).toContain(
			'--dsgo-overlay-color:var(--wp--preset--color--contrast);--dsgo-overlay-opacity:0.8'
		);
	});
});
