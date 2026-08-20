/**
 * Heading Segment Block - Save Function
 *
 * Outputs an inline span with its own typography styles.
 * Block Supports automatically apply font-family, font-weight,
 * color, and other typography properties.
 *
 * @since 2.0.0
 */

import classnames from 'classnames';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import HighlightShape, { HIGHLIGHT_PATHS } from './components/HighlightShape';
import { normalizeHeadingSegmentAnimation } from './utils';

/**
 * Heading Segment Save Function
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element|null} Saved heading segment markup, or null if empty
 */
export default function HeadingSegmentSave({ attributes }) {
	const { animatedHeadlineShape, content } = attributes;
	const animation = normalizeHeadingSegmentAnimation(attributes);
	const { animatedWords: words, headlineRole } = animation;
	const isAnimated = headlineRole === 'animated';
	const highlightShape =
		isAnimated && HIGHLIGHT_PATHS[animatedHeadlineShape]
			? animatedHeadlineShape
			: '';

	if (!isAnimated && (!content || !content.trim())) {
		return null;
	}

	const blockProps = useBlockProps.save({
		className: classnames('dsgo-heading-segment', {
			'dsgo-heading-segment--highlighted': Boolean(highlightShape),
		}),
	});

	return (
		<span {...blockProps}>
			{isAnimated ? (
				<>
					<span
						className="dsgo-heading-segment__animated"
						data-dsgo-animated-words={JSON.stringify(words)}
					>
						{words[0]}
					</span>
					{highlightShape && (
						<HighlightShape shape={highlightShape} />
					)}
				</>
			) : (
				<RichText.Content
					tagName="span"
					className="dsgo-heading-segment__text"
					value={content}
				/>
			)}
		</span>
	);
}
