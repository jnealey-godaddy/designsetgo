/**
 * Grid Block - Frontend JavaScript
 *
 * Handles responsive column span constraints dynamically at runtime.
 * This replaces hundreds of CSS rules with lightweight JavaScript.
 */

(function () {
	'use strict';

	class DSGGrid {
		constructor(element) {
			this.element = element;
			this.inner = element.querySelector('.dsgo-grid__inner');
			if (!this.inner) {
				return;
			}

			this.tabletBreakpoint = 1024;
			this.mobileBreakpoint = 767;

			this.init();
		}

		init() {
			// Apply initial constraints
			this.handleResize();

			// Debounced resize handler
			let resizeTimeout;
			this.handleResize = this.handleResize.bind(this);
			window.addEventListener('resize', () => {
				clearTimeout(resizeTimeout);
				resizeTimeout = setTimeout(() => this.handleResize(), 150);
			});
		}

		getResponsiveColumns() {
			const width = window.innerWidth;

			// Mobile: <= 767px
			if (width <= this.mobileBreakpoint) {
				// Check for mobile column class
				for (let i = 1; i <= 12; i++) {
					if (
						this.element.classList.contains(
							`dsgo-grid-cols-mobile-${i}`
						)
					) {
						return { breakpoint: 'mobile', columns: i };
					}
				}
				return { breakpoint: 'mobile', columns: 1 }; // Default mobile
			}

			// Tablet: 768px - 1024px
			if (width <= this.tabletBreakpoint) {
				// Check for tablet column class
				for (let i = 1; i <= 12; i++) {
					if (
						this.element.classList.contains(
							`dsgo-grid-cols-tablet-${i}`
						)
					) {
						return { breakpoint: 'tablet', columns: i };
					}
				}
				return { breakpoint: 'tablet', columns: 2 }; // Default tablet
			}

			// Desktop: > 1024px - no constraints needed
			return { breakpoint: 'desktop', columns: null };
		}

		handleResize() {
			const config = this.getResponsiveColumns();

			// Desktop: Remove all constraints
			if (config.breakpoint === 'desktop') {
				this.removeConstraints();
				return;
			}

			// Mobile/Tablet: Constrain spans
			this.applyConstraints(config.columns);
		}

		applyConstraints(maxColumns) {
			const children = Array.from(this.inner.children);

			children.forEach((child) => {
				// Get inline grid-column style
				const inlineStyle = child.style.gridColumn;
				if (!inlineStyle) {
					return;
				}

				// Parse span value (e.g., "span 3" or "3")
				const spanMatch = inlineStyle.match(/span\s+(\d+)|^(\d+)$/);
				if (!spanMatch) {
					return;
				}

				const spanValue = parseInt(spanMatch[1] || spanMatch[2]);

				// If span exceeds max columns, constrain it
				if (spanValue > maxColumns) {
					child.style.gridColumn = `span ${maxColumns}`;
				}
			});
		}

		removeConstraints() {
			// Reset to original inline styles (desktop view)
			// Elements keep their original grid-column values
			// No action needed - constraints only apply on tablet/mobile
		}
	}

	// Initialize all grids on page load
	function initGrids() {
		const gridElements = document.querySelectorAll('.dsgo-grid');
		gridElements.forEach((element) => {
			new DSGGrid(element);
		});
	}

	// Run on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initGrids);
	} else {
		initGrids();
	}

	// Re-initialize after soft navigation (bfcache, AJAX)
	document.addEventListener('dsgo-content-loaded', initGrids);

	// Expose for external access
	window.DSGGrid = DSGGrid;
})();
