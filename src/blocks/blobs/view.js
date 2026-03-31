/**
 * Blobs Block - Frontend JavaScript
 *
 * Handles accessibility and animation initialization
 *
 * @since 1.0.0
 */

/**
 * Transfer background styles from wrapper to blob
 */
function transferBackgroundStyles() {
	const wrappers = document.querySelectorAll('.dsgo-blobs-wrapper');

	wrappers.forEach((wrapper) => {
		const blob = wrapper.querySelector('.dsgo-blobs');
		if (!blob) {
			return;
		}

		// WordPress sets inline styles on the wrapper
		// We need to read inline styles directly because our CSS has `background: none !important;`
		// which overrides them in getComputedStyle()
		const inlineStyle = wrapper.style;

		// Transfer background image
		if (
			inlineStyle.backgroundImage &&
			inlineStyle.backgroundImage !== 'none'
		) {
			blob.style.setProperty(
				'background-image',
				inlineStyle.backgroundImage
			);
		}

		// Transfer background size
		if (
			inlineStyle.backgroundSize &&
			inlineStyle.backgroundSize !== 'auto'
		) {
			blob.style.setProperty(
				'background-size',
				inlineStyle.backgroundSize
			);
		}

		// Transfer background position
		if (inlineStyle.backgroundPosition) {
			blob.style.setProperty(
				'background-position',
				inlineStyle.backgroundPosition
			);
		}

		// Transfer background repeat
		if (
			inlineStyle.backgroundRepeat &&
			inlineStyle.backgroundRepeat !== 'repeat'
		) {
			blob.style.setProperty(
				'background-repeat',
				inlineStyle.backgroundRepeat
			);
		}

		// Transfer background attachment
		if (
			inlineStyle.backgroundAttachment &&
			inlineStyle.backgroundAttachment !== 'scroll'
		) {
			blob.style.setProperty(
				'background-attachment',
				inlineStyle.backgroundAttachment
			);
		}

		// Transfer WordPress background color classes from wrapper to blob
		// so WordPress's own CSS applies the color to the blob shape directly
		const bgClasses = Array.from(wrapper.classList).filter(
			(c) =>
				c.match(/^has-.*-background-color$/) || c === 'has-background'
		);
		bgClasses.forEach((cls) => {
			wrapper.classList.remove(cls);
			blob.classList.add(cls);
		});

		// Transfer inline background color (custom non-preset colors)
		if (inlineStyle.backgroundColor) {
			blob.style.setProperty(
				'background-color',
				inlineStyle.backgroundColor
			);
		} else if (bgClasses.length === 0) {
			// Apply default color if no user color is set
			const defaultColor =
				window
					.getComputedStyle(document.documentElement)
					.getPropertyValue('--wp--preset--color--accent-2') ||
				'#2563eb';
			blob.style.setProperty('background-color', defaultColor.trim());
		}
	});
}

/**
 * Initialize blob animations with accessibility support
 */
function initBlobs() {
	// First transfer background styles
	transferBackgroundStyles();

	const blobs = document.querySelectorAll('.dsgo-blobs');

	if (blobs.length === 0) {
		return;
	}

	// Check if user prefers reduced motion
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	if (prefersReducedMotion) {
		// Disable all animations by adding a class
		blobs.forEach((blob) => {
			blob.classList.add('dsgo-blobs--no-animation');
		});
		return;
	}

	// Initialize each blob
	blobs.forEach(() => {
		// Future: Add SVG-based animation logic here if implementing Phase 2
		// For now, CSS animations handle everything via data-blob-animation attribute
	});
}

// Initialize on DOMContentLoaded or immediately if DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initBlobs);
} else {
	initBlobs();
}

// Re-initialize after soft navigation (bfcache, AJAX)
document.addEventListener('dsgo-content-loaded', initBlobs);

// Re-initialize if user changes motion preferences
if (window.matchMedia) {
	const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

	// Modern browsers
	if (motionQuery.addEventListener) {
		motionQuery.addEventListener('change', initBlobs);
	}
	// Legacy browsers
	else if (motionQuery.addListener) {
		motionQuery.addListener(initBlobs);
	}
}
