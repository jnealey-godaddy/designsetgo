/**
 * Breadcrumbs Block - Edit Component
 *
 * Shows a preview of breadcrumbs in the editor based on current page context.
 */
/* eslint-disable no-nested-ternary, jsx-a11y/anchor-is-valid */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	JustifyContentControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import classnames from 'classnames';
import { useMemo, Fragment } from '@wordpress/element';
import { DisplaySettingsPanel } from './components/InspectorPanels';
import { useBreadcrumbData } from './hooks/useBreadcrumbData';

/**
 * Get separator character based on attributes
 *
 * @param {string} separator - Separator type
 * @return {string} Separator character
 */
function getSeparatorChar(separator) {
	const separators = {
		slash: '/',
		chevron: '›',
		greater: '>',
		bullet: '•',
		'arrow-right': '→',
	};
	return separators[separator] || '/';
}

export default function Edit({ attributes, setAttributes, clientId }) {
	const {
		showHome,
		homeText,
		separator,
		showCurrent,
		linkCurrent,
		prefixText,
		contentJustification,
	} = attributes;

	// Get color settings for ColorGradientSettingsDropdown
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Get current post/page data from editor
	const { postTitle, postType, postParents, postCategories, isEditorReady } =
		useBreadcrumbData();

	// Build preview breadcrumb trail
	const previewTrail = useMemo(() => {
		const trail = [];

		// Add home link (always a link)
		if (showHome) {
			trail.push({
				title: homeText,
				isCurrent: false,
			});
		}

		// Add parent pages for pages (always links)
		if (postType === 'page' && postParents.length > 0) {
			postParents.forEach((parent) => {
				trail.push({
					title: parent.title,
					isCurrent: false,
				});
			});
		}

		// Add categories for posts (always links)
		if (postType === 'post' && postCategories.length > 0) {
			postCategories.forEach((category) => {
				trail.push({
					title: category.title,
					isCurrent: false,
				});
			});
		}

		// Add current page (link only if linkCurrent is true)
		if (showCurrent && postTitle) {
			trail.push({
				title: postTitle,
				isCurrent: true,
				linked: linkCurrent,
			});
		}

		return trail;
	}, [
		showHome,
		homeText,
		showCurrent,
		linkCurrent,
		postTitle,
		postType,
		postParents,
		postCategories,
	]);

	const separatorChar = getSeparatorChar(separator);

	const justifyClass =
		contentJustification && contentJustification !== 'left'
			? `is-content-justification-${contentJustification}`
			: '';

	const blockProps = useBlockProps({
		className: classnames('dsgo-breadcrumbs', justifyClass),
	});

	return (
		<>
			<BlockControls group="block">
				<JustifyContentControl
					allowedControls={['left', 'center', 'right']}
					value={contentJustification}
					onChange={(value) =>
						setAttributes({ contentJustification: value ?? 'left' })
					}
				/>
			</BlockControls>

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							showHome: true,
							homeText: 'Home',
							separator: 'slash',
							showCurrent: true,
							linkCurrent: false,
							prefixText: '',
							hideOnHome: true,
						})
					}
				>
					<DisplaySettingsPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</DsgoInspectorPanel>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					__experimentalIsRenderedInSidebar
					settings={[
						{
							label: __('Link', 'designsetgo'),
							colorValue:
								attributes.style?.elements?.link?.color?.text,
							onColorChange: (newColor) => {
								setAttributes({
									style: {
										...attributes.style,
										elements: {
											...attributes.style?.elements,
											link: {
												...attributes.style?.elements
													?.link,
												color: {
													text: newColor,
												},
											},
										},
									},
								});
							},
							enableAlpha: true,
						},
						{
							label: __('Link Hover', 'designsetgo'),
							colorValue:
								attributes.style?.elements?.link?.[':hover']
									?.color?.text,
							onColorChange: (newColor) => {
								setAttributes({
									style: {
										...attributes.style,
										elements: {
											...attributes.style?.elements,
											link: {
												...attributes.style?.elements
													?.link,
												':hover': {
													color: {
														text: newColor,
													},
												},
											},
										},
									},
								});
							},
							enableAlpha: true,
						},
					]}
					panelId={clientId}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<nav {...blockProps}>
				{!isEditorReady ? (
					<Notice status="info" isDismissible={false}>
						{__('Loading breadcrumbs preview…', 'designsetgo')}
					</Notice>
				) : previewTrail.length === 0 ? (
					<Notice status="warning" isDismissible={false}>
						{__(
							'No breadcrumbs to display. Enable "Show home link" or "Show current page" in the block settings.',
							'designsetgo'
						)}
					</Notice>
				) : (
					<>
						{prefixText && (
							<span className="dsgo-breadcrumbs__prefix">
								{prefixText}
							</span>
						)}

						<ol className="dsgo-breadcrumbs__list">
							{previewTrail.map((item, index) => (
								<Fragment key={index}>
									<li
										className={classnames(
											'dsgo-breadcrumbs__item',
											{
												'dsgo-breadcrumbs__item--current':
													item.isCurrent,
											}
										)}
									>
										{!item.isCurrent || item.linked ? (
											<a
												href="#"
												className="dsgo-breadcrumbs__link"
												onClick={(e) =>
													e.preventDefault()
												}
											>
												{item.title}
											</a>
										) : (
											<span className="dsgo-breadcrumbs__text">
												{item.title}
											</span>
										)}
									</li>

									{index < previewTrail.length - 1 && (
										<li
											className="dsgo-breadcrumbs__separator"
											aria-hidden="true"
										>
											{separatorChar}
										</li>
									)}
								</Fragment>
							))}
						</ol>
					</>
				)}
			</nav>
		</>
	);
}
