/**
 * Slider — touch and mouse gesture handling.
 *
 * Both gestures resolve to the same three outcomes — advance, go back, or
 * settle where we were — so the slider passes in callbacks and never sees a
 * raw pointer coordinate.
 */

/** Pixels a gesture must travel along the track before it counts. */
const THRESHOLD = 50;

/**
 * Wire swipe navigation on a touch device.
 *
 * The horizontal distance is only acted on when it beats the vertical one:
 * a thumb travelling down the page drifts sideways easily, and comparing
 * `dx` against the threshold alone turns an ordinary page scroll into a
 * slide change.
 *
 * @param {HTMLElement} track           The slider track.
 * @param {Object}      handlers        Callbacks.
 * @param {Function}    handlers.onNext Advance one slide.
 * @param {Function}    handlers.onPrev Go back one slide.
 * @return {Function} Teardown that removes the listeners.
 */
export function initSwipe(track, { onNext, onPrev }) {
	let startX = 0;
	let startY = 0;

	const onTouchStart = (event) => {
		startX = event.touches[0].clientX;
		startY = event.touches[0].clientY || 0;
	};

	const onTouchEnd = (event) => {
		const endX = event.changedTouches[0].clientX;
		const endY = event.changedTouches[0].clientY || 0;
		const dx = startX - endX;
		const dy = startY - endY;

		if (Math.abs(dx) <= THRESHOLD || Math.abs(dx) <= Math.abs(dy)) {
			return;
		}
		if (dx > 0) {
			onNext();
		} else {
			onPrev();
		}
	};

	track.addEventListener('touchstart', onTouchStart, { passive: true });
	track.addEventListener('touchend', onTouchEnd, { passive: true });

	return () => {
		track.removeEventListener('touchstart', onTouchStart);
		track.removeEventListener('touchend', onTouchEnd);
	};
}

/**
 * Wire click-and-drag navigation with a mouse.
 *
 * The drag starts from wherever the track currently sits — `getOffset()` —
 * rather than from zero. Anchoring at zero made the track jump back to the
 * first slide the moment the pointer moved, because the first `mousemove`
 * wrote `translateX(delta)` over the real position.
 *
 * @param {HTMLElement} track              The slider track.
 * @param {Object}      handlers           Callbacks.
 * @param {Function}    handlers.getOffset Current track translation in pixels.
 * @param {Function}    handlers.setOffset Write a track translation in pixels.
 * @param {Function}    handlers.onNext    Advance one slide.
 * @param {Function}    handlers.onPrev    Go back one slide.
 * @param {Function}    handlers.onCancel  Settle back on the current slide.
 * @return {Function} Teardown that removes the listeners.
 */
export function initDrag(
	track,
	{ getOffset, setOffset, onNext, onPrev, onCancel }
) {
	let isDragging = false;
	let startX = 0;
	let startOffset = 0;
	let delta = 0;

	const onMouseDown = (event) => {
		isDragging = true;
		startX = event.clientX;
		startOffset = getOffset();
		delta = 0;
		track.style.cursor = 'grabbing';
	};

	const onMouseMove = (event) => {
		if (!isDragging) {
			return;
		}
		delta = event.clientX - startX;
		setOffset(startOffset + delta);
	};

	const onMouseUp = () => {
		if (!isDragging) {
			return;
		}
		isDragging = false;
		track.style.cursor = 'grab';

		if (Math.abs(delta) <= THRESHOLD) {
			onCancel();
		} else if (delta < 0) {
			onNext();
		} else {
			onPrev();
		}
	};

	// Images and links inside a slide are natively draggable; without this the
	// browser starts its own drag-and-drop and the pointer never reaches
	// mousemove, leaving the track stranded mid-gesture.
	const onDragStart = (event) => {
		if (isDragging) {
			event.preventDefault();
		}
	};

	track.addEventListener('mousedown', onMouseDown);
	track.addEventListener('dragstart', onDragStart);
	document.addEventListener('mousemove', onMouseMove);
	document.addEventListener('mouseup', onMouseUp);
	track.style.cursor = 'grab';

	return () => {
		track.removeEventListener('mousedown', onMouseDown);
		track.removeEventListener('dragstart', onDragStart);
		document.removeEventListener('mousemove', onMouseMove);
		document.removeEventListener('mouseup', onMouseUp);
		track.style.cursor = '';
	};
}
