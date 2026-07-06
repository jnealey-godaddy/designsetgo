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

	// Non-iframed fallback: the editor writing flow lives in the main document.
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
		const getId = core.__experimentalGetCurrentGlobalStylesId;
		const id = typeof getId === 'function' ? getId() : null;
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
		const root =
			document.querySelector(
				'.editor-visual-editor, .edit-post-visual-editor, .edit-site-visual-editor, .interface-interface-skeleton__content'
			) || document.body;

		let timer = null;
		const observer = new window.MutationObserver(() => {
			if (timer) {
				window.clearTimeout(timer);
			}
			timer = window.setTimeout(scheduleInject, 300);
		});
		observer.observe(root, { childList: true, subtree: true });
		return () => {
			if (timer) {
				window.clearTimeout(timer);
			}
			observer.disconnect();
		};
	}, [scheduleInject]);

	return null;
}

registerPlugin('dsgo-section-style-preview', {
	render: SectionStylePreview,
});
