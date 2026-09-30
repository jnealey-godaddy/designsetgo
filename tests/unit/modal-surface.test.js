/**
 * Compiled modal surface contract; rendered colors are also checked in Chrome.
 * Existing authored dark text needs the historical white fallback even when
 * the theme's page background is dark. Both canvases must use the same token.
 */
const path = require('path');
const sass = require('sass');
const postcss = require('postcss');

function declarations(file, selector, property) {
	const { css } = sass.compile(
		path.join(__dirname, '../../src/blocks/modal', file),
		{ logger: sass.Logger.silent }
	);
	const backgrounds = [];
	postcss.parse(css).walkRules((rule) => {
		if (rule.selector.endsWith(selector)) {
			rule.walkDecls(property, (decl) => backgrounds.push(decl));
		}
	});
	return backgrounds;
}

describe('modal surface compatibility', () => {
	const surfaces = ['style.scss', 'editor.scss'].map((file) =>
		declarations(file, '.dsgo-modal__content', 'background')
	);

	it.each(['frontend', 'editor'])(
		'%s keeps the historical surface independent of the page palette',
		(canvas) => {
			const [background] = surfaces[canvas === 'frontend' ? 0 : 1];
			expect(background.value).not.toContain('--wp--preset--color--base');
			expect(background.value).toMatch(/,\s*#fff\)$/);
			// Explicit per-instance background styles must continue to win.
			expect(background.important).toBeFalsy();
		}
	);

	it('keeps the default close icon visible on the historical white surface', () => {
		const [color] = declarations(
			'style.scss',
			'.dsgo-modal__close',
			'color'
		);
		expect(color.value).toBe('#000');
		expect(color.important).toBeFalsy();
	});

	it('uses the same opt-in theme surface in both canvases', () => {
		const [frontend] = surfaces[0];
		const [editor] = surfaces[1];
		expect(editor.value).toBe(frontend.value);
		expect(frontend.value).toContain(
			'--wp--custom--designsetgo--modal--background'
		);
	});
});
