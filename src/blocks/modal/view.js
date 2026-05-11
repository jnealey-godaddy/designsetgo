/**
 * Modal Block - Frontend JavaScript
 *
 * Handles modal open/close, focus trapping, keyboard navigation,
 * and accessibility features.
 *
 * @package
 */

/* global MutationObserver, sessionStorage, localStorage, navigator, history, requestAnimationFrame */
/* eslint-disable @wordpress/no-global-active-element, no-lonely-if, jsdoc/require-param-type, jsdoc/no-undefined-types */

(function () {
	'use strict';

	// Debug mode - checks if WP_DEBUG is enabled or if debug parameter is in URL
	const DEBUG_MODE =
		(typeof window.dsgoModalDebug !== 'undefined' &&
			window.dsgoModalDebug) ||
		window.location.search.includes('dsgo_debug=1');

	// In-memory fallback when browser storage is blocked (sandboxed iframes,
	// incognito mode with third-party cookies disabled, strict privacy modes).
	const memoryStore = {
		session: new Map(),
		local: new Map(),
		cookie: new Map(),
	};

	/**
	 * Read a value from browser storage, falling back to the in-memory store
	 * if the storage API throws (sandboxed iframe, strict privacy mode, etc.).
	 *
	 * @param {'session'|'local'} type Which Web Storage area to read from.
	 * @param {string}            key  Storage key.
	 * @return {string|null} Stored value, or `null` when neither storage nor
	 * the memory fallback has the key.
	 */
	function safeStorageGet(type, key) {
		try {
			const storage = type === 'session' ? sessionStorage : localStorage;
			const value = storage.getItem(key);
			if (value !== null) {
				return value;
			}
		} catch (e) {
			// Storage access blocked — fall through to memory.
		}
		return memoryStore[type].has(key) ? memoryStore[type].get(key) : null;
	}

	/**
	 * Write a value to browser storage, falling back to the in-memory store
	 * if the storage API throws (sandboxed iframe, strict privacy mode, etc.).
	 *
	 * When storage is blocked the value is kept in memory so suppression still
	 * works for the current page lifetime; it will not persist across reloads.
	 *
	 * @param {'session'|'local'} type  Which Web Storage area to write to.
	 * @param {string}            key   Storage key.
	 * @param {string}            value Value to store.
	 */
	function safeStorageSet(type, key, value) {
		try {
			const storage = type === 'session' ? sessionStorage : localStorage;
			storage.setItem(key, value);
			return;
		} catch (e) {
			// Storage access blocked — use memory fallback.
		}
		memoryStore[type].set(key, value);
	}

	/**
	 * Modal Manager Class
	 *
	 * Handles a single modal instance with full accessibility support.
	 */
	class DSGModal {
		constructor(element) {
			this.modal = element;
			this.modalId = element.getAttribute('data-modal-id');
			this.backdrop = element.querySelector('.dsgo-modal__backdrop');
			this.dialog = element.querySelector('.dsgo-modal__dialog');
			this.content = element.querySelector('.dsgo-modal__content');
			this.closeButton = element.querySelector('.dsgo-modal__close');
			this.isOpen = false;
			this.previouslyFocusedElement = null;
			this.focusableElements = [];
			this.focusableElementsCached = false;
			this.scrollPosition = 0;

			// Settings from data attributes (with validation)
			this.settings = {
				animationType: this.validateEnum(
					element.getAttribute('data-animation-type'),
					['fade', 'slide-up', 'slide-down', 'zoom', 'none'],
					'fade'
				),
				animationDuration: this.validateNumber(
					element.getAttribute('data-animation-duration'),
					300,
					0,
					2000
				),
				closeOnBackdrop: this.validateBoolean(
					element.getAttribute('data-close-on-backdrop'),
					true
				),
				closeOnEsc: this.validateBoolean(
					element.getAttribute('data-close-on-esc'),
					true
				),
				disableBodyScroll: this.validateBoolean(
					element.getAttribute('data-disable-body-scroll'),
					true
				),
				allowHashTrigger: this.validateBoolean(
					element.getAttribute('data-allow-hash-trigger'),
					true
				),
				updateUrlOnOpen: this.validateBoolean(
					element.getAttribute('data-update-url-on-open'),
					false
				),
				autoTriggerType: this.validateEnum(
					element.getAttribute('data-auto-trigger-type'),
					['none', 'pageLoad', 'exitIntent', 'scroll', 'time'],
					'none'
				),
				autoTriggerDelay: this.validateNumber(
					element.getAttribute('data-auto-trigger-delay'),
					0,
					0,
					300
				),
				autoTriggerFrequency: this.validateEnum(
					element.getAttribute('data-auto-trigger-frequency'),
					['always', 'session', 'once'],
					'always'
				),
				cookieDuration: this.validateNumber(
					element.getAttribute('data-cookie-duration'),
					7,
					1,
					365
				),
				exitIntentSensitivity: this.validateEnum(
					element.getAttribute('data-exit-intent-sensitivity'),
					['low', 'medium', 'high'],
					'medium'
				),
				exitIntentMinTime: this.validateNumber(
					element.getAttribute('data-exit-intent-min-time'),
					5,
					0,
					300
				),
				exitIntentExcludeMobile: this.validateBoolean(
					element.getAttribute('data-exit-intent-exclude-mobile'),
					true
				),
				scrollDepth: this.validateNumber(
					element.getAttribute('data-scroll-depth'),
					50,
					0,
					100
				),
				scrollDirection: this.validateEnum(
					element.getAttribute('data-scroll-direction'),
					['down', 'both'],
					'down'
				),
				timeOnPage: this.validateNumber(
					element.getAttribute('data-time-on-page'),
					30,
					0,
					600
				),
				galleryGroupId:
					element.getAttribute('data-gallery-group-id') || '',
				galleryIndex: this.validateNumber(
					element.getAttribute('data-gallery-index'),
					0,
					0,
					50
				),
				showGalleryNavigation: this.validateBoolean(
					element.getAttribute('data-show-gallery-navigation'),
					true
				),
				navigationStyle: this.validateEnum(
					element.getAttribute('data-navigation-style'),
					['arrows', 'chevrons', 'text'],
					'arrows'
				),
				navigationPosition: this.validateEnum(
					element.getAttribute('data-navigation-position'),
					['sides', 'bottom', 'top'],
					'sides'
				),
			};

			// Gallery state
			this.galleryModals = [];
			this.currentGalleryIndex = -1;
			this.galleryModalsCache = null;
			this.galleryCacheTimestamp = 0;
			this.galleryCacheDuration = 5000; // 5 seconds

			// Check for reduced motion preference
			this.prefersReducedMotion = window.matchMedia(
				'(prefers-reduced-motion: reduce)'
			).matches;

			this.init();
		}

		/**
		 * Initialize the modal
		 */
		init() {
			// Prevent duplicate initialization
			if (this.modal.hasAttribute('data-dsgo-initialized')) {
				return;
			}
			this.modal.setAttribute('data-dsgo-initialized', 'true');

			// Add animation class
			if (
				this.settings.animationType !== 'none' &&
				!this.prefersReducedMotion
			) {
				this.modal.classList.add(
					`dsgo-modal--animation-${this.settings.animationType}`
				);
			}

			// Set transition duration
			if (!this.prefersReducedMotion) {
				const duration = `${this.settings.animationDuration}ms`;
				if (this.backdrop) {
					this.backdrop.style.transition = `opacity ${duration} ease`;
				}
				if (this.dialog) {
					this.dialog.style.transition = `all ${duration} ease`;
				}
			}

			// Bind event handlers
			this.bindEvents();

			// Set up MutationObserver to invalidate focusable elements cache when content changes
			this.setupContentObserver();

			// Update focusable elements initially
			this.updateFocusableElements();

			// Set up hash triggering if enabled
			if (this.settings.allowHashTrigger && this.modalId) {
				this.setupHashTrigger();
			}

			// Set up auto-trigger if enabled
			if (this.settings.autoTriggerType !== 'none') {
				this.setupAutoTrigger();
			}

			// Set up gallery navigation if part of a gallery
			if (this.settings.galleryGroupId) {
				this.setupGallery();
			}
		}

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Store bound handlers for cleanup
			this.handleCloseClick = (e) => {
				e.preventDefault();
				this.close();
			};

			this.handleBackdropClick = (e) => {
				e.preventDefault();
				this.close();
			};

			// Close button
			if (this.closeButton) {
				this.closeButton.addEventListener(
					'click',
					this.handleCloseClick
				);
			}

			// Backdrop click
			if (this.settings.closeOnBackdrop && this.backdrop) {
				this.backdrop.addEventListener(
					'click',
					this.handleBackdropClick
				);
			}

			// Define ESC key handler (will be attached/detached on modal open/close)
			if (this.settings.closeOnEsc) {
				this.handleEscKey = (e) => {
					if (e.key === 'Escape' && this.isOpen) {
						e.preventDefault();
						this.close();
					}
				};
			}

			// Define focus trap handler (will be attached/detached on modal open/close)
			this.handleFocusTrap = (e) => {
				if (!this.isOpen || e.key !== 'Tab') {
					return;
				}

				// If no focusable elements, allow Tab to close modal for accessibility
				if (this.focusableElements.length === 0) {
					e.preventDefault();
					this.close();
					return;
				}

				const firstElement = this.focusableElements[0];
				const lastElement =
					this.focusableElements[this.focusableElements.length - 1];

				if (e.shiftKey) {
					// Shift + Tab
					if (document.activeElement === firstElement) {
						e.preventDefault();
						lastElement.focus();
					}
				} else {
					// Tab
					if (document.activeElement === lastElement) {
						e.preventDefault();
						firstElement.focus();
					}
				}
			};
		}

		/**
		 * Attach global keyboard listeners
		 */
		attachKeyboardListeners() {
			if (this.handleEscKey) {
				document.addEventListener('keydown', this.handleEscKey);
			}
			if (this.handleFocusTrap) {
				document.addEventListener('keydown', this.handleFocusTrap);
			}
		}

		/**
		 * Detach global keyboard listeners
		 */
		detachKeyboardListeners() {
			if (this.handleEscKey) {
				document.removeEventListener('keydown', this.handleEscKey);
			}
			if (this.handleFocusTrap) {
				document.removeEventListener('keydown', this.handleFocusTrap);
			}
		}

		/**
		 * Set up MutationObserver to watch for content changes
		 */
		setupContentObserver() {
			if (!this.content) {
				return;
			}

			// Invalidate cache when modal content changes
			this.contentObserver = new MutationObserver(() => {
				this.focusableElementsCached = false;
			});

			this.contentObserver.observe(this.content, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['disabled', 'tabindex', 'aria-hidden'],
			});
		}

		/**
		 * Update list of focusable elements (with caching)
		 */
		updateFocusableElements() {
			// Use cached elements if available and modal content hasn't changed
			if (
				this.focusableElementsCached &&
				this.focusableElements.length > 0
			) {
				return;
			}

			const focusableSelectors = [
				'a[href]',
				'button:not([disabled])',
				'textarea:not([disabled])',
				'input:not([disabled])',
				'select:not([disabled])',
				'[tabindex]:not([tabindex="-1"])',
			];

			this.focusableElements = Array.from(
				this.modal.querySelectorAll(focusableSelectors.join(','))
			).filter((el) => {
				return (
					el.offsetParent !== null && !el.hasAttribute('aria-hidden')
				);
			});

			this.focusableElementsCached = true;
		}

		/**
		 * Set up hash triggering
		 */
		setupHashTrigger() {
			// Define hash change handler
			this.handleHashChange = () => {
				// Ignore if we're the one updating the hash
				if (this.isUpdatingHash) {
					return;
				}

				const hash = window.location.hash.replace('#', '');

				// Check if hash matches this modal's ID
				if (hash === this.modalId) {
					if (!this.isOpen) {
						this.open();
					}
				} else if (this.isOpen && this.settings.updateUrlOnOpen) {
					// Close if we were opened by hash and hash changed
					this.close();
				}
			};

			// Listen for hash changes
			window.addEventListener('hashchange', this.handleHashChange);

			// Check initial hash on page load
			const currentHash = window.location.hash.replace('#', '');
			if (currentHash === this.modalId) {
				// Small delay to ensure all modals are initialized
				setTimeout(() => {
					if (!this.isOpen) {
						this.open();
					}
				}, 100);
			}
		}

		/**
		 * Set up auto-trigger
		 */
		setupAutoTrigger() {
			// Check if we should trigger based on frequency
			if (!this.checkTriggerFrequency()) {
				return;
			}

			switch (this.settings.autoTriggerType) {
				case 'pageLoad':
					this.setupPageLoadTrigger();
					break;
				case 'exitIntent':
					this.setupExitIntentTrigger();
					break;
				case 'scroll':
					this.setupScrollTrigger();
					break;
				case 'time':
					this.setupTimeOnPageTrigger();
					break;
			}
		}

		/**
		 * Check if modal should trigger based on frequency settings
		 */
		checkTriggerFrequency() {
			const frequency = this.settings.autoTriggerFrequency;

			// Always show
			if (frequency === 'always') {
				return true;
			}

			const storageKey = `dsgo_modal_${this.modalId}_shown`;

			// Check session storage for session frequency
			if (frequency === 'session') {
				if (safeStorageGet('session', storageKey)) {
					return false;
				}
			}

			// Check localStorage/cookie for once frequency
			if (frequency === 'once') {
				// Try localStorage first
				if (safeStorageGet('local', storageKey)) {
					return false;
				}

				// Fallback to cookie
				if (this.getCookie(storageKey)) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Mark modal as shown for frequency tracking
		 */
		markAsShown() {
			const frequency = this.settings.autoTriggerFrequency;

			if (frequency === 'always') {
				return;
			}

			const storageKey = `dsgo_modal_${this.modalId}_shown`;

			if (frequency === 'session') {
				safeStorageSet('session', storageKey, 'true');
			}

			if (frequency === 'once') {
				// Save to localStorage
				safeStorageSet('local', storageKey, 'true');

				// Also save to cookie as fallback
				this.setCookie(
					storageKey,
					'true',
					this.settings.cookieDuration
				);
			}
		}

		/**
		 * Get cookie value
		 *
		 * Uses a safe string-splitting approach to avoid ReDoS vulnerabilities.
		 * @param name
		 */
		getCookie(name) {
			let rawCookie = '';
			try {
				rawCookie = document.cookie;
			} catch (e) {
				// document.cookie access blocked (sandboxed iframe, strict
				// privacy mode). Fall back to in-memory store.
				return memoryStore.cookie.has(name)
					? memoryStore.cookie.get(name)
					: undefined;
			}

			// Use safer string splitting instead of complex regex
			const cookieString = `; ${rawCookie}`;
			const parts = cookieString.split(`; ${name}=`);

			if (parts.length === 2) {
				const value = parts.pop().split(';').shift();
				try {
					return decodeURIComponent(value);
				} catch (e) {
					// If decoding fails, return raw value
					return value;
				}
			}

			return memoryStore.cookie.has(name)
				? memoryStore.cookie.get(name)
				: undefined;
		}

		/**
		 * Set cookie with secure flags
		 *
		 * Uses SameSite=Strict and Secure (on HTTPS) for enhanced security.
		 * @param name
		 * @param value
		 * @param days
		 */
		setCookie(name, value, days) {
			const expires = new Date();
			expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);

			// Encode value to handle special characters
			const encodedValue = encodeURIComponent(value);

			// Use SameSite=Strict for frequency tracking (no cross-site needs)
			const sameSite = 'Strict';

			// Add Secure flag when using HTTPS
			const secure =
				window.location.protocol === 'https:' ? ';Secure' : '';

			try {
				document.cookie = `${name}=${encodedValue};expires=${expires.toUTCString()};path=/;SameSite=${sameSite}${secure}`;
			} catch (e) {
				// document.cookie write blocked — keep value in memory so
				// frequency tracking still works for the current page session.
				memoryStore.cookie.set(name, value);
			}
		}

		/**
		 * Set up page load trigger
		 */
		setupPageLoadTrigger() {
			// Convert seconds to milliseconds
			const delay = this.settings.autoTriggerDelay * 1000;

			this.pageLoadTimeout = setTimeout(() => {
				if (!this.isOpen) {
					this.open();
					this.markAsShown();
				}
			}, delay);
		}

		/**
		 * Set up exit intent trigger
		 */
		setupExitIntentTrigger() {
			// Don't trigger on mobile if excluded
			if (
				this.settings.exitIntentExcludeMobile &&
				this.isMobileDevice()
			) {
				return;
			}

			const minTime = this.settings.exitIntentMinTime * 1000;
			const pageLoadTime = Date.now();
			let hasTriggered = false;

			// Sensitivity settings (distance from top in pixels)
			const sensitivityMap = {
				low: 100,
				medium: 50,
				high: 20,
			};
			const threshold =
				sensitivityMap[this.settings.exitIntentSensitivity] || 50;

			this.handleExitIntent = (e) => {
				// Check if enough time has passed
				if (Date.now() - pageLoadTime < minTime) {
					return;
				}

				// Check if mouse is leaving from top
				if (e.clientY <= threshold && !hasTriggered && !this.isOpen) {
					hasTriggered = true;
					this.open();
					this.markAsShown();
				}
			};

			document.addEventListener('mouseout', this.handleExitIntent);
		}

		/**
		 * Set up scroll depth trigger
		 */
		setupScrollTrigger() {
			let hasTriggered = false;
			const targetDepth = this.settings.scrollDepth;
			let lastScrollTop = 0;

			const scrollHandler = () => {
				if (hasTriggered || this.isOpen) {
					return;
				}

				const scrollTop =
					window.pageYOffset || document.documentElement.scrollTop;
				const scrollHeight =
					document.documentElement.scrollHeight - window.innerHeight;
				const scrollPercent = (scrollTop / scrollHeight) * 100;

				// Check direction if needed
				if (this.settings.scrollDirection === 'down') {
					if (scrollTop < lastScrollTop) {
						lastScrollTop = scrollTop;
						return; // Scrolling up, ignore
					}
				}

				lastScrollTop = scrollTop;

				// Check if we've reached the target depth
				if (scrollPercent >= targetDepth) {
					hasTriggered = true;
					this.open();
					this.markAsShown();
					window.removeEventListener('scroll', this.handleScroll);
				}
			};

			// Debounce scroll handler for better performance (100ms delay)
			this.handleScroll = this.debounce(scrollHandler, 100);

			window.addEventListener('scroll', this.handleScroll, {
				passive: true,
			});
		}

		/**
		 * Set up time on page trigger
		 */
		setupTimeOnPageTrigger() {
			const delay = this.settings.timeOnPage * 1000;

			this.timeOnPageTimeout = setTimeout(() => {
				if (!this.isOpen) {
					this.open();
					this.markAsShown();
				}
			}, delay);
		}

		/**
		 * Check if device is mobile
		 */
		isMobileDevice() {
			return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
				navigator.userAgent
			);
		}

		/**
		 * Debounce utility for performance optimization
		 *
		 * @param {Function} func Function to debounce
		 * @param {number}   wait Wait time in milliseconds
		 * @return {Function} Debounced function
		 */
		debounce(func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}

		/**
		 * Validate number attribute
		 *
		 * @param {*}      value        Value to validate
		 * @param {number} defaultValue Default value if validation fails
		 * @param {number} min          Minimum value
		 * @param {number} max          Maximum value
		 * @return {number} Validated number
		 */
		validateNumber(value, defaultValue, min = -Infinity, max = Infinity) {
			const parsed = parseInt(value, 10);
			if (isNaN(parsed) || parsed < min || parsed > max) {
				return defaultValue;
			}
			return parsed;
		}

		/**
		 * Validate enum attribute
		 *
		 * @param {*}      value        Value to validate
		 * @param {Array}  validValues  Array of valid values
		 * @param {string} defaultValue Default value if validation fails
		 * @return {string} Validated enum value
		 */
		validateEnum(value, validValues, defaultValue) {
			if (validValues.includes(value)) {
				return value;
			}
			return defaultValue;
		}

		/**
		 * Validate boolean attribute
		 *
		 * @param {*}       value        Value to validate
		 * @param {boolean} defaultValue Default value if validation fails
		 * @return {boolean} Validated boolean
		 */
		validateBoolean(value, defaultValue) {
			if (value === 'true') {
				return true;
			}
			if (value === 'false') {
				return false;
			}
			if (value === true || value === false) {
				return value;
			}
			return defaultValue;
		}

		/**
		 * Safe logging - only logs when debug mode is enabled
		 *
		 * @param {string} level Log level (warn, error, log)
		 * @param {...*}   args  Arguments to log
		 */
		log(level, ...args) {
			// eslint-disable-next-line no-console
			if (DEBUG_MODE && console[level]) {
				// eslint-disable-next-line no-console
				console[level]('[DSGModal]', ...args);
			}
		}

		/**
		 * Set up gallery navigation
		 */
		setupGallery() {
			// Find all modals with the same gallery group ID
			this.updateGalleryModals();

			// Set up keyboard navigation for gallery
			if (this.settings.showGalleryNavigation) {
				this.setupGalleryKeyboardNavigation();
				this.setupSwipeGestures();
			}
		}

		/**
		 * Update the list of gallery modals (with caching)
		 */
		updateGalleryModals() {
			const now = Date.now();

			// Return cached results if still valid
			if (
				this.galleryModalsCache &&
				now - this.galleryCacheTimestamp < this.galleryCacheDuration
			) {
				this.galleryModals = this.galleryModalsCache;
				this.currentGalleryIndex = this.galleryModals.findIndex(
					(item) => item.modalId === this.modalId
				);
				return;
			}

			const groupId = this.settings.galleryGroupId;
			const allModals = document.querySelectorAll(
				`[data-gallery-group-id="${groupId}"]`
			);

			// Convert to array and sort by gallery index
			this.galleryModals = Array.from(allModals)
				.map((modalEl) => ({
					element: modalEl,
					instance: modalEl.dsgoModalInstance,
					index:
						parseInt(modalEl.getAttribute('data-gallery-index')) ||
						0,
					modalId: modalEl.getAttribute('data-modal-id'),
				}))
				.filter((item) => item.instance) // Only include initialized modals
				.sort((a, b) => a.index - b.index);

			// Cache the results
			this.galleryModalsCache = this.galleryModals;
			this.galleryCacheTimestamp = now;

			// Find this modal's position in the gallery
			this.currentGalleryIndex = this.galleryModals.findIndex(
				(item) => item.modalId === this.modalId
			);
		}

		/**
		 * Invalidate gallery modals cache
		 */
		invalidateGalleryCache() {
			this.galleryModalsCache = null;
			this.galleryCacheTimestamp = 0;
		}

		/**
		 * Navigate to next modal in gallery
		 */
		navigateToNext() {
			if (this.galleryModals.length === 0) {
				return;
			}

			const nextIndex =
				(this.currentGalleryIndex + 1) % this.galleryModals.length;
			this.navigateToModal(nextIndex);
		}

		/**
		 * Navigate to previous modal in gallery
		 */
		navigateToPrevious() {
			if (this.galleryModals.length === 0) {
				return;
			}

			const prevIndex =
				(this.currentGalleryIndex - 1 + this.galleryModals.length) %
				this.galleryModals.length;
			this.navigateToModal(prevIndex);
		}

		/**
		 * Navigate to specific modal in gallery
		 * @param targetIndex
		 */
		navigateToModal(targetIndex) {
			if (targetIndex < 0 || targetIndex >= this.galleryModals.length) {
				return;
			}

			const targetModal = this.galleryModals[targetIndex];
			if (!targetModal || !targetModal.instance) {
				return;
			}

			// Close current modal and open target modal
			this.close();

			// Small delay to ensure smooth transition
			setTimeout(() => {
				targetModal.instance.open();
			}, 50);
		}

		/**
		 * Set up keyboard navigation for gallery
		 */
		setupGalleryKeyboardNavigation() {
			this.handleGalleryKeydown = (e) => {
				if (!this.isOpen || this.galleryModals.length <= 1) {
					return;
				}

				// Left arrow - previous
				if (e.key === 'ArrowLeft') {
					e.preventDefault();
					this.navigateToPrevious();
				}

				// Right arrow - next
				if (e.key === 'ArrowRight') {
					e.preventDefault();
					this.navigateToNext();
				}
			};

			document.addEventListener('keydown', this.handleGalleryKeydown);
		}

		/**
		 * Set up swipe gestures for gallery navigation on touch devices
		 */
		setupSwipeGestures() {
			if (!this.dialog || this.galleryModals.length <= 1) {
				return;
			}

			let touchStartX = 0;
			let touchStartY = 0;
			let touchEndX = 0;
			let touchEndY = 0;

			// Minimum swipe distance in pixels
			const minSwipeDistance = 50;

			this.handleTouchStart = (e) => {
				if (!this.isOpen) {
					return;
				}

				touchStartX = e.changedTouches[0].screenX;
				touchStartY = e.changedTouches[0].screenY;
			};

			this.handleTouchMove = (e) => {
				if (!this.isOpen) {
					return;
				}

				touchEndX = e.changedTouches[0].screenX;
				touchEndY = e.changedTouches[0].screenY;
			};

			this.handleTouchEnd = () => {
				if (!this.isOpen) {
					return;
				}

				const swipeDistanceX = touchEndX - touchStartX;
				const swipeDistanceY = touchEndY - touchStartY;

				// Check if horizontal swipe distance is greater than vertical (to distinguish from scrolling)
				if (Math.abs(swipeDistanceX) > Math.abs(swipeDistanceY)) {
					// Swipe left - next
					if (swipeDistanceX < -minSwipeDistance) {
						this.navigateToNext();
					}
					// Swipe right - previous
					else if (swipeDistanceX > minSwipeDistance) {
						this.navigateToPrevious();
					}
				}

				// Reset values
				touchStartX = 0;
				touchStartY = 0;
				touchEndX = 0;
				touchEndY = 0;
			};

			// Add touch event listeners
			this.dialog.addEventListener('touchstart', this.handleTouchStart, {
				passive: true,
			});
			this.dialog.addEventListener('touchmove', this.handleTouchMove, {
				passive: true,
			});
			this.dialog.addEventListener('touchend', this.handleTouchEnd, {
				passive: true,
			});
		}

		/**
		 * Render navigation buttons in the modal
		 */
		renderNavigationButtons() {
			if (
				!this.settings.showGalleryNavigation ||
				this.galleryModals.length <= 1
			) {
				return;
			}

			// Remove existing buttons first
			this.removeNavigationButtons();

			const dialog = this.modal.querySelector('.dsgo-modal__dialog');
			if (!dialog) {
				return;
			}

			// Create navigation container
			const navContainer = document.createElement('div');
			navContainer.className = `dsgo-modal__gallery-nav dsgo-modal__gallery-nav--${this.settings.navigationPosition}`;

			// Previous button
			const prevButton = document.createElement('button');
			prevButton.className = 'dsgo-modal__gallery-prev';
			prevButton.setAttribute('type', 'button');
			prevButton.setAttribute('aria-label', 'Previous');
			prevButton.appendChild(this.getNavigationIcon('prev'));
			prevButton.addEventListener('click', (e) => {
				e.preventDefault();
				this.navigateToPrevious();
			});

			// Next button
			const nextButton = document.createElement('button');
			nextButton.className = 'dsgo-modal__gallery-next';
			nextButton.setAttribute('type', 'button');
			nextButton.setAttribute('aria-label', 'Next');
			nextButton.appendChild(this.getNavigationIcon('next'));
			nextButton.addEventListener('click', (e) => {
				e.preventDefault();
				this.navigateToNext();
			});

			// Add buttons to container
			navContainer.appendChild(prevButton);
			navContainer.appendChild(nextButton);

			// Add to dialog
			dialog.appendChild(navContainer);

			// Store references for cleanup
			this.galleryNavContainer = navContainer;
			this.galleryPrevButton = prevButton;
			this.galleryNextButton = nextButton;
		}

		/**
		 * Build the navigation icon as a DOM node based on the configured
		 * navigationStyle. Returning a Node (rather than an HTML string) lets
		 * callers use appendChild() instead of innerHTML, which avoids
		 * introducing an HTML-injection footgun if this method ever starts
		 * interpolating user content.
		 * @param direction
		 */
		getNavigationIcon(direction) {
			const isNext = direction === 'next';

			if (this.settings.navigationStyle === 'arrows') {
				const SVG_NS = 'http://www.w3.org/2000/svg';
				const svg = document.createElementNS(SVG_NS, 'svg');
				svg.setAttribute('width', '24');
				svg.setAttribute('height', '24');
				svg.setAttribute('viewBox', '0 0 24 24');
				svg.setAttribute('fill', 'none');

				const path = document.createElementNS(SVG_NS, 'path');
				path.setAttribute(
					'd',
					`M${isNext ? '9' : '15'} 6l${isNext ? '6' : '-6'} 6l${isNext ? '-6' : '6'} 6`
				);
				path.setAttribute('stroke', 'currentColor');
				path.setAttribute('stroke-width', '2');
				path.setAttribute('stroke-linecap', 'round');
				path.setAttribute('stroke-linejoin', 'round');
				svg.appendChild(path);
				return svg;
			}

			const span = document.createElement('span');
			if (this.settings.navigationStyle === 'text') {
				span.textContent = isNext ? 'Next' : 'Previous';
			} else {
				// Chevrons (default)
				span.textContent = isNext ? '›' : '‹';
			}
			return span;
		}

		/**
		 * Remove navigation buttons
		 */
		removeNavigationButtons() {
			if (
				this.galleryNavContainer &&
				this.galleryNavContainer.parentNode
			) {
				this.galleryNavContainer.remove();
			}
			this.galleryNavContainer = null;
			this.galleryPrevButton = null;
			this.galleryNextButton = null;
		}

		/**
		 * Open the modal
		 *
		 * @param {Element} trigger - The element that triggered the modal (optional)
		 */
		open(trigger = null) {
			if (this.isOpen) {
				return;
			}

			// Trigger beforeOpen event
			if (window.dsgoModal) {
				window.dsgoModal.trigger('modalBeforeOpen', {
					modalId: this.modalId,
					element: this.modal,
					trigger,
				});
			}

			// Store the element that triggered the modal
			this.previouslyFocusedElement = trigger || document.activeElement;

			// Move modal to body to avoid z-index stacking context issues
			// Store original parent for potential cleanup
			if (
				!this.originalParent &&
				this.modal.parentElement !== document.body
			) {
				this.originalParent = this.modal.parentElement;
				this.originalNextSibling = this.modal.nextSibling;
				document.body.appendChild(this.modal);
			}

			// Update focusable elements
			this.updateFocusableElements();

			// Attach keyboard listeners (ESC and Tab)
			this.attachKeyboardListeners();

			// Disable body scroll
			if (this.settings.disableBodyScroll) {
				this.disableBodyScroll();
			}

			// Show modal
			this.modal.style.display = 'flex';
			this.modal.setAttribute('aria-hidden', 'false');

			// Trigger opening animation
			if (
				!this.prefersReducedMotion &&
				this.settings.animationType !== 'none'
			) {
				this.modal.classList.add('dsgo-modal--opening');

				// Force reflow to trigger animation
				void this.modal.offsetHeight;

				// Remove opening class and add open class
				requestAnimationFrame(() => {
					this.modal.classList.remove('dsgo-modal--opening');
					this.modal.classList.add('dsgo-modal--open');

					// Clear any pending animation timeout to prevent race conditions
					if (this.animationTimeout) {
						clearTimeout(this.animationTimeout);
					}

					// Wait for animation to complete
					this.animationTimeout = setTimeout(() => {
						this.animationTimeout = null;
						this.onOpenComplete();
					}, this.settings.animationDuration);
				});
			} else {
				// No animation
				this.modal.classList.add('dsgo-modal--open');
				this.onOpenComplete();
			}

			this.isOpen = true;

			// Update URL hash if enabled
			if (this.settings.updateUrlOnOpen && this.modalId) {
				// Store that we're programmatically changing the hash to avoid triggering hash change listener
				this.isUpdatingHash = true;
				window.location.hash = this.modalId;
				// Reset flag after a short delay
				setTimeout(() => {
					this.isUpdatingHash = false;
				}, 100);
			}

			// Dispatch custom event
			this.modal.dispatchEvent(
				new CustomEvent('dsgo-modal-open', {
					bubbles: true,
					detail: { modalId: this.modalId },
				})
			);

			// Trigger API event
			if (window.dsgoModal) {
				window.dsgoModal.trigger('modalOpen', {
					modalId: this.modalId,
					element: this.modal,
					trigger,
				});
			}
		}

		/**
		 * Called when opening animation completes
		 */
		onOpenComplete() {
			// Render gallery navigation if applicable
			if (this.settings.galleryGroupId) {
				this.renderNavigationButtons();
			}

			// Focus the first focusable element or the content
			if (this.focusableElements.length > 0) {
				this.focusableElements[0].focus();
			} else if (this.content) {
				this.content.setAttribute('tabindex', '-1');
				this.content.focus();
			}
		}

		/**
		 * Close the modal
		 */
		close() {
			if (!this.isOpen) {
				return;
			}

			// Trigger beforeClose event
			if (window.dsgoModal) {
				window.dsgoModal.trigger('modalBeforeClose', {
					modalId: this.modalId,
					element: this.modal,
				});
			}

			// Trigger closing animation
			if (
				!this.prefersReducedMotion &&
				this.settings.animationType !== 'none'
			) {
				this.modal.classList.remove('dsgo-modal--open');
				this.modal.classList.add('dsgo-modal--closing');

				// Clear any pending animation timeout to prevent race conditions
				if (this.animationTimeout) {
					clearTimeout(this.animationTimeout);
				}

				// Wait for animation to complete
				this.animationTimeout = setTimeout(() => {
					this.animationTimeout = null;
					this.onCloseComplete();
				}, this.settings.animationDuration);
			} else {
				// No animation
				this.modal.classList.remove('dsgo-modal--open');
				this.onCloseComplete();
			}

			this.isOpen = false;

			// Clear URL hash if we set it on open
			if (
				this.settings.updateUrlOnOpen &&
				window.location.hash === `#${this.modalId}`
			) {
				this.isUpdatingHash = true;
				history.replaceState(null, null, ' ');
				// Reset flag after a short delay
				setTimeout(() => {
					this.isUpdatingHash = false;
				}, 100);
			}

			// Dispatch custom event
			this.modal.dispatchEvent(
				new CustomEvent('dsgo-modal-close', {
					bubbles: true,
					detail: { modalId: this.modalId },
				})
			);

			// Trigger API event
			if (window.dsgoModal) {
				window.dsgoModal.trigger('modalClose', {
					modalId: this.modalId,
					element: this.modal,
				});
			}
		}

		/**
		 * Called when closing animation completes
		 */
		onCloseComplete() {
			// Hide modal
			this.modal.style.display = 'none';
			this.modal.setAttribute('aria-hidden', 'true');
			this.modal.classList.remove('dsgo-modal--closing');

			// Detach keyboard listeners (ESC and Tab)
			this.detachKeyboardListeners();

			// Enable body scroll
			if (this.settings.disableBodyScroll) {
				this.enableBodyScroll();
			}

			// Return focus to trigger element
			if (
				this.previouslyFocusedElement &&
				this.previouslyFocusedElement.focus
			) {
				this.previouslyFocusedElement.focus();
			}

			this.previouslyFocusedElement = null;
		}

		/**
		 * Disable body scrolling
		 */
		disableBodyScroll() {
			// Store current scroll position
			this.scrollPosition =
				window.pageYOffset || document.documentElement.scrollTop;

			// Calculate scrollbar width to prevent layout shift
			const scrollbarWidth =
				window.innerWidth - document.documentElement.clientWidth;

			// Add class to body
			document.body.classList.add('dsgo-modal-open');
			document.body.style.top = `-${this.scrollPosition}px`;

			// Compensate for scrollbar disappearance to prevent content shift
			if (scrollbarWidth > 0) {
				document.body.style.paddingRight = `${scrollbarWidth}px`;
			}
		}

		/**
		 * Enable body scrolling
		 */
		enableBodyScroll() {
			// Remove class from body
			document.body.classList.remove('dsgo-modal-open');
			document.body.style.top = '';
			document.body.style.paddingRight = ''; // Remove scrollbar compensation

			// Restore scroll position
			window.scrollTo(0, this.scrollPosition);
		}

		/**
		 * Destroy the modal instance
		 */
		destroy() {
			// Remove event listeners
			if (this.closeButton && this.handleCloseClick) {
				this.closeButton.removeEventListener(
					'click',
					this.handleCloseClick
				);
			}
			if (this.backdrop && this.handleBackdropClick) {
				this.backdrop.removeEventListener(
					'click',
					this.handleBackdropClick
				);
			}
			if (this.handleHashChange) {
				window.removeEventListener('hashchange', this.handleHashChange);
			}
			if (this.handleExitIntent) {
				document.removeEventListener('mouseout', this.handleExitIntent);
			}
			if (this.handleScroll) {
				window.removeEventListener('scroll', this.handleScroll);
			}
			if (this.handleGalleryKeydown) {
				document.removeEventListener(
					'keydown',
					this.handleGalleryKeydown
				);
			}
			if (this.handleTouchStart && this.dialog) {
				this.dialog.removeEventListener(
					'touchstart',
					this.handleTouchStart
				);
			}
			if (this.handleTouchMove && this.dialog) {
				this.dialog.removeEventListener(
					'touchmove',
					this.handleTouchMove
				);
			}
			if (this.handleTouchEnd && this.dialog) {
				this.dialog.removeEventListener(
					'touchend',
					this.handleTouchEnd
				);
			}

			// Remove gallery navigation buttons
			this.removeNavigationButtons();

			// Clear auto-trigger timeouts
			if (this.pageLoadTimeout) {
				clearTimeout(this.pageLoadTimeout);
			}
			if (this.timeOnPageTimeout) {
				clearTimeout(this.timeOnPageTimeout);
			}

			// Ensure keyboard listeners are removed
			this.detachKeyboardListeners();

			// Disconnect MutationObserver to prevent memory leaks
			if (this.contentObserver) {
				this.contentObserver.disconnect();
				this.contentObserver = null;
			}

			// Clear animation timeout to prevent memory leaks
			if (this.animationTimeout) {
				clearTimeout(this.animationTimeout);
				this.animationTimeout = null;
			}

			// Restore modal to original parent if it was moved
			if (
				this.originalParent &&
				this.modal.parentElement === document.body
			) {
				if (this.originalNextSibling) {
					this.originalParent.insertBefore(
						this.modal,
						this.originalNextSibling
					);
				} else {
					this.originalParent.appendChild(this.modal);
				}
			}

			// Close if open
			if (this.isOpen) {
				this.close();
			}

			// Remove initialization flag
			this.modal.removeAttribute('data-dsgo-initialized');
		}
	}

	/**
	 * Initialize all modals on the page
	 */
	function initModals() {
		const modals = document.querySelectorAll(
			'[data-dsgo-modal]:not([data-dsgo-initialized])'
		);

		modals.forEach((modal) => {
			const instance = new DSGModal(modal);

			// Store instance on element for external access
			modal.dsgoModalInstance = instance;
		});
	}

	/**
	 * Initialize modal triggers
	 */
	function initTriggers() {
		const triggers = document.querySelectorAll(
			'[data-dsgo-modal-trigger]:not([data-dsgo-trigger-initialized])'
		);

		triggers.forEach((trigger) => {
			const modalId = trigger.getAttribute('data-dsgo-modal-trigger');
			const modal = document.getElementById(modalId);

			if (!modal || !modal.dsgoModalInstance) {
				return;
			}

			// Prevent duplicate initialization
			trigger.setAttribute('data-dsgo-trigger-initialized', 'true');

			// Add click handler
			trigger.addEventListener('click', (e) => {
				e.preventDefault();
				modal.dsgoModalInstance.open(trigger);
			});
		});
	}

	/**
	 * Initialize modal close triggers
	 */
	function initCloseTriggers() {
		const closeTriggers = document.querySelectorAll(
			'[data-dsgo-modal-close]:not([data-dsgo-close-trigger-initialized])'
		);

		closeTriggers.forEach((trigger) => {
			const modalId = trigger.getAttribute('data-dsgo-modal-close');

			// Prevent duplicate initialization
			trigger.setAttribute('data-dsgo-close-trigger-initialized', 'true');

			// Add click handler
			trigger.addEventListener('click', (e) => {
				e.preventDefault();

				// If modalId is specified, close that modal
				if (modalId && modalId !== 'true') {
					const modal = document.getElementById(modalId);
					if (modal && modal.dsgoModalInstance) {
						modal.dsgoModalInstance.close();
					}
				} else {
					// Otherwise, find the closest parent modal and close it
					const parentModal = trigger.closest('[data-dsgo-modal]');
					if (parentModal && parentModal.dsgoModalInstance) {
						parentModal.dsgoModalInstance.close();
					}
				}
			});
		});
	}

	/**
	 * Initialize hash link triggers
	 *
	 * Handles anchor links with href="#modal-id" that should open modals.
	 * This ensures modals can be reopened even when the hash is already in the URL,
	 * which normally wouldn't trigger a hashchange event.
	 */
	function initHashLinkTriggers() {
		// Find all anchor links with hash hrefs that haven't been initialized
		const hashLinks = document.querySelectorAll(
			'a[href^="#"]:not([data-dsgo-hash-trigger-initialized]):not([data-dsgo-modal-trigger])'
		);

		hashLinks.forEach((link) => {
			const href = link.getAttribute('href');
			if (!href || href === '#') {
				return;
			}

			const modalId = href.substring(1); // Remove the # prefix
			const modal = document.getElementById(modalId);

			// Only initialize if this links to a modal with hash trigger enabled
			if (
				!modal ||
				!modal.dsgoModalInstance ||
				!modal.dsgoModalInstance.settings.allowHashTrigger
			) {
				return;
			}

			// Prevent duplicate initialization
			link.setAttribute('data-dsgo-hash-trigger-initialized', 'true');

			// Add click handler
			link.addEventListener('click', (e) => {
				e.preventDefault();

				// Open the modal if not already open
				// Note: The open() method handles URL hash updates when updateUrlOnOpen is enabled,
				// so we don't duplicate that logic here
				if (!modal.dsgoModalInstance.isOpen) {
					modal.dsgoModalInstance.open(link);
				}
			});
		});
	}

	/**
	 * Initialize on DOM ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => {
			initModals();
			initTriggers();
			initCloseTriggers();
			initHashLinkTriggers();
		});
	} else {
		initModals();
		initTriggers();
		initCloseTriggers();
		initHashLinkTriggers();
	}

	/**
	 * Re-initialize on dynamic content load (for AJAX/SPA support)
	 */
	document.addEventListener('dsgo-content-loaded', () => {
		initModals();
		initTriggers();
		initCloseTriggers();
		initHashLinkTriggers();
	});

	/**
	 * Event listeners registry for custom events
	 */
	const eventListeners = {
		modalOpen: [],
		modalClose: [],
		modalBeforeOpen: [],
		modalBeforeClose: [],
	};

	/**
	 * Public API for modal control
	 */
	const publicAPI = {
		/**
		 * Open a modal by ID
		 *
		 * @param {string}      modalId         Modal ID to open
		 * @param {Object}      options         Optional configuration
		 * @param {HTMLElement} options.trigger Element that triggered the modal
		 * @return {boolean} Success status
		 */
		open(modalId, options = {}) {
			const modalElement = document.getElementById(modalId);
			if (!modalElement || !modalElement.dsgoModalInstance) {
				if (DEBUG_MODE) {
					// eslint-disable-next-line no-console
					console.warn(
						`[DSGModal] Modal with ID "${modalId}" not found`
					);
				}
				return false;
			}

			modalElement.dsgoModalInstance.open(options.trigger || null);
			return true;
		},

		/**
		 * Close a modal by ID
		 *
		 * @param {string} modalId Modal ID to close (if not provided, closes all)
		 * @return {boolean} Success status
		 */
		close(modalId) {
			if (!modalId) {
				return this.closeAll();
			}

			const modalElement = document.getElementById(modalId);
			if (!modalElement || !modalElement.dsgoModalInstance) {
				if (DEBUG_MODE) {
					// eslint-disable-next-line no-console
					console.warn(
						`[DSGModal] Modal with ID "${modalId}" not found`
					);
				}
				return false;
			}

			modalElement.dsgoModalInstance.close();
			return true;
		},

		/**
		 * Close all open modals
		 *
		 * @return {number} Number of modals closed
		 */
		closeAll() {
			const openModals = document.querySelectorAll(
				'[data-dsgo-modal].is-open'
			);
			let count = 0;

			openModals.forEach((modal) => {
				if (modal.dsgoModalInstance) {
					modal.dsgoModalInstance.close();
					count++;
				}
			});

			return count;
		},

		/**
		 * Check if a modal is currently open
		 *
		 * @param {string} modalId Modal ID to check
		 * @return {boolean} Whether the modal is open
		 */
		isOpen(modalId) {
			const modalElement = document.getElementById(modalId);
			if (!modalElement || !modalElement.dsgoModalInstance) {
				return false;
			}

			return modalElement.dsgoModalInstance.isOpen;
		},

		/**
		 * Get modal instance by ID
		 *
		 * @param {string} modalId Modal ID
		 * @return {DSGModal|null} Modal instance or null
		 */
		getInstance(modalId) {
			const modalElement = document.getElementById(modalId);
			return modalElement?.dsgoModalInstance || null;
		},

		/**
		 * Get all modal instances on the page
		 *
		 * @return {Array<Object>} Array of modal info objects
		 */
		getAllModals() {
			const modals = document.querySelectorAll('[data-dsgo-modal]');
			const result = [];

			modals.forEach((modal) => {
				if (modal.dsgoModalInstance) {
					result.push({
						id: modal.dsgoModalInstance.modalId,
						element: modal,
						instance: modal.dsgoModalInstance,
						isOpen: modal.dsgoModalInstance.isOpen,
					});
				}
			});

			return result;
		},

		/**
		 * Register event listener
		 *
		 * @param {string}   event    Event name (modalOpen, modalClose, modalBeforeOpen, modalBeforeClose)
		 * @param {Function} callback Callback function
		 * @return {Function} Unsubscribe function
		 */
		on(event, callback) {
			if (!eventListeners[event]) {
				if (DEBUG_MODE) {
					// eslint-disable-next-line no-console
					console.warn(`[DSGModal] Unknown event: ${event}`);
				}
				return () => {};
			}

			if (typeof callback !== 'function') {
				if (DEBUG_MODE) {
					// eslint-disable-next-line no-console
					console.warn('[DSGModal] Callback must be a function');
				}
				return () => {};
			}

			eventListeners[event].push(callback);

			// Return unsubscribe function
			return () => this.off(event, callback);
		},

		/**
		 * Remove event listener
		 *
		 * @param {string}   event    Event name
		 * @param {Function} callback Callback function to remove
		 * @return {boolean} Success status
		 */
		off(event, callback) {
			if (!eventListeners[event]) {
				return false;
			}

			const index = eventListeners[event].indexOf(callback);
			if (index > -1) {
				eventListeners[event].splice(index, 1);
				return true;
			}

			return false;
		},

		/**
		 * Trigger custom event
		 *
		 * @param {string} event Event name
		 * @param {Object} data  Event data
		 */
		trigger(event, data) {
			if (!eventListeners[event]) {
				return;
			}

			eventListeners[event].forEach((callback) => {
				try {
					callback(data);
				} catch (error) {
					if (DEBUG_MODE) {
						// eslint-disable-next-line no-console
						console.error(
							`[DSGModal] Error in ${event} callback:`,
							error
						);
					}
				}
			});

			// Also dispatch native DOM event
			const customEvent = new CustomEvent(`dsgo-${event}`, {
				detail: data,
				bubbles: true,
				cancelable: true,
			});
			document.dispatchEvent(customEvent);
		},
	};

	/**
	 * Expose to window for external access
	 */
	window.DSGModal = DSGModal;
	window.dsgoInitModals = initModals;
	window.dsgoInitModalTriggers = initTriggers;
	window.dsgoInitModalCloseTriggers = initCloseTriggers;
	window.dsgoInitHashLinkTriggers = initHashLinkTriggers;
	window.dsgoModal = publicAPI;
	window.dsgoModalAPI = publicAPI; // Alias for backwards compatibility
})();
