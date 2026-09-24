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

// Switched off on the Blocks & Extensions screen, Draft Mode shows no sidebar
// panel; Draft_Mode::is_enabled() stops new drafts being created to match.
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
}

// Register the draft mode controls plugin (post status info area controls).
// Always registered: with Draft Mode off it renders nothing, but its publish
// intercept still merges a draft copy that already exists into its original.
// Without it, Publish on that copy would make it a second live page.
registerPlugin('dsgo-draft-mode-controls', {
	render: () => (
		<Suspense fallback={null}>
			<DraftModeControls />
		</Suspense>
	),
});
