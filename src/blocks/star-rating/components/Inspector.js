/**
 * Star Rating — inspector controls.
 *
 * Composes the three inspector surfaces the plugin's IA allows: Settings,
 * Style, and — only once an author has opted the block into structured data —
 * the two fields a valid Review or AggregateRating node needs, in Advanced.
 *
 * @since 2.8.0
 */

import {
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useBlockColors } from '../../../hooks';
import { DsgoInspectorPanel } from '../../../components/shared';
import { DEFAULTS } from '../utils/defaults';
import SettingsPanel from './SettingsPanel';
import StylePanel from './StylePanel';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {string}   props.clientId      Block client id.
 * @param {boolean}  props.isRatingBound Whether `rating` is driven by a binding.
 * @return {JSX.Element} Inspector controls.
 */
export default function Inspector({
	attributes,
	setAttributes,
	clientId,
	isRatingBound,
}) {
	const { dsgoSchema, schemaItemName, schemaAuthor } = attributes;

	const { settings, colorGradientSettings } = useBlockColors({
		attributes,
		setAttributes,
		entries: [
			{
				label: __('Rating', 'designsetgo'),
				attribute: 'ratingColor',
			},
			{
				label: __('Track', 'designsetgo'),
				attribute: 'trackColor',
			},
		],
	});

	// The schema extension registers `dsgoSchema` and owns the type control
	// itself; these two fields only exist to complete the node it builds, so
	// they stay hidden until a type is actually chosen.
	const showSchemaFields = !!dsgoSchema && 'none' !== dsgoSchema;

	// Named after the node being configured, not after the feature. The schema
	// extension already renders a panel called "Structured Data" into this same
	// Advanced group, and two identically titled panels in one sidebar read as
	// a duplicate rather than as a type control plus its detail fields.
	const schemaPanelTitle =
		'review' === dsgoSchema
			? __('Review Details', 'designsetgo')
			: __('Aggregate Rating Details', 'designsetgo');

	return (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Stars', 'designsetgo')}
					settings={settings}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<InspectorControls>
				<SettingsPanel
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
					isRatingBound={isRatingBound}
				/>
				<StylePanel
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>

			{showSchemaFields && (
				<InspectorControls group="advanced">
					<DsgoInspectorPanel
						title={schemaPanelTitle}
						panelName="advanced"
						panelId={clientId}
						resetAll={() =>
							setAttributes({
								schemaItemName: DEFAULTS.schemaItemName,
								schemaAuthor: DEFAULTS.schemaAuthor,
							})
						}
					>
						<DsgoInspectorPanel.Item
							label={__('Item being rated', 'designsetgo')}
							hasValue={() =>
								schemaItemName !== DEFAULTS.schemaItemName
							}
							onDeselect={() =>
								setAttributes({
									schemaItemName: DEFAULTS.schemaItemName,
								})
							}
							isShownByDefault
						>
							<TextControl
								label={__('Item being rated', 'designsetgo')}
								help={__(
									'Defaults to the page title.',
									'designsetgo'
								)}
								value={schemaItemName}
								onChange={(value) =>
									setAttributes({ schemaItemName: value })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>

						{'review' === dsgoSchema && (
							<DsgoInspectorPanel.Item
								label={__('Review author', 'designsetgo')}
								hasValue={() =>
									schemaAuthor !== DEFAULTS.schemaAuthor
								}
								onDeselect={() =>
									setAttributes({
										schemaAuthor: DEFAULTS.schemaAuthor,
									})
								}
								isShownByDefault
							>
								<TextControl
									label={__('Review author', 'designsetgo')}
									help={__(
										'Required. A Review with no author is dropped, so nothing is emitted until this is filled in.',
										'designsetgo'
									)}
									value={schemaAuthor}
									onChange={(value) =>
										setAttributes({ schemaAuthor: value })
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
						)}
					</DsgoInspectorPanel>
				</InspectorControls>
			)}
		</>
	);
}
