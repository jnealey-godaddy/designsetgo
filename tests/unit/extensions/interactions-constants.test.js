import {
	TRIGGERS,
	ACTIONS,
	TARGET_MODES,
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
			once: false,
			offset: 0,
		});
	});
});
