/**
 * Sticky Sections Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import stickySectionsTemplates from '../templates';

export default function StickySectionsPlaceholder({ clientId, setAttributes }) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="sticky"
			label={__('Sticky Sections', 'designsetgo')}
			instructions={__(
				'Choose a starting layout for your sticky stacking sections.',
				'designsetgo'
			)}
			templates={stickySectionsTemplates}
			variant="sticky-sections"
		/>
	);
}
