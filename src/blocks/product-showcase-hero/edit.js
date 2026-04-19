/**
 * Product Showcase Hero Block - Edit Component
 *
 * Full-width hero section showcasing a WooCommerce product.
 * Supports manual product selection or inheriting the current product context
 * (for use in single product templates).
 *
 * @since 2.1.0
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import {
	Placeholder,
	Button,
	ToolbarButton,
	ToolbarGroup,
	Spinner,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseCustomUnits as useCustomUnits,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import { DsgoInspectorPanel } from '../../components/shared';
import ProductPicker from './components/ProductPicker';
import ProductPreview from './components/ProductPreview';
import DisplayOptionsPanel from './components/DisplayOptionsPanel';
import LayoutPanel from './components/LayoutPanel';

/**
 * Product Showcase Hero Edit Component
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to set attributes
 * @param {Object}   props.context       Block context (postId, postType from usesContext)
 * @param {string}   props.clientId      Block client ID
 * @return {JSX.Element} Edit component
 */
export default function ProductShowcaseHeroEdit({
	attributes,
	setAttributes,
	context,
	clientId,
}) {
	const { productId, productSource, layout } = attributes;

	const [productData, setProductData] = useState(null);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);

	const units = useCustomUnits({
		availableUnits: ['px', 'vh', 'vw', 'em', 'rem'],
	});

	const isCurrentMode = productSource === 'current';

	// Determine which product ID to preview.
	// In "current" mode, use the context postId (Site Editor provides this
	// for template editing) or fall back to fetching a sample product.
	const contextProductId =
		context?.postType === 'product' ? context?.postId : null;

	const previewProductId = useMemo(() => {
		if (!isCurrentMode) {
			return productId;
		}
		return contextProductId || null;
	}, [isCurrentMode, productId, contextProductId]);

	// When in "current" mode with no context, fetch the most recent product as a sample.
	const [fallbackProductId, setFallbackProductId] = useState(null);

	useEffect(() => {
		if (!isCurrentMode || contextProductId) {
			setFallbackProductId(null);
			return;
		}

		apiFetch({ path: '/wc/store/v1/products?per_page=1&orderby=date' })
			.then((products) => {
				if (products?.length) {
					setFallbackProductId(products[0].id);
				}
			})
			.catch(() => {
				// No products available — fallback will remain null.
			});
	}, [isCurrentMode, contextProductId]);

	const effectiveProductId = previewProductId || fallbackProductId;

	// Fetch product data when the effective product ID changes.
	useEffect(() => {
		if (!effectiveProductId) {
			setProductData(null);
			return;
		}

		setIsLoading(true);
		setError(null);

		apiFetch({ path: `/wc/store/v1/products/${effectiveProductId}` })
			.then((data) => setProductData(data))
			.catch(() => {
				setError(
					__('Product not found or unavailable.', 'designsetgo')
				);
				setProductData(null);
			})
			.finally(() => setIsLoading(false));
	}, [effectiveProductId]);

	const blockProps = useBlockProps({
		className: 'dsgo-product-showcase-hero-wrapper',
	});

	// Determine if we're showing a sample preview (no real context available).
	const isSamplePreview = isCurrentMode && !contextProductId;

	const isSetup = !isCurrentMode && !productId;

	function ProductPanelContent() {
		if (isSetup) {
			return (
				<>
					<p>
						{__(
							'Search for a product to feature, or use the current product for single product templates.',
							'designsetgo'
						)}
					</p>
					<ProductPicker
						onSelect={(id) =>
							setAttributes({
								productId: id,
								productSource: 'manual',
							})
						}
					/>
					<Button
						variant="tertiary"
						onClick={() =>
							setAttributes({ productSource: 'current' })
						}
						__next40pxDefaultSize
						style={{ marginTop: '8px' }}
					>
						{__('Use Current Product Instead', 'designsetgo')}
					</Button>
				</>
			);
		}
		if (isCurrentMode) {
			return (
				<>
					<p>
						{__(
							'Displaying the current product from page context.',
							'designsetgo'
						)}
					</p>
					<Button
						variant="secondary"
						onClick={() =>
							setAttributes({
								productSource: 'manual',
								productId: 0,
							})
						}
						__next40pxDefaultSize
					>
						{__('Switch to Manual Selection', 'designsetgo')}
					</Button>
				</>
			);
		}
		return (
			<>
				{productData && (
					<p>
						<strong>{decodeEntities(productData.name)}</strong>
					</p>
				)}
				<Button
					variant="secondary"
					onClick={() => setAttributes({ productId: 0 })}
					__next40pxDefaultSize
					style={{ marginBottom: '8px' }}
				>
					{__('Replace Product', 'designsetgo')}
				</Button>
				<Button
					variant="tertiary"
					onClick={() => setAttributes({ productSource: 'current' })}
					__next40pxDefaultSize
				>
					{__('Use Current Product Instead', 'designsetgo')}
				</Button>
			</>
		);
	}

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="image-flip-horizontal"
						label={__('Flip Layout', 'designsetgo')}
						onClick={() =>
							setAttributes({
								layout:
									layout === 'media-left'
										? 'media-right'
										: 'media-left',
							})
						}
					/>
				</ToolbarGroup>
				{!isCurrentMode && productId > 0 && (
					<ToolbarGroup>
						<ToolbarButton
							icon="update"
							label={__('Replace Product', 'designsetgo')}
							onClick={() => setAttributes({ productId: 0 })}
						/>
					</ToolbarGroup>
				)}
			</BlockControls>

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							productSource: 'manual',
							productId: 0,
							layout: 'media-left',
							imageSize: 'large',
							showPrice: true,
							showRating: true,
							showStockStatus: true,
							showSaleBadge: true,
							showShortDescription: false,
							showAddToCart: true,
							showVariations: true,
							minHeight: '500px',
							mediaFocalPoint: { x: 0.5, y: 0.5 },
							contentVerticalAlignment: 'center',
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Product', 'designsetgo')}
						hasValue={() =>
							productSource !== 'manual' || productId !== 0
						}
						onDeselect={() =>
							setAttributes({
								productSource: 'manual',
								productId: 0,
							})
						}
						isShownByDefault
					>
						<ProductPanelContent />
					</DsgoInspectorPanel.Item>

					<DisplayOptionsPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>

					<LayoutPanel
						attributes={attributes}
						setAttributes={setAttributes}
						units={units}
						imageUrl={productData?.images?.[0]?.src}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				{isSetup && (
					<Placeholder
						icon="cart"
						label={__('Product Showcase Hero', 'designsetgo')}
					>
						<div className="dsgo-product-showcase-hero__setup">
							<p className="dsgo-product-showcase-hero__setup-label">
								{__(
									'Search for a product to feature, or use the current product for single product templates.',
									'designsetgo'
								)}
							</p>
							<ProductPicker
								className="dsgo-product-showcase-hero__setup-picker"
								onSelect={(id) =>
									setAttributes({
										productId: id,
										productSource: 'manual',
									})
								}
							/>
							<div className="dsgo-product-showcase-hero__setup-divider">
								<span>{__('or', 'designsetgo')}</span>
							</div>
							<Button
								variant="secondary"
								onClick={() =>
									setAttributes({
										productSource: 'current',
									})
								}
								__next40pxDefaultSize
							>
								{__('Use Current Product', 'designsetgo')}
							</Button>
						</div>
					</Placeholder>
				)}
				{isSamplePreview && productData && (
					<Notice
						status="info"
						isDismissible={false}
						className="dsgo-product-showcase-hero__sample-notice"
					>
						{__(
							'Preview: showing a sample product. The current product will be used on the frontend.',
							'designsetgo'
						)}
					</Notice>
				)}

				{isLoading && (
					<Placeholder
						icon="cart"
						label={__('Product Showcase Hero', 'designsetgo')}
					>
						<Spinner />
					</Placeholder>
				)}

				{error && (
					<Placeholder
						icon="cart"
						label={__('Product Showcase Hero', 'designsetgo')}
					>
						<Notice status="error" isDismissible={false}>
							{error}
						</Notice>
						{!isCurrentMode && (
							<Button
								variant="secondary"
								onClick={() => setAttributes({ productId: 0 })}
								__next40pxDefaultSize
							>
								{__('Select Another Product', 'designsetgo')}
							</Button>
						)}
					</Placeholder>
				)}

				{!isLoading && !error && productData && (
					<ProductPreview
						productData={productData}
						attributes={attributes}
					/>
				)}

				{isCurrentMode && !isLoading && !error && !productData && (
					<Placeholder
						icon="cart"
						label={__('Product Showcase Hero', 'designsetgo')}
						instructions={__(
							'No products found. Add a WooCommerce product to preview this block.',
							'designsetgo'
						)}
					/>
				)}
			</div>
		</>
	);
}
