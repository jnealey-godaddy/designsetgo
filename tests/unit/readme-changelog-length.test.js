/**
 * readme.txt changelog stays within WordPress.org's limit.
 *
 * The plugin directory's readme parser truncates the Changelog section at
 * 5,000 words, and the import warns only the plugin committers. Older versions
 * are summarized under "Earlier releases", which links the full history.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const MAX_CHANGELOG_WORDS = 5000;

/**
 * Return the body of a `== Section ==` block in a WordPress readme.
 *
 * @param {string} readme Readme contents.
 * @param {string} name   Section name.
 * @return {string} Section body, or an empty string when absent.
 */
function readmeSection(readme, name) {
	const lines = readme.split('\n');
	const start = lines.findIndex((line) => line.trim() === `== ${name} ==`);
	if (start === -1) {
		return '';
	}
	const end = lines.findIndex(
		(line, index) => index > start && /^==\s.*\s==\s*$/.test(line.trim())
	);

	return lines.slice(start + 1, end === -1 ? undefined : end).join('\n');
}

/**
 * Count words the way wp_trim_words() splits them: runs of whitespace.
 *
 * @param {string} text Text.
 * @return {number} Word count.
 */
function countWords(text) {
	return text.split(/[\n\r\t ]+/).filter(Boolean).length;
}

describe('readme.txt changelog', () => {
	const readme = fs.readFileSync(path.join(ROOT, 'readme.txt'), 'utf8');
	const changelog = readmeSection(readme, 'Changelog');

	it('has a Changelog section', () => {
		expect(changelog.trim()).not.toBe('');
	});

	it(`stays within WordPress.org's ${MAX_CHANGELOG_WORDS}-word limit`, () => {
		const words = countWords(changelog);
		if (words > MAX_CHANGELOG_WORDS) {
			throw new Error(
				`readme.txt Changelog is ${words} words; WordPress.org truncates it at ${MAX_CHANGELOG_WORDS}. ` +
					'Replace the oldest version entries with a one-line highlight under "Earlier releases".'
			);
		}
	});
});
