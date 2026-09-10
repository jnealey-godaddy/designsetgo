/**
 * Form Builder — Cloudflare Turnstile widget options.
 *
 * turnstile.render() injects a hidden cf-turnstile-response input into its
 * container unless told not to, and the container sits inside the <form>, so
 * the token would be submitted as if it were one of the visitor's fields. The
 * widget's callback already copies the token into dsg_turnstile_token, which
 * is the only place the server reads it from.
 */

function mountForm() {
	document.body.insertAdjacentHTML(
		'afterbegin',
		`<div class="dsgo-form-builder" data-form-id="turnstile-form" data-dsgo-turnstile="true" data-ajax-submit="true">
			<form class="dsgo-form">
				<div class="dsgo-turnstile-widget" data-dsgo-turnstile-container="true"></div>
				<button class="dsgo-form__submit" type="submit">Send</button>
				<div class="dsgo-form__message"></div>
			</form>
		</div>`
	);
}

describe('form-builder Turnstile widget', () => {
	beforeEach(() => {
		document.body.replaceChildren();
		document.head
			.querySelectorAll('script[src*="challenges.cloudflare.com"]')
			.forEach((script) => script.remove());
		global.dsgoIntegrations = {
			turnstileSiteKey: '1x00000000000000000000AA',
		};
		global.designsetgoForm = {
			restUrl: '/wp-json/designsetgo/v1/form/submit',
			ajaxUrl: '/wp-admin/admin-ajax.php',
			ajaxNonce: 'nonce',
			adminPostUrl: '/wp-admin/admin-post.php',
		};
		window.turnstile = undefined;
	});

	afterEach(() => {
		delete global.dsgoIntegrations;
		delete global.designsetgoForm;
		delete window.turnstile;
	});

	it('renders the widget without a cf-turnstile-response form input', async () => {
		mountForm();
		jest.isolateModules(() => {
			require('../../../src/blocks/form-builder/view.js');
		});
		document.dispatchEvent(new Event('dsgo-content-loaded'));

		const script = document.head.querySelector(
			'script[src*="challenges.cloudflare.com/turnstile"]'
		);
		expect(script).not.toBeNull();

		const render = jest.fn(() => 'widget-1');
		window.turnstile = { render, reset: jest.fn(), remove: jest.fn() };
		script.onload();
		await Promise.resolve();
		await Promise.resolve();

		expect(render).toHaveBeenCalledTimes(1);
		const [container, options] = render.mock.calls[0];
		expect(container).toBe(
			document.querySelector('[data-dsgo-turnstile-container]')
		);
		expect(options['response-field']).toBe(false);
		expect(typeof options.callback).toBe('function');
	});
});
