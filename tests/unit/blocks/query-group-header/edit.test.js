import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));

jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: () => ({ className: 'dsgo-query-group-header' }),
	useInnerBlocksProps: (p = {}) => ({
		...p,
		children: <div data-testid="inner-blocks" />,
	}),
}));

import Edit from '../../../../src/blocks/query-group-header/edit';

describe('QueryGroupHeader edit', () => {
	it('renders an InnerBlocks wrapper', () => {
		render(<Edit attributes={{}} context={{}} clientId="g1" />);
		expect(screen.getByTestId('inner-blocks')).toBeInTheDocument();
	});

	it('applies the block class to the wrapper', () => {
		const { container } = render(
			<Edit attributes={{}} context={{}} clientId="g2" />
		);
		const wrapper = container.firstChild;
		expect(wrapper).toHaveClass('dsgo-query-group-header');
	});
});
