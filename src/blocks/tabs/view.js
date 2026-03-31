/**
 * Tabs Block - Frontend JavaScript
 *
 * Handles tab switching, keyboard navigation, deep linking, and mobile responsive behavior
 */

(function () {
	'use strict';

	class DSGTabs {
		constructor(element) {
			this.element = element;
			this.nav = element.querySelector('.dsgo-tabs__nav');
			this.panels = element.querySelectorAll('.dsgo-tab');
			this.activeTab = parseInt(element.dataset.activeTab) || 0;
			this.mobileBreakpoint =
				parseInt(element.dataset.mobileBreakpoint) || 768;
			this.mobileMode = element.dataset.mobileMode || 'accordion';
			this.enableDeepLinking = element.dataset.deepLinking === 'true';

			this.init();
		}

		/**
		 * Create an icon element with lazy loading support securely (no innerHTML)
		 *
		 * @param {string} iconSlug - Sanitized icon slug
		 * @return {HTMLElement} Icon span element
		 */
		createIcon(iconSlug) {
			const iconWrapper = document.createElement('span');
			iconWrapper.className = 'dsgo-tabs__tab-icon dsgo-lazy-icon';

			// Icon slug is already sanitized in save.js, but validate again
			// Only allow: lowercase letters, numbers, hyphens
			const safeIcon = iconSlug.replace(/[^a-z0-9\-]/g, '');
			iconWrapper.setAttribute('data-icon-name', safeIcon);

			return iconWrapper;
		}

		init() {
			// Build tab navigation from panels
			this.buildNavigation();

			// Handle mobile responsiveness FIRST (before setting active tab)
			// This ensures accordion mode is set up before panels are shown/hidden
			this.handleResize();

			// Debounced resize handler for better performance
			let resizeTimeout;
			window.addEventListener('resize', () => {
				clearTimeout(resizeTimeout);
				resizeTimeout = setTimeout(() => this.handleResize(), 150);
			});

			// Determine initial tab: prioritize deep linking, then default to first tab
			let initialTab = 0;

			// Check for deep link hash first
			if (this.enableDeepLinking) {
				const hash = window.location.hash.substring(1);
				if (hash) {
					const panel = this.element.querySelector(`#${hash}`);
					if (panel) {
						const index = Array.from(this.panels).indexOf(panel);
						if (index !== -1) {
							initialTab = index;
						}
					}
				}
			}

			// Set initial active tab (after mobile mode is determined)
			this.setActiveTab(initialTab, false);

			// Set up deep linking listeners (after initial tab is set)
			if (this.enableDeepLinking) {
				this.setupDeepLinkingListeners();
			}
		}

		buildNavigation() {
			if (!this.nav || this.panels.length === 0) {
				return;
			}

			// Clear existing navigation (modern approach without innerHTML)
			this.nav.replaceChildren();

			// Add skip link for keyboard accessibility
			const skipLink = document.createElement('a');
			skipLink.href = `#${this.panels[this.activeTab].id}`;
			skipLink.className = 'dsgo-tabs__skip-link';
			skipLink.textContent = 'Skip to tab content';
			skipLink.addEventListener('click', (e) => {
				e.preventDefault();
				this.panels[this.activeTab].focus();
			});
			this.nav.appendChild(skipLink);

			// Build tab buttons from panels
			this.panels.forEach((panel, index) => {
				const tabId = panel.id.replace('panel-', 'tab-');
				const title =
					panel.getAttribute('aria-label') ||
					this.getTabTitle(panel) ||
					`Tab ${index + 1}`;
				const icon = panel.dataset.icon;
				const iconPosition = panel.dataset.iconPosition || 'left';

				const button = document.createElement('button');
				button.className = 'dsgo-tabs__tab';
				button.id = tabId;
				button.setAttribute('type', 'button');
				button.setAttribute('role', 'tab');
				button.setAttribute(
					'aria-selected',
					index === this.activeTab ? 'true' : 'false'
				);
				button.setAttribute('aria-controls', panel.id);
				button.setAttribute(
					'tabindex',
					index === this.activeTab ? '0' : '-1'
				);
				button.dataset.tabIndex = index;

				// Add icon if present (✅ SECURITY: Using createElement, not innerHTML)
				if (icon) {
					button.classList.add(
						'has-icon',
						`has-icon-${iconPosition}`
					);

					if (iconPosition === 'top') {
						const iconTopWrapper = document.createElement('span');
						iconTopWrapper.className = 'dsgo-tabs__tab-icon-top';
						iconTopWrapper.appendChild(this.createIcon(icon));
						button.appendChild(iconTopWrapper);
					} else if (iconPosition === 'left') {
						button.appendChild(this.createIcon(icon));
					}
				}

				// Add title
				const titleSpan = document.createElement('span');
				titleSpan.className = 'dsgo-tabs__tab-title';
				titleSpan.textContent = title;
				button.appendChild(titleSpan);

				// Add right icon if needed (✅ SECURITY: Using createElement, not innerHTML)
				if (icon && iconPosition === 'right') {
					button.appendChild(this.createIcon(icon));
				}

				// ✅ PERFORMANCE: No individual listeners - use event delegation below
				this.nav.appendChild(button);
			});

			// ✅ PERFORMANCE: Event delegation - single listener for all tabs
			// Reduces event listeners from 2*N to 2 total (90% reduction for 10 tabs)
			this.nav.addEventListener('click', (e) => {
				const button = e.target.closest('.dsgo-tabs__tab');
				if (!button) {
					return;
				}

				const index = parseInt(button.dataset.tabIndex);
				if (!isNaN(index)) {
					this.setActiveTab(index);
				}
			});

			this.nav.addEventListener('keydown', (e) => {
				const button = e.target.closest('.dsgo-tabs__tab');
				if (!button) {
					return;
				}

				const index = parseInt(button.dataset.tabIndex);
				if (!isNaN(index)) {
					this.handleKeyboard(e, index);
				}
			});

			// Inject icons after navigation is built
			if (typeof window.dsgoInjectIcons === 'function') {
				window.dsgoInjectIcons(this.nav);
			}
		}

		getTabTitle(panel) {
			// Try to get title from data attribute or panel content
			const contentDiv = panel.querySelector('.dsgo-tab__content');
			if (!contentDiv) {
				return null;
			}

			// Look for heading in first few elements
			const heading = contentDiv.querySelector('h1, h2, h3, h4, h5, h6');
			return heading ? heading.textContent : null;
		}

		setActiveTab(index, updateURL = true) {
			if (index < 0 || index >= this.panels.length) {
				return;
			}

			this.activeTab = index;

			// Check if we're in accordion mode
			const isAccordionMode = this.element.classList.contains(
				'dsgo-tabs--accordion'
			);

			// Update tabs
			const tabs = this.nav.querySelectorAll('.dsgo-tabs__tab');
			tabs.forEach((tab, i) => {
				const isActive = i === index;
				tab.classList.toggle('is-active', isActive);
				tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
				tab.setAttribute('tabindex', isActive ? '0' : '-1');
			});

			// Update panels
			this.panels.forEach((panel, i) => {
				const isActive = i === index;
				panel.classList.toggle('is-active', isActive);

				// In accordion mode, keep all panels visible (CSS handles content visibility)
				// In tabs mode, hide inactive panels
				if (!isAccordionMode) {
					panel.hidden = !isActive;
				}

				// Update accordion header aria-expanded if present
				const accordionHeader = panel.querySelector(
					'.dsgo-tab__accordion-header'
				);
				if (accordionHeader) {
					accordionHeader.setAttribute(
						'aria-expanded',
						isActive ? 'true' : 'false'
					);
				}
			});

			// Update URL hash if deep linking enabled
			if (this.enableDeepLinking && updateURL) {
				const panel = this.panels[index];
				if (panel.id) {
					// eslint-disable-next-line no-undef
					history.replaceState(null, null, `#${panel.id}`);
				}
			}

			// Trigger custom event
			this.element.dispatchEvent(
				new CustomEvent('dsgo-tab-change', {
					detail: { index, panel: this.panels[index] },
				})
			);
		}

		handleKeyboard(e, currentIndex) {
			let newIndex = currentIndex;
			const orientation = this.element.classList.contains(
				'dsgo-tabs--vertical'
			)
				? 'vertical'
				: 'horizontal';

			if (orientation === 'horizontal') {
				if (e.key === 'ArrowLeft') {
					newIndex =
						currentIndex > 0
							? currentIndex - 1
							: this.panels.length - 1;
					e.preventDefault();
				} else if (e.key === 'ArrowRight') {
					newIndex =
						currentIndex < this.panels.length - 1
							? currentIndex + 1
							: 0;
					e.preventDefault();
				}
			} else if (e.key === 'ArrowUp') {
				newIndex =
					currentIndex > 0
						? currentIndex - 1
						: this.panels.length - 1;
				e.preventDefault();
			} else if (e.key === 'ArrowDown') {
				newIndex =
					currentIndex < this.panels.length - 1
						? currentIndex + 1
						: 0;
				e.preventDefault();
			}

			if (e.key === 'Home') {
				newIndex = 0;
				e.preventDefault();
			} else if (e.key === 'End') {
				newIndex = this.panels.length - 1;
				e.preventDefault();
			}

			if (newIndex !== currentIndex) {
				this.setActiveTab(newIndex);
				this.nav.querySelectorAll('.dsgo-tabs__tab')[newIndex].focus();
			}
		}

		setupDeepLinkingListeners() {
			// Listen for hash changes (initial hash is handled in init())
			window.addEventListener('hashchange', () => {
				const newHash = window.location.hash.substring(1);
				if (newHash) {
					const panel = this.element.querySelector(`#${newHash}`);
					if (panel) {
						const index = Array.from(this.panels).indexOf(panel);
						if (index !== -1) {
							this.setActiveTab(index, false);
						}
					}
				}
			});
		}

		handleResize() {
			const isMobile = window.innerWidth < this.mobileBreakpoint;

			if (isMobile) {
				this.element.classList.add('is-mobile');

				if (this.mobileMode === 'accordion') {
					this.convertToAccordion();
				} else if (this.mobileMode === 'dropdown') {
					this.convertToDropdown();
				}
			} else {
				this.element.classList.remove('is-mobile');
				this.restoreTabsMode();
			}
		}

		convertToAccordion() {
			this.element.classList.add('dsgo-tabs--accordion');
			this.element.classList.remove('dsgo-tabs--dropdown');

			// Hide tab navigation
			if (this.nav) {
				this.nav.style.display = 'none';
			}

			// Show all panels in accordion mode
			this.panels.forEach((panel) => {
				panel.hidden = false;
			});

			// Add accordion headers to each panel
			this.panels.forEach((panel, index) => {
				let header = panel.querySelector('.dsgo-tab__accordion-header');

				if (!header) {
					header = document.createElement('button');
					header.className = 'dsgo-tab__accordion-header';
					header.setAttribute('type', 'button');
					header.setAttribute(
						'aria-expanded',
						index === this.activeTab ? 'true' : 'false'
					);

					// Get title from aria-label attribute (set in save.js)
					const title =
						panel.getAttribute('aria-label') ||
						this.getTabTitle(panel) ||
						`Tab ${index + 1}`;
					header.textContent = title;

					header.addEventListener('click', () => {
						this.setActiveTab(index);
					});

					panel.insertBefore(header, panel.firstChild);
				}

				header.setAttribute(
					'aria-expanded',
					index === this.activeTab ? 'true' : 'false'
				);
			});
		}

		convertToDropdown() {
			this.element.classList.add('dsgo-tabs--dropdown');
			this.element.classList.remove('dsgo-tabs--accordion');

			// Check if dropdown already exists
			let dropdown = this.element.querySelector('.dsgo-tabs__dropdown');

			if (!dropdown) {
				// Create dropdown select element
				dropdown = document.createElement('select');
				dropdown.className = 'dsgo-tabs__dropdown';
				dropdown.setAttribute('aria-label', 'Select tab');

				// Add options from panels
				this.panels.forEach((panel, index) => {
					const option = document.createElement('option');
					option.value = index;
					option.textContent =
						panel.getAttribute('aria-label') ||
						this.getTabTitle(panel) ||
						`Tab ${index + 1}`;
					option.selected = index === this.activeTab;
					dropdown.appendChild(option);
				});

				// Add change event listener
				dropdown.addEventListener('change', (e) => {
					this.setActiveTab(parseInt(e.target.value));
				});

				// Insert dropdown before panels
				this.element.insertBefore(
					dropdown,
					this.element.querySelector('.dsgo-tabs__panels')
				);
			}

			// Hide tab navigation
			if (this.nav) {
				this.nav.style.display = 'none';
			}

			// Update dropdown selected value
			dropdown.value = this.activeTab;
		}

		restoreTabsMode() {
			this.element.classList.remove(
				'dsgo-tabs--accordion',
				'dsgo-tabs--dropdown'
			);

			// Show tab navigation
			if (this.nav) {
				this.nav.style.display = '';
			}

			// Remove dropdown if exists
			const dropdown = this.element.querySelector('.dsgo-tabs__dropdown');
			if (dropdown) {
				dropdown.remove();
			}

			// Remove accordion headers
			this.panels.forEach((panel) => {
				const header = panel.querySelector(
					'.dsgo-tab__accordion-header'
				);
				if (header) {
					header.remove();
				}
			});

			// Restore panel visibility for tab mode
			this.panels.forEach((panel, index) => {
				panel.hidden = index !== this.activeTab;
			});
		}
	}

	// Initialize all tabs on page load
	function initTabs() {
		const tabsElements = document.querySelectorAll('.dsgo-tabs');
		tabsElements.forEach((element) => {
			new DSGTabs(element);
		});
	}

	// Run on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTabs);
	} else {
		initTabs();
	}

	// Re-initialize after soft navigation (bfcache, AJAX)
	document.addEventListener('dsgo-content-loaded', initTabs);

	// Expose to window for external access
	window.DSGTabs = DSGTabs;
})();
