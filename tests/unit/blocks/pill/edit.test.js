/**
 * Pill edit — color/border/gradient regression test.
 *
 * Before this fix, block.json left color/border/spacing.padding serialized on
 * the block wrapper, so `useBlockProps()` (via the real editor's block-support
 * HOCs) put `has-*` classes and inline styles on `.dsgo-pill` — a block-level
 * positioning box core's constrained layout caps at the theme content width.
 * A background there painted a full-width bar instead of hugging the badge,
 * and a preset GRADIENT (class-driven, no inline style) never reached the
 * visible span at all because the old mitigation only sniffed `style="…"`.
 *
 * block.json now sets `__experimentalSkipSerialization` on color / border /
 * spacing.padding, and edit.js re-derives the equivalent classes/styles with
 * the official `__experimentalUseColorProps` / `__experimentalUseBorderProps` /
 * `__experimentalGetSpacingClassesAndStyles` helpers and applies them to the
 * `.dsgo-pill__content` span — mirroring render.php's
 * designsetgo_route_visual_supports() on the frontend.
 *
 * Coverage note: `useBlockProps()` only receives wrapper-side `has-*`
 * class/style from the real editor's `editor.BlockListBlock` block-support
 * HOCs, which run against a live `BlockListBlock` ancestor (full
 * `BlockEditorProvider` tree) — not something reachable from a bare
 * `render(<PillEdit ... />)` in Jest. So `useBlockProps` is mocked to a plain
 * passthrough below (same pattern as tests/unit/blocks/query-group-header),
 * and this test cannot exercise "block.json skip-serialization keeps the
 * class/style off the real wrapper" end-to-end — that half is covered by the
 * PHPUnit render test (tests/phpunit/blocks/pill/render-test.php) for the
 * frontend, and was verified manually in the editor canvas (see the task-3
 * report) since no JS test infrastructure here can drive a full
 * BlockListBlock. What IS fully covered here, with the real (non-mocked)
 * `__experimentalUseColorProps` / `__experimentalUseBorderProps` /
 * `__experimentalGetSpacingClassesAndStyles` helpers, is the half that
 * regressed silently before: that edit.js correctly derives `has-*` classes
 * (including the gradient class, which has no inline style to sniff) and
 * lands them — plus padding — on the span, not the wrapper string edit.js
 * itself builds.
 *
 * @package
 */

import { render } from '@testing-library/react';
import '@testing-library/jest-dom';

// @wordpress/i18n is left real (not stubbed): @wordpress/components pulls in
// its real `isRTL()` transitively via the requireActual('@wordpress/block-editor')
// below, and a plain `{ __: (t) => t }` mock breaks that import.
jest.mock('@wordpress/block-editor', () => ({
	...jest.requireActual('@wordpress/block-editor'),
	// Bare passthrough — the real hook needs a BlockListBlock/PrivateBlockContext
	// ancestor (see file docblock) that isn't reachable from a bare render().
	useBlockProps: (props = {}) => ({ ...props }),
	// BlockControls renders into a toolbar Slot that doesn't exist outside the
	// full editor chrome; render children directly so DsgoJustificationToolbar
	// doesn't need one.
	BlockControls: ({ children }) => <>{children}</>,
}));

import PillEdit from '../../../../src/blocks/pill/edit';

function renderPill(attributes) {
	const { container } = render(
		<PillEdit
			attributes={{
				content: 'Hi',
				justification: 'center',
				...attributes,
			}}
			setAttributes={jest.fn()}
		/>
	);
	return {
		wrapper: container.querySelector('.dsgo-pill'),
		span: container.querySelector('.dsgo-pill__content'),
	};
}

describe('Pill edit — preset background + text colour', () => {
	it('puts the has-* classes on the span, not the wrapper', () => {
		const { wrapper, span } = renderPill({
			backgroundColor: 'accent-3',
			textColor: 'base',
		});

		expect(span).toHaveClass('has-accent-3-background-color');
		expect(span).toHaveClass('has-background');
		expect(span).toHaveClass('has-base-color');
		expect(span).toHaveClass('has-text-color');

		expect(wrapper.className).not.toMatch(/has-accent-3-background-color/);
		expect(wrapper.className).not.toMatch(/has-base-color/);
		expect(wrapper.className).not.toMatch(/has-background/);
	});
});

describe('Pill edit — preset gradient', () => {
	it('puts the has-*-gradient-background class on the span (no inline style to sniff)', () => {
		const { wrapper, span } = renderPill({
			gradient: 'vivid-cyan-blue-to-vivid-purple',
		});

		expect(span).toHaveClass(
			'has-vivid-cyan-blue-to-vivid-purple-gradient-background'
		);
		expect(span).toHaveClass('has-background');

		expect(wrapper.className).not.toMatch(/gradient-background/);
	});
});

describe('Pill edit — custom (inline) background + text colour', () => {
	it('applies the inline colour to the span style, not the wrapper', () => {
		const { wrapper, span } = renderPill({
			style: { color: { background: '#123456', text: '#abcdef' } },
		});

		// jsdom normalizes hex to rgb() when reading back `element.style.*`.
		expect(span.style.backgroundColor).toBe('rgb(18, 52, 86)');
		expect(span.style.color).toBe('rgb(171, 205, 239)');

		expect(wrapper.style.backgroundColor).toBe('');
		expect(wrapper.style.color).toBe('');
	});
});

describe('Pill edit — custom border colour + radius', () => {
	it('applies the has-* class and inline border styles to the span only', () => {
		const { wrapper, span } = renderPill({
			borderColor: 'accent-3',
			style: { border: { radius: '12px' } },
		});

		expect(span).toHaveClass('has-accent-3-border-color');
		expect(span).toHaveClass('has-border-color');
		expect(span.style.borderRadius).toBe('12px');

		expect(wrapper.className).not.toMatch(/border-color/);
		expect(wrapper.style.borderRadius).toBe('');
	});
});

describe('Pill edit — padding via the spacing control', () => {
	it('applies padding to the span and leaves the wrapper untouched', () => {
		const { wrapper, span } = renderPill({
			style: {
				spacing: {
					padding: {
						top: '10px',
						right: '20px',
						bottom: '10px',
						left: '20px',
					},
				},
			},
		});

		expect(span.style.paddingTop).toBe('10px');
		expect(span.style.paddingRight).toBe('20px');

		expect(wrapper.style.paddingTop).toBe('');
		expect(wrapper.style.paddingRight).toBe('');
	});
});
