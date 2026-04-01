/**
 * Clickable Group - Frontend JavaScript
 *
 * Handles clickable group functionality on the frontend.
 * Makes entire group blocks clickable while preserving
 * functionality of interactive elements inside.
 *
 * @package
 * @since 1.0.0
 */

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

		// SECURITY: Validate URL before using it
		if (!isValidHttpUrl(linkUrl)) {
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

		const linkTarget = group.getAttribute('data-link-target');

		// Make the cursor show it's clickable
		group.style.cursor = 'pointer';

		// Handle click event
		group.addEventListener('click', function (e) {
			// Don't navigate if clicking on an interactive element
			const target = e.target;
			const isInteractive =
				target.tagName === 'A' ||
				target.tagName === 'BUTTON' ||
				target.tagName === 'INPUT' ||
				target.tagName === 'TEXTAREA' ||
				target.tagName === 'SELECT' ||
				target.closest('a') ||
				target.closest('button');

			if (!isInteractive) {
				// Navigate to URL
				if (linkTarget === '_blank') {
					const newWindow = window.open(linkUrl, '_blank');
					if (newWindow) {
						newWindow.opener = null; // Security: prevent window.opener access
					}
				} else {
					window.location.href = linkUrl;
				}
			}
		});
	});
}

document.addEventListener('DOMContentLoaded', initClickableGroups);
document.addEventListener('dsgo-content-loaded', initClickableGroups);
