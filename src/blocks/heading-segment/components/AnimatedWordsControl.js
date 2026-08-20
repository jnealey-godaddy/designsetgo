/**
 * Animated words list control.
 *
 * The persisted list is intentionally strict: it preserves order but never
 * includes blank entries, so the saved static fallback is always meaningful.
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	TextControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { normalizeAnimatedWords as normalizeWords } from '../utils';

export function normalizeAnimatedWords(words) {
	return normalizeWords(words);
}

/**
 * Edit a headline segment's rotating word list.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.value    Current words.
 * @param {Function} props.onChange Receives a normalized word list.
 * @return {JSX.Element} Word list editor.
 */
export default function AnimatedWordsControl({ value, onChange }) {
	const words = useMemo(() => normalizeAnimatedWords(value), [value]);
	const [newWord, setNewWord] = useState('');
	const [draftWords, setDraftWords] = useState(words);

	useEffect(() => {
		setDraftWords(words);
	}, [words]);

	const updateWords = (nextWords) =>
		onChange(normalizeAnimatedWords(nextWords));
	const updateDraft = (index, nextWord) =>
		setDraftWords(
			draftWords.map((word, wordIndex) =>
				wordIndex === index ? nextWord : word
			)
		);
	const commitWord = (index) => {
		const nextWords = words.map((word, wordIndex) =>
			wordIndex === index ? draftWords[index] : word
		);

		updateWords(nextWords);
	};
	const moveWord = (index, offset) => {
		const nextIndex = index + offset;

		if (nextIndex < 0 || nextIndex >= words.length) {
			return;
		}

		const nextWords = [...words];
		[nextWords[index], nextWords[nextIndex]] = [
			nextWords[nextIndex],
			nextWords[index],
		];
		updateWords(nextWords);
	};
	const addWord = () => {
		const normalizedWord = newWord.trim();

		if (!normalizedWord) {
			return;
		}

		updateWords([...words, normalizedWord]);
		setNewWord('');
	};

	return (
		<Flex direction="column" gap={3}>
			{words.map((word, index) => (
				<Flex
					// Word text is mutable and has no separate persistent ID.
					// eslint-disable-next-line react/no-array-index-key
					key={index}
					align="flex-end"
					gap={2}
				>
					<FlexBlock>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Animated word', 'designsetgo')}
							value={draftWords[index] ?? word}
							onChange={(nextWord) =>
								updateDraft(index, nextWord)
							}
							onBlur={() => commitWord(index)}
							onKeyDown={(event) => {
								if (event.key === 'Enter') {
									event.preventDefault();
									commitWord(index);
								}
							}}
						/>
					</FlexBlock>
					<FlexItem>
						<Button
							variant="tertiary"
							size="small"
							disabled={index === 0}
							label={__('Move word up', 'designsetgo')}
							onClick={() => moveWord(index, -1)}
						>
							{__('Up', 'designsetgo')}
						</Button>
						<Button
							variant="tertiary"
							size="small"
							disabled={index === words.length - 1}
							label={__('Move word down', 'designsetgo')}
							onClick={() => moveWord(index, 1)}
						>
							{__('Down', 'designsetgo')}
						</Button>
						<Button
							isDestructive
							variant="tertiary"
							size="small"
							label={__('Remove word', 'designsetgo')}
							onClick={() =>
								updateWords(
									words.filter(
										(_, wordIndex) => wordIndex !== index
									)
								)
							}
						>
							{__('Remove', 'designsetgo')}
						</Button>
					</FlexItem>
				</Flex>
			))}

			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Add animated word', 'designsetgo')}
				value={newWord}
				onChange={setNewWord}
				onKeyDown={(event) => {
					if (event.key === 'Enter') {
						event.preventDefault();
						addWord();
					}
				}}
			/>
			<Button variant="secondary" onClick={addWord}>
				{__('Add word', 'designsetgo')}
			</Button>
		</Flex>
	);
}
