/** Pure generation must survive native editing and responsive frontend rendering. */
const { test, expect } = require('@playwright/test');
const { cli, deletePostIds } = require('./helpers/wp-cli');
const { getEditorCanvas, openBlockSettings } = require('./helpers/wordpress');

async function tracks(locator) {
	return locator.evaluate((element) => {
		const styles = getComputedStyle(element);
		return {
			columns: styles.gridTemplateColumns.split(' ').map(parseFloat),
			rowGap: styles.rowGap,
			columnGap: styles.columnGap,
		};
	});
}

test('serialized generation and API updates remain editable across editor saves and breakpoints', async ({
	page,
	context,
}, testInfo) => {
	test.skip(
		testInfo.project.name !== 'chromium',
		'One isolated editor acceptance path.'
	);
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	const postId = Number(
		cli(
			'wp eval-file wp-content/plugins/designsetgo/tests/e2e/fixtures/generation-contract.php'
		).trim()
	);
	expect(postId).toBeGreaterThan(0);
	try {
		await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
		await page.locator('iframe[name="editor-canvas"]').waitFor();
		const close = page.locator(
			'.components-modal__header button[aria-label="Close"]'
		);
		if (await close.first().isVisible()) {
			await close.first().click();
		}
		await expect
			.poll(() =>
				page.evaluate(() => {
					const blocks = window.wp.data
						.select('core/block-editor')
						.getBlocks();
					const invalid = (items) =>
						items.flatMap((block) => [
							...(block.isValid === false
								? [
										{
											name: block.name,
											issues: block.validationIssues.map(
												(issue) => issue.args.slice(0, 3)
											),
										},
									]
								: []),
							...invalid(block.innerBlocks),
						]);
					return blocks.length === 1
						? invalid(blocks)
						: ['Waiting for generated content'];
				})
			)
			.toEqual([]);
		await page.evaluate(() => {
			const block = window.wp.data
				.select('core/block-editor')
				.getBlocks()[0];
			window.wp.data
				.dispatch('core/block-editor')
				.selectBlock(block.clientId);
		});
		await openBlockSettings(page);
		const mobileTemplate = page.getByRole('textbox', {
			name: 'Mobile Column Template',
			exact: true,
		});
		await expect(mobileTemplate).toHaveValue('minmax(0, 1fr)');
		await mobileTemplate.fill('minmax(0, 1fr) minmax(0, 1fr)');
		await page.getByRole('button', { name: 'Save', exact: true }).click();
		await expect
			.poll(() =>
				page.evaluate(() =>
					window.wp.data.select('core/editor').isEditedPostDirty()
				)
			)
			.toBe(false);
		await page.reload();
		await page.locator('iframe[name="editor-canvas"]').waitFor();
		await expect(
			getEditorCanvas(page).getByText('Generated native content')
		).toBeVisible();
		await expect
			.poll(() =>
				page.evaluate(() => {
					const block = window.wp.data
						.select('core/block-editor')
						.getBlocks()[0];
					return (
						block?.isValid && block.attributes.mobileColumnTemplate
					);
				})
			)
			.toBe('minmax(0, 1fr) minmax(0, 1fr)');

		const frontend = await context.newPage();
		frontend.on('pageerror', (error) => errors.push(error.message));
		for (const [width, count, ratio, nestedCount] of [
			[1280, 2, 1.5, 3],
			[900, 2, 2, 3],
			[390, 2, 1, 2],
		]) {
			await frontend.setViewportSize({ width, height: 900 });
			const response = await frontend.goto(`/?page_id=${postId}`);
			expect(response.ok()).toBe(true);
			const inner = frontend
				.locator('.wp-block-designsetgo-grid > .dsgo-grid__inner')
				.first();
			const style = await tracks(inner);
			expect(style.columns).toHaveLength(count);
			expect(style.columns[0] / style.columns[1]).toBeCloseTo(ratio, 1);
			expect(style.rowGap).toBe('24px');
			expect(style.columnGap).toBe('40px');
			const nested = inner.locator(
				':scope > .wp-block-designsetgo-grid > .dsgo-grid__inner'
			);
			expect((await tracks(nested)).columns).toHaveLength(nestedCount);
			await expect(inner.locator(':scope > p')).toHaveCSS(
				'color',
				width < 768 ? 'rgb(101, 67, 33)' : 'rgb(18, 52, 86)'
			);
			await expect(nested.locator(':scope > p')).toHaveCSS(
				'grid-column-start',
				`span ${nestedCount}`
			);
			await expect(
				frontend.getByRole('link', { name: 'Our work' })
			).toBeVisible();
		}
		await frontend.close();
		expect(errors).toEqual([]);
	} finally {
		deletePostIds([postId]);
	}
});
