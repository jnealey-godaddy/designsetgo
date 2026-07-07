import {
	getPatternBackground,
	PATTERN_IDS,
} from '../../../../src/extensions/svg-patterns/patterns';

test('returns a background object for a known pattern id', () => {
	const bg = getPatternBackground(PATTERN_IDS[0], '#123456', 0.4, 1);
	expect(bg).toMatchObject({
		backgroundImage: expect.stringContaining('data:image/svg+xml'),
		backgroundSize: expect.any(String),
	});
});

test('returns null for an unknown pattern id', () => {
	expect(
		getPatternBackground('not-a-real-pattern', '#000', 0.4, 1)
	).toBeNull();
});

test('returns null (not a crash) for Object.prototype property names', () => {
	// A truthy PATTERNS[id] lookup would return a function here and throw in
	// the width/height/paths destructure; the own-property guard prevents it.
	[
		'constructor',
		'toString',
		'hasOwnProperty',
		'valueOf',
		'__proto__',
	].forEach((name) => {
		expect(getPatternBackground(name, '#000', 0.4, 1)).toBeNull();
	});
});
