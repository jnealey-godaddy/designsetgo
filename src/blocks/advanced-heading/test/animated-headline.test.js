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
			/\.dsgo-heading-segment:not\(:first-child\)\s+\.dsgo-heading-segment__animated\s*\{\s*margin-inline-start:\s*var\(--dsgo-animated-segment-gap\);/
		);
		expect(editSource).toContain(
			"'--dsgo-animated-segment-gap': blockGap ? '0' : '.2em'"
		);
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
