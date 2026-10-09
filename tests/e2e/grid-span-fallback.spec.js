/**
 * CSS-only Grid span fallback. No view script runs in these fixtures, so the
 * stylesheet must constrain its own default columns without leaking into
 * nested Grids or overriding an authored responsive template.
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const styles = [
	'build/blocks/grid/style-index.css',
	'build/extensions/grid-span/style.css',
]
	.map((file) =>
		fs.readFileSync(path.resolve(__dirname, '../..', file), 'utf8')
	)
	.join('\n');

async function span(page, selector) {
	return page
		.locator(selector)
		.evaluate((element) => getComputedStyle(element).gridColumn);
}

test('default tablet fallback constrains its own children and preserves nested columns', async ({
	page,
}) => {
	await page.setViewportSize({ width: 900, height: 800 });
	await page.setContent(`<style>${styles}</style>
		<div class="dsgo-grid dsgo-grid-cols-tablet-2"><div class="dsgo-grid__inner">
			<p id="outer" style="grid-column:span 3">Outer</p>
			<div class="dsgo-grid dsgo-grid-cols-tablet-3"><div class="dsgo-grid__inner">
				<p id="nested" style="grid-column:span 3">Nested</p>
			</div></div>
		</div></div>`);
	expect(await span(page, '#outer')).toBe('span 2');
	expect(await span(page, '#nested')).toBe('span 3');
});

test('templates retain spans at their own breakpoint without another fallback overriding them', async ({
	page,
}) => {
	await page.setContent(`<style>${styles}</style>
		<div class="dsgo-grid dsgo-grid-cols-tablet-1 dsgo-grid-cols-mobile-1"><div class="dsgo-grid__inner" style="--dsgo-grid-columns-tablet:1fr 1fr;--dsgo-grid-columns-mobile:1fr 1fr">
			<p id="template" style="grid-column:span 2">Template</p>
		</div></div>`);
	for (const width of [900, 600]) {
		await page.setViewportSize({ width, height: 800 });
		expect(await span(page, '#template')).toBe('span 2');
	}
});

test('mobile templates are not constrained by an absent tablet template', async ({
	page,
}) => {
	await page.setViewportSize({ width: 600, height: 800 });
	await page.setContent(`<style>${styles}</style>
		<div class="dsgo-grid dsgo-grid-cols-tablet-1 dsgo-grid-cols-mobile-1"><div class="dsgo-grid__inner" style="--dsgo-grid-columns-mobile:1fr 1fr">
			<p id="mobile" style="grid-column:span 2">Mobile</p>
		</div></div>`);
	expect(await span(page, '#mobile')).toBe('span 2');
});

test('Align Rows takes priority over default fallback spans', async ({
	page,
}) => {
	await page.setViewportSize({ width: 900, height: 800 });
	await page.setContent(`<style>${styles}</style>
		<div class="dsgo-grid dsgo-grid--match-rows dsgo-grid-cols-tablet-2"><div class="dsgo-grid__inner dsgo-grid__inner--rows-matched" style="--dsgo-row-count:2">
			<div id="matched" class="wp-block-group" style="grid-column:span 3"><p>A</p><p>B</p></div>
		</div></div>`);
	expect(await span(page, '#matched')).toBe('auto');
});

test('count fallbacks preserve valid one-through-twelve spans without JavaScript', async ({
	page,
}) => {
	const markup = [1, 2, 3, 12]
		.map(
			(count) =>
				`<div class="dsgo-grid dsgo-grid-cols-tablet-${count} dsgo-grid-cols-mobile-${count}"><div class="dsgo-grid__inner"><p id="count-${count}" style="grid-column:span 12">Count</p></div></div>`
		)
		.join('');
	await page.setContent(`<style>${styles}</style>${markup}`);
	for (const width of [900, 600]) {
		await page.setViewportSize({ width, height: 800 });
		for (const count of [1, 2, 3, 12]) {
			expect(await span(page, `#count-${count}`)).toBe(`span ${count}`);
		}
	}
});
