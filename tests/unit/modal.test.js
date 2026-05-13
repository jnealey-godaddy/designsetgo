/**
 * Modal Block - Unit Tests
 *
 * Tests for critical paths in modal functionality.
 *
 * @package
 */

/* global sessionStorage, localStorage, Document, Storage */

// Import modal functionality
import '../../src/blocks/modal/view.js';

/**
 * Mock DOM elements for testing
 * @param {Object} options
 */
function createMockModal(options = {}) {
	const modal = document.createElement('div');
	modal.id = options.id || 'dsgo-modal-test';
	modal.setAttribute('data-dsgo-modal', 'true');
	modal.setAttribute('data-modal-id', options.id || 'dsgo-modal-test');
	modal.setAttribute('data-animation-type', options.animationType || 'fade');
	modal.setAttribute(
		'data-animation-duration',
		options.animationDuration || '300'
	);
	modal.setAttribute(
		'data-close-on-backdrop',
		options.closeOnBackdrop !== false ? 'true' : 'false'
	);
	modal.setAttribute(
		'data-close-on-esc',
		options.closeOnEsc !== false ? 'true' : 'false'
	);
	modal.setAttribute(
		'data-disable-body-scroll',
		options.disableBodyScroll !== false ? 'true' : 'false'
	);

	// Create modal structure
	const backdrop = document.createElement('div');
	backdrop.className = 'dsgo-modal__backdrop';
	modal.appendChild(backdrop);

	const dialog = document.createElement('div');
	dialog.className = 'dsgo-modal__dialog';
	modal.appendChild(dialog);

	const content = document.createElement('div');
	content.className = 'dsgo-modal__content';
	dialog.appendChild(content);

	const closeButton = document.createElement('button');
	closeButton.className = 'dsgo-modal__close';
	closeButton.setAttribute('type', 'button');
	content.appendChild(closeButton);

	// Add to document
	document.body.appendChild(modal);

	return modal;
}

/**
 * Clean up test modals
 */
function cleanupModals() {
	document.querySelectorAll('[data-dsgo-modal]').forEach((el) => el.remove());
	document.body.classList.remove('dsgo-modal-open');
	document.body.style.top = '';
	document.body.style.paddingRight = '';
}

describe('DSGModal - Core Functionality', () => {
	afterEach(() => {
		cleanupModals();
	});

	describe('Initialization', () => {
		test('should initialize modal instance', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			expect(instance).toBeDefined();
			expect(instance.modal).toBe(modal);
			expect(instance.modalId).toBe('dsgo-modal-test');
			expect(instance.isOpen).toBe(false);
		});

		test('should prevent duplicate initialization', () => {
			const modal = createMockModal();
			new window.DSGModal(modal);
			new window.DSGModal(modal);

			// Second init should return early (check by verifying data attribute)
			expect(modal.getAttribute('data-dsgo-initialized')).toBe('true');
		});

		test('should respect reduced motion preference', () => {
			// Mock matchMedia
			const mockMatchMedia = jest.fn().mockImplementation((query) => ({
				matches: query === '(prefers-reduced-motion: reduce)',
				media: query,
				addEventListener: jest.fn(),
				removeEventListener: jest.fn(),
			}));
			global.matchMedia = mockMatchMedia;

			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			expect(instance.prefersReducedMotion).toBe(true);
		});
	});

	describe('Cookie Methods - Security Fixes', () => {
		beforeEach(() => {
			// Clear all cookies
			document.cookie.split(';').forEach((c) => {
				document.cookie = c
					.replace(/^ +/, '')
					.replace(
						/=.*/,
						`=;expires=${new Date().toUTCString()};path=/`
					);
			});
		});

		test('setCookie should encode values', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			instance.setCookie('test_cookie', 'value with spaces', 1);

			const cookieValue = instance.getCookie('test_cookie');
			expect(cookieValue).toBe('value with spaces');
		});

		test('setCookie should use SameSite=Strict', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			// Spy on document.cookie setter to verify the full cookie string
			let setCookieValue = '';
			const originalDescriptor = Object.getOwnPropertyDescriptor(
				Document.prototype,
				'cookie'
			);
			Object.defineProperty(document, 'cookie', {
				set: (value) => {
					setCookieValue = value;
				},
				get: originalDescriptor.get,
				configurable: true,
			});

			instance.setCookie('test_cookie', 'test', 1);

			expect(setCookieValue).toContain('SameSite=Strict');

			// Restore original
			Object.defineProperty(
				document,
				'cookie',
				originalDescriptor || { value: '', writable: true }
			);
		});

		test('setCookie should not add Secure flag on non-HTTPS', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			// Spy on document.cookie setter
			let setCookieValue = '';
			const originalDescriptor = Object.getOwnPropertyDescriptor(
				Document.prototype,
				'cookie'
			);
			Object.defineProperty(document, 'cookie', {
				set: (value) => {
					setCookieValue = value;
				},
				get: originalDescriptor.get,
				configurable: true,
			});

			// jsdom runs on http:// by default and window.location.protocol cannot be mocked
			instance.setCookie('test_cookie', 'test', 1);

			// On non-HTTPS, cookie should NOT contain Secure flag
			expect(setCookieValue).not.toContain('Secure');
			// But should still contain SameSite=Strict
			expect(setCookieValue).toContain('SameSite=Strict');

			// Restore original
			Object.defineProperty(
				document,
				'cookie',
				originalDescriptor || { value: '', writable: true }
			);
		});

		test('getCookie should handle invalid values safely', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			// Set cookie with invalid encoded value
			document.cookie = 'invalid_cookie=%E0%A4%A;path=/';

			// Should not throw error - returning null/undefined for invalid cookies is safe
			expect(() => {
				instance.getCookie('invalid_cookie');
			}).not.toThrow();
		});

		test('getCookie should not be vulnerable to ReDoS', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			// Attempt ReDoS attack with many backslashes
			const attackString = 'a'.repeat(100) + '\\'.repeat(100);

			const start = performance.now();
			instance.getCookie(attackString);
			const end = performance.now();

			// Should complete in less than 100ms (ReDoS would take seconds)
			expect(end - start).toBeLessThan(100);
		});
	});

	describe('Open and Close', () => {
		test('should open modal', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			instance.open();

			expect(instance.isOpen).toBe(true);
			expect(modal.getAttribute('aria-hidden')).toBe('false');
			expect(modal.style.display).toBe('flex');
		});

		test('should close modal', (done) => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			instance.open();
			instance.close();

			// Wait for animation
			setTimeout(() => {
				expect(instance.isOpen).toBe(false);
				expect(modal.getAttribute('aria-hidden')).toBe('true');
				done();
			}, 350);
		});

		test('should disable body scroll when modal opens', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			instance.open();

			expect(document.body.classList.contains('dsgo-modal-open')).toBe(
				true
			);
			expect(document.body.style.top).toBeTruthy();
		});

		test('should restore focus to trigger element on close', (done) => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			const trigger = document.createElement('button');
			document.body.appendChild(trigger);
			trigger.focus();

			instance.open(trigger);
			instance.close();

			setTimeout(() => {
				expect(document.activeElement).toBe(trigger);
				trigger.remove();
				done();
			}, 350);
		});
	});

	describe('Focus Trap', () => {
		test('should trap focus within modal', () => {
			const modal = createMockModal();
			const content = modal.querySelector('.dsgo-modal__content');

			// Add focusable elements with mocked offsetParent (jsdom doesn't support layout)
			const button1 = document.createElement('button');
			const button2 = document.createElement('button');
			Object.defineProperty(button1, 'offsetParent', {
				get: () => document.body,
			});
			Object.defineProperty(button2, 'offsetParent', {
				get: () => document.body,
			});
			content.appendChild(button1);
			content.appendChild(button2);

			// Also mock offsetParent on the close button
			const closeButton = modal.querySelector('.dsgo-modal__close');
			if (closeButton) {
				Object.defineProperty(closeButton, 'offsetParent', {
					get: () => document.body,
				});
			}

			const instance = new window.DSGModal(modal);
			instance.open();

			expect(instance.focusableElements.length).toBeGreaterThan(0);
		});

		test('should update focusable elements when content changes', () => {
			const modal = createMockModal();
			const content = modal.querySelector('.dsgo-modal__content');

			// Mock offsetParent on existing close button
			const closeButton = modal.querySelector('.dsgo-modal__close');
			if (closeButton) {
				Object.defineProperty(closeButton, 'offsetParent', {
					get: () => document.body,
					configurable: true,
				});
			}

			const instance = new window.DSGModal(modal);
			instance.updateFocusableElements();
			const initialCount = instance.focusableElements.length;

			// Add new button - must be added to DOM before setting offsetParent
			const newButton = document.createElement('button');
			content.appendChild(newButton);

			// Mock offsetParent AFTER the element is in the DOM
			Object.defineProperty(newButton, 'offsetParent', {
				get: () => document.body,
				configurable: true,
			});

			// Clear cache and update focusable elements
			instance.focusableElementsCached = false;
			instance.updateFocusableElements();

			expect(instance.focusableElements.length).toBeGreaterThan(
				initialCount
			);
		});
	});

	describe('Auto-Trigger Frequency', () => {
		beforeEach(() => {
			// Clear storage
			sessionStorage.clear();
			localStorage.clear();
		});

		test('should trigger "always" frequency every time', () => {
			const modal = createMockModal();
			modal.setAttribute('data-auto-trigger-frequency', 'always');

			const instance = new window.DSGModal(modal);

			expect(instance.checkTriggerFrequency()).toBe(true);
			expect(instance.checkTriggerFrequency()).toBe(true);
		});

		test('should respect "session" frequency', () => {
			const modal = createMockModal();
			modal.setAttribute('data-auto-trigger-frequency', 'session');

			const instance = new window.DSGModal(modal);

			expect(instance.checkTriggerFrequency()).toBe(true);

			instance.markAsShown();

			expect(instance.checkTriggerFrequency()).toBe(false);
		});

		test('should respect "once" frequency', () => {
			const modal = createMockModal();
			modal.setAttribute('data-auto-trigger-frequency', 'once');
			modal.setAttribute('data-cookie-duration', '7');

			const instance = new window.DSGModal(modal);

			expect(instance.checkTriggerFrequency()).toBe(true);

			instance.markAsShown();

			expect(instance.checkTriggerFrequency()).toBe(false);
		});
	});

	describe('Storage Fallback - Blocked Storage', () => {
		// Each test uses a unique modal id so the module-scoped in-memory
		// fallback (which persists across tests) doesn't bleed state.

		test('"session" frequency tolerates sessionStorage throwing and uses memory fallback', () => {
			const getSpy = jest
				.spyOn(Storage.prototype, 'getItem')
				.mockImplementation(() => {
					throw new Error('SecurityError: storage blocked');
				});
			const setSpy = jest
				.spyOn(Storage.prototype, 'setItem')
				.mockImplementation(() => {
					throw new Error('SecurityError: storage blocked');
				});

			try {
				const modal = createMockModal({ id: 'blocked-session' });
				modal.setAttribute('data-auto-trigger-frequency', 'session');
				const instance = new window.DSGModal(modal);

				expect(() => instance.checkTriggerFrequency()).not.toThrow();
				expect(instance.checkTriggerFrequency()).toBe(true);

				expect(() => instance.markAsShown()).not.toThrow();

				// In-memory fallback should now suppress repeat triggers.
				expect(instance.checkTriggerFrequency()).toBe(false);
			} finally {
				getSpy.mockRestore();
				setSpy.mockRestore();
			}
		});

		test('"once" frequency tolerates localStorage and document.cookie throwing', () => {
			const getSpy = jest
				.spyOn(Storage.prototype, 'getItem')
				.mockImplementation(() => {
					throw new Error('SecurityError: storage blocked');
				});
			const setSpy = jest
				.spyOn(Storage.prototype, 'setItem')
				.mockImplementation(() => {
					throw new Error('SecurityError: storage blocked');
				});

			const originalDescriptor = Object.getOwnPropertyDescriptor(
				Document.prototype,
				'cookie'
			);
			Object.defineProperty(document, 'cookie', {
				get: () => {
					throw new Error('SecurityError: cookie blocked');
				},
				set: () => {
					throw new Error('SecurityError: cookie blocked');
				},
				configurable: true,
			});

			try {
				const modal = createMockModal({ id: 'blocked-once' });
				modal.setAttribute('data-auto-trigger-frequency', 'once');
				modal.setAttribute('data-cookie-duration', '7');
				const instance = new window.DSGModal(modal);

				expect(() => instance.checkTriggerFrequency()).not.toThrow();
				expect(instance.checkTriggerFrequency()).toBe(true);

				expect(() => instance.markAsShown()).not.toThrow();

				// In-memory fallback should now suppress repeat triggers.
				expect(instance.checkTriggerFrequency()).toBe(false);
			} finally {
				getSpy.mockRestore();
				setSpy.mockRestore();
				Object.defineProperty(
					document,
					'cookie',
					originalDescriptor || { value: '', writable: true }
				);
			}
		});

		test('getCookie returns undefined without throwing when document.cookie read throws', () => {
			const originalDescriptor = Object.getOwnPropertyDescriptor(
				Document.prototype,
				'cookie'
			);
			Object.defineProperty(document, 'cookie', {
				get: () => {
					throw new Error('SecurityError: cookie blocked');
				},
				set: () => {
					throw new Error('SecurityError: cookie blocked');
				},
				configurable: true,
			});

			try {
				const modal = createMockModal({ id: 'blocked-cookie-read' });
				const instance = new window.DSGModal(modal);

				let result;
				expect(() => {
					result = instance.getCookie('nonexistent-key');
				}).not.toThrow();
				expect(result).toBeUndefined();
			} finally {
				Object.defineProperty(
					document,
					'cookie',
					originalDescriptor || { value: '', writable: true }
				);
			}
		});
	});

	describe('Event Cleanup', () => {
		test('should remove all event listeners on destroy', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			// Open and close to attach listeners
			instance.open();
			instance.close();

			// Spy on removeEventListener
			const removeSpy = jest.spyOn(document, 'removeEventListener');

			instance.destroy();

			// Should have removed keyboard listeners
			expect(removeSpy).toHaveBeenCalled();

			removeSpy.mockRestore();
		});

		test('should clear all timeouts on destroy', () => {
			const modal = createMockModal();
			modal.setAttribute('data-auto-trigger-type', 'pageLoad');
			modal.setAttribute('data-auto-trigger-delay', '5');

			const instance = new window.DSGModal(modal);

			expect(instance.pageLoadTimeout).toBeDefined();

			instance.destroy();

			// Timeout should be cleared (can't directly test, but check that destroy completes)
			expect(instance.modal.hasAttribute('data-dsgo-initialized')).toBe(
				false
			);
		});

		test('should disconnect MutationObserver on destroy', () => {
			const modal = createMockModal();
			const instance = new window.DSGModal(modal);

			// Verify observer exists before destroy
			expect(instance.contentObserver).toBeDefined();

			instance.destroy();

			expect(instance.contentObserver).toBeNull();
		});
	});
});

describe('Public API (window.dsgoModal)', () => {
	afterEach(() => {
		cleanupModals();
	});

	test('should expose global API', () => {
		expect(window.dsgoModal).toBeDefined();
		expect(typeof window.dsgoModal.open).toBe('function');
		expect(typeof window.dsgoModal.close).toBe('function');
		expect(typeof window.dsgoModal.isOpen).toBe('function');
	});

	test('open() should open modal by ID', () => {
		const modal = createMockModal({ id: 'test-api' });
		const instance = new window.DSGModal(modal);
		modal.dsgoModalInstance = instance;

		const result = window.dsgoModal.open('test-api');

		expect(result).toBe(true);
		expect(instance.isOpen).toBe(true);
	});

	test('close() should close modal by ID', (done) => {
		const modal = createMockModal({ id: 'test-api' });
		const instance = new window.DSGModal(modal);
		modal.dsgoModalInstance = instance;

		instance.open();
		window.dsgoModal.close('test-api');

		setTimeout(() => {
			expect(instance.isOpen).toBe(false);
			done();
		}, 350);
	});

	test('isOpen() should return modal state', () => {
		const modal = createMockModal({ id: 'test-api' });
		const instance = new window.DSGModal(modal);
		modal.dsgoModalInstance = instance;

		expect(window.dsgoModal.isOpen('test-api')).toBe(false);

		instance.open();

		expect(window.dsgoModal.isOpen('test-api')).toBe(true);
	});

	test('on() should register event listener', () => {
		const callback = jest.fn();

		const unsubscribe = window.dsgoModal.on('modalOpen', callback);

		const modal = createMockModal({ id: 'test-api' });
		const instance = new window.DSGModal(modal);
		modal.dsgoModalInstance = instance;

		instance.open();

		expect(callback).toHaveBeenCalledWith(
			expect.objectContaining({
				modalId: 'test-api',
			})
		);

		unsubscribe();
	});

	test('off() should remove event listener', () => {
		const callback = jest.fn();

		window.dsgoModal.on('modalOpen', callback);
		window.dsgoModal.off('modalOpen', callback);

		const modal = createMockModal({ id: 'test-api' });
		const instance = new window.DSGModal(modal);
		modal.dsgoModalInstance = instance;

		instance.open();

		expect(callback).not.toHaveBeenCalled();
	});

	test('getAllModals() should return all modal instances', () => {
		const modal1 = createMockModal({ id: 'modal-1' });
		const modal2 = createMockModal({ id: 'modal-2' });

		const instance1 = new window.DSGModal(modal1);
		const instance2 = new window.DSGModal(modal2);

		modal1.dsgoModalInstance = instance1;
		modal2.dsgoModalInstance = instance2;

		const allModals = window.dsgoModal.getAllModals();

		expect(allModals.length).toBe(2);
		expect(allModals[0].id).toBe('modal-1');
		expect(allModals[1].id).toBe('modal-2');
	});
});

describe('Gallery Navigation', () => {
	afterEach(() => {
		cleanupModals();
	});

	test('should discover gallery modals', () => {
		const modal1 = createMockModal({ id: 'gallery-1' });
		const modal2 = createMockModal({ id: 'gallery-2' });

		modal1.setAttribute('data-gallery-group-id', 'my-gallery');
		modal1.setAttribute('data-gallery-index', '0');
		modal2.setAttribute('data-gallery-group-id', 'my-gallery');
		modal2.setAttribute('data-gallery-index', '1');

		const instance1 = new window.DSGModal(modal1);
		modal1.dsgoModalInstance = instance1;

		const instance2 = new window.DSGModal(modal2);
		modal2.dsgoModalInstance = instance2;

		// Invalidate cache from constructor (which ran before dsgoModalInstance was set)
		instance1.invalidateGalleryCache();
		instance1.updateGalleryModals();

		expect(instance1.galleryModals.length).toBe(2);
	});

	test('should navigate to next modal', (done) => {
		const modal1 = createMockModal({ id: 'gallery-1' });
		const modal2 = createMockModal({ id: 'gallery-2' });

		modal1.setAttribute('data-gallery-group-id', 'my-gallery');
		modal1.setAttribute('data-gallery-index', '0');
		modal2.setAttribute('data-gallery-group-id', 'my-gallery');
		modal2.setAttribute('data-gallery-index', '1');

		const instance1 = new window.DSGModal(modal1);
		modal1.dsgoModalInstance = instance1;

		const instance2 = new window.DSGModal(modal2);
		modal2.dsgoModalInstance = instance2;

		instance1.invalidateGalleryCache();
		instance1.updateGalleryModals();

		instance1.open();
		instance1.navigateToNext();

		// Wait for animation (300ms duration + buffer)
		setTimeout(() => {
			expect(instance1.isOpen).toBe(false);
			expect(instance2.isOpen).toBe(true);
			done();
		}, 400);
	});

	test('should navigate to previous modal', (done) => {
		const modal1 = createMockModal({ id: 'gallery-1' });
		const modal2 = createMockModal({ id: 'gallery-2' });

		modal1.setAttribute('data-gallery-group-id', 'my-gallery');
		modal1.setAttribute('data-gallery-index', '0');
		modal2.setAttribute('data-gallery-group-id', 'my-gallery');
		modal2.setAttribute('data-gallery-index', '1');

		const instance1 = new window.DSGModal(modal1);
		modal1.dsgoModalInstance = instance1;

		const instance2 = new window.DSGModal(modal2);
		modal2.dsgoModalInstance = instance2;

		instance2.invalidateGalleryCache();
		instance2.updateGalleryModals();

		instance2.open();
		instance2.navigateToPrevious();

		// Wait for animation (300ms duration + buffer)
		setTimeout(() => {
			expect(instance2.isOpen).toBe(false);
			expect(instance1.isOpen).toBe(true);
			done();
		}, 400);
	});
});
