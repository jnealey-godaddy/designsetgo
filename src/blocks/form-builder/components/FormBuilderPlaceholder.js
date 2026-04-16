/**
 * Form Builder Placeholder
 *
 * First-insert template chooser. Skippable — "Blank" seeds a minimal field
 * so authors can always bail out and build from scratch.
 */

import { __ } from '@wordpress/i18n';
import { Button, Placeholder, Icon } from '@wordpress/components';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';
import { formBuilderTemplates } from '../templates';
import './form-builder-placeholder.scss';

export default function FormBuilderPlaceholder({ clientId, setAttributes }) {
	const { replaceInnerBlocks } = useDispatch('core/block-editor');

	const selectTemplate = (template) => {
		if (template.attributes) {
			setAttributes(template.attributes);
		}
		if (template.innerBlocks?.length) {
			const blocks = createBlocksFromInnerBlocksTemplate(
				template.innerBlocks
			);
			replaceInnerBlocks(clientId, blocks, false);
		}
	};

	return (
		<Placeholder
			icon="feedback"
			label={__('Form Builder', 'designsetgo')}
			instructions={__(
				'Pick a starting template or begin with a blank form.',
				'designsetgo'
			)}
			className="dsgo-form-builder-placeholder"
		>
			<div className="dsgo-form-builder-placeholder__templates">
				{formBuilderTemplates.map((template) => (
					<Button
						key={template.name}
						className={`dsgo-form-builder-placeholder__template dsgo-form-builder-placeholder__template--${template.name}`}
						onClick={() => selectTemplate(template)}
						variant="secondary"
					>
						<div className="dsgo-form-builder-placeholder__template-icon">
							<Icon icon={template.icon} size={32} />
						</div>
						<div className="dsgo-form-builder-placeholder__template-info">
							<span className="dsgo-form-builder-placeholder__template-title">
								{template.title}
							</span>
							<span className="dsgo-form-builder-placeholder__template-description">
								{template.description}
							</span>
						</div>
					</Button>
				))}
			</div>
		</Placeholder>
	);
}
