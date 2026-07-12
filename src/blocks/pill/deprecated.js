import { useBlockProps, RichText } from '@wordpress/block-editor';

// Shared style-transfer used by the static save() reproductions below: the
// visible pill is the inner span, so colour/background/border inline styles that
// useBlockProps.save() puts on the wrapper are moved onto `.dsgo-pill__content`.
// Clone so we never mutate the object returned by useBlockProps.save().
function splitPillStyles(blockProps) {
	const wrapperStyle = { ...(blockProps.style || {}) };
	const innerStyle = {};

	if (wrapperStyle.backgroundColor) {
		innerStyle.backgroundColor = wrapperStyle.backgroundColor;
		delete wrapperStyle.backgroundColor;
	}
	if (wrapperStyle.background) {
		innerStyle.background = wrapperStyle.background;
		delete wrapperStyle.background;
	}
	if (wrapperStyle.color) {
		innerStyle.color = wrapperStyle.color;
		delete wrapperStyle.color;
	}
	if (wrapperStyle.borderColor) {
		innerStyle.borderColor = wrapperStyle.borderColor;
		delete wrapperStyle.borderColor;
	}
	if (wrapperStyle.borderWidth) {
		innerStyle.borderWidth = wrapperStyle.borderWidth;
		delete wrapperStyle.borderWidth;
	}
	if (wrapperStyle.borderStyle) {
		innerStyle.borderStyle = wrapperStyle.borderStyle;
		delete wrapperStyle.borderStyle;
	}
	if (wrapperStyle.borderRadius) {
		innerStyle.borderRadius = wrapperStyle.borderRadius;
		delete wrapperStyle.borderRadius;
	}

	return { wrapperStyle, innerStyle };
}

// Supports for the static reproductions — mirrors the block.json supports that
// were in place while the pill was still a static block.
const staticSupports = {
	html: false,
	inserter: true,
	align: ['left', 'center', 'right'],
	alignWide: false,
	spacing: {
		padding: true,
		margin: ['top', 'bottom'],
		__experimentalDefaultControls: {
			padding: true,
		},
		__experimentalSelector: '.dsgo-pill__content',
	},
	color: {
		background: true,
		text: true,
		gradients: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
		__experimentalSelector: '.dsgo-pill__content',
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		textAlign: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
		__experimentalSelector: '.dsgo-pill__content',
		__experimentalFontFamily: true,
		__experimentalFontWeight: true,
		__experimentalFontStyle: true,
		__experimentalTextTransform: true,
		__experimentalTextDecoration: true,
		__experimentalLetterSpacing: true,
		__experimentalWritingMode: true,
	},
	__experimentalBorder: {
		color: true,
		radius: true,
		style: true,
		width: true,
		__experimentalDefaultControls: {
			radius: true,
		},
		__experimentalSelector: '.dsgo-pill__content',
	},
};

// vStatic: the last STATIC version, in use immediately before the Pill block
// became server-rendered (dynamic). Its save() baked `aligncenter` (from the old
// `align` default of "center") and `has-small-font-size` (from the old `fontSize`
// default of "small") into every wrapper. The dynamic block's save() now returns
// null, so any stored static markup would trip the editor's "Attempt Recovery"
// warning. This deprecation reproduces that markup and migrates existing content
// silently; migrate() drops the old center/small defaults so migrated pills are
// as clean as freshly inserted ones (see migrate() below).
//
// apiVersion: 2 (as with v1) disables the second getSaveContent.extraProps pass
// that would otherwise re-add the colour/border inline styles to the wrapper div
// that save() moves to the inner span.
const vStatic = {
	apiVersion: 2,
	attributes: {
		content: {
			type: 'string',
			default: '',
		},
		align: {
			type: 'string',
			default: 'center',
		},
		fontSize: {
			type: 'string',
			default: 'small',
		},
		style: {
			type: 'object',
		},
	},
	supports: staticSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Static pills always carried an alignment class (the "center" default was
		// baked by useBlockProps.save()). Match a dsgo-pill wrapper that has one —
		// the pre-align legacy (no alignment class) is handled by v1 below.
		const html = innerHTML || '';
		return (
			/class="[^"]*\bdsgo-pill\b/.test(html) &&
			(html.includes('aligncenter') ||
				html.includes('alignleft') ||
				html.includes('alignright'))
		);
	},
	save({ attributes }) {
		const { content } = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-pill',
		});

		const { wrapperStyle, innerStyle } = splitPillStyles(blockProps);
		blockProps.style = wrapperStyle;

		return (
			<div {...blockProps}>
				<RichText.Content
					tagName="span"
					className="dsgo-pill__content"
					value={content}
					style={innerStyle}
				/>
			</div>
		);
	},
	migrate(attributes) {
		// Deprecations do not cascade — a legacy static pill matches THIS entry,
		// never vAlign below, so migrate() must land on the CURRENT schema
		// (justification), not the intermediate `align` schema.
		const { align, fontSize, ...rest } = attributes;
		return {
			...rest,
			// `small` was the old baked default; drop it so no has-small-font-size
			// class is re-serialized. Explicit non-default sizes are preserved.
			...(fontSize && fontSize !== 'small' ? { fontSize } : {}),
			justification: ['left', 'center', 'right'].includes(align)
				? align
				: 'center',
		};
	},
};

// v1: the pill block had no `align` attribute in the attributes schema, so
// `useBlockProps.save()` never injected `aligncenter` even when the block
// support's alignment defaulted to "center". After commit 9f743ef6 the
// attribute was added with `default: "center"`, causing current save() to
// emit `aligncenter` — which mismatches all legacy patterns that omit the
// attribute. v1 predates `align` entirely, so every v1 pill was centred;
// migrate() lands directly on `justification: "center"` (see below).
//
// Two extra rules needed for inline deprecated block types:
//
// 1. `style` must be listed explicitly. WP runs blocks.registerBlockType
//    filters on each deprecated entry during registration, so extension
//    attributes (dsgoAnimation*, dsgoHideOn*, …) are auto-added. But the
//    style-engine hook only adds `style: {type:'object'}` when the key is
//    absent — that happens during the filter pass — so the deprecated
//    block type *does* end up with a `style` attribute automatically.
//    Listing it here is harmless but makes the intent explicit.
//
// 2. `apiVersion: 2` is required. Without it, WP's getSaveElement()
//    applies the `blocks.getSaveContent.extraProps` filter a second time
//    after save() returns (apiVersion ≤ 1 path), re-adding the inline
//    colour/border styles to the wrapper div that our save() carefully
//    moved to the inner span. apiVersion: 2 disables that second pass so
//    only the getBlockProps() call inside save() runs the filter.
const v1 = {
	apiVersion: 2,
	attributes: {
		// No `align` attribute — matches pre-9f743ef6 schema where align
		// lived only in supports, not in attributes.
		content: {
			type: 'string',
			default: '',
		},
		fontSize: {
			type: 'string',
			default: 'small',
		},
		style: {
			type: 'object',
		},
	},
	supports: {
		html: false,
		inserter: true,
		align: ['left', 'center', 'right'],
		alignWide: false,
		spacing: {
			padding: true,
			margin: ['top', 'bottom'],
			__experimentalDefaultControls: {
				padding: true,
			},
			__experimentalSelector: '.dsgo-pill__content',
		},
		color: {
			background: true,
			text: true,
			gradients: true,
			__experimentalDefaultControls: {
				background: true,
				text: true,
			},
			__experimentalSelector: '.dsgo-pill__content',
		},
		typography: {
			fontSize: true,
			lineHeight: true,
			textAlign: true,
			__experimentalDefaultControls: {
				fontSize: true,
			},
			__experimentalSelector: '.dsgo-pill__content',
			__experimentalFontFamily: true,
			__experimentalFontWeight: true,
			__experimentalFontStyle: true,
			__experimentalTextTransform: true,
			__experimentalTextDecoration: true,
			__experimentalLetterSpacing: true,
			__experimentalWritingMode: true,
		},
		__experimentalBorder: {
			color: true,
			radius: true,
			style: true,
			width: true,
			__experimentalDefaultControls: {
				radius: true,
			},
			__experimentalSelector: '.dsgo-pill__content',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Legacy saves lack `aligncenter` even when no explicit align was
		// stored (the default "center" was not yet an attribute). Current
		// saves always emit `aligncenter` for the default. Match only the
		// legacy case: dsgo-prefix pill whose wrapper has no align class.
		const html = innerHTML || '';
		return (
			/class="[^"]*\bdsgo-pill\b/.test(html) &&
			!html.includes('aligncenter') &&
			!html.includes('alignleft') &&
			!html.includes('alignright')
		);
	},
	save({ attributes }) {
		const { content } = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-pill',
		});

		// Extract color/background styles from blockProps to apply to
		// inner span (same style-transfer logic as current save.js).
		// Clone so we never mutate the object returned by useBlockProps.save().
		const wrapperStyle = { ...(blockProps.style || {}) };
		const innerStyle = {};

		if (wrapperStyle.backgroundColor) {
			innerStyle.backgroundColor = wrapperStyle.backgroundColor;
			delete wrapperStyle.backgroundColor;
		}
		if (wrapperStyle.background) {
			innerStyle.background = wrapperStyle.background;
			delete wrapperStyle.background;
		}
		if (wrapperStyle.color) {
			innerStyle.color = wrapperStyle.color;
			delete wrapperStyle.color;
		}
		if (wrapperStyle.borderColor) {
			innerStyle.borderColor = wrapperStyle.borderColor;
			delete wrapperStyle.borderColor;
		}
		if (wrapperStyle.borderWidth) {
			innerStyle.borderWidth = wrapperStyle.borderWidth;
			delete wrapperStyle.borderWidth;
		}
		if (wrapperStyle.borderStyle) {
			innerStyle.borderStyle = wrapperStyle.borderStyle;
			delete wrapperStyle.borderStyle;
		}
		if (wrapperStyle.borderRadius) {
			innerStyle.borderRadius = wrapperStyle.borderRadius;
			delete wrapperStyle.borderRadius;
		}

		blockProps.style = wrapperStyle;

		return (
			<div {...blockProps}>
				<RichText.Content
					tagName="span"
					className="dsgo-pill__content"
					value={content}
					style={innerStyle}
				/>
			</div>
		);
	},
	migrate(attributes) {
		// v1 predates `align` entirely (no attribute in its schema), so there is
		// nothing to read — every v1 pill was rendered centred. Land on the
		// current schema's default directly.
		return { ...attributes, justification: 'center' };
	},
};

/**
 * v3 — `align` replaced by `justification`.
 *
 * The pill was already dynamic here (save() === null), so the stored markup is a
 * self-closing comment and there is no HTML to reproduce. Only the attribute
 * schema changed: `align: left|center|right` became `justification`, because
 * core's constrained layout excludes aligned blocks from the content-size cap.
 *
 * `supports` MUST declare the full support set (color / border / spacing /
 * typography), not just `align`. WP re-runs the `blocks.registerBlockType`
 * filters (color.js, border.js, spacing.js, typography.js, align.js) against
 * EACH deprecation entry at registration time, and those filters add
 * `backgroundColor` / `textColor` / `gradient` / `borderColor` / `fontSize` /
 * `style` to `attributes` only when the matching support is present. A
 * `supports` block that declares only `align` — as an earlier version of this
 * entry did — silently tells WordPress the old pill had no colour, border or
 * typography supports, so `getBlockAttributes()` strips those attributes
 * before `migrate()` ever runs, permanently discarding stored styling. This
 * is the same full set `staticSupports` already declares for vStatic/v1 (the
 * dynamic pill at this point in history had identical color/border/spacing/
 * typography config, just without a static `save()`), so it's reused here
 * rather than duplicated. `__experimentalSkipSerialization` (added later)
 * only affects serialization, not attribute registration, so its absence
 * here doesn't change which attributes survive.
 */
const vAlign = {
	attributes: {
		content: { type: 'string', default: '' },
	},
	supports: staticSupports,
	isEligible(attributes) {
		return Object.prototype.hasOwnProperty.call(attributes, 'align');
	},
	save() {
		return null;
	},
	migrate(attributes) {
		const { align, ...rest } = attributes;
		return {
			...rest,
			justification: ['left', 'center', 'right'].includes(align)
				? align
				: 'center',
		};
	},
};

export default [vAlign, vStatic, v1];
