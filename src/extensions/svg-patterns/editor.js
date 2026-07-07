/**
 * SVG Patterns Extension - Editor Integration
 *
 * @package
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import SvgPatternsPanel from './components/SvgPatternsPanel';
import { SUPPORTED_BLOCKS, DEFAULTS, INHERIT } from './constants';
import { getPatternBackground, PATTERNS, PATTERN_IDS } from './patterns';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { useInheritedSvgPattern } from './use-inherited-svg-pattern';

/**
 * Add SVG pattern controls to the block editor
 */
const withSvgPatternControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name } = props;

		if (!SUPPORTED_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<Fragment>
				<BlockEdit {...props} />
				<SvgPatternsPanel {...props} />
			</Fragment>
		);
	};
}, 'withSvgPatternControls');

addFilter(
	'editor.BlockEdit',
	'designsetgo/svg-pattern-controls',
	withSvgPatternControls
);

/**
 * Add SVG pattern preview styles to block wrapper in editor
 */
const addSvgPatternEditorStyles = createHigherOrderComponent(
	(BlockListBlock) => {
		return (props) => {
			const { attributes, name } = props;

			if (!SUPPORTED_BLOCKS.includes(name)) {
				return <BlockListBlock {...props} />;
			}

			const {
				dsgoSvgPatternEnabled,
				dsgoSvgPatternType,
				dsgoSvgPatternColor,
				dsgoSvgPatternOpacity,
				dsgoSvgPatternScale,
				dsgoSvgPatternFixed,
			} = attributes;

			const isInherit = dsgoSvgPatternType === INHERIT;

			// Theme preset (settings.custom.designsetgo.svgPattern), resolved
			// with fallbacks. Shared with the inspector panel via this hook.
			const inherited = useInheritedSvgPattern();

			const isActive =
				dsgoSvgPatternEnabled &&
				dsgoSvgPatternType &&
				(isInherit || PATTERNS[dsgoSvgPatternType]);

			const effectiveRawColor = isInherit
				? inherited.color
				: dsgoSvgPatternColor;

			// Resolve preset color slugs to hex values. CSS variables
			// cannot be used inside SVG data URIs because the SVG is an
			// external document that doesn't inherit the page's CSS.
			const resolvedColor = useSelect(
				(select) => {
					if (
						!effectiveRawColor ||
						typeof effectiveRawColor !== 'string'
					) {
						return DEFAULTS.color;
					}

					// Accept both var:preset|color|slug and var(--wp--preset--color--slug).
					const presetMatch = effectiveRawColor.match(
						/^var:preset\|color\|(.+)$/
					);
					const cssVarMatch = effectiveRawColor.match(
						/^var\(--wp--preset--color--(.+)\)$/
					);
					const slug = presetMatch?.[1] || cssVarMatch?.[1];
					if (!slug) {
						// Already a raw color value (hex, rgb, etc.)
						return effectiveRawColor;
					}

					const settings = select(blockEditorStore).getSettings();
					const colors = settings.colors || [];
					const found = colors.find((c) => c.slug === slug);
					return found?.color || DEFAULTS.color;
				},
				[effectiveRawColor]
			);

			// Memoize SVG generation to avoid re-encoding on every render
			const bg = useMemo(() => {
				if (!isActive) {
					return null;
				}
				const effType = isInherit ? inherited.type : dsgoSvgPatternType;
				const effOpacity = isInherit
					? inherited.opacity
					: (dsgoSvgPatternOpacity ?? DEFAULTS.opacity);
				const effScale = isInherit
					? inherited.scale
					: (dsgoSvgPatternScale ?? DEFAULTS.scale);
				return getPatternBackground(
					effType,
					resolvedColor,
					effOpacity,
					effScale
				);
			}, [
				isActive,
				isInherit,
				inherited,
				dsgoSvgPatternType,
				resolvedColor,
				dsgoSvgPatternOpacity,
				dsgoSvgPatternScale,
			]);

			if (isActive && bg) {
				const patternStyle = {
					...props.wrapperProps?.style,
					'--dsgo-svg-pattern-image': bg.backgroundImage,
					'--dsgo-svg-pattern-size': bg.backgroundSize,
				};

				if (dsgoSvgPatternFixed) {
					patternStyle['--dsgo-svg-pattern-attachment'] = 'fixed';
				}

				// Use wrapperProps for both style and class — passing className
				// as a separate prop is silently dropped when wrapperProps is present.
				const wrapperProps = {
					...props.wrapperProps,
					className: [
						props.wrapperProps?.className,
						'has-dsgo-svg-pattern',
					]
						.filter(Boolean)
						.join(' '),
					style: patternStyle,
				};

				return (
					<BlockListBlock {...props} wrapperProps={wrapperProps} />
				);
			}

			return <BlockListBlock {...props} />;
		};
	},
	'addSvgPatternEditorStyles'
);

addFilter(
	'editor.BlockListBlock',
	'designsetgo/svg-pattern-editor-styles',
	addSvgPatternEditorStyles
);

/**
 * Add SVG pattern attributes to save props
 *
 * @param {Object} extraProps Block save props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 * @return {Object} Modified props
 */
export function addSvgPatternSaveProps(extraProps, blockType, attributes) {
	const {
		dsgoSvgPatternEnabled,
		dsgoSvgPatternType,
		dsgoSvgPatternColor,
		dsgoSvgPatternOpacity,
		dsgoSvgPatternScale,
	} = attributes;

	const isInherit = dsgoSvgPatternType === INHERIT;

	if (
		!SUPPORTED_BLOCKS.includes(blockType.name) ||
		!dsgoSvgPatternEnabled ||
		!dsgoSvgPatternType ||
		(!isInherit && !PATTERN_IDS.includes(dsgoSvgPatternType))
	) {
		return extraProps;
	}

	if (isInherit) {
		return {
			...extraProps,
			className: [extraProps.className, 'has-dsgo-svg-pattern']
				.filter(Boolean)
				.join(' '),
			style: extraProps.style || {},
			'data-dsgo-svg-pattern': INHERIT,
		};
	}

	const safeOpacity =
		typeof dsgoSvgPatternOpacity === 'number'
			? dsgoSvgPatternOpacity
			: DEFAULTS.opacity;
	const safeScale =
		typeof dsgoSvgPatternScale === 'number'
			? dsgoSvgPatternScale
			: DEFAULTS.scale;

	// Only save data attributes and class — the server-side renderer
	// (SVG_Pattern_Renderer) generates the SVG data URI at render time
	// from these attributes, keeping post_content lean.
	return {
		...extraProps,
		className: [extraProps.className, 'has-dsgo-svg-pattern']
			.filter(Boolean)
			.join(' '),
		style: extraProps.style || {},
		'data-dsgo-svg-pattern': dsgoSvgPatternType,
		// Omit when unset — the server-side renderer falls back to its own
		// default color, so a hand-authored pattern can leave this off too.
		'data-dsgo-svg-pattern-color':
			convertColorToCSSVar(dsgoSvgPatternColor),
		'data-dsgo-svg-pattern-opacity': String(safeOpacity),
		'data-dsgo-svg-pattern-scale': String(safeScale),
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/svg-pattern-save-props',
	addSvgPatternSaveProps
);

/**
 * Strip legacy inline SVG pattern CSS variables from saved content.
 *
 * Older versions saved the full SVG data URI in --dsgo-svg-pattern-image
 * and --dsgo-svg-pattern-size inline styles. The server-side renderer now
 * generates these at render time, so they are no longer saved. This filter
 * normalizes old content during block validation so the editor doesn't
 * show "Block contains unexpected content" errors.
 *
 * @param {string} content   Serialized block HTML.
 * @param {Object} blockType Block type definition.
 * @return {string} Cleaned content.
 */
function stripLegacySvgPatternStyles(content, blockType) {
	if (
		!SUPPORTED_BLOCKS.includes(blockType.name) ||
		typeof content !== 'string' ||
		!content.includes('--dsgo-svg-pattern-image')
	) {
		return content;
	}

	// Remove --dsgo-svg-pattern-image:url("data:image/svg+xml,...");
	// The url() value may contain encoded parens, so match up to the closing ");
	content = content.replace(
		/--dsgo-svg-pattern-image:url\(&quot;[^&]*&quot;\);?/g,
		''
	);

	// Remove --dsgo-svg-pattern-size:<value>;
	content = content.replace(/--dsgo-svg-pattern-size:[^;"]+;?/g, '');

	// Remove --dsgo-svg-pattern-attachment:fixed;
	content = content.replace(/--dsgo-svg-pattern-attachment:fixed;?/g, '');

	// Clean up dangling semicolons and empty style attributes.
	content = content.replace(/style=";\s*/g, 'style="');
	content = content.replace(/style="\s*"/g, '');

	return content;
}

addFilter(
	'blocks.getSaveContent',
	'designsetgo/svg-pattern-strip-legacy',
	stripLegacySvgPatternStyles
);
