/**
 * Background Video Extension - Frontend
 *
 * Handles background video initialization on the frontend.
 *
 * @package
 * @since 1.0.0
 */

(function () {
	'use strict';

	/**
	 * Validate video URL to prevent XSS attacks.
	 * Only allows http(s) protocols.
	 *
	 * @param {string} url - URL to validate.
	 * @return {boolean} True if URL is safe.
	 */
	function isValidVideoUrl(url) {
		if (!url || typeof url !== 'string') {
			return false;
		}

		// Allow only http(s) protocols.
		try {
			const parsed = new URL(url, window.location.href);
			return parsed.protocol === 'http:' || parsed.protocol === 'https:';
		} catch (e) {
			return false;
		}
	}

	/**
	 * Validate a CSS color value before assigning it to a style property.
	 * Accepts the formats the plugin can emit (hex, rgb/rgba, hsl/hsla, CSS
	 * custom property references, and CSS named colors). Rejects anything
	 * that could carry a `url()`, `expression()`, or `javascript:` payload.
	 *
	 * @param {string} value The candidate color value read from a data attribute.
	 * @return {boolean} True if the value is safe to pass to `style.backgroundColor`.
	 */
	function isSafeCssColor(value) {
		if (!value || typeof value !== 'string') {
			return false;
		}
		const trimmed = value.trim();
		if (trimmed.length === 0 || trimmed.length > 64) {
			return false;
		}
		// Disallow anything that could carry a URL or expression, and
		// control/escape characters that sanitizers often miss.
		if (/[<>"'`\\\u0000-\u001f]/.test(trimmed)) {
			return false;
		}
		if (/url\s*\(|expression\s*\(|javascript:/i.test(trimmed)) {
			return false;
		}
		return /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.%/]+\)|hsla?\([\d\s,.%/]+\)|var\(--[a-zA-Z0-9_-]+(,\s*[^)]+)?\)|[a-zA-Z]+)$/.test(
			trimmed
		);
	}

	/**
	 * Initialize background videos
	 */
	function initBackgroundVideos() {
		const videoBlocks = document.querySelectorAll(
			'.dsgo-has-video-background'
		);

		videoBlocks.forEach((block) => {
			const videoUrl = block.getAttribute('data-video-url');
			const muted = block.getAttribute('data-video-muted') === 'true';
			const loop = block.getAttribute('data-video-loop') === 'true';
			const autoplay =
				block.getAttribute('data-video-autoplay') === 'true';
			const mobileHide =
				block.getAttribute('data-video-mobile-hide') === 'true';

			// Validate video URL for security.
			if (!videoUrl || !isValidVideoUrl(videoUrl)) {
				// Invalid URL detected and blocked for security.
				// Silently fail to prevent console clutter in production.
				return;
			}

			// Check if mobile and should hide
			const isMobile = window.innerWidth <= 767;
			if (isMobile && mobileHide) {
				return;
			}

			// Check if video already exists
			if (block.querySelector('.dsgo-video-background')) {
				return;
			}

			// Create video wrapper
			const videoWrapper = document.createElement('div');
			videoWrapper.className = 'dsgo-video-background';
			videoWrapper.style.position = 'absolute';
			videoWrapper.style.top = '0';
			videoWrapper.style.left = '0';
			videoWrapper.style.width = '100%';
			videoWrapper.style.height = '100%';
			videoWrapper.style.zIndex = '0';
			videoWrapper.style.overflow = 'hidden';
			videoWrapper.style.pointerEvents = 'none';

			// Create video element
			const video = document.createElement('video');
			video.src = videoUrl;

			// Validate and set poster URL if provided.
			const posterUrl = block.getAttribute('data-video-poster');
			if (posterUrl && isValidVideoUrl(posterUrl)) {
				video.poster = posterUrl;
			}
			video.muted = muted;
			video.loop = loop;
			video.autoplay = autoplay;
			video.playsInline = true;
			video.style.width = '100%';
			video.style.height = '100%';
			video.style.objectFit = 'cover';

			// Append video to wrapper
			videoWrapper.appendChild(video);

			// Add overlay if color is set. The value is validated to reject
			// anything that could smuggle url()/expression()/javascript:
			// payloads into the style attribute.
			const overlayColor = block.getAttribute('data-video-overlay-color');
			if (overlayColor && isSafeCssColor(overlayColor)) {
				const overlay = document.createElement('div');
				overlay.className = 'dsgo-video-overlay';
				overlay.style.position = 'absolute';
				overlay.style.top = '0';
				overlay.style.left = '0';
				overlay.style.width = '100%';
				overlay.style.height = '100%';
				overlay.style.backgroundColor = overlayColor;
				overlay.style.opacity = '0.7';
				overlay.style.zIndex = '1';
				overlay.style.pointerEvents = 'none';
				videoWrapper.appendChild(overlay);
			}

			// Ensure block has position relative
			const blockPosition = window.getComputedStyle(block).position;
			if (blockPosition === 'static') {
				block.style.position = 'relative';
			}

			// Insert video wrapper as first child
			block.insertBefore(videoWrapper, block.firstChild);

			// Ensure content is above video
			Array.from(block.children).forEach((child) => {
				if (child !== videoWrapper) {
					const childPosition =
						window.getComputedStyle(child).position;
					if (childPosition === 'static') {
						child.style.position = 'relative';
						child.style.zIndex = '2';
					}
				}
			});

			// Play video if autoplay is enabled
			if (autoplay) {
				const playPromise = video.play();
				if (playPromise !== undefined) {
					playPromise.catch(() => {
						// Autoplay failed, likely because not muted.
						// This is expected behavior in many browsers, so we silently ignore it.
						// Developers can check the browser console if debugging is needed.
					});
				}
			}
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBackgroundVideos);
	} else {
		initBackgroundVideos();
	}

	// Re-initialize after soft navigation (bfcache, AJAX)
	document.addEventListener('dsgo-content-loaded', initBackgroundVideos);

	// Re-initialize on window resize (for mobile hide/show)
	let resizeTimeout;
	window.addEventListener('resize', () => {
		clearTimeout(resizeTimeout);
		resizeTimeout = setTimeout(() => {
			// Remove existing videos
			document
				.querySelectorAll('.dsgo-video-background')
				.forEach((video) => {
					video.remove();
				});
			// Re-initialize
			initBackgroundVideos();
		}, 250);
	});
})();
