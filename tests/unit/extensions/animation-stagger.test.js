/**
 * Child stagger attributes.
 *
 * @package
 */

import { applyFilters } from '@wordpress/hooks';
import '../../../src/extensions/block-animations/attributes';

const settingsFor = (name) =>
	applyFilters('blocks.registerBlockType', { name, attributes: {} }, name);

describe('stagger attributes', () => {
	it('registers dsgoStaggerEnabled defaulting to false', () => {
		expect(
			settingsFor('designsetgo/grid').attributes.dsgoStaggerEnabled
		).toEqual({ type: 'boolean', default: false });
	});

	it('registers dsgoStaggerStep defaulting to 80ms', () => {
		expect(
			settingsFor('designsetgo/grid').attributes.dsgoStaggerStep
		).toEqual({ type: 'number', default: 80 });
	});

	it('does not add stagger attributes to core/freeform', () => {
		expect(
			settingsFor('core/freeform').attributes.dsgoStaggerEnabled
		).toBeUndefined();
	});
});
