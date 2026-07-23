/**
 * AnimationPanel tri-state (Inherit / Custom / Off) + inherited indicator.
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({
	__: (t) => t,
	// AnimationPanel formats the inherited-default indicator with sprintf;
	// the brief's mock only covered __(), which crashes that render path.
	sprintf: (template, ...args) => {
		let result = template;
		args.forEach((arg) => {
			result = result.replace(/%\d?\$?[sd]/, arg);
		});
		return result;
	},
}));

// Minimal component mocks that render enough to assert against.
jest.mock('@wordpress/components', () => ({
	PanelBody: ({ children }) => <div>{children}</div>,
	ToggleControl: ({ label }) => <label>{label}</label>,
	SelectControl: ({ label }) => <label>{label}</label>,
	RangeControl: ({ label }) => <label>{label}</label>,
	Notice: ({ children }) => <div>{children}</div>,
	__experimentalToggleGroupControl: ({ label, value, children }) => (
		<div aria-label={label} data-value={value}>
			{children}
		</div>
	),
	__experimentalToggleGroupControlOption: ({ label, value }) => (
		<button data-value={value}>{label}</button>
	),
}));

import AnimationPanel from '../../../src/extensions/block-animations/components/AnimationPanel';

describe('AnimationPanel tri-state', () => {
	beforeEach(() => {
		window.dsgoSettings = {
			blockAnimationsEnabled: true,
			blockAnimations: [
				{
					block: 'core/button',
					entrance: 'fadeInUp',
					trigger: 'scroll',
					duration: 600,
				},
			],
		};
	});

	it('shows the inherited indicator for a block type with a default', () => {
		render(
			<AnimationPanel
				name="core/button"
				attributes={{}}
				setAttributes={() => {}}
			/>
		);
		expect(
			screen.getByText(/Inheriting theme animation/i)
		).toBeInTheDocument();
	});

	it('shows the no-default message when none applies', () => {
		render(
			<AnimationPanel
				name="core/paragraph"
				attributes={{}}
				setAttributes={() => {}}
			/>
		);
		expect(
			screen.getByText(/No theme animation for this block type/i)
		).toBeInTheDocument();
	});
});
