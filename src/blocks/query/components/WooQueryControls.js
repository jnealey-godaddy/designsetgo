import { __ } from '@wordpress/i18n';
import { ToggleControl, FormTokenField } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

const STOCK_STATUSES = [
	{ value: 'instock', label: __('In stock', 'designsetgo') },
	{ value: 'outofstock', label: __('Out of stock', 'designsetgo') },
	{ value: 'onbackorder', label: __('On backorder', 'designsetgo') },
];

const STOCK_LABELS = STOCK_STATUSES.map((status) => status.label);

const labelToValue = (label) =>
	STOCK_STATUSES.find((status) => status.label === label)?.value ?? label;

const valueToLabel = (value) =>
	STOCK_STATUSES.find((status) => status.value === value)?.label ?? value;

/**
 * WooCommerce-only query controls.
 *
 * Rendered only when the query targets products AND WooCommerce is active, so
 * non-shop sites never see shop controls. Note there is deliberately no filter
 * UI here: WooCommerce ships `woocommerce/product-filters`, and the Query block
 * reads the URL params those blocks emit. See the WooCommerce Surface plan (D0).
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element|null} Controls, or null when not applicable.
 */
export default function WooQueryControls({ attributes, setAttributes }) {
	const {
		source,
		postType,
		wooCatalogVisibility = true,
		wooFeatured = false,
		wooOnSale = false,
		wooStockStatus = [],
	} = attributes;

	const targetsProducts = source === 'posts' && postType === 'product';

	// `wooCommerceBlocksConfig` is printed by WooCommerce itself; its presence is
	// the cheapest reliable signal that Woo is active in the editor.
	const wooActive =
		typeof window !== 'undefined' &&
		(typeof window.wcSettings !== 'undefined' ||
			typeof window.wooCommerceBlocksConfig !== 'undefined');

	if (!targetsProducts || !wooActive) {
		return null;
	}

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Respect catalog visibility', 'designsetgo')}
				hasValue={() => wooCatalogVisibility !== true}
				onDeselect={() => setAttributes({ wooCatalogVisibility: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Respect catalog visibility', 'designsetgo')}
					help={__(
						'Hides products excluded from the catalog. Turn this off only if you intend to show hidden products.',
						'designsetgo'
					)}
					checked={wooCatalogVisibility}
					onChange={(value) =>
						setAttributes({ wooCatalogVisibility: value })
					}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Featured products only', 'designsetgo')}
				hasValue={() => wooFeatured !== false}
				onDeselect={() => setAttributes({ wooFeatured: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Featured products only', 'designsetgo')}
					checked={wooFeatured}
					onChange={(value) => setAttributes({ wooFeatured: value })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('On sale only', 'designsetgo')}
				hasValue={() => wooOnSale !== false}
				onDeselect={() => setAttributes({ wooOnSale: false })}
				isShownByDefault
			>
				<ToggleControl
					label={__('On sale only', 'designsetgo')}
					checked={wooOnSale}
					onChange={(value) => setAttributes({ wooOnSale: value })}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Stock status', 'designsetgo')}
				hasValue={() => (wooStockStatus || []).length > 0}
				onDeselect={() => setAttributes({ wooStockStatus: [] })}
				isShownByDefault
			>
				<FormTokenField
					label={__('Stock status', 'designsetgo')}
					value={(wooStockStatus || []).map(valueToLabel)}
					suggestions={STOCK_LABELS}
					onChange={(tokens) =>
						setAttributes({
							wooStockStatus: tokens.map(labelToValue),
						})
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
