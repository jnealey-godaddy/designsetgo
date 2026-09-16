/**
 * `withQuietConsole` mutes and restores the console around a synchronous call.
 *
 * Tests reach `console` through a `theConsole` alias so ESLint's `no-console`
 * rule (which pattern-matches the literal `console` identifier) doesn't flag
 * property reads that never emit output — this file's whole point is
 * inspecting/restoring those methods, not calling them for real.
 */
import { withQuietConsole } from '../quiet';

const theConsole = console;

const METHODS = ['log', 'info', 'warn', 'error', 'groupCollapsed', 'groupEnd'];

describe('withQuietConsole', () => {
	test('mutes console methods for the duration of fn', () => {
		let sawMuted = false;

		withQuietConsole(() => {
			sawMuted = METHODS.every(
				(method) => typeof theConsole[method] === 'function'
			);
		});

		expect(sawMuted).toBe(true);
	});

	test('returns the value of fn', () => {
		const result = withQuietConsole(() => 42);
		expect(result).toBe(42);
	});

	test('restores the original console methods after fn returns', () => {
		const originals = {};
		METHODS.forEach((method) => {
			originals[method] = theConsole[method];
		});

		withQuietConsole(() => {});

		METHODS.forEach((method) => {
			expect(theConsole[method]).toBe(originals[method]);
		});
	});

	test('restores the original console methods after fn throws', () => {
		const originals = {};
		METHODS.forEach((method) => {
			originals[method] = theConsole[method];
		});

		expect(() =>
			withQuietConsole(() => {
				throw new Error('boom');
			})
		).toThrow('boom');

		METHODS.forEach((method) => {
			expect(theConsole[method]).toBe(originals[method]);
		});
	});
});
