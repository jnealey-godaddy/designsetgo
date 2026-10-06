/**
 * Form Builder — conditional fields in the browser.
 */
import { initFormConditions } from '../../../src/blocks/form-builder/conditions-dom';

const business = JSON.stringify({
	rules: [{ field: 'type', op: 'is', value: 'business' }],
});
const checked = JSON.stringify({
	rules: [{ field: 'agree', op: 'not_empty', value: '' }],
});

function mount() {
	document.body.innerHTML = `
	<div class="dsgo-form-builder">
		<form class="dsgo-form">
			<div class="dsgo-form-field" data-dsgo-field="type">
				<label class="dsgo-form-field__label">Customer type<span class="dsgo-form-field__required">*</span></label>
				<select name="type"><option value="">-</option><option value="business">Business</option></select>
			</div>
			<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="company" data-dsgo-conditions='${business}'>
				<label class="dsgo-form-field__label">Company name</label>
				<input name="company" required value="">
			</div>
			<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="phone" data-dsgo-conditions='${business}'>
				<label class="dsgo-form-field__label">Phone</label>
				<input name="phone" type="tel"><select name="phone_country_code"><option value="+1">+1</option></select>
			</div>
			<div class="dsgo-form-field" data-dsgo-field="agree">
				<label class="dsgo-form-field__label">I agree</label>
				<input name="agree" type="checkbox" value="1">
			</div>
			<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="note" data-dsgo-conditions='${checked}'>
				<label class="dsgo-form-field__label">Note</label>
				<textarea name="note" disabled data-other="1"></textarea>
			</div>
		</form>
	</div>`;
	const container = document.querySelector('.dsgo-form-builder');
	return { container, form: container.querySelector('form') };
}

const wrapper = (name) => document.querySelector(`[data-dsgo-field="${name}"]`);

describe('initFormConditions', () => {
	beforeEach(() => jest.useFakeTimers());
	afterEach(() => jest.useRealTimers());

	it('applies initial visibility, disables hidden controls and marks the form ready', () => {
		const { container, form } = mount();
		initFormConditions(container, form);

		expect(container.dataset.dsgoConditionsReady).toBe('1');
		expect(wrapper('company').hidden).toBe(true);
		expect(form.elements.company.disabled).toBe(true);
		expect(form.elements.phone.disabled).toBe(true);
		expect(form.elements.phone_country_code.disabled).toBe(true);
		expect([...new FormData(form).keys()]).not.toContain('company');
	});

	it('shows fields on change, keeps typed values, and announces only interactions', () => {
		const { container, form } = mount();
		initFormConditions(container, form);
		const region = container.querySelector('[aria-live="polite"]');
		jest.runAllTimers();
		expect(region.textContent).toBe('');

		form.elements.type.value = 'business';
		form.elements.type.dispatchEvent(
			new Event('change', { bubbles: true })
		);
		expect(wrapper('company').hidden).toBe(false);
		expect(form.elements.company.disabled).toBe(false);
		jest.runAllTimers();
		expect(region.textContent).toContain('Company name');
		expect(region.textContent).not.toContain('*');

		form.elements.company.value = 'Acme';
		form.elements.type.value = '';
		form.elements.type.dispatchEvent(
			new Event('change', { bubbles: true })
		);
		expect(wrapper('company').hidden).toBe(true);
		expect(form.elements.company.value).toBe('Acme');
	});

	it('never re-enables controls disabled for other reasons', () => {
		const { container, form } = mount();
		initFormConditions(container, form);
		form.elements.agree.checked = true;
		form.elements.agree.dispatchEvent(
			new Event('change', { bubbles: true })
		);
		expect(wrapper('note').hidden).toBe(false);
		expect(form.elements.note.disabled).toBe(true);
	});

	it('re-evaluates after reset', () => {
		const { container, form } = mount();
		initFormConditions(container, form);
		form.elements.type.value = 'business';
		form.elements.type.dispatchEvent(
			new Event('change', { bubbles: true })
		);
		expect(wrapper('company').hidden).toBe(false);

		form.reset();
		form.dispatchEvent(new Event('reset'));
		jest.runAllTimers();
		expect(wrapper('company').hidden).toBe(true);
	});

	it('fails open: a setup error leaves every field visible and the form ready', () => {
		jest.isolateModules(() => {
			jest.doMock('../../../src/blocks/form-builder/conditions', () => ({
				visibleFields: () => {
					throw new Error('boom');
				},
			}));
			const {
				initFormConditions: init,
			} = require('../../../src/blocks/form-builder/conditions-dom');
			const { container, form } = mount();
			// Must not throw: the rest of the form (submit handling) still has to initialise.
			expect(() => init(container, form)).not.toThrow();
			expect(container.dataset.dsgoConditionsReady).toBe('1');
			document
				.querySelectorAll('[data-dsgo-field]')
				.forEach((el) => expect(el.hidden).toBe(false));
			expect(form.elements.company.disabled).toBe(false);
		});
		jest.dontMock('../../../src/blocks/form-builder/conditions');
	});

	it('ignores malformed rules (field stays visible)', () => {
		const { container, form } = mount();
		wrapper('company').setAttribute('data-dsgo-conditions', '{not json');
		initFormConditions(container, form);
		expect(wrapper('company').hidden).toBe(false);
	});

	it('marks ready and does nothing without a form', () => {
		const { container } = mount();
		initFormConditions(container, null);
		expect(container.dataset.dsgoConditionsReady).toBe('1');
	});
});
