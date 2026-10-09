/** Compatibility snapshots must preserve optional layout predecessors. */
import {
	createBlock,
	getBlockType,
	getSaveContent,
	parse,
	registerBlockType,
	serialize,
	unregisterBlockType,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { cloneElement } from '@wordpress/element';
import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';
import '../../src/extensions/custom-css';
import '../../src/extensions/grid-span';
import '../../src/extensions/responsive';
import sectionMetadata from '../../src/blocks/section/block.json';
import rowMetadata from '../../src/blocks/row/block.json';
import gridMetadata from '../../src/blocks/grid/block.json';
import sectionDeprecated from '../../src/blocks/section/deprecated';
import rowDeprecated from '../../src/blocks/row/deprecated';
import gridDeprecated from '../../src/blocks/grid/deprecated';
import sectionSave from '../../src/blocks/section/save';
import rowSave from '../../src/blocks/row/save';
import gridSave from '../../src/blocks/grid/save';

const blocks = [
	['section', sectionMetadata, sectionDeprecated, sectionSave],
	['row', rowMetadata, rowDeprecated, rowSave],
	['grid', gridMetadata, gridDeprecated, gridSave],
];
const priorAttributes = {
	anchor: 'layout-compatibility',
	className: 'is-style-overlay-dark is-style-hover-text-light',
	backgroundColor: 'contrast',
	textColor: 'base',
	borderColor: 'accent-1',
	fontSize: 'large',
	contentWidth: '760px',
	overlayColor: '#102030',
	overlayOpacity: 44,
	dsgoCustomCSS: 'selector { border-top-width: 3px; }',
	dsgoAnimationEnabled: true,
	dsgoEntranceAnimation: 'fadeIn',
	dsgoColumnSpan: 2,
	dsgoRowSpan: 2,
	dsgoHideOnMobile: true,
	style: {
		spacing: { padding: { top: '16px', left: '24px' }, blockGap: '20px' },
		border: { width: '2px', radius: '12px', style: 'solid' },
		typography: { lineHeight: '1.45' },
		color: { background: '#102030', text: '#ffffff' },
	},
};

beforeEach(() => {
	blocks.forEach(([slug]) => registerDesignSetGoBlock(`designsetgo/${slug}`));
});
afterEach(() => {
	blocks.forEach(([slug]) => unregisterBlockType(`designsetgo/${slug}`));
});

describe.each(blocks)(
	'%s pre-layout compatibility',
	(slug, metadata, entries, save) => {
		test('freezes the complete previous attributes and support groups', () => {
			const snapshot = entries[0];
			expect(snapshot.apiVersion).toBe(3);
			expect(snapshot.attributes).toEqual(metadata.attributes);
			expect(snapshot.attributes).not.toBe(metadata.attributes);
			expect(snapshot.supports).toEqual(metadata.supports);
			expect(snapshot.supports).not.toBe(metadata.supports);
			expect(snapshot.attributes).not.toHaveProperty('dsgoLayout');
			expect(snapshot.save).not.toBe(save);
		});

		test('never eagerly migrates valid unchanged markup', () => {
			const snapshot = entries[0];
			expect(
				snapshot.isEligible(priorAttributes, [], { innerHTML: '' })
			).toBe(false);
			const original = serialize(
				createBlock(metadata.name, priorAttributes)
			);
			const [parsed] = parse(original);
			expect(parsed.isValid).toBe(true);
			expect(parsed.attributes).toMatchObject(priorAttributes);
			expect(parsed.attributes).not.toHaveProperty('dsgoLayout');
			expect(serialize(parsed)).toBe(original);
			expect(console).not.toHaveInformed();
		});

		test('passes every attribute and inner block through to the optional current schema', () => {
			const snapshot = entries[0];
			const children = [
				createBlock('designsetgo/section', { contentWidth: '200px' }),
			];
			const migrated = snapshot.migrate(priorAttributes, children);
			expect(migrated).toEqual([priorAttributes, children]);
			expect(migrated[0]).toBe(priorAttributes);
			expect(migrated[1]).toBe(children);
		});

		test('reproduces previous painted markup and supports without a layout class', () => {
			const registered = getBlockType(metadata.name);
			const snapshot = registered.deprecated[0];
			const attrs = createBlock(metadata.name, {
				...priorAttributes,
				...(slug === 'grid' && {
					tabletColumnTemplate: '2fr 1fr',
					mobileColumnTemplate: '1fr',
				}),
				...(slug === 'section' && {
					boxWidth: '820px',
					contentPosition: 'right',
					shapeDividerTop: 'wave',
					shapeDividerTopHeight: 65,
					shapeDividerTopSpacing: '32px',
				}),
			}).attributes;
			const previous = getSaveContent(
				{ ...registered, ...snapshot },
				attrs,
				[]
			);
			const current = getSaveContent(registered, attrs, []);
			expect(previous).toBe(current);
			expect(previous).toContain('has-contrast-background-color');
			expect(previous).toContain('border-radius:12px');
			expect(previous).toContain('line-height:1.45');
			expect(previous).toContain('dsgo-custom-css-');
			expect(previous).toContain('data-dsgo-animation');
			expect(previous).not.toContain('dsgo-layout-');
			const stored = `<!-- wp:${metadata.name} ${JSON.stringify(attrs)} -->\n${previous}\n<!-- /wp:${metadata.name} -->`;
			const [parsed] = parse(stored);
			expect(parsed.isValid).toBe(true);
			expect(parsed.attributes).toMatchObject(priorAttributes);
			expect(console).not.toHaveInformed();
		});

		test('preserves painted supports and extension data when the snapshot must migrate', () => {
			const stored = serialize(
				createBlock(metadata.name, priorAttributes)
			);
			unregisterBlockType(metadata.name);
			registerBlockType(metadata.name, {
				...metadata,
				deprecated: entries,
				save: ({ attributes }) =>
					cloneElement(save({ attributes }), {
						'data-layout-version': 'future-save',
					}),
			});
			const [parsed] = parse(stored);
			expect(console).toHaveInformed();
			expect(parsed.isValid).toBe(true);
			expect(parsed.attributes).toMatchObject(priorAttributes);
			expect(parsed.attributes.dsgoLayout).toBeUndefined();
			expect(serialize(parsed)).toContain(
				'data-layout-version="future-save"'
			);
		});
	}
);
