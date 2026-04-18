/**
 * Image Accordion Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive that supplies
 * the image-accordion-specific copy and starter templates.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import imageAccordionTemplates from '../templates';

export default function ImageAccordionPlaceholder({ clientId, setAttributes }) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="format-gallery"
			label={__('Image Accordion', 'designsetgo')}
			instructions={__(
				'Pick a starting layout for your image accordion.',
				'designsetgo'
			)}
			templates={imageAccordionTemplates}
			variant="image-accordion"
		/>
	);
}
