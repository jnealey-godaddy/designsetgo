/**
 * Generates the PHP shape-divider list from the JavaScript definitions.
 *
 * `Configure_Shape_Divider::VALID_SHAPES` used to be a hand-typed copy of the
 * shapes in src/blocks/section/utils/shape-dividers.js. The two agreed, and
 * nothing made them keep agreeing: adding a shape to the library left the
 * ability rejecting it as out-of-enum, which is valid input refused before the
 * callback ever runs.
 *
 * The generated file is COMMITTED rather than read from build/ at runtime. A
 * missing or stale build artifact would silently empty the schema's enum, and
 * an ability whose enum is empty accepts nothing — the same silent-inertness
 * failure CLAUDE.md documents for uncopied query PHP. CI regenerates and runs
 * `git diff --exit-code`, so a stale committed file fails the build instead.
 *
 * Usage: npm run generate:shape-enum
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const SOURCE = path.join(ROOT, 'src/blocks/section/utils/shape-dividers.js');
const TARGET = path.join(
	ROOT,
	'includes/abilities/generated/shape-dividers.php'
);

/**
 * Read the shape names from the SHAPE_DIVIDERS object literal.
 *
 * The file is JSX, so it cannot be require()d. The keys are matched at one
 * level of indentation inside the object, which is what distinguishes a shape
 * name from the SVG attributes nested under it.
 *
 * @return {string[]} Shape names, in source order.
 */
function readShapeNames() {
	const source = fs.readFileSync(SOURCE, 'utf8');
	const start = source.indexOf('export const SHAPE_DIVIDERS');

	if (start === -1) {
		throw new Error(`SHAPE_DIVIDERS not found in ${SOURCE}`);
	}

	const names = [
		...source.slice(start).matchAll(/^\t'?([a-z][a-z0-9-]*)'?:\s/gm),
	].map((match) => match[1]);

	if (names.length === 0) {
		throw new Error(
			'No shape names matched — has the object literal changed shape?'
		);
	}

	return names;
}

const shapes = readShapeNames();

const php = `<?php
/**
 * Shape divider names.
 *
 * GENERATED FILE — DO NOT EDIT.
 *
 * Regenerate with: npm run generate:shape-enum
 * Source of truth: src/blocks/section/utils/shape-dividers.js
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

defined( 'ABSPATH' ) || exit;

return array(
${shapes.map((name) => `\t'${name}',`).join('\n')}
);
`;

fs.mkdirSync(path.dirname(TARGET), { recursive: true });
fs.writeFileSync(TARGET, php);

// eslint-disable-next-line no-console
console.log(`Wrote ${shapes.length} shapes to ${path.relative(ROOT, TARGET)}`);
