/**
 * Scroll Slides Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import scrollSlidesTemplates from '../templates';

export default function ScrollSlidesPlaceholder({ clientId, setAttributes }) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="slides"
			label={__('Scroll Slides', 'designsetgo')}
			instructions={__(
				'Choose a starting layout for your scroll-pinned slideshow.',
				'designsetgo'
			)}
			templates={scrollSlidesTemplates}
			variant="scroll-slides"
		/>
	);
}
