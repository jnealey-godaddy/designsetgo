/**
 * Draft Mode Extension
 *
 * Adds "draft mode" functionality to the block editor, allowing users
 * to create draft versions of published pages. Drafts are created
 * automatically when editing published pages.
 *
 * @package
 * @since 1.4.0
 */

import { registerPlugin } from '@wordpress/plugins';
import { lazy, Suspense } from '@wordpress/element';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

import './editor.scss';

// Lazy-load draft mode components to reduce initial bundle size
const DraftModePanel = lazy(
	() => import(/* webpackChunkName: "ext-draft-mode" */ './DraftModePanel')
);
const DraftModeControls = lazy(
	() => import(/* webpackChunkName: "ext-draft-mode" */ './DraftModeControls')
);

// Switched off on the Blocks & Extensions screen, Draft Mode registers no
// editor UI; Draft_Mode::is_enabled() turns off its REST routes and admin
// actions to match.
if (isExtensionEnabled('draft-mode')) {
	// Register the draft mode panel plugin (sidebar + auto-detection + controls).
	registerPlugin('dsgo-draft-mode', {
		render: () => (
			<Suspense fallback={null}>
				<DraftModePanel />
			</Suspense>
		),
		icon: 'edit-page',
	});

	// Register the draft mode controls plugin (post status info area controls).
	registerPlugin('dsgo-draft-mode-controls', {
		render: () => (
			<Suspense fallback={null}>
				<DraftModeControls />
			</Suspense>
		),
	});
}
