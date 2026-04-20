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

const SOURCES = [
	{ value: 'posts', label: __('Posts', 'designsetgo') },
	{ value: 'users', label: __('Users', 'designsetgo') },
	{ value: 'terms', label: __('Terms', 'designsetgo') },
	{ value: 'manual', label: __('Manual picks', 'designsetgo') },
	{ value: 'current', label: __('Current archive', 'designsetgo') },
	{ value: 'relationship', label: __('Related items (field-driven)', 'designsetgo') },
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
		columns,
		columnsTablet,
		columnsMobile,
		columnGap,
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
					columns: 1,
					columnsTablet: 0,
					columnsMobile: 0,
					columnGap: '',
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
						onChange={(v) => setAttributes({ relationshipField: v })}
					/>
				</DsgoInspectorPanel.Item>
			)}

			{showRelationship && (
				<DsgoInspectorPanel.Item
					label={__('When no related items', 'designsetgo')}
					hasValue={() => (relationshipFallback || 'empty') !== 'empty'}
					onDeselect={() => setAttributes({ relationshipFallback: 'empty' })}
					isShownByDefault
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={__('When no related items', 'designsetgo')}
						value={relationshipFallback || 'empty'}
						onChange={(v) => setAttributes({ relationshipFallback: v })}
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
				label={__('Columns', 'designsetgo')}
				hasValue={() => columns !== 1}
				onDeselect={() => setAttributes({ columns: 1 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Columns', 'designsetgo')}
					help={__(
						'Desktop columns. Tablet and mobile narrow further by default.',
						'designsetgo'
					)}
					value={columns}
					min={1}
					max={6}
					onChange={(value) => setAttributes({ columns: value || 1 })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Columns (tablet)', 'designsetgo')}
				hasValue={() => columnsTablet !== 0}
				onDeselect={() => setAttributes({ columnsTablet: 0 })}
				isShownByDefault={false}
			>
				<RangeControl
					label={__('Columns (tablet)', 'designsetgo')}
					help={__('0 inherits from desktop.', 'designsetgo')}
					value={columnsTablet}
					min={0}
					max={6}
					onChange={(value) =>
						setAttributes({ columnsTablet: value || 0 })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Columns (mobile)', 'designsetgo')}
				hasValue={() => columnsMobile !== 0}
				onDeselect={() => setAttributes({ columnsMobile: 0 })}
				isShownByDefault={false}
			>
				<RangeControl
					label={__('Columns (mobile)', 'designsetgo')}
					help={__('0 means single column on mobile.', 'designsetgo')}
					value={columnsMobile}
					min={0}
					max={3}
					onChange={(value) =>
						setAttributes({ columnsMobile: value || 0 })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Column gap', 'designsetgo')}
				hasValue={() => columnGap !== ''}
				onDeselect={() => setAttributes({ columnGap: '' })}
				isShownByDefault={false}
			>
				<TextControl
					label={__('Column gap', 'designsetgo')}
					help={__(
						'CSS length (e.g. 1.5rem, 24px). Leave blank for default.',
						'designsetgo'
					)}
					value={columnGap}
					onChange={(value) =>
						setAttributes({ columnGap: String(value || '') })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Offset', 'designsetgo')}
				hasValue={() => offset !== 0}
				onDeselect={() => setAttributes({ offset: 0 })}
				isShownByDefault={false}
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
				isShownByDefault={false}
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
				isShownByDefault={false}
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
		</DsgoInspectorPanel>
	);
}
