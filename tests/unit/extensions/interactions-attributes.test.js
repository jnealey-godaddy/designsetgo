import { applyFilters } from '@wordpress/hooks';
import '../../../src/extensions/interactions/attributes';
import '../../../src/extensions/interactions/save-props';

const settingsFor = (name) =>
	applyFilters(
		'blocks.registerBlockType',
		{ name, attributes: { existing: { type: 'string' } } },
		name
	);

const savePropsFor = (attributes) =>
	applyFilters(
		'blocks.getSaveContent.extraProps',
		{},
		{ name: 'core/group' },
		attributes
	);

describe('interactions attribute registration', () => {
	it('adds dsgoInteractions to a normal block', () => {
		const settings = settingsFor('core/group');
		expect(settings.attributes.dsgoInteractions).toEqual({
			type: 'array',
			default: [],
		});
	});

	it('preserves existing attributes', () => {
		expect(settingsFor('core/group').attributes.existing).toEqual({
			type: 'string',
		});
	});

	it('skips core/freeform', () => {
		expect(
			settingsFor('core/freeform').attributes.dsgoInteractions
		).toBeUndefined();
	});

	it('skips core-embed blocks', () => {
		expect(
			settingsFor('core-embed/youtube').attributes.dsgoInteractions
		).toBeUndefined();
	});

	it('emits no data attribute when there are no interactions', () => {
		expect(savePropsFor({ dsgoInteractions: [] })).toEqual({});
	});

	it('emits no data attribute when the value is missing', () => {
		expect(savePropsFor({})).toEqual({});
	});

	it('serialises interactions as JSON', () => {
		const interactions = [
			{ id: 'a', trigger: 'click', action: 'toggleClass', value: 'x' },
		];
		expect(
			savePropsFor({ dsgoInteractions: interactions })[
				'data-dsgo-interactions'
			]
		).toBe(JSON.stringify(interactions));
	});

	it('preserves other save props', () => {
		const props = applyFilters(
			'blocks.getSaveContent.extraProps',
			{ className: 'keep-me' },
			{ name: 'core/group' },
			{ dsgoInteractions: [{ id: 'a' }] }
		);
		expect(props.className).toBe('keep-me');
	});
});
