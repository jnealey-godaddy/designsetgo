/**
 * Dynamic Tags extension — inline "Connect" button per bindable attribute.
 *
 * Adds a picker trigger to the Inspector sidebar for every core block
 * whose attribute schema supports the WP Block Bindings API. Writes
 * directly to the native `attributes.metadata.bindings` object so the
 * binding resolves through core's own pipeline on both preview and render.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';

import { DynamicTagButton } from '../../components/DynamicTagPicker';
import { getBindableAttributes } from './bindable-attributes';

const withDynamicTagsInspector = createHigherOrderComponent((BlockEdit) => {
	return function WithDynamicTagsInspector(props) {
		const bindable = getBindableAttributes(props.name);
		if (!bindable) {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;
		const metadata = attributes.metadata || {};
		const bindings = metadata.bindings || {};

		const setBinding = (attributeName, nextBinding, subkey) => {
			const nextBindings = { ...bindings };

			if (!nextBinding) {
				delete nextBindings[attributeName];
			} else {
				const args = { ...(nextBinding.args || {}) };
				// Image-typed attributes need a subkey to project the source's
				// array value into a scalar. Merge the per-attribute default.
				if (subkey && !args.subkey) {
					args.subkey = subkey;
				}
				nextBindings[attributeName] = {
					source: nextBinding.source,
					args,
				};
			}

			const nextMetadata = { ...metadata };
			if (Object.keys(nextBindings).length === 0) {
				delete nextMetadata.bindings;
			} else {
				nextMetadata.bindings = nextBindings;
			}

			setAttributes({
				metadata: Object.keys(nextMetadata).length
					? nextMetadata
					: undefined,
			});
		};

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody
						title={__('Dynamic Tags', 'designsetgo')}
						initialOpen={Object.keys(bindings).length > 0}
					>
						<VStack spacing={3}>
							{bindable.map(
								({ attribute, returns, label, subkey }) => {
									const current = bindings[attribute] || null;
									return (
										<HStack
											key={attribute}
											justify="space-between"
											alignment="center"
										>
											<span className="dsgo-dynamic-tags-extension__label">
												{label}
											</span>
											<DynamicTagButton
												value={current}
												onChange={(next) =>
													setBinding(
														attribute,
														next,
														subkey
													)
												}
												returns={returns}
											/>
										</HStack>
									);
								}
							)}
						</VStack>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withDynamicTagsInspector');

addFilter(
	'editor.BlockEdit',
	'designsetgo/dynamic-tags-inspector',
	withDynamicTagsInspector
);
