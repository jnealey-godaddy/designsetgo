/**
 * DsgoInspectorPanel Tests
 *
 * @package
 */

import { render, screen } from '@testing-library/react';
import { DsgoInspectorPanel, _resetWarnCache } from '../../../../src/components/shared/DsgoInspectorPanel';

describe('DsgoInspectorPanel', () => {
	beforeEach(() => {
		_resetWarnCache();
	});
	test('renders with the provided title', () => {
		render(
			<DsgoInspectorPanel
				title="Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(screen.getByText('Settings')).toBeInTheDocument();
	});

	test('renders children', () => {
		render(
			<DsgoInspectorPanel
				title="Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div data-testid="child">Hello</div>
			</DsgoInspectorPanel>
		);
		expect(screen.getByTestId('child')).toBeInTheDocument();
	});

	test('throws (or warns) when panelName is not one of the canonical names', () => {
		const consoleWarn = jest.spyOn(console, 'warn').mockImplementation();
		render(
			<DsgoInspectorPanel
				title="Custom Settings"
				panelName="Custom Settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(consoleWarn).toHaveBeenCalledWith(
			expect.stringContaining(
				'DsgoInspectorPanel: panelName "Custom Settings" is not one of the canonical values'
			)
		);
		consoleWarn.mockRestore();
	});

	test('does not warn for canonical settings/style panelName values', () => {
		const consoleWarn = jest.spyOn(console, 'warn').mockImplementation();
		render(
			<DsgoInspectorPanel
				title="Settings"
				panelName="settings"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		render(
			<DsgoInspectorPanel
				title="Style"
				panelName="style"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(consoleWarn).not.toHaveBeenCalled();
		consoleWarn.mockRestore();
	});

	test('does not warn when panelName is not provided (incremental adoption)', () => {
		const consoleWarn = jest.spyOn(console, 'warn').mockImplementation();
		render(
			<DsgoInspectorPanel
				title="Anything"
				panelId="test-panel"
				resetAll={jest.fn()}
			>
				<div>Child</div>
			</DsgoInspectorPanel>
		);
		expect(consoleWarn).not.toHaveBeenCalled();
		consoleWarn.mockRestore();
	});
});
