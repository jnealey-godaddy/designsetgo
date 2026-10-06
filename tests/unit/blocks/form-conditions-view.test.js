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
	// Field markup mirrors what each field's render.php outputs.
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
			<div class="dsgo-form-field dsgo-form-field--checkbox dsgo-form-field--conditional" data-dsgo-field="invoice" data-dsgo-conditions='${business}'>
				<div class="dsgo-form-field__checkbox-wrapper">
					<input type="checkbox" id="field-invoice" name="invoice" class="dsgo-form-field__checkbox-input" value="1" required aria-required="true" data-field-type="checkbox"/>
					<label for="field-invoice" class="dsgo-form-field__checkbox-label"><span>Send an <a href="/invoices">invoice</a></span><span class="dsgo-form-field__required" aria-label="required"> *</span></label>
				</div>
			</div>
			<div class="dsgo-form-field dsgo-form-field--text dsgo-form-field--conditional" data-dsgo-field="vat_number" data-dsgo-conditions='${business}'>
				<input name="vat_number" type="text">
			</div>
			<div class="dsgo-form-field dsgo-form-field--hidden dsgo-form-field--conditional" data-dsgo-field="campaign" data-dsgo-conditions='${business}'>
				<input type="hidden" name="campaign" value="spring" data-field-type="hidden"/>
			</div>
			<div class="dsgo-form-field dsgo-form-field--checkbox" data-dsgo-field="agree">
				<div class="dsgo-form-field__checkbox-wrapper">
					<input type="checkbox" id="field-agree" name="agree" class="dsgo-form-field__checkbox-input" value="1" data-field-type="checkbox"/>
					<label for="field-agree" class="dsgo-form-field__checkbox-label"><span>I agree</span></label>
				</div>
			</div>
			<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="note" data-dsgo-conditions='${checked}'>
				<label class="dsgo-form-field__label">Note</label>
				<textarea name="note"></textarea>
			</div>
		</form>
	</div>`;
	const container = document.querySelector('.dsgo-form-builder');
	return { container, form: container.querySelector('form') };
}

const wrapper = (name) => document.querySelector(`[data-dsgo-field="${name}"]`);

function change(control) {
	control.dispatchEvent(new Event('change', { bubbles: true }));
}

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
		change(form.elements.agree);
		expect(form.elements.note.disabled).toBe(false);

		// Another script disables the visible control after init.
		form.elements.note.disabled = true;
		form.elements.agree.checked = false;
		change(form.elements.agree);
		expect(wrapper('note').hidden).toBe(true);
		form.elements.agree.checked = true;
		change(form.elements.agree);
		expect(wrapper('note').hidden).toBe(false);
		expect(form.elements.note.disabled).toBe(true);
	});

	it('clears disabled state a browser restored on reload', () => {
		const { container, form } = mount();
		// Firefox restores script-set `disabled` without our marker attribute;
		// no field's render.php ever outputs `disabled`.
		form.elements.type.value = 'business';
		form.elements.company.disabled = true;
		form.elements.note.disabled = true;
		form.elements.note.dataset.dsgoCondDisabled = '1';
		initFormConditions(container, form);

		expect(wrapper('company').hidden).toBe(false);
		expect(form.elements.company.disabled).toBe(false);

		// A hidden field's restored `disabled` is ours again, so showing it re-enables it.
		expect(wrapper('note').hidden).toBe(true);
		form.elements.agree.checked = true;
		change(form.elements.agree);
		expect(form.elements.note.disabled).toBe(false);
	});

	it('announces checkbox fields, falls back to the field name, and skips hidden-type fields', () => {
		const { container, form } = mount();
		initFormConditions(container, form);
		const region = container.querySelector('[aria-live="polite"]');

		form.elements.type.value = 'business';
		change(form.elements.type);
		jest.runAllTimers();
		expect(region.textContent).toBe(
			'Shown: Company name, Phone, Send an invoice, vat_number.'
		);
		expect(region.textContent).not.toContain('campaign');
	});

	it('announces a field that toggled within the debounce window only in its final state', () => {
		const { container, form } = mount();
		initFormConditions(container, form);
		const region = container.querySelector('[aria-live="polite"]');

		form.elements.agree.checked = true;
		change(form.elements.agree);
		form.elements.agree.checked = false;
		change(form.elements.agree);
		jest.runAllTimers();
		expect(region.textContent).toBe('Hidden: Note.');
	});

	it('keeps rules and values for a field named __proto__', () => {
		const proIs = JSON.stringify({
			rules: [{ field: 'plan', op: 'is', value: 'pro' }],
		});
		const protoFilled = JSON.stringify({
			rules: [{ field: '__proto__', op: 'not_empty', value: '' }],
		});
		document.body.innerHTML = `
		<div class="dsgo-form-builder">
			<form class="dsgo-form">
				<div class="dsgo-form-field" data-dsgo-field="plan">
					<input name="plan" value="">
				</div>
				<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="__proto__" data-dsgo-conditions='${proIs}'>
					<input name="__proto__" value="">
				</div>
				<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="extra" data-dsgo-conditions='${protoFilled}'>
					<input name="extra" value="">
				</div>
			</form>
		</div>`;
		const container = document.querySelector('.dsgo-form-builder');
		const form = container.querySelector('form');
		const control = (name) => form.querySelector(`[name="${name}"]`);
		initFormConditions(container, form);
		expect(wrapper('__proto__').hidden).toBe(true);
		expect(wrapper('extra').hidden).toBe(true);

		control('plan').value = 'pro';
		control('__proto__').value = 'x';
		change(control('plan'));
		expect(wrapper('__proto__').hidden).toBe(false);
		expect(wrapper('extra').hidden).toBe(false);
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
