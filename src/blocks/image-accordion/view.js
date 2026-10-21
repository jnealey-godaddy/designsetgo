/**
 * Image Accordion Frontend JavaScript
 *
 * Handles expansion/collapse interactions with support for:
 * - Hover (desktop)
 * - Click/tap (mobile)
 * - Keyboard navigation (Tab, Enter, Space)
 * - Per-interaction hover capability detection
 * - Accessibility (ARIA attributes)
 *
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';

function initImageAccordions() {
	const accordions = document.querySelectorAll('.dsgo-image-accordion');

	// Evaluated per interaction, not once: touch laptops and iPads with a
	// mouse have both a touch screen and a hovering pointer.
	const canHover = () => window.matchMedia('(hover: hover)').matches;

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
				} else {
					item.classList.remove('is-expanded');
					item.classList.add('is-collapsed');
				}
			});
		}

		/**
		 * Reset all items to default state
		 */
		function resetItems() {
			items.forEach((item) => {
				item.classList.remove('is-expanded', 'is-collapsed');
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
			} catch {
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

		const onHashChange = function () {
			// Soft navigation removed the accordion: drop the listener.
			if (!accordion.isConnected) {
				window.removeEventListener('hashchange', onHashChange);
				return;
			}
			const hashItem = getHashItem();
			if (hashItem) {
				restingItem = hashItem;
				showRestingItem();
				hashItem.scrollIntoView({ block: 'nearest' });
			}
		};
		window.addEventListener('hashchange', onHashChange);

		// ARIA: a panel holds a heading, caption and possibly links, so it is
		// a labelled group (not role="button", whose name would replace its
		// content for screen readers and hide links inside). The saved markup
		// carries role="button"; replace it here. Name it from its heading
		// when there is one, else fall back to "Image panel N".
		items.forEach((item, index) => {
			item.setAttribute('role', 'group');
			item.removeAttribute('aria-expanded');
			const heading = item.querySelector(
				'h1, h2, h3, h4, h5, h6, [role="heading"]'
			);
			if (heading) {
				if (!heading.id) {
					heading.id = `dsgo-image-accordion-${index + 1}-${Math.random()
						.toString(36)
						.slice(2, 8)}-heading`;
				}
				item.setAttribute('aria-labelledby', heading.id);
				item.removeAttribute('aria-label');
			} else {
				item.setAttribute(
					'aria-label',
					/* translators: %d: panel number */
					sprintf(__('Image panel %d', 'designsetgo'), index + 1)
				);
			}

			// Make focusable if not already
			if (!item.hasAttribute('tabindex')) {
				item.setAttribute('tabindex', '0');
			}
		});

		// HOVER INTERACTION (mouse pointers only; decided per event)
		if (triggerType === 'hover') {
			items.forEach((item) => {
				// Tapping on a touch screen also fires mouseenter, so
				// ask the pointer's capability at the moment it happens.
				item.addEventListener('mouseenter', function () {
					if (canHover()) {
						expandItem(item);
					}
				});

				// Focus: expand this item (keyboard navigation)
				item.addEventListener('focus', function () {
					expandItem(item);
				});
			});

			accordion.addEventListener('mouseleave', function () {
				if (canHover()) {
					showRestingItem();
				}
			});
		}

		// CLICK/TAP: always for click mode; in hover mode, for pointers that
		// cannot hover (touch), so panels stay reachable.
		items.forEach((item) => {
			item.addEventListener('click', function (e) {
				if (triggerType !== 'click' && canHover()) {
					return;
				}
				// Don't trigger if clicking on a link or button inside the item
				if (e.target.closest('a, button')) {
					return;
				}
				expandItem(item);
			});

			// Keyboard support (Enter/Space)
			item.addEventListener('keydown', function (e) {
				if (e.target !== item) {
					return;
				}
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					expandItem(item);
				}
			});
		});

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
