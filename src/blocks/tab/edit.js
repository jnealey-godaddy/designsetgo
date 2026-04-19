/**
 * Tab Block - Edit Component
 *
 * Individual tab panel (child block)
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { TextControl, SelectControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useEffect } from '@wordpress/element';
import { IconPicker } from '../icon/components/IconPicker';

export default function Edit({ attributes, setAttributes, clientId, context }) {
	const { uniqueId, title, icon, iconPosition } = attributes;

	// Get context from parent Tabs block
	const activeTab = context['designsetgo/tabs/activeTab'] || 0;
	const tabStyle = context['designsetgo/tabs/tabStyle'] || 'default';

	// Generate unique ID on mount
	useEffect(() => {
		if (!uniqueId) {
			setAttributes({ uniqueId: clientId.substring(0, 8) });
		}
	}, [uniqueId, clientId, setAttributes]);

	// Generate anchor from title if not set
	useEffect(() => {
		if (title && !attributes.anchor) {
			const anchor = title
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/(^-|-$)/g, '');
			setAttributes({ anchor });
		}
	}, [title, attributes.anchor, setAttributes]);

	// Get this tab's index from parent
	const tabIndex = wp.data
		.select('core/block-editor')
		.getBlockOrder(
			wp.data.select('core/block-editor').getBlockRootClientId(clientId)
		)
		.indexOf(clientId);

	const isActive = tabIndex === activeTab;

	const blockProps = useBlockProps({
		className: `dsgo-tab dsgo-tab--${tabStyle} ${isActive ? 'is-active' : 'is-inactive'}`,
		role: 'tabpanel',
		'aria-labelledby': `tab-${uniqueId}`,
		id: `panel-${uniqueId}`,
		'data-tab-active': isActive,
		style: {
			display: isActive ? 'block' : 'none',
		},
	});

	// Use useInnerBlocksProps for proper WordPress integration
	// IMPORTANT: Always show appender when tab is active so users can add content
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-tab__content',
		},
		{
			templateLock: false,
			renderAppender: isActive
				? wp.blockEditor.InnerBlocks.ButtonBlockAppender
				: false,
		}
	);

	const inspector = (
		<InspectorControls>
			<DsgoInspectorPanel
				title={__('Settings', 'designsetgo')}
				panelName="settings"
				panelId={clientId}
				resetAll={() =>
					setAttributes({
						title: 'Tab',
						icon: '',
						iconPosition: 'none',
						anchor: '',
					})
				}
			>
				<DsgoInspectorPanel.Item
					label={__('Tab Title', 'designsetgo')}
					hasValue={() => title !== 'Tab'}
					onDeselect={() => setAttributes({ title: 'Tab' })}
					isShownByDefault
				>
					<TextControl
						label={__('Tab Title', 'designsetgo')}
						value={title}
						onChange={(value) => setAttributes({ title: value })}
						help={__(
							'The title shown in the tab navigation',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>

				<DsgoInspectorPanel.Item
					label={__('Icon Position', 'designsetgo')}
					hasValue={() => iconPosition !== 'none'}
					onDeselect={() =>
						setAttributes({ iconPosition: 'none', icon: '' })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Icon Position', 'designsetgo')}
						value={iconPosition}
						options={[
							{
								label: __('None', 'designsetgo'),
								value: 'none',
							},
							{
								label: __('Left', 'designsetgo'),
								value: 'left',
							},
							{
								label: __('Right', 'designsetgo'),
								value: 'right',
							},
							{
								label: __('Top', 'designsetgo'),
								value: 'top',
							},
						]}
						onChange={(value) =>
							setAttributes({ iconPosition: value })
						}
						help={__(
							'Choose where to display an icon with the tab',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>

				{iconPosition !== 'none' && (
					<DsgoInspectorPanel.Item
						label={__('Tab Icon', 'designsetgo')}
						hasValue={() => icon !== ''}
						onDeselect={() => setAttributes({ icon: '' })}
						isShownByDefault
					>
						<IconPicker
							value={icon}
							onChange={(value) => setAttributes({ icon: value })}
							label={__('Tab Icon', 'designsetgo')}
							help={__(
								'Choose an icon to display with the tab',
								'designsetgo'
							)}
						/>
					</DsgoInspectorPanel.Item>
				)}

				<DsgoInspectorPanel.Item
					label={__('Anchor (URL Hash)', 'designsetgo')}
					hasValue={() => !!attributes.anchor}
					onDeselect={() => setAttributes({ anchor: '' })}
					isShownByDefault={false}
				>
					<TextControl
						label={__('Anchor (URL Hash)', 'designsetgo')}
						value={attributes.anchor}
						onChange={(value) => setAttributes({ anchor: value })}
						help={__(
							'URL-friendly identifier for deep linking',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			</DsgoInspectorPanel>
		</InspectorControls>
	);

	// Don't render inactive tabs at all - improves performance
	if (!isActive) {
		return (
			<>
				{inspector}

				<div {...blockProps}>
					<div className="dsgo-tab__inactive-notice">
						<svg
							xmlns="http://www.w3.org/2000/svg"
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2"
						>
							<circle cx="12" cy="12" r="10" />
							<line x1="12" y1="8" x2="12" y2="12" />
							<line x1="12" y1="16" x2="12.01" y2="16" />
						</svg>
						<span>
							{__(
								'Click the tab above to edit its content',
								'designsetgo'
							)}
						</span>
					</div>
				</div>
			</>
		);
	}

	return (
		<>
			{inspector}

			<div {...blockProps}>
				{/* Use spread props pattern - InnerBlocks will show appender */}
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}
