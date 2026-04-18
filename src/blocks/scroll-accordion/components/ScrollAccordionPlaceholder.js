/**
 * Scroll Accordion Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive that supplies
 * the scroll-accordion-specific copy and starter templates.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import scrollAccordionTemplates from '../templates';

export default function ScrollAccordionPlaceholder({
	clientId,
	setAttributes,
}) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="layout"
			label={__('Scroll Accordion', 'designsetgo')}
			instructions={__(
				'Pick a starting layout for your sticky-stacking cards.',
				'designsetgo'
			)}
			templates={scrollAccordionTemplates}
			variant="scroll-accordion"
		/>
	);
}
