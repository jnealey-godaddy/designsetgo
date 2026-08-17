import {
	TRIGGERS,
	ACTIONS,
	ACTION_GROUPS,
	TARGET_MODES,
	ACTION_VALUE_FIELD,
	OFFSET_ACTIONS,
	VISIBILITY_ACTIONS,
	HIDDEN_CLASS,
	DEFAULT_INTERACTION,
} from '../../../src/extensions/interactions/constants';

describe('interactions constants', () => {
	it('exposes every trigger as a value/label pair', () => {
		expect(TRIGGERS.map((t) => t.value)).toEqual([
			'click',
			'hover',
			'inView',
			'exitIntent',
			'keydown',
		]);
		TRIGGERS.forEach((t) => expect(typeof t.label).toBe('string'));
	});

	it('exposes every action as a value/label pair', () => {
		expect(ACTIONS.map((a) => a.value)).toEqual([
			'show',
			'hide',
			'toggleVisibility',
			'toggleClass',
			'addClass',
			'removeClass',
			'setAttribute',
			'removeAttribute',
			'scrollTo',
			'scrollToTop',
			'openModal',
			'closeModal',
			'submitForm',
			'playMedia',
			'pauseMedia',
			'toggleMedia',
			'copyToClipboard',
			'focusTarget',
			'dispatchEvent',
		]);
		ACTIONS.forEach((a) => expect(typeof a.label).toBe('string'));
	});

	it('assigns every action to a declared group', () => {
		const keys = ACTION_GROUPS.map((g) => g.key);
		ACTIONS.forEach((a) => {
			expect(keys).toContain(a.group);
		});
	});

	it('leaves no group empty', () => {
		ACTION_GROUPS.forEach((g) => {
			expect(ACTIONS.some((a) => a.group === g.key)).toBe(true);
		});
	});

	it('names the visibility actions and the class they toggle', () => {
		expect(VISIBILITY_ACTIONS).toEqual([
			'show',
			'hide',
			'toggleVisibility',
		]);
		expect(HIDDEN_CLASS).toBe('dsgo-interaction-hidden');
		VISIBILITY_ACTIONS.forEach((action) => {
			expect(ACTIONS.map((a) => a.value)).toContain(action);
		});
	});

	it('exposes the three target modes', () => {
		expect(TARGET_MODES.map((t) => t.value)).toEqual([
			'self',
			'selector',
			'parent',
		]);
	});

	it('defaults to a click that toggles a class on itself', () => {
		expect(DEFAULT_INTERACTION).toEqual({
			id: '',
			trigger: 'click',
			targetMode: 'self',
			targetSelector: '',
			action: 'toggleClass',
			value: '',
			attributeName: '',
			key: '',
			once: false,
			offset: 0,
		});
	});

	it('keeps the keyboard key separate from the attribute name', () => {
		// They were one overloaded field; a keydown that sets an attribute
		// needs both at once, so they must not share storage.
		expect(DEFAULT_INTERACTION).toHaveProperty('key');
		expect(DEFAULT_INTERACTION).toHaveProperty('attributeName');
	});

	it('labels the shared value field for every action that takes one', () => {
		// These act on the target itself and need no payload, so the value
		// field is hidden for them rather than shown with a guessed label.
		const valueless = [
			'show',
			'hide',
			'toggleVisibility',
			'removeAttribute',
			'scrollTo',
			'scrollToTop',
			'submitForm',
			'playMedia',
			'pauseMedia',
			'toggleMedia',
			'focusTarget',
		];
		ACTIONS.forEach(({ value }) => {
			if (valueless.includes(value)) {
				expect(ACTION_VALUE_FIELD[value]).toBeUndefined();
				return;
			}
			expect(ACTION_VALUE_FIELD[value]).toBeDefined();
			expect(typeof ACTION_VALUE_FIELD[value].label).toBe('string');
		});
	});

	it('marks scroll actions as offset-aware', () => {
		expect(OFFSET_ACTIONS).toEqual(['scrollTo']);
		OFFSET_ACTIONS.forEach((action) => {
			expect(ACTIONS.map((a) => a.value)).toContain(action);
		});
	});
});
