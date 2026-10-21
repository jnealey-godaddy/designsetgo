/**
 * Comparison Table Block - Frontend Interactivity
 *
 * Handles tooltip display on hover/focus with accessibility support.
 */

import { __ } from '@wordpress/i18n';

let tooltipCounter = 0;

// The one open tooltip: its trigger and how to close it. A single set of
// page listeners closes it, rather than a set per trigger.
let openTooltip = null;

function closeOpenTooltip() {
	openTooltip?.hide();
}

function listenForOutsideClose() {
	if (listenForOutsideClose.done) {
		return;
	}
	listenForOutsideClose.done = true;
	// Close when tapping elsewhere or when the page/scroll wrapper moves
	// (a fixed tooltip would otherwise stay behind).
	document.addEventListener('click', (e) => {
		if (openTooltip && !openTooltip.trigger.contains(e.target)) {
			closeOpenTooltip();
		}
	});
	window.addEventListener('scroll', closeOpenTooltip, {
		passive: true,
		capture: true,
	});
	window.addEventListener('resize', closeOpenTooltip);
}

function initComparisonTables() {
	const tables = document.querySelectorAll('.dsgo-comparison-table');

	tables.forEach((table) => {
		if (table.dataset.dsgoInitialized) {
			return;
		}
		table.dataset.dsgoInitialized = 'true';

		initTooltips(table);
	});
}

document.addEventListener('DOMContentLoaded', initComparisonTables);
document.addEventListener('dsgo-content-loaded', initComparisonTables);

/**
 * Initializes tooltip behavior for feature row info icons
 *
 * @param {HTMLElement} table - The comparison table wrapper element
 */
function initTooltips(table) {
	const triggers = table.querySelectorAll(
		'.dsgo-comparison-table__tooltip-trigger[data-tooltip]'
	);

	if (!triggers.length) {
		return;
	}
	listenForOutsideClose();

	triggers.forEach((trigger) => {
		const tooltipText = trigger.dataset.tooltip;
		if (!tooltipText) {
			return;
		}

		// save() writes the tooltip text as the trigger's aria-label, which is
		// the right no-JS fallback. Once the tooltip element exists and is
		// wired up as the description, keeping that label as well makes a
		// screen reader read the same sentence twice, so the name becomes a
		// short generic one and the tooltip supplies the detail.
		const tooltipId = `dsgo-tooltip-${++tooltipCounter}`;
		trigger.setAttribute('aria-describedby', tooltipId);
		trigger.setAttribute(
			'aria-label',
			__('More information', 'designsetgo')
		);
		trigger.setAttribute('tabindex', '0');
		trigger.setAttribute('role', 'button');

		// textContent is used (not innerHTML) to prevent XSS from user-supplied tooltip text
		const tooltip = document.createElement('div');
		tooltip.className = 'dsgo-comparison-table__tooltip';
		tooltip.id = tooltipId;
		tooltip.setAttribute('role', 'tooltip');
		tooltip.textContent = tooltipText;
		// On <body>: position: fixed is measured from the nearest transformed
		// ancestor, and animated sections and slider tracks keep a transform,
		// which would throw the tooltip off its trigger. aria-describedby
		// links by id, so it still describes the trigger from here.
		document.body.appendChild(tooltip);

		// A tap (or a click) pins the tooltip open: touch has no hover or
		// focus-on-hover, and a pinned one needs an outside tap to close.
		let pinned = false;

		/**
		 * Places the tooltip above the trigger, clamped to the viewport.
		 * It is position: fixed so the scroll wrapper's overflow can't clip it.
		 */
		const place = () => {
			const rect = trigger.getBoundingClientRect();
			const width = tooltip.offsetWidth;
			const height = tooltip.offsetHeight;
			const margin = 8;
			const left = Math.min(
				Math.max(rect.left + rect.width / 2 - width / 2, margin),
				Math.max(window.innerWidth - width - margin, margin)
			);
			const above = rect.top - height - 6;
			const top = above >= margin ? above : rect.bottom + 6;
			tooltip.style.left = `${left}px`;
			tooltip.style.top = `${top}px`;
		};

		const hide = () => {
			pinned = false;
			tooltip.classList.remove('is-visible');
			if (openTooltip?.trigger === trigger) {
				openTooltip = null;
			}
		};

		const show = () => {
			if (openTooltip && openTooltip.trigger !== trigger) {
				closeOpenTooltip();
			}
			openTooltip = { trigger, hide };
			tooltip.classList.add('is-visible');
			place();
		};

		trigger.addEventListener('mouseenter', show);
		trigger.addEventListener('mouseleave', () => {
			if (!pinned) {
				hide();
			}
		});
		trigger.addEventListener('focus', show);
		trigger.addEventListener('blur', hide);

		trigger.addEventListener('click', () => {
			if (pinned) {
				hide();
			} else {
				pinned = true;
				show();
			}
		});

		trigger.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				hide();
				trigger.blur();
			} else if (e.key === 'Enter' || e.key === ' ') {
				// role="button" on a span: activate like a button.
				e.preventDefault();
				trigger.click();
			}
		});
	});
}
