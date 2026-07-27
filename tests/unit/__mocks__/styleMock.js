/**
 * Stylesheet mock for Jest.
 *
 * Source modules import their own SCSS so webpack extracts a stylesheet
 * (e.g. `src/utils/sticky-header.js` imports `./sticky-header.scss`). Jest has
 * no stylesheet loader, so those imports are mapped here and resolve to an
 * empty module. Mirrors the `fileMock.js` treatment of asset imports.
 *
 * @package
 */

module.exports = {};
