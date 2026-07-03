/**
 * Icon Block - Save Component
 *
 * Dynamic block rendered server-side via render.php (the SVG comes from the
 * shared PHP icon library, includes/data/icon-svg-library.php). No static HTML
 * is saved to the database, so the serialized block is a single self-closing
 * comment. Historical static markup is handled by ./deprecated.js.
 *
 * @return {null} Null because this is a dynamic block.
 */
export default function save() {
	return null;
}
