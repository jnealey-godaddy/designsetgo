/**
 * Form Builder — the view script works with the fields and result message the
 * server renders for visitors without JavaScript.
 *
 * Form_No_JS_Submit adds `action`, `_wpnonce` and `dsg_timestamp` to every
 * form, and prints the admin-post result into the message box. The script must
 * reuse those fields rather than post a second copy, keep them out of the AJAX
 * field list, and never show the printed message twice.
 */

const VIEW = '../../../src/blocks/form-builder/view.js';
const SERVER_TIMESTAMP = '1000000000000';

function mountForm({ ajax = true, message = '' } = {}) {
	document.body.insertAdjacentHTML(
		'afterbegin',
		`<div class="dsgo-form-builder" data-form-id="contact" data-ajax-submit="${ajax}" data-success-message="Thanks!" data-error-message="Nope.">
			<form class="dsgo-form" action="/wp-admin/admin-post.php" method="post" novalidate>
				<div class="dsgo-form__fields">
					<input type="text" name="your_name" value="Pat" data-field-type="text" />
				</div>
				<input type="text" name="dsg_website" value="" />
				<input type="hidden" name="dsg_form_id" value="contact" />
				<div class="dsgo-form__footer"><button class="dsgo-form__submit" type="submit">Send</button></div>
				${
					message ||
					'<div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display:none"></div>'
				}
				<input type="hidden" name="action" value="designsetgo_form_submit" />
				<input type="hidden" name="_wpnonce" value="servernonce" />
				<input type="hidden" name="dsg_timestamp" value="${SERVER_TIMESTAMP}" />
			</form>
		</div>`
	);
	return document.querySelector('.dsgo-form');
}

const SERVER_SUCCESS =
	'<div class="dsgo-form__message dsgo-form__message--success" id="dsgo-form-message-contact" role="status" aria-live="polite" aria-atomic="true" data-dsgo-server-message="success">Thanks!</div>';

function init() {
	jest.isolateModules(() => {
		require(VIEW);
	});
	document.dispatchEvent(new Event('dsgo-content-loaded'));
}

function count(form, name) {
	return form.querySelectorAll(`input[name="${name}"]`).length;
}

describe('form-builder with server-rendered no-JS fields', () => {
	let addListener;
	let originalFetch;

	beforeEach(() => {
		originalFetch = global.fetch;
		addListener = jest.spyOn(document, 'addEventListener');
		document.body.replaceChildren();
		window.history.replaceState(null, '', '/contact/');
		window.sessionStorage.clear();
		global.designsetgoForm = {
			restUrl: '/wp-json/designsetgo/v1/form/submit',
			ajaxUrl: '/wp-admin/admin-ajax.php',
			ajaxNonce: 'scriptnonce',
			adminPostUrl: '/wp-admin/admin-post.php',
			postId: '0',
		};
		global.fetch = jest.fn(() =>
			Promise.resolve({
				ok: true,
				status: 200,
				json: () => Promise.resolve({ success: true }),
			})
		);
	});

	afterEach(() => {
		addListener.mock.calls.forEach((args) =>
			document.removeEventListener(...args)
		);
		addListener.mockRestore();
		delete global.designsetgoForm;
		global.fetch = originalFetch;
	});

	it('reuses the rendered fields for a non-AJAX form instead of adding copies', () => {
		const form = mountForm({ ajax: false });
		init();

		expect(count(form, 'action')).toBe(1);
		expect(count(form, '_wpnonce')).toBe(1);
		expect(count(form, 'dsg_timestamp')).toBe(1);
		// The script's own values win: the time it ran, its localized nonce.
		expect(form.querySelector('[name="dsg_timestamp"]').value).not.toBe(
			SERVER_TIMESTAMP
		);
		expect(form.querySelector('[name="_wpnonce"]').value).toBe(
			'scriptnonce'
		);
	});

	it('keeps the admin-post fields out of the AJAX field list', async () => {
		const form = mountForm({ ajax: true });
		init();
		expect(count(form, 'dsg_timestamp')).toBe(1);

		form.dispatchEvent(
			new Event('submit', { bubbles: true, cancelable: true })
		);
		await new Promise((resolve) => setTimeout(resolve, 0));

		const body = JSON.parse(global.fetch.mock.calls[0][1].body);
		expect(body.fields.map((field) => field.name)).toEqual(['your_name']);
		expect(Number(body.timestamp)).toBeGreaterThan(
			Number(SERVER_TIMESTAMP)
		);
	});

	it('shows a server-printed result once when the URL carries it', () => {
		window.history.replaceState(
			null,
			'',
			'/contact/?dsgo_form_status=success&dsgo_form_id=contact'
		);
		const form = mountForm({ message: SERVER_SUCCESS });
		init();

		const box = form.querySelector('.dsgo-form__message');
		expect(form.querySelectorAll('.dsgo-form__message')).toHaveLength(1);
		expect(box.hasAttribute('data-dsgo-server-message')).toBe(false);
		expect(box.style.display).toBe('block');
		// One visible copy of the text (plus showMessage's screen-reader echo).
		expect(box.firstChild.nodeValue).toBe('Thanks!');
		expect(
			Array.from(box.childNodes).filter(
				(node) => node.nodeType === 3 && node.nodeValue === 'Thanks!'
			)
		).toHaveLength(1);
		expect(window.location.search).toBe('');
	});

	it('hides a server-printed result the URL does not carry (a cached page)', () => {
		const form = mountForm({ message: SERVER_SUCCESS });
		init();

		const box = form.querySelector('.dsgo-form__message');
		expect(box.style.display).toBe('none');
		expect(box.textContent).toBe('');
		expect(box.hasAttribute('data-dsgo-server-message')).toBe(false);
	});
});
