/**
 * Form Builder — the view script initialises however late it loads, and one
 * form's failure never strands the others (their conditional fields would stay
 * pre-hidden by CSS while still required).
 */

const VIEW = '../../../src/blocks/form-builder/view.js';
const CONDITIONS_DOM = '../../../src/blocks/form-builder/conditions-dom';

function mountForm(id) {
	document.body.insertAdjacentHTML(
		'beforeend',
		`<div class="dsgo-form-builder" id="${id}" data-form-id="${id}">
			<form class="dsgo-form" novalidate>
				<div class="dsgo-form-field" data-dsgo-field="type">
					<input type="text" name="type" value="" />
				</div>
				<div class="dsgo-form-field dsgo-form-field--conditional" data-dsgo-field="company" data-dsgo-conditions='{"rules":[{"field":"type","op":"is","value":"b"}]}'>
					<input type="text" name="company" required />
				</div>
				<button class="dsgo-form__submit" type="submit">Send</button>
				<div class="dsgo-form__message"></div>
			</form>
		</div>`
	);
	return document.getElementById(id);
}

describe('form-builder view initialisation', () => {
	let addListener;

	beforeEach(() => {
		// Each require() adds document listeners; drop them after every test
		// so one test's module can't initialise another test's forms.
		addListener = jest.spyOn(document, 'addEventListener');
		document.body.replaceChildren();
		global.designsetgoForm = {
			restUrl: '/wp-json/designsetgo/v1/form/submit',
			ajaxUrl: '/wp-admin/admin-ajax.php',
			ajaxNonce: 'nonce',
			adminPostUrl: '/wp-admin/admin-post.php',
		};
	});

	afterEach(() => {
		addListener.mock.calls.forEach((args) =>
			document.removeEventListener(...args)
		);
		addListener.mockRestore();
		delete global.designsetgoForm;
		jest.useRealTimers();
	});

	it('initialises immediately when loaded after DOMContentLoaded', () => {
		expect(document.readyState).not.toBe('loading');
		const container = mountForm('late');
		jest.isolateModules(() => {
			require(VIEW);
		});
		// No DOMContentLoaded / dsgo-content-loaded dispatched.
		expect(container.dataset.dsgoInitialized).toBe('true');
		expect(container.dataset.dsgoConditionsReady).toBe('1');
	});

	it("keeps initialising later forms when one form's setup throws, and still surfaces the error", () => {
		jest.useFakeTimers();
		const first = mountForm('first');
		const second = mountForm('second');
		jest.isolateModules(() => {
			jest.doMock(CONDITIONS_DOM, () => ({
				initFormConditions: (container) => {
					if (container.id === 'first') {
						throw new Error('boom');
					}
					container.dataset.dsgoConditionsReady = '1';
				},
			}));
			require(VIEW);
		});
		jest.dontMock(CONDITIONS_DOM);
		document.dispatchEvent(new Event('dsgo-content-loaded'));

		expect(first.dataset.dsgoInitialized).toBe('true');
		expect(second.dataset.dsgoInitialized).toBe('true');
		expect(second.dataset.dsgoConditionsReady).toBe('1');
		// Rethrown asynchronously so it still reaches the console.
		expect(() => jest.runOnlyPendingTimers()).toThrow('boom');
	});
});
