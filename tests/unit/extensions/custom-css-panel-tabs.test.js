/**
 * Custom CSS panel: Desktop / Tablet / Mobile tabs.
 *
 * Each tab edits its own part of the one stored `dsgoCustomCSS` string, in
 * the sections the configure-custom-css ability also writes.
 */
import { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({
	__: (t) => t,
	sprintf: (format, ...args) => {
		let i = 0;
		return format.replace(/%(\d\$)?[sd]/g, () => String(args[i++]));
	},
}));

jest.mock('@wordpress/block-editor', () => ({
	InspectorControls: ({ children }) => <div>{children}</div>,
}));

jest.mock('@wordpress/components', () => {
	const { useState: useTab } = jest.requireActual('react');
	return {
		PanelBody: ({ children }) => <div>{children}</div>,
		Notice: ({ children }) => <div role="alert">{children}</div>,
		TabPanel: ({ tabs, children }) => {
			const [selected, setSelected] = useTab(tabs[0].name);
			return (
				<div>
					{tabs.map((tab) => (
						<button
							key={tab.name}
							onClick={() => setSelected(tab.name)}
						>
							{tab.title}
						</button>
					))}
					{children(tabs.find((tab) => tab.name === selected))}
				</div>
			);
		},
		TextareaControl: ({ label, value, onChange, help }) => (
			<>
				<textarea
					aria-label={label}
					value={value}
					onChange={(event) => onChange(event.target.value)}
				/>
				<p>{help}</p>
			</>
		),
	};
});

import { CustomCSSPanel } from '../../../src/extensions/custom-css/edit';

function Harness({ initial, onStore }) {
	const [attributes, setState] = useState({ dsgoCustomCSS: initial });
	return (
		<CustomCSSPanel
			attributes={attributes}
			setAttributes={(next) => {
				onStore(next.dsgoCustomCSS);
				setState((previous) => ({ ...previous, ...next }));
			}}
		/>
	);
}

describe('CustomCSSPanel breakpoint tabs', () => {
	it('keeps desktop-only CSS exactly as typed', () => {
		const onStore = jest.fn();
		render(<Harness initial="" onStore={onStore} />);
		fireEvent.change(screen.getByLabelText('CSS Code'), {
			target: { value: 'selector { color: red; }\n' },
		});
		expect(onStore).toHaveBeenLastCalledWith('selector { color: red; }\n');
	});

	it('writes tablet CSS into its own media-query section', () => {
		const onStore = jest.fn();
		render(
			<Harness initial="selector { color: red; }" onStore={onStore} />
		);
		fireEvent.click(screen.getByText('Tablet'));
		expect(screen.getByLabelText('CSS Code')).toHaveValue('');
		expect(
			screen.getByText(/Applies at 1024px wide and below/)
		).toBeInTheDocument();
		expect(
			screen.getByText('Also has CSS for: Desktop')
		).toBeInTheDocument();

		fireEvent.change(screen.getByLabelText('CSS Code'), {
			target: { value: 'selector { color: green; }' },
		});
		expect(onStore).toHaveBeenLastCalledWith(
			'selector { color: red; }\n/* dsgo-css:tablet:start */\n@media (max-width: 1024px) {\nselector { color: green; }\n}\n/* dsgo-css:tablet:end */'
		);

		// Desktop still shows only the desktop rules.
		fireEvent.click(screen.getByText('Desktop'));
		expect(screen.getByLabelText('CSS Code')).toHaveValue(
			'selector { color: red; }'
		);
		expect(
			screen.getByText('Also has CSS for: Tablet')
		).toBeInTheDocument();
	});

	it('clearing a tab removes its section', () => {
		const onStore = jest.fn();
		render(
			<Harness
				initial={
					'a {}\n/* dsgo-css:mobile:start */\n@media (max-width: 767px) {\nb {}\n}\n/* dsgo-css:mobile:end */'
				}
				onStore={onStore}
			/>
		);
		fireEvent.click(screen.getByText('Mobile'));
		expect(screen.getByLabelText('CSS Code')).toHaveValue('b {}');
		fireEvent.change(screen.getByLabelText('CSS Code'), {
			target: { value: '' },
		});
		expect(onStore).toHaveBeenLastCalledWith('a {}');
	});
});
