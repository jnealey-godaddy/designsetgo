/**
 * Flip Card Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive that supplies
 * the flip-card-specific copy and starter templates. Every template seeds
 * both faces so the card is never single-sided after selection.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import flipCardTemplates from '../templates';

export default function FlipCardPlaceholder({ clientId, setAttributes }) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="image-flip-horizontal"
			label={__('Flip Card', 'designsetgo')}
			instructions={__(
				'Pick a starting layout. Both faces are seeded so the card is immediately interactive.',
				'designsetgo'
			)}
			templates={flipCardTemplates}
			variant="flip-card"
		/>
	);
}
