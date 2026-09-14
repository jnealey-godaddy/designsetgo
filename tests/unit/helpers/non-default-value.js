/**
 * A value that differs from the attribute's default, so the attribute actually
 * lands in the block comment AND in save()'s output.
 *
 * Testing only `createBlock(name, {})` would miss any guard keyed on markup that
 * a DEFAULT block never emits — grid's `repeat(N, minmax(...))` track, for one,
 * which only appears once `columnMinWidth` is set. Those guards are precisely
 * the ones that can quietly start claiming current content.
 *
 * @param {Object} schema The attribute's block.json schema.
 * @return {*} A non-default value, or `undefined` to skip this attribute.
 */
export function nonDefaultValue(schema) {
	if (schema.enum) {
		return schema.enum.find((v) => v !== schema.default);
	}

	switch (schema.type) {
		case 'boolean':
			return !schema.default;
		case 'number':
		case 'integer':
			return (schema.default ?? 0) + 1;
		case 'string':
			// A length + unit reads as valid CSS wherever a value is interpolated
			// into a style, and as an ordinary string everywhere else.
			return schema.default === '7px' ? '9px' : '7px';
		default:
			// Objects/arrays (style, lock, metadata, border…) have no meaningful
			// generic probe; the defaults case already covers them.
			return undefined;
	}
}
