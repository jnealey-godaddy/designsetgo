/**
 * Modal Trigger Block - Editor Component
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	SelectControl,
	Notice,
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { getIcon } from '../icon/utils/svg-icons';
import { IconPicker } from '../icon/components/IconPicker';

/**
 * Recursively find modal blocks in a block tree
 *
 * @param {Array} blocks - Blocks to search
 * @param {Array} result - Accumulator array
 * @return {Array} Array of modal info objects
 */
function findModals(blocks, result = []) {
	for (const block of blocks) {
		if (block.name === 'designsetgo/modal') {
			result.push({
				id: block.attributes.modalId || '',
				clientId: block.clientId,
			});
		}
		if (block.innerBlocks?.length) {
			findModals(block.innerBlocks, result);
		}
	}
	return result;
}

export default function ModalTriggerEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		targetModalId,
		text,
		buttonStyle,
		align,
		icon,
		iconPosition,
		iconSize,
		iconGap,
		style,
		backgroundColor,
		textColor,
		fontSize,
	} = attributes;

	// Get all blocks from the editor — getBlocks returns a stable reference
	// when blocks haven't changed, so useSelect won't cause re-renders
	const allBlocks = useSelect(
		(select) => select('core/block-editor').getBlocks(),
		[]
	);

	// Derive modal list outside useSelect so we don't create new arrays
	// inside the selector. useMemo ensures stable reference.
	const modals = useMemo(() => findModals(allBlocks), [allBlocks]);

	// Create options for the select control
	const modalOptions = [
		{ label: __('Select a modal…', 'designsetgo'), value: '' },
		...modals.map((modal) => ({
			label: modal.id || __('(Unnamed Modal)', 'designsetgo'),
			value: modal.id,
		})),
	];

	// Extract WordPress color values
	// Custom colors come from style.color.background (hex/rgb)
	// Preset colors come from backgroundColor/textColor (slugs that need conversion)
	const bgColor =
		style?.color?.background ||
		(backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
	const txtColor =
		style?.color?.text ||
		(textColor && `var(--wp--preset--color--${textColor})`);

	// Extract font size
	// Custom font sizes come from style.typography.fontSize (px/rem/em)
	// Preset font sizes come from fontSize (slug that needs conversion)
	const fontSizeValue =
		style?.typography?.fontSize ||
		(fontSize && `var(--wp--preset--font-size--${fontSize})`);

	// Extract padding - WordPress stores it in style.spacing.padding
	const paddingValue = style?.spacing?.padding;

	// Calculate if full width based on alignment
	const isFullWidth = align === 'full';

	// Calculate button styles
	const buttonStyles = {
		display: isFullWidth ? 'flex' : 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: isFullWidth ? '100%' : 'auto',
		gap: iconPosition !== 'none' && icon ? iconGap : 0,
		flexDirection: iconPosition === 'end' ? 'row-reverse' : 'row',
		...(bgColor && { backgroundColor: bgColor }),
		...(txtColor && { color: txtColor }),
		...(fontSizeValue && { fontSize: fontSizeValue }),
		...(paddingValue?.top !== undefined && {
			paddingTop: paddingValue.top,
		}),
		...(paddingValue?.right !== undefined && {
			paddingRight: paddingValue.right,
		}),
		...(paddingValue?.bottom !== undefined && {
			paddingBottom: paddingValue.bottom,
		}),
		...(paddingValue?.left !== undefined && {
			paddingLeft: paddingValue.left,
		}),
	};

	// Calculate icon wrapper styles
	const iconWrapperStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: `${iconSize}px`,
		height: `${iconSize}px`,
		flexShrink: 0,
	};

	// wp-block-button and wp-element-button enable theme.json button styles
	// wp-block-button__link ensures theme compatibility
	// WordPress automatically adds alignment classes (alignleft, aligncenter, alignright, alignfull)
	const blockProps = useBlockProps({
		className: `dsgo-modal-trigger dsgo-modal-trigger--${buttonStyle} wp-block-button wp-block-button__link wp-element-button`,
		style: buttonStyles,
	});

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							targetModalId: '',
							buttonStyle: 'fill',
							icon: '',
							iconPosition: 'none',
							iconSize: 20,
							iconGap: '8px',
						})
					}
				>
					{modals.length === 0 && (
						<Notice status="warning" isDismissible={false}>
							{__(
								'No modal blocks found on this page. Add a Modal block first.',
								'designsetgo'
							)}
						</Notice>
					)}

					<DsgoInspectorPanel.Item
						label={__('Target Modal', 'designsetgo')}
						hasValue={() => targetModalId !== ''}
						onDeselect={() => setAttributes({ targetModalId: '' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Target Modal', 'designsetgo')}
							value={targetModalId}
							options={modalOptions}
							onChange={(value) =>
								setAttributes({ targetModalId: value })
							}
							help={__(
								'Select which modal this button should open.',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Button Style', 'designsetgo')}
						hasValue={() => buttonStyle !== 'fill'}
						onDeselect={() =>
							setAttributes({ buttonStyle: 'fill' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Button Style', 'designsetgo')}
							value={buttonStyle}
							onChange={(value) =>
								setAttributes({ buttonStyle: value })
							}
							options={[
								{
									label: __('Fill', 'designsetgo'),
									value: 'fill',
								},
								{
									label: __('Outline', 'designsetgo'),
									value: 'outline',
								},
								{
									label: __('Link', 'designsetgo'),
									value: 'link',
								},
							]}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Icon', 'designsetgo')}
						hasValue={() => icon !== ''}
						onDeselect={() =>
							setAttributes({ icon: '', iconPosition: 'none' })
						}
						isShownByDefault
					>
						<IconPicker
							label={__('Icon', 'designsetgo')}
							value={icon}
							onChange={(value) => {
								setAttributes({ icon: value });
								// If icon is selected and position is none, default to start
								if (value && iconPosition === 'none') {
									setAttributes({ iconPosition: 'start' });
								}
								// If icon is cleared, set position to none
								if (!value) {
									setAttributes({ iconPosition: 'none' });
								}
							}}
						/>
					</DsgoInspectorPanel.Item>

					{icon && (
						<DsgoInspectorPanel.Item
							label={__('Icon Position', 'designsetgo')}
							hasValue={() => iconPosition !== 'none'}
							onDeselect={() =>
								setAttributes({ iconPosition: 'none' })
							}
							isShownByDefault
						>
							<SelectControl
								label={__('Icon Position', 'designsetgo')}
								value={iconPosition}
								options={[
									{
										label: __('Start', 'designsetgo'),
										value: 'start',
									},
									{
										label: __('End', 'designsetgo'),
										value: 'end',
									},
									{
										label: __('None', 'designsetgo'),
										value: 'none',
									},
								]}
								onChange={(value) =>
									setAttributes({ iconPosition: value })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{icon && iconPosition !== 'none' && (
						<DsgoInspectorPanel.Item
							label={__('Icon Size (px)', 'designsetgo')}
							hasValue={() => iconSize !== 20}
							onDeselect={() => setAttributes({ iconSize: 20 })}
							isShownByDefault
						>
							<RangeControl
								label={__('Icon Size (px)', 'designsetgo')}
								value={iconSize}
								onChange={(value) =>
									setAttributes({ iconSize: value })
								}
								min={12}
								max={48}
								step={1}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{icon && iconPosition !== 'none' && (
						<DsgoInspectorPanel.Item
							label={__('Icon Gap', 'designsetgo')}
							hasValue={() => iconGap !== '8px'}
							onDeselect={() => setAttributes({ iconGap: '8px' })}
							isShownByDefault
						>
							<UnitControl
								label={__('Icon Gap', 'designsetgo')}
								value={iconGap}
								onChange={(value) =>
									setAttributes({ iconGap: value })
								}
								units={[
									{ value: 'px', label: 'px' },
									{ value: 'em', label: 'em' },
									{ value: 'rem', label: 'rem' },
								]}
								__next40pxDefaultSize
							/>
						</DsgoInspectorPanel.Item>
					)}
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				{icon && iconPosition === 'start' && (
					<span
						className="dsgo-modal-trigger__icon"
						style={iconWrapperStyles}
					>
						{getIcon(icon)}
					</span>
				)}
				<RichText
					tagName="span"
					value={text}
					onChange={(value) => setAttributes({ text: value })}
					placeholder={__('Button text…', 'designsetgo')}
					allowedFormats={['core/bold', 'core/italic']}
					className="dsgo-modal-trigger__text"
				/>
				{icon && iconPosition === 'end' && (
					<span
						className="dsgo-modal-trigger__icon"
						style={iconWrapperStyles}
					>
						{getIcon(icon)}
					</span>
				)}
			</div>
		</>
	);
}
