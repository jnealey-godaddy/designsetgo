/**
 * Task 13: per-block agent guidance.
 *
 * Every `src/blocks/{block}/agent.json` must (a) match the shape documented
 * in `src/engine/guidance-schema.json` (enforced here with a small
 * hand-rolled validator — no schema-validation dependency), and (b) every
 * example tree it ships must assemble to valid markup and lint completely
 * clean (zero errors, zero warnings) against the Twenty Twenty-Five design
 * context fixture. That's what makes the guidance trustworthy: an agent
 * that copies an example gets known-good output.
 */
import fs from 'fs';
import path from 'path';
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { createEngine } from '../../../src/engine';

const BLOCKS_DIR = path.resolve(__dirname, '../../../src/blocks');
const DESIGN_CONTEXT = JSON.parse(
	fs.readFileSync(
		path.resolve(__dirname, '../__fixtures__/design-context-tt5.json'),
		'utf8'
	)
);

/** Every block that ships hand-written agent guidance for this task. */
const GUIDED_BLOCKS = [
	'section',
	'row',
	'grid',
	'card',
	'icon-button',
	'accordion',
	'tabs',
];

/**
 * Validate a parsed `agent.json` payload against the documented shape:
 * `{ whenToUse: string, avoid: string[], examples: { title, tree }[] }`,
 * no other top-level keys.
 *
 * @param {unknown} guidance Parsed agent.json content.
 * @return {string[]} Human-readable problems; empty when valid.
 */
function validateGuidance(guidance) {
	const problems = [];

	if (
		typeof guidance !== 'object' ||
		guidance === null ||
		Array.isArray(guidance)
	) {
		return ['guidance must be a plain object'];
	}

	const allowedKeys = new Set(['whenToUse', 'avoid', 'examples']);
	for (const key of Object.keys(guidance)) {
		if (!allowedKeys.has(key)) {
			problems.push(`unexpected top-level key "${key}"`);
		}
	}

	if (typeof guidance.whenToUse !== 'string' || guidance.whenToUse === '') {
		problems.push('whenToUse must be a non-empty string');
	}

	if (!Array.isArray(guidance.avoid) || guidance.avoid.length === 0) {
		problems.push('avoid must be a non-empty array');
	} else {
		guidance.avoid.forEach((entry, index) => {
			if (typeof entry !== 'string' || entry === '') {
				problems.push(`avoid[${index}] must be a non-empty string`);
			}
		});
	}

	if (!Array.isArray(guidance.examples) || guidance.examples.length === 0) {
		problems.push('examples must be a non-empty array');
	} else {
		guidance.examples.forEach((example, index) => {
			if (
				typeof example !== 'object' ||
				example === null ||
				Array.isArray(example)
			) {
				problems.push(`examples[${index}] must be a plain object`);
				return;
			}
			const exampleKeys = new Set(['title', 'tree']);
			for (const key of Object.keys(example)) {
				if (!exampleKeys.has(key)) {
					problems.push(
						`examples[${index}] has unexpected key "${key}"`
					);
				}
			}
			if (typeof example.title !== 'string' || example.title === '') {
				problems.push(
					`examples[${index}].title must be a non-empty string`
				);
			}
			if (
				typeof example.tree !== 'object' ||
				example.tree === null ||
				Array.isArray(example.tree)
			) {
				problems.push(`examples[${index}].tree must be an object`);
			}
		});
	}

	return problems;
}

describe('guidance schema validator', () => {
	it('accepts a minimal valid payload', () => {
		expect(
			validateGuidance({
				whenToUse: 'Use this for X.',
				avoid: ['Do not do Y.'],
				examples: [
					{ title: 'Basic', tree: { version: 1, blocks: [] } },
				],
			})
		).toEqual([]);
	});

	it('rejects an empty whenToUse', () => {
		expect(
			validateGuidance({
				whenToUse: '',
				avoid: ['x'],
				examples: [{ title: 'A', tree: {} }],
			})
		).toContain('whenToUse must be a non-empty string');
	});

	it('rejects an empty avoid array', () => {
		expect(
			validateGuidance({
				whenToUse: 'x',
				avoid: [],
				examples: [{ title: 'A', tree: {} }],
			})
		).toContain('avoid must be a non-empty array');
	});

	it('rejects a blank entry in avoid', () => {
		expect(
			validateGuidance({
				whenToUse: 'x',
				avoid: [''],
				examples: [{ title: 'A', tree: {} }],
			})
		).toContain('avoid[0] must be a non-empty string');
	});

	it('rejects an empty examples array', () => {
		expect(
			validateGuidance({ whenToUse: 'x', avoid: ['y'], examples: [] })
		).toContain('examples must be a non-empty array');
	});

	it('rejects an example missing a title', () => {
		expect(
			validateGuidance({
				whenToUse: 'x',
				avoid: ['y'],
				examples: [{ tree: {} }],
			})
		).toContain('examples[0].title must be a non-empty string');
	});

	it('rejects an example missing a tree', () => {
		expect(
			validateGuidance({
				whenToUse: 'x',
				avoid: ['y'],
				examples: [{ title: 'A' }],
			})
		).toContain('examples[0].tree must be an object');
	});

	it('rejects an unexpected top-level key', () => {
		expect(
			validateGuidance({
				whenToUse: 'x',
				avoid: ['y'],
				examples: [{ title: 'A', tree: {} }],
				extra: true,
			})
		).toContain('unexpected top-level key "extra"');
	});

	it('rejects a non-object payload', () => {
		expect(validateGuidance(null)).toEqual([
			'guidance must be a plain object',
		]);
		expect(validateGuidance([])).toEqual([
			'guidance must be a plain object',
		]);
	});
});

describe("every guided block's agent.json", () => {
	let engine;
	let failures;

	beforeAll(() => {
		failures = registerForJest();
		engine = createEngine(blocksApi);
	});

	it('registers all blocks with no failures (sanity check on the fixture)', () => {
		expect(failures).toEqual([]);
	});

	describe.each(GUIDED_BLOCKS)('%s', (blockDir) => {
		const agentJsonPath = path.join(BLOCKS_DIR, blockDir, 'agent.json');

		it('has an agent.json file', () => {
			expect(fs.existsSync(agentJsonPath)).toBe(true);
		});

		it('parses as JSON and matches the guidance schema', () => {
			const raw = fs.readFileSync(agentJsonPath, 'utf8');
			const guidance = JSON.parse(raw);
			expect(validateGuidance(guidance)).toEqual([]);
		});

		describe('examples', () => {
			const guidance = JSON.parse(fs.readFileSync(agentJsonPath, 'utf8'));

			it.each(
				guidance.examples.map((example) => [example.title, example])
			)(
				'"%s" assembles to valid markup and lints clean',
				(title, example) => {
					const assembled = engine.assemble(example.tree);
					expect(assembled.status).toBe('valid');
					expect(assembled.invalid).toEqual([]);

					const findings = engine.lint(example.tree, DESIGN_CONTEXT);
					const errors = findings.filter(
						(finding) => finding.severity === 'error'
					);
					const warnings = findings.filter(
						(finding) => finding.severity === 'warning'
					);

					expect(errors).toEqual([]);
					expect(warnings).toEqual([]);
				}
			);
		});
	});
});
