/**
 * Modal Block - Editor Component
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useUniqueBlockId } from '../../hooks';
import { transferStylesToContent } from './utils/style-transfer';
import ModalSettings from './components/ModalSettings';
import AnimationSettings from './components/AnimationSettings';
import OverlaySettings from './components/OverlaySettings';
import CloseButtonSettings from './components/CloseButtonSettings';
import BehaviorSettings from './components/BehaviorSettings';
import TriggerSettings from './components/TriggerSettings';
import GallerySettings from './components/GallerySettings';
import ModalPlaceholder from './components/ModalPlaceholder';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

export default function ModalEdit({ attributes, setAttributes, clientId }) {
	const {
		modalId,
		width,
		maxWidth,
		height,
		maxHeight,
		overlayOpacity,
		overlayColor,
		showCloseButton,
		closeButtonPosition,
		closeButtonSize,
		closeButtonIconColor,
		closeButtonBgColor,
	} = attributes;

	useUniqueBlockId({
		clientId,
		attributeName: 'modalId',
		value: modalId,
		setAttributes,
		prefix: 'dsgo-modal-',
		length: null,
	});

	// Check if this block has inner blocks and for duplicate modal IDs
	const { hasInnerBlocks, hasDuplicateId } = useSelect(
		(select) => {
			const { getBlock, getBlocks } = select('core/block-editor');
			const block = getBlock(clientId);
			const hasBlocks = block?.innerBlocks?.length > 0;

			let duplicateId = false;
			if (modalId) {
				const allBlocks = getBlocks();

				// Find all modal blocks with matching ID (excluding this block)
				const findDuplicates = (blocks) => {
					let count = 0;
					for (const b of blocks) {
						if (
							b.name === 'designsetgo/modal' &&
							b.clientId !== clientId &&
							b.attributes.modalId === modalId
						) {
							count++;
						}
						if (b.innerBlocks?.length) {
							count += findDuplicates(b.innerBlocks);
						}
					}
					return count;
				};

				duplicateId = findDuplicates(allBlocks) > 0;
			}

			return {
				hasInnerBlocks: hasBlocks,
				hasDuplicateId: duplicateId,
			};
		},
		[modalId, clientId]
	);

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	const blockProps = useBlockProps({
		className: 'dsgo-modal-editor-preview',
	});

	// Transfer block support styles from wrapper to content using shared utility
	const { contentStyle, wrapperProps, contentClasses } =
		transferStylesToContent(blockProps, {
			width,
			maxWidth,
			height,
			maxHeight,
		});

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: ['dsgo-modal__content', ...contentClasses].join(' '),
			style: contentStyle,
		},
		{
			// No default template - user selects from template chooser
			templateLock: false,
		}
	);

	// Show template chooser if there are no inner blocks
	if (!hasInnerBlocks) {
		return (
			<div {...blockProps}>
				<ModalPlaceholder
					clientId={clientId}
					setAttributes={setAttributes}
				/>
			</div>
		);
	}

	// Normal modal editor view
	return (
		<>
			<InspectorControls>
				{hasDuplicateId && (
					<Notice status="error" isDismissible={false}>
						{__(
							'Warning: Another modal on this page has the same ID. This will cause conflicts. Please change the Modal ID to be unique.',
							'designsetgo'
						)}
					</Notice>
				)}

				<ModalSettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				<AnimationSettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				<OverlaySettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				<CloseButtonSettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				<BehaviorSettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				<TriggerSettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				<GallerySettings
					attributes={attributes}
					setAttributes={setAttributes}
				/>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Colors', 'designsetgo')}
					settings={[
						{
							label: __('Overlay Color', 'designsetgo'),
							colorValue: decodeColorValue(
								overlayColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									overlayColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '#000000',
								}),
							clearable: false,
							enableAlpha: true,
						},
						{
							label: __('Close Button Icon', 'designsetgo'),
							colorValue: decodeColorValue(
								closeButtonIconColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									closeButtonIconColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
							enableAlpha: true,
						},
						{
							label: __('Close Button Background', 'designsetgo'),
							colorValue: decodeColorValue(
								closeButtonBgColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									closeButtonBgColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							clearable: true,
							enableAlpha: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...wrapperProps}>
				<div
					className="dsgo-modal-editor-preview__backdrop"
					style={{
						backgroundColor: convertColorToCSSVar(overlayColor),
						opacity: overlayOpacity / 100,
					}}
				/>

				<div className="dsgo-modal-editor-preview__dialog">
					{showCloseButton &&
						!closeButtonPosition.startsWith('inside-') && (
							<button
								className={`dsgo-modal__close dsgo-modal__close--${closeButtonPosition}`}
								style={{
									width: `${closeButtonSize}px`,
									height: `${closeButtonSize}px`,
									color:
										convertColorToCSSVar(
											closeButtonIconColor
										) || undefined,
									backgroundColor:
										convertColorToCSSVar(
											closeButtonBgColor
										) || undefined,
								}}
								aria-label={__('Close modal', 'designsetgo')}
								disabled
							>
								<svg
									width="100%"
									height="100%"
									viewBox="0 0 24 24"
									fill="none"
									xmlns="http://www.w3.org/2000/svg"
								>
									<path
										d="M18 6L6 18M6 6L18 18"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
							</button>
						)}

					<div
						{...innerBlocksProps}
						className={`${innerBlocksProps.className}${
							showCloseButton &&
							closeButtonPosition.startsWith('inside-')
								? ' has-inside-close-button'
								: ''
						}`}
					>
						{innerBlocksProps.children}
						{showCloseButton &&
							closeButtonPosition.startsWith('inside-') && (
								<button
									className={`dsgo-modal__close dsgo-modal__close--${closeButtonPosition}`}
									style={{
										width: `${closeButtonSize}px`,
										height: `${closeButtonSize}px`,
										color:
											convertColorToCSSVar(
												closeButtonIconColor
											) || undefined,
										backgroundColor:
											convertColorToCSSVar(
												closeButtonBgColor
											) || undefined,
									}}
									aria-label={__(
										'Close modal',
										'designsetgo'
									)}
									disabled
								>
									<svg
										width="100%"
										height="100%"
										viewBox="0 0 24 24"
										fill="none"
										xmlns="http://www.w3.org/2000/svg"
									>
										<path
											d="M18 6L6 18M6 6L18 18"
											stroke="currentColor"
											strokeWidth="2"
											strokeLinecap="round"
											strokeLinejoin="round"
										/>
									</svg>
								</button>
							)}
					</div>
				</div>
			</div>
		</>
	);
}
