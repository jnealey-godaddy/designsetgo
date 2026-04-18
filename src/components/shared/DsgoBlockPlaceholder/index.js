/**
 * DsgoBlockPlaceholder
 *
 * First-insert wizard for compound blocks. Modeled directly on the proven
 * pattern in src/blocks/modal/components/ModalPlaceholder.js and
 * src/blocks/form-builder/components/FormBuilderPlaceholder.js.
 *
 * Theme 1 of the editor UX design migrates ~8 compound blocks onto this
 * component to give every block a consistent onboarding experience.
 *
 * Usage:
 *
 *   <DsgoBlockPlaceholder
 *     icon="block-default"
 *     label={__('Tabs', 'designsetgo')}
 *     instructions={__('Choose a starting layout.', 'designsetgo')}
 *     variations={[
 *       { name: 'horizontal', title: 'Horizontal', description: '...', icon: 'align-center' },
 *     ]}
 *     onSelect={(variation) => {
 *       setAttributes(variation.attributes);
 *       replaceInnerBlocks(clientId, createBlocksFromInnerBlocksTemplate(variation.innerBlocks));
 *     }}
 *   />
 */
import { Placeholder, Button, Icon } from '@wordpress/components';

export function DsgoBlockPlaceholder({
	icon,
	label,
	instructions,
	variations,
	onSelect,
	className = '',
}) {
	return (
		<Placeholder
			icon={icon}
			label={label}
			instructions={instructions}
			className={`dsgo-block-placeholder ${className}`.trim()}
		>
			{variations.length > 0 && (
				<div className="dsgo-block-placeholder__variations">
					{variations.map((variation) => (
						<Button
							key={variation.name}
							className={`dsgo-block-placeholder__variation dsgo-block-placeholder__variation--${variation.name}`}
							onClick={() => onSelect(variation)}
							variant="secondary"
						>
							{variation.icon && (
								<Icon icon={variation.icon} size={32} />
							)}
							<span className="dsgo-block-placeholder__variation-title">
								{variation.title}
							</span>
							{variation.description && (
								<span className="dsgo-block-placeholder__variation-description">
									{variation.description}
								</span>
							)}
						</Button>
					))}
				</div>
			)}
		</Placeholder>
	);
}
