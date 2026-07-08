/**
 * Icon List Item Block - Edit Component
 *
 * Child block that displays a single list item with icon and flexible content area.
 * Users can add any blocks in the content area. Default template includes a paragraph.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { getIcon } from '../icon/utils/svg-icons';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { IconPickerPanel } from './components/inspector/IconPickerPanel';
import { LinkSettingsPanel } from './components/inspector/LinkSettingsPanel';
import { SpacingPanel } from './components/inspector/SpacingPanel';
import { hasExplicitNumber } from '../../utils/has-explicit-value';
import { useIconDefaults } from '../../hooks';

/**
 * Icon List Item Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {Object}   props.context       - Block context from parent
 * @return {JSX.Element} Icon List Item edit component
 */
export default function IconListItemEdit({
	attributes,
	setAttributes,
	context,
}) {
	const { icon, linkUrl, contentGap } = attributes;

	// Theme-level defaults inherited when the parent leaves size/style unset.
	// Mirrors the resolution used in save.js (context number vs. theme token).
	const iconDefaults = useIconDefaults({
		sizeKey: 'iconList',
		sizeFallback: 32,
	});

	// Get settings from parent via context
	const ctxIconSize = context['designsetgo/iconList/iconSize'];
	const effectiveSize =
		typeof ctxIconSize === 'number' ? ctxIconSize : iconDefaults.size;
	const ctxIconStyle = context['designsetgo/iconList/iconStyle'];
	const effectiveStyle = ctxIconStyle || iconDefaults.style;
	const ctxStrokeWidth = context['designsetgo/iconList/strokeWidth'];
	const strokeWidth =
		typeof ctxStrokeWidth === 'number' ? ctxStrokeWidth : 1.5;
	const iconColor = context['designsetgo/iconList/iconColor'] || '';
	const iconBackgroundColor =
		context['designsetgo/iconList/iconBackgroundColor'] || '';
	const iconPosition = context['designsetgo/iconList/iconPosition'] || 'left';
	const iconVerticalAlignment =
		context['designsetgo/iconList/iconVerticalAlignment'] || 'top';

	// Calculate text alignment based on icon position
	const getTextAlign = () => {
		if (iconPosition === 'top') {
			return 'center';
		}
		if (iconPosition === 'right') {
			return 'right';
		}
		return 'left';
	};

	// Calculate vertical alignment for left/right icon positions
	const getVerticalAlignItems = () => {
		if (iconPosition === 'top') {
			return 'center';
		}
		return iconVerticalAlignment === 'center' ? 'center' : 'flex-start';
	};

	// Calculate item layout styles. The icon↔content gap is owned by the
	// stylesheet (keyed on the position modifier classes) so it is NOT written
	// inline here — this keeps the editor preview in lock-step with save.js and
	// lets kits/patterns retheme the gap. See style.scss.
	const itemStyles = {
		display: 'flex',
		flexDirection: iconPosition === 'top' ? 'column' : 'row',
		alignItems: getVerticalAlignItems(),
		...(iconPosition === 'right' && { flexDirection: 'row-reverse' }),
	};

	// Calculate icon wrapper styles. Preview uses the effective (possibly
	// inherited) size so the canvas matches the frontend inheritance.
	const iconWrapperStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		...(iconBackgroundColor
			? {
					width: `${effectiveSize + 16}px`,
					height: `${effectiveSize + 16}px`,
					minWidth: `${effectiveSize + 16}px`,
					backgroundColor: convertColorToCSSVar(iconBackgroundColor),
					padding: '8px',
					borderRadius: '4px',
					boxSizing: 'border-box',
				}
			: {
					width: `${effectiveSize}px`,
					height: `${effectiveSize}px`,
					minWidth: `${effectiveSize}px`,
				}),
		...(iconColor && {
			color: convertColorToCSSVar(iconColor),
			'--dsgo-icon-color': convertColorToCSSVar(iconColor),
		}),
	};

	// Get block wrapper props
	const blockProps = useBlockProps({
		className: `dsgo-icon-list-item dsgo-icon-list-item--icon-${iconPosition}`,
		style: itemStyles,
	});

	// Configure inner blocks with paragraph as default template. Content gap is
	// inline only for an explicit author value (must match save.js); otherwise
	// the stylesheet default owns it.
	const hasExplicitContentGap = hasExplicitNumber(contentGap);
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-icon-list-item__content',
			style: {
				textAlign: getTextAlign(),
				display: 'flex',
				flexDirection: 'column',
				...(hasExplicitContentGap && { gap: `${contentGap}px` }),
			},
		},
		{
			template: [
				[
					'core/paragraph',
					{
						placeholder: __('List item text…', 'designsetgo'),
					},
				],
			],
			templateLock: false, // Allow adding/removing blocks
		}
	);

	return (
		<>
			<InspectorControls>
				<IconPickerPanel icon={icon} setAttributes={setAttributes} />
				<SpacingPanel
					contentGap={contentGap}
					setAttributes={setAttributes}
				/>
				<LinkSettingsPanel
					linkUrl={linkUrl}
					linkTarget={attributes.linkTarget}
					linkRel={attributes.linkRel}
					setAttributes={setAttributes}
				/>
			</InspectorControls>

			<div {...blockProps}>
				<div
					className="dsgo-icon-list-item__icon"
					style={iconWrapperStyles}
				>
					{getIcon(icon, effectiveStyle, strokeWidth)}
				</div>

				<div {...innerBlocksProps} />

				{linkUrl && (
					<div className="dsgo-icon-list-item__link-indicator">
						🔗
					</div>
				)}
			</div>
		</>
	);
}
