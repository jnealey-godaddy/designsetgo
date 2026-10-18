/**
 * Jest Setup File
 *
 * Runs before each test suite.
 *
 * @package
 */

/* eslint-disable @wordpress/no-global-active-element */

// Mock matchMedia
global.matchMedia =
	global.matchMedia ||
	function () {
		return {
			matches: false,
			media: '',
			onchange: null,
			addListener: jest.fn(),
			removeListener: jest.fn(),
			addEventListener: jest.fn(),
			removeEventListener: jest.fn(),
			dispatchEvent: jest.fn(),
		};
	};

// Mock performance.now for timing tests
global.performance = global.performance || {
	now: () => Date.now(),
};

// Load modal view script
// Note: In actual implementation, you would load the built file
// For now, this is a placeholder
global.DSGModal = class MockDSGModal {
	constructor(element) {
		this.modal = element;
		this.modalId = element.getAttribute('data-modal-id');
		this.isOpen = false;
		this.settings = {};
		this.focusableElements = [];
		this.init();
	}

	init() {
		if (this.modal.hasAttribute('data-dsgo-initialized')) {
			return;
		}
		this.modal.setAttribute('data-dsgo-initialized', 'true');
		this.prefersReducedMotion = global.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;
	}

	open(trigger = null) {
		this.isOpen = true;
		this.modal.style.display = 'flex';
		this.modal.setAttribute('aria-hidden', 'false');
		this.previouslyFocusedElement = trigger || document.activeElement;
		this.disableBodyScroll();
	}

	close() {
		this.isOpen = false;
		this.modal.style.display = 'none';
		this.modal.setAttribute('aria-hidden', 'true');
		this.enableBodyScroll();
		if (
			this.previouslyFocusedElement &&
			this.previouslyFocusedElement.focus
		) {
			this.previouslyFocusedElement.focus();
		}
	}

	disableBodyScroll() {
		document.body.classList.add('dsgo-modal-open');
		document.body.style.top = `-${window.pageYOffset || 0}px`;
	}

	enableBodyScroll() {
		document.body.classList.remove('dsgo-modal-open');
		document.body.style.top = '';
		document.body.style.paddingRight = '';
	}

	getCookie(name) {
		const cookieString = `; ${document.cookie}`;
		const parts = cookieString.split(`; ${name}=`);
		if (parts.length === 2) {
			const value = parts.pop().split(';').shift();
			try {
				return decodeURIComponent(value);
			} catch {
				return value;
			}
		}
		return undefined;
	}

	setCookie(name, value, days) {
		const expires = new Date();
		expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
		const encodedValue = encodeURIComponent(value);
		const sameSite = 'Strict';
		const secure =
			global.window?.location?.protocol === 'https:' ? ';Secure' : '';
		document.cookie = `${name}=${encodedValue};expires=${expires.toUTCString()};path=/;SameSite=${sameSite}${secure}`;
	}

	checkTriggerFrequency() {
		const frequency = this.settings.autoTriggerFrequency || 'always';
		if (frequency === 'always') {
			return true;
		}

		const storageKey = `dsgo_modal_${this.modalId}_shown`;

		if (frequency === 'session') {
			if (window.sessionStorage.getItem(storageKey)) {
				return false;
			}
		}

		if (frequency === 'once') {
			if (window.localStorage.getItem(storageKey)) {
				return false;
			}
			if (this.getCookie(storageKey)) {
				return false;
			}
		}

		return true;
	}

	markAsShown() {
		const frequency = this.settings.autoTriggerFrequency || 'always';
		if (frequency === 'always') {
			return;
		}

		const storageKey = `dsgo_modal_${this.modalId}_shown`;

		if (frequency === 'session') {
			window.sessionStorage.setItem(storageKey, 'true');
		}

		if (frequency === 'once') {
			window.localStorage.setItem(storageKey, 'true');
			this.setCookie(
				storageKey,
				'true',
				this.settings.cookieDuration || 7
			);
		}
	}

	updateFocusableElements() {
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
		).filter(
			(el) => el.offsetParent !== null && !el.hasAttribute('aria-hidden')
		);
	}

	updateGalleryModals() {
		const groupId = this.settings.galleryGroupId;
		if (!groupId) {
			return;
		}

		const allModals = document.querySelectorAll(
			`[data-gallery-group-id="${groupId}"]`
		);

		this.galleryModals = Array.from(allModals)
			.map((modalEl) => ({
				element: modalEl,
				instance: modalEl.dsgoModalInstance,
				index:
					parseInt(modalEl.getAttribute('data-gallery-index')) || 0,
				modalId: modalEl.getAttribute('data-modal-id'),
			}))
			.filter((item) => item.instance)
			.sort((a, b) => a.index - b.index);

		this.currentGalleryIndex = this.galleryModals.findIndex(
			(item) => item.modalId === this.modalId
		);
	}

	navigateToNext() {
		if (!this.galleryModals || this.galleryModals.length === 0) {
			return;
		}

		const nextIndex =
			(this.currentGalleryIndex + 1) % this.galleryModals.length;
		this.navigateToModal(nextIndex);
	}

	navigateToPrevious() {
		if (!this.galleryModals || this.galleryModals.length === 0) {
			return;
		}

		const prevIndex =
			(this.currentGalleryIndex - 1 + this.galleryModals.length) %
			this.galleryModals.length;
		this.navigateToModal(prevIndex);
	}

	navigateToModal(targetIndex) {
		if (targetIndex < 0 || targetIndex >= this.galleryModals.length) {
			return;
		}

		const targetModal = this.galleryModals[targetIndex];
		if (!targetModal || !targetModal.instance) {
			return;
		}

		this.close();
		setTimeout(() => {
			targetModal.instance.open();
		}, 50);
	}

	destroy() {
		this.modal.removeAttribute('data-dsgo-initialized');
		this.contentObserver = null;
	}
};

// Mock public API
global.window.dsgoModal = {
	open: (modalId, options = {}) => {
		const modalElement = document.getElementById(modalId);
		if (!modalElement || !modalElement.dsgoModalInstance) {
			return false;
		}
		modalElement.dsgoModalInstance.open(options.trigger || null);
		return true;
	},

	close: (modalId) => {
		if (!modalId) {
			return 0;
		}
		const modalElement = document.getElementById(modalId);
		if (!modalElement || !modalElement.dsgoModalInstance) {
			return false;
		}
		modalElement.dsgoModalInstance.close();
		return true;
	},

	isOpen: (modalId) => {
		const modalElement = document.getElementById(modalId);
		return modalElement?.dsgoModalInstance?.isOpen || false;
	},

	getInstance: (modalId) => {
		const modalElement = document.getElementById(modalId);
		return modalElement?.dsgoModalInstance || null;
	},

	getAllModals: () => {
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

	on: (event, callback) => {
		if (!global.eventListeners) {
			global.eventListeners = {
				modalOpen: [],
				modalClose: [],
				modalBeforeOpen: [],
				modalBeforeClose: [],
			};
		}
		if (!global.eventListeners[event]) {
			return () => {};
		}
		global.eventListeners[event].push(callback);
		return () => {
			const index = global.eventListeners[event].indexOf(callback);
			if (index > -1) {
				global.eventListeners[event].splice(index, 1);
			}
		};
	},

	off: (event, callback) => {
		if (!global.eventListeners || !global.eventListeners[event]) {
			return false;
		}
		const index = global.eventListeners[event].indexOf(callback);
		if (index > -1) {
			global.eventListeners[event].splice(index, 1);
			return true;
		}
		return false;
	},

	trigger: (event, data) => {
		if (!global.eventListeners || !global.eventListeners[event]) {
			return;
		}
		global.eventListeners[event].forEach((callback) => {
			try {
				callback(data);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error(`Error in ${event} callback:`, error);
			}
		});
	},
};

global.window.DSGModal = global.DSGModal;
