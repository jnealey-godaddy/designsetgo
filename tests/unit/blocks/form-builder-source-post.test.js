/**
 * Form Builder — submissions name the page they were sent from.
 *
 * Form IDs repeat across pages (DSGo patterns ship fixed IDs), so the server
 * needs the source page to validate and email with the right copy of the
 * form. The page ID is localized as designsetgoForm.postId.
 */

function mountForm() {
	document.body.insertAdjacentHTML(
		'afterbegin',
		`<div class="dsgo-form-builder" data-form-id="contact-professional" data-ajax-submit="true">
			<form class="dsgo-form" novalidate>
				<div class="dsgo-form__fields">
					<input type="text" name="your_name" value="Pat" data-field-type="text" />
				</div>
				<input type="text" name="dsg_website" value="" />
				<input type="hidden" name="dsg_form_id" value="contact-professional" />
				<button class="dsgo-form__submit" type="submit">Send</button>
				<div class="dsgo-form__message"></div>
			</form>
		</div>`
	);
}

describe('form-builder source page', () => {
	beforeEach(() => {
		document.body.replaceChildren();
		global.designsetgoForm = {
			restUrl: '/wp-json/designsetgo/v1/form/submit',
			ajaxUrl: '/wp-admin/admin-ajax.php',
			ajaxNonce: 'nonce',
			adminPostUrl: '/wp-admin/admin-post.php',
			postId: '23',
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
		delete global.designsetgoForm;
		delete global.fetch;
	});

	it('sends the localized page ID as sourcePostId', async () => {
		mountForm();
		jest.isolateModules(() => {
			require('../../../src/blocks/form-builder/view.js');
		});
		document.dispatchEvent(new Event('dsgo-content-loaded'));

		document
			.querySelector('.dsgo-form')
			.dispatchEvent(
				new Event('submit', { bubbles: true, cancelable: true })
			);

		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(global.fetch).toHaveBeenCalled();
		const body = JSON.parse(global.fetch.mock.calls[0][1].body);
		expect(body.sourcePostId).toBe(23);
	});
});
