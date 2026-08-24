import {
	createBlock,
	getBlockType,
	parse,
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

		expect(animation).toMatchObject({
			headlineRole: 'animated',
			animatedWords: ['First word'],
			normalContent: '<strong>First word</strong>',
		});
	});

	test('persists normal content while a segment is animated', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: 'Original normal content',
				headlineRole: 'normal',
				animatedWords: [],
			},
			'animated'
		);
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Original normal content',
				...animation,
			})
		);

		expect(animation).toMatchObject({
			headlineRole: 'animated',
			animatedWords: ['Original normal content'],
			normalContent: 'Original normal content',
		});
		expect(html).toContain('"normalContent":"Original normal content"');
	});

	test('round trips preserved normal content through an animated save', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: 'Original normal content',
				headlineRole: 'normal',
				animatedWords: [],
			},
			'animated'
		);
		const [reloaded] = parse(
			serialize(
				createBlock(metadata.name, {
					content: 'Original normal content',
					...animation,
				})
			)
		);
		const demoted = getHeadingSegmentAnimationForRole(
			reloaded.attributes,
			'normal'
		);

		expect(reloaded.attributes.normalContent).toBe(
			'Original normal content'
		);
		expect(demoted.content).toBe('Original normal content');
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

		expect(animation).toMatchObject({
			headlineRole: 'animated',
			animatedWords: ['R&D\nResearch'],
			normalContent: 'R&amp;D<br><strong>Research</strong>',
		});
	});

	test('keeps a reloaded animated segment readable when it is demoted', () => {
		// The animated save() renders no `.dsgo-heading-segment__text`, so a
		// reloaded segment parses back with an empty html-sourced `content`.
		// Demotion must recover readable text from the word list or the
		// segment's save() returns null and it disappears from the heading.
		const animated = getHeadingSegmentAnimationForRole(
			{
				content: '',
				headlineRole: 'normal',
				animatedWords: ['Alpha', 'Beta'],
			},
			'animated'
		);
		const [reloaded] = parse(
			serialize(createBlock(metadata.name, animated))
		);

		expect(reloaded.isValid).toBe(true);
		expect(reloaded.attributes.content).toBe('');

		const demoted = getHeadingSegmentAnimationForRole(
			reloaded.attributes,
			'normal'
		);
		const html = serialize(
			createBlock(metadata.name, {
				...reloaded.attributes,
				...demoted,
			})
		);

		expect(demoted.content).toBe('Alpha');
		expect(html).toContain('dsgo-heading-segment__text');
		expect(html).toContain('Alpha');
	});

	test('restores the full word list when a demoted segment is animated again', () => {
		const demoted = getHeadingSegmentAnimationForRole(
			{
				content: '',
				headlineRole: 'animated',
				animatedWords: ['Design', 'Build', 'Ship'],
			},
			'normal'
		);

		// A normal segment must not carry an animated payload, so the list is
		// parked rather than dropped.
		expect(demoted.animatedWords).toEqual([]);
		expect(demoted.preservedAnimatedWords).toEqual([
			'Design',
			'Build',
			'Ship',
		]);

		const repromoted = getHeadingSegmentAnimationForRole(
			{
				content: demoted.content ?? '',
				headlineRole: 'normal',
				animatedWords: [],
				preservedAnimatedWords: demoted.preservedAnimatedWords,
			},
			'animated'
		);

		expect(repromoted.animatedWords).toEqual(['Design', 'Build', 'Ship']);
		expect(repromoted.preservedAnimatedWords).toEqual([]);
	});

	test('parks the list when an author clears the final animated word', () => {
		// Removing the last word demotes through the words helper, which must
		// forward the parked list rather than dropping it on the way to the
		// role helper.
		const demoted = getHeadingSegmentAnimationForWords(
			{
				content: '',
				headlineRole: 'animated',
				animatedWords: ['Design', 'Build'],
			},
			[]
		);

		expect(demoted.headlineRole).toBe('normal');
		expect(demoted.preservedAnimatedWords).toEqual(['Design', 'Build']);
	});

	test('leaves an already-parked list untouched when there is nothing to park', () => {
		const result = getHeadingSegmentAnimationForWords(
			{
				content: 'Plain',
				headlineRole: 'normal',
				animatedWords: [],
				preservedAnimatedWords: ['Design', 'Build'],
			},
			[]
		);

		// setAttributes merges, so omitting the key keeps the parked list
		// rather than overwriting it with an empty array.
		expect(result).not.toHaveProperty('preservedAnimatedWords');
	});

	test('forwards the parked list from every editor call site', () => {
		// The helpers read `preservedAnimatedWords` off the attributes object,
		// but edit.js hands them a hand-built object rather than `attributes`.
		// Omitting the key there silently disables restoration.
		const roleCalls =
			editorSource.split('animatedWords: words,').length - 1;
		const forwarded =
			editorSource.split('preservedAnimatedWords,').length - 1;

		expect(roleCalls).toBeGreaterThan(0);
		expect(forwarded).toBe(roleCalls);
	});

	test('parks nothing when a demoted segment had no words to lose', () => {
		const demoted = getHeadingSegmentAnimationForRole(
			{
				content: 'Plain text',
				headlineRole: 'normal',
				animatedWords: [],
			},
			'normal'
		);

		expect(demoted.preservedAnimatedWords).toBeUndefined();
	});

	test('prefers an explicit word list over a parked one', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: '',
				headlineRole: 'normal',
				animatedWords: ['Current'],
				preservedAnimatedWords: ['Old', 'Stale'],
			},
			'animated'
		);

		expect(animation.animatedWords).toEqual(['Current']);
		expect(animation.preservedAnimatedWords).toEqual([]);
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
			preservedAnimatedWords: ['Stale word'],
		});
		expect(html).not.toContain('"animatedWords"');
	});

	test('recovers readable normal content when a saved animated segment is demoted', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: '',
				headlineRole: 'animated',
				animatedWords: ['Recovered word', 'Next word'],
			},
			'normal'
		);
		const html = serialize(createBlock(metadata.name, animation));

		expect(animation).toEqual({
			content: 'Recovered word',
			headlineRole: 'normal',
			animatedWords: [],
			// Parked so re-animating the segment restores both words rather
			// than collapsing the list to the recovered first word.
			preservedAnimatedWords: ['Recovered word', 'Next word'],
		});
		expect(html).toContain('Recovered word');
		expect(html).not.toContain('dsgo-heading-segment__animated');
	});

	test('restores its original normal content after an animated save is reloaded', () => {
		const animation = getHeadingSegmentAnimationForRole(
			{
				content: '',
				normalContent: 'Original normal content',
				headlineRole: 'animated',
				animatedWords: ['Animated word', 'Next word'],
			},
			'normal'
		);

		expect(animation).toMatchObject({
			content: 'Original normal content',
			headlineRole: 'normal',
			animatedWords: [],
			normalContent: '',
		});
	});

	test('preserves normal content while an author edits animated words', () => {
		const animation = getHeadingSegmentAnimationForWords(
			{
				content: '',
				normalContent: 'Original normal content',
				headlineRole: 'animated',
				animatedWords: ['First word'],
			},
			['Updated word']
		);

		expect(animation).toEqual({
			normalContent: 'Original normal content',
			headlineRole: 'animated',
			animatedWords: ['Updated word'],
		});
	});

	test('switches to normal and clears words when an author removes the final word', () => {
		const animation = getHeadingSegmentAnimationForWords(
			{ headlineRole: 'animated', animatedWords: ['Only word'] },
			[]
		);
		const html = serialize(createBlock(metadata.name, animation));

		expect(animation).toEqual({
			headlineRole: 'normal',
			animatedWords: [],
			// The segment had no normal content of its own, so the word it
			// just lost becomes its readable text instead of leaving an empty
			// segment that save() drops entirely.
			content: 'Only word',
			preservedAnimatedWords: ['Only word'],
		});
		expect(html).not.toContain('"headlineRole":"animated"');
		expect(html).not.toContain('"animatedWords"');
		expect(html).toContain('Only word');
	});

	test('keeps existing normal content when an author removes the final word', () => {
		const animation = getHeadingSegmentAnimationForWords(
			{
				content: 'Static segment',
				headlineRole: 'animated',
				animatedWords: ['Only word'],
			},
			[]
		);
		const html = serialize(
			createBlock(metadata.name, {
				content: 'Static segment',
				...animation,
			})
		);

		expect(animation).not.toHaveProperty('content');
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

	test('keeps an uncommitted draft when a parent rerenders with equivalent words', () => {
		const onChange = jest.fn();
		const { rerender } = render(
			<AnimatedWordsControl value={['Before']} onChange={onChange} />
		);
		const input = screen.getByLabelText('Animated word');

		fireEvent.change(input, { target: { value: 'Draft' } });
		rerender(
			<AnimatedWordsControl value={['Before']} onChange={onChange} />
		);

		expect(screen.getByLabelText('Animated word')).toHaveValue('Draft');
		expect(onChange).not.toHaveBeenCalled();
	});
});
