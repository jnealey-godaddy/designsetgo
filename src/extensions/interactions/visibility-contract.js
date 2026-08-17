/**
 * Interaction Layers - The visibility contract
 *
 * The class name and action names that both the editor and the frontend
 * runtime need to agree on.
 *
 * Deliberately its own module with no imports. `constants.js` cannot hold
 * these: it imports @wordpress/i18n for its labels, and webpack externalises
 * that to `wp.i18n` — a global the frontend bundle never loads, because the
 * bundle is registered with no script dependencies. One import of
 * `constants.js` from the runtime therefore throws on page load and disables
 * every interaction on the site.
 *
 * `tests/unit/extensions/interactions-frontend-deps.test.js` enforces this.
 *
 * @package
 */

/** The class the visibility actions add and remove. */
export const HIDDEN_CLASS = 'dsgo-interaction-hidden';

/** Actions that show or hide their target. */
export const VISIBILITY_ACTIONS = ['show', 'hide', 'toggleVisibility'];
