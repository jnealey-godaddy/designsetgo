import { applyFilters } from '@wordpress/hooks';
import '../../../../src/extensions/visibility';

describe('dsgoVisibility attribute filter', () => {
	it('adds the attribute to an allowed block', () => {
		const settings = applyFilters(
			'blocks.registerBlockType',
			{ attributes: {} },
			'core/paragraph'
		);
		expect(settings.attributes.dsgoVisibility).toEqual({
			type: 'object',
			default: null,
		});
	});

	it('leaves unsupported blocks untouched', () => {
		const settings = applyFilters(
			'blocks.registerBlockType',
			{ attributes: {} },
			'core/freeform'
		);
		expect(settings.attributes.dsgoVisibility).toBeUndefined();
	});
});
