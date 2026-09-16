/** Pattern insertion must return reliably and never insert duplicate blocks. */
const { test, expect } = require('@playwright/test');
const {
	createNewPost,
	insertPatternBySlug,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const { setPostTitle } = require('./helpers/artifacts');
const { deletePostIds } = require('./helpers/wp-cli');

for (const slug of [
	'team/team-instructor-profiles',
	'testimonials/testimonials-professional-grid',
	'cta/cta-work-together',
]) {
	test(`${slug} inserts once per call with cold and cached pattern lookups`, async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		try {
			await setPostTitle(page, `Insertion regression: ${slug}`);
			const first = await insertPatternBySlug(
				page,
				`designsetgo/${slug}`
			);
			expect(first.blockCount).toBeGreaterThan(0);
			const ids = () =>
				page.evaluate(() =>
					wp.data
						.select('core/block-editor')
						.getBlocks()
						.map((block) => block.clientId)
				);
			expect(await ids()).toHaveLength(first.blockCount);
			expect(await ids()).toContain(first.clientId);
			const second = await insertPatternBySlug(
				page,
				`designsetgo/${slug}`
			);
			const insertedIds = await ids();
			expect(insertedIds).toHaveLength(
				first.blockCount + second.blockCount
			);
			expect(new Set(insertedIds).size).toBe(insertedIds.length);
			expect(insertedIds).toContain(second.clientId);
			expect(await getInvalidBlockNames(page)).toEqual([]);
			await expect(
				insertPatternBySlug(
					page,
					'designsetgo/nonexistent-regression-pattern'
				)
			).rejects.toThrow('is not registered');
			expect(await ids()).toEqual(insertedIds);
		} finally {
			const id = await page
				.evaluate(() =>
					wp.data.select('core/editor').getCurrentPostId()
				)
				.catch(() => null);
			if (id) {
				deletePostIds([id]);
			}
		}
	});
}
