/**
 * Legacy Shape Divider Snapshots (deprecation-frozen)
 *
 * The section block's deprecations (v3–v6 in ../deprecated.js) reproduce the
 * old inline-`<svg>` divider markup so WordPress can byte-match stored content
 * and silently migrate it to the current class-based rendering. That
 * reproduction must emit the EXACT geometry the old content was saved with.
 *
 * The live `SHAPE_DIVIDERS` in ./shape-dividers.js is free to evolve — e.g. the
 * drops/fan/steps/slime redesign for the see-through mask model changed their
 * paths. But the deprecations import that same library, so when the live data
 * diverges from what old content stored, `deprecation.save()` stops
 * byte-matching and the editor shows "unexpected or invalid content / Attempt
 * Recovery" for any post that used one of the changed shapes.
 *
 * This module freezes the PRE-redesign JSX for every shape whose geometry has
 * changed, decoupling the deprecations from the live library. Unchanged shapes
 * intentionally fall through to `getShapeDivider()` (their live and legacy
 * markup are identical, so freezing them would be redundant duplication).
 *
 * RULE: whenever you change a shape's geometry in `SHAPE_DIVIDERS`, first copy
 * its CURRENT (pre-change) definition here, keyed by slug, so already-published
 * content keeps migrating cleanly. Never edit an entry already in this map —
 * these are historical constants, not live data.
 *
 * @since 2.6.0
 */

import { getShapeDivider } from './shape-dividers';

/**
 * Frozen pre-redesign shape elements. Byte-faithful copies of `SHAPE_DIVIDERS`
 * as it stood before the see-through mask redesign (plugin 2.2.0 / main).
 */
export const LEGACY_SHAPE_DIVIDERS = {
	// Pre-redesign: five flat ellipses over a base rect ("oval drops pattern").
	drops: (
		<>
			<ellipse cx="100" cy="90" rx="100" ry="50" />
			<ellipse cx="350" cy="100" rx="120" ry="40" />
			<ellipse cx="580" cy="85" rx="90" ry="55" />
			<ellipse cx="800" cy="95" rx="110" ry="45" />
			<ellipse cx="1030" cy="90" rx="100" ry="50" />
			<rect x="0" y="100" width="1200" height="20" />
		</>
	),

	// Pre-redesign: six opacity-layered rays converging on a bottom-center apex.
	fan: (
		<>
			<path d="M600,120 L0,120 L600,0 Z" opacity="0.3" />
			<path d="M600,120 L200,120 L600,20 Z" opacity="0.5" />
			<path d="M600,120 L400,120 L600,40 Z" opacity="0.7" />
			<path d="M600,120 L1200,120 L600,0 Z" opacity="0.3" />
			<path d="M600,120 L1000,120 L600,20 Z" opacity="0.5" />
			<path d="M600,120 L800,120 L600,40 Z" opacity="0.7" />
		</>
	),

	// Pre-redesign: shallow rounded (quadratic) two-step bump.
	steps: (
		<path d="M0,120 L0,80 Q100,80 100,60 L400,60 Q400,40 500,40 L700,40 Q700,60 800,60 L1100,60 Q1100,80 1200,80 L1200,120 Z" />
	),

	// Pre-redesign: inverted solid-edge-at-top drips at the two edges.
	slime: (
		<path d="M0,0 L0,40 Q50,40 50,80 Q50,120 100,120 L100,60 Q100,40 150,40 L150,100 Q150,120 200,120 L1000,120 Q1050,120 1050,100 L1050,40 Q1050,40 1100,40 L1100,80 Q1100,120 1150,120 Q1200,120 1200,80 L1200,0 Z" />
	),
};

/**
 * Resolve a shape element for a deprecation's save(): the frozen legacy copy
 * when one exists, otherwise the live library (unchanged shapes).
 *
 * @param {string} shape Shape slug.
 * @return {JSX.Element|null} Shape element, or null when the slug is unknown.
 */
export function getLegacyShapeDivider(shape) {
	if (Object.prototype.hasOwnProperty.call(LEGACY_SHAPE_DIVIDERS, shape)) {
		return LEGACY_SHAPE_DIVIDERS[shape];
	}
	return getShapeDivider(shape);
}
