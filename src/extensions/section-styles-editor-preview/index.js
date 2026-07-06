/**
 * Section Styles — Editor Preview
 *
 * Makes user-customized (Global Styles) section-style variations preview on the
 * DesignSetGo container blocks in the editor canvas.
 *
 * WordPress mirrors theme/plugin section styles onto DSGo blocks server-side
 * (`DesignSetGo\Section_Styles`), which covers the frontend and the editor's
 * base config. But the editor generates block-style-variation CSS client-side
 * from the browser-merged global-styles config, which never runs that mirror
 * for the user layer — so a border/radius an author adds to a section style in
 * Global Styles is missing from the DSGo block's editor preview (the saved
 * frontend output is correct). Core's own fix for this is a locked private API.
 *
 * This module reproduces the user-layer variation styles as a low-cost CSS
 * overlay, scoped to the stable `is-style-{slug}` class, injected into the
 * editor canvas and updated reactively.
 *
 * @package
 */

import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useEffect, useRef } from '@wordpress/element';

import { buildVariationCss } from './generate-css';

const STYLE_ELEMENT_ID = 'dsgo-section-style-preview';

// Whether the missing-API warning has fired (dev only). See the note in
// SectionStylePreview.
let warnedMissingApi = false;

/**
 * Collect the documents that make up the editor canvas. Handles both the
 * iframed canvas (post/site editor) and the rare non-iframed case.
 *
 * @return {Document[]} Canvas documents to inject into.
 */
function getCanvasDocuments() {
	const docs = [];

	document
		.querySelectorAll('iframe[name="editor-canvas"]')
		.forEach((iframe) => {
			try {
				if (iframe.contentDocument) {
					docs.push(iframe.contentDocument);
				}
			} catch (e) {
				// Cross-origin (never happens for the local canvas) — ignore.
			}
		});

	// Non-iframed fallback: the editor writing flow lives in the main document
	// (legacy/classic path; modern editors iframe the canvas). Here the overlay
	// goes in the top-level <head> and is scoped only by its class selector
	// (`.wp-block-designsetgo-{suffix}.is-style-{slug}`). Those classes only
	// appear on DSGo blocks in the editor content, so a stray match elsewhere on
	// the admin screen is effectively impossible — an accepted tradeoff for this
	// rare path rather than prefixing every selector with the wrapper.
	if (!docs.length && document.querySelector('.editor-styles-wrapper')) {
		docs.push(document);
	}

	return docs;
}

/**
 * Inject or update the overlay `<style>` in each canvas document.
 *
 * @param {string} css Stylesheet text.
 */
function injectStyles(css) {
	getCanvasDocuments().forEach((doc) => {
		const head = doc.head || doc.documentElement;
		if (!head) {
			return;
		}

		let element = doc.getElementById(STYLE_ELEMENT_ID);

		if (!css) {
			if (element) {
				element.textContent = '';
			}
			return;
		}

		if (!element) {
			element = doc.createElement('style');
			element.id = STYLE_ELEMENT_ID;
			head.appendChild(element);
		}

		if (element.textContent !== css) {
			element.textContent = css;
		}
	});
}

/**
 * Editor component: reads the live user global-styles config and keeps the
 * canvas overlay in sync. Renders nothing.
 *
 * @return {null} No visible output.
 */
function SectionStylePreview() {
	const css = useSelect((select) => {
		const core = select(coreStore);

		// NOTE: `__experimentalGetCurrentGlobalStylesId` is an experimental
		// core-data selector — there is no stable public equivalent for the
		// current global-styles entity id. If a future WP release renames or
		// removes it, this overlay quietly stops working; surface that in dev
		// so it isn't a silent "border stopped previewing" mystery. See the
		// Known limitations section of the plan doc.
		const getId = core.__experimentalGetCurrentGlobalStylesId;
		if (typeof getId !== 'function') {
			if (!warnedMissingApi && process.env.NODE_ENV !== 'production') {
				warnedMissingApi = true;
				// eslint-disable-next-line no-console
				console.warn(
					'DesignSetGo: core-data selector __experimentalGetCurrentGlobalStylesId is unavailable; section-style editor preview is disabled.'
				);
			}
			return '';
		}

		const id = getId();
		if (!id) {
			return '';
		}

		const record = core.getEditedEntityRecord('root', 'globalStyles', id);
		return buildVariationCss(record?.styles?.blocks);
	}, []);

	// Keep the latest CSS available to deferred/observer callbacks without
	// re-subscribing, and so stale retries always write the current value.
	const cssRef = useRef(css);
	cssRef.current = css;

	// Inject now plus a couple of short retries — a freshly (re)mounted canvas
	// iframe may not have its document ready on the first attempt.
	const scheduleInject = useRef(() => {
		injectStyles(cssRef.current);
		window.setTimeout(() => injectStyles(cssRef.current), 150);
		window.setTimeout(() => injectStyles(cssRef.current), 600);
	}).current;

	// Re-inject whenever the computed CSS changes.
	useEffect(() => {
		scheduleInject();
	}, [css, scheduleInject]);

	// Re-assert the overlay when the canvas iframe (re)mounts — switching
	// devices, entering/leaving fullscreen, etc. recreate the canvas document
	// and drop the overlay. Observe the visual-editor wrapper rather than
	// `document.body`: the wrapper only mutates when the iframe itself is
	// swapped (typing/selection churn happens inside the iframe's own
	// document, which this observer does not see), so it fires rarely. The
	// debounce coalesces bursts and `injectStyles` is idempotent, so a
	// settle-triggered re-inject is a no-op unless the canvas was replaced.
	useEffect(() => {
		let observer = null;
		let debounce = null;
		let retry = null;
		let attempts = 0;

		const attach = () => {
			const root = document.querySelector(
				'.editor-visual-editor, .edit-post-visual-editor, .edit-site-visual-editor, .interface-interface-skeleton__content'
			);

			// If the editor shell hasn't rendered its wrapper yet (this plugin
			// component can mount first), retry briefly rather than permanently
			// falling back to document.body — which would reintroduce the broad
			// observation this scoping avoids. Use body only as a last resort.
			if (!root && attempts < 10) {
				attempts += 1;
				retry = window.setTimeout(attach, 300);
				return;
			}

			observer = new window.MutationObserver(() => {
				if (debounce) {
					window.clearTimeout(debounce);
				}
				debounce = window.setTimeout(scheduleInject, 300);
			});
			observer.observe(root || document.body, {
				childList: true,
				subtree: true,
			});
		};
		attach();

		return () => {
			if (retry) {
				window.clearTimeout(retry);
			}
			if (debounce) {
				window.clearTimeout(debounce);
			}
			if (observer) {
				observer.disconnect();
			}
		};
	}, [scheduleInject]);

	return null;
}

registerPlugin('dsgo-section-style-preview', {
	render: SectionStylePreview,
});
