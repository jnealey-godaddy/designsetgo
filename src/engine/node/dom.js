/**
 * Installs jsdom globals for the Node engine CLI.
 *
 * `@wordpress/blocks`, `@wordpress/block-library`, and the plugin's own
 * `edit`/`save` modules assume a browser DOM exists at module-evaluation
 * time (not just call time) — `@wordpress/dom-ready`, `RichText`, and
 * several block-support helpers touch `document`/`window` as soon as they're
 * required. This module must be the very first thing `require()`d by the
 * Node CLI, before any `@wordpress/*` module loads, or those modules throw
 * on missing globals.
 *
 * CommonJS (not ESM `import`) so a bundler can't hoist a later `@wordpress/*`
 * import above this file's evaluation — see `boot.js` and `cli.js`.
 *
 * Idempotent: every global is defined only when absent, so requiring this
 * file twice (or running under an environment that already has a DOM, like
 * Jest's jsdom test environment) is a no-op.
 */
'use strict';

const { JSDOM, VirtualConsole } = require('jsdom');

/** Globals mirrored from the jsdom `window` onto `globalThis`. */
const WINDOW_GLOBALS = [
	'document',
	'navigator',
	'Node',
	'Element',
	'HTMLElement',
	'DOMParser',
	'MutationObserver',
	'Event',
	'CustomEvent',
	'getComputedStyle',
	'requestAnimationFrame',
	'cancelAnimationFrame',
];

/**
 * Defines `globalThis[key] = value` only when `key` isn't already present,
 * so a real browser-like environment (or a previous call) is never clobbered.
 *
 * @param {string} key   Global property name.
 * @param {*}      value Value to install.
 */
function defineIfAbsent(key, value) {
	if (typeof globalThis[key] !== 'undefined') {
		return;
	}
	Object.defineProperty(globalThis, key, {
		configurable: true,
		writable: true,
		value,
	});
}

/**
 * A `matchMedia` stub matching the legacy `MediaQueryList` shape WordPress's
 * responsive helpers expect. jsdom doesn't implement CSS media evaluation,
 * so this always reports "no match" rather than throwing.
 *
 * @return {{matches: boolean, addListener: Function, removeListener: Function, addEventListener: Function, removeEventListener: Function}}
 *   Stub `MediaQueryList`.
 */
function matchMediaStub() {
	return {
		matches: false,
		addListener() {},
		removeListener() {},
		addEventListener() {},
		removeEventListener() {},
	};
}

function installDom() {
	if (typeof globalThis.window !== 'undefined') {
		return;
	}

	// jsdom's default console forwards CSS-parse errors (WordPress's admin
	// stylesheets aren't loaded, but block save() output can still reference
	// unrecognized at-rules) straight to stderr as "jsdomError" events. The
	// CLI's stderr must carry only its own messages, so silence those here
	// rather than filtering them out downstream.
	const virtualConsole = new VirtualConsole();
	virtualConsole.on('jsdomError', () => {});

	const dom = new JSDOM('<!doctype html><html><body></body></html>', {
		pretendToBeVisual: true,
		url: 'http://localhost/',
		virtualConsole,
	});

	if (typeof dom.window.matchMedia !== 'function') {
		dom.window.matchMedia = matchMediaStub;
	}

	defineIfAbsent('window', dom.window);
	WINDOW_GLOBALS.forEach((key) => defineIfAbsent(key, dom.window[key]));
	defineIfAbsent('matchMedia', dom.window.matchMedia);
}

installDom();

module.exports = {};
