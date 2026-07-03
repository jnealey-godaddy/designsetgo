/**
 * E2E Tests for blocks converted from static to dynamic (server-rendered).
 *
 * Each converted leaf block must satisfy two guarantees:
 *  1. A freshly inserted block is valid in the editor (no "Attempt Recovery"
 *     warning) and renders its real inline SVG on the frontend — NOT the old
 *     `.dsgo-lazy-icon` placeholder, since the SVG now ships from render.php.
 *  2. Legacy STATIC markup (the pre-conversion lazy-placeholder format) parses
 *     through the block's new deprecation and migrates silently — i.e. it never
 *     reports `isValid: false`, which is what would surface the recovery UI.
 *
 * The migration check runs the exact parse+deprecation pipeline the editor uses
 * on load via `wp.blocks.parse()`, then inspects the resulting block tree.
 */

const { test, expect } = require('@playwright/test');
const {
	getEditorCanvas,
	createNewPost,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const {
	defineArtifact,
	saveScreenshot,
	installVideoCapture,
	installPublishedPageCleanup,
	slowScrollToBottom,
	setPostTitle,
	publishAndResolveUrl,
} = require('./helpers/artifacts');

installVideoCapture(test);
installPublishedPageCleanup(test);

// Pre-conversion STATIC markup (lazy-icon placeholder). Feeding this through
// wp.blocks.parse() must migrate to the dynamic block without going invalid.
const LEGACY_ICON = `<!-- wp:designsetgo/icon {"icon":"heart","iconSize":40} -->
<div class="wp-block-designsetgo-icon dsgo-icon" style="display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="heart" data-icon-stroke-width="1.5" role="img" aria-label="Heart"></div></div>
<!-- /wp:designsetgo/icon -->`;

const LEGACY_DIVIDER = `<!-- wp:designsetgo/divider {"dividerStyle":"icon","iconName":"star"} -->
<div class="wp-block-designsetgo-divider dsgo-divider dsgo-divider--icon"><div class="dsgo-divider__container" style="width:100%"><div class="dsgo-divider__icon-wrapper"><span class="dsgo-divider__line dsgo-divider__line--left" style="height:2px"></span><span class="dsgo-divider__icon dsgo-lazy-icon" data-icon-name="star"></span><span class="dsgo-divider__line dsgo-divider__line--right" style="height:2px"></span></div></div></div>
<!-- /wp:designsetgo/divider -->`;

// Authentic pre-conversion markup from the site-designer contact patterns
// (block-patterns/patterns/contact). This is the exact shape that used to throw
// "Block validation: Expected text ..., saw ...": the block comment carries no
// `placeholder` attribute (so save() expected the English default
// "-- Select an option --") while the stored inner HTML holds the substituted
// "-- {{formSelectDefaultLabel}} --". The new deprecation must migrate it
// silently now that the field is server-rendered.
const LEGACY_SELECT_PATTERN = `<!-- wp:designsetgo/form-select-field {"fieldName":"select_interest_re","label":"{{selectFieldLabel}}","options":[{"value":"{{selectOption1Value}}","label":"{{selectOption1Label}}"},{"value":"{{selectOption2Value}}","label":"{{selectOption2Label}}"},{"value":"{{selectOption3Value}}","label":"{{selectOption3Label}}"},{"value":"{{selectOption4Value}}","label":"{{selectOption4Label}}"}]} -->
<div class="wp-block-designsetgo-form-select-field dsgo-form-field dsgo-form-field--select" style="flex-basis:100%;max-width:100%"><label for="field-select_interest_re" class="dsgo-form-field__label">{{selectFieldLabel}}</label><select id="field-select_interest_re" name="select_interest_re" class="dsgo-form-field__select" data-field-type="select"><option value="">-- {{formSelectDefaultLabel}} --</option><option value="{{selectOption1Value}}">{{selectOption1Label}}</option><option value="{{selectOption2Value}}">{{selectOption2Label}}</option><option value="{{selectOption3Value}}">{{selectOption3Label}}</option><option value="{{selectOption4Value}}">{{selectOption4Label}}</option></select></div>
<!-- /wp:designsetgo/form-select-field -->`;

// A required text field whose label has been translated (non-English) — the
// static save baked the translated text into the HTML, which is precisely what
// diverged from the English default and tripped validation before this change.
const LEGACY_TEXT_TRANSLATED = `<!-- wp:designsetgo/form-text-field {"fieldName":"nom","label":"Nom complet","required":true} -->
<div class="wp-block-designsetgo-form-text-field dsgo-form-field dsgo-form-field--text" style="flex-basis:100%;max-width:100%"><label for="field-nom" class="dsgo-form-field__label">Nom complet<span class="dsgo-form-field__required" aria-label="required">*</span></label><input type="text" id="field-nom" name="nom" class="dsgo-form-field__input" required aria-required="true" data-field-type="text"/></div>
<!-- /wp:designsetgo/form-text-field -->`;

// Authentic map markup from contact-form-with-map.html: the marker colour and a
// border-radius style attribute are both baked in. Removing/changing either used
// to invalidate the block.
const LEGACY_MAP_PATTERN = `<!-- wp:designsetgo/map {"dsgoAddress":"{{address}}","dsgoLatitude":0,"dsgoLongitude":0,"style":{"border":{"radius":"16px"}}} -->
<div class="wp-block-designsetgo-map dsgo-map" style="border-radius:16px;height:400px" data-dsgo-provider="openstreetmap" data-dsgo-lat="0" data-dsgo-lng="0" data-dsgo-zoom="13" data-dsgo-address="{{address}}" data-dsgo-marker-icon="📍" data-dsgo-marker-color="#e74c3c" data-dsgo-privacy-mode="false" data-dsgo-map-style="standard"><div class="dsgo-map__container" role="region" aria-label="Map showing {{address}}"></div></div>
<!-- /wp:designsetgo/map -->`;

// A form-builder whose wrapper baked the spacing tokens (--dsgo-form-field-spacing
// / --dsgo-form-input-height / --dsgo-form-input-padding) and whose submit button
// baked min-height + padding — the default output before those became nullable.
// The v5 deprecation must migrate it silently AND strip the default-valued tokens
// so the re-serialized form no longer carries them (it inherits the theme).
const LEGACY_FORM_BUILDER = `<!-- wp:designsetgo/form-builder {"formId":"883a7cd1"} -->
<div class="wp-block-designsetgo-form-builder dsgo-form-builder dsgo-form-builder--align-left" style="--dsgo-form-field-spacing:1.5rem;--dsgo-form-input-height:44px;--dsgo-form-input-padding:0.75rem" data-form-id="883a7cd1" data-ajax-submit="true" data-success-message="Thank you! Your form has been submitted successfully." data-error-message="There was an error submitting the form. Please try again." data-submit-text="Submit"><form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields"><!-- wp:designsetgo/form-text-field {"fieldName":"name","label":"Name","required":true} /--></div><input type="text" name="dsg_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"/><input type="hidden" name="dsg_form_id" value="883a7cd1"/><div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit wp-element-button" style="min-height:44px;padding-top:0.75rem;padding-bottom:0.75rem;padding-left:2rem;padding-right:2rem">Submit</button></div><div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div></form></div>
<!-- /wp:designsetgo/form-builder -->`;

/**
 * Insert a block programmatically from name + attributes (registry-backed).
 *
 * @param {import('@playwright/test').Page} page       - Playwright page.
 * @param {string}                          name       - Block name.
 * @param {Object}                          attributes - Block attributes.
 */
async function insertBlock(page, name, attributes) {
	await page.evaluate(
		({ blockName, attrs }) => {
			const block = wp.blocks.createBlock(blockName, attrs);
			wp.data.dispatch('core/block-editor').insertBlocks([block]);
		},
		{ blockName: name, attrs: attributes }
	);
	await page.waitForTimeout(500);
}

/**
 * Parse raw serialized markup through the block pipeline and insert it. This is
 * the same path the editor uses on post load, so it exercises deprecations.
 *
 * @param {import('@playwright/test').Page} page   - Playwright page.
 * @param {string}                          markup - Serialized block markup.
 */
async function insertRawMarkup(page, markup) {
	await page.evaluate((html) => {
		const blocks = wp.blocks.parse(html);
		wp.data.dispatch('core/block-editor').insertBlocks(blocks);
	}, markup);
	await page.waitForTimeout(500);
}

/**
 * Run markup through `wp.blocks.parse()` (the exact parse + deprecation pipeline
 * the editor runs on post load) and return the names of any block that ended up
 * `isValid: false`. Unlike getInvalidBlockNames() this never inserts into the
 * store, so it works for `parent`-restricted blocks (the form fields) without a
 * form-builder wrapper. Also returns the full parsed name list so the caller can
 * assert the target block was actually present (guards against a vacuous pass).
 *
 * @param {import('@playwright/test').Page} page   - Playwright page.
 * @param {string}                          markup - Serialized block markup.
 * @return {Promise<{ invalid: string[], names: string[] }>} Parse result.
 */
async function parseValidity(page, markup) {
	return page.evaluate((html) => {
		const invalid = [];
		const names = [];
		const walk = (blocks) => {
			for (const b of blocks) {
				if (b.name) {
					names.push(b.name);
				}
				if (b.isValid === false) {
					invalid.push(b.name);
				}
				if (b.innerBlocks) {
					walk(b.innerBlocks);
				}
			}
		};
		walk(wp.blocks.parse(html));
		return { invalid: Array.from(new Set(invalid)), names };
	}, markup);
}

// ---------------------------------------------------------------------------
// Icon block
// ---------------------------------------------------------------------------
test.describe('Icon block — dynamic render', () => {
	test('fresh insert is valid and renders inline SVG on the frontend', async ({
		page,
	}, testInfo) => {
		const artifact = defineArtifact(
			testInfo,
			'blocks',
			'icon',
			'dynamic-render'
		);

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Icon Dynamic Render');
		await insertBlock(page, 'designsetgo/icon', {
			icon: 'star',
			iconSize: 64,
		});

		const canvas = getEditorCanvas(page);
		await expect(
			canvas.locator('[data-type="designsetgo/icon"]').first()
		).toBeVisible({ timeout: 10000 });

		// No failed validation, no recovery warning.
		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/icon'
		);
		await expect(
			canvas.locator(
				'[data-type="designsetgo/icon"] .block-editor-warning'
			)
		).toHaveCount(0);
		await saveScreenshot(page, artifact, 'editor');

		const frontendUrl = await publishAndResolveUrl(page);
		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		// Real inline SVG is present; the old lazy placeholder is gone.
		await expect(
			page.locator('.wp-block-designsetgo-icon svg').first()
		).toBeVisible({ timeout: 10000 });
		expect(
			await page
				.locator('.wp-block-designsetgo-icon .dsgo-lazy-icon')
				.count()
		).toBe(0);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});

	test('legacy static markup migrates silently (no invalid content)', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Icon Legacy Migration');
		await insertRawMarkup(page, LEGACY_ICON);

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/icon'
		);
	});
});

// ---------------------------------------------------------------------------
// Divider block
// ---------------------------------------------------------------------------
test.describe('Divider block — dynamic render', () => {
	test('fresh icon-style insert is valid and renders inline SVG on the frontend', async ({
		page,
	}, testInfo) => {
		const artifact = defineArtifact(
			testInfo,
			'blocks',
			'divider',
			'dynamic-render'
		);

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Divider Dynamic Render');
		await insertBlock(page, 'designsetgo/divider', {
			dividerStyle: 'icon',
			iconName: 'heart',
		});

		const canvas = getEditorCanvas(page);
		await expect(
			canvas.locator('[data-type="designsetgo/divider"]').first()
		).toBeVisible({ timeout: 10000 });

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/divider'
		);
		await expect(
			canvas.locator(
				'[data-type="designsetgo/divider"] .block-editor-warning'
			)
		).toHaveCount(0);
		await saveScreenshot(page, artifact, 'editor');

		const frontendUrl = await publishAndResolveUrl(page);
		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		await expect(
			page.locator('.wp-block-designsetgo-divider svg').first()
		).toBeVisible({ timeout: 10000 });
		expect(
			await page
				.locator('.wp-block-designsetgo-divider .dsgo-lazy-icon')
				.count()
		).toBe(0);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});

	test('legacy static markup migrates silently (no invalid content)', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Divider Legacy Migration');
		await insertRawMarkup(page, LEGACY_DIVIDER);

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/divider'
		);
	});
});

// ---------------------------------------------------------------------------
// Form fields — dynamic render + migration
//
// The headline fix: form fields are server-rendered, so authored / translated /
// site-designer-substituted label + placeholder + option text is no longer
// baked into stored HTML and can never trip block validation.
// ---------------------------------------------------------------------------
test.describe('Form fields — dynamic render', () => {
	test('a freshly inserted field serializes valid (no stored HTML to diff)', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Form Field Fresh Insert');

		// A dynamic field with a translated label + placeholder — the shape that
		// used to invalidate. Fresh insert must round-trip clean.
		const { invalid, names } = await parseValidity(
			page,
			'<!-- wp:designsetgo/form-select-field {"fieldName":"choix","label":"Choisissez une option","placeholder":"-- Choisir --","options":[{"label":"Café","value":"cafe"},{"label":"Thé","value":"the"}]} /-->'
		);
		expect(names).toContain('designsetgo/form-select-field');
		expect(invalid).toEqual([]);
	});

	test('authentic contact-pattern select markup migrates silently', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Select Pattern Migration');

		// This exact markup previously threw "Expected text -- Select an option
		// --, saw -- {{formSelectDefaultLabel}} --". It must now migrate silently.
		const { invalid, names } = await parseValidity(
			page,
			LEGACY_SELECT_PATTERN
		);
		expect(names).toContain('designsetgo/form-select-field');
		expect(invalid).toEqual([]);
	});

	test('a translated static text field migrates silently', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Text Field Translated Migration');

		const { invalid, names } = await parseValidity(
			page,
			LEGACY_TEXT_TRANSLATED
		);
		expect(names).toContain('designsetgo/form-text-field');
		expect(invalid).toEqual([]);
	});
});

// ---------------------------------------------------------------------------
// Map block — dynamic render + migration
// ---------------------------------------------------------------------------
test.describe('Map block — dynamic render', () => {
	test('fresh insert is valid and ships the resolved marker colour on the frontend', async ({
		page,
	}, testInfo) => {
		const artifact = defineArtifact(
			testInfo,
			'blocks',
			'map',
			'dynamic-render'
		);

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Map Dynamic Render');
		await insertBlock(page, 'designsetgo/map', {
			dsgoAddress: 'Paris, France',
		});

		const canvas = getEditorCanvas(page);
		await expect(
			canvas.locator('[data-type="designsetgo/map"]').first()
		).toBeVisible({ timeout: 10000 });

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/map'
		);
		await expect(
			canvas.locator(
				'[data-type="designsetgo/map"] .block-editor-warning'
			)
		).toHaveCount(0);
		await saveScreenshot(page, artifact, 'editor');

		const frontendUrl = await publishAndResolveUrl(page);
		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		// render.php resolved the default marker colour into the data attribute
		// that view.js reads (no attribute was set, no kit override, no filter).
		await expect(
			page.locator(
				'.wp-block-designsetgo-map[data-dsgo-marker-color="#e74c3c"]'
			)
		).toHaveCount(1);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});

	test('authentic contact-pattern map markup (marker colour + border) migrates silently', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Map Pattern Migration');
		await insertRawMarkup(page, LEGACY_MAP_PATTERN);

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/map'
		);

		// Belt and braces: the raw parse pipeline agrees.
		const { invalid, names } = await parseValidity(
			page,
			LEGACY_MAP_PATTERN
		);
		expect(names).toContain('designsetgo/map');
		expect(invalid).toEqual([]);
	});
});

// ---------------------------------------------------------------------------
// Form builder — spacing/sizing tokens are removable + inherit the theme
// ---------------------------------------------------------------------------
test.describe('Form builder — inheritable tokens', () => {
	test('a form that baked the spacing tokens migrates silently AND drops them so it inherits the theme', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Form Builder Token Migration');

		const result = await page.evaluate((html) => {
			const blocks = wp.blocks.parse(html);
			const fb = blocks.find(
				(b) => b.name === 'designsetgo/form-builder'
			);
			// Re-serialize through the current save() with the migrated attrs.
			const serialized = wp.blocks.serialize(blocks);
			return {
				found: !!fb,
				isValid: fb ? fb.isValid : null,
				inputHeight: fb ? fb.attributes.inputHeight : null,
				fieldSpacing: fb ? fb.attributes.fieldSpacing : null,
				serialized,
			};
		}, LEGACY_FORM_BUILDER);

		// Migrated silently (no invalid content / recovery warning).
		expect(result.found).toBe(true);
		expect(result.isValid).toBe(true);

		// The default-valued tokens were stripped to inherit.
		expect(result.inputHeight).toBe('');
		expect(result.fieldSpacing).toBe('');

		// So the re-serialized wrapper no longer bakes the tokens, and the submit
		// button no longer forces its own sizing (it inherits wp-element-button).
		expect(result.serialized).not.toContain('--dsgo-form-input-height');
		expect(result.serialized).not.toContain('--dsgo-form-field-spacing');
		expect(result.serialized).not.toContain('min-height:44px');
	});
});
