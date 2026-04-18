/**
 * Tabs Placeholder
 *
 * Thin wrapper around the shared DsgoBlockPlaceholder primitive that supplies
 * the tabs-specific copy and starter templates.
 */

import { __ } from '@wordpress/i18n';
import DsgoBlockPlaceholder from '../../../components/shared/DsgoBlockPlaceholder';
import tabsTemplates from '../templates';

export default function TabsPlaceholder({ clientId, setAttributes }) {
	return (
		<DsgoBlockPlaceholder
			clientId={clientId}
			setAttributes={setAttributes}
			icon="editor-table"
			label={__('Tabs', 'designsetgo')}
			instructions={__(
				'Pick a starting layout for your tabbed content.',
				'designsetgo'
			)}
			templates={tabsTemplates}
			variant="tabs"
		/>
	);
}
