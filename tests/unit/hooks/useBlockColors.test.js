/**
 * useBlockColors Tests
 *
 * @package
 */

import { renderHook } from '@testing-library/react';
import { useBlockColors } from '../../../src/hooks/useBlockColors';

const mockColorSettings = {
	colors: [
		{
			colors: [
				{ slug: 'primary', color: '#ff0000', name: 'Primary' },
				{ slug: 'secondary', color: '#00ff00', name: 'Secondary' },
			],
		},
	],
	gradients: [],
	disableCustomColors: false,
	disableCustomGradients: false,
};

jest.mock('@wordpress/block-editor', () => ({
	useMultipleOriginColorsAndGradients: () => mockColorSettings,
}));

describe('useBlockColors', () => {
	test('returns settings array with decoded colorValue for each entry', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: 'var:preset|color|primary', text: '#abcdef' },
				setAttributes,
				entries: [
					{ label: 'Background', attribute: 'bg' },
					{ label: 'Text', attribute: 'text' },
				],
			})
		);
		expect(result.current.settings).toHaveLength(2);
		expect(result.current.settings[0].label).toBe('Background');
		expect(result.current.settings[0].colorValue).toBe('#ff0000');
		expect(result.current.settings[1].colorValue).toBe('#abcdef');
	});

	test('onColorChange encodes preset hex back to var:preset format', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		result.current.settings[0].onColorChange('#ff0000');
		expect(setAttributes).toHaveBeenCalledWith({
			bg: 'var:preset|color|primary',
		});
	});

	test('onColorChange stores custom hex unchanged when no preset matches', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		result.current.settings[0].onColorChange('#123456');
		expect(setAttributes).toHaveBeenCalledWith({ bg: '#123456' });
	});

	test('onColorChange clears attribute to empty string when value is undefined', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: '#ff0000' },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		result.current.settings[0].onColorChange(undefined);
		expect(setAttributes).toHaveBeenCalledWith({ bg: '' });
	});

	test('exposes colorGradientSettings for spreading into dropdown', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: {},
				setAttributes,
				entries: [],
			})
		);
		expect(result.current.colorGradientSettings).toBe(mockColorSettings);
	});

	test('defaults each entry to enableAlpha=true and clearable=true', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [{ label: 'Background', attribute: 'bg' }],
			})
		);
		expect(result.current.settings[0].enableAlpha).toBe(true);
		expect(result.current.settings[0].clearable).toBe(true);
	});

	test('per-entry options override the alpha/clearable defaults', () => {
		const setAttributes = jest.fn();
		const { result } = renderHook(() =>
			useBlockColors({
				attributes: { bg: undefined },
				setAttributes,
				entries: [
					{
						label: 'Background',
						attribute: 'bg',
						enableAlpha: false,
						clearable: false,
					},
				],
			})
		);
		expect(result.current.settings[0].enableAlpha).toBe(false);
		expect(result.current.settings[0].clearable).toBe(false);
	});
});
