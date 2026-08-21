import {
	createBlock,
	getBlockType,
	registerBlockType,
	serialize,
	setCategories,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { fireEvent, render, screen } from '@testing-library/react';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import metadata from '../block.json';
import save from '../save';
import AnimatedWordsControl from '../components/AnimatedWordsControl';
import {
	getHeadingSegmentAnimationForRole,
	getHeadingSegmentAnimationForWords,
} from '../utils';

const editorSource = readFileSync(resolve(__dirname, '../edit.js'), 'utf8');

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

if (!getBlockType(metadata.name)) {
	registerBlockType(metadata.name, { ...metadata, save });
}

describe('animated heading segment save', () => {
	test('exposes parent-owned animation controls while animated words are selected', () => {
		expect(editorSource).toContain('AnimatedHeadlinePanel');
		expect(editorSource).toContain('parentClientId');
		expect(editorSource).toContain('updateBlockAttributes');
	});

	test('keeps a normal segment byte-identical to the existing saved markup', () => {
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Heading text',
			})
		);

		expect(html).toBe(
			'<!-- wp:designsetgo/heading-segment -->\n<span class="wp-block-designsetgo-heading-segment dsgo-heading-segment"><span class="dsgo-heading-segment__text">Heading text</span></span>\n<!-- /wp:designsetgo/heading-segment -->'
		);
	});

	test('does not save animated markup when its role is normal', () => {
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Heading text',
				headlineRole: 'normal',
				animatedWords: ['Ignored'],
			})
		);

		expect(html).not.toContain('dsgo-heading-segment__animated');
		expect(html).not.toContain('data-dsgo-animated-words');
		expect(html).toContain('Heading text');
	});

	test('saves ordered non-empty animated words with the first valid word as the static fallback', () => {
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Ignored segment content',
				headlineRole: 'animated',
				animatedWords: ['', ' First word ', null, 'Second word'],
			})
		);

		expect(html).toContain('dsgo-heading-segment__animated');
		expect(html).toContain('data-dsgo-animated-words');
		expect(html).toContain('First word');
		expect(html).toContain('Second word');
		expect(html).not.toContain('Ignored segment content');
	});

	test('declares normal as the default role and an empty word list', () => {
		expect(metadata.attributes.headlineRole).toMatchObject({
			type: 'string',
			default: 'normal',
			enum: ['normal', 'animated'],
		});
		expect(metadata.attributes.animatedWords).toEqual({
			type: 'array',
			default: [],
		});
	});

	test('rejects an animated role without words or fallback content', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: '',
				headlineRole: 'normal',
				animatedWords: [],
			},
			'animated'
		);
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Stable fallback',
				...animation,
			})
		);

		expect(animation).toEqual({
			headlineRole: 'normal',
			animatedWords: [],
		});
		expect(html).not.toContain('"headlineRole":"animated"');
		expect(html).not.toContain('dsgo-heading-segment__animated');
		expect(html).toContain('Stable fallback');
	});

	test('uses normal segment content to establish a valid animated role', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: '<strong>First word</strong>',
				headlineRole: 'normal',
				animatedWords: [],
			},
			'animated'
		);

		expect(animation).toEqual({
			headlineRole: 'animated',
			animatedWords: ['First word'],
		});
	});

	test('decodes RichText entities and preserves line breaks when seeding animation words', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: 'R&amp;D<br><strong>Research</strong>',
				headlineRole: 'normal',
				animatedWords: [],
			},
			'animated'
		);

		expect(animation).toEqual({
			headlineRole: 'animated',
			animatedWords: ['R&D\nResearch'],
		});
	});

	test('clears stale words atomically when an author selects the normal role', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: 'Normal segment',
				headlineRole: 'animated',
				animatedWords: ['Stale word'],
			},
			'normal'
		);
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Normal segment',
				...animation,
			})
		);

		expect(animation).toEqual({
			headlineRole: 'normal',
			animatedWords: [],
		});
		expect(html).not.toContain('"animatedWords"');
	});

	test('switches to normal and clears words when an author removes the final word', () => {
		const animation = getHeadingSegmentAnimationForWords(
			{ headlineRole: 'animated', animatedWords: ['Only word'] },
			[]
		);
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Static segment',
				...animation,
			})
		);

		expect(animation).toEqual({
			headlineRole: 'normal',
			animatedWords: [],
		});
		expect(html).not.toContain('"headlineRole":"animated"');
		expect(html).not.toContain('"animatedWords"');
		expect(html).toContain('Static segment');
	});

	test('allows select-all replacement without removing the existing word mid-edit', () => {
		const onChange = jest.fn();

		render(<AnimatedWordsControl value={['Before']} onChange={onChange} />);

		const input = screen.getByLabelText('Animated word');
		fireEvent.change(input, { target: { value: '' } });

		expect(input).toHaveValue('');
		expect(screen.getByLabelText('Animated word')).toBeInTheDocument();
		expect(onChange).not.toHaveBeenCalled();

		fireEvent.change(input, { target: { value: 'After' } });
		fireEvent.keyDown(input, { key: 'Enter' });

		expect(onChange).toHaveBeenLastCalledWith(['After']);
	});
});
