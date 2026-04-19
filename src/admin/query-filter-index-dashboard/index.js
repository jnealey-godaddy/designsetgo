/**
 * Dynamic Query — Filter Index Admin Dashboard entry point.
 *
 * @package
 */

import { createRoot, StrictMode } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import Dashboard from './Dashboard';
import './style.scss';

domReady(() => {
	const mount = document.getElementById('dsgo-query-filter-index-dashboard');
	if (!mount) {
		return;
	}
	createRoot(mount).render(
		<StrictMode>
			<Dashboard />
		</StrictMode>
	);
});
