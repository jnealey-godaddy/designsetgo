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
		return /^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{4}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{8}|rgba?\([\d\s,.%/]+\)|hsla?\([\d\s,.%/]+\)|var\(--[a-zA-Z0-9_-]+(,\s*[^)]+)?\)|[a-zA-Z]+)$/.test(
			trimmed
		);
	}

	/**
	 * Translated string localized onto the frontend bundle by PHP (see
	 * Assets::register_frontend_assets()). The bundle loads on every page, so
	 * it takes its few strings this way instead of depending on wp-i18n.
	 *
	 * @param {string} key      String key.
	 * @param {string} fallback English fallback.
	 * @return {string} Localized string.
	 */
	function t(key, fallback) {
		const strings = window.dsgoFrontendL10n || {};
		return typeof strings[key] === 'string' && strings[key]
			? strings[key]
			: fallback;
	}

	const SVG_NS = 'http://www.w3.org/2000/svg';

	/**
	 * Sync the toggle button's label and glyph with the playback state.
	 *
	 * @param {HTMLElement} button  Toggle button.
	 * @param {boolean}     playing Whether the video is playing.
	 */
	function updateToggle(button, playing) {
		button.setAttribute(
			'aria-label',
			playing
				? t('pauseVideo', 'Pause background video')
				: t('playVideo', 'Play background video')
		);
		button.classList.toggle('is-playing', playing);

		const svg = document.createElementNS(SVG_NS, 'svg');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('width', '16');
		svg.setAttribute('height', '16');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('focusable', 'false');
		const path = document.createElementNS(SVG_NS, 'path');
		path.setAttribute('fill', 'currentColor');
		path.setAttribute(
			'd',
			playing ? 'M6 5h4v14H6zM14 5h4v14h-4z' : 'M8 5v14l11-7z'
		);
		svg.appendChild(path);
		button.replaceChildren(svg);
	}

	/**
	 * Attach the parked source (first time only).
	 *
	 * @param {HTMLElement} video Video element.
	 */
	function loadSource(video) {
		if (!video.getAttribute('src') && video.dataset.dsgoSrc) {
			// A video that won't play yet still loads metadata so it shows
			// its first frame when there is no poster.
			video.preload =
				video.dataset.dsgoShouldPlay === 'true' ? 'auto' : 'metadata';
			video.src = video.dataset.dsgoSrc;
		}
	}

	/**
	 * Start playback, attaching the source first if needed.
	 *
	 * @param {HTMLElement} video Video element.
	 */
	function playVideo(video) {
		loadSource(video);
		const playPromise = video.play();
		if (playPromise !== undefined) {
			playPromise.catch(() => {
				// Autoplay can be refused (e.g. not muted). The toggle stays
				// in its "play" state so the visitor can start it manually.
			});
		}
	}

	// Off-screen videos neither download nor play: the source is attached
	// the first time a section nears the viewport, and playback pauses
	// whenever it scrolls away. A visitor's own pause is never overridden.
	const observer =
		'IntersectionObserver' in window
			? new window.IntersectionObserver(
					(entries) => {
						entries.forEach((entry) => {
							const video = entry.target.querySelector(
								':scope > .dsgo-video-background video'
							);
							if (!video) {
								return;
							}
							if (entry.isIntersecting) {
								loadSource(video);
								if (video.dataset.dsgoShouldPlay === 'true') {
									playVideo(video);
								}
							} else if (!video.paused) {
								video.pause();
							}
						});
					},
					{ rootMargin: '200px 0px' }
				)
			: null;

	const reducedMotionQuery = window.matchMedia
		? window.matchMedia('(prefers-reduced-motion: reduce)')
		: null;

	/**
	 * Whether the mobile-hide breakpoint currently applies.
	 *
	 * @return {boolean} True on narrow viewports.
	 */
	function isMobileViewport() {
		return window.innerWidth <= 767;
	}

	/**
	 * Remove a block's video and its toggle.
	 *
	 * @param {HTMLElement} block Block element.
	 */
	function teardown(block) {
		if (observer) {
			observer.unobserve(block);
		}
		block
			.querySelectorAll(
				':scope > .dsgo-video-background, :scope > .dsgo-video-background__toggle'
			)
			.forEach((el) => el.remove());
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
			if (isMobileViewport() && mobileHide) {
				teardown(block);
				return;
			}

			// Check if video already exists
			if (block.querySelector(':scope > .dsgo-video-background')) {
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

			// Create video element. The source is parked on a data attribute
			// and attached on first play, so sections below the fold don't
			// download video on page load.
			const video = document.createElement('video');
			video.dataset.dsgoSrc = videoUrl;
			video.preload = 'none';
			video.setAttribute('aria-hidden', 'true');

			// Validate and set poster URL if provided.
			const posterUrl = block.getAttribute('data-video-poster');
			if (posterUrl && isValidVideoUrl(posterUrl)) {
				video.poster = posterUrl;
			}
			video.muted = muted;
			video.loop = loop;
			video.playsInline = true;
			video.style.width = '100%';
			video.style.height = '100%';
			video.style.objectFit = 'cover';

			// Visitors who ask for reduced motion get the poster frame and a
			// play button instead of autoplay. A visitor's explicit pause
			// survives re-initialization (breakpoint crossings, soft nav).
			const prefersReducedMotion =
				!!reducedMotionQuery && reducedMotionQuery.matches;
			const shouldPlay =
				autoplay &&
				!prefersReducedMotion &&
				block.dataset.dsgoVideoPaused !== 'true';
			video.dataset.dsgoShouldPlay = shouldPlay ? 'true' : 'false';

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

			// Ensure content is above video
			Array.from(block.children).forEach((child) => {
				const childPosition = window.getComputedStyle(child).position;
				if (childPosition === 'static') {
					child.style.position = 'relative';
					child.style.zIndex = '2';
				}
			});

			// Play/pause control (WCAG 2.2.2). It follows the video wrapper
			// so keyboard users reach it before the section's content.
			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'dsgo-video-background__toggle';
			updateToggle(toggle, false);
			toggle.addEventListener('click', () => {
				const willPlay = video.paused;
				block.dataset.dsgoVideoPaused = willPlay ? 'false' : 'true';
				video.dataset.dsgoShouldPlay = willPlay ? 'true' : 'false';
				if (willPlay) {
					playVideo(video);
				} else {
					video.pause();
				}
			});
			video.addEventListener('play', () => updateToggle(toggle, true));
			video.addEventListener('pause', () => updateToggle(toggle, false));

			// Insert video wrapper as first child, toggle right after it.
			block.insertBefore(videoWrapper, block.firstChild);
			videoWrapper.after(toggle);

			if (observer) {
				observer.observe(block);
			} else if (shouldPlay) {
				playVideo(video);
			} else {
				loadSource(video);
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

	// Only the mobile-hide setting depends on viewport width, so re-evaluate
	// just when the breakpoint is crossed. Mobile browsers fire resize while
	// scrolling (the URL bar collapses), and rebuilding every video then
	// restarted and re-downloaded them mid-scroll.
	let wasMobile = isMobileViewport();
	let resizeTimeout;
	window.addEventListener(
		'resize',
		() => {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(() => {
				const isMobile = isMobileViewport();
				if (isMobile === wasMobile) {
					return;
				}
				wasMobile = isMobile;
				// Existing videos are skipped; only mobile-hide blocks are
				// torn down (entering mobile) or built (leaving it).
				initBackgroundVideos();
			}, 250);
		},
		{ passive: true }
	);
})();
