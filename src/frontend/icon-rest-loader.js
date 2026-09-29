/** Fetch dynamic icons in serial batches matching IconInjector::MAX_REST_NAMES. */
const MAX_REST_NAMES = 100;
const RETRY_DELAY = 30000;

/**
 * Build a loader that deduplicates queued, in-flight and cooling-down names.
 *
 * @param {Function} onLoaded Inject newly available SVGs into the current DOM.
 * @return {Function} Queue missing icon names for loading.
 */
export function createIconRestLoader(onLoaded) {
	const requested = new Set();
	const queued = new Set();
	let loading = false;

	function releaseAfterDelay(names) {
		if (names.length) {
			setTimeout(
				() => names.forEach((name) => requested.delete(name)),
				RETRY_DELAY
			);
		}
	}

	async function loadBatch() {
		if (loading || queued.size === 0) {
			return;
		}
		loading = true;
		const names = Array.from(queued).sort().slice(0, MAX_REST_NAMES);
		names.forEach((name) => queued.delete(name));
		try {
			const rest = window.dsgoIconsRest;
			const url = new URL(rest.url, window.location.href);
			url.searchParams.set('names', names.join(','));
			url.searchParams.set('ver', rest.version || '');
			const response = await fetch(url.toString(), {
				credentials: 'omit',
			});
			if (!response.ok) {
				throw new Error(`HTTP ${response.status}`);
			}
			const icons = await response.json();
			if (!icons || typeof icons !== 'object' || Array.isArray(icons)) {
				throw new Error('Invalid icon response');
			}
			const unanswered = names.filter((name) => {
				if (
					Object.prototype.hasOwnProperty.call(icons, name) &&
					typeof icons[name] === 'string'
				) {
					// Only an explicit empty string means the name is unknown.
					window.dsgoIcons[name] = icons[name];
					return false;
				}
				return true;
			});
			releaseAfterDelay(unanswered);
			onLoaded();
		} catch {
			// Retry on a later DOM scan after cooldown, never on every mutation.
			releaseAfterDelay(names);
		} finally {
			loading = false;
			loadBatch();
		}
	}

	return (names) => {
		const rest = window.dsgoIconsRest;
		if (!rest || !rest.url) {
			return;
		}
		names.forEach((name) => {
			if (!requested.has(name)) {
				requested.add(name);
				queued.add(name);
			}
		});
		loadBatch();
	};
}
