/**
 * Text Path Block - Frontend
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { initTextPathMotion, setTextPathMotionPaused } from './motion';

/**
 * Motion never stops by itself, so give visitors a way to pause it
 * (WCAG 2.2.2 Pause, Stop, Hide).
 *
 * @param {Document|Element} root Root to search for moving blocks.
 */
function addPauseControls(root = document) {
	root.querySelectorAll('[data-dsgo-text-path-motion="true"]').forEach(
		(block) => {
			if (block.querySelector('.dsgo-text-path__pause')) {
				return;
			}

			const pauseLabel = __('Pause text animation', 'designsetgo');
			const playLabel = __('Play text animation', 'designsetgo');
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'dsgo-text-path__pause';
			button.textContent = pauseLabel;
			button.addEventListener('click', () => {
				const paused = button.textContent === pauseLabel;
				if (setTextPathMotionPaused(block, paused)) {
					button.textContent = paused ? playLabel : pauseLabel;
				}
			});
			block.appendChild(button);
		}
	);
}

function init() {
	initTextPathMotion();
	// Reduced motion never starts the loop, so no control is needed.
	if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		addPauseControls();
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}

document.addEventListener('dsgo-content-loaded', init);
