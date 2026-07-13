/**
 * getDeprecatedBlockHTML() — the isEligible third-argument contract.
 *
 * This helper is the single place that knows what WordPress actually passes to a
 * deprecation's isEligible(). Pinning it here means a future change to that
 * contract fails one test rather than silently neutering all 71 guards, which is
 * exactly the failure mode it was extracted to prevent.
 */
import { getDeprecatedBlockHTML } from '../deprecated-block-html';

describe('getDeprecatedBlockHTML', () => {
	it('reads blockNode.innerHTML — what WordPress actually passes', () => {
		expect(
			getDeprecatedBlockHTML({
				blockNode: { innerHTML: '<div class="old"></div>' },
			})
		).toBe('<div class="old"></div>');
	});

	it('falls back to block.originalContent', () => {
		expect(
			getDeprecatedBlockHTML({
				block: { originalContent: '<p class="old"></p>' },
			})
		).toBe('<p class="old"></p>');
	});

	it('prefers blockNode.innerHTML over block.originalContent', () => {
		expect(
			getDeprecatedBlockHTML({
				blockNode: { innerHTML: 'from-blockNode' },
				block: { originalContent: 'from-block' },
			})
		).toBe('from-blockNode');
	});

	it.each([
		['no argument', undefined],
		['an empty object', {}],
		[
			'a legacy { innerHTML } shape',
			{ innerHTML: '<div class="old"></div>' },
		],
	])('returns an empty string for %s', (_label, extra) => {
		// The `{ innerHTML }` case is the bug this helper exists to prevent: that
		// key does not exist on WordPress's third argument, so a guard reading it
		// must get '' (falsy, and .includes() is always false) rather than throw or
		// silently match.
		expect(getDeprecatedBlockHTML(extra)).toBe('');
	});

	it('never returns undefined, so callers can call .includes() unguarded', () => {
		expect(() =>
			getDeprecatedBlockHTML().includes('anything')
		).not.toThrow();
		expect(getDeprecatedBlockHTML().includes('anything')).toBe(false);
	});
});
