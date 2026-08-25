/**
 * Every focusable control must keep a visible focus indicator.
 *
 * This exists because two blocks had shipped `&:focus-visible { outline: none; }`
 * with nothing in its place — the Slider's arrows and dots, and every Tabs tab
 * plus its mobile dropdown. A keyboard user driving those controls got no
 * indication at all of where they were, which is a WCAG 2.4.7 (AA) failure on
 * every site running the plugin.
 *
 * Suppressing the ring for POINTER users is fine and deliberate, so bare
 * `:focus { outline: none }` is allowed — as long as a `:focus-visible` rule
 * nearby puts an indicator back. What this test forbids is killing the
 * indicator on `:focus-visible` itself, which is the keyboard path.
 */
const fs = require('fs');
const path = require('path');

const SRC = path.join(__dirname, '../../src');

/**
 * Every .scss file under src/.
 *
 * @param {string} dir Directory to walk.
 * @return {string[]} Absolute paths.
 */
function scssFiles(dir) {
	return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
		const full = path.join(dir, entry.name);
		if (entry.isDirectory()) {
			return scssFiles(full);
		}
		return entry.name.endsWith('.scss') ? [full] : [];
	});
}

/**
 * Find :focus-visible rules that turn the outline off and put nothing back.
 *
 * Reads the whole declaration block, not just the lines after the
 * `outline: none` — query-filter writes the replacement `box-shadow` first,
 * and a one-directional scan reports that as a failure when it is correct.
 *
 * @param {string} source SCSS source.
 * @return {Array<{line: number, selector: string}>} Offending rules.
 */
function findUnreplacedFocusVisible(source) {
	const lines = source.split('\n');
	const findings = [];

	lines.forEach((line, index) => {
		if (!line.includes('{') || !line.includes(':focus-visible')) {
			return;
		}

		// Selectors can be split across lines (`&:focus,\n&:focus-visible {`),
		// so walk back for anything that is part of this selector list.
		const selector = [line.trim()];
		for (let i = index - 1; i >= 0; i--) {
			const candidate = lines[i].trim();
			if (candidate === '' || candidate.startsWith('//')) {
				continue;
			}
			if (!candidate.endsWith(',')) {
				break;
			}
			selector.unshift(candidate);
		}

		// Collect the block by brace depth.
		let depth = 0;
		const body = [];
		for (let i = index; i < lines.length; i++) {
			depth += (lines[i].match(/\{/g) || []).length;
			depth -= (lines[i].match(/\}/g) || []).length;
			body.push(lines[i]);
			if (depth <= 0) {
				break;
			}
		}
		const block = body.join('\n');

		if (!/outline:\s*none/.test(block)) {
			return;
		}
		// Strip the suppression before looking for a replacement. A lookahead
		// (`outline:\s*(?!none)`) cannot do this job: `\s*` backtracks to zero
		// width and the assertion then passes on the space, so every
		// `outline: none` reads as its own replacement.
		const remaining = block.replace(/outline:\s*none\s*;?/g, '');
		if (
			/box-shadow|border-color|@include\s+focus-ring|outline:/.test(
				remaining
			)
		) {
			return;
		}

		findings.push({ line: index + 1, selector: selector.join(' ') });
	});

	return findings;
}

describe('focus-visible indicators', () => {
	const files = scssFiles(SRC);

	it('finds stylesheets to check', () => {
		expect(files.length).toBeGreaterThan(20);
	});

	it.each(files.map((f) => [path.relative(SRC, f), f]))(
		'%s does not remove the keyboard focus indicator',
		(_relative, file) => {
			const findings = findUnreplacedFocusVisible(
				fs.readFileSync(file, 'utf8')
			);
			expect(findings).toEqual([]);
		}
	);
});

describe('findUnreplacedFocusVisible', () => {
	it('flags a :focus-visible rule that only removes the outline', () => {
		const scss = `.thing {\n\t&:focus-visible {\n\t\toutline: none;\n\t}\n}`;
		expect(findUnreplacedFocusVisible(scss)).toHaveLength(1);
	});

	it('allows :focus-visible that swaps in another indicator', () => {
		const scss = `.thing {\n\t&:focus-visible {\n\t\toutline: none;\n\t\tbox-shadow: 0 0 0 3px #000;\n\t}\n}`;
		expect(findUnreplacedFocusVisible(scss)).toEqual([]);
	});

	it('allows a bare :focus reset, which only affects pointer users', () => {
		const scss = `.thing {\n\t&:focus {\n\t\toutline: none;\n\t}\n}`;
		expect(findUnreplacedFocusVisible(scss)).toEqual([]);
	});
});
