/**
 * Form Builder — Turnstile toggle in the editor.
 *
 * The server rejects every submission to a Turnstile form unless both keys
 * are configured, so the toggle can't be switched on without them, and a
 * form already switched on is warned that it is turning visitors away.
 */

import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import TurnstileSettings from '../../../src/blocks/form-builder/components/TurnstileSettings';

jest.mock('@wordpress/components', () => ({
	ToggleControl: ({ label, checked, disabled, onChange, help }) => (
		<label>
			<input
				type="checkbox"
				aria-label={label}
				checked={checked}
				disabled={disabled}
				onChange={(event) => onChange(event.target.checked)}
			/>
			<span>{help}</span>
		</label>
	),
	Notice: ({ status, children }) => (
		<div data-status={status} role="alert">
			{children}
		</div>
	),
}));

describe('TurnstileSettings', () => {
	afterEach(() => {
		delete window.dsgoIntegrations;
	});

	const renderWith = (configured, enabled, onChange = jest.fn()) => {
		// wp_localize_script sends booleans as "1" / "".
		window.dsgoIntegrations = {
			turnstileConfigured: configured ? '1' : '',
		};
		render(<TurnstileSettings enabled={enabled} onChange={onChange} />);
		return {
			onChange,
			toggle: screen.getByRole('checkbox', {
				name: 'Enable Cloudflare Turnstile',
			}),
		};
	};

	it('works normally once both keys are configured', () => {
		const { toggle, onChange } = renderWith(true, false);

		expect(toggle).not.toBeDisabled();
		fireEvent.click(toggle);
		expect(onChange).toHaveBeenCalledWith(true);
		expect(screen.queryByRole('alert')).toBeNull();
	});

	it('cannot be switched on while the keys are incomplete', () => {
		const { toggle, onChange } = renderWith(false, false);

		expect(toggle).toBeDisabled();
		expect(
			screen.getByText(/needs a site key and a secret key/)
		).toBeInTheDocument();
		expect(
			screen.getByRole('link', { name: /Settings → Integrations/ })
		).toHaveAttribute(
			'href',
			expect.stringContaining('designsetgo-settings')
		);
		expect(onChange).not.toHaveBeenCalled();
	});

	it('warns a form already switched on with incomplete keys, and lets it switch off', () => {
		const { toggle, onChange } = renderWith(false, true);

		expect(screen.getByRole('alert')).toHaveAttribute(
			'data-status',
			'error'
		);
		expect(screen.getByRole('alert')).toHaveTextContent(
			'rejects every submission'
		);
		expect(toggle).not.toBeDisabled();
		fireEvent.click(toggle);
		expect(onChange).toHaveBeenCalledWith(false);
	});
});
