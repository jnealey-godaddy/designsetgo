/**
 * The editor publishes measured responsive tracks for native child-span CSS.
 * Authored spans remain unchanged so saving and widening retain the layout.
 */
import { render, screen } from '@testing-library/react';
import metadata from '../block.json';
import GridEdit from '../edit';

jest.mock('@wordpress/data', () => ({
	useSelect: (callback) =>
		callback(() => ({
			getBlock: () => ({ innerBlocks: [{ name: 'core/paragraph' }] }),
			getDeviceType: () => 'Mobile',
		})),
}));

jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: (props) => props,
	useInnerBlocksProps: (props) => ({
		...props,
		children: (
			<p
				data-testid="grid-child"
				data-block="child"
				style={{ gridColumn: 'span 3' }}
			>
				Card
			</p>
		),
	}),
	InnerBlocks: { ButtonBlockAppender: () => null },
	InspectorControls: () => null,
	BlockControls: () => null,
	AlignmentControl: () => null,
	store: 'core/block-editor',
	useSettings: () => ['650px'],
	__experimentalUseMultipleOriginColorsAndGradients: () => ({}),
	__experimentalColorGradientSettingsDropdown: () => null,
}));

jest.mock('@wordpress/components', () => ({
	__experimentalUseCustomUnits: () => [],
	__experimentalUnitControl: () => null,
	RangeControl: () => null,
	SelectControl: () => null,
	TextControl: () => null,
	ToggleControl: () => null,
	ToolbarGroup: () => null,
	ToolbarDropdownMenu: () => null,
}));

jest.mock('../../../components/shared', () => {
	const Panel = () => null;
	Panel.Item = () => null;
	return { DsgoInspectorPanel: Panel };
});

const attributes = Object.fromEntries(
	Object.entries(metadata.attributes).map(([key, schema]) => [
		key,
		schema.default,
	])
);

afterEach(() => jest.restoreAllMocks());

test('exposes the template track count without changing the saved child span', () => {
	jest.spyOn(window, 'getComputedStyle').mockImplementation((element) => ({
		gridTemplateColumns:
			element.children[0].style.gridColumn === 'span 3'
				? '200px 200px 200px'
				: '300px 300px',
	}));
	const { container } = render(
		<GridEdit
			clientId="grid"
			attributes={{ ...attributes, mobileColumnTemplate: '1fr 1fr' }}
			setAttributes={jest.fn()}
		/>
	);
	const inner = container.querySelector('.dsgo-grid__inner');
	expect(inner.style.getPropertyValue('--dsgo-grid-rendered-columns')).toBe(
		'2'
	);
	expect(screen.getByTestId('grid-child').style.gridColumn).toBe('span 3');
});

test('re-measures when a native responsive column count changes without resizing', () => {
	let tracks = '300px 300px';
	jest.spyOn(window, 'getComputedStyle').mockImplementation(() => ({
		gridTemplateColumns: tracks,
	}));
	const props = { clientId: 'grid', setAttributes: jest.fn() };
	const { container, rerender } = render(
		<GridEdit {...props} attributes={{ ...attributes, tabletColumns: 2 }} />
	);
	const inner = container.querySelector('.dsgo-grid__inner');
	expect(inner.style.getPropertyValue('--dsgo-grid-rendered-columns')).toBe(
		'2'
	);
	tracks = '600px';
	rerender(
		<GridEdit {...props} attributes={{ ...attributes, tabletColumns: 1 }} />
	);
	expect(inner.style.getPropertyValue('--dsgo-grid-rendered-columns')).toBe(
		'1'
	);
});
