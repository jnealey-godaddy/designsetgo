import { __ } from '@wordpress/i18n';
import { DEFAULT_TEMPLATE } from './edit-template';
import variations from './variations';

const minimal = {
	name: 'minimal',
	title: __('Minimal', 'designsetgo'),
	description: __(
		'Start with a single item template and build from scratch.',
		'designsetgo'
	),
	icon: 'welcome-add-page',
	attributes: {},
	innerBlocks: DEFAULT_TEMPLATE,
};

const queryTemplates = [minimal, ...variations];

export default queryTemplates;
