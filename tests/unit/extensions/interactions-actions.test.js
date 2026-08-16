import {
	runAction,
	registerAction,
	actionRegistry,
} from '../../../src/extensions/interactions/actions';

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

	it('allows registering a new action', () => {
		const fn = jest.fn();
		registerAction('custom', fn);
		expect(actionRegistry.custom).toBe(fn);
		runAction('custom', [target], { value: 'v' });
		expect(fn).toHaveBeenCalled();
	});
});
