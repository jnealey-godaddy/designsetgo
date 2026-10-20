/**
 * Slider — autoplay controller.
 *
 * Owns the timer and every reason to suspend it. Autoplay only runs while the
 * slider is on screen, the tab is in the foreground, and nobody is reading or
 * interacting with the slider — each of those is a separate suspend reason so
 * releasing one does not resume over the top of another.
 */

/* global IntersectionObserver */

export default class AutoplayController {
	/**
	 * @param {HTMLElement} slider               Slider root.
	 * @param {Object}      options              Controller options.
	 * @param {number}      options.interval     Milliseconds between advances.
	 * @param {boolean}     options.pauseOnHover Suspend while hovered or focused.
	 * @param {Function}    options.advance      Called on each tick.
	 */
	constructor(slider, { interval, pauseOnHover, advance }) {
		this.slider = slider;
		this.interval = interval;
		this.advance = advance;
		this.timer = null;
		this.suspended = new Set(['offscreen']);
		this.teardowns = [];
		this.stopped = false;
		/** Called whenever the user-facing paused state may have changed. */
		this.onStateChange = null;

		this.observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					this.setSuspended('offscreen', !entry.isIntersecting);
				});
			},
			{ threshold: 0.5 }
		);
		this.observer.observe(slider);

		// A background tab still fires setInterval, so without this the slider
		// races through its slides while nobody is looking and the reader comes
		// back to an arbitrary position.
		this.onVisibilityChange = () => {
			this.setSuspended('hidden', document.hidden);
		};
		this.listen(document, 'visibilitychange', this.onVisibilityChange);
		this.setSuspended('hidden', document.hidden);

		if (pauseOnHover) {
			this.listen(slider, 'mouseenter', () =>
				this.setSuspended('hover', true)
			);
			this.listen(slider, 'mouseleave', () =>
				this.setSuspended('hover', false)
			);
			// Keyboard users get the same courtesy: tabbing into a slide is the
			// same "I am reading this" signal as hovering it.
			this.listen(slider, 'focusin', () =>
				this.setSuspended('focus', true)
			);
			this.listen(slider, 'focusout', (event) => {
				if (!slider.contains(event.relatedTarget)) {
					this.setSuspended('focus', false);
				}
			});
		}
	}

	/**
	 * Register a listener and remember how to remove it.
	 *
	 * @param {EventTarget} target  Listener target.
	 * @param {string}      type    Event name.
	 * @param {Function}    handler Listener.
	 */
	listen(target, type, handler) {
		target.addEventListener(type, handler);
		this.teardowns.push(() => target.removeEventListener(type, handler));
	}

	/**
	 * Add or clear one suspend reason and reconcile the timer.
	 *
	 * @param {string}  reason Suspend reason key.
	 * @param {boolean} active Whether that reason currently applies.
	 */
	setSuspended(reason, active) {
		if (active) {
			this.suspended.add(reason);
		} else {
			this.suspended.delete(reason);
		}
		this.sync();
	}

	/** Start or stop the timer to match the current suspend reasons. */
	sync() {
		if (this.suspended.size > 0 || this.stopped) {
			this.clearTimer();
			return;
		}
		if (!this.timer) {
			this.timer = setInterval(() => this.advance(), this.interval);
		}
	}

	/** Clear the running timer, if any. */
	clearTimer() {
		if (this.timer) {
			clearInterval(this.timer);
			this.timer = null;
		}
	}

	/**
	 * Stop autoplay for the rest of the page's life.
	 *
	 * Used for `pauseOnInteraction`: once the reader has driven the slider
	 * themselves, resuming would fight them.
	 */
	stop() {
		this.stopped = true;
		this.clearTimer();
		this.onStateChange?.();
	}

	/**
	 * Whether autoplay is held off by the reader (pause button, interaction or
	 * running out of slides), as opposed to a transient hover/focus/offscreen
	 * suspension.
	 *
	 * @return {boolean} True when autoplay will not run until resumed.
	 */
	isPausedByUser() {
		return this.stopped || this.suspended.has('user');
	}

	/** Pause autoplay until `resume()` is called. */
	pause() {
		this.setSuspended('user', true);
		this.onStateChange?.();
	}

	/** Resume autoplay after a pause or a stop. */
	resume() {
		this.stopped = false;
		this.setSuspended('user', false);
		this.onStateChange?.();
	}

	/** Tear down the timer, the observer, and every listener. */
	destroy() {
		this.onStateChange = null;
		this.stop();
		this.observer?.disconnect();
		this.teardowns.forEach((teardown) => teardown());
		this.teardowns = [];
	}
}
