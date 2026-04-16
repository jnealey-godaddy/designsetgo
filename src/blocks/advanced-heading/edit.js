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
import {
	PanelBody,
	ToolbarGroup,
	ToolbarDropdownMenu,
} from '@wordpress/components';
import { heading as headingIcon } from '@wordpress/icons';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';

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
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Advanced Heading block edit component
 */
export default function AdvancedHeadingEdit({ attributes, setAttributes }) {
	const { level = 2, textAlign } = attributes;
	const validLevel = HEADING_LEVELS.includes(level) ? level : 2;
	const TagName = `h${validLevel}`;

	const blockGap = convertPresetToCSSVar(attributes.style?.spacing?.blockGap);

	const blockProps = useBlockProps({
		className: classnames('dsgo-advanced-heading', {
			[`has-text-align-${textAlign}`]: textAlign,
		}),
	});

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-advanced-heading__inner',
			style: blockGap ? { '--dsgo-segment-gap': blockGap } : undefined,
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
				<PanelBody
					title={__('Heading Settings', 'designsetgo')}
					initialOpen={true}
				>
					<p className="dsgo-advanced-heading__level-label">
						{__('Heading Level', 'designsetgo')}
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
				</PanelBody>
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
