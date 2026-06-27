import { useBlockProps, RichText } from '@wordpress/block-editor';

// v1: the pill block had no `align` attribute in the attributes schema, so
// `useBlockProps.save()` never injected `aligncenter` even when the block
// support's alignment defaulted to "center". After commit 9f743ef6 the
// attribute was added with `default: "center"`, causing current save() to
// emit `aligncenter` — which mismatches all legacy patterns that omit the
// attribute. Markup-only change → passthrough migrate.
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
		return attributes;
	},
};

export default [v1];
