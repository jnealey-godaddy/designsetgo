/* global requestAnimationFrame */

const MOTION_SELECTOR = '[data-dsgo-text-path-motion="true"]';
const MIN_DURATION = 2;
const MAX_DURATION = 120;
const FALLBACK_OPEN_PATH_MAXIMUM_OFFSET = 80;
const motionStates = new WeakMap();
let visibilityListenerAttached = false;

function isClosedGuidePath(guide) {
	const pathData = guide.getAttribute('d')?.trim() || '';

	return (
		/(?:^|\s)[zZ]\s*$/.test(pathData) ||
		/^M\s+500\s+0\s+A\s+500\s+(?:500|250)\s+0\s+1\s+1\s+499\.9\s+0\s*$/.test(
			pathData
		)
	);
}

function getOpenPathMaximumOffset(textPath) {
	const href = textPath.getAttribute('href');
	const guideId = href?.startsWith('#') ? href.slice(1) : '';
	const guide = guideId ? document.getElementById(guideId) : null;

	if (!guide) {
		return null;
	}

	if (
		typeof guide.getTotalLength !== 'function' ||
		typeof guide.getPointAtLength !== 'function' ||
		typeof textPath.getComputedTextLength !== 'function'
	) {
		return isClosedGuidePath(guide)
			? null
			: FALLBACK_OPEN_PATH_MAXIMUM_OFFSET;
	}

	try {
		const pathLength = guide.getTotalLength();
		const startPoint = guide.getPointAtLength(0);
		const end = guide.getPointAtLength(pathLength);
		const isClosed =
			Math.hypot(startPoint.x - end.x, startPoint.y - end.y) < 1;
		const textLength = textPath.getComputedTextLength();

		if (
			isClosed ||
			!Number.isFinite(pathLength) ||
			pathLength <= 0 ||
			!Number.isFinite(textLength) ||
			textLength < 0
		) {
			return null;
		}

		return Math.max(0, 100 - (textLength / pathLength) * 100);
	} catch {
		return isClosedGuidePath(guide)
			? null
			: FALLBACK_OPEN_PATH_MAXIMUM_OFFSET;
	}
}

function getBouncedOffset(initialOffset, movement, maximumOffset) {
	if (maximumOffset <= 0) {
		return 0;
	}

	const period = maximumOffset * 2;
	const initial = Math.max(0, Math.min(maximumOffset, initialOffset));
	const position = (((initial + movement) % period) + period) % period;

	return position <= maximumOffset ? position : period - position;
}

function getMotionSettings(block) {
	const duration = Number(block.dataset.dsgoTextPathMotionDuration);
	const direction = block.dataset.dsgoTextPathMotionDirection;
	const textPath = block.querySelector(
		'textPath[data-dsgo-text-path-offset]'
	);
	const initialOffset = Number(textPath?.dataset.dsgoTextPathOffset);

	if (
		!textPath ||
		!Number.isFinite(duration) ||
		duration < MIN_DURATION ||
		duration > MAX_DURATION ||
		!['forward', 'reverse'].includes(direction) ||
		!Number.isFinite(initialOffset)
	) {
		return null;
	}

	return {
		duration,
		direction,
		initialOffset,
		maximumOffset: getOpenPathMaximumOffset(textPath),
		textPath,
	};
}

function renderFrame(state, timestamp) {
	if (!state.textPath.isConnected || document.hidden) {
		state.frame = null;
		return;
	}

	if (state.startTime === null) {
		state.startTime = timestamp;
	}

	const elapsed = (timestamp - state.startTime) / 1000;
	const progress = (elapsed / state.duration) * 100;
	const movement = state.direction === 'reverse' ? -progress : progress;
	const offset =
		state.maximumOffset === null
			? (((state.initialOffset + movement) % 100) + 100) % 100
			: getBouncedOffset(
					state.initialOffset,
					movement,
					state.maximumOffset
				);
	state.textPath.setAttribute('startOffset', `${offset}%`);
	state.frame = requestAnimationFrame((nextTimestamp) =>
		renderFrame(state, nextTimestamp)
	);
}

function start(state) {
	if (state.frame !== null || document.hidden) {
		return;
	}

	state.startTime = null;
	state.frame = requestAnimationFrame((timestamp) =>
		renderFrame(state, timestamp)
	);
}

function attachVisibilityListener() {
	if (visibilityListenerAttached) {
		return;
	}

	visibilityListenerAttached = true;
	document.addEventListener('visibilitychange', () => {
		if (document.hidden) {
			return;
		}
		document.querySelectorAll(MOTION_SELECTOR).forEach((block) => {
			const state = motionStates.get(block);
			if (state) {
				start(state);
			}
		});
	});
}

export function initTextPathMotion(root = document) {
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	attachVisibilityListener();
	const blocks = [];
	if (root.matches?.(MOTION_SELECTOR)) {
		blocks.push(root);
	}
	root.querySelectorAll?.(MOTION_SELECTOR).forEach((block) =>
		blocks.push(block)
	);

	blocks.forEach((block) => {
		if (motionStates.has(block)) {
			return;
		}

		const settings = getMotionSettings(block);
		if (!settings) {
			return;
		}

		const state = { ...settings, block, frame: null, startTime: null };
		motionStates.set(block, state);
		start(state);
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => initTextPathMotion());
} else {
	initTextPathMotion();
}

document.addEventListener('dsgo-content-loaded', () => initTextPathMotion());
