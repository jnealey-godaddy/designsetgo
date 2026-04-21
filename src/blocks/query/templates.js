import { __ } from '@wordpress/i18n';
import variations from './variations';

const blank = {
	name: 'blank',
	title: __('Blank', 'designsetgo'),
	description: __(
		'Start with a single item template and build from scratch.',
		'designsetgo'
	),
	icon: 'welcome-add-page',
	attributes: {},
	innerBlocks: [
		[
			'designsetgo/section',
			{},
			[
				['core/post-featured-image', { isLink: true }],
				['core/post-title', { level: 3, isLink: true }],
				['core/post-excerpt'],
			],
		],
	],
};

const queryTemplates = [blank, ...variations];

export default queryTemplates;
