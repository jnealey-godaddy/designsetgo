/**
 * A container's tagName comes from the block comment, which kses never treats
 * as markup. The block.json enum makes the parser drop anything outside the
 * allowed elements, so a hand-written `img src=x onerror=...` can never reach
 * save() and be written back by someone who opens the post.
 */
import {
	createBlock,
	getBlockType,
	getSaveContent,
	parse,
	serialize,
	unregisterBlockType,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const PAYLOAD = 'img src=x onerror=alert(document.domain)';
const names = ['designsetgo/section', 'designsetgo/row', 'designsetgo/grid'];

beforeEach(() => {
	names.forEach((name) => registerDesignSetGoBlock(name));
});
afterEach(() => {
	names.forEach((name) => unregisterBlockType(name));
});

describe.each(names)('%s tagName', (name) => {
	test('parses a disallowed tagName as the default div', () => {
		const html = getSaveContent(
			getBlockType(name),
			createBlock(name).attributes,
			[]
		);
		const stored = `<!-- wp:${name} ${JSON.stringify({
			tagName: PAYLOAD,
		})} -->\n${html}\n<!-- /wp:${name} -->`;

		const [parsed] = parse(stored);

		expect(parsed.attributes.tagName).toBe('div');
		expect(serialize(parsed)).not.toContain('onerror');
		expect(serialize(createBlock(name, parsed.attributes))).not.toContain(
			'<img'
		);
	});

	test('keeps an allowed tagName', () => {
		const original = serialize(createBlock(name, { tagName: 'article' }));
		const [parsed] = parse(original);

		expect(parsed.isValid).toBe(true);
		expect(parsed.attributes.tagName).toBe('article');
		expect(original).toContain('<article ');
	});
});
