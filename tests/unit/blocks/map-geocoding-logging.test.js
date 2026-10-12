/**
 * Failed frontend geocoding must preserve diagnostics without logging addresses.
 */

import DSGMap from '../../../src/blocks/map/handlers/DSGMap';
import { initOpenStreetMap } from '../../../src/blocks/map/handlers/openstreetmap-handler';
import { initGoogleMap } from '../../../src/blocks/map/handlers/googlemaps-handler';

jest.mock('../../../src/blocks/map/handlers/openstreetmap-handler', () => ({
	initOpenStreetMap: jest.fn(),
}));
jest.mock('../../../src/blocks/map/handlers/googlemaps-handler', () => ({
	initGoogleMap: jest.fn(),
}));

const ADDRESS = '123 F02 Test Lane, Exampleville';
const originalFetch = global.fetch;

describe.each([
	['openstreetmap', initOpenStreetMap],
	['googlemaps', initGoogleMap],
])('DSGMap geocoding logging (%s)', (provider, initialize) => {
	let map;

	beforeEach(() => {
		global.fetch = jest.fn();
		initOpenStreetMap.mockClear();
		initGoogleMap.mockClear();

		const element = document.createElement('div');
		Object.assign(element.dataset, {
			dsgoProvider: provider,
			dsgoLat: '0',
			dsgoLng: '0',
			dsgoAddress: ADDRESS,
			// Defer the constructor's load so each test can await it explicitly.
			dsgoPrivacyMode: 'true',
		});
		map = new DSGMap(element);
	});

	afterEach(() => {
		map.destroy();
		global.fetch = originalFetch;
	});

	it.each(['no results', 'HTTP error', 'network error'])(
		'warns without the configured address and initializes the fallback on %s',
		async (failure) => {
			if (failure === 'network error') {
				global.fetch.mockRejectedValue(
					new TypeError('Failed to fetch')
				);
			} else {
				global.fetch.mockResolvedValue({
					ok: failure === 'no results',
					json: async () => [],
				});
			}

			await map.loadMap();

			// Read the emitted arguments before the console matcher consumes them.
			// eslint-disable-next-line no-console
			const warnings = console.warn.mock.calls.flat().join(' ');
			expect(console).toHaveWarned();
			if (failure !== 'no results') {
				expect(console).toHaveErrored();
			}
			expect(warnings).toMatch(/could not geocode/i);
			expect(warnings).toContain('Falling back to default coordinates.');
			expect(warnings).not.toContain(ADDRESS);
			expect(warnings).not.toContain('123 F02 Test Lane');
			expect(warnings).not.toContain('Exampleville');
			expect(map.config.lat).toBe(40.7128);
			expect(map.config.lng).toBe(-74.006);
			expect(initialize).toHaveBeenCalledTimes(1);
			expect(initialize).toHaveBeenCalledWith(map);
		}
	);

	it('uses successful geocoding coordinates without a failure warning', async () => {
		global.fetch.mockResolvedValue({
			ok: true,
			json: async () => [
				{ lat: '51.5', lon: '-0.12', display_name: ADDRESS },
			],
		});

		await map.loadMap();

		expect(map.config.lat).toBe(51.5);
		expect(map.config.lng).toBe(-0.12);
		expect(initialize).toHaveBeenCalledWith(map);
		expect(console).not.toHaveWarned();
		expect(console).not.toHaveErrored();
	});

	it('keeps configured coordinates without geocoding or warning', async () => {
		map.config.lat = 51.5;
		map.config.lng = -0.12;

		await map.loadMap();

		expect(global.fetch).not.toHaveBeenCalled();
		expect(map.config.lat).toBe(51.5);
		expect(map.config.lng).toBe(-0.12);
		expect(initialize).toHaveBeenCalledWith(map);
		expect(console).not.toHaveWarned();
		expect(console).not.toHaveErrored();
	});
});
