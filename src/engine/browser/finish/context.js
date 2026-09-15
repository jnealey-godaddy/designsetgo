/**
 * Where the agent-build editor code is allowed to run.
 *
 * `build/index.js` loads on `enqueue_block_assets`, so it runs in every
 * block-editor context: the post editor, the Site Editor, the widgets
 * editor, and again inside each editor's canvas iframe. The finish plugin
 * only makes sense in the top-level post editor with a real post open, and
 * the Agent build sidebar only in a top-level editor window. Anywhere else
 * both must do nothing at all — no REST calls, no notices.
 */

/**
 * @param {Object} win Window to check (anything with `self`/`top`).
 * @return {boolean} Whether `win` is the top-level window, not a frame.
 */
export function isTopWindow(win) {
	try {
		return win.self === win.top;
	} catch (error) {
		return false;
	}
}

/**
 * @param {Object}  context
 * @param {boolean} context.isTop    Whether this is the top-level window.
 * @param {*}       context.postId   `core/editor` `getCurrentPostId()`.
 * @param {*}       context.postType `core/editor` `getCurrentPostType()`.
 * @return {boolean} Whether a pending build may be finished here: a top
 *   window editing a real post (positive integer id) of a non-`wp_` type.
 *   The Site Editor fails both of the latter — its ids are template id
 *   strings and its types are `wp_template`/`wp_template_part`/….
 */
export function isFinishableContext({ isTop, postId, postType }) {
	return (
		isTop === true &&
		Number.isInteger(postId) &&
		postId > 0 &&
		typeof postType === 'string' &&
		postType !== '' &&
		!postType.startsWith('wp_')
	);
}
