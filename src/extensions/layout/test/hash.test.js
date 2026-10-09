import { layoutHash } from '../hash';

describe('canonical ASCII layout digest', () => {
	it.each([
		['', 'e3b0c44298fc1c149afbf4c8996fb924'],
		['abc', 'ba7816bf8f01cfea414140de5dae2223'],
		[
			'abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq',
			'248d6a61d20638b8e5c026930c3e6039',
		],
	])('matches SHA-256 known vector %s', (input, expected) => {
		expect(layoutHash(input)).toBe(expected);
	});
});
