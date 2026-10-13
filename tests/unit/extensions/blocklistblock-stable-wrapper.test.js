/**
 * editor.BlockListBlock wrappers must not change element type when an
 * attribute goes from empty to set.
 *
 * Swapping `<BlockListBlock>` for a styled wrapper on the first keystroke
 * remounts the block: inspector state resets (Layout overrides' Viewport
 * snapped back to Desktop) and focus leaves the field, so the rest of the
 * typing lands in the block's content (Custom CSS "selector { … }" became
 * "s" plus "elector { … }" in the heading).
 */
import { useEffect } from 'react';
import { render } from '@testing-library/react';
import { applyFilters, removeAllFilters } from '@wordpress/hooks';

jest.mock('@wordpress/block-editor', () => ({
	InspectorControls: ({ children }) => <div>{children}</div>,
	useStyleOverride: jest.fn(),
}));

let mounts = 0;
function BaseBlockListBlock({ className, wrapperProps }) {
	useEffect(() => {
		mounts++;
	}, []);
	return (
		<div
			data-testid="block"
			className={[className, wrapperProps?.className]
				.filter(Boolean)
				.join(' ')}
		/>
	);
}

/**
 * Render a block, then re-render it with `next` attributes.
 *
 * @param {string} name  Block name.
 * @param {Object} next  Attributes after the edit.
 * @param {Object} start Attributes before the edit.
 * @return {{mountsAfter: number, block: HTMLElement}} Mount count and node.
 */
function edit(name, next, start = {}) {
	const Filtered = applyFilters('editor.BlockListBlock', BaseBlockListBlock);
	mounts = 0;
	const props = { name, clientId: 'abc', isSelected: true };
	const { rerender, getByTestId } = render(
		<Filtered {...props} attributes={start} />
	);
	rerender(<Filtered {...props} attributes={next} />);
	return { mountsAfter: mounts, block: getByTestId('block') };
}

describe('stable BlockListBlock wrappers', () => {
	beforeAll(() => {
		require('../../../src/extensions/custom-css');
		require('../../../src/extensions/layout');
	});
	afterAll(() => removeAllFilters('editor.BlockListBlock'));

	it('keeps the block mounted when the first Custom CSS character is typed', () => {
		const { mountsAfter, block } = edit('core/heading', {
			dsgoCustomCSS: 's',
		});
		expect(mountsAfter).toBe(1);
		expect(block.className).toContain('dsgo-custom-css-abc');
	});

	it('keeps the block mounted when the Custom CSS is cleared', () => {
		const { mountsAfter, block } = edit(
			'core/heading',
			{ dsgoCustomCSS: '' },
			{ dsgoCustomCSS: 'selector { color: red; }' }
		);
		expect(mountsAfter).toBe(1);
		expect(block.className).not.toContain('dsgo-custom-css-abc');
	});

	it('keeps the block mounted when the first layout override is set', () => {
		const { mountsAfter, block } = edit('designsetgo/row', {
			dsgoLayout: { mobile: { gap: '6px' } },
		});
		expect(mountsAfter).toBe(1);
		expect(block.className).toMatch(/dsgo-layout-[0-9a-f]+/);
	});

	it('keeps the block mounted when the last layout override is removed', () => {
		const { mountsAfter, block } = edit(
			'core/group',
			{ dsgoLayout: undefined },
			{ dsgoLayout: { desktop: { gridColumn: 'span 2' } } }
		);
		expect(mountsAfter).toBe(1);
		expect(block.className).not.toMatch(/dsgo-layout-/);
	});
});
