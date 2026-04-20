import { addFilter } from '@wordpress/hooks';

const BLOCKED = new Set([
	'core/freeform',
	'core/missing',
	'core/template-part',
]);

function addVisibilityAttribute(settings, name) {
	if (BLOCKED.has(name)) return settings;
	return {
		...settings,
		attributes: {
			...(settings.attributes ?? {}),
			dsgoVisibility: { type: 'object', default: null },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/visibility/add-attribute',
	addVisibilityAttribute
);
