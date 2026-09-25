/**
 * Comparison Table: fallback labels read back from the stored markup.
 *
 * savedCtaTexts is positional (one entry per linked column), so a column edit
 * has to carry each stored fallback with its own column. Editing one column
 * must never change another column's button.
 */
import {
	getCtaFallback,
	getFeaturedBadgeText,
	getSavedCtaText,
	remapSavedCtaTexts,
} from '../../src/blocks/comparison-table/utils/fallback-labels';

const linked = (name, linkText = '') => ({ name, link: '#', linkText });
const unlinked = (name, linkText = '') => ({ name, link: '', linkText });

describe('comparison table fallback labels', () => {
	const columns = [
		linked('Starter', 'Choose'),
		unlinked('Free'),
		linked('Business'),
	];
	// As parsed from stored markup: the two <a> CTAs, in column order.
	const saved = [{ text: 'Choose' }, { text: 'Get Started' }];

	it('reads the stored text by linked-column position', () => {
		expect(getSavedCtaText(columns, saved, 0)).toBe('Choose');
		expect(getSavedCtaText(columns, saved, 2)).toBe('Get Started');
		expect(getCtaFallback(columns, undefined, 2)).toBe('Get Started');
	});

	it('uses the stored badge text, else the default', () => {
		expect(getFeaturedBadgeText('Beliebt')).toBe('Beliebt');
		expect(getFeaturedBadgeText(undefined)).toBe('Popular');
	});

	it('keeps another column’s fallback when one column is renamed', () => {
		const next = columns.map((col, i) =>
			i === 0 ? { ...col, name: 'Starter Plan' } : col
		);
		const remapped = remapSavedCtaTexts(
			columns,
			next,
			saved,
			columns.map((_, i) => i)
		);
		expect(getCtaFallback(next, remapped, 2)).toBe('Get Started');
	});

	it('keeps fallbacks aligned when an earlier column is removed', () => {
		const next = columns.filter((_, i) => i !== 0);
		const remapped = remapSavedCtaTexts(columns, next, saved, [1, 2]);
		expect(getSavedCtaText(next, remapped, 1)).toBe('Get Started');
	});

	it('keeps fallbacks when a column is appended', () => {
		const next = [...columns, linked('Enterprise')];
		const remapped = remapSavedCtaTexts(columns, next, saved, [
			0,
			1,
			2,
			null,
		]);
		expect(getSavedCtaText(next, remapped, 2)).toBe('Get Started');
		expect(getSavedCtaText(next, remapped, 3)).toBe('');
	});

	it('drops stored custom text so clearing the field shows the default', () => {
		const next = columns.map((col, i) =>
			i === 0 ? { ...col, linkText: '' } : col
		);
		const remapped = remapSavedCtaTexts(
			columns,
			next,
			saved,
			columns.map((_, i) => i)
		);
		expect(getSavedCtaText(next, remapped, 0)).toBe('');
		expect(getSavedCtaText(next, remapped, 2)).toBe('Get Started');
	});

	it('drops a fallback once its column is given its own text', () => {
		const next = columns.map((col, i) =>
			i === 2 ? { ...col, linkText: 'Buy' } : col
		);
		expect(
			remapSavedCtaTexts(
				columns,
				next,
				saved,
				columns.map((_, i) => i)
			)
		).toBeUndefined();
	});

	it('drops everything when columns are replaced wholesale', () => {
		expect(
			remapSavedCtaTexts(columns, columns, saved, [null, null, null])
		).toBeUndefined();
	});
});
