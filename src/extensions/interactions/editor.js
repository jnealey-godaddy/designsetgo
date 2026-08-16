/**
 * Interaction Layers - Editor controls
 *
 * @package
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { InteractionsPanel } from './components/InteractionsPanel';

const withInteractionControls = createHigherOrderComponent(
	(BlockEdit) => (props) => {
		const { name, attributes, setAttributes, isSelected } = props;

		if (!isSelected || !shouldExtendBlock(name)) {
			return <BlockEdit {...props} />;
		}

		if (undefined === attributes.dsgoInteractions) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls group="advanced">
					<PanelBody
						title={__('Interactions', 'designsetgo')}
						initialOpen={false}
					>
						<InteractionsPanel
							value={attributes.dsgoInteractions}
							onChange={(dsgoInteractions) =>
								setAttributes({ dsgoInteractions })
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withInteractionControls'
);

addFilter(
	'editor.BlockEdit',
	'designsetgo/interactions/editor',
	withInteractionControls
);
