/**
 * Table of Contents Block - Frontend JavaScript
 *
 * Scans page for headings and populates TOC list with smooth scroll and scroll spy.
 */

/* global IntersectionObserver, history, requestAnimationFrame */

import { __ } from '@wordpress/i18n';
import {
	parseHeadingLevels,
	parseDisplayMode,
	parseScrollOffset,
	getResponsiveScrollOffset,
} from './utils/validation';
import { generatePrettyId } from './utils/id-generator';
import { buildHierarchy } from './utils/hierarchy';

// Prevent browser's default hash scroll if hash is present
// This ensures our custom smooth scroll and offset work correctly
if (window.location.hash) {
	// Store the hash for later use
	window.dsgoInitialHash = window.location.hash;
	// Prevent default scroll restoration
	if ('scrollRestoration' in history) {
		history.scrollRestoration = 'manual';
	}
	// Reset scroll position immediately (before layout)
	window.scrollTo(0, 0);
}

document.addEventListener('DOMContentLoaded', function () {
	initTableOfContents();
});
document.addEventListener('dsgo-content-loaded', initTableOfContents);

class DSGTableOfContents {
	constructor(element) {
		this.element = element;
		this.uniqueId = element.dataset.uniqueId || '';

		// Validate and parse heading levels
		this.headingLevels = parseHeadingLevels(element.dataset.headingLevels);

		// Validate display mode
		this.displayMode = parseDisplayMode(element.dataset.displayMode);

		// Validate scroll smooth boolean
		this.scrollSmooth = element.dataset.scrollSmooth === 'true';

		// Responsive scroll offset: configurable desktop (default 0px), 60px mobile
		const baseOffset = parseScrollOffset(element.dataset.scrollOffset);
		this.scrollOffset = getResponsiveScrollOffset(baseOffset);

		this.listElement = element.querySelector(
			'.dsgo-table-of-contents__list'
		);
		this.observedHeadings = [];

		// Flag to prevent URL updates during initial hash navigation
		this.isInitialHashNavigation = false;

		// Flag to prevent scroll spy updates during manual link clicks
		this.isManualNavigation = false;

		// Prevent duplicate initialization
		if (element.hasAttribute('data-dsgo-initialized')) {
			return;
		}
		element.setAttribute('data-dsgo-initialized', 'true');

		this.init();
	}

	init() {
		try {
			// Scan page for headings
			const headings = this.scanHeadings();

			if (headings.length === 0) {
				this.showEmptyMessage();
				return;
			}

			// Populate TOC list
			this.populateTOC(headings);

			// Setup link click handlers
			this.setupLinkHandlers();

			// Setup scroll spy
			if (this.scrollSmooth) {
				this.setupScrollSpy(headings);
			}

			// Handle URL hash on page load
			this.handleInitialHash();
		} catch (error) {
			// Silently fail - show empty message to user
			this.showEmptyMessage();
		}
	}

	scanHeadings() {
		const headings = [];
		const selector = this.headingLevels.join(',');
		const usedIds = new Set();

		// Define content area selectors (prioritized)
		const contentSelectors = [
			'.entry-content', // WordPress default post content
			'article .content', // Article content
			'main article', // Main article element
			'.post-content', // Alternative post content class
			'main', // Semantic main element
			'.site-content', // Site content area
		];

		// Find the main content container
		let contentArea = null;
		for (const contentSelector of contentSelectors) {
			contentArea = document.querySelector(contentSelector);
			if (contentArea) {
				break;
			}
		}

		// Fallback to document if no content area found
		const searchArea = contentArea || document;

		// Search for headings within the content area
		searchArea.querySelectorAll(selector).forEach((heading) => {
			// Skip if heading is inside a TOC block
			if (heading.closest('.dsgo-table-of-contents')) {
				return;
			}

			// Skip headings in excluded areas (comments, sidebars, footers, headers)
			const excludedAreas = [
				'.site-header',
				'header',
				'.site-footer',
				'footer',
				'aside',
				'.sidebar',
				'.widget',
				'.comments-area',
				'#comments',
				'.comment-respond',
				'.navigation',
				'.nav',
			];

			for (const excludedSelector of excludedAreas) {
				if (heading.closest(excludedSelector)) {
					return;
				}
			}

			const text = heading.textContent?.trim();
			if (!text) {
				return;
			}

			// Generate pretty ID from heading text if missing
			if (!heading.id) {
				heading.id = generatePrettyId(text, usedIds);
			} else {
				// Track existing IDs to avoid duplicates
				usedIds.add(heading.id);
			}

			headings.push({
				level: parseInt(heading.tagName.replace('H', '')),
				text,
				id: heading.id,
				element: heading,
			});
		});

		return headings;
	}

	showEmptyMessage() {
		if (this.listElement) {
			const li = document.createElement('li');
			li.className = 'dsgo-table-of-contents__empty';
			li.textContent = __('No headings found.', 'designsetgo');
			this.listElement.appendChild(li);
		}
	}

	populateTOC(headings) {
		if (!this.listElement) {
			return;
		}

		// Clear existing content
		this.listElement.innerHTML = '';

		if (this.displayMode === 'flat') {
			this.populateFlatTOC(headings);
		} else {
			this.populateHierarchicalTOC(headings);
		}
	}

	populateFlatTOC(headings) {
		headings.forEach((heading) => {
			const li = this.createListItem(heading);
			this.listElement.appendChild(li);
		});
	}

	populateHierarchicalTOC(headings) {
		if (headings.length === 0) {
			return;
		}

		const minLevel = Math.min(...headings.map((h) => h.level));
		const items = buildHierarchy(
			headings,
			minLevel,
			this.createListItem.bind(this),
			this.listElement
		);
		items.forEach((item) => this.listElement.appendChild(item));
	}

	createListItem(heading) {
		const li = document.createElement('li');
		li.className = `dsgo-table-of-contents__item dsgo-table-of-contents__item--level-${heading.level}`;

		const a = document.createElement('a');
		a.href = `#${heading.id}`;
		a.className = 'dsgo-table-of-contents__link';
		a.textContent = heading.text;
		a.setAttribute('aria-label', `Jump to section: ${heading.text}`);

		li.appendChild(a);
		return li;
	}

	setupLinkHandlers() {
		try {
			const links = this.element.querySelectorAll(
				'.dsgo-table-of-contents__link'
			);

			links.forEach((link) => {
				link.addEventListener('click', (e) => {
					e.preventDefault();

					try {
						const targetId = link.getAttribute('href').substring(1);
						const target = document.getElementById(targetId);

						if (target) {
							this.scrollToTarget(target);
							this.updateActiveLink(link);

							// Update URL hash
							if (window.history.replaceState) {
								window.history.replaceState(
									null,
									null,
									`#${targetId}`
								);
							}

							// Focus target for accessibility
							target.setAttribute('tabindex', '-1');
							target.focus();
						}
					} catch (error) {
						// Silently fail on scroll errors
					}
				});
			});
		} catch (error) {
			// Silently fail on link handler setup errors
		}
	}

	scrollToTarget(target) {
		const rect = target.getBoundingClientRect();
		const targetPosition = window.scrollY + rect.top - this.scrollOffset;

		if (this.scrollSmooth) {
			this.smoothScrollTo(targetPosition);
		} else {
			window.scrollTo(0, targetPosition);
		}
	}

	smoothScrollTo(targetPosition) {
		// Respect user's motion preferences
		const prefersReducedMotion = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;

		if (prefersReducedMotion) {
			window.scrollTo(0, targetPosition);
			return;
		}

		const startPosition = window.scrollY;
		const distance = targetPosition - startPosition;
		const duration = 800; // milliseconds
		let startTime = null;

		// Easing function (ease-in-out cubic)
		const easeInOutCubic = (t) => {
			return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
		};

		const animation = (currentTime) => {
			if (startTime === null) {
				startTime = currentTime;
			}

			const timeElapsed = currentTime - startTime;
			const progress = Math.min(timeElapsed / duration, 1);
			const ease = easeInOutCubic(progress);

			window.scrollTo(0, startPosition + distance * ease);

			if (progress < 1) {
				requestAnimationFrame(animation);
			}
		};

		requestAnimationFrame(animation);
	}

	setupScrollSpy(headings) {
		// Store heading elements for cleanup
		this.observedHeadings = headings.map((h) => h.element);

		// IntersectionObserver configuration for scroll spy
		// - Top offset: User's scroll offset + 100px buffer for early detection
		//   (ensures we detect heading before it reaches the exact scroll position)
		// - Bottom offset: 66% ensures we highlight the section when it's in the top 1/3 of viewport
		//   (100% - 66% = 34% visible area, centered on upper third for natural reading position)
		const EARLY_DETECTION_BUFFER = 100; // Pixels before scroll offset for smoother transitions
		const VIEWPORT_TRIGGER_THRESHOLD = 66; // Percentage from bottom (triggers in top 34% of viewport)

		const observerOptions = {
			rootMargin: `-${this.scrollOffset + EARLY_DETECTION_BUFFER}px 0px -${VIEWPORT_TRIGGER_THRESHOLD}% 0px`,
			threshold: 0,
		};

		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting && entry.target.id) {
					const link = this.element.querySelector(
						`.dsgo-table-of-contents__link[href="#${entry.target.id}"]`
					);
					if (link) {
						this.updateActiveLink(link);
						// Update URL hash when scrolling through sections
						// (but not during initial hash navigation to prevent override)
						if (
							!this.isInitialHashNavigation &&
							!this.isManualNavigation &&
							window.history.replaceState
						) {
							window.history.replaceState(
								null,
								null,
								`#${entry.target.id}`
							);
						}
					}
				}
			});
		}, observerOptions);

		// Observe all heading elements
		this.observedHeadings.forEach((heading) => {
			observer.observe(heading);
		});

		// Store observer for cleanup
		this.observer = observer;
	}

	updateActiveLink(activeLink) {
		// Remove active class from all links
		this.element
			.querySelectorAll('.dsgo-table-of-contents__link')
			.forEach((link) => {
				link.classList.remove('dsgo-table-of-contents__link--active');
				const li = link.closest('li');
				if (li) {
					li.classList.remove('dsgo-table-of-contents__item--active');
				}
			});

		// Add active class to current link
		if (activeLink) {
			activeLink.classList.add('dsgo-table-of-contents__link--active');
			const li = activeLink.closest('li');
			if (li) {
				li.classList.add('dsgo-table-of-contents__item--active');
			}
		}
	}

	handleInitialHash() {
		// Check if there's a hash in the URL
		const hash = window.location.hash;
		if (!hash) {
			return;
		}

		try {
			// Get the target element (remove the # from hash)
			const targetId = hash.substring(1);
			const target = document.getElementById(targetId);

			if (!target) {
				return;
			}

			// Set flag to prevent scroll spy from updating URL during initial navigation
			this.isInitialHashNavigation = true;

			// Delay to ensure page is fully loaded and rendered
			// Longer delay for direct navigation to ensure all content is ready
			setTimeout(() => {
				// Scroll to the target
				this.scrollToTarget(target);

				// Update active link in TOC
				const link = this.element.querySelector(
					`.dsgo-table-of-contents__link[href="${hash}"]`
				);
				if (link) {
					// Skip updates during manual navigation or initial hash navigation
					if (!this.isManualNavigation) {
						this.updateActiveLink(link);
					}
				}

				// Re-enable URL updates after scroll animation completes
				// (800ms animation + 200ms buffer)
				setTimeout(() => {
					this.isInitialHashNavigation = false;
				}, 1000);
			}, 300);
		} catch (error) {
			// Ensure flag is reset even if there's an error
			this.isInitialHashNavigation = false;
		}
	}

	destroy() {
		// Cleanup observer
		if (this.observer) {
			this.observedHeadings.forEach((heading) => {
				this.observer.unobserve(heading);
			});
			this.observer.disconnect();
		}
	}
}

function initTableOfContents() {
	const blocks = document.querySelectorAll('.dsgo-table-of-contents');
	blocks.forEach((block) => {
		new DSGTableOfContents(block);
	});
}

// Re-initialize on dynamic content changes (optional, for compatibility with dynamic loaders)
if (typeof window.addEventListener !== 'undefined') {
	window.addEventListener('dsgo-reinit-toc', initTableOfContents);
}
