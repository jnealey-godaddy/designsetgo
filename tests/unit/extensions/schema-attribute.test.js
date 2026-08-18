/**
 * The dsgoSchema opt-in attribute.
 *
 * Structured data that misdescribes its content gets sites penalised, so the
 * attribute is added to an explicit allowlist of blocks whose shape genuinely
 * maps to a schema type — never to every block — and it defaults to 'none'.
 */

import { applyFilters } from '@wordpress/hooks';
import '../../../src/extensions/schema';
import {
	SCHEMA_TYPES,
	SCHEMA_BLOCKS,
} from '../../../src/extensions/schema/constants';

const settingsFor = (name) =>
	applyFilters('blocks.registerBlockType', { name, attributes: {} }, name);

describe('schema attribute', () => {
	it('adds dsgoSchema to the accordion', () => {
		expect(
			settingsFor('designsetgo/accordion').attributes.dsgoSchema
		).toEqual({
			type: 'string',
			default: 'none',
		});
	});

	it.each([
		'core/paragraph',
		'designsetgo/grid',
		'designsetgo/accordion-item',
		'designsetgo/section',
	])('does not add it to %s', (name) => {
		expect(settingsFor(name).attributes.dsgoSchema).toBeUndefined();
	});

	it('offers exactly none, faq and howto for the accordion', () => {
		const values = SCHEMA_TYPES['designsetgo/accordion'].map(
			(t) => t.value
		);
		expect(values).toEqual(['none', 'faq', 'howto']);
	});

	it('defaults to none, so nothing is emitted until an author opts in', () => {
		expect(SCHEMA_TYPES['designsetgo/accordion'][0].value).toBe('none');
	});

	it('only registers blocks that have a matching server-side builder', () => {
		// A control offering a type with no builder behind it is a no-op that
		// looks like a feature. comparison-table and card were in the original
		// plan for Review, but neither block holds rating data, so neither is
		// listed here until a builder exists.
		expect(SCHEMA_BLOCKS).toEqual(['designsetgo/accordion']);
	});

	it('leaves existing attributes untouched', () => {
		const settings = applyFilters(
			'blocks.registerBlockType',
			{
				name: 'designsetgo/accordion',
				attributes: { itemGap: { type: 'string', default: '0.5rem' } },
			},
			'designsetgo/accordion'
		);

		expect(settings.attributes.itemGap).toEqual({
			type: 'string',
			default: '0.5rem',
		});
		expect(settings.attributes.dsgoSchema).toBeDefined();
	});
});
