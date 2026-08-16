import {
	TRIGGERS,
	ACTIONS,
	TARGET_MODES,
	ACTION_VALUE_FIELD,
	OFFSET_ACTIONS,
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
			'toggleClass',
			'addClass',
			'removeClass',
			'setAttribute',
			'scrollTo',
			'openModal',
			'closeModal',
			'copyToClipboard',
		]);
		ACTIONS.forEach((a) => expect(typeof a.label).toBe('string'));
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
		const valueless = ['scrollTo'];
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
