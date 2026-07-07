import { addSvgPatternSaveProps } from '../../../../src/extensions/svg-patterns/editor';

const blockType = { name: 'designsetgo/section' };

test('explicit pattern writes color/opacity/scale data attrs', () => {
	const props = addSvgPatternSaveProps({}, blockType, {
		dsgoSvgPatternEnabled: true,
		dsgoSvgPatternType: 'waves',
		dsgoSvgPatternColor: '#123456',
		dsgoSvgPatternOpacity: 0.3,
		dsgoSvgPatternScale: 2,
	});
	expect(props['data-dsgo-svg-pattern']).toBe('waves');
	expect(props['data-dsgo-svg-pattern-opacity']).toBe('0.3');
	expect(props['data-dsgo-svg-pattern-scale']).toBe('2');
	expect(props.className).toContain('has-dsgo-svg-pattern');
});

test('inherit writes only the type marker, omits color/opacity/scale', () => {
	const props = addSvgPatternSaveProps({}, blockType, {
		dsgoSvgPatternEnabled: true,
		dsgoSvgPatternType: 'inherit',
	});
	expect(props['data-dsgo-svg-pattern']).toBe('inherit');
	expect(props).not.toHaveProperty('data-dsgo-svg-pattern-color');
	expect(props).not.toHaveProperty('data-dsgo-svg-pattern-opacity');
	expect(props).not.toHaveProperty('data-dsgo-svg-pattern-scale');
	expect(props.className).toContain('has-dsgo-svg-pattern');
});

test('disabled pattern is untouched', () => {
	const props = addSvgPatternSaveProps({}, blockType, {
		dsgoSvgPatternEnabled: false,
		dsgoSvgPatternType: 'inherit',
	});
	expect(props).not.toHaveProperty('data-dsgo-svg-pattern');
});
