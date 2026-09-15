/**
 * The finish plugin and the Agent build sidebar only belong in the top-level
 * post editor: never inside the editor canvas iframe, the Site Editor (whose
 * "post id" is a template id string and whose post types are `wp_*`), or an
 * editor with no post at all (widgets).
 */
import { isTopWindow, isFinishableContext } from '../context';

describe('isTopWindow()', () => {
	test('true when the window is its own top', () => {
		const win = {};
		win.self = win;
		win.top = win;

		expect(isTopWindow(win)).toBe(true);
	});

	test('false inside a frame', () => {
		const top = {};
		const win = { top };
		win.self = win;

		expect(isTopWindow(win)).toBe(false);
	});

	test('false when reading the top window throws', () => {
		const win = {};
		win.self = win;
		Object.defineProperty(win, 'top', {
			get() {
				throw new Error('blocked');
			},
		});

		expect(isTopWindow(win)).toBe(false);
	});

	test('true for the Jest window itself', () => {
		expect(isTopWindow(window)).toBe(true);
	});
});

describe('isFinishableContext()', () => {
	const base = { isTop: true, postId: 12, postType: 'page' };

	test('a top-window post editor with a real post is finishable', () => {
		expect(isFinishableContext(base)).toBe(true);
		expect(isFinishableContext({ ...base, postType: 'post' })).toBe(true);
	});

	test('never inside a frame', () => {
		expect(isFinishableContext({ ...base, isTop: false })).toBe(false);
	});

	test('never without a positive integer post id', () => {
		for (const postId of [
			'twentytwentyfive//home',
			'12',
			0,
			-1,
			1.5,
			NaN,
			null,
			undefined,
		]) {
			expect(isFinishableContext({ ...base, postId })).toBe(false);
		}
	});

	test('never for wp_-prefixed post types or a missing post type', () => {
		for (const postType of [
			'wp_template',
			'wp_template_part',
			'wp_navigation',
			'wp_block',
			null,
			undefined,
			'',
		]) {
			expect(isFinishableContext({ ...base, postType })).toBe(false);
		}
	});
});
