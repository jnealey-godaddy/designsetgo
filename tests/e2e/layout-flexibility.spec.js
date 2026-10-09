/** Native layouts must match independent HTML references and remain editable. */
const { test, expect } = require('@playwright/test');
const { cli, deletePostIds } = require('./helpers/wp-cli');
const { openBlockSettings } = require('./helpers/wordpress');
const reference = require('../fixtures/layout-reference-designs.json');

async function geometry(page, design, native) {
	return page.evaluate(({ name, measure, isNative }) => {
		const root = document.querySelector(isNative ? `#${name}` : `.${name}`);
		const origin = root.getBoundingClientRect();
		return Object.fromEntries(measure.map((key) => {
			const element = key === 'root' ? root : root.querySelector(isNative ? `#${name}-${key}` : `[data-ref="${key}"]`);
			const rect = element.getBoundingClientRect();
			return [key, { width: rect.width, height: rect.height, x: rect.x - origin.x, y: rect.y - origin.y }];
		}));
	}, { ...design, isNative: native });
}

async function invalidBlocks(page) {
	return page.evaluate(() => {
		const visit = (blocks) => blocks.flatMap((block) => [
			...(block.isValid === false ? [block.name] : []),
			...visit(block.innerBlocks),
		]);
		const blocks = window.wp.data.select('core/block-editor').getBlocks();
		return blocks.length === 8 ? visit(blocks) : ['Waiting for compositions'];
	});
}

async function selectComposition(page, anchor) {
	await page.evaluate((name) => {
		const find = (blocks) => {
			for (const item of blocks) {
				if (item.attributes.anchor === name) {
					return item;
				}
				const child = find(item.innerBlocks);
				if (child) {
					return child;
				}
			}
			return null;
		};
		const block = find(window.wp.data.select('core/block-editor').getBlocks());
		window.wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
	}, anchor);
	await openBlockSettings(page);
}

async function save(page) {
	await page.getByRole('button', { name: 'Save', exact: true }).click();
	await expect.poll(() => page.evaluate(() => window.wp.data.select('core/editor').isEditedPostDirty())).toBe(false);
}

test('eight native compositions match HTML and survive inspector edits', async ({ page, context }, testInfo) => {
	test.skip(testInfo.project.name !== 'chromium', 'Sequential editor acceptance.');
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	const postId = Number(cli('wp eval-file wp-content/plugins/designsetgo/tests/e2e/fixtures/layout-flexibility.php').trim());
	expect(postId).toBeGreaterThan(0);
	const frontend = await context.newPage();
	const html = await context.newPage();
	frontend.on('pageerror', (error) => errors.push(error.message));
	try {
		await frontend.goto(`/?page_id=${postId}`);
		await expect(frontend.locator('#split-hero')).toBeVisible();
		for (const width of [1440, 900, 390]) {
			await frontend.setViewportSize({ width, height: 1000 });
			await html.setViewportSize({ width, height: 1000 });
			const available = await frontend.locator('#split-hero').evaluate((root) => {
				const parent = root.parentElement;
				const style = getComputedStyle(parent);
				return parent.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
			});
			await html.setContent(`<style>${reference.referenceBaseCSS}${reference.designs.map((design) => design.referenceCSS).join('')}</style><div style="width:${available}px">${reference.designs.map((design) => design.referenceHTML).join('')}</div>`);
			const comparisons = [];
			for (const design of reference.designs) {
				const actual = await geometry(frontend, design, true);
				const expected = await geometry(html, design, false);
				comparisons.push({ design: design.name, actual, expected });
				for (const key of design.measure) {
					for (const property of ['width', 'height', 'x', 'y']) {
						// Root y is normalized to zero; parent theme placement is irrelevant.
						expect(Math.abs(actual[key][property] - expected[key][property]), `${width}px ${design.name}/${key}/${property}`).toBeLessThan(2);
					}
				}
			}
			await testInfo.attach(`geometry-${width}`, { body: JSON.stringify(comparisons, null, 2), contentType: 'application/json' });
			await frontend.screenshot({ path: testInfo.outputPath(`native-${width}.png`), fullPage: true });
			await html.screenshot({ path: testInfo.outputPath(`html-${width}.png`), fullPage: true });
		}
		await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
		await page.locator('iframe[name="editor-canvas"]').waitFor();
		const close = page.locator('.components-modal__header button[aria-label="Close"]').first();
		if (await close.isVisible()) {
			await close.click();
		}
		await expect.poll(() => invalidBlocks(page)).toEqual([]);
		await selectComposition(page, 'split-hero');
		await page.getByRole('combobox', { name: 'Viewport', exact: true }).selectOption('mobile');
		const gap = page.getByRole('textbox', { name: 'Gap', exact: true });
		await expect(gap).toHaveValue('12px');
		await gap.fill('20px');
		await save(page);
		await page.reload();
		await page.locator('iframe[name="editor-canvas"]').waitFor();
		await expect.poll(() => invalidBlocks(page)).toEqual([]);
		await selectComposition(page, 'split-hero');
		await page.getByRole('combobox', { name: 'Viewport', exact: true }).selectOption('mobile');
		await expect(gap).toHaveValue('20px');
		await gap.fill('');
		await save(page);
		await frontend.reload();
		await frontend.setViewportSize({ width: 390, height: 1000 });
		await expect.poll(() => frontend.locator('#split-hero > .dsgo-flex__inner').evaluate((element) => getComputedStyle(element).gap)).toBe('24px');
		// Tablet overrides direction only, so clearing mobile gap inherits desktop.
		await selectComposition(page, 'editorial-areas');
		await page.getByRole('combobox', { name: 'Viewport', exact: true }).selectOption('mobile');
		const areas = page.getByRole('textbox', { name: 'Named grid areas', exact: true });
		await areas.fill('"c" "b" "a"');
		await save(page);
		await page.reload();
		await page.locator('iframe[name="editor-canvas"]').waitFor();
		await expect.poll(() => invalidBlocks(page)).toEqual([]);
		await frontend.reload();
		const a = await frontend.locator('#editorial-areas-a').boundingBox();
		const c = await frontend.locator('#editorial-areas-c').boundingBox();
		expect(c.y).toBeLessThan(a.y);
		await selectComposition(page, 'overlap-story');
		await page.getByRole('combobox', { name: 'Viewport', exact: true }).selectOption('desktop');
		await page.getByRole('combobox', { name: 'Property group', exact: true }).selectOption('size');
		await page.getByRole('textbox', { name: 'Height', exact: true }).fill('360px');
		await selectComposition(page, 'overlap-story-b');
		await page.getByRole('combobox', { name: 'Property group', exact: true }).selectOption('placement');
		await page.getByRole('textbox', { name: 'Top', exact: true }).fill('130px');
		await page.getByRole('textbox', { name: 'Layer order', exact: true }).fill('5');
		await expect.poll(() => page.frameLocator('iframe[name="editor-canvas"]').locator('.dsgo-stack').evaluateAll((items) => items.filter((item) => getComputedStyle(item).zIndex === '5').length)).toBe(1);
		await save(page);
		await page.reload();
		await page.locator('iframe[name="editor-canvas"]').waitFor();
		await expect.poll(() => invalidBlocks(page)).toEqual([]);
		await frontend.setViewportSize({ width: 1440, height: 1000 });
		await frontend.reload();
		await expect.poll(() => frontend.locator('#overlap-story').evaluate((element) => element.getBoundingClientRect().height)).toBe(360);
		await expect.poll(() => frontend.locator('#overlap-story-b').evaluate((element) => ({ top: getComputedStyle(element).top, layer: getComputedStyle(element).zIndex }))).toEqual({ top: '130px', layer: '5' });
		expect(await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent())).not.toContain('dsgoCustomCSS');
		expect(errors).toEqual([]);
	} finally {
		await frontend.close();
		await html.close();
		deletePostIds([postId]);
	}
});
