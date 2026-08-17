import {
	runAction,
	registerAction,
	actionRegistry,
} from '../../../src/extensions/interactions/actions';
import { ACTIONS } from '../../../src/extensions/interactions/constants';

describe('action registry coverage', () => {
	it('implements every action offered in the picker', () => {
		// Without this, an action can be added to the dropdown and silently
		// do nothing when a visitor triggers it.
		const missing = ACTIONS.map((a) => a.value).filter(
			(name) => 'function' !== typeof actionRegistry[name]
		);
		expect(missing).toEqual([]);
	});

	it('offers every implemented action in the picker', () => {
		const listed = ACTIONS.map((a) => a.value);
		const unreachable = Object.keys(actionRegistry).filter(
			(name) => !listed.includes(name)
		);
		expect(unreachable).toEqual([]);
	});
});

describe('interaction actions', () => {
	let target;

	beforeEach(() => {
		document.body.innerHTML = '<div id="t"></div><div id="s"></div>';
		target = document.getElementById('t');
	});

	it('toggles a class on and off', () => {
		runAction('toggleClass', [target], { value: 'is-open' });
		expect(target.classList.contains('is-open')).toBe(true);
		runAction('toggleClass', [target], { value: 'is-open' });
		expect(target.classList.contains('is-open')).toBe(false);
	});

	it('adds and removes a class', () => {
		runAction('addClass', [target], { value: 'a' });
		expect(target.classList.contains('a')).toBe(true);
		runAction('removeClass', [target], { value: 'a' });
		expect(target.classList.contains('a')).toBe(false);
	});

	it('sets an attribute', () => {
		runAction('setAttribute', [target], {
			attributeName: 'aria-expanded',
			value: 'true',
		});
		expect(target.getAttribute('aria-expanded')).toBe('true');
	});

	it('refuses to set an event-handler attribute', () => {
		runAction('setAttribute', [target], {
			attributeName: 'onclick',
			value: 'alert(1)',
		});
		expect(target.hasAttribute('onclick')).toBe(false);
	});

	describe('hostile input', () => {
		it.each([
			['href', 'javascript:alert(1)'],
			['src', ' javascript:alert(1)'],
			['formaction', 'JavaScript:alert(1)'],
			['href', 'data:text/html,<script>alert(1)</script>'],
			['href', 'vbscript:msgbox(1)'],
		])('refuses to set %s to a script URL (%s)', (attributeName, value) => {
			runAction('setAttribute', [target], { attributeName, value });
			expect(target.hasAttribute(attributeName)).toBe(false);
		});

		it('still allows an ordinary href', () => {
			runAction('setAttribute', [target], {
				attributeName: 'href',
				value: 'https://example.com',
			});
			expect(target.getAttribute('href')).toBe('https://example.com');
		});

		it('refuses an attribute name setAttribute would throw on', () => {
			// An invalid XML name throws InvalidCharacterError, which would
			// escape the delegated listener and kill every later interaction.
			expect(() =>
				runAction('setAttribute', [target], {
					attributeName: '1bad name',
					value: 'x',
				})
			).not.toThrow();
			expect(target.attributes).toHaveLength(1); // id only
		});

		it.each([
			['.is-open', 'is-open'],
			['  is-open  ', 'is-open'],
		])('normalises the class value %s', (input, expected) => {
			runAction('addClass', [target], { value: input });
			expect(target.classList.contains(expected)).toBe(true);
		});

		it('applies every class when given several', () => {
			runAction('addClass', [target], { value: 'one two' });
			expect(target.classList.contains('one')).toBe(true);
			expect(target.classList.contains('two')).toBe(true);
		});

		it('does not throw on a class value that is only punctuation', () => {
			expect(() =>
				runAction('addClass', [target], { value: '  .  ' })
			).not.toThrow();
			expect(target.classList.length).toBe(0);
		});
	});

	it('applies to every element in the target list', () => {
		document.body.innerHTML = '<i class="x"></i><i class="x"></i>';
		const many = Array.from(document.querySelectorAll('.x'));
		runAction('addClass', many, { value: 'on' });
		expect(document.querySelectorAll('.x.on')).toHaveLength(2);
	});

	it('dispatches a modal open event carrying the modal id', () => {
		const spy = jest.fn();
		document.addEventListener('dsgo-modal-open', spy);
		runAction('openModal', [target], { value: 'my-modal' });
		expect(spy).toHaveBeenCalled();
		expect(spy.mock.calls[0][0].detail).toEqual({ modalId: 'my-modal' });
		document.removeEventListener('dsgo-modal-open', spy);
	});

	it('dispatches a modal close event carrying the modal id', () => {
		const spy = jest.fn();
		document.addEventListener('dsgo-modal-close', spy);
		runAction('closeModal', [target], { value: 'my-modal' });
		expect(spy.mock.calls[0][0].detail).toEqual({ modalId: 'my-modal' });
		document.removeEventListener('dsgo-modal-close', spy);
	});

	it('ignores an unknown action name without throwing', () => {
		expect(() => runAction('nope', [target], {})).not.toThrow();
	});

	it('ignores an empty target list', () => {
		expect(() => runAction('addClass', [], { value: 'a' })).not.toThrow();
	});

	it('ignores a class action with no class name', () => {
		expect(() => runAction('addClass', [target], {})).not.toThrow();
		expect(target.classList.length).toBe(0);
	});

	describe('visibility', () => {
		it('hides and shows a target', () => {
			runAction('hide', [target], {});
			expect(target.classList.contains('dsgo-interaction-hidden')).toBe(
				true
			);
			runAction('show', [target], {});
			expect(target.classList.contains('dsgo-interaction-hidden')).toBe(
				false
			);
		});

		it('toggles visibility', () => {
			runAction('toggleVisibility', [target], {});
			expect(target.classList.contains('dsgo-interaction-hidden')).toBe(
				true
			);
			runAction('toggleVisibility', [target], {});
			expect(target.classList.contains('dsgo-interaction-hidden')).toBe(
				false
			);
		});

		it('needs no value, unlike the class actions', () => {
			// hide() must not silently no-op the way addClass('') does.
			expect(() => runAction('hide', [target], {})).not.toThrow();
			expect(target.classList.contains('dsgo-interaction-hidden')).toBe(
				true
			);
		});

		it('mirrors the state onto a button trigger as aria-expanded', () => {
			document.body.innerHTML =
				'<button id="b"></button><div id="t"></div>';
			const btn = document.getElementById('b');
			const t = document.getElementById('t');

			runAction('hide', [t], {}, btn);
			expect(btn.getAttribute('aria-expanded')).toBe('false');

			runAction('show', [t], {}, btn);
			expect(btn.getAttribute('aria-expanded')).toBe('true');
		});

		it('mirrors state onto an element given button semantics', () => {
			document.body.innerHTML =
				'<div id="b" role="button"></div><div id="t"></div>';
			const btn = document.getElementById('b');
			runAction('hide', [document.getElementById('t')], {}, btn);
			expect(btn.getAttribute('aria-expanded')).toBe('false');
		});

		it('does not put aria-expanded on a non-control', () => {
			document.body.innerHTML = '<div id="b"></div><div id="t"></div>';
			const plain = document.getElementById('b');
			runAction('hide', [document.getElementById('t')], {}, plain);
			expect(plain.hasAttribute('aria-expanded')).toBe(false);
		});
	});

	describe('attributes', () => {
		it('removes an attribute', () => {
			target.setAttribute('aria-expanded', 'true');
			runAction('removeAttribute', [target], {
				attributeName: 'aria-expanded',
			});
			expect(target.hasAttribute('aria-expanded')).toBe(false);
		});

		it('refuses to remove an event-handler attribute', () => {
			// Removing on* is harmless, but allowing the name through here
			// would make the allowlist inconsistent between the two actions.
			target.setAttribute('onclick', 'x');
			runAction('removeAttribute', [target], {
				attributeName: 'onclick',
			});
			expect(target.hasAttribute('onclick')).toBe(true);
		});
	});

	describe('media', () => {
		let media;

		beforeEach(() => {
			document.body.innerHTML = '<video id="v"></video>';
			media = document.getElementById('v');
			media.play = jest.fn();
			media.pause = jest.fn();
			Object.defineProperty(media, 'paused', {
				value: true,
				writable: true,
			});
		});

		it('plays and pauses', () => {
			runAction('playMedia', [media], {});
			expect(media.play).toHaveBeenCalled();
			runAction('pauseMedia', [media], {});
			expect(media.pause).toHaveBeenCalled();
		});

		it('toggles based on the current state', () => {
			runAction('toggleMedia', [media], {});
			expect(media.play).toHaveBeenCalled();

			media.paused = false;
			runAction('toggleMedia', [media], {});
			expect(media.pause).toHaveBeenCalled();
		});

		it('ignores a target that is not a media element', () => {
			expect(() => runAction('toggleMedia', [target], {})).not.toThrow();
		});

		it('descends into a wrapper to find the media', () => {
			// core/video renders <figure><video>…</video></figure>, so the
			// block an author targets is the figure, not the media element.
			document.body.innerHTML = '<figure id="f"><video></video></figure>';
			const figure = document.getElementById('f');
			const video = figure.querySelector('video');
			video.play = jest.fn();

			runAction('playMedia', [figure], {});
			expect(video.play).toHaveBeenCalled();
		});
	});

	describe('forms', () => {
		it('submits the form containing the target', () => {
			document.body.innerHTML =
				'<form id="f"><button id="inner"></button></form>';
			const form = document.getElementById('f');
			form.requestSubmit = jest.fn();

			runAction('submitForm', [document.getElementById('inner')], {});
			expect(form.requestSubmit).toHaveBeenCalled();
		});

		it('submits when the target is the form itself', () => {
			document.body.innerHTML = '<form id="f"></form>';
			const form = document.getElementById('f');
			form.requestSubmit = jest.fn();

			runAction('submitForm', [form], {});
			expect(form.requestSubmit).toHaveBeenCalled();
		});

		it('descends into a wrapper to find the form', () => {
			// The form-builder block's wrapper is a <div> around the <form>,
			// so the natural target is an ancestor, not a descendant.
			document.body.innerHTML =
				'<div id="wrap"><form id="f"></form></div>';
			const form = document.getElementById('f');
			form.requestSubmit = jest.fn();

			runAction('submitForm', [document.getElementById('wrap')], {});
			expect(form.requestSubmit).toHaveBeenCalled();
		});

		it('does nothing when there is no form', () => {
			expect(() => runAction('submitForm', [target], {})).not.toThrow();
		});
	});

	describe('focus', () => {
		it('focuses a natively focusable target', () => {
			document.body.innerHTML = '<input id="i" />';
			const input = document.getElementById('i');
			runAction('focusTarget', [input], {});
			expect(document.activeElement).toBe(input);
		});

		it('makes a non-focusable target programmatically focusable', () => {
			runAction('focusTarget', [target], {});
			expect(target.getAttribute('tabindex')).toBe('-1');
			expect(document.activeElement).toBe(target);
		});

		it('does not override an existing tabindex', () => {
			target.setAttribute('tabindex', '0');
			runAction('focusTarget', [target], {});
			expect(target.getAttribute('tabindex')).toBe('0');
		});
	});

	describe('custom events', () => {
		it('fires a named event on the target that bubbles', () => {
			const spy = jest.fn();
			document.addEventListener('my-event', spy);
			runAction('dispatchEvent', [target], { value: 'my-event' });
			expect(spy).toHaveBeenCalled();
			document.removeEventListener('my-event', spy);
		});

		it('does nothing without an event name', () => {
			expect(() =>
				runAction('dispatchEvent', [target], {})
			).not.toThrow();
		});
	});

	it('scrolls the window to the top', () => {
		window.scrollTo = jest.fn();
		runAction('scrollToTop', [], {});
		expect(window.scrollTo).toHaveBeenCalledWith(
			expect.objectContaining({ top: 0 })
		);
	});

	it('allows registering a new action', () => {
		const fn = jest.fn();
		registerAction('custom', fn);
		expect(actionRegistry.custom).toBe(fn);
		runAction('custom', [target], { value: 'v' });
		expect(fn).toHaveBeenCalled();
	});
});
