/**
 * The Map placeholder must only claim blocks that truly have no location.
 * Patterns ship address-only maps at 0,0 (view.js geocodes them), and older
 * maps rely on the New York attribute defaults; neither may be replaced by
 * the "find a location" placeholder.
 */

import { hasNoLocation } from '../../../src/blocks/map/components/MapPlaceholder';
import metadata from '../../../src/blocks/map/block.json';

const defaults = Object.fromEntries(
	Object.entries(metadata.attributes).map(([key, { default: value }]) => [
		key,
		value,
	])
);

describe('Map hasNoLocation', () => {
	test('a freshly inserted map (0,0, no address) has no location', () => {
		expect(
			hasNoLocation({ ...defaults, dsgoLatitude: 0, dsgoLongitude: 0 })
		).toBe(true);
	});

	test('whitespace is not an address', () => {
		expect(
			hasNoLocation({
				...defaults,
				dsgoLatitude: 0,
				dsgoLongitude: 0,
				dsgoAddress: '  ',
			})
		).toBe(true);
	});

	test('an address-only pattern map has a location', () => {
		expect(
			hasNoLocation({
				...defaults,
				dsgoLatitude: 0,
				dsgoLongitude: 0,
				dsgoAddress: '1 Main St',
			})
		).toBe(false);
	});

	test('an existing map on the default coordinates has a location', () => {
		expect(hasNoLocation(defaults)).toBe(false);
	});

	test('one coordinate set is enough', () => {
		expect(
			hasNoLocation({ ...defaults, dsgoLatitude: 51.5, dsgoLongitude: 0 })
		).toBe(false);
	});
});
