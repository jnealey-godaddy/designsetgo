/**
 * Breadcrumbs Block - Frontend JavaScript
 *
 * Injects Schema.org BreadcrumbList JSON-LD for improved SEO.
 */

document.addEventListener('DOMContentLoaded', function () {
	initBreadcrumbs();
});
document.addEventListener('dsgo-content-loaded', initBreadcrumbs);

/**
 * Initialize all breadcrumb blocks on the page
 */
function initBreadcrumbs() {
	const breadcrumbBlocks = document.querySelectorAll(
		'.dsgo-breadcrumbs[data-dsgo-breadcrumbs]'
	);

	if (!breadcrumbBlocks || breadcrumbBlocks.length === 0) {
		return;
	}

	breadcrumbBlocks.forEach((block) => {
		try {
			// Prevent duplicate initialization
			if (block.hasAttribute('data-dsgo-schema-injected')) {
				return;
			}
			block.setAttribute('data-dsgo-schema-injected', 'true');

			// Parse breadcrumb data
			const breadcrumbData = JSON.parse(
				block.getAttribute('data-dsgo-breadcrumbs')
			);

			if (!breadcrumbData || breadcrumbData.length === 0) {
				return;
			}

			// Generate and inject Schema.org JSON-LD
			injectSchemaMarkup(breadcrumbData);
		} catch (error) {
			// Silently fail - breadcrumbs still work without Schema.org
			// eslint-disable-next-line no-console
			console.error('DesignSetGo Breadcrumbs: Schema.org error', error);
		}
	});
}

/**
 * Generate and inject Schema.org BreadcrumbList JSON-LD
 *
 * @param {Array} breadcrumbs - Array of breadcrumb items
 */
function injectSchemaMarkup(breadcrumbs) {
	// Build Schema.org BreadcrumbList structure
	const schemaData = {
		'@context': 'https://schema.org',
		'@type': 'BreadcrumbList',
		itemListElement: breadcrumbs.map((item, index) => {
			const listItem = {
				'@type': 'ListItem',
				position: item.position || index + 1,
				name: item.title,
			};

			// Add item URL to schema (all breadcrumb items have URLs).
			if (item.url) {
				listItem.item = item.url;
			}

			return listItem;
		}),
	};

	// Check if Schema.org markup already exists for breadcrumbs
	const existingSchema = document.querySelector(
		'script[type="application/ld+json"][data-dsgo-breadcrumbs-schema]'
	);

	if (existingSchema) {
		// Update existing schema
		existingSchema.textContent = JSON.stringify(schemaData);
	} else {
		// Create new schema script tag
		const scriptTag = document.createElement('script');
		scriptTag.type = 'application/ld+json';
		scriptTag.setAttribute('data-dsgo-breadcrumbs-schema', 'true');
		scriptTag.textContent = JSON.stringify(schemaData);

		// Inject before closing </body> tag (or append to head if body not found)
		const targetElement = document.body || document.head;
		if (targetElement) {
			targetElement.appendChild(scriptTag);
		}
	}
}

/**
 * Export for potential reuse (if module system is available)
 */
if (typeof module !== 'undefined' && module.exports) {
	module.exports = { initBreadcrumbs, injectSchemaMarkup };
}
