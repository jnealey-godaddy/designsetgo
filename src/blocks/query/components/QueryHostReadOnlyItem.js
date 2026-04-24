/**
 * QueryHostReadOnlyItem — injects a single pre-rendered item's HTML into a
 * plain container so the slider / scroll-slides editor preview can show
 * items 1..N as true-to-frontend markup.
 *
 * Item 0 is owned by the parent block's useInnerBlocksProps (the editable
 * template). Items 1..N come from /designsetgo/v1/query/render and land here.
 *
 * Content is produced by the plugin's own render pipeline — the same markup
 * served to frontend visitors, already passed through wp_kses_post / block
 * rendering. The REST endpoint requires edit_posts capability, so only
 * trusted editors can influence this output.
 *
 * @since 2.6.0
 */
import { useEffect, useRef } from '@wordpress/element';
import { Spinner, Placeholder } from '@wordpress/components';

/**
 * @param {Object}      props
 * @param {string|null} props.html        Rendered inner HTML for this item.
 * @param {boolean}     props.loading     True while the server render is in flight.
 * @param {string}      [props.className] Optional wrapping className.
 */
export default function QueryHostReadOnlyItem({ html, loading, className }) {
	const hostRef = useRef(null);

	useEffect(() => {
		const node = hostRef.current;
		if (!node) {
			return;
		}
		while (node.firstChild) {
			node.removeChild(node.firstChild);
		}
		if (typeof html !== 'string') {
			return;
		}
		const range = node.ownerDocument.createRange();
		range.selectNodeContents(node);
		const fragment = range.createContextualFragment(html);
		node.appendChild(fragment);
	}, [html]);

	if (typeof html === 'string') {
		return (
			<div
				ref={hostRef}
				className={className}
				aria-hidden="true"
				contentEditable={false}
			/>
		);
	}
	if (loading) {
		return (
			<Placeholder
				className={`${className || ''} dsgo-query__editor-placeholder`}
			>
				<Spinner />
			</Placeholder>
		);
	}
	return null;
}
