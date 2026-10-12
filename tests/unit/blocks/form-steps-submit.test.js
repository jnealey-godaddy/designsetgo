/**
 * Form Builder — submitting a multi-step form through view.js.
 *
 * Enter in a field submits the form, whichever step is showing. On an earlier
 * step that must advance instead, for native (non-AJAX) posts as well as AJAX.
 */

const VIEW = '../../../src/blocks/form-builder/view.js';

function step(n, title, name, required = false) {
	return `<div class="dsgo-form-step" data-dsgo-step="${n}" role="group" aria-labelledby="t${n}">
		<h3 class="dsgo-form-step__title" id="t${n}" tabindex="-1">${title}</h3>
		<div class="dsgo-form-step__fields">
			<div class="dsgo-form-field" data-dsgo-field="${name}">
				<input name="${name}" type="text" ${required ? 'required' : ''}>
			</div>
		</div>
	</div>`;
}

function mount(ajax, { steps = true } = {}) {
	const fields = steps
		? step(1, 'About you', 'name', true) +
			step(2, 'Details', 'city') +
			step(3, 'Finish', 'note')
		: '<div class="dsgo-form-field" data-dsgo-field="name"><input name="name" type="text" required></div>';
	document.body.innerHTML = `
	<div class="dsgo-form-builder" data-form-id="steps-${ajax}" data-ajax-submit="${ajax}">
		<form class="dsgo-form" method="post" novalidate>
			<div class="dsgo-form__fields">
				${fields}
			</div>
			<div class="dsgo-form__footer"><button type="submit" class="dsgo-form__submit">Send</button></div>
			<div class="dsgo-form__message" role="status" style="display:none"></div>
		</form>
	</div>`;
	jest.isolateModules(() => {
		require(VIEW);
	});
	return document.querySelector('.dsgo-form');
}

const stepEl = (n) => document.querySelector(`[data-dsgo-step="${n}"]`);
const submit = (form) => {
	const event = new Event('submit', { bubbles: true, cancelable: true });
	form.dispatchEvent(event);
	return event;
};
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('multi-step form submission', () => {
	let addListener;
	let reportSpy;

	beforeEach(() => {
		// Drop the document listeners each require() adds.
		addListener = jest.spyOn(document, 'addEventListener');
		reportSpy = jest
			.spyOn(window.HTMLInputElement.prototype, 'reportValidity')
			.mockImplementation(() => false);
		global.designsetgoForm = {
			restUrl: '/wp-json/designsetgo/v1/form/submit',
			ajaxUrl: '/wp-admin/admin-ajax.php',
			ajaxNonce: 'nonce',
			adminPostUrl: '/wp-admin/admin-post.php',
		};
		// tests/unit/setup.js installs a global fetch mock and clears it.
		global.fetch.mockReset();
		window.sessionStorage.clear();
	});

	afterEach(() => {
		addListener.mock.calls.forEach((args) =>
			document.removeEventListener(...args)
		);
		addListener.mockRestore();
		reportSpy.mockRestore();
		delete global.designsetgoForm;
		document.body.replaceChildren();
	});

	describe('without AJAX', () => {
		it('treats submit on step 1 as Next instead of posting', () => {
			const form = mount('false');
			form.elements.name.value = 'Ann';
			const event = submit(form);
			expect(event.defaultPrevented).toBe(true);
			expect(stepEl(1).hidden).toBe(true);
			expect(stepEl(2).hidden).toBe(false);
		});

		it('keeps an invalid step 1 in place', () => {
			const form = mount('false');
			const event = submit(form);
			expect(event.defaultPrevented).toBe(true);
			expect(stepEl(1).hidden).toBe(false);
			expect(reportSpy).toHaveBeenCalled();
		});

		it('lets the native post through on a valid last step', () => {
			const form = mount('false');
			form.elements.name.value = 'Ann';
			document.querySelector('.dsgo-form-steps__next').click();
			document.querySelector('.dsgo-form-steps__next').click();
			expect(stepEl(3).hidden).toBe(false);
			const event = submit(form);
			expect(event.defaultPrevented).toBe(false);
		});
	});

	describe('with AJAX', () => {
		it('advances on step 1 without fetching or disabling the button', async () => {
			const form = mount('true');
			form.elements.name.value = 'Ann';
			submit(form);
			await flush();
			expect(global.fetch).not.toHaveBeenCalled();
			expect(form.querySelector('.dsgo-form__submit').disabled).toBe(
				false
			);
			expect(stepEl(2).hidden).toBe(false);
		});

		it('after success, scrolls to and focuses the message once step 1 is back', async () => {
			global.fetch.mockResolvedValue({
				ok: true,
				status: 200,
				json: async () => ({ success: true, message: 'Thanks' }),
			});
			const form = mount('true');
			const message = form.querySelector('.dsgo-form__message');
			message.getBoundingClientRect = () => ({
				top: -200,
				left: 0,
				bottom: -150,
				right: 100,
			});
			const seen = [];
			message.scrollIntoView = jest.fn(() => {
				seen.push({
					step1Shown: !stepEl(1).hidden,
					focused: document.activeElement === message,
				});
			});

			form.elements.name.value = 'Ann';
			document.querySelector('.dsgo-form-steps__next').click();
			document.querySelector('.dsgo-form-steps__next').click();
			submit(form);
			for (let i = 0; i < 5; i++) {
				await flush();
			}

			expect(global.fetch).toHaveBeenCalledTimes(1);
			expect(message.textContent).toContain('Thanks');
			expect(stepEl(1).hidden).toBe(false);
			expect(message.getAttribute('tabindex')).toBe('-1');
			expect(document.activeElement).toBe(message);
			expect(
				message
					.querySelector('.screen-reader-text')
					.getAttribute('aria-hidden')
			).toBe('true');
			expect(seen).toEqual([{ step1Shown: true, focused: true }]);
		});

		it('leaves the single-page success path as it was', async () => {
			global.fetch.mockResolvedValue({
				ok: true,
				status: 200,
				json: async () => ({ success: true, message: 'Thanks' }),
			});
			const form = mount('true', { steps: false });
			const message = form.querySelector('.dsgo-form__message');
			message.getBoundingClientRect = () => ({
				top: -200,
				left: 0,
				bottom: -150,
				right: 100,
			});
			message.scrollIntoView = jest.fn();

			form.elements.name.value = 'Ann';
			submit(form);
			for (let i = 0; i < 5; i++) {
				await flush();
			}

			expect(message.scrollIntoView).toHaveBeenCalledTimes(1);
			expect(message.hasAttribute('tabindex')).toBe(false);
			expect(document.activeElement).not.toBe(message);
		});
	});
});
