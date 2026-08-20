/**
 * Advanced Heading Block - Save Function
 *
 * Renders the heading element with inner block content.
 * Each heading segment saves its own typography styles.
 *
 * @since 2.0.0
 */

import classnames from 'classnames';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';
import {
	normalizeAnimatedHeadline,
	normalizeAnimatedHeadlineLink,
} from './components/AnimatedHeadlinePanel';

const HEADING_LEVELS = [1, 2, 3, 4, 5, 6];

/**
 * Advanced Heading Save Function
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Saved advanced heading block markup
 */
export default function AdvancedHeadingSave({ attributes }) {
	const { animatedHeadline, level = 2, textAlign } = attributes;
	const validLevel = HEADING_LEVELS.includes(level) ? level : 2;
	const TagName = `h${validLevel}`;
	const headline = normalizeAnimatedHeadline(animatedHeadline);
	const link = normalizeAnimatedHeadlineLink(animatedHeadline);

	const blockGap = convertPresetToCSSVar(attributes.style?.spacing?.blockGap);

	const blockProps = useBlockProps.save({
		className: classnames('dsgo-advanced-heading', {
			[`has-text-align-${textAlign}`]: textAlign,
		}),
	});

	const innerStyle = {
		...(blockGap ? { '--dsgo-segment-gap': blockGap } : {}),
		...(headline
			? {
					'--dsgo-animated-headline-duration': `${headline.duration}ms`,
					'--dsgo-animated-headline-delay': `${headline.delay}ms`,
				}
			: {}),
	};
	const innerBlocksProps = useInnerBlocksProps.save({
		className: classnames('dsgo-advanced-heading__inner', {
			'dsgo-advanced-heading__inner--rotating':
				headline?.mode === 'rotating',
			'dsgo-advanced-heading__inner--highlighted':
				headline?.mode === 'highlighted',
		}),
		style: Object.keys(innerStyle).length ? innerStyle : undefined,
		...(headline
			? {
					'data-dsgo-animated-headline': 'true',
					'data-dsgo-animated-headline-mode': headline.mode,
					'data-dsgo-animated-headline-effect': headline.effect,
					'data-dsgo-animated-headline-shape': headline.shape,
					'data-dsgo-animated-headline-duration': headline.duration,
					'data-dsgo-animated-headline-delay': headline.delay,
					'data-dsgo-animated-headline-loop': headline.loop
						? 'true'
						: 'false',
				}
			: {}),
	});

	const heading = <TagName {...innerBlocksProps} />;

	return (
		<div {...blockProps}>
			{link ? (
				<a
					className="dsgo-advanced-heading__link"
					href={link.url}
					{...(link.target ? { target: link.target } : {})}
					{...(link.rel ? { rel: link.rel } : {})}
				>
					{heading}
				</a>
			) : (
				heading
			)}
		</div>
	);
}
