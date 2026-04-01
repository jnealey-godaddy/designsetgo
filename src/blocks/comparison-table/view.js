/**
 * Comparison Table Block - Frontend Interactivity
 *
 * Handles tooltip display on hover/focus with accessibility support.
 */

let tooltipCounter = 0;

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

	triggers.forEach((trigger) => {
		const tooltipText = trigger.dataset.tooltip;
		if (!tooltipText) {
			return;
		}

		// Set ARIA attributes with counter-based unique ID
		const tooltipId = `dsgo-tooltip-${++tooltipCounter}`;
		trigger.setAttribute('aria-describedby', tooltipId);
		trigger.setAttribute('tabindex', '0');
		trigger.setAttribute('role', 'button');

		// Create tooltip element
		// textContent is used (not innerHTML) to prevent XSS from user-supplied tooltip text
		const tooltip = document.createElement('div');
		tooltip.className = 'dsgo-comparison-table__tooltip';
		tooltip.id = tooltipId;
		tooltip.setAttribute('role', 'tooltip');
		tooltip.textContent = tooltipText;
		trigger.parentNode.style.position = 'relative';
		trigger.parentNode.appendChild(tooltip);

		/**
		 * Shows the tooltip
		 */
		const show = () => {
			tooltip.classList.add('is-visible');
		};

		/**
		 * Hides the tooltip
		 */
		const hide = () => {
			tooltip.classList.remove('is-visible');
		};

		// Mouse events
		trigger.addEventListener('mouseenter', show);
		trigger.addEventListener('mouseleave', hide);

		// Keyboard events
		trigger.addEventListener('focus', show);
		trigger.addEventListener('blur', hide);

		// Dismiss on Escape
		trigger.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				hide();
				trigger.blur();
			}
		});
	});
}
