/**
 * dsgoAnimationOptOut attribute registration.
 */
jest.mock('@wordpress/hooks', () => ({ addFilter: jest.fn() }));

import { addFilter } from '@wordpress/hooks';
import '../../../src/extensions/block-animations/attributes';

function getAddAttributesFn() {
	const call = addFilter.mock.calls.find(
		([hook]) => hook === 'blocks.registerBlockType'
	);
	return call[2];
}

describe('block-animations attributes: opt-out', () => {
	it('adds dsgoAnimationOptOut default false to a normal block', () => {
		const fn = getAddAttributesFn();
		const out = fn({ attributes: {} }, 'core/button');
		expect(out.attributes.dsgoAnimationOptOut).toEqual({
			type: 'boolean',
			default: false,
		});
	});

	it('does not add attributes to excluded blocks', () => {
		const fn = getAddAttributesFn();
		const out = fn({ attributes: {} }, 'core/freeform');
		expect(out.attributes.dsgoAnimationOptOut).toBeUndefined();
	});
});
