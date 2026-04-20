import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));
jest.mock('@wordpress/api-fetch', () => jest.fn());
const apiFetch = require('@wordpress/api-fetch');

// Jest hoists `jest.mock()` factories before variable declarations, so names
// referenced inside factories must be prefixed with `mock` (case-insensitive)
// to bypass the scope guard. We declare them here and reference them via the
// `mock*` alias rule.
const mockReplaceBlocks = jest.fn();
const mockCreateErrorNotice = jest.fn();
const mockCreateSuccessNotice = jest.fn();
let mockCurrentBlock = {
	clientId: 'c1',
	attributes: { queryId: 'abc', perPage: 6 },
	innerBlocks: [{ name: 'core/paragraph' }],
};

jest.mock('@wordpress/data', () => ({
	useDispatch: () => ({
		replaceBlocks: mockReplaceBlocks,
		createErrorNotice: mockCreateErrorNotice,
		createSuccessNotice: mockCreateSuccessNotice,
	}),
	useSelect: (selector) =>
		selector((storeName) => {
			if (storeName === 'core/block-editor') {
				return {
					getBlock: () => mockCurrentBlock,
				};
			}
			return {};
		}),
}));
jest.mock('@wordpress/block-editor', () => ({ store: 'core/block-editor' }));
jest.mock('@wordpress/notices', () => ({ store: 'core/notices' }));
jest.mock('@wordpress/blocks', () => ({
	parse: jest.fn(() => [{ name: 'designsetgo/query' }]),
	serialize: jest.fn(() => '<!-- wp:paragraph --><p>Item</p><!-- /wp:paragraph -->'),
}));

jest.mock('@wordpress/components', () => ({
	Button: ({ children, onClick, disabled }) => (
		<button type="button" onClick={onClick} disabled={disabled}>
			{children}
		</button>
	),
	__experimentalVStack: ({ children }) => <div>{children}</div>,
}));

import TemplateIO from '../../../../src/blocks/query/components/TemplateIO';

// Stub URL blob helpers once for the whole suite — jsdom does not implement
// them. Tests that trigger export need these to exist so the handler doesn't
// throw before the apiFetch call is verified.
//
// Also stub HTMLAnchorElement.prototype.click: jsdom throws "Not implemented:
// navigation" when an <a> with an href is clicked, which @wordpress/jest-console
// treats as a test failure. The no-op stub suppresses that without affecting the
// test assertions (we verify apiFetch + notice calls, not the DOM click itself).
let origCreateObjectURL;
let origRevokeObjectURL;
let origAnchorClick;

beforeAll(() => {
	origCreateObjectURL = global.URL.createObjectURL;
	origRevokeObjectURL = global.URL.revokeObjectURL;
	origAnchorClick = HTMLAnchorElement.prototype.click;

	global.URL.createObjectURL = jest.fn(() => 'blob:mock');
	global.URL.revokeObjectURL = jest.fn();
	// eslint-disable-next-line @typescript-eslint/no-empty-function
	HTMLAnchorElement.prototype.click = jest.fn();
});

afterAll(() => {
	global.URL.createObjectURL = origCreateObjectURL;
	global.URL.revokeObjectURL = origRevokeObjectURL;
	HTMLAnchorElement.prototype.click = origAnchorClick;
});

describe('TemplateIO', () => {
	beforeEach(() => {
		apiFetch.mockReset();
		mockReplaceBlocks.mockReset();
		mockCreateSuccessNotice.mockReset();
		mockCreateErrorNotice.mockReset();
		mockCurrentBlock = {
			clientId: 'c1',
			attributes: { queryId: 'abc', perPage: 6 },
			innerBlocks: [{ name: 'core/paragraph' }],
		};
		global.URL.createObjectURL.mockClear();
		global.URL.revokeObjectURL.mockClear();
		HTMLAnchorElement.prototype.click.mockClear();
	});

	it('renders export + import buttons', () => {
		render(<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />);
		expect(screen.getByText('Export template')).toBeInTheDocument();
		expect(screen.getByText('Import template')).toBeInTheDocument();
	});

	it('disables export when queryId is empty', () => {
		render(<TemplateIO clientId="c1" attributes={{ queryId: '' }} />);
		expect(
			screen.getByText('Export template').closest('button')
		).toBeDisabled();
	});

	it('disables export when the current block cannot be resolved', () => {
		mockCurrentBlock = null;

		render(<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />);
		expect(
			screen.getByText('Export template').closest('button')
		).toBeDisabled();
	});

	it('import button is enabled when not busy', () => {
		render(<TemplateIO clientId="c1" attributes={{ queryId: '' }} />);
		expect(
			screen.getByText('Import template').closest('button')
		).not.toBeDisabled();
	});

	it('exports the live block state instead of calling the REST export endpoint', async () => {
		const { serialize } = require('@wordpress/blocks');
		render(<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />);
		fireEvent.click(screen.getByText('Export template'));

		await waitFor(() =>
			expect(mockCreateSuccessNotice).toHaveBeenCalledWith(
				'Query template exported.',
				{ type: 'snackbar' }
			)
		);
		expect(apiFetch).not.toHaveBeenCalled();
		expect(serialize).toHaveBeenCalledWith(mockCurrentBlock.innerBlocks);
		expect(global.URL.createObjectURL).toHaveBeenCalledTimes(1);
	});

	it('shows success notice after export', async () => {
		render(<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />);
		fireEvent.click(screen.getByText('Export template'));

		await waitFor(() =>
			expect(mockCreateSuccessNotice).toHaveBeenCalledWith(
				'Query template exported.',
				{ type: 'snackbar' }
			)
		);
	});

	it('shows error notice when export fails', async () => {
		const originalBlob = global.Blob;
		global.Blob = jest.fn(() => {
			throw new Error('Blob failure');
		});

		render(<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />);
		fireEvent.click(screen.getByText('Export template'));

		await waitFor(() =>
			expect(mockCreateErrorNotice).toHaveBeenCalledWith(
				'Blob failure',
				{ type: 'snackbar' }
			)
		);
		global.Blob = originalBlob;
	});

	it('calls apiFetch POST and replaceBlocks on import', async () => {
		const { parse } = require('@wordpress/blocks');
		apiFetch.mockResolvedValue({
			blockMarkup: '<!-- wp:designsetgo/query /-->',
		});

		const { container } = render(
			<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />
		);

		const fileInput = container.querySelector('input[type="file"]');
		expect(fileInput).not.toBeNull();

		const jsonContent = JSON.stringify({ schemaVersion: 1 });
		const file = new File([jsonContent], 'template.json', {
			type: 'application/json',
		});
		// jsdom's File.text() may not be implemented; patch it directly.
		file.text = () => Promise.resolve(jsonContent);

		Object.defineProperty(fileInput, 'files', {
			value: [file],
			configurable: true,
		});

		fireEvent.change(fileInput);

		await waitFor(() =>
			expect(apiFetch).toHaveBeenCalledWith(
				expect.objectContaining({ method: 'POST' })
			)
		);

		await waitFor(() =>
			expect(mockReplaceBlocks).toHaveBeenCalledWith('c1', expect.any(Array))
		);
		expect(parse).toHaveBeenCalled();
	});

	it('shows error notice when import JSON is invalid', async () => {
		const { container } = render(
			<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />
		);

		const fileInput = container.querySelector('input[type="file"]');
		expect(fileInput).not.toBeNull();

		const file = new File(['not-json!!!'], 'bad.json', {
			type: 'application/json',
		});
		file.text = () => Promise.resolve('not-json!!!');

		Object.defineProperty(fileInput, 'files', {
			value: [file],
			configurable: true,
		});

		fireEvent.change(fileInput);

		await waitFor(() => expect(mockCreateErrorNotice).toHaveBeenCalled());
	});

	it('shows error notice when import returns empty blocks', async () => {
		const { parse } = require('@wordpress/blocks');
		parse.mockReturnValueOnce([]); // simulate empty parse result
		apiFetch.mockResolvedValue({ blockMarkup: '' });

		const { container } = render(
			<TemplateIO clientId="c1" attributes={{ queryId: 'abc' }} />
		);

		const fileInput = container.querySelector('input[type="file"]');
		expect(fileInput).not.toBeNull();

		const jsonContent = JSON.stringify({ schemaVersion: 1 });
		const file = new File([jsonContent], 'template.json', {
			type: 'application/json',
		});
		file.text = () => Promise.resolve(jsonContent);

		Object.defineProperty(fileInput, 'files', {
			value: [file],
			configurable: true,
		});

		fireEvent.change(fileInput);

		await waitFor(() =>
			expect(mockCreateErrorNotice).toHaveBeenCalledWith(
				'Imported JSON did not yield a valid block.',
				{ type: 'snackbar' }
			)
		);
	});
});
