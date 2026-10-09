/**
 * Form Step — editor.
 *
 * Steps stack in the canvas: a header with the step number and an inline
 * title, then the step's fields.
 *
 * @since 2.10.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	RichText,
	BlockControls,
	InspectorControls,
} from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { DsgoInspectorPanel, DsgoChildToolbar } from '../../components/shared';
import { FORM_FIELD_BLOCKS } from '../form-builder/utils/field-blocks';

export default function FormStepEdit({ attributes, setAttributes, clientId }) {
	const { title } = attributes;
	const { rootClientId, index, siblingCount } = useSelect(
		(select) => {
			const store = select('core/block-editor');
			const root = store.getBlockRootClientId(clientId);
			return {
				rootClientId: root,
				index: store.getBlockIndex(clientId),
				siblingCount: store.getBlockCount(root),
			};
		},
		[clientId]
	);

	const blockProps = useBlockProps({ className: 'dsgo-form-step' });
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'dsgo-form-step__fields' },
		{
			allowedBlocks: FORM_FIELD_BLOCKS,
			orientation: 'vertical',
			renderAppender: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<>
			<BlockControls>
				<DsgoChildToolbar
					parentClientId={rootClientId}
					childBlockName="designsetgo/form-step"
					activeIndex={index}
					orientation="vertical"
					addLabel={__('Add step', 'designsetgo')}
					duplicateLabel={__('Duplicate step', 'designsetgo')}
					removeLabel={__('Remove step', 'designsetgo')}
					movePrevLabel={__('Move step up', 'designsetgo')}
					moveNextLabel={__('Move step down', 'designsetgo')}
					disableRemove={siblingCount <= 1}
				/>
			</BlockControls>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => setAttributes({ title: '' })}
				>
					<DsgoInspectorPanel.Item
						label={__('Title', 'designsetgo')}
						hasValue={() => title !== ''}
						onDeselect={() => setAttributes({ title: '' })}
						isShownByDefault
					>
						<TextControl
							label={__('Title', 'designsetgo')}
							value={title}
							onChange={(value) =>
								setAttributes({ title: value })
							}
							help={__(
								'Shown as the step heading and in the progress indicator. Leave empty for "Step N".',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>
			<div {...blockProps}>
				<div className="dsgo-form-step__header">
					<span className="dsgo-form-step__number">
						{sprintf(
							/* translators: %d: step number */
							__('Step %d', 'designsetgo'),
							index + 1
						)}
					</span>
					<RichText
						tagName="h3"
						className="dsgo-form-step__title"
						value={title}
						onChange={(value) => setAttributes({ title: value })}
						placeholder={__('Step title', 'designsetgo')}
						allowedFormats={[]}
						withoutInteractiveFormatting
					/>
				</div>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}
