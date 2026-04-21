import variations from '../../../../src/blocks/query/variations';

const ITEM_HOST_NAMES = [
	'designsetgo/query-results',
	'designsetgo/slider',
	'designsetgo/scroll-slides',
];

describe('Query block variations', () => {
	it('ships at least the built-in variations', () => {
		// Count floor: bump when a new built-in variation is added.
		expect(variations.length).toBeGreaterThanOrEqual(8);
	});

	it.each(variations.map((v) => [v.name, v]))(
		'%s — has required shape',
		(_name, v) => {
			expect(v.name).toBeTruthy();
			expect(v.title).toBeTruthy();
			expect(v.description).toBeTruthy();
			expect(v.icon).toBeTruthy();
			expect(v.attributes).toMatchObject({ source: 'posts' });
			expect(Array.isArray(v.innerBlocks)).toBe(true);
			expect(v.innerBlocks.length).toBeGreaterThan(0);
			// v2.6: `scope: ['inserter']` dropped — the QueryPlaceholder
			// template picker surfaces variations on first insert instead.
		}
	);

	it('wraps the item template in a registered item-host child', () => {
		// v2.6: accept any of the known item hosts (query-results, slider,
		// scroll-slides). Mirrors designsetgo_query_item_host_block_names().
		variations.forEach((v) => {
			const hostChild = v.innerBlocks.find(
				(b) => Array.isArray(b) && ITEM_HOST_NAMES.includes(b[0])
			);
			expect(hostChild).toBeDefined();
		});
	});

	it('has unique names', () => {
		const names = variations.map((v) => v.name);
		expect(new Set(names).size).toBe(names.length);
	});

	it('related-posts variation has excludeCurrent: true', () => {
		const v = variations.find((x) => x.name === 'related-posts');
		expect(v.attributes.excludeCurrent).toBe(true);
	});

	it('events variation orders ASC', () => {
		const v = variations.find((x) => x.name === 'events');
		expect(v.attributes.order).toBe('ASC');
	});
});
