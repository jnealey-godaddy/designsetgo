import {
	summariseInteraction,
	summariseTarget,
} from '../../../src/extensions/interactions/summarise';

describe('summariseInteraction', () => {
	it('names the trigger and the action', () => {
		expect(
			summariseInteraction({ trigger: 'click', action: 'toggleClass' })
		).toBe('Click → Toggle class');
	});

	it('includes the value when there is one', () => {
		expect(
			summariseInteraction({
				trigger: 'hover',
				action: 'addClass',
				value: 'is-lit',
			})
		).toBe('Hover → Add class is-lit');
	});

	it('renders setAttribute as name=value', () => {
		expect(
			summariseInteraction({
				trigger: 'click',
				action: 'setAttribute',
				attributeName: 'aria-expanded',
				value: 'true',
			})
		).toBe('Click → Set attribute aria-expanded=true');
	});

	it('distinguishes two interactions that differ only by value', () => {
		const a = summariseInteraction({
			trigger: 'click',
			action: 'addClass',
			value: 'one',
		});
		const b = summariseInteraction({
			trigger: 'click',
			action: 'addClass',
			value: 'two',
		});
		expect(a).not.toBe(b);
	});

	it('does not throw on an empty interaction', () => {
		expect(() => summariseInteraction()).not.toThrow();
		expect(() => summariseInteraction({})).not.toThrow();
	});
});

describe('summariseTarget', () => {
	it('says nothing when the target is the block itself', () => {
		expect(summariseTarget({ targetMode: 'self' })).toBe('');
		expect(summariseTarget({})).toBe('');
	});

	it('shows the selector when one is set', () => {
		expect(
			summariseTarget({
				targetMode: 'selector',
				targetSelector: '.panel',
			})
		).toBe('.panel');
	});

	it('flags a selector mode with no selector', () => {
		expect(summariseTarget({ targetMode: 'selector' })).toBe(
			'CSS selector (none set)'
		);
	});
});
