/**
 * Clickable Group - Frontend JavaScript
 *
 * Handles clickable group functionality on the frontend.
 * Makes entire group blocks clickable while preserving
 * functionality of interactive elements inside.
 *
 * Mouse users click anywhere on the group. Keyboard and screen-reader users
 * get a real `<a>` appended to the group (visually hidden; the group shows
 * the focus ring), so the link is focusable, announced as a link, and
 * follows target/rel natively. A group that already contains a link to the
 * same URL gets no extra link, since that link is already reachable.
 *
 * @package
 * @since 1.0.0
 */

const INTERACTIVE_SELECTOR =
	'a, button, input, textarea, select, label, summary, [role="button"], [role="link"]';

const ALLOWED_PROTOCOLS = ['https:', 'http:', 'mailto:', 'tel:'];

/**
 * Validate URL to prevent XSS attacks
 *
 * @param {string} url URL to validate
 * @return {boolean} True if URL is safe
 */
function isValidHttpUrl(url) {
	if (!url || typeof url !== 'string') {
		return false;
	}

	// Trim whitespace
	url = url.trim();

	// Block dangerous protocols
	const dangerousProtocols = /^(javascript|data|vbscript|file|about):/i;
	if (dangerousProtocols.test(url)) {
		return false;
	}

	// Allow relative URLs, http, https, mailto, tel
	const safePattern = /^(https?:\/\/|mailto:|tel:|\/|\.\/|\.\.\/|#)/i;
	return safePattern.test(url);
}

/**
 * Resolve a group's link URL, guaranteeing protocol safety so a
 * `javascript:` payload in the data attribute can never be followed.
 *
 * @param {HTMLElement} group Clickable group element.
 * @return {URL|null} Parsed URL, or null when missing or unsafe.
 */
function getSafeUrl(group) {
	const linkUrl = group.getAttribute('data-link-url');
	if (!linkUrl || !isValidHttpUrl(linkUrl)) {
		return null;
	}
	let parsed;
	try {
		parsed = new URL(linkUrl, window.location.href);
	} catch {
		return null;
	}
	return ALLOWED_PROTOCOLS.includes(parsed.protocol) ? parsed : null;
}

/**
 * Whitespace-collapsed text of an element.
 *
 * @param {Element|null} el Element.
 * @return {string} Text content.
 */
function textOf(el) {
	return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
}

/**
 * Accessible name for the injected link: the group's first heading, else its
 * text, else the first image's alt text, else the URL itself.
 *
 * @param {HTMLElement} group  Clickable group element.
 * @param {URL}         parsed Resolved link URL.
 * @return {string} Link text.
 */
function getLinkName(group, parsed) {
	const heading = textOf(group.querySelector('h1, h2, h3, h4, h5, h6'));
	if (heading) {
		return heading;
	}
	const text = textOf(group);
	if (text) {
		return text;
	}
	const img = group.querySelector('img[alt]:not([alt=""])');
	if (img) {
		return img.getAttribute('alt').trim();
	}
	return parsed.href;
}

function initClickableGroups() {
	// Find all clickable groups
	const clickableGroups = document.querySelectorAll('.dsgo-clickable');

	clickableGroups.forEach((group) => {
		// Prevent duplicate initialization
		if (group.dataset.dsgoInitialized) {
			return;
		}
		group.dataset.dsgoInitialized = 'true';

		const linkUrl = group.getAttribute('data-link-url');

		if (!linkUrl) {
			return;
		}

		const parsed = getSafeUrl(group);
		if (!parsed) {
			// eslint-disable-next-line no-console
			if (window.console && console.warn) {
				// eslint-disable-next-line no-console
				console.warn(
					'DesignSetGo: Blocked potentially unsafe URL:',
					linkUrl
				);
			}
			return;
		}

		// Make the cursor show it's clickable
		group.style.cursor = 'pointer';

		// A link to the same destination is already keyboard-reachable.
		const hasSameLink = Array.from(group.querySelectorAll('a[href]')).some(
			(a) => a.href === parsed.href
		);
		if (hasSameLink) {
			return;
		}

		// Appended (not prepended) so layout rules keyed on :first-child —
		// e.g. core's flow-layout margin reset — still hit the real content.
		const link = document.createElement('a');
		link.className = 'dsgo-clickable__link';
		link.href = parsed.href;
		link.textContent = getLinkName(group, parsed);
		const rel = (group.getAttribute('data-link-rel') || '').trim();
		if (group.getAttribute('data-link-target') === '_blank') {
			link.target = '_blank';
			link.rel = rel
				? `${rel} noopener noreferrer`
				: 'noopener noreferrer';
		} else if (rel) {
			link.rel = rel;
		}
		group.appendChild(link);
	});
}

// One delegated listener serves every group, including ones added later by
// soft navigation or AJAX.
document.addEventListener('click', function (e) {
	if (e.defaultPrevented || e.button !== 0) {
		return;
	}

	const group = e.target.closest('.dsgo-clickable[data-link-url]');
	if (!group) {
		return;
	}

	// Don't navigate if clicking on an interactive element inside the group.
	const interactive = e.target.closest(INTERACTIVE_SELECTOR);
	if (interactive && group.contains(interactive)) {
		return;
	}

	// Don't hijack a click that ends a text selection.
	const selection = group.ownerDocument.defaultView.getSelection();
	if (selection && selection.toString().trim() !== '') {
		return;
	}

	const parsed = getSafeUrl(group);
	if (!parsed) {
		return;
	}

	if (
		group.getAttribute('data-link-target') === '_blank' ||
		e.metaKey ||
		e.ctrlKey
	) {
		window.open(parsed.href, '_blank', 'noopener,noreferrer');
	} else {
		window.location.assign(parsed.href);
	}
});

document.addEventListener('DOMContentLoaded', initClickableGroups);
document.addEventListener('dsgo-content-loaded', initClickableGroups);
