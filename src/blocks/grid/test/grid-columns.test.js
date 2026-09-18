/**
 * Grid Block - desktop column track builder
 */

import { getGridTemplateColumns } from '../grid-columns';

const GAP = 'var(--wp--preset--spacing--50)';

describe('getGridTemplateColumns', () => {
	it('repeats the column count when nothing else is set', () => {
		expect(getGridTemplateColumns('', 3, GAP, '')).toBe('repeat(3, 1fr)');
	});

	it('uses a custom column template verbatim on desktop', () => {
		expect(
			getGridTemplateColumns(
				'',
				2,
				GAP,
				'minmax(0, .7fr) minmax(0, 1.3fr)'
			)
		).toBe('minmax(0, .7fr) minmax(0, 1.3fr)');
	});

	it('trims a custom column template', () => {
		expect(getGridTemplateColumns('', 2, GAP, '  2fr 1fr  ')).toBe(
			'2fr 1fr'
		);
	});

	it('prefers the custom template over a column min width', () => {
		expect(getGridTemplateColumns('16rem', 3, GAP, '2fr 1fr')).toBe(
			'2fr 1fr'
		);
	});

	it('falls back to the min-width track list when the template is blank', () => {
		expect(getGridTemplateColumns('16rem', 3, GAP, '   ')).toBe(
			'repeat(auto-fill, minmax(min(100%, max(16rem, (100% - 2 * var(--wp--preset--spacing--50)) / 3)), 1fr))'
		);
	});
});
