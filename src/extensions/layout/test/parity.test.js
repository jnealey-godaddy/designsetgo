import fixtures from '../../../../tests/fixtures/layout-values.json';
import { sanitizeLayout, getLayoutClass, compileLayoutCSS } from '../utils';

describe('PHP and JavaScript layout contract parity', () => {
	it.each(fixtures)('$name: $layout', (fixture) => {
		expect(sanitizeLayout(fixture.name, fixture.layout) !== null).toBe(
			fixture.valid
		);
		expect(getLayoutClass(fixture.name, fixture.layout)).toBe(
			fixture.className || ''
		);
		expect(compileLayoutCSS(fixture.name, fixture.layout)).toBe(
			fixture.css || ''
		);
	});
});
