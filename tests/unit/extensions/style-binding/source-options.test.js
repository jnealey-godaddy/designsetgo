import {
	SCALAR_RETURN_TYPES,
	KNOWN_KEYED_SLUGS,
	FALLBACK_SOURCES,
	isKeyedSource,
	filterScalarSources,
	buildSourceOptions,
	buildSourceMetaMap,
	withSavedSource,
	argsForSource,
} from '../../../../src/extensions/style-binding/source-options';

describe('style-binding source-options', () => {
	describe('filterScalarSources', () => {
		it('keeps sources that return at least one scalar type', () => {
			const sources = [
				{
					slug: 'designsetgo/woo-stock-quantity',
					label: 'Stock quantity',
					returns: ['number', 'text'],
				},
			];
			expect(filterScalarSources(sources)).toHaveLength(1);
		});

		it('drops sources that only return html', () => {
			const sources = [
				{
					slug: 'designsetgo/woo-price-html',
					label: 'Price (formatted)',
					returns: ['html'],
				},
			];
			expect(filterScalarSources(sources)).toHaveLength(0);
		});

		it('drops sources that only return image', () => {
			const sources = [
				{
					slug: 'designsetgo/acf-image',
					label: 'ACF image',
					returns: ['image'],
				},
			];
			expect(filterScalarSources(sources)).toHaveLength(0);
		});

		it('drops sources outside the designsetgo/ namespace', () => {
			const sources = [
				{
					slug: 'third-party/thing',
					label: 'Third party',
					returns: ['text'],
				},
			];
			expect(filterScalarSources(sources)).toHaveLength(0);
		});

		it('handles a missing or malformed list gracefully', () => {
			expect(filterScalarSources(undefined)).toEqual([]);
			expect(filterScalarSources([{}])).toEqual([]);
		});
	});

	describe('buildSourceOptions', () => {
		it('falls back to the static five when the catalog is empty', () => {
			const options = buildSourceOptions([]);
			const values = options.map((o) => o.value);
			expect(values).toEqual(
				expect.arrayContaining([...KNOWN_KEYED_SLUGS])
			);
			expect(options).toHaveLength(FALLBACK_SOURCES.length);
		});

		it('includes a keyless registered source alongside the fallback five', () => {
			const sources = [
				...FALLBACK_SOURCES,
				{
					slug: 'designsetgo/woo-stock-quantity',
					label: 'Stock quantity',
					group: 'woocommerce',
					returns: ['number', 'text'],
					args: {},
				},
			];
			const values = buildSourceOptions(sources).map((o) => o.value);
			expect(values).toContain('designsetgo/woo-stock-quantity');
			expect(values).toContain('designsetgo/post-meta');
		});

		it('excludes an html-only source even when present in the catalog', () => {
			const sources = [
				...FALLBACK_SOURCES,
				{
					slug: 'designsetgo/woo-price-html',
					label: 'Price (formatted)',
					group: 'woocommerce',
					returns: ['html'],
					args: {},
				},
			];
			const values = buildSourceOptions(sources).map((o) => o.value);
			expect(values).not.toContain('designsetgo/woo-price-html');
		});

		it('sorts options by label', () => {
			const options = buildSourceOptions([]);
			const labels = options.map((o) => o.label);
			expect(labels).toEqual(
				[...labels].sort((a, b) => a.localeCompare(b))
			);
		});
	});

	describe('isKeyedSource', () => {
		it('is true for a source whose schema requires a key arg', () => {
			const source = { args: { key: { required: true } } };
			expect(isKeyedSource(source, 'designsetgo/post-meta')).toBe(true);
		});

		it('is false for a source with no required key arg', () => {
			const source = { args: {} };
			expect(
				isKeyedSource(source, 'designsetgo/woo-stock-quantity')
			).toBe(false);
		});

		it('is false for a source with an optional (not required) key arg', () => {
			const source = { args: { key: { required: false } } };
			expect(isKeyedSource(source, 'anything')).toBe(false);
		});

		it('falls back to the known keyed slug list when metadata is unavailable', () => {
			expect(isKeyedSource(undefined, 'designsetgo/acf')).toBe(true);
			expect(
				isKeyedSource(undefined, 'designsetgo/woo-discount-percent')
			).toBe(false);
		});
	});

	describe('buildSourceMetaMap', () => {
		it('maps every option slug to its metadata', () => {
			const map = buildSourceMetaMap([]);
			for (const slug of KNOWN_KEYED_SLUGS) {
				expect(map[slug]).toBeDefined();
			}
		});
	});

	it('SCALAR_RETURN_TYPES excludes html and image', () => {
		expect(SCALAR_RETURN_TYPES).not.toContain('html');
		expect(SCALAR_RETURN_TYPES).not.toContain('image');
	});
	describe('withSavedSource', () => {
		const options = [
			{
				label: 'Stock quantity',
				value: 'designsetgo/woo-stock-quantity',
			},
		];

		it('leaves the options alone when the saved source is listed', () => {
			expect(
				withSavedSource(options, 'designsetgo/woo-stock-quantity')
			).toBe(options);
		});

		it('adds a saved source the catalog does not list, marked unavailable', () => {
			expect(withSavedSource(options, 'designsetgo/acf')).toEqual([
				...options,
				{
					label: 'designsetgo/acf (unavailable)',
					value: 'designsetgo/acf',
				},
			]);
		});
	});

	describe('argsForSource', () => {
		const keyed = {
			slug: 'designsetgo/post-meta',
			args: { key: { required: true } },
		};
		const keyless = { slug: 'designsetgo/woo-stock-quantity', args: {} };

		it('drops a key when switching to a source that reads none', () => {
			expect(
				argsForSource(
					{
						source: 'designsetgo/post-meta',
						args: { key: '_stock' },
					},
					keyless,
					keyless.slug
				)
			).toEqual({});
		});

		it('keeps the key between keyed sources', () => {
			expect(
				argsForSource(
					{ source: 'designsetgo/acf', args: { key: 'stock' } },
					keyed,
					keyed.slug
				)
			).toEqual({ key: 'stock' });
		});

		it('starts a keyed source with an empty key', () => {
			expect(
				argsForSource(
					{ source: keyless.slug, args: {} },
					keyed,
					keyed.slug
				)
			).toEqual({ key: '' });
		});
	});
});
