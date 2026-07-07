/**
 * E2E: Section Styles — Editor Preview overlay
 *
 * Regression coverage for the editor-only overlay that mirrors a
 * *user-customized* (Global Styles) section-style variation onto DSGo
 * container blocks in the editor canvas.
 *
 * This specifically exercises the USER layer, which is the gap the overlay
 * fixes: WordPress does not natively apply a Global-Styles customization of a
 * core-container section style to DSGo blocks in the editor, so a border/radius
 * appearing on the DSGo section in the canvas proves the overlay ran. (Theme /
 * plugin-registered variation *styles* preview natively and would pass without
 * the overlay, so they wouldn't test anything — only the user-layer border is
 * the overlay's responsibility.)
 *
 * Self-contained: a mu-plugin registers a throwaway section style (so it isn't
 * stripped by theme.json sanitization and is mirrored onto DSGo blocks), and a
 * user-layer border is set on it. Both are removed in teardown.
 *
 * @package
 */

const { test, expect } = require('@playwright/test');
const { cli, shellArg, deletePostIds } = require('./helpers/wp-cli');
const { getEditorCanvas } = require('./helpers/wordpress');

// Throwaway slug + a distinctive border only ever set here, so the assertion
// can't accidentally match a theme default.
const SLUG = 'dsgoe2e';
const BORDER = {
	color: '#123456', // -> rgb(18, 52, 86)
	colorRgb: 'rgb(18, 52, 86)',
	width: '7px',
	style: 'solid',
	radius: '13px',
};
const MU_FILE = 'dsgo-e2e-section-style.php';

// This test mutates shared server state (a mu-plugin + the user Global Styles
// entity), so it must run on a single project to avoid cross-worker races. The
// overlay under test is browser-agnostic editor JS, so one browser suffices.
const PRIMARY_PROJECT = 'chromium';

// mu-plugin registering the section style with `style_data`, so it survives
// theme.json sanitization and Section_Styles mirrors it onto the DSGo blocks
// (name-only registrations are not mirrored). The border itself comes from the
// user (Global Styles) layer, set separately.
const MU_PLUGIN = `<?php
add_action( 'init', function () {
	register_block_style( 'core/group', array(
		'name'       => '${SLUG}',
		'label'      => 'DSGo E2E Section Style',
		'style_data' => array( 'color' => array( 'background' => '#eeeeee' ) ),
	) );
}, 5 );
`;

function evalPhp(php) {
	cli(`wp eval ${shellArg(php)}`);
}

function writeMuPlugin() {
	const b64 = Buffer.from(MU_PLUGIN).toString('base64');
	evalPhp(
		`if ( ! is_dir( WPMU_PLUGIN_DIR ) ) { wp_mkdir_p( WPMU_PLUGIN_DIR ); } ` +
			`file_put_contents( WPMU_PLUGIN_DIR . "/${MU_FILE}", base64_decode( "${b64}" ) ); echo "ok";`
	);
}

function removeMuPlugin() {
	evalPhp(
		`$f = WPMU_PLUGIN_DIR . "/${MU_FILE}"; if ( file_exists( $f ) ) { unlink( $f ); } echo "ok";`
	);
}

function setUserGlobalStylesBorder() {
	evalPhp(
		`$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		$gs = json_decode( get_post( $id )->post_content, true );
		if ( ! is_array( $gs ) ) { $gs = array( "version" => 3, "isGlobalStylesUserThemeJSON" => true ); }
		$gs["styles"]["blocks"]["core/group"]["variations"]["${SLUG}"]["border"] = array(
			"color" => "${BORDER.color}", "width" => "${BORDER.width}",
			"style" => "${BORDER.style}", "radius" => "${BORDER.radius}"
		);
		wp_update_post( array( "ID" => $id, "post_content" => wp_slash( wp_json_encode( $gs ) ) ) );
		echo "ok";`
	);
}

function clearUserGlobalStyles() {
	evalPhp(
		`$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		$gs = json_decode( get_post( $id )->post_content, true );
		if ( is_array( $gs ) ) {
			unset( $gs["styles"]["blocks"]["core/group"]["variations"]["${SLUG}"] );
			wp_update_post( array( "ID" => $id, "post_content" => wp_slash( wp_json_encode( $gs ) ) ) );
		}
		echo "ok";`
	);
}

test.describe('Section styles — editor preview overlay', () => {
	let pageId;

	// Skip on every non-primary project before any browser launches or fixtures
	// are set up (a plain `test.skip()` inside the test body runs too late — the
	// page/browser is already created).
	test.beforeEach(({}, testInfo) => {
		test.skip(
			testInfo.project.name !== PRIMARY_PROJECT,
			'editor overlay logic is browser-agnostic; runs once on chromium'
		);
	});

	test.beforeAll(({}, testInfo) => {
		if (testInfo.project.name !== PRIMARY_PROJECT) {
			return;
		}
		writeMuPlugin();
		setUserGlobalStylesBorder();

		const markup = [
			`<!-- wp:designsetgo/section {"className":"is-style-${SLUG}"} -->`,
			`<div class="wp-block-designsetgo-section dsgo-stack is-style-${SLUG}"><div class="dsgo-stack__inner"><!-- wp:paragraph --><p>Section style preview E2E</p><!-- /wp:paragraph --></div></div>`,
			`<!-- /wp:designsetgo/section -->`,
		].join('\n');

		const out = cli(
			'wp post create --post_type=page --post_status=publish --porcelain ' +
				`--post_title=${shellArg('DSGo Section Style Preview E2E')} ` +
				`--post_content=${shellArg(markup)}`
		);
		pageId = (out.match(/\d+/) || [])[0];
		expect(pageId, 'created page id').toBeTruthy();
	});

	test.afterAll(({}, testInfo) => {
		if (testInfo.project.name !== PRIMARY_PROJECT) {
			return;
		}
		if (pageId) {
			deletePostIds([pageId]);
		}
		clearUserGlobalStyles();
		removeMuPlugin();
	});

	test('previews a user-customized section-style border/radius on a DSGo section', async ({
		page,
	}) => {
		await page.goto(`/wp-admin/post.php?post=${pageId}&action=edit`);

		await page
			.locator('iframe[name="editor-canvas"]')
			.waitFor({ timeout: 30000 });

		// Dismiss the welcome guide if it appears.
		try {
			await page
				.locator('.components-modal__header button[aria-label="Close"]')
				.first()
				.click({ timeout: 2000 });
		} catch {
			// No modal.
		}

		const canvas = getEditorCanvas(page);
		const section = canvas.locator('.wp-block-designsetgo-section').first();
		await section.waitFor({ state: 'attached', timeout: 15000 });

		// Core does NOT apply a user-layer variation to DSGo blocks in the
		// editor, so this border only appears because the overlay ran. Poll to
		// absorb the reactive inject timing.
		await expect
			.poll(
				async () =>
					section.evaluate((el) => {
						const cs = window.getComputedStyle(el);
						return {
							width: cs.borderTopWidth,
							style: cs.borderTopStyle,
							color: cs.borderTopColor,
							radius: cs.borderTopLeftRadius,
						};
					}),
				{ timeout: 15000 }
			)
			.toEqual({
				width: BORDER.width,
				style: BORDER.style,
				color: BORDER.colorRgb,
				radius: BORDER.radius,
			});

		// The overlay <style> element is what supplies it.
		await expect(
			canvas.locator('style#dsgo-section-style-preview')
		).toHaveCount(1);
	});
});
