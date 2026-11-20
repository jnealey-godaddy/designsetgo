/**
 * Agent block search: replays real site-designer queries.
 *
 * The site-designer agent finds blocks with a `list_block_types` tool. It
 * filters by exact `category` first, then keeps blocks where ANY search
 * term is a case-insensitive substring of the name, title, description, or
 * keywords. That logic lives in site-designer-api; `search()` below models
 * it so these tests catch block.json changes that would hide DesignSetGo
 * blocks from the agent.
 *
 * The queries come from dev agent traces. With the old `designsetgo`
 * category, every category-filtered search returned core blocks only.
 *
 * @package
 */

const fs = require('fs');
const path = require('path');

const blocksDir = path.resolve(__dirname, '../../src/blocks');
const blocks = fs
	.readdirSync(blocksDir)
	.filter((dir) => fs.existsSync(path.join(blocksDir, dir, 'block.json')))
	.map((dir) =>
		JSON.parse(
			fs.readFileSync(path.join(blocksDir, dir, 'block.json'), 'utf8')
		)
	);

const CORE_CATEGORIES = [
	'text',
	'media',
	'design',
	'widgets',
	'theme',
	'embed',
];

function search(query, category) {
	const terms = query.toLowerCase().split(/\s+/).filter(Boolean);
	return blocks
		.filter((block) => !category || block.category === category)
		.filter((block) => {
			const haystack = [
				block.name,
				block.title,
				block.description,
				...(block.keywords || []),
			]
				.join(' ')
				.toLowerCase();
			return terms.some((term) => haystack.includes(term));
		})
		.map((block) => block.name);
}

describe('Agent block search', () => {
	it.each(blocks.map((block) => [block.name, block.category]))(
		'%s registers into a core category (%s)',
		(name, category) => {
			expect(CORE_CATEGORIES).toContain(category);
		}
	);

	it.each([
		['columns', 'design', 'designsetgo/grid'],
		['columns', null, 'designsetgo/grid'],
		['group paragraph', null, 'designsetgo/section'],
		['group paragraph', null, 'designsetgo/row'],
		['cover image media text', 'media', 'designsetgo/fifty-fifty'],
		['cover image media text', null, 'designsetgo/section'],
		['details accordion faq', null, 'designsetgo/accordion'],
		['details accordion faq', 'design', 'designsetgo/accordion'],
		['photo', null, 'designsetgo/card'],
		['restaurant menu dishes', 'design', 'designsetgo/card'],
	])('"%s" in category %s finds %s', (query, category, expected) => {
		expect(search(query, category)).toContain(expected);
	});
});
