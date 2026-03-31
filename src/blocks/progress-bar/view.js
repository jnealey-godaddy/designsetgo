/**
 * Progress Bar Block - Frontend JavaScript
 *
 * Handles scroll-triggered animations for progress bars.
 *
 * @since 1.0.0
 */

/**
 * Initialize progress bars with scroll animations
 */
function initProgressBars() {
	const progressBars = document.querySelectorAll(
		'.dsgo-progress-bar--animate'
	);

	if (!progressBars.length) {
		return;
	}

	// Use Intersection Observer for better performance
	const observerOptions = {
		root: null,
		rootMargin: '0px',
		threshold: 0.1, // Trigger when 10% of element is visible
	};

	// eslint-disable-next-line no-undef
	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				const progressBar = entry.target;
				const fill = progressBar.querySelector(
					'.dsgo-progress-bar__fill'
				);

				if (!fill) {
					return;
				}

				// Get target percentage from data attribute
				const targetPercentage =
					progressBar.getAttribute('data-percentage');

				if (targetPercentage) {
					// Animate to target percentage
					setTimeout(() => {
						fill.style.width = `${targetPercentage}%`;
					}, 100); // Small delay for better visual effect
				}

				// Unobserve after animation starts (only animate once)
				observer.unobserve(progressBar);
			}
		});
	}, observerOptions);

	// Observe all progress bars (skip already-initialized ones)
	progressBars.forEach((bar) => {
		if (!bar.hasAttribute('data-dsgo-initialized')) {
			bar.setAttribute('data-dsgo-initialized', 'true');
			observer.observe(bar);
		}
	});
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initProgressBars);
} else {
	initProgressBars();
}

// Re-initialize after dynamic content loads (e.g., AJAX, soft navigation)
document.addEventListener('wp-blocks-post-content-loaded', initProgressBars);
document.addEventListener('dsgo-content-loaded', initProgressBars);
