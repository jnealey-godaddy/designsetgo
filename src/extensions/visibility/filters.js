import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import VisibilityPanel from './VisibilityPanel';
import evaluateRules from './evaluateRules';

const BLOCKED = new Set([
	'core/freeform',
	'core/missing',
	'core/template-part',
]);

function addVisibilityAttribute(settings, name) {
	if (BLOCKED.has(name)) {
		return settings;
	}
	return {
		...settings,
		attributes: {
			...(settings.attributes ?? {}),
			dsgoVisibility: { type: 'object', default: null },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/visibility/add-attribute',
	addVisibilityAttribute
);

const withVisibilityPanel = createHigherOrderComponent(
	(BlockEdit) => (props) => {
		if (BLOCKED.has(props.name)) {
			return <BlockEdit {...props} />;
		}
		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls group="advanced">
					<PanelBody
						title={__('Visibility', 'designsetgo')}
						initialOpen={!!props.attributes.dsgoVisibility}
					>
						<VisibilityPanel
							value={props.attributes.dsgoVisibility}
							onChange={(value) =>
								props.setAttributes({ dsgoVisibility: value })
							}
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	},
	'withDsgoVisibilityPanel'
);

addFilter(
	'editor.BlockEdit',
	'designsetgo/visibility/inspector',
	withVisibilityPanel
);

/**
 * Gate HOC for editor.BlockListBlock.
 *
 * Short-circuits rendering (returns null) when:
 *   1. The block carries a dsgoVisibility attribute, AND
 *   2. The block context explicitly provides `designsetgo/itemIndex`
 *      (meaning we are inside a query-item preview, not the main canvas), AND
 *   3. evaluateRules() returns false for the current item context.
 */
const withVisibilityGate = createHigherOrderComponent(
	(BlockListBlock) => (props) => {
		const visibility = props.attributes?.dsgoVisibility;
		// props.context is populated by the editor.BlockListBlock filter in
		// Gutenberg >= 16.x (WP 6.5+). It carries any context values declared
		// via `usesContext` in the block's block.json.
		const ctx = props.context || {};

		// Only gate inside query-item previews. If the item index context key is
		// absent we are on the main canvas and must not hide anything.
		if (
			ctx['designsetgo/itemIndex'] === null ||
			ctx['designsetgo/itemIndex'] === undefined
		) {
			return <BlockListBlock {...props} />;
		}

		const evalCtx = {
			index: ctx['designsetgo/itemIndex'],
			meta: ctx['designsetgo/itemMeta'] || {},
			terms: ctx['designsetgo/itemTerms'] || {},
			postId: ctx.postId,
			postType: ctx.postType,
			isAuthenticated: ctx['designsetgo/isAuthenticated'] || false,
		};

		if (!evaluateRules(visibility, evalCtx)) {
			// Return a hidden placeholder rather than null so the React tree stays
			// stable. Returning null from editor.BlockListBlock can break tree
			// diffing because Gutenberg expects a stable DOM node per slot.
			return (
				<div
					className="dsgo-visibility-hidden"
					style={{ display: 'none' }}
					aria-hidden="true"
				/>
			);
		}

		return <BlockListBlock {...props} />;
	},
	'withDsgoVisibilityGate'
);

addFilter(
	'editor.BlockListBlock',
	'designsetgo/visibility/gate',
	withVisibilityGate
);
