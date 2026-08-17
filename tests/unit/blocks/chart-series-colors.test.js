/**
 * Chart Block - palette slot tests
 */

import { setPaletteSlot } from '../../../src/blocks/chart/components/SeriesColors';

describe('setPaletteSlot', () => {
	it('writes the first slot', () => {
		expect(setPaletteSlot([], 0, '#ff8800', 3)).toEqual(['#ff8800']);
	});

	it('pads earlier slots so the palette stays positional', () => {
		expect(setPaletteSlot([], 2, '#ff8800', 3)).toEqual([
			'',
			'',
			'#ff8800',
		]);
	});

	it('leaves the other slots untouched', () => {
		expect(setPaletteSlot(['#111111', '#222222'], 1, '#333333', 2)).toEqual(
			['#111111', '#333333']
		);
	});

	it('clearing the only colour returns to the default empty palette', () => {
		expect(setPaletteSlot(['#ff8800'], 0, '', 2)).toEqual([]);
	});

	it('clearing a trailing colour drops the empty tail', () => {
		expect(setPaletteSlot(['#111111', '#222222'], 1, '', 2)).toEqual([
			'#111111',
		]);
	});

	it('grows when a series is added', () => {
		expect(setPaletteSlot(['#111111'], 3, '#444444', 4)).toEqual([
			'#111111',
			'',
			'',
			'#444444',
		]);
	});

	it('survives a missing palette', () => {
		expect(setPaletteSlot(undefined, 0, '#ff8800', 1)).toEqual(['#ff8800']);
	});
});
