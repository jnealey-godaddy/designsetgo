/**
 * Schema Extension
 *
 * Lets an author opt a block into emitting schema.org JSON-LD. The attribute
 * only records the choice; `DesignSetGo\SchemaOutput` reads it on wp_head and
 * prints a single graph for the page.
 *
 * Opt-in by design: emitting FAQ markup for an accordion that holds navigation
 * links is structured-data spam and gets sites penalised, so the default is
 * 'none' and nothing reaches the page until an author deliberately chooses.
 *
 * @package
 * @since 1.3.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { SCHEMA_TYPES, SCHEMA_BLOCKS } from './constants';

/**
 * Add the dsgoSchema attribute to allowlisted blocks.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Modified block settings.
 */
function addSchemaAttribute(settings, name) {
	if (!SCHEMA_BLOCKS.includes(name)) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoSchema: {
				type: 'string',
				default: 'none',
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/schema/attributes',
	addSchemaAttribute
);

const withSchemaControl = createHigherOrderComponent(
	(BlockEdit) => (props) => {
		const { name, attributes, setAttributes, isSelected } = props;

		if (!isSelected || !SCHEMA_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls group="advanced">
					<PanelBody
						title={__('Structured Data', 'designsetgo')}
						initialOpen={false}
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Schema type', 'designsetgo')}
							value={attributes.dsgoSchema || 'none'}
							options={SCHEMA_TYPES[name]}
							onChange={(dsgoSchema) =>
								setAttributes({ dsgoSchema })
							}
							help={__(
								'Only choose a type when the content genuinely matches it. Mislabelled structured data can get a site penalised. If an SEO plugin already outputs this type for the page, leave this set to None.',
								'designsetgo'
							)}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withSchemaControl'
);

addFilter('editor.BlockEdit', 'designsetgo/schema/editor', withSchemaControl);
