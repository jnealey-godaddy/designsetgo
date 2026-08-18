/* eslint-disable @wordpress/no-unsafe-wp-apis -- experimental layout/control primitives intentionally used; stable replacements not yet available */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import {
	SelectControl,
	RangeControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';
import TemplateIO from './TemplateIO';
import WooQueryControls from './WooQueryControls';

export const GROUP_BY_OPTIONS = [
	{ value: 'none', label: __('None', 'designsetgo') },
	{ value: 'taxonomy', label: __('Taxonomy', 'designsetgo') },
	{ value: 'meta', label: __('Meta field', 'designsetgo') },
	{ value: 'date', label: __('Date', 'designsetgo') },
];

export const DATE_PRECISION_OPTIONS = [
	{ value: 'Y', label: __('Year', 'designsetgo') },
	{ value: 'Y-M', label: __('Year + Month', 'designsetgo') },
	{ value: 'Y-M-D', label: __('Year + Month + Day', 'designsetgo') },
];

const SOURCES = [
	{ value: 'posts', label: __('Posts', 'designsetgo') },
	{ value: 'users', label: __('Users', 'designsetgo') },
	{ value: 'terms', label: __('Terms', 'designsetgo') },
	{ value: 'manual', label: __('Manual picks', 'designsetgo') },
	{ value: 'current', label: __('Current archive', 'designsetgo') },
	{
		value: 'relationship',
		label: __('Related items (field-driven)', 'designsetgo'),
	},
];

const RELATIONSHIP_FALLBACK_OPTIONS = [
	{ value: 'empty', label: __('Render no items', 'designsetgo') },
	{ value: 'all', label: __('Fall back to all posts', 'designsetgo') },
	{ value: 'parent', label: __('Render the parent item', 'designsetgo') },
];

const ORDER_BY_OPTIONS = [
	{ value: 'date', label: __('Date', 'designsetgo') },
	{ value: 'title', label: __('Title', 'designsetgo') },
	{ value: 'menu_order', label: __('Menu order', 'designsetgo') },
	{ value: 'rand', label: __('Random', 'designsetgo') },
	{ value: 'comment_count', label: __('Comment count', 'designsetgo') },
	{ value: 'meta_value', label: __('Meta value (text)', 'designsetgo') },
	{
		value: 'meta_value_num',
		label: __('Meta value (numeric)', 'designsetgo'),
	},
];

const ORDER_OPTIONS = [
	{ value: 'DESC', label: __('Descending', 'designsetgo') },
	{ value: 'ASC', label: __('Ascending', 'designsetgo') },
];

export default function QuerySourcePanel({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		source,
		postType,
		perPage,
		offset,
		orderBy,
		orderByMetaKey,
		order,
		relationshipField,
		relationshipFallback,
	} = attributes;

	const postTypes = useSelect(
		(select) => select(coreStore).getPostTypes({ per_page: -1 }) || [],
		[]
	);

	const postTypeOptions = (postTypes || [])
		.filter((pt) => pt && pt.viewable)
		.map((pt) => ({
			label: pt.labels?.singular_name || pt.slug,
			value: pt.slug,
		}));

	const showPostType = source === 'posts';
	const showRelationship = source === 'relationship';
	const showMetaKey = ['meta_value', 'meta_value_num'].includes(orderBy);

	return (
		<DsgoInspectorPanel
			title={__('Settings', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() =>
				setAttributes({
					source: 'posts',
					postType: 'post',
					perPage: 6,
					offset: 0,
					orderBy: 'date',
					orderByMetaKey: '',
					order: 'DESC',
				})
			}
		>
			<DsgoInspectorPanel.Item
				label={__('Source', 'designsetgo')}
				hasValue={() => source !== 'posts'}
				onDeselect={() => setAttributes({ source: 'posts' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Source', 'designsetgo')}
					value={source}
					options={SOURCES}
					onChange={(value) => setAttributes({ source: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{showPostType && (
				<DsgoInspectorPanel.Item
					label={__('Post type', 'designsetgo')}
					hasValue={() => postType !== 'post'}
					onDeselect={() => setAttributes({ postType: 'post' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Post type', 'designsetgo')}
						value={postType}
						options={postTypeOptions}
						onChange={(value) => setAttributes({ postType: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			{showRelationship && (
				<DsgoInspectorPanel.Item
					label={__('Relationship field', 'designsetgo')}
					hasValue={() => (relationshipField || '') !== ''}
					onDeselect={() => setAttributes({ relationshipField: '' })}
					isShownByDefault
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={__('Relationship field', 'designsetgo')}
						help={__(
							'Meta key or ACF field on the parent item that holds the related post IDs.',
							'designsetgo'
						)}
						value={relationshipField || ''}
						onChange={(v) =>
							setAttributes({ relationshipField: v })
						}
					/>
				</DsgoInspectorPanel.Item>
			)}

			{showRelationship && (
				<DsgoInspectorPanel.Item
					label={__('When no related items', 'designsetgo')}
					hasValue={() =>
						(relationshipFallback || 'empty') !== 'empty'
					}
					onDeselect={() =>
						setAttributes({ relationshipFallback: 'empty' })
					}
					isShownByDefault
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={__('When no related items', 'designsetgo')}
						value={relationshipFallback || 'empty'}
						onChange={(v) =>
							setAttributes({ relationshipFallback: v })
						}
						options={RELATIONSHIP_FALLBACK_OPTIONS}
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Items per page', 'designsetgo')}
				hasValue={() => perPage !== 6}
				onDeselect={() => setAttributes({ perPage: 6 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Items per page', 'designsetgo')}
					value={perPage}
					min={1}
					max={48}
					onChange={(value) => setAttributes({ perPage: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Offset', 'designsetgo')}
				hasValue={() => offset !== 0}
				onDeselect={() => setAttributes({ offset: 0 })}
				isShownByDefault
			>
				<NumberControl
					label={__('Offset', 'designsetgo')}
					value={offset}
					min={0}
					onChange={(value) =>
						setAttributes({ offset: Number(value) || 0 })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Order by', 'designsetgo')}
				hasValue={() => orderBy !== 'date'}
				onDeselect={() => setAttributes({ orderBy: 'date' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Order by', 'designsetgo')}
					value={orderBy}
					options={ORDER_BY_OPTIONS}
					onChange={(value) => setAttributes({ orderBy: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{showMetaKey && (
				<DsgoInspectorPanel.Item
					label={__('Order by meta key', 'designsetgo')}
					hasValue={() => orderByMetaKey !== ''}
					onDeselect={() => setAttributes({ orderByMetaKey: '' })}
					isShownByDefault
				>
					<TextControl
						label={__('Meta key', 'designsetgo')}
						value={orderByMetaKey}
						onChange={(value) =>
							setAttributes({
								orderByMetaKey: String(value || ''),
							})
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}

			<DsgoInspectorPanel.Item
				label={__('Order direction', 'designsetgo')}
				hasValue={() => order !== 'DESC'}
				onDeselect={() => setAttributes({ order: 'DESC' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Order direction', 'designsetgo')}
					value={order}
					options={ORDER_OPTIONS}
					onChange={(value) => setAttributes({ order: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<WooQueryControls
				attributes={attributes}
				setAttributes={setAttributes}
			/>

			<DsgoInspectorPanel.Item
				label={__('Template I/O', 'designsetgo')}
				hasValue={() => false}
				onDeselect={() => {}}
				isShownByDefault
			>
				<TemplateIO clientId={clientId} attributes={attributes} />
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}
