/**
 * Advanced Heading Block - Edit Component
 *
 * Renders a heading element containing inner blocks (heading segments)
 * that each support independent typography controls.
 *
 * @since 2.0.0
 */

import classnames from 'classnames';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
} from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarDropdownMenu } from '@wordpress/components';
import { heading as headingIcon } from '@wordpress/icons';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import { DsgoInspectorPanel } from '../../components/shared/DsgoInspectorPanel';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';
import AnimatedHeadlinePanel, {
	DEFAULT_ANIMATED_HEADLINE,
	normalizeAnimatedHeadline,
} from './components/AnimatedHeadlinePanel';
import { getHeadingSegmentAnimationForRole } from '../heading-segment/utils';

const ALLOWED_BLOCKS = ['designsetgo/heading-segment'];
const TEMPLATE = [
	[
		'designsetgo/heading-segment',
		{
			content: __('Bold', 'designsetgo'),
			style: { typography: { fontWeight: '700' } },
		},
	],
	['designsetgo/heading-segment', { content: __('Heading', 'designsetgo') }],
];

const HEADING_LEVELS = [1, 2, 3, 4, 5, 6];

/**
 * Advanced Heading Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {string}   props.clientId      - Block client ID.
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Advanced Heading block edit component
 */
export default function AdvancedHeadingEdit({
	attributes,
	clientId,
	setAttributes,
}) {
	const { animatedHeadline, level = 2, textAlign } = attributes;
	const validLevel = HEADING_LEVELS.includes(level) ? level : 2;
	const TagName = `h${validLevel}`;
	const { updateBlockAttributes } = useDispatch('core/block-editor');
	const registry = useRegistry();
	// Select strings, not blocks. `getBlock()` rebuilds the whole subtree on
	// every store change and hands back a new `innerBlocks` array each time,
	// which re-renders this heading on every unrelated keystroke.
	const { segmentSignature, selectedBlockClientId } = useSelect(
		(select) => {
			const {
				getBlockAttributes,
				getBlockName,
				getBlockOrder,
				getSelectedBlockClientId,
			} = select('core/block-editor');

			return {
				segmentSignature: getBlockOrder(clientId)
					.filter(
						(childClientId) =>
							getBlockName(childClientId) ===
							'designsetgo/heading-segment'
					)
					.map((childClientId) => {
						const childAttributes =
							getBlockAttributes(childClientId) || {};

						return [
							childClientId,
							childAttributes.headlineRole === 'animated'
								? 'animated'
								: 'normal',
							childAttributes.animatedHeadlineShape || '',
						].join('|');
					})
					.join(','),
				selectedBlockClientId: getSelectedBlockClientId(),
			};
		},
		[clientId]
	);
	const segments = useMemo(
		() =>
			segmentSignature
				? segmentSignature.split(',').map((entry) => {
						const [
							segmentClientId,
							headlineRole,
							animatedHeadlineShape,
						] = entry.split('|');

						return {
							clientId: segmentClientId,
							headlineRole,
							animatedHeadlineShape,
						};
					})
				: [],
		[segmentSignature]
	);
	const animatedSegments = useMemo(
		() => segments.filter((segment) => segment.headlineRole === 'animated'),
		[segments]
	);
	const normalizedHeadline = normalizeAnimatedHeadline(animatedHeadline);

	useEffect(() => {
		const selectedAnimatedSegment = animatedSegments.find(
			(block) => block.clientId === selectedBlockClientId
		);

		if (!selectedAnimatedSegment || animatedSegments.length < 2) {
			return;
		}

		animatedSegments.forEach((segment) => {
			if (segment.clientId !== selectedAnimatedSegment.clientId) {
				updateBlockAttributes(
					segment.clientId,
					getHeadingSegmentAnimationForRole(
						registry
							.select('core/block-editor')
							.getBlockAttributes(segment.clientId) || {},
						'normal'
					)
				);
			}
		});
	}, [
		animatedSegments,
		registry,
		selectedBlockClientId,
		updateBlockAttributes,
	]);

	useEffect(() => {
		if (animatedSegments.length === 1) {
			const nextHeadline =
				normalizedHeadline || DEFAULT_ANIMATED_HEADLINE;

			if (
				JSON.stringify(nextHeadline) !==
				JSON.stringify(animatedHeadline)
			) {
				setAttributes({ animatedHeadline: nextHeadline });
			}
		}

		if (animatedSegments.length !== 1 && animatedHeadline !== null) {
			setAttributes({ animatedHeadline: null });
		}
	}, [
		animatedHeadline,
		animatedSegments.length,
		normalizedHeadline,
		setAttributes,
	]);

	useEffect(() => {
		const animatedSegment =
			animatedSegments.length === 1 ? animatedSegments[0] : null;
		const highlightShape =
			animatedSegment && normalizedHeadline?.mode === 'highlighted'
				? normalizedHeadline.shape
				: '';

		segments.forEach((segment) => {
			const nextShape =
				segment.clientId === animatedSegment?.clientId
					? highlightShape
					: '';

			if (segment.animatedHeadlineShape !== nextShape) {
				updateBlockAttributes(segment.clientId, {
					animatedHeadlineShape: nextShape,
				});
			}
		});
	}, [animatedSegments, segments, normalizedHeadline, updateBlockAttributes]);

	const blockGap = convertPresetToCSSVar(attributes.style?.spacing?.blockGap);

	const blockProps = useBlockProps({
		className: classnames('dsgo-advanced-heading', {
			[`has-text-align-${textAlign}`]: textAlign,
		}),
	});

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-advanced-heading__inner',
			style: {
				...(blockGap ? { '--dsgo-segment-gap': blockGap } : {}),
				'--dsgo-animated-segment-gap': blockGap ? '0' : '.2em',
			},
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
		}
	);

	return (
		<>
			{/* ========================================
			     BLOCK TOOLBAR
			    ======================================== */}
			<BlockControls group="block">
				<ToolbarGroup>
					<ToolbarDropdownMenu
						icon={headingIcon}
						label={__('Change heading level', 'designsetgo')}
						controls={HEADING_LEVELS.map((targetLevel) => ({
							icon: headingIcon,
							title: `H${targetLevel}`,
							isActive: level === targetLevel,
							onClick: () =>
								setAttributes({ level: targetLevel }),
						}))}
					/>
				</ToolbarGroup>
				<AlignmentToolbar
					value={textAlign}
					onChange={(value) => setAttributes({ textAlign: value })}
				/>
			</BlockControls>

			{/* ========================================
			     INSPECTOR CONTROLS
			    ======================================== */}
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							level: 2,
							animatedHeadline: null,
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Heading level', 'designsetgo')}
						hasValue={() => level !== 2}
						onDeselect={() => setAttributes({ level: 2 })}
						isShownByDefault
					>
						<p className="dsgo-advanced-heading__level-label">
							{__('Heading level', 'designsetgo')}
						</p>
						<div className="dsgo-advanced-heading__level-buttons">
							{HEADING_LEVELS.map((targetLevel) => (
								<button
									key={targetLevel}
									className={`dsgo-advanced-heading__level-button${level === targetLevel ? ' is-active' : ''}`}
									onClick={() =>
										setAttributes({ level: targetLevel })
									}
									aria-pressed={level === targetLevel}
								>
									H{targetLevel}
								</button>
							))}
						</div>
					</DsgoInspectorPanel.Item>

					<AnimatedHeadlinePanel
						value={normalizedHeadline || DEFAULT_ANIMATED_HEADLINE}
						segmentCount={animatedSegments.length}
						onChange={(next) =>
							setAttributes({
								animatedHeadline:
									normalizeAnimatedHeadline(next),
							})
						}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			{/* ========================================
			     BLOCK CONTENT
			    ======================================== */}
			<div {...blockProps}>
				<TagName {...innerBlocksProps} />
			</div>
		</>
	);
}
