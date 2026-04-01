/**
 * Flip Card Frontend JavaScript
 *
 * Handles flip interactions on the frontend based on trigger type.
 *
 * @since 1.0.0
 */

function initFlipCards() {
	const flipCards = document.querySelectorAll('.dsgo-flip-card');

	flipCards.forEach((card) => {
		// Prevent duplicate initialization
		if (card.dataset.dsgoInitialized) {
			return;
		}
		card.dataset.dsgoInitialized = 'true';

		const flipTrigger = card.getAttribute('data-flip-trigger');

		if (flipTrigger === 'click') {
			// Click trigger: toggle flip state
			card.addEventListener('click', function (e) {
				// Don't trigger if clicking on a link or button inside the card
				const isInteractive =
					e.target.tagName === 'A' ||
					e.target.tagName === 'BUTTON' ||
					e.target.closest('a') ||
					e.target.closest('button');

				if (!isInteractive) {
					card.classList.toggle('is-flipped');
				}
			});

			// Add keyboard support for accessibility
			card.setAttribute('tabindex', '0');
			card.setAttribute('role', 'button');
			card.setAttribute('aria-label', 'Flip card to reveal content');

			card.addEventListener('keydown', function (e) {
				// Space or Enter key
				if (e.key === ' ' || e.key === 'Enter') {
					e.preventDefault();
					card.classList.toggle('is-flipped');

					// Update aria-label based on state
					const isFlipped = card.classList.contains('is-flipped');
					card.setAttribute(
						'aria-label',
						isFlipped
							? 'Flip card back to front'
							: 'Flip card to reveal content'
					);
				}
			});
		} else if (flipTrigger === 'hover') {
			// Hover trigger: add/remove flip class on hover
			card.addEventListener('mouseenter', function () {
				card.classList.add('is-flipped');
			});

			card.addEventListener('mouseleave', function () {
				card.classList.remove('is-flipped');
			});

			// Keyboard support for hover cards
			card.setAttribute('tabindex', '0');
			card.setAttribute(
				'aria-label',
				'Flip card - hover or focus to reveal content'
			);

			card.addEventListener('focus', function () {
				card.classList.add('is-flipped');
			});

			card.addEventListener('blur', function () {
				card.classList.remove('is-flipped');
			});
		}
	});
}

document.addEventListener('DOMContentLoaded', initFlipCards);
document.addEventListener('dsgo-content-loaded', initFlipCards);
