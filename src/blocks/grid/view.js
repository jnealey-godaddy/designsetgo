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

			// "Align Rows" — publish the per-card subgrid row count so the
			// stylesheet's subgrid rules can activate (see style.scss).
			this.matchRows = element.classList.contains(
				'dsgo-grid--match-rows'
			);

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

			// Effective columns at the current breakpoint (desktop reports
			// null, so fall back to the desktop column class), then narrowed
			// to what the grid is actually rendering — a column min width can
			// drop a column instead of overflowing.
			//
			// Only Align Rows consumes this, and the measurement forces a
			// synchronous layout flush, so skip it entirely for grids without
			// the feature: `applyRowMatching()` treats a falsy count the same
			// way it treats a single column, and returns before using it.
			const configuredColumns =
				config.columns === null
					? this.getDesktopColumns()
					: config.columns;
			const effectiveColumns = this.matchRows
				? this.getRenderedColumns(configuredColumns)
				: null;

			// Desktop: Remove all constraints
			if (config.breakpoint === 'desktop') {
				this.removeConstraints();
			} else {
				// Mobile/Tablet: Constrain spans
				this.applyConstraints(config.columns);
			}

			this.applyRowMatching(effectiveColumns);
		}

		/**
		 * Read the configured desktop column count from the `dsgo-grid-cols-{n}`
		 * class (the desktop breakpoint sets no responsive override).
		 *
		 * @return {number} Column count (defaults to 1 if none found).
		 */
		getDesktopColumns() {
			for (let i = 12; i >= 1; i--) {
				if (this.element.classList.contains(`dsgo-grid-cols-${i}`)) {
					return i;
				}
			}
			return 1;
		}

		/**
		 * Count the columns the grid is ACTUALLY rendering, by reading the
		 * resolved track list off the computed style.
		 *
		 * This can be fewer than the configured desktop count: the column min
		 * width builds an `auto-fill` track list that drops a column rather
		 * than overflowing the container (see utils/grid-columns.js). Row
		 * matching must key off the rendered count, not the configured one —
		 * a card spanning `--dsgo-row-count` row tracks in a grid that has
		 * wrapped to a single column absorbs the row gaps between those tracks
		 * and grows taller for no benefit, since there is nothing beside it to
		 * align to.
		 *
		 * @param {number} fallback Count to use when the track list is
		 *                          unreadable (detached or `display: none`).
		 * @return {number} Rendered column count.
		 */
		getRenderedColumns(fallback) {
			const tracks = window.getComputedStyle(
				this.inner
			).gridTemplateColumns;

			// 'none' (no grid), '' (detached / jsdom without layout), or any
			// unresolved value: fall back to the configured count.
			if (!tracks || tracks === 'none') {
				return fallback;
			}

			// Resolved track lists are space-separated used values
			// ('364px 364px 364px'). `minmax()`/`repeat()` only survive here if
			// the browser could not resolve them, which the guard above covers.
			return tracks.split(/\s+/).filter(Boolean).length || fallback;
		}

		/**
		 * Count the content rows a card contributes to the subgrid. Mirrors the
		 * CSS allowlist: Section (`.dsgo-stack__inner`) and Flex
		 * (`.dsgo-flex__inner`) cards count their wrapper's children, Group
		 * (`.wp-block-group`) counts its own children, and anything else (e.g.
		 * the Card block, which the CSS also leaves alone) returns 0 so it
		 * neither aligns nor inflates the shared row count.
		 *
		 * The "supported cards" set is defined in four places — see the note on
		 * SUPPORTED_CARD_BLOCKS in utils/use-grid-row-match.js. This one and the
		 * CSS match by class (rename-proof); the editor list matches by name.
		 *
		 * @param {HTMLElement} child A direct child (card) of the inner grid.
		 * @return {number} Number of element rows in the card (0 if unsupported).
		 */
		countCardRows(child) {
			const rowHost =
				child.querySelector(
					':scope > .dsgo-stack__inner, :scope > .dsgo-flex__inner'
				) ||
				(child.classList.contains('wp-block-group') ? child : null);
			if (!rowHost) {
				return 0;
			}
			// `.children` is an HTMLCollection — element nodes only (unlike
			// `.childNodes`), so no text/comment filtering is needed.
			return rowHost.children.length;
		}

		/**
		 * Activate (or clear) subgrid row matching. Only meaningful with 2+
		 * columns; on a single column the cards stack and need no alignment.
		 *
		 * @param {number} effectiveColumns Columns at the current breakpoint.
		 */
		applyRowMatching(effectiveColumns) {
			if (!this.matchRows) {
				return;
			}

			const MATCHED = 'dsgo-grid__inner--rows-matched';

			if (!effectiveColumns || effectiveColumns <= 1) {
				this.inner.classList.remove(MATCHED);
				this.inner.style.removeProperty('--dsgo-row-count');
				return;
			}

			const rowCount = Array.from(this.inner.children).reduce(
				(max, child) => Math.max(max, this.countCardRows(child)),
				0
			);

			if (rowCount < 1) {
				this.inner.classList.remove(MATCHED);
				this.inner.style.removeProperty('--dsgo-row-count');
				return;
			}

			this.inner.style.setProperty('--dsgo-row-count', String(rowCount));
			this.inner.classList.add(MATCHED);
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
			// Prevent duplicate initialization (avoids resize listener accumulation)
			if (element.hasAttribute('data-dsgo-initialized')) {
				return;
			}
			element.setAttribute('data-dsgo-initialized', 'true');
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
