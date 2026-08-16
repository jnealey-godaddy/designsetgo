/**
 * Interaction Layers - The hidden-state class
 *
 * Deliberately its own module with no imports. Both the editor constants and
 * the frontend runtime need this name, but the frontend must not pull in
 * `constants.js` — that module imports @wordpress/i18n for its labels, which
 * would drag `wp.i18n` into a bundle that has no WordPress script
 * dependencies and crash the runtime on load.
 *
 * @package
 */

/** The class the visibility actions add and remove. */
export const HIDDEN_CLASS = 'dsgo-interaction-hidden';
