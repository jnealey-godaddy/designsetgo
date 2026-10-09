/**
 * Form Builder — multi-step forms in the browser.
 */
import { initFormSteps } from '../../../src/blocks/form-builder/steps-dom';

function field(name, { required = false, extra = '' } = {}) {
	return `<div class="dsgo-form-field" data-dsgo-field="${name}">
		<label class="dsgo-form-field__label">${name}</label>
		<input name="${name}" type="text" ${required ? 'required' : ''} ${extra}>
	</div>`;
}

function step(n, title, fields) {
	return `<div class="dsgo-form-step" data-dsgo-step="${n}" role="group" aria-labelledby="t${n}">
		<h3 class="dsgo-form-step__title" id="t${n}" tabindex="-1">${title}</h3>
		<div class="dsgo-form-step__fields">${fields}</div>
	</div>`;
}

function mount({ progress = '', steps = true } = {}) {
	const body = steps
		? step(1, 'About you', field('name', { required: true })) +
			step(2, 'Details', field('city')) +
			step(3, 'Finish', field('note'))
		: field('name');
	document.body.innerHTML = `
	<div class="dsgo-form-builder" ${
		progress ? `data-dsgo-step-progress="${progress}"` : ''
	}>
		<form class="dsgo-form">
			${body}
			<div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit">Send</button></div>
			<div class="dsgo-form__message"></div>
		</form>
	</div>`;
	const container = document.querySelector('.dsgo-form-builder');
	return { container, form: container.querySelector('form') };
}

const stepEl = (n) => document.querySelector(`[data-dsgo-step="${n}"]`);
const nextBtn = () => document.querySelector('.dsgo-form-steps__next');
const backBtn = () => document.querySelector('.dsgo-form-steps__back');
const footer = () => document.querySelector('.dsgo-form__footer');
const items = () => [...document.querySelectorAll('.dsgo-form-steps__item')];
const status = () =>
	document.querySelector('[data-dsgo-steps-status]').textContent;

describe('initFormSteps', () => {
	let reportSpy;
	beforeEach(() => {
		jest.useFakeTimers();
		reportSpy = jest
			.spyOn(window.HTMLInputElement.prototype, 'reportValidity')
			.mockImplementation(() => false);
	});
	afterEach(() => {
		jest.useRealTimers();
		reportSpy.mockRestore();
	});

	it('leaves a non-step form alone but sets the ready flag', () => {
		const { container, form } = mount({ steps: false });
		const c = initFormSteps(container, form);
		expect(c.isMultiStep).toBe(false);
		expect(c.beforeSubmit()).toBe(true);
		expect(container.dataset.dsgoStepsReady).toBe('1');
		expect(document.querySelector('.dsgo-form-steps__nav')).toBeNull();
		expect(document.querySelector('.dsgo-form-steps__progress')).toBeNull();
	});

	it('shows only step 1 with progress, Next and no footer', () => {
		const { container, form } = mount();
		const c = initFormSteps(container, form);
		expect(c.isMultiStep).toBe(true);
		expect(container.dataset.dsgoStepsReady).toBe('1');
		expect(stepEl(1).hidden).toBe(false);
		expect(stepEl(2).hidden).toBe(true);
		expect(stepEl(3).hidden).toBe(true);
		expect(items()).toHaveLength(3);
		expect(items()[0].getAttribute('aria-current')).toBe('step');
		expect(backBtn().hidden).toBe(true);
		expect(nextBtn().hidden).toBe(false);
		expect(footer().hidden).toBe(true);
	});

	it('blocks Next on an invalid step, then advances with focus and announcement', () => {
		const { container, form } = mount();
		initFormSteps(container, form);
		nextBtn().click();
		expect(reportSpy).toHaveBeenCalled();
		expect(stepEl(1).hidden).toBe(false);

		form.elements.name.value = 'Ann';
		nextBtn().click();
		expect(stepEl(1).hidden).toBe(true);
		expect(stepEl(2).hidden).toBe(false);
		expect(items()[0].classList.contains('is-complete')).toBe(true);
		expect(items()[0].textContent).toContain(' (completed)');
		expect(items()[1].getAttribute('aria-current')).toBe('step');
		expect(document.activeElement).toBe(
			stepEl(2).querySelector('.dsgo-form-step__title')
		);
		expect(status()).toBe('Step 2 of 3: Details');
	});

	it('ignores disabled (conditionally hidden) required fields', () => {
		const { container, form } = mount();
		initFormSteps(container, form);
		form.elements.name.disabled = true;
		nextBtn().click();
		expect(stepEl(2).hidden).toBe(false);
	});

	it('goes Back without validating', () => {
		const { container, form } = mount();
		initFormSteps(container, form);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		form.elements.name.value = '';
		reportSpy.mockClear();
		backBtn().click();
		expect(stepEl(1).hidden).toBe(false);
		expect(reportSpy).not.toHaveBeenCalled();
	});

	it('shows the footer on the last step and submits when valid', () => {
		const { container, form } = mount();
		const c = initFormSteps(container, form);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		nextBtn().click();
		expect(nextBtn().hidden).toBe(true);
		expect(footer().hidden).toBe(false);
		expect(c.beforeSubmit()).toBe(true);
	});

	it('treats submit (Enter) on an earlier step as Next', () => {
		const { container, form } = mount();
		const c = initFormSteps(container, form);
		form.elements.name.value = 'Ann';
		expect(c.beforeSubmit()).toBe(false);
		expect(stepEl(2).hidden).toBe(false);
	});

	it('jumps to an earlier invalid step on submit', () => {
		const { container, form } = mount();
		const c = initFormSteps(container, form);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		nextBtn().click();
		form.elements.name.value = '';
		reportSpy.mockClear();
		expect(c.beforeSubmit()).toBe(false);
		expect(stepEl(1).hidden).toBe(false);
		expect(stepEl(3).hidden).toBe(true);
		expect(reportSpy).toHaveBeenCalled();
	});

	it('skips steps whose fields are all hidden, and restores them', () => {
		const { container, form } = mount();
		initFormSteps(container, form);
		const w = document.querySelector('[data-dsgo-field="city"]');
		w.hidden = true;
		form.dispatchEvent(new Event('change', { bubbles: true }));
		expect(items()).toHaveLength(2);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		expect(stepEl(3).hidden).toBe(false);
		w.hidden = false;
		form.dispatchEvent(new Event('change', { bubbles: true }));
		expect(items()).toHaveLength(3);
	});

	it('does not count Hidden fields when deciding whether a step is active', () => {
		const { container, form } = mount();
		document.querySelector('[data-dsgo-field="city"]').insertAdjacentHTML(
			'afterend',
			`<div class="dsgo-form-field dsgo-form-field--hidden" data-dsgo-field="utm_source">
					<input name="utm_source" type="hidden" value="ad">
				</div>`
		);
		initFormSteps(container, form);
		expect(items()).toHaveLength(3);
		document.querySelector('[data-dsgo-field="city"]').hidden = true;
		form.dispatchEvent(new Event('change', { bubbles: true }));
		expect(items()).toHaveLength(2);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		expect(stepEl(2).hidden).toBe(true);
		expect(stepEl(3).hidden).toBe(false);
	});

	it('sends the final submit to a skipped step that a later answer revealed', () => {
		const { container, form } = mount();
		const c = initFormSteps(container, form);
		const city = document.querySelector('[data-dsgo-field="city"]');
		city.hidden = true;
		form.dispatchEvent(new Event('change', { bubbles: true }));
		form.elements.name.value = 'Ann';
		nextBtn().click();
		expect(stepEl(3).hidden).toBe(false);

		// An answer on step 3 brings step 2 back, behind the visitor.
		city.hidden = false;
		form.dispatchEvent(new Event('change', { bubbles: true }));
		expect(c.beforeSubmit()).toBe(false);
		expect(stepEl(2).hidden).toBe(false);
		expect(stepEl(3).hidden).toBe(true);
		expect(status()).toBe('Step 2 of 3: Details');

		// Once step 2 is completed the submit goes through.
		nextBtn().click();
		expect(stepEl(3).hidden).toBe(false);
		expect(c.beforeSubmit()).toBe(true);
	});

	it('un-completes a step whose visible fields grew', () => {
		const { container, form } = mount();
		stepEl(2)
			.querySelector('.dsgo-form-step__fields')
			.insertAdjacentHTML('beforeend', field('company', { extra: '' }));
		const company = document.querySelector('[data-dsgo-field="company"]');
		company.hidden = true;
		const c = initFormSteps(container, form);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		nextBtn().click();
		expect(items()[1].classList.contains('is-complete')).toBe(true);

		company.hidden = false;
		form.dispatchEvent(new Event('change', { bubbles: true }));
		expect(items()[1].classList.contains('is-complete')).toBe(false);
		expect(c.beforeSubmit()).toBe(false);
		expect(stepEl(2).hidden).toBe(false);
	});

	it('renders a progress bar or nothing per the setting', () => {
		let m = mount({ progress: 'bar' });
		initFormSteps(m.container, m.form);
		const bar = document.querySelector(
			'.dsgo-form-steps__bar [role=progressbar]'
		);
		expect(bar.getAttribute('aria-valuenow')).toBe('1');
		expect(bar.getAttribute('aria-valuemax')).toBe('3');
		expect(bar.getAttribute('aria-valuetext')).toBe('Step 1 of 3');

		m = mount({ progress: 'none' });
		initFormSteps(m.container, m.form);
		expect(document.querySelector('.dsgo-form-steps__bar')).toBeNull();
		expect(document.querySelector('.dsgo-form-steps__progress')).toBeNull();
	});

	it('hides nav and progress when only one step is active', () => {
		const { container, form } = mount();
		initFormSteps(container, form);
		['city', 'note'].forEach((n) => {
			document.querySelector(`[data-dsgo-field="${n}"]`).hidden = true;
		});
		form.dispatchEvent(new Event('change', { bubbles: true }));
		expect(document.querySelector('.dsgo-form-steps__nav').hidden).toBe(
			true
		);
		expect(
			document.querySelector('.dsgo-form-steps__progress').hidden
		).toBe(true);
		expect(footer().hidden).toBe(false);
	});

	it('resets to step 1', () => {
		const { container, form } = mount();
		initFormSteps(container, form);
		form.elements.name.value = 'Ann';
		nextBtn().click();
		nextBtn().click();
		form.reset();
		form.dispatchEvent(new Event('reset'));
		jest.runAllTimers();
		expect(stepEl(1).hidden).toBe(false);
		expect(stepEl(3).hidden).toBe(true);
		expect(document.querySelector('.is-complete')).toBeNull();
	});

	it('fails open when the first render throws, and stays open on input', () => {
		const { container, form } = mount();
		const spy = jest
			.spyOn(window.HTMLLIElement.prototype, 'setAttribute')
			.mockImplementation(() => {
				throw new Error('boom');
			});
		let c;
		expect(() => {
			c = initFormSteps(container, form);
		}).not.toThrow();
		spy.mockRestore();
		expect(c.isMultiStep).toBe(false);
		expect(container.dataset.dsgoStepsReady).toBe('1');
		expect(document.querySelector('.dsgo-form-steps__nav')).toBeNull();
		expect(document.querySelector('.dsgo-form-steps__progress')).toBeNull();
		expect(footer().hidden).toBe(false);
		[1, 2, 3].forEach((n) => expect(stepEl(n).hidden).toBe(false));

		form.dispatchEvent(new Event('input', { bubbles: true }));
		[1, 2, 3].forEach((n) => expect(stepEl(n).hidden).toBe(false));
	});
});
