import { applyFilters } from '@wordpress/hooks';
import '../../../../src/extensions/style-binding/filters';

describe( 'style-binding extension', () => {
	it( 'registers dsgoStyleBinding attribute on block registration', () => {
		const result = applyFilters(
			'blocks.registerBlockType',
			{ attributes: {} },
			'core/paragraph'
		);
		expect( result.attributes ).toHaveProperty( 'dsgoStyleBinding' );
		expect( result.attributes.dsgoStyleBinding.type ).toBe( 'object' );
	} );

	it( 'sets dsgoStyleBinding default to empty object', () => {
		const result = applyFilters(
			'blocks.registerBlockType',
			{ attributes: {} },
			'core/group'
		);
		expect( result.attributes.dsgoStyleBinding.default ).toEqual( {} );
	} );

	it( 'creates attributes object when settings.attributes is missing', () => {
		const result = applyFilters(
			'blocks.registerBlockType',
			{},
			'core/paragraph'
		);
		expect( result.attributes ).toBeDefined();
		expect( result.attributes.dsgoStyleBinding ).toBeDefined();
	} );
} );
