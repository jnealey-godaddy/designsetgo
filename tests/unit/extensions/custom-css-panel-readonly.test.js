/**
 * Custom CSS panel: read-only for users without edit_css.
 *
 * dsgoSettings reaches the editor through wp_localize_script(), which casts
 * every top-level scalar to a string: current_user_can() arrives as "1" or "",
 * never as a boolean. These cases use those real values, not booleans.
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));

jest.mock('@wordpress/block-editor', () => ({
	InspectorControls: ({ children }) => <div>{children}</div>,
}));

jest.mock('@wordpress/components', () => ({
	PanelBody: ({ children }) => <div>{children}</div>,
	Notice: ({ children }) => <div role="alert">{children}</div>,
	TextareaControl: ({ label, value, readOnly }) => (
		<textarea aria-label={label} defaultValue={value} readOnly={readOnly} />
	),
}));

import { CustomCSSPanel } from '../../../src/extensions/custom-css/edit';

function renderPanel() {
	render(
		<CustomCSSPanel
			attributes={{ dsgoCustomCSS: 'selector { color: red; }' }}
			setAttributes={() => {}}
		/>
	);
	return screen.getByLabelText('CSS Code');
}

describe('CustomCSSPanel edit_css gate', () => {
	afterEach(() => {
		delete window.dsgoSettings;
	});

	it('is read-only with a warning when the localized flag is ""', () => {
		window.dsgoSettings = { canEditCustomCSS: '' };
		expect(renderPanel()).toHaveAttribute('readonly');
		expect(screen.getByRole('alert')).toBeInTheDocument();
	});

	it('is editable when the localized flag is "1"', () => {
		window.dsgoSettings = { canEditCustomCSS: '1' };
		expect(renderPanel()).not.toHaveAttribute('readonly');
		expect(screen.queryByRole('alert')).not.toBeInTheDocument();
	});

	it('stays editable when an older payload has no flag', () => {
		window.dsgoSettings = {};
		expect(renderPanel()).not.toHaveAttribute('readonly');
		expect(screen.queryByRole('alert')).not.toBeInTheDocument();
	});
});
