/**
 * source-options — pure logic for the Style Bindings source picker.
 *
 * Separated from filters.js so it can be unit tested without mounting the
 * inspector UI. Consumes the Dynamic Tags source catalog (the same one
 * `/designsetgo/v1/dynamic-tags/sources` serves and `useDynamicTagSources`
 * fetches) and turns it into `SelectControl` options plus a "does this
 * source need a key?" check.
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Return types a CSS custom property can hold. `image` and `html` sources
 * (e.g. `designsetgo/woo-price-html`) are excluded — StyleBinding::resolve()
 * on the PHP side refuses a source that declares `html`, and an image
 * descriptor is not a scalar either.
 */
export const SCALAR_RETURN_TYPES = ['text', 'number', 'date'];

/**
 * The five custom-field sources that are meaningless without a `key` arg —
 * mirrors `StyleBinding::KEYED_SOURCES` (includes/features/class-style-binding.php).
 * Used only as a fallback before the Dynamic Tags catalog has loaded; once
 * loaded, `isKeyedSource()` prefers the live `args` schema so a future keyed
 * source does not need a matching edit here.
 */
export const KNOWN_KEYED_SLUGS = new Set([
	'designsetgo/post-meta',
	'designsetgo/acf',
	'designsetgo/metabox',
	'designsetgo/pods',
	'designsetgo/jetengine',
]);

/**
 * The pre-2.9 static list, used when the Dynamic Tags catalog is still
 * loading, errored, or (in tests) never fetched at all. Keeps "the existing
 * five working" true even without a REST round trip.
 */
export const FALLBACK_SOURCES = [
	{
		slug: 'designsetgo/post-meta',
		label: __('Post meta', 'designsetgo'),
		group: 'custom-fields',
		returns: ['text', 'number', 'date'],
		args: { key: { required: true } },
	},
	{
		slug: 'designsetgo/acf',
		label: __('ACF', 'designsetgo'),
		group: 'custom-fields',
		returns: ['text', 'number', 'date'],
		args: { key: { required: true } },
	},
	{
		slug: 'designsetgo/metabox',
		label: __('Meta Box', 'designsetgo'),
		group: 'custom-fields',
		returns: ['text', 'number', 'date'],
		args: { key: { required: true } },
	},
	{
		slug: 'designsetgo/pods',
		label: __('Pods', 'designsetgo'),
		group: 'custom-fields',
		returns: ['text', 'number', 'date'],
		args: { key: { required: true } },
	},
	{
		slug: 'designsetgo/jetengine',
		label: __('JetEngine', 'designsetgo'),
		group: 'custom-fields',
		returns: ['text', 'number', 'date'],
		args: { key: { required: true } },
	},
];

/**
 * Whether a source needs a "Field key / name" value to resolve.
 *
 * Prefers the live arg schema (a required `key` arg) so a newly registered
 * keyed source works without touching this file; falls back to the known
 * slug list when no schema is available yet (catalog still loading).
 *
 * @param {Object|undefined} source Dynamic Tags source metadata, or undefined
 *                                  when the catalog has not resolved it.
 * @param {string}           slug   The source slug being checked.
 * @return {boolean} True when a key field should be shown.
 */
export function isKeyedSource(source, slug) {
	if (source && source.args) {
		return Boolean(source.args.key?.required);
	}
	return KNOWN_KEYED_SLUGS.has(slug);
}

/**
 * Filters a Dynamic Tags source list down to scalar `designsetgo/` sources —
 * the only ones that make sense as the value of a CSS custom property.
 *
 * @param {Object[]} sources Dynamic Tags source catalog entries.
 * @return {Object[]} Sources that can drive a style binding.
 */
export function filterScalarSources(sources) {
	return (sources || []).filter((source) => {
		if (
			typeof source?.slug !== 'string' ||
			!source.slug.startsWith('designsetgo/')
		) {
			return false;
		}
		const returns = Array.isArray(source.returns) ? source.returns : [];
		return returns.some((type) => SCALAR_RETURN_TYPES.includes(type));
	});
}

/**
 * Builds `SelectControl` options from a Dynamic Tags source list, falling
 * back to the static five when the list is empty (loading/error/untested).
 *
 * @param {Object[]} sources Dynamic Tags source catalog entries (already
 *                           filtered, or not — filtering is applied here).
 * @return {{label: string, value: string}[]} Sorted select options.
 */
export function buildSourceOptions(sources) {
	const scalar = filterScalarSources(sources);
	const list = scalar.length > 0 ? scalar : FALLBACK_SOURCES;

	return list
		.map((source) => ({ label: source.label, value: source.slug }))
		.sort((a, b) => a.label.localeCompare(b.label));
}

/**
 * Builds a slug → source metadata lookup, for the "does the currently
 * selected source need a key field?" check.
 *
 * @param {Object[]} sources Dynamic Tags source catalog entries.
 * @return {Object<string, Object>} Map of slug to source metadata.
 */
export function buildSourceMetaMap(sources) {
	const scalar = filterScalarSources(sources);
	const list = scalar.length > 0 ? scalar : FALLBACK_SOURCES;

	return Object.fromEntries(list.map((source) => [source.slug, source]));
}

/**
 * Adds the saved source to the options when the catalog does not list it.
 *
 * A saved binding can name a source that is not registered right now: ACF,
 * Meta Box, Pods, JetEngine or WooCommerce is inactive, or a third-party
 * source was removed. A controlled select whose value matches no option
 * shows the first option instead, which misreports what is saved and blocks
 * picking that option. Listing it as unavailable keeps the display truthful.
 *
 * @param {{label: string, value: string}[]} options Select options.
 * @param {string}                           slug    Saved source slug.
 * @return {{label: string, value: string}[]} Options including the saved one.
 */
export function withSavedSource(options, slug) {
	if (!slug || options.some((option) => option.value === slug)) {
		return options;
	}
	return [
		...options,
		{
			/* translators: %s: source identifier, e.g. designsetgo/acf. */
			label: sprintf(__('%s (unavailable)', 'designsetgo'), slug),
			value: slug,
		},
	];
}

/**
 * The binding args to keep when the source changes.
 *
 * A key only belongs to a source that reads one. Keeping it on a keyless
 * source hides it (the field is not shown) while StyleBinding::resolve()
 * still rejects a protected key such as `_stock`, so the binding would
 * silently never resolve with no field left to clear it.
 *
 * @param {Object} config     Current binding config ({ source, args }).
 * @param {Object} nextSource Metadata for the new source, if known.
 * @param {string} nextSlug   New source slug.
 * @return {Object} Args for the new source.
 */
export function argsForSource(config, nextSource, nextSlug) {
	return isKeyedSource(nextSource, nextSlug)
		? { key: config?.args?.key ?? '' }
		: {};
}
