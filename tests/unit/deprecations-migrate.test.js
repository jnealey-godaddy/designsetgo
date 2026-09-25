/**
 * Guard: every deprecation entry must be able to carry its OWN content forward.
 *
 * deprecations-isEligible.test.js checks the opposite direction (no entry may
 * reclaim current content). This file checks what that one cannot see: the
 * traps CLAUDE.md documents under "Deprecations", all of which fail silently.
 *
 * 1. Every entry redeclares `apiVersion`. It is a DEPRECATED_ENTRY_KEY, so
 *    WordPress strips it from the block type when it builds the deprecated
 *    version; an entry without it runs save() under apiVersion 1 semantics,
 *    where getSaveContent.extraProps is applied a second time. Every DSGo
 *    block has been apiVersion 3 since its first commit.
 *
 * 2. Every `supports.typography` key is one WordPress recognises. The
 *    unprefixed `fontFamily` / `fontWeight` / `letterSpacing` / … look like
 *    supports but fail hasBlockSupport(), so the entry never gets the
 *    top-level `fontFamily` attribute (or its `has-*-font-family` class), and
 *    getBlockAttributes() drops that attribute before migrate() runs.
 *
 * 3. Markup produced by an entry's own save() parses as a valid block under
 *    the current registration, and a block-support style probe survives
 *    migrate(). This is a synthetic fixture per entry, so it reaches every
 *    deprecation in the plugin, including those with no hand-written fixture.
 */
// Same nested @wordpress/blocks instance as useBlockProps.save() — see
// deprecations-isEligible.test.js for why.
import {
	parse,
	getBlockType,
	getSaveContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import fs from 'fs';
import path from 'path';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const BLOCKS_DIR = path.join(__dirname, '../../src/blocks');

const blocksWithDeprecations = fs
	.readdirSync(BLOCKS_DIR)
	.filter((slug) =>
		fs.existsSync(path.join(BLOCKS_DIR, slug, 'deprecated.js'))
	)
	.map((slug) => `designsetgo/${slug}`);

// Keys WordPress strips from the block type when it builds a deprecated
// version (@wordpress/blocks → api/constants.js DEPRECATED_ENTRY_KEYS).
const DEPRECATED_ENTRY_KEYS = [
	'attributes',
	'supports',
	'save',
	'migrate',
	'isEligible',
	'apiVersion',
];

// Every key hasBlockSupport() understands under `supports.typography`.
const TYPOGRAPHY_SUPPORT_KEYS = new Set([
	'fontSize',
	'lineHeight',
	'textAlign',
	'textColumns',
	'fitText',
	'__experimentalFontFamily',
	'__experimentalFontStyle',
	'__experimentalFontWeight',
	'__experimentalLetterSpacing',
	'__experimentalTextTransform',
	'__experimentalTextDecoration',
	'__experimentalWritingMode',
	'__experimentalDefaultControls',
	'__experimentalSelector',
	'__experimentalSkipSerialization',
]);

// Lives inside the `style` attribute, which every entry with any style
// support registers, so it needs no per-feature support to be carried.
const STYLE_PROBE = {
	typography: { letterSpacing: '3px', textTransform: 'uppercase' },
};

/**
 * Mirrors @wordpress/blocks' serializeAttributes() escaping, so a probe value
 * can never close the block comment early.
 *
 * @param {Object} attributes Comment attributes.
 * @return {string} Serialized JSON.
 */
function serializeAttributes(attributes) {
	return JSON.stringify(attributes)
		.replace(/--/g, '\\u002d\\u002d')
		.replace(/</g, '\\u003c')
		.replace(/>/g, '\\u003e')
		.replace(/&/g, '\\u0026')
		.replace(/\\"/g, '\\u0022');
}

/**
 * The attributes a freshly inserted block of this version would have had,
 * plus the style probe when the version registered `style`.
 *
 * @param {Object} schema Processed attribute schema of the version.
 * @return {Object} Attributes.
 */
function probeAttributes(schema) {
	const attributes = {};
	Object.entries(schema).forEach(([key, def]) => {
		if (def.default !== undefined) {
			attributes[key] = JSON.parse(JSON.stringify(def.default));
		}
	});
	if (schema.style) {
		attributes.style = { ...attributes.style, ...STYLE_PROBE };
	}
	return attributes;
}

/**
 * Serializes a block exactly as the given (deprecated) version would have.
 *
 * @param {string} name        Block name.
 * @param {Object} versionType Block type assembled for the deprecated version.
 * @param {Object} attributes  Attributes to save.
 * @return {string} Block markup, comment delimiters included.
 */
function serializeAsVersion(name, versionType, attributes) {
	const comment = {};
	Object.entries(versionType.attributes).forEach(([key, def]) => {
		const value = attributes[key];
		if (
			value === undefined ||
			def.source !== undefined ||
			def.role === 'local' ||
			('default' in def &&
				JSON.stringify(def.default) === JSON.stringify(value))
		) {
			return;
		}
		comment[key] = value;
	});

	const json = Object.keys(comment).length
		? ` ${serializeAttributes(comment)}`
		: '';
	const html = getSaveContent(versionType, attributes, []);

	return html
		? `<!-- wp:${name}${json} -->\n${html}\n<!-- /wp:${name} -->`
		: `<!-- wp:${name}${json} /-->`;
}

describe('deprecation entries', () => {
	beforeAll(() => {
		blocksWithDeprecations.forEach(registerDesignSetGoBlock);
	});

	it('finds the blocks under test', () => {
		expect(blocksWithDeprecations.length).toBeGreaterThan(20);
	});

	describe.each(blocksWithDeprecations)('%s', (name) => {
		const entries = () => getBlockType(name).deprecated ?? [];

		it('redeclares apiVersion on every entry', () => {
			const { apiVersion } = getBlockType(name);
			const missing = entries()
				.map((entry, i) => [entry, i])
				.filter(([entry]) => !(entry.apiVersion >= 2))
				.map(([, i]) => `deprecated[${i}]`);

			expect(apiVersion).toBe(3);
			expect(missing).toEqual([]);
		});

		it('uses only real typography support keys', () => {
			const bad = [];
			entries().forEach((entry, i) => {
				Object.keys(entry.supports?.typography ?? {}).forEach((key) => {
					if (!TYPOGRAPHY_SUPPORT_KEYS.has(key)) {
						bad.push(`deprecated[${i}].typography.${key}`);
					}
				});
			});

			expect(bad).toEqual([]);
		});

		it("migrates each entry's own markup without losing styles", () => {
			const blockType = getBlockType(name);
			const base = Object.fromEntries(
				Object.entries(blockType).filter(
					([key]) =>
						!DEPRECATED_ENTRY_KEYS.includes(key) &&
						key !== 'deprecated'
				)
			);
			const failures = [];

			entries().forEach((entry, i) => {
				const versionType = { ...base, ...entry };
				const attributes = probeAttributes(versionType.attributes);
				const html = serializeAsVersion(name, versionType, attributes);
				const [block] = parse(html);

				if (!block.isValid) {
					failures.push(`deprecated[${i}]: invalid after parse`);
					return;
				}
				if (!versionType.attributes.style) {
					return;
				}
				const typography = block.attributes.style?.typography ?? {};
				Object.entries(STYLE_PROBE.typography).forEach(
					([key, value]) => {
						if (typography[key] !== value) {
							failures.push(
								`deprecated[${i}]: lost style.typography.${key}`
							);
						}
					}
				);
			});

			// WordPress logs every migration (console.info) and every failed
			// validation (console.warn / console.error). Validity is asserted
			// above instead, so drop those logs before @wordpress/jest-console
			// treats them as unexpected.
			['info', 'warn', 'error'].forEach((method) =>
				// eslint-disable-next-line no-console
				console[method].mockClear?.()
			);
			expect(failures).toEqual([]);
		});
	});
});
