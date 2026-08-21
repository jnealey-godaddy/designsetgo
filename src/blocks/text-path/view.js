/* global requestAnimationFrame */

const MOTION_SELECTOR = '[data-dsgo-text-path-motion="true"]';
const MIN_DURATION = 2;
const MAX_DURATION = 120;
const motionStates = new WeakMap();
let visibilityListenerAttached = false;

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

	return { duration, direction, initialOffset, textPath };
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
	const offset = (((state.initialOffset + movement) % 100) + 100) % 100;
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
