/**
 * SVG Patterns Extension - Legacy Color Default Deprecation
 *
 * Integration test proving that content saved before dsgoSvgPatternColor's
 * default changed from '#9c92ac' to '' still parses as valid — no "Attempt
 * Recovery" notice — via the deprecated entry injected in attributes.js.
 *
 * Deliberately uses the real @wordpress/blocks parser/validator (not mocked)
 * since the thing under test IS the parser's deprecation-matching behavior.
 *
 * @package
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';

// @wordpress/block-editor ships its own nested copy of @wordpress/blocks
// (version-hoisting didn't dedupe it). useBlockProps.save() resolves block
// supports against THAT copy's registry, so registration/parsing here must
// go through the same instance or useBlockProps can't find our block.
const {
	registerBlockType,
	unregisterBlockType,
	parse,
	getSaveContent,
	getBlockType,
} = require('@wordpress/block-editor/node_modules/@wordpress/blocks');

// Register the extension's filters against the real @wordpress/hooks registry
// (a true singleton — unlike @wordpress/blocks, this one isn't duplicated).
import '../../src/extensions/svg-patterns/attributes';
import '../../src/extensions/svg-patterns/editor';

describe('SVG pattern color legacy-default deprecation', () => {
	const BLOCK_NAME = 'core/group';

	beforeAll(() => {
		registerBlockType(BLOCK_NAME, {
			apiVersion: 2,
			title: 'Dummy Group',
			category: 'design',
			attributes: {},
			save() {
				const blockProps = useBlockProps.save();
				return createElement('div', blockProps, 'Hello');
			},
		});
	});

	afterAll(() => {
		unregisterBlockType(BLOCK_NAME);
	});

	it("registers a deprecated entry using the block's own current save", () => {
		const blockType = getBlockType(BLOCK_NAME);

		expect(blockType.deprecated).toHaveLength(1);
		expect(blockType.deprecated[0].attributes.dsgoSvgPatternColor).toEqual({
			type: 'string',
			default: '#9c92ac',
		});
		expect(blockType.deprecated[0].save).toBe(blockType.save);
	});

	it('parses pre-fix content (implicit #9c92ac color, omitted from the comment) as valid', () => {
		// The inner markup is value-driven (extraProps reads the resolved
		// attribute, not the schema default), so generating it with an explicit
		// '#9c92ac' reproduces byte-for-byte what the pre-fix save() emitted.
		const innerContent = getSaveContent(BLOCK_NAME, {
			dsgoSvgPatternEnabled: true,
			dsgoSvgPatternType: 'dot-grid',
			dsgoSvgPatternColor: '#9c92ac',
			dsgoSvgPatternOpacity: 0.4,
			dsgoSvgPatternScale: 1,
		});

		// Pre-fix comment omits dsgoSvgPatternColor/Opacity/Scale because they
		// equalled the (then-current) schema defaults — only non-default
		// attributes get serialized into the block comment.
		const rawContent = `<!-- wp:core/group {"dsgoSvgPatternEnabled":true,"dsgoSvgPatternType":"dot-grid"} -->\n${innerContent}\n<!-- /wp:core/group -->`;

		expect(innerContent).toContain('data-dsgo-svg-pattern-color="#9c92ac"');

		const [block] = parse(rawContent);

		// The parser logs an info message when a deprecated version's `save`
		// matches and the block gets silently migrated — exactly the behavior
		// under test here (as opposed to matching on the very first/current
		// save, which logs nothing).
		expect(console).toHaveInformed();

		expect(block.isValid).toBe(true);
		expect(block.attributes.dsgoSvgPatternColor).toBe('#9c92ac');
	});

	it('still parses new-style content (color omitted from markup) as valid', () => {
		const innerContent = getSaveContent(BLOCK_NAME, {
			dsgoSvgPatternEnabled: true,
			dsgoSvgPatternType: 'dot-grid',
			dsgoSvgPatternColor: '',
			dsgoSvgPatternOpacity: 0.4,
			dsgoSvgPatternScale: 1,
		});

		expect(innerContent).not.toContain('data-dsgo-svg-pattern-color');

		const rawContent = `<!-- wp:core/group {"dsgoSvgPatternEnabled":true,"dsgoSvgPatternType":"dot-grid"} -->\n${innerContent}\n<!-- /wp:core/group -->`;

		const [block] = parse(rawContent);

		expect(block.isValid).toBe(true);
		expect(block.attributes.dsgoSvgPatternColor).toBe('');
	});
});
