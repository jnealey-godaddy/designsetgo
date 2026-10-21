/**
 * Query load more on swapped markup (delegated click path).
 *
 * After a filter refresh the region HTML is swapped in without live
 * Interactivity directives, so the delegated click handler reads the
 * pagination context from data-wp-context. That context must persist between
 * clicks or every click requests page 2 again.
 */
jest.mock(
	'@wordpress/interactivity',
	() => ({
		store: jest.fn(),
		getContext: jest.fn(),
		getElement: jest.fn(),
	}),
	{ virtual: true }
);

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

function mount() {
	document.body.innerHTML = `
		<div data-dsgo-query-region="qa1">
			<ul data-dsgo-query-results-role="container" data-dsgo-query-id="qa1"><li>1</li></ul>
			<div class="dsgo-query-pagination" data-dsgo-query-id="qa1" data-dsgo-pagination="loadmore"
				data-wp-context='{"queryId":"qa1","page":1,"busy":false,"restUrl":"/rest","nonce":""}'>
				<button type="button" class="dsgo-query-pagination__loadmore">More</button>
			</div>
		</div>
		<div data-dsgo-blobs-for="qa1" data-dsgo-refresh-source="src" data-dsgo-signature="sig"></div>`;
}

describe('delegated load more', () => {
	beforeAll(() => {
		require('../../src/blocks/query/view.js');
	});

	it('requests successive pages and removes the button on the last', async () => {
		mount();
		const pages = [];
		global.fetch = jest.fn((url, init) => {
			pages.push(JSON.parse(init.body).page);
			return Promise.resolve({
				ok: true,
				json: () =>
					Promise.resolve({
						html: `<ul data-dsgo-query-results-role="container" data-dsgo-query-id="qa1"><li>x${pages.length}</li></ul>`,
						totalPages: 3,
					}),
			});
		});

		const click = async () => {
			const btn = document.querySelector(
				'.dsgo-query-pagination__loadmore'
			);
			if (btn) {
				btn.click();
			}
			await flush();
			await flush();
		};

		await click();
		await click();

		expect(pages).toEqual([2, 3]);
		expect(
			document.querySelector('.dsgo-query-pagination__loadmore')
		).toBeNull();
		expect(document.querySelectorAll('li')).toHaveLength(3);
	});
});
