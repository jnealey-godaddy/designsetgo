/** Optional composition attributes and styles, shared by editor and serialization. */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, useStyleOverride } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';
import { DsgoInspectorPanel } from '../../components/shared/DsgoInspectorPanel';
import LayoutControls, { useLayoutParent } from './controls';
import {
	support,
	isContainer,
	getLayoutClass,
	compileLayoutCSS,
} from './utils';

export function addLayoutAttribute(settings, name) {
	if (!support.blocks[name] || !shouldExtendBlock(name)) {
		return settings;
	}
	return {
		...settings,
		attributes: { ...settings.attributes, dsgoLayout: { type: 'object' } },
	};
}

export function applyLayoutSaveProps(props, blockType, attributes) {
	if (!shouldExtendBlock(blockType.name)) {
		return props;
	}
	const className = getLayoutClass(blockType.name, attributes.dsgoLayout);
	return className
		? {
				...props,
				className: [props.className, className]
					.filter(Boolean)
					.join(' '),
			}
		: props;
}

function ChildControls(props) {
	const parent = useLayoutParent(props.clientId);
	if (!isContainer(parent?.name) || !props.isSelected) {
		return null;
	}
	return (
		<InspectorControls>
			<DsgoInspectorPanel
				title={__('Settings', 'designsetgo')}
				panelName="settings"
				panelId={props.clientId}
				resetAll={() => props.setAttributes({ dsgoLayout: undefined })}
			>
				<LayoutControls {...props} />
			</DsgoInspectorPanel>
		</InspectorControls>
	);
}

const withChildControls = createHigherOrderComponent(
	(BlockEdit) => (props) => (
		<>
			<BlockEdit {...props} />
			{support.blocks[props.name]?.role === 'item' &&
				props.name.startsWith('core/') &&
				shouldExtendBlock(props.name) && <ChildControls {...props} />}
		</>
	),
	'withLayoutChildControls'
);

function StyledBlock({ BlockListBlock, ...props }) {
	const className = getLayoutClass(props.name, props.attributes.dsgoLayout);
	const selector = `#block-${props.clientId}[data-block].${className}`;
	useStyleOverride({
		id: `dsgo-layout-${props.clientId}`,
		css: compileLayoutCSS(
			props.name,
			props.attributes.dsgoLayout,
			selector
		),
	});
	return (
		<BlockListBlock
			{...props}
			wrapperProps={{
				...props.wrapperProps,
				className: [props.wrapperProps?.className, className]
					.filter(Boolean)
					.join(' '),
			}}
		/>
	);
}

const withLayoutStyles = createHigherOrderComponent(
	(BlockListBlock) => (props) => {
		if (
			!support.blocks[props.name] ||
			!shouldExtendBlock(props.name) ||
			!getLayoutClass(props.name, props.attributes.dsgoLayout)
		) {
			return <BlockListBlock {...props} />;
		}
		return <StyledBlock {...props} BlockListBlock={BlockListBlock} />;
	},
	'withLayoutStyles'
);

addFilter(
	'blocks.registerBlockType',
	'designsetgo/layout-attribute',
	addLayoutAttribute
);
addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/layout-save-props',
	applyLayoutSaveProps
);
addFilter(
	'editor.BlockListBlock',
	'designsetgo/layout-editor-styles',
	withLayoutStyles
);
if (isExtensionEnabled('layout')) {
	addFilter(
		'editor.BlockEdit',
		'designsetgo/layout-child-controls',
		withChildControls
	);
}
