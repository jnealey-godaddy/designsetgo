/**
 * Flip Card Frontend JavaScript
 *
 * Handles flip interactions on the frontend based on trigger type.
 *
 * Keyboard and screen-reader access goes through a real <button> injected
 * as the card's first child. It overlays the card (transparent, and
 * pointer-events: none so mouse clicks still reach links inside the faces)
 * and draws the focus ring. The card itself stays a plain container, so the
 * face content is read normally rather than flattened into a button name.
 * The hidden face is `inert`, so it is neither tabbable nor announced.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Apply a flip state: class, inert face, and toggle label.
 *
 * @param {HTMLElement} card    Flip card element.
 * @param {boolean}     flipped Whether the back face should show.
 */
function setFlipped(card, flipped) {
	card.classList.toggle('is-flipped', flipped);

	const front = card.querySelector(
		':scope > .dsgo-flip-card__container > .dsgo-flip-card__front'
	);
	const back = card.querySelector(
		':scope > .dsgo-flip-card__container > .dsgo-flip-card__back'
	);
	const hidden = flipped ? front : back;
	const shown = flipped ? back : front;
	if (hidden) {
		hidden.setAttribute('inert', '');
		hidden.setAttribute('aria-hidden', 'true');
	}
	if (shown) {
		shown.removeAttribute('inert');
		shown.removeAttribute('aria-hidden');
	}

	const toggle = card.querySelector(':scope > .dsgo-flip-card__toggle');
	if (toggle) {
		toggle.setAttribute(
			'aria-label',
			flipped
				? __('Show front of card', 'designsetgo')
				: __('Show back of card', 'designsetgo')
		);
	}
}

function initFlipCards() {
	const flipCards = document.querySelectorAll('.dsgo-flip-card');

	flipCards.forEach((card) => {
		// Prevent duplicate initialization
		if (card.dataset.dsgoInitialized) {
			return;
		}
		card.dataset.dsgoInitialized = 'true';

		const flipTrigger = card.getAttribute('data-flip-trigger');
		if (flipTrigger !== 'click' && flipTrigger !== 'hover') {
			return;
		}

		const toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.className = 'dsgo-flip-card__toggle';
		toggle.addEventListener('click', () => {
			setFlipped(card, !card.classList.contains('is-flipped'));
		});
		card.insertBefore(toggle, card.firstChild);
		setFlipped(card, card.classList.contains('is-flipped'));

		if (flipTrigger === 'click') {
			// Click trigger: toggle flip state
			card.addEventListener('click', function (e) {
				// Don't trigger if clicking on a link or button inside the card
				// (the injected toggle handles its own clicks).
				if (e.target.closest('a, button, input, select, textarea')) {
					return;
				}
				setFlipped(card, !card.classList.contains('is-flipped'));
			});
		} else {
			// Hover trigger: add/remove flip class on hover
			card.addEventListener('mouseenter', function () {
				setFlipped(card, true);
			});

			card.addEventListener('mouseleave', function () {
				// Keep the back face up while keyboard focus is inside it, or
				// making it inert would drop that focus.
				if (card.contains(card.ownerDocument.activeElement)) {
					return;
				}
				setFlipped(card, false);
			});
		}
	});
}

document.addEventListener('DOMContentLoaded', initFlipCards);
document.addEventListener('dsgo-content-loaded', initFlipCards);
