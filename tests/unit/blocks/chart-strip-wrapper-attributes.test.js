/**
 * Chart block - ServerSideRender payload trimming
 */

import { stripWrapperAttributes } from '../../../src/blocks/chart/utils/strip-wrapper-attributes';

describe('stripWrapperAttributes', () => {
	it('drops every attribute the editor wrapper already applies', () => {
		const result = stripWrapperAttributes({
			chartType: 'bar',
			style: { spacing: { padding: '40px' } },
			className: 'is-custom',
			anchor: 'sales',
			backgroundColor: 'primary',
			textColor: 'contrast',
			gradient: 'vivid',
			fontSize: 'large',
			fontFamily: 'body',
		});

		expect(result).toEqual({ chartType: 'bar' });
	});

	it('leaves the chart data attributes untouched', () => {
		const attributes = {
			chartType: 'donut',
			data: [{ label: 'Q1', value: 10 }],
			dataSource: 'manual',
			metaKey: '',
			height: 240,
			showValues: true,
			showLegend: true,
			showGrid: true,
			palette: ['#111111'],
			label: 'Revenue',
			dsgoVisibility: { operator: 'AND', rules: [] },
		};

		expect(stripWrapperAttributes(attributes)).toEqual(attributes);
	});
});
