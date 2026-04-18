/**
 * Form Builder Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive. The "Blank"
 * template seeds a single field so authors who skip the chooser still land in a
 * working form rather than an empty container.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import { formBuilderTemplates } from '../templates';

export default function FormBuilderPlaceholder({ clientId, setAttributes }) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="feedback"
			label={__('Form Builder', 'designsetgo')}
			instructions={__(
				'Pick a starting template or begin with a blank form.',
				'designsetgo'
			)}
			templates={formBuilderTemplates}
			variant="form-builder"
		/>
	);
}
