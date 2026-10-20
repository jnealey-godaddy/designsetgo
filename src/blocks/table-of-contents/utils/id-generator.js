/**
 * ID generation utilities for Table of Contents block
 */

/**
 * Generate a URL-friendly ID from heading text.
 *
 * @param {string} text    - The heading text
 * @param {Set}    usedIds - Set of already used IDs to ensure uniqueness
 * @return {string} URL-safe unique ID
 */
export function generatePrettyId(text, usedIds) {
	// Convert heading text to URL-friendly slug. Unicode letters and numbers
	// survive (so non-Latin headings keep meaningful ids); accents on Latin
	// letters are folded away.
	let slug = text
		.normalize('NFD')
		// Fold accents on Latin letters only (é -> e); marks that are part of
		// other scripts (Indic vowel signs, etc.) are meaningful and stay.
		.replace(/(\p{Script=Latin})\p{M}+/gu, '$1')
		.normalize('NFC')
		.toLowerCase()
		.trim()
		// Replace apostrophes and quotes
		.replace(/['"\u2018\u2019\u201C\u201D]/g, '')
		// Replace anything that is not a letter, number or hyphen
		.replace(/[^\p{L}\p{N}-]+/gu, '-')
		// Replace multiple hyphens with single hyphen
		.replace(/-+/g, '-')
		// Remove leading/trailing hyphens
		.replace(/^-+|-+$/g, '');

	// Limit length by code point so surrogate pairs are not split, then drop a
	// trailing hyphen if the cut landed after one.
	slug = Array.from(slug).slice(0, 50).join('').replace(/-+$/, '');

	// Ensure slug is not empty
	if (!slug) {
		slug = 'section';
	}

	// An id is taken if we handed it out already OR anything on the page owns it.
	const isTaken = (id) =>
		usedIds.has(id) ||
		(typeof document !== 'undefined' &&
			document.getElementById(id) !== null);

	// Make unique if already used
	let uniqueSlug = slug;
	let counter = 1;
	while (isTaken(uniqueSlug)) {
		uniqueSlug = `${slug}-${counter}`;
		counter++;
	}

	usedIds.add(uniqueSlug);
	return uniqueSlug;
}
