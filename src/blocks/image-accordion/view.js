/**
 * Image Accordion Frontend JavaScript
 *
 * Handles expansion/collapse interactions with support for:
 * - Hover (desktop)
 * - Click/tap (mobile)
 * - Keyboard navigation (Tab, Enter, Space)
 * - Touch device detection
 * - Accessibility (ARIA attributes)
 *
 * @since 1.0.0
 */

/* global navigator */

function initImageAccordions() {
	const accordions = document.querySelectorAll('.dsgo-image-accordion');

	// Detect touch devices
	const isTouchDevice =
		'ontouchstart' in window || navigator.maxTouchPoints > 0;

	accordions.forEach((accordion) => {
		// Prevent duplicate initialization
		if (accordion.dataset.dsgoInitialized) {
			return;
		}
		accordion.dataset.dsgoInitialized = 'true';

		const triggerType =
			accordion.getAttribute('data-trigger-type') || 'hover';
		const defaultExpandedIndex =
			parseInt(accordion.getAttribute('data-default-expanded'), 10) || 0;
		const items = accordion.querySelectorAll('.dsgo-image-accordion-item');

		if (items.length === 0) {
			return;
		}

		/**
		 * Expand an item and collapse all others
		 * @param {HTMLElement} itemToExpand - The accordion item to expand.
		 */
		function expandItem(itemToExpand) {
			items.forEach((item) => {
				if (item === itemToExpand) {
					item.classList.add('is-expanded');
					item.classList.remove('is-collapsed');
					item.setAttribute('aria-expanded', 'true');
				} else {
					item.classList.remove('is-expanded');
					item.classList.add('is-collapsed');
					item.setAttribute('aria-expanded', 'false');
				}
			});
		}

		/**
		 * Reset all items to default state
		 */
		function resetItems() {
			items.forEach((item) => {
				item.classList.remove('is-expanded', 'is-collapsed');
				item.setAttribute('aria-expanded', 'false');
			});
		}

		/**
		 * The item the URL hash points at: an item's anchor, or anything
		 * inside an item.
		 *
		 * @return {HTMLElement|null} Matching item.
		 */
		function getHashItem() {
			let target = null;
			try {
				const id = decodeURIComponent(window.location.hash.slice(1));
				target = id ? document.getElementById(id) : null;
			} catch (error) {
				return null;
			}
			const item = target?.closest('.dsgo-image-accordion-item');
			return item && Array.from(items).includes(item) ? item : null;
		}

		// The item the accordion rests on when nothing is hovered: the one a
		// deep link names, else the author's default (1-based), else none.
		let restingItem =
			getHashItem() ||
			(defaultExpandedIndex > 0 && defaultExpandedIndex <= items.length
				? items[defaultExpandedIndex - 1]
				: null);

		function showRestingItem() {
			if (restingItem) {
				expandItem(restingItem);
			} else {
				resetItems();
			}
		}

		// Initialize: Set default expanded item
		showRestingItem();

		window.addEventListener('hashchange', function () {
			const hashItem = getHashItem();
			if (hashItem) {
				restingItem = hashItem;
				showRestingItem();
				hashItem.scrollIntoView({ block: 'nearest' });
			}
		});

		// Add ARIA attributes to all items
		items.forEach((item, index) => {
			item.setAttribute('role', 'button');
			item.setAttribute('aria-label', `Image panel ${index + 1}`);
			item.setAttribute('aria-expanded', 'false');

			// Make focusable if not already
			if (!item.hasAttribute('tabindex')) {
				item.setAttribute('tabindex', '0');
			}
		});

		// HOVER INTERACTION (Desktop only, not on touch devices)
		if (
			(triggerType === 'hover' && !isTouchDevice) ||
			triggerType === 'hover'
		) {
			items.forEach((item) => {
				// Mouse enter: expand this item
				item.addEventListener('mouseenter', function () {
					// Only apply hover on non-touch devices
					if (!isTouchDevice) {
						expandItem(item);
					}
				});

				// Focus: expand this item (keyboard navigation)
				item.addEventListener('focus', function () {
					expandItem(item);
				});
			});

			// Reset on accordion mouse leave (optional - creates nice reset effect)
			accordion.addEventListener('mouseleave', function () {
				if (!isTouchDevice) {
					showRestingItem();
				}
			});
		}

		// CLICK/TAP INTERACTION (Always enabled on touch devices, or when explicitly set)
		if (isTouchDevice || triggerType === 'click') {
			items.forEach((item) => {
				item.addEventListener('click', function (e) {
					// Don't trigger if clicking on a link or button inside the item
					const isInteractive =
						e.target.tagName === 'A' ||
						e.target.tagName === 'BUTTON' ||
						e.target.closest('a') ||
						e.target.closest('button');

					if (!isInteractive) {
						expandItem(item);
					}
				});

				// Keyboard support (Enter/Space)
				item.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						expandItem(item);
					}
				});
			});
		}

		// KEYBOARD NAVIGATION
		// Arrow keys to navigate between items
		accordion.addEventListener('keydown', function (e) {
			const focusedItem = e.target.ownerDocument.activeElement;
			const focusedIndex = Array.from(items).indexOf(focusedItem);

			if (focusedIndex === -1) {
				return;
			}

			let nextIndex;

			switch (e.key) {
				case 'ArrowRight':
				case 'ArrowDown':
					e.preventDefault();
					nextIndex = (focusedIndex + 1) % items.length;
					items[nextIndex].focus();
					break;

				case 'ArrowLeft':
				case 'ArrowUp':
					e.preventDefault();
					nextIndex =
						(focusedIndex - 1 + items.length) % items.length;
					items[nextIndex].focus();
					break;

				case 'Home':
					e.preventDefault();
					items[0].focus();
					break;

				case 'End':
					e.preventDefault();
					items[items.length - 1].focus();
					break;
			}
		});
	});
}

document.addEventListener('DOMContentLoaded', initImageAccordions);
document.addEventListener('dsgo-content-loaded', initImageAccordions);
