/**
 * Dynamic Tags extension — inline toolbar + Inspector picker.
 *
 * Adds two ways to bind any of a core block's bindable attributes to a
 * Dynamic Tag source:
 *
 *  1. A toolbar icon in the block's inline BlockControls (database
 *     cylinder — the conventional "dynamic data" symbol; deliberately
 *     distinct from the lightning-bolt used by the block-animations
 *     extension). Single-attribute blocks open the picker directly;
 *     multi-attribute blocks (image, button) open a dropdown menu of
 *     attribute names that each open the picker.
 *
 *  2. An Inspector "Dynamic Tags" panel listing every bindable attribute.
 *
 * Both paths write to native `attributes.metadata.bindings` so the value
 * resolves through WP core's own bindings pipeline.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, BlockControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToolbarGroup,
	ToolbarButton,
	ToolbarDropdownMenu,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Fragment, useState } from '@wordpress/element';

import { DynamicTagButton, DynamicTagPicker } from '../../components/DynamicTagPicker';
import { getBindableAttributes } from './bindable-attributes';

// Database cylinder — the conventional "dynamic data" icon used by
// Elementor and other page builders, and matches the icon already on
// our DynamicTagButton in the inspector. Deliberately distinct from
// the block-animations extension's lightning bolt.
const dynamicIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="20"
		height="20"
		aria-hidden="true"
	>
		<ellipse
			cx="12"
			cy="5"
			rx="7"
			ry="2.5"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.6"
		/>
		<path
			d="M5 5v6c0 1.38 3.13 2.5 7 2.5s7-1.12 7-2.5V5"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
		/>
		<path
			d="M5 11v6c0 1.38 3.13 2.5 7 2.5s7-1.12 7-2.5v-6"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
		/>
	</svg>
);

function getDynamicTagsBindings({ attributes, setAttributes }) {
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
			metadata: Object.keys(nextMetadata).length ? nextMetadata : undefined,
		});
	};

	return { bindings, setBinding };
}

function DynamicTagsToolbar({ bindable, bindings, setBinding }) {
	const [pickerAttribute, setPickerAttribute] = useState(null);

	const isAnyConnected = Object.keys(bindings).length > 0;
	const active = bindable.find((b) => b.attribute === pickerAttribute) || null;
	const currentValue = active ? bindings[active.attribute] || null : null;

	const closePicker = () => setPickerAttribute(null);

	// Single bindable attribute (paragraph, heading, post-date): one button.
	if (bindable.length === 1) {
		const only = bindable[0];
		const isConnected = Boolean(bindings[only.attribute]);
		return (
			<>
				<BlockControls group="other">
					<ToolbarGroup>
						<ToolbarButton
							icon={dynamicIcon}
							label={
								isConnected
									? sprintf(
											/* translators: %s: bound source slug */
											__('Dynamic — bound to %s', 'designsetgo'),
											bindings[only.attribute].source
										)
									: __('Connect to Dynamic Tag', 'designsetgo')
							}
							isActive={isConnected}
							onClick={() => setPickerAttribute(only.attribute)}
						/>
					</ToolbarGroup>
				</BlockControls>
				{pickerAttribute && (
					<DynamicTagPicker
						isOpen
						onClose={closePicker}
						value={currentValue}
						onChange={(next) =>
							setBinding(active.attribute, next, active.subkey)
						}
						returns={active.returns}
						title={sprintf(
							/* translators: %s: bindable attribute label */
							__('Dynamic Tag — %s', 'designsetgo'),
							active.label
						)}
					/>
				)}
			</>
		);
	}

	// Multi-attribute block (image, button): dropdown menu.
	const controls = bindable.map((b) => ({
		title: bindings[b.attribute]
			? sprintf(
					/* translators: 1: attribute label, 2: bound source slug */
					__('%1$s — %2$s', 'designsetgo'),
					b.label,
					bindings[b.attribute].source
				)
			: b.label,
		icon: bindings[b.attribute] ? dynamicIcon : undefined,
		onClick: () => setPickerAttribute(b.attribute),
	}));

	return (
		<>
			<BlockControls group="other">
				<ToolbarGroup>
					<ToolbarDropdownMenu
						icon={dynamicIcon}
						label={__('Connect to Dynamic Tag', 'designsetgo')}
						toggleProps={{ isActive: isAnyConnected }}
						controls={controls}
					/>
				</ToolbarGroup>
			</BlockControls>
			{pickerAttribute && active && (
				<DynamicTagPicker
					isOpen
					onClose={closePicker}
					value={currentValue}
					onChange={(next) =>
						setBinding(active.attribute, next, active.subkey)
					}
					returns={active.returns}
					title={sprintf(
						/* translators: %s: bindable attribute label */
						__('Dynamic Tag — %s', 'designsetgo'),
						active.label
					)}
				/>
			)}
		</>
	);
}

const withDynamicTagsControls = createHigherOrderComponent((BlockEdit) => {
	return function WithDynamicTagsControls(props) {
		const bindable = getBindableAttributes(props.name);
		if (!bindable) {
			return <BlockEdit {...props} />;
		}

		const { bindings, setBinding } = getDynamicTagsBindings(props);

		return (
			<Fragment>
				<BlockEdit {...props} />
				<DynamicTagsToolbar
					bindable={bindable}
					bindings={bindings}
					setBinding={setBinding}
				/>
				<InspectorControls>
					<PanelBody
						title={__('Dynamic Tags', 'designsetgo')}
						initialOpen={Object.keys(bindings).length > 0}
					>
						<VStack spacing={3}>
							{bindable.map(({ attribute, returns, label, subkey }) => {
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
												setBinding(attribute, next, subkey)
											}
											returns={returns}
										/>
									</HStack>
								);
							})}
						</VStack>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withDynamicTagsControls');

addFilter(
	'editor.BlockEdit',
	'designsetgo/dynamic-tags-controls',
	withDynamicTagsControls
);
