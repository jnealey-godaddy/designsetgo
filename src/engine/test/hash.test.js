/**
 * Pure-JS SHA-256 tests. `crypto` is used only as an oracle here (this test
 * runs in Node); `hash.js` itself must stay dependency-free so it also runs
 * in the browser build of the engine.
 */
import { createHash } from 'crypto';
import { sha256Hex } from '../hash';

describe('sha256Hex', () => {
	test('matches the known digest of "abc"', () => {
		expect(sha256Hex('abc')).toBe(
			'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad'
		);
	});

	test('matches Node crypto for the empty string', () => {
		const expected = createHash('sha256').update('', 'utf8').digest('hex');
		expect(sha256Hex('')).toBe(expected);
	});

	test('matches Node crypto for a non-ASCII string', () => {
		const input = 'DesignSetGo — agent block engine — café — 日本語 🙂';
		const expected = createHash('sha256')
			.update(input, 'utf8')
			.digest('hex');

		expect(sha256Hex(input)).toBe(expected);
	});

	test('matches Node crypto for a long JSON-shaped string', () => {
		const input = JSON.stringify({
			version: 1,
			blocks: Array.from({ length: 50 }, (_, i) => ({
				name: 'core/paragraph',
				attributes: { content: `Block number ${i} — emoji 🎉` },
			})),
		});
		const expected = createHash('sha256')
			.update(input, 'utf8')
			.digest('hex');

		expect(sha256Hex(input)).toBe(expected);
	});

	test('is deterministic: identical input yields identical output', () => {
		const input = JSON.stringify({ a: 1, b: [1, 2, 3] });
		expect(sha256Hex(input)).toBe(sha256Hex(input));
	});

	test('different input yields a different digest', () => {
		expect(sha256Hex('a')).not.toBe(sha256Hex('b'));
	});
});
