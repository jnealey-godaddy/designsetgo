import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
	_n: (singular, plural, count) => (count === 1 ? singular : plural),
	sprintf: (template, ...args) => {
		let result = template;
		args.forEach((arg) => {
			result = result.replace(/%d/, String(arg));
		});
		return result;
	},
}));

import ResultCountBadge from '../../../../src/blocks/query/components/ResultCountBadge';

describe('ResultCountBadge', () => {
	it('returns null when totalItems is null', () => {
		const { container } = render(
			<ResultCountBadge totalItems={null} loading={false} error={null} />
		);
		expect(container.firstChild).toBeNull();
	});

	it('returns null when totalItems is undefined', () => {
		const { container } = render(
			<ResultCountBadge totalItems={undefined} loading={false} error={null} />
		);
		expect(container.firstChild).toBeNull();
	});

	it('renders loading state', () => {
		render(<ResultCountBadge totalItems={null} loading={true} error={null} />);
		expect(screen.getByText(/loading/i)).toBeInTheDocument();
	});

	it('loading span has is-loading class', () => {
		const { container } = render(
			<ResultCountBadge totalItems={null} loading={true} error={null} />
		);
		expect(container.firstChild).toHaveClass('is-loading');
	});

	it('renders error state', () => {
		render(
			<ResultCountBadge totalItems={null} loading={false} error={new Error('boom')} />
		);
		expect(screen.getByText(/preview failed/i)).toBeInTheDocument();
	});

	it('error span has is-error class', () => {
		const { container } = render(
			<ResultCountBadge totalItems={null} loading={false} error={new Error('boom')} />
		);
		expect(container.firstChild).toHaveClass('is-error');
	});

	it('renders singular match count', () => {
		render(<ResultCountBadge totalItems={1} loading={false} error={null} />);
		expect(screen.getByText(/1 match/i)).toBeInTheDocument();
	});

	it('renders plural match count', () => {
		render(<ResultCountBadge totalItems={7} loading={false} error={null} />);
		expect(screen.getByText(/7 matches/i)).toBeInTheDocument();
	});

	it('renders zero with plural', () => {
		render(<ResultCountBadge totalItems={0} loading={false} error={null} />);
		expect(screen.getByText(/0 matches/i)).toBeInTheDocument();
	});

	it('error takes precedence over loading', () => {
		render(
			<ResultCountBadge totalItems={null} loading={true} error={new Error('fail')} />
		);
		expect(screen.getByText(/preview failed/i)).toBeInTheDocument();
		expect(screen.queryByText(/loading/i)).not.toBeInTheDocument();
	});

	it('all rendered spans have aria-live polite', () => {
		const { container: c1 } = render(
			<ResultCountBadge totalItems={null} loading={true} error={null} />
		);
		expect(c1.firstChild).toHaveAttribute('aria-live', 'polite');

		const { container: c2 } = render(
			<ResultCountBadge totalItems={5} loading={false} error={null} />
		);
		expect(c2.firstChild).toHaveAttribute('aria-live', 'polite');
	});
});
