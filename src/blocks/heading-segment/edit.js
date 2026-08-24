/**
 * Heading Segment Block - Edit Component
 *
 * An inline text span within an Advanced Heading. Typography (font family,
 * weight, style, transform, decoration, letter-spacing, size, line-height) is
 * provided entirely by WordPress Block Supports in the Inspector's Typography
 * panel — see the `typography` supports in block.json. No custom toolbar
 * controls are needed; they duplicated the native panel and wrote to the same
 * `fontFamily` attribute / `style.typography.*` storage.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';
import classnames from 'classnames';
import {
	InspectorControls,
	RichText,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { SelectControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { DsgoInspectorPanel } from '../../components/shared';
import AnimatedHeadlinePanel, {
	DEFAULT_ANIMATED_HEADLINE,
	normalizeAnimatedHeadline,
} from '../advanced-heading/components/AnimatedHeadlinePanel';
import AnimatedWordsControl, {
	normalizeAnimatedWords,
} from './components/AnimatedWordsControl';
import HighlightShape, { HIGHLIGHT_PATHS } from './components/HighlightShape';
import {
	getHeadingSegmentAnimationForRole,
	getHeadingSegmentAnimationForWords,
	normalizeHeadingSegmentAnimation,
} from './utils';

/**
 * Heading Segment Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {string}   props.clientId      - Block client ID.
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Heading segment edit component
 */
export default function HeadingSegmentEdit({
	attributes,
	clientId,
	setAttributes,
}) {
	const {
		animatedHeadlineShape,
		animatedWords = [],
		content,
		headlineRole = 'normal',
		normalContent = '',
		preservedAnimatedWords = [],
	} = attributes;
	const animation = normalizeHeadingSegmentAnimation({
		headlineRole,
		animatedWords,
	});
	const words = animation.animatedWords;
	const isAnimated = animation.headlineRole === 'animated';
	const highlightShape =
		isAnimated && HIGHLIGHT_PATHS[animatedHeadlineShape]
			? animatedHeadlineShape
			: '';
	const { updateBlockAttributes } = useDispatch(blockEditorStore);
	const { animatedSegmentCount, parentAnimatedHeadline, parentClientId } =
		useSelect(
			(select) => {
				const editor = select(blockEditorStore);
				const rootClientId = editor.getBlockRootClientId(clientId);
				const parent = editor.getBlock(rootClientId);
				const isAdvancedHeading =
					parent?.name === 'designsetgo/advanced-heading';

				return {
					parentClientId: isAdvancedHeading ? rootClientId : null,
					parentAnimatedHeadline: isAdvancedHeading
						? parent.attributes.animatedHeadline
						: null,
					animatedSegmentCount: isAdvancedHeading
						? parent.innerBlocks.filter(
								(block) =>
									block.name ===
										'designsetgo/heading-segment' &&
									block.attributes.headlineRole === 'animated'
							).length
						: 0,
				};
			},
			[clientId]
		);
	const normalizedParentHeadline = normalizeAnimatedHeadline(
		parentAnimatedHeadline
	);

	useEffect(() => {
		const wordsMatch =
			Array.isArray(animatedWords) &&
			animatedWords.length === words.length &&
			animatedWords.every((word, index) => word === words[index]);

		if (headlineRole !== animation.headlineRole || !wordsMatch) {
			setAttributes(animation);
		}
	}, [animatedWords, animation, headlineRole, setAttributes, words]);

	const blockProps = useBlockProps({
		className: classnames('dsgo-heading-segment', {
			'dsgo-heading-segment--highlighted': Boolean(highlightShape),
		}),
	});

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes(
							getHeadingSegmentAnimationForRole(
								{
									content,
									normalContent,
									animatedWords: words,
									preservedAnimatedWords,
								},
								'normal'
							)
						)
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Segment role', 'designsetgo')}
						hasValue={() => headlineRole !== 'normal'}
						onDeselect={() =>
							setAttributes(
								getHeadingSegmentAnimationForRole(
									{
										content,
										normalContent,
										animatedWords: words,
										preservedAnimatedWords,
									},
									'normal'
								)
							)
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Segment role', 'designsetgo')}
							value={headlineRole}
							options={[
								{
									label: __('Normal text', 'designsetgo'),
									value: 'normal',
								},
								{
									label: __('Animated words', 'designsetgo'),
									value: 'animated',
								},
							]}
							onChange={(value) =>
								setAttributes(
									getHeadingSegmentAnimationForRole(
										{
											content,
											normalContent,
											animatedWords: words,
											preservedAnimatedWords,
										},
										value
									)
								)
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{isAnimated && (
						<>
							<DsgoInspectorPanel.Item
								label={__('Animated words', 'designsetgo')}
								hasValue={() => words.length > 0}
								onDeselect={() =>
									setAttributes(
										getHeadingSegmentAnimationForWords(
											{
												content,
												headlineRole,
												normalContent,
												animatedWords: words,
												preservedAnimatedWords,
											},
											[]
										)
									)
								}
								isShownByDefault
							>
								<AnimatedWordsControl
									value={words}
									onChange={(nextWords) =>
										setAttributes(
											getHeadingSegmentAnimationForWords(
												{
													content,
													headlineRole,
													normalContent,
													animatedWords: words,
													preservedAnimatedWords,
												},
												normalizeAnimatedWords(
													nextWords
												)
											)
										)
									}
								/>
							</DsgoInspectorPanel.Item>
							{parentClientId && (
								<AnimatedHeadlinePanel
									value={
										normalizedParentHeadline ||
										DEFAULT_ANIMATED_HEADLINE
									}
									segmentCount={animatedSegmentCount}
									onChange={(next) =>
										updateBlockAttributes(parentClientId, {
											animatedHeadline:
												normalizeAnimatedHeadline(next),
										})
									}
								/>
							)}
						</>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<span {...blockProps}>
				{isAnimated ? (
					<>
						<span className="dsgo-heading-segment__animated">
							{words[0] ||
								__('Add animated words…', 'designsetgo')}
						</span>
						{highlightShape && (
							<HighlightShape shape={highlightShape} />
						)}
					</>
				) : (
					<RichText
						tagName="span"
						className="dsgo-heading-segment__text"
						value={content}
						onChange={(newContent) =>
							setAttributes({ content: newContent, ...animation })
						}
						placeholder={__('Heading text…', 'designsetgo')}
						allowedFormats={[
							'core/bold',
							'core/italic',
							'core/strikethrough',
							'core/superscript',
							'core/subscript',
						]}
					/>
				)}
			</span>
		</>
	);
}
