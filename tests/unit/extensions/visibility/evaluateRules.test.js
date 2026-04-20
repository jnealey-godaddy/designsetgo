import evaluateRules from '../../../../src/extensions/visibility/evaluateRules';

describe( 'evaluateRules', () => {
	it( 'defaults visible when rules null', () => {
		expect( evaluateRules( null, { postId: 1 } ) ).toBe( true );
	} );

	it( 'defaults visible when rules has empty array', () => {
		expect( evaluateRules( { operator: 'AND', rules: [] }, {} ) ).toBe( true );
	} );

	it( 'meta equals', () => {
		const ctx = { meta: { featured: '1' } };
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'featured', op: 'equals', value: '1' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'featured', op: 'equals', value: '0' } ] }, ctx ) ).toBe( false );
	} );

	it( 'meta not_equals', () => {
		const ctx = { meta: { status: 'active' } };
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'status', op: 'not_equals', value: 'inactive' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'status', op: 'not_equals', value: 'active' } ] }, ctx ) ).toBe( false );
	} );

	it( 'meta contains', () => {
		const ctx = { meta: { title: 'Hello World' } };
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'title', op: 'contains', value: 'world' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'title', op: 'contains', value: 'foo' } ] }, ctx ) ).toBe( false );
	} );

	it( 'meta empty / not_empty', () => {
		const ctx = { meta: { filled: 'yes', blank: '' } };
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'blank', op: 'empty' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'filled', op: 'not_empty' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'meta', key: 'filled', op: 'empty' } ] }, ctx ) ).toBe( false );
	} );

	it( 'index equals', () => {
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'index', op: 'equals', value: 0 } ] }, { index: 0 } ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'index', op: 'equals', value: 0 } ] }, { index: 1 } ) ).toBe( false );
	} );

	it( 'index gt / lt', () => {
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'index', op: 'gt', value: 2 } ] }, { index: 5 } ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'index', op: 'lt', value: 3 } ] }, { index: 1 } ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'index', op: 'gt', value: 5 } ] }, { index: 3 } ) ).toBe( false );
	} );

	it( 'taxonomy has / not_has', () => {
		const ctx = { terms: { category: [ 'news', 'featured' ] } };
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'taxonomy', taxonomy: 'category', op: 'has', value: 'news' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'taxonomy', taxonomy: 'category', op: 'not_has', value: 'sports' } ] }, ctx ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'taxonomy', taxonomy: 'category', op: 'has', value: 'sports' } ] }, ctx ) ).toBe( false );
	} );

	it( 'auth rule', () => {
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'auth', value: true } ] }, { isAuthenticated: true } ) ).toBe( true );
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'auth', value: true } ] }, { isAuthenticated: false } ) ).toBe( false );
	} );

	it( 'unknown rule type returns false', () => {
		expect( evaluateRules( { operator: 'AND', rules: [ { type: 'unknown_type', op: 'equals', value: '1' } ] }, {} ) ).toBe( false );
	} );

	it( 'OR relation', () => {
		const rules = { operator: 'OR', rules: [ { type: 'index', op: 'equals', value: 0 }, { type: 'index', op: 'equals', value: 1 } ] };
		expect( evaluateRules( rules, { index: 1 } ) ).toBe( true );
		expect( evaluateRules( rules, { index: 5 } ) ).toBe( false );
	} );

	it( 'AND relation requires all rules', () => {
		const ctx = { index: 0, meta: { featured: '1' } };
		const rules = {
			operator: 'AND',
			rules: [
				{ type: 'index', op: 'equals', value: 0 },
				{ type: 'meta', key: 'featured', op: 'equals', value: '1' },
			],
		};
		expect( evaluateRules( rules, ctx ) ).toBe( true );
		expect( evaluateRules( rules, { index: 0, meta: { featured: '0' } } ) ).toBe( false );
	} );
} );
