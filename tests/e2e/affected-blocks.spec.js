/**
 * E2E regression tests for blocks fixed in the fix/block-errors branch.
 *
 * Two describe blocks:
 *
 * A) Insert-and-validate — each affected block is inserted fresh, checked for
 *    invalid blocks (no "Attempt Recovery"), published, and the frontend is
 *    verified to render without a PHP critical error and without uncaught JS
 *    errors.
 *
 * B) Legacy-markup-migrates-silently — each block's captured OLD markup fixture
 *    is loaded into the editor via wp.blocks.parse + resetBlocks, then checked
 *    for zero invalid blocks. This is the direct regression guard for the
 *    deprecations added this branch: stale save-markup → "Attempt Recovery" was
 *    the bug class; a non-empty getInvalidBlockNames() here means a deprecation
 *    is broken.
 *
 *    The form-phone-field fixture contains the legacy `style="flex:1px"` value
 *    that triggered the original bug. Its deprecation MUST silently migrate it.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const {
	createNewPost,
	getEditorCanvas,
	getInvalidBlockNames,
} = require( './helpers/wordpress' );
const { insertBlockByName } = require( './helpers/blocks' );
const {
	installVideoCapture,
	installPublishedPageCleanup,
	setPostTitle,
	publishAndResolveUrl,
} = require( './helpers/artifacts' );

// Record a video per test when DSGO_RECORD_VIDEO=1 (screenshots only by default).
installVideoCapture( test );
// Delete each published page after the test so pages do not pile up.
installPublishedPageCleanup( test );

// ---------------------------------------------------------------------------
// A) Insert-and-validate
// ---------------------------------------------------------------------------

// Top-level affected blocks. form-phone-field is a child of form-builder and
// is covered by the migration test below.
const INSERT_BLOCKS = [
	'designsetgo/accordion',
	'designsetgo/slider',
	'designsetgo/form-builder',
	'designsetgo/section',
	'designsetgo/pill',
];

test.describe( 'Affected blocks — insert, validate, render', () => {
	for ( const name of INSERT_BLOCKS ) {
		test( `${ name } inserts valid and renders clean`, async ( { page } ) => {
			const pageErrors = [];
			page.on( 'pageerror', ( e ) => pageErrors.push( e.message ) );

			await createNewPost( page, 'page' );
			await setPostTitle( page, `Affected: ${ name.split( '/' ).pop() }` );

			const { clientId } = await insertBlockByName( page, name );

			// Wait for the inserted block to appear in the canvas — a real DOM
			// signal that React has finished rendering, replacing the bare timeout.
			const canvas = getEditorCanvas( page );
			await canvas
				.locator( `[data-block="${ clientId }"]` )
				.first()
				.waitFor( { state: 'attached', timeout: 10000 } );

			expect( await getInvalidBlockNames( page ) ).toEqual( [] );

			const frontendUrl = await publishAndResolveUrl( page );
			const res = await page.goto( frontendUrl );
			expect( res?.ok() ).toBeTruthy();

			await page.waitForLoadState( 'domcontentloaded' );
			expect( await page.content() ).not.toContain(
				'There has been a critical error'
			);

			expect( pageErrors ).toEqual( [] );
		} );
	}
} );

// ---------------------------------------------------------------------------
// B) Legacy-markup-migrates-silently (regression guard)
// ---------------------------------------------------------------------------

// Mapping of block name → fixture filename inside
// tests/unit/__fixtures__/patterns/.
//
// form-phone-field is explicitly included here: its save.js changed this
// branch (flex:1px deprecation) and this test directly guards that regression.
const FIXTURES = {
	'designsetgo/accordion': 'accordion-old.html',
	'designsetgo/slider': 'slider-old.html',
	'designsetgo/form-builder': 'form-builder-old.html',
	'designsetgo/form-phone-field': 'form-phone-field-old.html',
	'designsetgo/section': 'section-old.html',
	'designsetgo/pill': 'pill-old.html',
};

const FIXTURES_DIR = path.join(
	__dirname,
	'..',
	'unit',
	'__fixtures__',
	'patterns'
);

test.describe( 'Affected blocks — legacy markup migrates silently', () => {
	for ( const [ name, fixture ] of Object.entries( FIXTURES ) ) {
		test( `${ name } legacy markup migrates with no invalid blocks`, async ( { page } ) => {
			const markup = fs.readFileSync(
				path.join( FIXTURES_DIR, fixture ),
				'utf8'
			);

			await createNewPost( page, 'page' );

			// Inject the OLD serialised markup directly into the editor via the
			// block parser, bypassing the inserter UI. If the deprecation stack
			// is correct the parser silently migrates to the new format; if not,
			// the block is left isValid:false and getInvalidBlockNames returns it.
			await page.evaluate( ( html ) => {
				const blocks = window.wp.blocks.parse( html );
				window.wp.data
					.dispatch( 'core/block-editor' )
					.resetBlocks( blocks );
			}, markup );

			// Wait until the block-editor store reports at least one block
			// (the parse + resetBlocks is synchronous, but React reconciliation
			// and block validation are async). Replaces the bare timeout.
			await expect
				.poll(
					() =>
						page.evaluate( () =>
							window.wp.data
								.select( 'core/block-editor' )
								.getBlocks().length
						),
					{ timeout: 5000 }
				)
				.toBeGreaterThan( 0 );

			expect( await getInvalidBlockNames( page ) ).toEqual( [] );
		} );
	}
} );
