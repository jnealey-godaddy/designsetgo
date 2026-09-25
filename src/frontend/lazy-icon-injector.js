/**
 * Icon Injector
 *
 * Finds all icons with data-icon-name attributes and injects
 * the appropriate SVG markup from the global icon library.
 *
 * PHP prints only the icons the page rendered as `window.dsgoIcons`
 * (name → SVG, '' for unknown names). Icons that arrive later — Query
 * "load more", soft navigation — are fetched from the icons REST route.
 *
 * @since 1.2.0
 */

/* global DOMParser, MutationObserver, Node, requestAnimationFrame, cancelAnimationFrame */

/**
 * Names already asked of the REST route, so each is fetched at most once.
 *
 * @type {Set<string>}
 */
const requestedIcons = new Set();

/**
 * Normalize an icon name the same way PHP's designsetgo_sanitize_icon_slug()
 * does, so lookups match the keys the server sends.
 *
 * @param {string} name Raw data-icon-name value.
 * @return {string} Normalized icon name.
 */
function normalizeIconName(name) {
	return typeof name === 'string'
		? name
				.trim()
				.toLowerCase()
				.replace(/[^a-z0-9-]/g, '')
		: '';
}

/**
 * Fetch icons the page did not render on first paint, then inject them.
 *
 * @param {string[]} names Normalized icon names missing from the library.
 */
function fetchMissingIcons(names) {
	const rest = window.dsgoIconsRest;
	const toFetch = names.filter((name) => !requestedIcons.has(name));

	if (!rest || !rest.url || toFetch.length === 0) {
		return;
	}

	toFetch.forEach((name) => requestedIcons.add(name));

	const url = new URL(rest.url, window.location.href);
	url.searchParams.set('names', toFetch.sort().join(','));
	url.searchParams.set('ver', rest.version || '');

	fetch(url.toString(), { credentials: 'omit' })
		.then((response) => (response.ok ? response.json() : {}))
		.then((icons) => {
			if (!icons || typeof icons !== 'object') {
				return;
			}
			// Record every requested name, found or not, so an unknown
			// name is not fetched again on the next mutation.
			toFetch.forEach((name) => {
				window.dsgoIcons[name] =
					typeof icons[name] === 'string' ? icons[name] : '';
			});
			initIconInjection();
		})
		.catch(() => {
			// Offline or blocked: those icons stay empty, as they would
			// have before on-demand loading existed.
		});
}

/**
 * Initialize icon injection on page load or for specific container.
 *
 * @param {HTMLElement} container - Optional container to search within
 */
function initIconInjection(container = document) {
	if (
		typeof window.dsgoIcons !== 'object' ||
		window.dsgoIcons === null ||
		Array.isArray(window.dsgoIcons)
	) {
		window.dsgoIcons = {};
	}

	const missingIcons = new Set();

	// Find all icon placeholders
	const iconPlaceholders = container.querySelectorAll(
		'.dsgo-lazy-icon[data-icon-name]'
	);

	if (iconPlaceholders.length === 0) {
		return;
	}

	// Inject icons into all placeholders
	iconPlaceholders.forEach((placeholder) => {
		// Skip if already injected
		if (placeholder.dataset.iconInjected === 'true') {
			return;
		}

		const iconName = normalizeIconName(placeholder.dataset.iconName);

		// When a block leaves the style unset it inherits the theme default
		// (settings.custom.designsetgo.icon.defaultStyle), localized as
		// window.dsgoIconDefaults. An explicit data-icon-style always wins.
		const defaultStyle =
			typeof window.dsgoIconDefaults !== 'undefined' &&
			window.dsgoIconDefaults.style
				? window.dsgoIconDefaults.style
				: 'filled';
		// Icons whose block cannot serialize their own style (e.g. icon-list
		// items, whose style is a shared parent setting that block context can't
		// pass to a static save()) inherit from the nearest ancestor that stamps
		// data-dsgo-icon-style. Own attribute → inherited ancestor → theme default.
		const inheritedFrom = placeholder.dataset.iconStyle
			? null
			: placeholder.closest('[data-dsgo-icon-style]');
		const iconStyle =
			placeholder.dataset.iconStyle ||
			inheritedFrom?.dataset.dsgoIconStyle ||
			defaultStyle;
		const strokeWidth =
			placeholder.dataset.iconStrokeWidth ||
			inheritedFrom?.dataset.dsgoIconStrokeWidth ||
			'1.5';

		// Aliases are resolved server-side, so the name is the key as written.
		if (!iconName) {
			return;
		}

		if (!Object.prototype.hasOwnProperty.call(window.dsgoIcons, iconName)) {
			missingIcons.add(iconName);
			return;
		}

		if (!window.dsgoIcons[iconName]) {
			return;
		}

		try {
			// Get SVG markup
			const iconSvg = window.dsgoIcons[iconName];

			// ✅ SECURITY: Use DOMParser instead of innerHTML to prevent XSS
			// Parse SVG string safely without executing any potential scripts
			const parser = new DOMParser();
			const doc = parser.parseFromString(iconSvg, 'image/svg+xml');
			const svgElement = doc.documentElement;

			// Check for parsing errors
			const parserError = doc.querySelector('parsererror');
			if (parserError) {
				throw new Error('Invalid SVG markup');
			}

			// ✅ ACCESSIBILITY: Copy ARIA attributes from placeholder to SVG
			// Preserve accessibility labels and roles set in save.js
			const ariaAttributes = ['role', 'aria-label', 'aria-hidden'];
			ariaAttributes.forEach((attr) => {
				const value = placeholder.getAttribute(attr);
				if (value) {
					svgElement.setAttribute(attr, value);
				}
			});

			// For outlined style, wrap with styling span
			if (iconStyle === 'outlined') {
				const wrapper = document.createElement('span');
				wrapper.className = 'dsgo-icon-outlined';
				wrapper.style.display = 'contents';
				wrapper.style.setProperty('--icon-stroke-width', strokeWidth);
				wrapper.appendChild(svgElement);
				placeholder.appendChild(wrapper);
			} else {
				// Inject SVG element directly
				placeholder.appendChild(svgElement);
			}

			// Mark as injected
			placeholder.dataset.iconInjected = 'true';
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error(`Failed to inject icon "${iconName}":`, error);
		}
	});

	if (missingIcons.size > 0) {
		fetchMissingIcons(Array.from(missingIcons));
	}
}

// Expose globally for dynamic content
window.dsgoInjectIcons = initIconInjection;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => initIconInjection());
} else {
	initIconInjection();
}

// Re-initialize when custom content-loaded event fires (bfcache, AJAX/SPA navigation)
// On bfcache restoration, frontend.js dispatches dsgo-content-loaded via pageshow.
document.addEventListener('dsgo-content-loaded', (event) => {
	const container = event.detail?.container || document;

	// Reset injection flags for icons missing their SVG (bfcache may discard them)
	if (event.detail?.source === 'bfcache') {
		document
			.querySelectorAll('.dsgo-lazy-icon[data-icon-injected="true"]')
			.forEach((el) => {
				if (!el.querySelector('svg')) {
					el.removeAttribute('data-icon-injected');
				}
			});
	}

	initIconInjection(container);
});

// Watch for dynamically added icon elements (client-side routing, AJAX, etc.)
if (typeof MutationObserver !== 'undefined') {
	let pendingInjection = null;

	const observer = new MutationObserver((mutations) => {
		for (const mutation of mutations) {
			for (const node of mutation.addedNodes) {
				if (node.nodeType !== Node.ELEMENT_NODE) {
					continue;
				}

				const hasIcon =
					(node.matches?.('.dsgo-lazy-icon[data-icon-name]') &&
						node.dataset.iconInjected !== 'true') ||
					node.querySelector?.(
						'.dsgo-lazy-icon[data-icon-name]:not([data-icon-injected="true"])'
					);

				if (hasIcon) {
					// Debounce to batch multiple additions
					if (pendingInjection) {
						cancelAnimationFrame(pendingInjection);
					}
					pendingInjection = requestAnimationFrame(() => {
						initIconInjection();
						pendingInjection = null;
					});
					return;
				}
			}
		}
	});

	if (document.body) {
		observer.observe(document.body, { childList: true, subtree: true });
	} else {
		document.addEventListener('DOMContentLoaded', () => {
			observer.observe(document.body, { childList: true, subtree: true });
		});
	}
}
