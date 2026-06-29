/**
 * Pattern helpers for DesignSetGo e2e tests.
 *
 * listDesignSetGoPatterns() — synchronous, filesystem-derived list of every
 * registered pattern the happy-path sweep should insert. Mirrors how the PHP
 * loader (includes/patterns/class-loader.php) derives slugs: one level of
 * category directories under patterns/, each holding <name>.php files, with
 * slug `designsetgo/<category>/<name>`. New patterns are covered automatically.
 *
 * Programmatic insertion itself lives in helpers/wordpress.js
 * (insertPatternBySlug), which reads the resolved content straight from the
 * registry — so registry truth is the insertion gate.
 */

const fs = require('fs');
const path = require('path');

// tests/e2e/helpers -> repo root -> patterns
const PATTERNS_DIR = path.join(__dirname, '..', '..', '..', 'patterns');

// Mirror WP's sanitize_key(): lowercase, keep a-z0-9 _ - only. Pattern files
// are already kebab-case, but this keeps the derived slug byte-identical to the
// one the PHP loader registers.
function sanitizeKey(value) {
	return value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
}

// Pull "Title:" out of the pattern file's header doc-block for a readable test
// name. Falls back to the slug when absent. Scan only the header doc-block (up
// to its closing `*/`) rather than an arbitrary byte cap, so a long header that
// pushes the Title line further down still resolves.
function readTitle(file) {
	const content = fs.readFileSync(file, 'utf8');
	const headerEnd = content.indexOf('*/');
	const head = headerEnd === -1 ? content : content.slice(0, headerEnd);
	const match = head.match(/^\s*\*\s*Title:\s*(.+?)\s*$/m);
	return match ? match[1] : null;
}

/**
 * List every registered DesignSetGo pattern from the patterns/ directory.
 *
 * @return {Array<{slug: string, title: string}>} Sorted by slug.
 */
function listDesignSetGoPatterns() {
	const patterns = [];
	for (const category of fs.readdirSync(PATTERNS_DIR, {
		withFileTypes: true,
	})) {
		if (!category.isDirectory()) {
			continue;
		}
		const categoryDir = path.join(PATTERNS_DIR, category.name);
		for (const entry of fs.readdirSync(categoryDir, {
			withFileTypes: true,
		})) {
			if (!entry.isFile() || !entry.name.endsWith('.php')) {
				continue;
			}
			const base = entry.name.slice(0, -'.php'.length);
			const slug = `designsetgo/${sanitizeKey(
				category.name
			)}/${sanitizeKey(base)}`;
			const file = path.join(categoryDir, entry.name);
			patterns.push({ slug, title: readTitle(file) || slug });
		}
	}
	return patterns.sort((a, b) => a.slug.localeCompare(b.slug));
}

module.exports = {
	listDesignSetGoPatterns,
};
