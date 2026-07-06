/**
 * Pill Block - Save Component
 *
 * Dynamic block rendered server-side via render.php. No static HTML is saved to
 * the database, so the serialized block is a single self-closing comment
 * (`<!-- wp:designsetgo/pill {"content":"…"} /-->`) with no baked-in alignment
 * or font-size classes. Historical static markup is handled by ./deprecated.js.
 *
 * @return {null} Null because this is a dynamic block.
 */
export default function PillSave() {
	return null;
}
