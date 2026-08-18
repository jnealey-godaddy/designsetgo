/**
 * Scroll-linked animation attribute.
 *
 * @package
 */

import { applyFilters } from '@wordpress/hooks';
import '../../../src/extensions/block-animations/attributes';

const settingsFor = (name) =>
	applyFilters('blocks.registerBlockType', { name, attributes: {} }, name);

describe('scroll-linked attribute', () => {
	it('registers dsgoScrollLinked defaulting to false', () => {
		expect(
			settingsFor('designsetgo/section').attributes.dsgoScrollLinked
		).toEqual({ type: 'boolean', default: false });
	});
});
