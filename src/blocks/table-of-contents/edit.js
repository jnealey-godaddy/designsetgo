import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useEffect, useMemo } from '@wordpress/element';
import classnames from 'classnames';
import { useHeadingScanner } from './components/useHeadingScanner';
import { renderHierarchical } from './components/HierarchicalList';
import {
	HeadingLevelsPanel,
	DisplaySettingsPanel,
	TitleSettingsPanel,
	ScrollSettingsPanel,
} from './components/InspectorPanels';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

export default function Edit({ attributes, setAttributes, clientId }) {
	const {
		uniqueId,
		includeH2,
		includeH3,
		includeH4,
		includeH5,
		includeH6,
		displayMode,
		listStyle,
		showTitle,
		titleText,
		scrollSmooth,
		stickyOffset,
		linkColor,
		activeLinkColor,
	} = attributes;

	// Generate unique ID on mount
	useEffect(() => {
		if (!uniqueId) {
			setAttributes({ uniqueId: clientId.substring(0, 8) });
		}
	}, [uniqueId, clientId, setAttributes]);

	// Use custom hook to scan editor for headings
	const previewHeadings = useHeadingScanner({
		includeH2,
		includeH3,
		includeH4,
		includeH5,
		includeH6,
	});

	// Get color settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Check if at least one heading level is selected
	const hasSelectedLevels =
		includeH2 || includeH3 || includeH4 || includeH5 || includeH6;

	// Styles using CSS custom properties (only set if user has chosen colors)
	const customStyles = {};
	if (linkColor) {
		customStyles['--dsgo-toc-link-color'] = convertColorToCSSVar(linkColor);
	}
	if (activeLinkColor) {
		customStyles['--dsgo-toc-active-link-color'] =
			convertColorToCSSVar(activeLinkColor);
	}
	if (stickyOffset) {
		customStyles['--dsgo-toc-sticky-offset'] = `${stickyOffset}px`;
	}

	const blockProps = useBlockProps({
		className: classnames('dsgo-table-of-contents', {
			'dsgo-table-of-contents--hierarchical':
				displayMode === 'hierarchical',
			'dsgo-table-of-contents--flat': displayMode === 'flat',
			'dsgo-table-of-contents--ordered': listStyle === 'ordered',
			'dsgo-table-of-contents--smooth': scrollSmooth,
		}),
		style: customStyles,
	});

	// Memoize the TOC preview content to avoid recalculating hierarchy on every render
	const tocContent = useMemo(() => {
		if (!hasSelectedLevels) {
			return (
				<Notice status="warning" isDismissible={false}>
					{__(
						'Please select at least one heading level to display.',
						'designsetgo'
					)}
				</Notice>
			);
		}

		if (previewHeadings.length === 0) {
			return (
				<Notice status="info" isDismissible={false}>
					{__(
						'No headings found. Add heading blocks to your page to see the table of contents.',
						'designsetgo'
					)}
				</Notice>
			);
		}

		const ListTag = listStyle === 'ordered' ? 'ol' : 'ul';

		if (displayMode === 'flat') {
			return (
				<ListTag className="dsgo-table-of-contents__list">
					{previewHeadings.map((heading, idx) => (
						<li key={idx} className="dsgo-table-of-contents__item">
							<a
								href={`#${heading.id}`}
								className="dsgo-table-of-contents__link"
							>
								{heading.text}
							</a>
						</li>
					))}
				</ListTag>
			);
		}

		// Hierarchical mode
		const minLevel = Math.min(...previewHeadings.map((h) => h.level));
		return (
			<ListTag className="dsgo-table-of-contents__list">
				{renderHierarchical(previewHeadings, minLevel, ListTag)}
			</ListTag>
		);
	}, [previewHeadings, displayMode, listStyle, hasSelectedLevels]);

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							includeH2: true,
							includeH3: true,
							includeH4: false,
							includeH5: false,
							includeH6: false,
							displayMode: 'hierarchical',
							listStyle: 'unordered',
							showTitle: true,
							titleText: 'Table of Contents',
							scrollSmooth: true,
							scrollOffset: 0,
							stickyOffset: 0,
						})
					}
				>
					<HeadingLevelsPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>
					<DisplaySettingsPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>
					<TitleSettingsPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>
					<ScrollSettingsPanel
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
							label: __('Link Color', 'designsetgo'),
							colorValue: decodeColorValue(
								linkColor,
								colorGradientSettings
							),
							onColorChange: (value) =>
								setAttributes({
									linkColor:
										encodeColorValue(
											value,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Active Link Color', 'designsetgo'),
							colorValue: decodeColorValue(
								activeLinkColor,
								colorGradientSettings
							),
							onColorChange: (value) =>
								setAttributes({
									activeLinkColor:
										encodeColorValue(
											value,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					panelId={clientId}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...blockProps}>
				<div className="dsgo-table-of-contents__content">
					{showTitle && (
						<div className="dsgo-table-of-contents__title">
							{titleText}
						</div>
					)}
					{tocContent}
				</div>
			</div>
		</>
	);
}
