import { create, getTextContent } from '@wordpress/rich-text';

/**
 * Normalize a heading segment's animated-word data at the editor boundary.
 *
 * An animated role is only meaningful when it has a non-empty ordered word
 * list. Treat every other state as a normal segment so parents and frontend
 * code never receive an animated role without its corresponding payload.
 *
 * @param {Array} words Candidate animated words.
 * @return {Array} Ordered, trimmed, non-empty words.
 */
export function normalizeAnimatedWords(words) {
	if (!Array.isArray(words)) {
		return [];
	}

	return words.reduce((validWords, word) => {
		const normalizedWord = typeof word === 'string' ? word.trim() : '';

		if (normalizedWord) {
			validWords.push(normalizedWord);
		}

		return validWords;
	}, []);
}

/**
 * Return the only persisted animation states heading consumers may rely on.
 *
 * @param {Object} attributes               Heading segment attributes.
 * @param {string} attributes.headlineRole  Requested segment role.
 * @param {Array}  attributes.animatedWords Candidate animated words.
 * @return {Object} Normalized role and word list.
 */
export function normalizeHeadingSegmentAnimation({
	headlineRole = 'normal',
	animatedWords = [],
} = {}) {
	const words = normalizeAnimatedWords(animatedWords);

	if (headlineRole !== 'animated' || words.length === 0) {
		return {
			headlineRole: 'normal',
			animatedWords: [],
		};
	}

	return {
		headlineRole: 'animated',
		animatedWords: words,
	};
}

/**
 * Apply an author-selected role without ever creating an empty animation.
 *
 * When a normal segment becomes animated, its plain-text content seeds the
 * first word only if the author has not already supplied a valid word list.
 * An empty segment stays normal until the author provides text to animate.
 *
 * @param {Object} attributes               Heading segment attributes.
 * @param {string} attributes.content       Segment rich-text HTML.
 * @param {Array}  attributes.animatedWords Current animated words.
 * @param {string} nextRole                 Requested segment role.
 * @return {Object} Valid role and word list for the attribute update.
 */
export function getHeadingSegmentAnimationForRole(
	{ content = '', animatedWords = [] } = {},
	nextRole
) {
	if (nextRole !== 'animated') {
		return normalizeHeadingSegmentAnimation({
			headlineRole: 'normal',
			animatedWords: [],
		});
	}

	const words = normalizeAnimatedWords(animatedWords);
	const fallbackWords = normalizeAnimatedWords([
		getTextContent(create({ html: content })),
	]);

	return normalizeHeadingSegmentAnimation({
		headlineRole: 'animated',
		animatedWords: words.length > 0 ? words : fallbackWords,
	});
}

/**
 * Apply an author-edited word list without leaving an orphaned role behind.
 *
 * @param {Object} attributes               Heading segment attributes.
 * @param {string} attributes.headlineRole  Current segment role.
 * @param {Array}  attributes.animatedWords Current animated words.
 * @param {Array}  nextWords                Edited word list.
 * @return {Object} Valid role and word list for the attribute update.
 */
export function getHeadingSegmentAnimationForWords(
	{ headlineRole = 'normal', animatedWords = [] } = {},
	nextWords
) {
	return normalizeHeadingSegmentAnimation({
		headlineRole,
		animatedWords: nextWords ?? animatedWords,
	});
}
