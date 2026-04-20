import { __ } from '@wordpress/i18n';
import {
	ToggleControl,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

const TAG_OPTIONS = [
	{ value: 'ul', label: __('Unordered list (ul)', 'designsetgo') },
	{ value: 'ol', label: __('Ordered list (ol)', 'designsetgo') },
	{ value: 'div', label: __('Container (div)', 'designsetgo') },
];

const ITEM_TAG_OPTIONS = [
	{ value: 'li', label: 'li' },
	{ value: 'div', label: 'div' },
	{ value: 'article', label: 'article' },
];

const ATTR_DEFAULTS = {
	search: '',
	bindSearchTo: '',
	excludeCurrent: false,
	ignoreSticky: true,
	manualIds: [],
	tagName: 'ul',
	itemTagName: 'li',
};

export default function AdvancedPanel({ attributes, setAttributes, clientId }) {
	const {
		source,
		search,
		bindSearchTo,
		excludeCurrent,
		ignoreSticky,
		manualIds,
		tagName,
		itemTagName,
	} = attributes;

	const manualIdsAsText = Array.isArray(manualIds)
		? manualIds.join(', ')
		: '';

	return (
		<DsgoInspectorPanel
			title={__('Advanced query', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() => setAttributes(ATTR_DEFAULTS)}
		>
			<DsgoInspectorPanel.Item
				label={__('Search', 'designsetgo')}
				hasValue={() => search !== ''}
				onDeselect={() => setAttributes({ search: '' })}
			>
				<TextControl
					label={__('Search text', 'designsetgo')}
					help={__(
						'Limit results by keyword (like WP search).',
						'designsetgo'
					)}
					value={search}
					onChange={(v) => setAttributes({ search: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Bind search to URL param', 'designsetgo')}
				hasValue={() => bindSearchTo !== ''}
				onDeselect={() => setAttributes({ bindSearchTo: '' })}
			>
				<TextControl
					label={__('URL parameter name', 'designsetgo')}
					help={__(
						'Overrides the static search with ?<param>=\u2026 at render time. Leave blank to ignore.',
						'designsetgo'
					)}
					value={bindSearchTo}
					onChange={(v) => setAttributes({ bindSearchTo: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Exclude current post', 'designsetgo')}
				hasValue={() => excludeCurrent !== false}
				onDeselect={() => setAttributes({ excludeCurrent: false })}
			>
				<ToggleControl
					label={__('Exclude current post', 'designsetgo')}
					checked={!!excludeCurrent}
					onChange={(v) => setAttributes({ excludeCurrent: !!v })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Ignore sticky', 'designsetgo')}
				hasValue={() => ignoreSticky !== true}
				onDeselect={() => setAttributes({ ignoreSticky: true })}
			>
				<ToggleControl
					label={__('Ignore sticky posts', 'designsetgo')}
					checked={!!ignoreSticky}
					onChange={(v) => setAttributes({ ignoreSticky: !!v })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{source === 'manual' && (
				<DsgoInspectorPanel.Item
					label={__('Manual IDs', 'designsetgo')}
					hasValue={() =>
						Array.isArray(manualIds) && manualIds.length > 0
					}
					onDeselect={() => setAttributes({ manualIds: [] })}
					isShownByDefault
				>
					<TextareaControl
						label={__(
							'Manual post IDs (comma-separated)',
							'designsetgo'
						)}
						value={manualIdsAsText}
						onChange={(v) => {
							const ids = String(v || '')
								.split(',')
								.map((s) => parseInt(s.trim(), 10))
								.filter((n) => Number.isInteger(n) && n > 0);
							setAttributes({ manualIds: ids });
						}}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Wrapper tag', 'designsetgo')}
				hasValue={() => tagName !== 'ul'}
				onDeselect={() => setAttributes({ tagName: 'ul' })}
			>
				<SelectControl
					label={__('Wrapper tag', 'designsetgo')}
					value={tagName}
					options={TAG_OPTIONS}
					onChange={(v) => setAttributes({ tagName: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Item tag', 'designsetgo')}
				hasValue={() => itemTagName !== 'li'}
				onDeselect={() => setAttributes({ itemTagName: 'li' })}
			>
				<SelectControl
					label={__('Item tag', 'designsetgo')}
					value={itemTagName}
					options={ITEM_TAG_OPTIONS}
					onChange={(v) => setAttributes({ itemTagName: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}
