/**
 * Hotspot frontend interactions.
 *
 * The Hotspot markup carries its resolved parent/child trigger contract in
 * data attributes. Keeping that contract separate from Interaction Layers'
 * generic action array gives the compound block its own small, predictable
 * state controller while retaining the same single-document-delegate model.
 *
 * @package
 */

const PARENT_SELECTOR = '[data-dsgo-hotspot]';
const ITEM_SELECTOR = '[data-dsgo-hotspot-item]';
const MARKER_SELECTOR = '[data-dsgo-hotspot-marker]';
const TOOLTIP_SELECTOR = '[data-dsgo-hotspot-tooltip]';

let delegatesAttached = false;

/**
 * Find the closest matching ancestor for an event target.
 *
 * @param {EventTarget} target   Event target.
 * @param {string}      selector Selector to match.
 * @return {Element|null} Matching ancestor, if any.
 */
function closest(target, selector) {
	if (!target || 'function' !== typeof target.closest) {
		return null;
	}
	return target.closest(selector);
}

/**
 * Resolve an item's trigger from its explicit override or parent default.
 *
 * @param {Element} item Hotspot item.
 * @return {string} Effective trigger.
 */
function getTrigger(item) {
	return (
		item.getAttribute('data-dsgo-hotspot-trigger') ||
		item
			.closest(PARENT_SELECTOR)
			?.getAttribute('data-dsgo-hotspot-trigger') ||
		'click'
	);
}

/**
 * Whether a marker is a normal navigable link.
 *
 * @param {Element|null} marker Marker element.
 * @return {boolean} True for anchors with a destination.
 */
function isLinkedMarker(marker) {
	return 'A' === marker?.tagName && marker.hasAttribute('href');
}

/**
 * Whether a marker uses the hover/focus tooltip behaviour.
 *
 * Linked markers retain their browser navigation and therefore describe their
 * tooltip on hover/focus even when their parent defaults to click.
 *
 * @param {Element} item Hotspot item.
 * @return {boolean} True when hover/focus should control the tooltip.
 */
function opensOnHover(item) {
	return (
		isLinkedMarker(item.querySelector(MARKER_SELECTOR)) ||
		'hover' === getTrigger(item)
	);
}

/**
 * Keep the marker's ARIA relationship consistent with its effective trigger.
 *
 * @param {Element} item   Hotspot item.
 * @param {boolean} isOpen Whether the tooltip is currently open.
 */
function syncItemAria(item, isOpen) {
	const marker = item.querySelector(MARKER_SELECTOR);
	const tooltip = item.querySelector(TOOLTIP_SELECTOR);

	if (!marker || !tooltip?.id) {
		return;
	}

	if (!isLinkedMarker(marker) && 'click' === getTrigger(item)) {
		marker.setAttribute('aria-controls', tooltip.id);
		marker.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		marker.removeAttribute('aria-describedby');
		return;
	}

	marker.setAttribute('aria-describedby', tooltip.id);
	marker.removeAttribute('aria-controls');
	marker.removeAttribute('aria-expanded');
}

/**
 * Apply the only visibility state a Hotspot tooltip can have.
 *
 * @param {Element} item   Hotspot item.
 * @param {boolean} isOpen Whether it should be visible.
 */
function setItemOpen(item, isOpen) {
	const tooltip = item.querySelector(TOOLTIP_SELECTOR);

	item.classList.toggle('is-active', isOpen);

	syncItemAria(item, isOpen);

	if (!tooltip) {
		return;
	}

	tooltip.classList.toggle('is-open', isOpen);
	tooltip.hidden = !isOpen;
	tooltip.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
}

/**
 * Close every item in one parent Hotspot.
 *
 * @param {Element}      parent Hotspot parent.
 * @param {Element|null} except Optional item to leave open.
 */
function closeParent(parent, except = null) {
	parent.querySelectorAll(ITEM_SELECTOR).forEach((item) => {
		if (item !== except) {
			setItemOpen(item, false);
		}
	});
}

/**
 * Open or close a click-triggered marker. One Hotspot parent owns one active
 * item at a time, but distinct parent blocks remain independent.
 *
 * @param {Element} item Hotspot item.
 */
function toggleClickItem(item) {
	const shouldOpen = !item.classList.contains('is-active');
	const parent = item.closest(PARENT_SELECTOR);

	if (!parent) {
		return;
	}

	if (shouldOpen) {
		closeParent(parent, item);
	}
	setItemOpen(item, shouldOpen);
}

/**
 * Whether an item still contains the related pointer/focus target.
 *
 * @param {Element} item  Hotspot item.
 * @param {Event}   event Pointer or focus event.
 * @return {boolean} True when the visitor remains inside the item.
 */
function remainsInside(item, event) {
	return !!event.relatedTarget && item.contains(event.relatedTarget);
}

/**
 * Return the marker that should regain focus after its tooltip closes.
 *
 * @param {Element} parent Hotspot parent.
 * @return {Element|null} Marker to focus, when focus is inside its item.
 */
function focusRestoreMarker(parent) {
	const activeElement = parent.ownerDocument.activeElement;
	const activeItem = closest(activeElement, ITEM_SELECTOR);

	if (!activeItem || !parent.contains(activeItem)) {
		return null;
	}

	const marker = activeItem.querySelector(MARKER_SELECTOR);
	return marker && marker !== activeElement ? marker : null;
}

/**
 * Attach the six delegated document listeners once for every Hotspot block.
 */
function attachDelegates() {
	if (delegatesAttached) {
		return;
	}
	delegatesAttached = true;

	document.addEventListener('click', (event) => {
		const marker = closest(event.target, MARKER_SELECTOR);

		if (marker) {
			// An author who gives a marker a destination expects a normal link.
			// Do not prevent navigation or turn the click into a dead toggle.
			if ('A' === marker.tagName && marker.hasAttribute('href')) {
				return;
			}

			const item = marker.closest(ITEM_SELECTOR);
			if (item && 'click' === getTrigger(item)) {
				toggleClickItem(item);
			}
			return;
		}

		document.querySelectorAll(PARENT_SELECTOR).forEach((parent) => {
			if (!parent.contains(event.target)) {
				closeParent(parent);
			}
		});
	});

	document.addEventListener('pointerover', (event) => {
		const source = closest(
			event.target,
			`${MARKER_SELECTOR}, ${TOOLTIP_SELECTOR}`
		);
		const item = source?.closest(ITEM_SELECTOR);

		if (item && opensOnHover(item)) {
			setItemOpen(item, true);
		}
	});

	document.addEventListener('pointerout', (event) => {
		const source = closest(
			event.target,
			`${MARKER_SELECTOR}, ${TOOLTIP_SELECTOR}`
		);
		const item = source?.closest(ITEM_SELECTOR);

		if (item && opensOnHover(item) && !remainsInside(item, event)) {
			setItemOpen(item, false);
		}
	});

	document.addEventListener('focusin', (event) => {
		const source = closest(
			event.target,
			`${MARKER_SELECTOR}, ${TOOLTIP_SELECTOR}`
		);
		const item = source?.closest(ITEM_SELECTOR);

		if (item && opensOnHover(item)) {
			setItemOpen(item, true);
		}
	});

	document.addEventListener('focusout', (event) => {
		const source = closest(
			event.target,
			`${MARKER_SELECTOR}, ${TOOLTIP_SELECTOR}`
		);
		const item = source?.closest(ITEM_SELECTOR);

		if (item && opensOnHover(item) && !remainsInside(item, event)) {
			setItemOpen(item, false);
		}
	});

	document.addEventListener('keydown', (event) => {
		if ('Escape' !== event.key) {
			return;
		}

		const parents = Array.from(document.querySelectorAll(PARENT_SELECTOR));
		const marker = parents
			.map((parent) => focusRestoreMarker(parent))
			.find(Boolean);

		parents.forEach((parent) => {
			closeParent(parent);
		});

		marker?.focus();
	});
}

/**
 * Initialise Hotspot state. Safe after a soft navigation or repeated call.
 *
 * @param {Element|Document} root Subtree to scan.
 */
export function initHotspots(root = document) {
	attachDelegates();

	const parents = [];
	if (root.matches?.(PARENT_SELECTOR)) {
		parents.push(root);
	}
	root.querySelectorAll(PARENT_SELECTOR).forEach((parent) =>
		parents.push(parent)
	);

	parents.forEach((parent) => {
		if (parent.hasAttribute('data-dsgo-hotspot-initialized')) {
			return;
		}

		parent.setAttribute('data-dsgo-hotspot-initialized', 'true');
		closeParent(parent);
	});
}

if ('undefined' !== typeof document) {
	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', () => initHotspots());
	} else {
		initHotspots();
	}

	document.addEventListener('dsgo-content-loaded', () => initHotspots());
}
