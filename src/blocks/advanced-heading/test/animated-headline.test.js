import {
	createBlock,
	getBlockType,
	registerBlockType,
	serialize,
	setCategories,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import headingMetadata from '../block.json';
import headingSave from '../save';
import segmentMetadata from '../../heading-segment/block.json';
import segmentSave from '../../heading-segment/save';

const editorSource = readFileSync(resolve(__dirname, '../editor.scss'), 'utf8');
const editSource = readFileSync(resolve(__dirname, '../edit.js'), 'utf8');
const styleSource = readFileSync(resolve(__dirname, '../style.scss'), 'utf8');
const viewSource = readFileSync(resolve(__dirname, '../view.js'), 'utf8');

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

if (!getBlockType(segmentMetadata.name)) {
	registerBlockType(segmentMetadata.name, {
		...segmentMetadata,
		save: segmentSave,
	});
}

if (!getBlockType(headingMetadata.name)) {
	registerBlockType(headingMetadata.name, {
		...headingMetadata,
		save: headingSave,
	});
}

function createHeading(attributes = {}, segmentAttributes = {}) {
	return createBlock(headingMetadata.name, attributes, [
		createBlock(segmentMetadata.name, {
			content: 'Creative',
			...segmentAttributes,
		}),
	]);
}

describe('animated headline save', () => {
	test('keeps a default heading byte-identical to its existing saved markup', () => {
		const html = serialize(createHeading());

		expect(html).toBe(
			'<!-- wp:designsetgo/advanced-heading -->\n<div class="wp-block-designsetgo-advanced-heading dsgo-advanced-heading"><h2 class="dsgo-advanced-heading__inner"><!-- wp:designsetgo/heading-segment -->\n<span class="wp-block-designsetgo-heading-segment dsgo-heading-segment"><span class="dsgo-heading-segment__text">Creative</span></span>\n<!-- /wp:designsetgo/heading-segment --></h2></div>\n<!-- /wp:designsetgo/advanced-heading -->'
		);
	});

	test('adds an editor-only fallback gap before a non-leading animated segment', () => {
		expect(editorSource).toMatch(
			/\.dsgo-heading-segment:not\(:first-child\)\s+\.dsgo-heading-segment__animated\s*\{\s*margin-inline-start:\s*var\(--dsgo-editor-segment-gap\);/
		);
		expect(editSource).toContain(
			"'--dsgo-editor-segment-gap': blockGap ? '0' : '.2em'"
		);
	});

	// The frontend gap between plain segments comes from the newlines the
	// serializer puts between the inner blocks, which collapse to a single
	// space. The editor renders the segments adjacent with no text node
	// between them, so without this the canvas shows them run together.
	test('adds the same editor-only fallback gap before a non-leading plain segment', () => {
		expect(editorSource).toMatch(
			/\.dsgo-heading-segment:not\(:first-child\)\s+\.dsgo-heading-segment__text\s*\{\s*margin-inline-start:\s*var\(--dsgo-editor-segment-gap\);/
		);
	});

	// steps(n, end) reaches its final value only at exactly t=1, and an
	// infinite animation restarts at that instant -- so the last 1/n of the
	// word was never painted. jump-none paints both ends.
	test('the typing effect never hides the end of the word', () => {
		expect(styleSource).not.toMatch(/steps\(\s*12\s*,\s*end\s*\)/);
		expect(styleSource).toMatch(
			/animation-timing-function:\s*steps\(.*jump-none\s*\);/
		);
	});

	// A fixed step count reveals a fraction of the width rather than a
	// character, so short words typed in sub-character slivers.
	test('the typing step count comes from the word length', () => {
		expect(styleSource).toContain('--dsgo-typing-steps');
		expect(viewSource).toContain('--dsgo-typing-steps');
	});

	// clip-path: inset() clips to the border box, which is the line box --
	// shorter than the glyphs' ink, so descenders and accents were shaved.
	test('the clip-path effects bleed past the line box vertically', () => {
		const clipEffects = [
			'dsgo-animated-headline-typing',
			'dsgo-animated-headline-clip',
			'dsgo-animated-headline-blinds',
		];

		clipEffects.forEach((name) => {
			const frames = styleSource.slice(
				styleSource.indexOf(`@keyframes ${name}`)
			);
			const body = frames.slice(0, frames.indexOf('}\n\n'));

			expect(body).toContain('--dsgo-headline-bleed');
		});
	});

	test('saves a bounded rotating headline without encoding link data attributes', () => {
		const html = serialize(
			createHeading(
				{
					animatedHeadline: {
						mode: 'rotating',
						effect: 'untrusted',
						duration: 999999,
						delay: -4,
						loop: true,
						url: 'https://example.com/work',
						target: '_blank',
						rel: 'nofollow',
					},
				},
				{
					headlineRole: 'animated',
					animatedWords: ['Creative', 'Effective'],
				}
			)
		);

		expect(html).toContain('data-dsgo-animated-headline="true"');
		expect(html).toContain('data-dsgo-animated-headline-mode="rotating"');
		expect(html).toContain('data-dsgo-animated-headline-effect="typing"');
		expect(html).toContain('data-dsgo-animated-headline-duration="10000"');
		expect(html).toContain('data-dsgo-animated-headline-delay="0"');
		expect(html).toContain('data-dsgo-animated-headline-loop="true"');
		expect(html).not.toContain('data-dsgo-animated-headline-url');
		expect(html).not.toContain('data-dsgo-animated-headline-target');
		expect(html).not.toContain('data-dsgo-animated-headline-rel');
		expect(html.match(/dsgo-heading-segment__animated/g)).toHaveLength(1);
	});

	test('saves a reverse rotation direction only when an author selects it', () => {
		const reverseHtml = serialize(
			createHeading(
				{
					animatedHeadline: {
						mode: 'rotating',
						effect: 'slide',
						direction: 'reverse',
					},
				},
				{
					headlineRole: 'animated',
					animatedWords: ['First', 'Last'],
				}
			)
		);
		const defaultHtml = serialize(
			createHeading(
				{ animatedHeadline: { mode: 'rotating' } },
				{
					headlineRole: 'animated',
					animatedWords: ['First', 'Last'],
				}
			)
		);

		expect(reverseHtml).toContain(
			'data-dsgo-animated-headline-direction="reverse"'
		);
		expect(defaultHtml).not.toContain(
			'data-dsgo-animated-headline-direction'
		);
	});

	test('wraps a valid headline URL in a real sanitized anchor', () => {
		const html = serialize(
			createHeading(
				{
					animatedHeadline: {
						mode: 'rotating',
						url: 'https://example.com/work',
						target: '_blank',
						rel: 'nofollow',
					},
				},
				{
					headlineRole: 'animated',
					animatedWords: ['Creative', 'Effective'],
				}
			)
		);

		expect(html).toContain(
			'<a class="dsgo-advanced-heading__link" href="https://example.com/work" target="_blank" rel="nofollow noopener noreferrer"><h2'
		);
		expect(html).not.toContain('data-dsgo-animated-headline-url');
	});

	test('does not turn an unsafe headline URL into an anchor', () => {
		const html = serialize(
			createHeading(
				{
					animatedHeadline: {
						mode: 'rotating',
						url: 'javascript:alert(1)',
					},
				},
				{
					headlineRole: 'animated',
					animatedWords: ['Creative', 'Effective'],
				}
			)
		);

		expect(html).not.toContain('dsgo-advanced-heading__link');
		expect(html).not.toContain('href="javascript:alert(1)"');
	});

	test('nests a decorative highlight SVG with the selected animated segment', () => {
		const html = serialize(
			createBlock(
				headingMetadata.name,
				{
					animatedHeadline: {
						mode: 'highlighted',
						shape: 'circle',
					},
				},
				[
					createBlock(segmentMetadata.name, { content: 'Before ' }),
					createBlock(segmentMetadata.name, {
						headlineRole: 'animated',
						animatedWords: ['Creative'],
						animatedHeadlineShape: 'circle',
					}),
					createBlock(segmentMetadata.name, { content: ' After' }),
				]
			)
		);

		expect(html).toContain('data-dsgo-animated-headline="true"');
		expect(html).toContain(
			'data-dsgo-animated-headline-mode="highlighted"'
		);
		expect(html).toContain('data-dsgo-animated-headline-shape="circle"');
		expect(html).toMatch(
			/<span class="wp-block-designsetgo-heading-segment dsgo-heading-segment dsgo-heading-segment--highlighted"><span class="dsgo-heading-segment__animated"[^>]*>Creative<\/span><svg class="dsgo-heading-segment__highlight" aria-hidden="true"/
		);
		expect(html).not.toContain('dsgo-advanced-heading__highlight');
	});
});
