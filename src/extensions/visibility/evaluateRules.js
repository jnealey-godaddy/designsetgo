/**
 * JS mirror of DesignSetGo\BlockVisibility::matches — keep in sync.
 *
 * Pure function: no React imports, no WordPress imports.
 *
 * @param {object|null} rules   The dsgoVisibility attribute.
 * @param {object}      context Per-item context: { postId, postType, index, meta, terms, isAuthenticated }.
 * @return {boolean} Whether the block should be visible.
 */
export default function evaluateRules( rules, context = {} ) {
	if ( ! rules || ! rules.rules?.length ) return true;
	const op = ( rules.operator || 'AND' ).toUpperCase();
	for ( const rule of rules.rules ) {
		const ok = evaluate( rule, context );
		if ( op === 'OR' && ok ) return true;
		if ( op === 'AND' && ! ok ) return false;
	}
	return op === 'AND';
}

function evaluate( rule, ctx ) {
	const { type, op = 'equals', value } = rule;
	switch ( type ) {
		case 'meta':
			return compare( ctx.meta?.[ rule.key ], op, value );
		case 'taxonomy': {
			const slugs = ctx.terms?.[ rule.taxonomy ] || [];
			const has = slugs.includes( String( value ) );
			return op === 'not_has' ? ! has : has;
		}
		case 'index':
			return compare( ctx.index, op, Number( value ) );
		case 'auth':
			return !! ctx.isAuthenticated === !! value;
		default:
			return false;
	}
}

function compare( actual, op, expected ) {
	switch ( op ) {
		case 'not_equals': return String( actual ) !== String( expected );
		case 'contains':   return String( actual ).toLowerCase().includes( String( expected ).toLowerCase() );
		case 'gt':         return Number( actual ) > Number( expected );
		case 'lt':         return Number( actual ) < Number( expected );
		case 'empty':      return actual === '' || actual == null;
		case 'not_empty':  return actual !== '' && actual != null;
		case 'equals':
		default:           return String( actual ) === String( expected );
	}
}
