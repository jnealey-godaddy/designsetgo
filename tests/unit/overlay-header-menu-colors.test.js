/**
 * Overlay menu CSS contract. Like overlay-header-clearance.test.js, read source
 * so these guards run in CI without a build. Browser checks cover the cascade;
 * jsdom cannot verify the rendered colors of nested navigation menus.
 */
const fs = require('fs');
const path = require('path');

const source = fs
	.readFileSync(
		path.join(__dirname, '../../src/styles/utilities/_sticky-header.scss'),
		'utf8'
	)
	.replace(/\/\*[\s\S]*?\*\//g, '')
	.replace(/(^|\s)\/\/.*$/gm, '');

const MENU_SELECTOR =
	'.wp-block-navigation :is(.wp-block-navigation__submenu-container, .wp-block-navigation__responsive-container.is-menu-open)';
const AUTHORED_GUARD = '&:not(.has-background):not(.has-text-color)';

/**
 * Extract one nested SCSS rule without treating its first closing brace as its end.
 *
 * @param {string} css      Stylesheet or parent rule body.
 * @param {string} selector Exact selector to find.
 * @return {{body: string, rest: string}} Rule body and source without the rule.
 */
function extractRule(css, selector) {
	const start = css.indexOf(`${selector} {`);
	if (start < 0) {
		throw new Error(`Missing rule: ${selector}`);
	}
	const open = css.indexOf('{', start);
	let depth = 1;
	let end = open + 1;
	for (; end < css.length && depth; end++) {
		if (css[end] === '{') {
			depth++;
		} else if (css[end] === '}') {
			depth--;
		}
	}
	if (depth) {
		throw new Error(`Unclosed rule: ${selector}`);
	}
	return {
		body: css.slice(open + 1, end - 1),
		rest: css.slice(0, start) + css.slice(end),
	};
}

function menuRules() {
	return extractRule(source, MENU_SELECTOR).body;
}

describe('overlay menu colors', () => {
	it('keeps the menu rules inside the frontend-only overlay header', () => {
		const overlay = extractRule(
			source,
			'body:not(.block-editor-page).dsgo-page-overlay-header header.wp-block-template-part'
		).body;

		expect(overlay).toContain('position: fixed');
		expect(overlay).toContain(MENU_SELECTOR);
		expect(menuRules()).toContain('.wp-block-navigation-item__label');
	});

	it('applies both automatic colors only when neither color is authored', () => {
		const automatic = extractRule(menuRules(), AUTHORED_GUARD);

		expect(automatic.body).toMatch(
			/background:\s*var\(--dsgo-overlay-menu-bg, #fff\)\s*!important/
		);
		expect(automatic.body).toMatch(
			/color:\s*var\(--dsgo-overlay-menu-fg, #000\)\s*!important/
		);
		expect(automatic.rest).not.toContain('--dsgo-overlay-menu-bg');
		expect(automatic.rest).not.toContain('--dsgo-overlay-menu-fg');
	});

	it('inherits menu text for authored menus as well as automatic menus', () => {
		const automatic = extractRule(menuRules(), AUTHORED_GUARD);
		const descendants = extractRule(
			automatic.rest,
			'.wp-block-navigation__responsive-container-close *'
		).body;

		expect(automatic.body).not.toContain('{');
		expect(descendants.trim()).toBe('color: inherit !important;');
		for (const selector of [
			'.wp-block-navigation__responsive-close',
			'.wp-block-navigation__responsive-dialog',
			'.wp-block-navigation__responsive-container-content',
			'.wp-block-navigation__container',
			'.wp-block-navigation-item',
			'.wp-block-navigation-item__content',
			'.wp-block-navigation-item__label',
			'.wp-block-navigation__submenu-icon',
			'.wp-block-navigation__submenu-icon *',
			'.wp-block-navigation__responsive-container-close',
		]) {
			expect(automatic.rest).toContain(`${selector},`);
		}
	});

	it('keeps control icons on currentColor without filling outline SVGs', () => {
		const icons = extractRule(
			menuRules(),
			':is(.wp-block-navigation__submenu-icon, .wp-block-navigation__responsive-container-close) svg'
		).body;

		expect(icons).toMatch(/fill:\s*currentcolor/);
		expect(extractRule(icons, '&[fill="none"]').body.trim()).toBe(
			'fill: none;'
		);
	});
});
