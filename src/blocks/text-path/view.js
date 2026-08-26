/**
 * Text Path Block - Frontend
 *
 * @package
 */

import { initTextPathMotion } from './motion';

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => initTextPathMotion());
} else {
	initTextPathMotion();
}

document.addEventListener('dsgo-content-loaded', () => initTextPathMotion());
