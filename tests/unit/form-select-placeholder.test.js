/**
 * Select placeholders distinguish locale-aware defaults from authored content.
 */
import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from '@wordpress/element';
import {
	createBlock,
	parse,
	registerBlockType,
	serialize,
	unregisterBlockType,
} from '@wordpress/blocks';
import { resetLocaleData, setLocaleData } from '@wordpress/i18n';
import fs from 'fs';
import path from 'path';
import { createHash } from 'crypto';
import metadata from '../../src/blocks/form-select-field/block.json';
import Edit from '../../src/blocks/form-select-field/edit';
import save from '../../src/blocks/form-select-field/save';

// The editor shell owns block selection and inspector portals, not field behavior.
jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: () => ({}),
	InspectorControls: ({ children }) => <>{children}</>,
}));

// Expose the settings panel's reset callbacks without its popover/store machinery.
jest.mock('../../src/components/shared', () => {
	const Panel = ({ children, resetAll }) => (
		<div>
			<button onClick={resetAll}>Reset all</button>
			{children}
		</div>
	);
	Panel.Item = ({ children, label, onDeselect, hasValue }) => (
		<div>
			{label ===
				require('@wordpress/i18n').__('Placeholder', 'designsetgo') && (
				<button onClick={onDeselect} disabled={!hasValue()}>
					Reset placeholder
				</button>
			)}
			{children}
		</div>
	);
	return { DsgoInspectorPanel: Panel };
});

function Editor({ initialAttributes = {} }) {
	const [block, setBlock] = useState(() =>
		createBlock(metadata.name, { fieldName: 'topic', ...initialAttributes })
	);
	return (
		<>
			<Edit
				attributes={block.attributes}
				setAttributes={(changes) =>
					setBlock((current) => ({
						...current,
						attributes: { ...current.attributes, ...changes },
					}))
				}
				clientId="select-test"
				context={{}}
			/>
			<output data-testid="saved-block">{serialize(block)}</output>
		</>
	);
}

function placeholderOption(container) {
	return container.querySelector('.dsgo-form-field__select option[value=""]');
}

function loadEditorCatalog(locale) {
	const hash = createHash('md5')
		.update('build/blocks/form-select-field/index.js')
		.digest('hex');
	const catalog = JSON.parse(
		fs.readFileSync(
			path.join(
				__dirname,
				'../../languages',
				`designsetgo-${locale}-${hash}.json`
			),
			'utf8'
		)
	);
	resetLocaleData(catalog.locale_data.messages, 'designsetgo');
}

beforeAll(() =>
	registerBlockType(metadata.name, {
		...metadata,
		category: 'widgets',
		edit: Edit,
		save,
	})
);
afterAll(() => unregisterBlockType(metadata.name));
beforeEach(() => {
	setLocaleData(
		{
			'': { domain: 'designsetgo', lang: 'fr' },
			'-- Select an option --': ['-- Choisir --'],
		},
		'designsetgo'
	);
});
afterEach(() => resetLocaleData({}, 'designsetgo'));

describe('select placeholder defaults', () => {
	it('falls back to English when the editor catalog has no translation', () => {
		resetLocaleData({}, 'designsetgo');
		const { container } = render(<Editor />);
		expect(placeholderOption(container)).toHaveTextContent(
			'-- Select an option --'
		);
		expect(screen.getByTestId('saved-block').textContent).not.toContain(
			'placeholder'
		);
	});

	it.each(['Reset placeholder', 'Reset all'])(
		'%s stays translatable after saving and reopening in another locale',
		(name) => {
			loadEditorCatalog('fr_FR');
			const first = render(
				<Editor initialAttributes={{ placeholder: 'Authored text' }} />
			);
			fireEvent.click(screen.getByRole('button', { name }));
			expect(placeholderOption(first.container)).toHaveTextContent(
				'-- Sélectionnez une option --'
			);
			const saved = screen.getByTestId('saved-block').textContent;
			expect(saved).not.toContain('placeholder');
			first.unmount();
			loadEditorCatalog('de_DE');
			const [reopened] = parse(saved);
			const second = render(
				<Editor initialAttributes={reopened.attributes} />
			);
			expect(placeholderOption(second.container)).toHaveTextContent(
				'-- Bitte wählen Sie eine Option --'
			);
			expect(screen.getByTestId('saved-block').textContent).toBe(saved);
		}
	);

	it('keeps an omitted placeholder unset when parsing generated content', () => {
		const [block] = parse(
			'<!-- wp:designsetgo/form-select-field {"fieldName":"topic"} /-->'
		);
		expect(block.attributes.placeholder).toBeUndefined();
	});

	it('shows the translated default without persisting it', () => {
		const { container } = render(<Editor />);
		expect(placeholderOption(container)).toHaveTextContent('-- Choisir --');
		expect(
			screen.getByRole('textbox', { name: 'Placeholder' })
		).toHaveValue('-- Choisir --');
		expect(
			screen.getByRole('button', { name: 'Reset placeholder' })
		).toBeDisabled();
		expect(screen.getByTestId('saved-block').textContent).not.toContain(
			'placeholder'
		);
	});

	it.each(['Choisissez un sujet', '-- Select an option --'])(
		'preserves authored text: %s',
		(placeholder) => {
			const { container } = render(
				<Editor initialAttributes={{ placeholder }} />
			);
			expect(placeholderOption(container)).toHaveTextContent(placeholder);
			expect(
				screen.getByRole('button', { name: 'Reset placeholder' })
			).toBeEnabled();
			const [reopened] = parse(
				screen.getByTestId('saved-block').textContent
			);
			expect(reopened.attributes.placeholder).toBe(placeholder);
		}
	);

	it('preserves a cleared placeholder through save and reopen', () => {
		const { container } = render(<Editor />);
		fireEvent.change(screen.getByRole('textbox', { name: 'Placeholder' }), {
			target: { value: '' },
		});
		expect(placeholderOption(container)).toBeNull();
		const [reopened] = parse(screen.getByTestId('saved-block').textContent);
		expect(reopened.attributes.placeholder).toBe('');
	});

	it.each(['Reset placeholder', 'Reset all'])(
		'%s restores an unset, translated default',
		(name) => {
			const { container } = render(
				<Editor initialAttributes={{ placeholder: 'Custom' }} />
			);
			fireEvent.click(screen.getByRole('button', { name }));
			expect(placeholderOption(container)).toHaveTextContent(
				'-- Choisir --'
			);
			expect(
				screen.getByRole('button', { name: 'Reset placeholder' })
			).toBeDisabled();
			const [reopened] = parse(
				screen.getByTestId('saved-block').textContent
			);
			expect(reopened.attributes.placeholder).toBeUndefined();
		}
	);
});
