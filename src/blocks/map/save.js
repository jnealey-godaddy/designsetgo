/**
 * Map Block - Save Component
 *
 * Dynamic block rendered server-side via render.php. No static HTML is saved, so
 * the map's data-* config (including the marker colour, which now resolves from
 * the block attribute → theme.json kit setting → default) can change without
 * tripping block validation. Historical static markup is migrated by
 * ./deprecated.js.
 *
 * @return {null} Null because this is a dynamic block.
 */
export default function Save() {
	return null;
}
