/** Card content saved in one editor language must reopen in another. */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { createHash } = require('crypto');
const {
	createNewPost,
	insertPatternBySlug,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const { deletePostIds } = require('./helpers/wp-cli');

const hash = createHash('md5')
	.update('build/blocks/card/index.js')
	.digest('hex');
const germanCatalog = JSON.parse(
	fs.readFileSync(
		path.join(
			__dirname,
			'../../languages',
			`designsetgo-de_DE-${hash}.json`
		),
		'utf8'
	)
).locale_data.messages;

async function cardContent(page) {
	return page.evaluate(() => {
		const cards = [];
		const walk = (blocks) => {
			for (const block of blocks) {
				if (block.name === 'designsetgo/card') {
					const a = block.attributes;
					cards.push({
						title: a.title,
						subtitle: a.subtitle,
						body: a.bodyText,
						badge: a.badgeText,
						badgeLabel: a.badgeAriaLabel,
						image: a.imageUrl,
						alt: a.imageAlt,
						fallback: a.imageFallbackAlt,
					});
				}
				walk(block.innerBlocks || []);
			}
		};
		walk(wp.data.select('core/block-editor').getBlocks());
		return cards;
	});
}

for (const slug of [
	'content/content-portfolio-cards',
	'pricing/pricing-cards',
	'team/team-grid',
]) {
	test(`${slug} saves in German and reopens in English`, async ({ page }) => {
		await createNewPost(page, 'page');
		const postId = await page.evaluate(() =>
			wp.data.select('core/editor').getCurrentPostId()
		);
		try {
			const translatedLabel = await page.evaluate((catalog) => {
				wp.i18n.resetLocaleData(catalog, 'designsetgo');
				return wp.i18n.__('Badge', 'designsetgo');
			}, germanCatalog);
			expect(translatedLabel).toBe(germanCatalog.Badge[0]);
			await insertPatternBySlug(page, `designsetgo/${slug}`);
			expect(await getInvalidBlockNames(page)).not.toContain(
				'designsetgo/card'
			);
			const before = await cardContent(page);
			expect(before.length).toBeGreaterThan(0);
			await page.evaluate(async () => {
				wp.data
					.dispatch('core/editor')
					.editPost({ title: 'Card locale regression' });
				await wp.data.dispatch('core/editor').savePost();
			});
			await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
			await page.waitForFunction(
				() =>
					window.wp?.data?.select('core/block-editor')?.getBlocks()
						?.length > 0
			);
			expect(
				await page.evaluate(() => wp.i18n.__('Badge', 'designsetgo'))
			).toBe('Badge');
			expect(await getInvalidBlockNames(page)).not.toContain(
				'designsetgo/card'
			);
			expect(await cardContent(page)).toEqual(before);
		} finally {
			deletePostIds([postId]);
		}
	});
}
