/**
 * E2E coverage for the modal block's off-canvas panel mode.
 *
 * Drives the real editor → save → frontend path, so the whole chain is
 * exercised: the new attributes, the save() class/custom-property branch, the
 * per-block stylesheet, and the modal's existing view.js machinery.
 *
 * The regression case matters as much as the feature: adding displayMode must
 * leave an ordinary centred modal byte-identical, or every modal already
 * published needs a deprecation.
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
 * Seed a trigger plus a modal, using the data API so the stored HTML is
 * whatever save() really produces.
 *
 * @param {import('@playwright/test').Page} page       Playwright page.
 * @param {Object}                          modalAttrs Modal attributes.
 * @param {string}                          modalId    Modal id to wire up.
 */
async function seedModal(page, modalAttrs, modalId) {
	await page.evaluate(
		({ attrs, id }) => {
			const { createBlock } = wp.blocks;
			wp.data
				.dispatch('core/block-editor')
				.resetBlocks([
					createBlock('designsetgo/modal-trigger', {
						targetModalId: id,
					}),
					createBlock('designsetgo/modal', { ...attrs, modalId: id }, [
						createBlock('core/paragraph', {
							content: 'Panel content',
						}),
					]),
				]);
		},
		{ attrs: modalAttrs, id: modalId }
	);
}

test.describe('Off-canvas panel', () => {
	test('slides in from the configured edge and traps focus', async ({
		page,
	}, testInfo) => {
		defineArtifact(testInfo, 'blocks', 'modal', 'offcanvas-panel');

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Off-canvas panel');
		await seedModal(
			page,
			{ displayMode: 'panel', panelEdge: 'right', panelSize: '24rem' },
			'dsgo-modal-e2e-oc'
		);

		const url = await publishAndResolveUrl(page);
		await page.goto(url);

		const modal = page.locator('#dsgo-modal-e2e-oc');
		await expect(modal).toHaveClass(/dsgo-modal--panel/);
		await expect(modal).toHaveClass(/dsgo-modal--panel-right/);

		await page.locator('[data-dsgo-modal-trigger]').click();
		await expect(modal).toHaveAttribute('aria-hidden', 'false');

		// The panel is pinned flush to the right edge at its configured width,
		// which is the whole point of panel mode.
		const geometry = await page.evaluate(() => {
			const d = document.querySelector(
				'#dsgo-modal-e2e-oc .dsgo-modal__dialog'
			);
			const r = d.getBoundingClientRect();
			return {
				position: getComputedStyle(d).position,
				right: Math.round(r.right),
				width: Math.round(r.width),
				height: Math.round(r.height),
				viewportWidth: window.innerWidth,
				viewportHeight: window.innerHeight,
			};
		});
		expect(geometry.position).toBe('fixed');
		expect(geometry.right).toBe(geometry.viewportWidth);
		expect(geometry.width).toBe(384); // 24rem
		expect(geometry.height).toBe(geometry.viewportHeight);

		// The modal's existing machinery must still apply in panel mode.
		await expect(page.locator('body')).toHaveClass(/dsgo-modal-open/);
		await page.keyboard.press('Escape');
		await expect(modal).toHaveAttribute('aria-hidden', 'true');
		await expect(page.locator('body')).not.toHaveClass(/dsgo-modal-open/);
	});

	test('opens from an interaction-layers openModal action', async ({
		page,
	}, testInfo) => {
		defineArtifact(testInfo, 'blocks', 'modal', 'offcanvas-interaction');

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Off-canvas via interaction');
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock(
					'core/group',
					{
						className: 'dsgo-e2e-hamburger',
						dsgoInteractions: [
							{
								id: 'oc1',
								trigger: 'click',
								targetMode: 'self',
								targetSelector: '',
								action: 'openModal',
								value: 'dsgo-modal-e2e-int',
								once: false,
								offset: 0,
								attributeName: '',
							},
						],
					},
					[createBlock('core/paragraph', { content: 'Menu' })]
				),
				createBlock(
					'designsetgo/modal',
					{
						modalId: 'dsgo-modal-e2e-int',
						displayMode: 'panel',
						panelEdge: 'left',
					},
					[createBlock('core/paragraph', { content: 'Nav' })]
				),
			]);
		});

		const url = await publishAndResolveUrl(page);
		await page.goto(url);

		const modal = page.locator('#dsgo-modal-e2e-int');
		await expect(modal).toHaveAttribute('aria-hidden', 'true');

		await page.locator('.dsgo-e2e-hamburger').click();
		await expect(modal).toHaveAttribute('aria-hidden', 'false');
	});

	test('leaves an ordinary centred modal unchanged', async ({
		page,
	}, testInfo) => {
		defineArtifact(testInfo, 'blocks', 'modal', 'offcanvas-regression');

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Centred modal regression');
		await seedModal(page, {}, 'dsgo-modal-e2e-plain');

		// No validation warning in the editor for a default-mode modal.
		const warnings = page
			.frameLocator('iframe[name="editor-canvas"]')
			.locator('[data-type="designsetgo/modal"] .block-editor-warning');
		await expect(warnings).toHaveCount(0);

		const url = await publishAndResolveUrl(page);
		await page.goto(url);

		const modal = page.locator('#dsgo-modal-e2e-plain');
		await expect(modal).not.toHaveClass(/dsgo-modal--panel/);

		await page.locator('[data-dsgo-modal-trigger]').click();
		await expect(modal).toHaveAttribute('aria-hidden', 'false');

		// Still centred, still not fixed-position.
		const centred = await page.evaluate(() => {
			const d = document.querySelector(
				'#dsgo-modal-e2e-plain .dsgo-modal__dialog'
			);
			const r = d.getBoundingClientRect();
			return {
				position: getComputedStyle(d).position,
				offset: Math.abs(
					(r.left + r.right) / 2 - window.innerWidth / 2
				),
			};
		});
		expect(centred.position).toBe('relative');
		expect(centred.offset).toBeLessThan(2);
	});
});
