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
 * A segment demoted to normal parks its word list in `preservedAnimatedWords`
 * so promoting it again restores the full list instead of the single word the
 * demotion left as content.
 *
 * @param {Object} attributes                        Heading segment attributes.
 * @param {string} attributes.content                Segment rich-text HTML.
 * @param {string} attributes.normalContent          Serialized normal RichText HTML.
 * @param {Array}  attributes.animatedWords          Current animated words.
 * @param {Array}  attributes.preservedAnimatedWords Word list parked by an earlier demotion.
 * @param {string} nextRole                          Requested segment role.
 * @return {Object} Valid role and word list for the attribute update.
 */
export function getHeadingSegmentAnimationForRole(
	{
		content = '',
		normalContent = '',
		animatedWords = [],
		preservedAnimatedWords = [],
	} = {},
	nextRole
) {
	const currentContent = typeof content === 'string' ? content : '';
	const preservedContent =
		typeof normalContent === 'string' ? normalContent : '';

	if (nextRole !== 'animated') {
		const words = normalizeAnimatedWords(animatedWords);
		const normalAnimation = {
			...normalizeHeadingSegmentAnimation({
				headlineRole: 'normal',
				animatedWords: [],
			}),
			// Demotion clears `animatedWords` so a normal segment never carries
			// an animated payload. Park the list instead of discarding it, so
			// re-promoting the segment restores every word the author wrote
			// rather than collapsing the list to its first entry.
			...(words.length > 0 ? { preservedAnimatedWords: words } : {}),
		};

		// `content` is sourced from the normal RichText span, which an animated
		// save intentionally omits. When an animated segment is parsed again,
		// retain a readable normal fallback from its first saved word.
		if (currentContent.trim()) {
			return {
				...normalAnimation,
				...(preservedContent ? { normalContent: '' } : {}),
			};
		}

		const recoveredContent = preservedContent.trim() || words[0] || '';

		if (recoveredContent) {
			return {
				...normalAnimation,
				content: recoveredContent,
				...(preservedContent ? { normalContent: '' } : {}),
			};
		}

		return normalAnimation;
	}

	const fallbackWords = normalizeAnimatedWords([
		getTextContent(create({ html: content })),
	]);
	const words = normalizeAnimatedWords(animatedWords);
	const parkedWords = normalizeAnimatedWords(preservedAnimatedWords);
	// A segment demoted earlier parked its full list; prefer it over the single
	// word the demotion left behind as content.
	const restoredWords = parkedWords.length > 0 ? parkedWords : fallbackWords;

	const animation = {
		...normalizeHeadingSegmentAnimation({
			headlineRole: 'animated',
			animatedWords: words.length > 0 ? words : restoredWords,
		}),
		// Consume the parked list only when there was one; an ordinary
		// promotion should not write an empty attribute.
		...(parkedWords.length > 0 ? { preservedAnimatedWords: [] } : {}),
	};
	const contentToPreserve = preservedContent.trim() || currentContent;

	return contentToPreserve
		? { ...animation, normalContent: contentToPreserve }
		: animation;
}

/**
 * Apply an author-edited word list without leaving an orphaned role behind.
 *
 * @param {Object} attributes               Heading segment attributes.
 * @param {string} attributes.content       Segment rich-text HTML.
 * @param {string} attributes.headlineRole  Current segment role.
 * @param {string} attributes.normalContent Serialized normal RichText HTML.
 * @param {Array}  attributes.animatedWords Current animated words.
 * @param {Array}  nextWords                Edited word list.
 * @return {Object} Valid role and word list for the attribute update.
 */
export function getHeadingSegmentAnimationForWords(
	{
		content = '',
		headlineRole = 'normal',
		normalContent = '',
		animatedWords = [],
	} = {},
	nextWords
) {
	const words = nextWords ?? animatedWords;

	if (
		headlineRole !== 'animated' ||
		normalizeAnimatedWords(words).length === 0
	) {
		return getHeadingSegmentAnimationForRole(
			{ content, normalContent, animatedWords: words },
			'normal'
		);
	}

	const animation = normalizeHeadingSegmentAnimation({
		headlineRole,
		animatedWords: words,
	});
	const preservedContent =
		typeof normalContent === 'string' ? normalContent : '';

	return preservedContent.trim()
		? { ...animation, normalContent: preservedContent }
		: animation;
}
