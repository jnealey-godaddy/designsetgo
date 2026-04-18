/**
 * DsgoBlockPlaceholder
 *
 * Shared first-insert template chooser. Renders a `<Placeholder>` with a grid of
 * starter-template tiles. Selecting a tile applies its `attributes` to the block
 * and replaces inner blocks from `innerBlocks` (`InnerBlocks.Content`-style array).
 *
 * Used by every compound block that needs onboarding parity with `modal` /
 * `form-builder`. Kills silent-empty states and the "what now?" moment.
 */

import { Button, Placeholder, Icon } from '@wordpress/components';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';
import './style.scss';

/**
 * @typedef {Object} DsgoPlaceholderTemplate
 * @property {string}             name         BEM modifier slug. Required.
 * @property {string}             title        Tile title.
 * @property {string}             description  Short tile description.
 * @property {string|JSX.Element} icon         Dashicon slug or icon element.
 * @property {Object}             [attributes] Optional attributes to apply to
 *                                             the parent block.
 * @property {Array}              innerBlocks  InnerBlocks.Content-style
 *                                             template. Required and must
 *                                             contain at least one block —
 *                                             parent blocks gate the
 *                                             placeholder on
 *                                             `innerBlocks.length > 0`, so an
 *                                             empty template would lock the
 *                                             author on the chooser.
 */

/**
 * @param {Object}                    props
 * @param {string}                    props.clientId      Parent block client ID.
 * @param {Function}                  props.setAttributes Parent block setAttributes.
 * @param {string|JSX.Element}        props.icon          Header icon for the
 *                                                        placeholder.
 * @param {string}                    props.label         Header label.
 * @param {string}                    props.instructions  Helper copy.
 * @param {DsgoPlaceholderTemplate[]} props.templates     Tiles to render.
 * @param {string}                    [props.variant]     Optional BEM modifier
 *                                                        for block-specific
 *                                                        styling overrides.
 * @return {JSX.Element} Placeholder UI.
 */
export default function DsgoBlockPlaceholder({
	clientId,
	setAttributes,
	icon,
	label,
	instructions,
	templates,
	variant,
}) {
	const { replaceInnerBlocks } = useDispatch(blockEditorStore);

	const selectTemplate = (template) => {
		if (template.attributes) {
			setAttributes(template.attributes);
		}
		if (template.innerBlocks?.length) {
			const blocks = createBlocksFromInnerBlocksTemplate(
				template.innerBlocks
			);
			replaceInnerBlocks(clientId, blocks, false);
		} else if (process.env.NODE_ENV !== 'production') {
			// Parent blocks gate the placeholder on `hasInnerBlocks > 0`. A
			// template that ships only `attributes` would apply them and then
			// the placeholder would re-render, locking the author in. Catch
			// that at template-author time so it's never shipped to users.
			// eslint-disable-next-line no-console
			console.warn(
				`DsgoBlockPlaceholder: template "${template.name}" has no innerBlocks. ` +
					'Templates must seed at least one inner block or the placeholder will re-render and the author will be stuck.'
			);
		}
	};

	const rootClass = variant
		? `dsgo-block-placeholder dsgo-block-placeholder--${variant}`
		: 'dsgo-block-placeholder';

	return (
		<Placeholder
			icon={icon}
			label={label}
			instructions={instructions}
			className={rootClass}
		>
			<div className="dsgo-block-placeholder__templates">
				{templates.map((template) => (
					<Button
						key={template.name}
						className={`dsgo-block-placeholder__template dsgo-block-placeholder__template--${template.name}`}
						onClick={() => selectTemplate(template)}
						variant="secondary"
					>
						<div className="dsgo-block-placeholder__template-icon">
							<Icon icon={template.icon} size={32} />
						</div>
						<div className="dsgo-block-placeholder__template-info">
							<span className="dsgo-block-placeholder__template-title">
								{template.title}
							</span>
							{template.description && (
								<span className="dsgo-block-placeholder__template-description">
									{template.description}
								</span>
							)}
						</div>
					</Button>
				))}
			</div>
		</Placeholder>
	);
}
