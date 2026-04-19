import variations from '../../../../src/blocks/query/variations';

describe( 'Query block variations', () => {
	it( 'ships exactly six variations', () => {
		expect( variations ).toHaveLength( 6 );
	} );

	it.each( variations.map( ( v ) => [ v.name, v ] ) )( '%s — has required shape', ( _name, v ) => {
		expect( v.name ).toBeTruthy();
		expect( v.title ).toBeTruthy();
		expect( v.description ).toBeTruthy();
		expect( v.icon ).toBeTruthy();
		expect( v.attributes ).toMatchObject( { source: 'posts' } );
		expect( Array.isArray( v.innerBlocks ) ).toBe( true );
		expect( v.innerBlocks.length ).toBeGreaterThan( 0 );
		expect( v.scope ).toContain( 'inserter' );
	} );

	it( 'has unique names', () => {
		const names = variations.map( ( v ) => v.name );
		expect( new Set( names ).size ).toBe( 6 );
	} );

	it( 'related-posts variation has excludeCurrent: true', () => {
		const v = variations.find( ( x ) => x.name === 'related-posts' );
		expect( v.attributes.excludeCurrent ).toBe( true );
	} );

	it( 'events variation orders ASC', () => {
		const v = variations.find( ( x ) => x.name === 'events' );
		expect( v.attributes.order ).toBe( 'ASC' );
	} );
} );
