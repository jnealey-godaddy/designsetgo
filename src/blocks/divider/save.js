/**
 * Divider Block - Save Function
 *
 * Dynamic block rendered server-side via render.php. Non-icon styles are pure
 * CSS lines; the "icon" style embeds the SVG from the shared PHP icon library.
 * No static HTML is saved to the database, so the serialized block is a single
 * self-closing comment. Historical static markup is handled by ./deprecated.js.
 *
 * @return {null} Null because this is a dynamic block.
 */
export default function DividerSave() {
	return null;
}
