/**
 * E2E coverage for the interaction layers extension.
 *
 * Drives the real editor → save → frontend path so the whole chain is
 * exercised: the attribute registration, the save-props serialiser, the
 * conditional PHP enqueue, and the delegated frontend runtime.
 */

const { test, expect } = require('@playwright/test');
const { createNewPost } = require('./helpers/wordpress');
const {
	defineArtifact,
	installVideoCapture,
	installPublishedPageCleanup,
	setPostTitle,
	publishAndResolveUrl,
} = require('./helpers/artifacts');

installVideoCapture(test);
installPublishedPageCleanup(test);

/**
 * Replace the post's blocks with a trigger group and a target group.
 *
 * Uses the data API rather than hand-authored markup so the stored HTML is
 * whatever save() really produces.
 *
 * @param {import('@playwright/test').Page} page        Playwright page.
 * @param {Array}                           interactions Interaction list.
 */
async function seedBlocks(page, interactions) {
	await page.evaluate((specs) => {
		const { createBlock } = wp.blocks;
		wp.data.dispatch('core/block-editor').resetBlocks([
			createBlock(
				'core/group',
				{
					dsgoInteractions: specs,
					className: 'dsgo-e2e-trigger',
				},
				// An empty group collapses to zero height and cannot be
				// clicked; give it real content so the element is hittable.
				[createBlock('core/paragraph', { content: 'Trigger' })]
			),
			createBlock('core/group', { className: 'dsgo-e2e-panel' }, [
				createBlock('core/paragraph', { content: 'Panel' }),
			]),
		]);
	}, interactions);
}

test.describe('Interaction layers', () => {
	test('a click interaction toggles a class on the frontend', async ({
		page,
	}, testInfo) => {
		defineArtifact(testInfo, 'extensions', 'interactions', 'click-toggle');

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Interactions click toggle');
		await seedBlocks(page, [
			{
				id: 'e2e1',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '.dsgo-e2e-panel',
				action: 'toggleClass',
				value: 'is-open',
				once: false,
				offset: 0,
				attributeName: '',
			},
		]);

		const url = await publishAndResolveUrl(page);
		await page.goto(url);

		// The runtime only ships when a block declares an interaction.
		await expect(page.locator('.dsgo-e2e-trigger')).toHaveAttribute(
			'data-dsgo-interactions',
			/toggleClass/
		);

		await page.locator('.dsgo-e2e-trigger').click();
		await expect(page.locator('.dsgo-e2e-panel')).toHaveClass(/is-open/);

		// Toggling back proves the action is stateful, not one-way.
		await page.locator('.dsgo-e2e-trigger').click();
		await expect(page.locator('.dsgo-e2e-panel')).not.toHaveClass(/is-open/);
	});

	test('a click interaction is operable by keyboard', async ({
		page,
	}, testInfo) => {
		defineArtifact(testInfo, 'extensions', 'interactions', 'keyboard');

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Interactions keyboard');
		await seedBlocks(page, [
			{
				id: 'e2e2',
				trigger: 'click',
				targetMode: 'self',
				targetSelector: '',
				action: 'addClass',
				value: 'hit',
				once: false,
				offset: 0,
				attributeName: '',
			},
		]);

		const url = await publishAndResolveUrl(page);
		await page.goto(url);

		const trigger = page.locator('.dsgo-e2e-trigger');

		// The runtime gives a non-native element button semantics.
		await expect(trigger).toHaveAttribute('tabindex', '0');
		await expect(trigger).toHaveAttribute('role', 'button');

		await trigger.focus();
		await page.keyboard.press('Enter');
		await expect(trigger).toHaveClass(/hit/);
	});

	test('the runtime is not loaded on a page with no interactions', async ({
		page,
	}, testInfo) => {
		defineArtifact(testInfo, 'extensions', 'interactions', 'conditional-load');

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Interactions absent');
		await page.evaluate(() => {
			wp.data
				.dispatch('core/block-editor')
				.resetBlocks([
					wp.blocks.createBlock('core/group', {
						className: 'dsgo-e2e-plain',
					}),
				]);
		});

		const url = await publishAndResolveUrl(page);

		const requested = [];
		page.on('request', (req) => requested.push(req.url()));
		await page.goto(url);

		expect(
			requested.some((u) => u.includes('extensions/interactions.js'))
		).toBe(false);
	});
});
