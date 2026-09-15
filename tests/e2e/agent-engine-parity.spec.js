/**
 * Node vs. browser engine parity.
 *
 * The agent block engine runs in two places: the Node CLI (jsdom, core
 * blocks pinned to WordPress 6.7) and the real block editor
 * (`window.designsetgoEngine`, backed by whatever WordPress this site
 * runs). Local agents build markup with the fast, dependency-free Node CLI
 * and trust it matches what a live site would produce — this spec is the
 * proof: for a set of real agent trees, both sides must serialize every
 * DesignSetGo block region identically.
 *
 * Comparison is scoped to DesignSetGo structure only (see
 * `helpers/engine-parity.js`): core block markup can legitimately differ
 * between WordPress 6.7 and the site's own WordPress, so every
 * non-DesignSetGo block collapses to a `<!--core-->` placeholder before the
 * two markups are diffed. Only the Node side runs here in `beforeAll`; the
 * browser side is assembled fresh per test against the live registry.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { createNewPost } = require('./helpers/wordpress');
const { extractDesignSetGoRegions } = require('./helpers/engine-parity');

const REPO_ROOT = path.join(__dirname, '..', '..');
const CLI = path.join(REPO_ROOT, 'build/engine/node.cjs');

const TREES = [
	{
		name: 'valid-tree (section + row/icon-button)',
		file: path.join(REPO_ROOT, 'tests/engine/fixtures/valid-tree.json'),
	},
	{
		name: 'lint-error-tree (section + core/html)',
		file: path.join(REPO_ROOT, 'tests/engine/fixtures/lint-error-tree.json'),
	},
	{
		name: 'grid-cards (grid > card > icon-button)',
		file: path.join(__dirname, 'fixtures/agent-trees/grid-cards.json'),
	},
	{
		name: 'tabs-content (tabs > tab > core heading/paragraph)',
		file: path.join(__dirname, 'fixtures/agent-trees/tabs-content.json'),
	},
	{
		name: 'section-accordion-row (section > heading, accordion > accordion-item, row > icon-button)',
		file: path.join(__dirname, 'fixtures/agent-trees/section-accordion-row.json'),
	},
];

/**
 * Runs the built Node CLI's `assemble --json` for one tree file.
 *
 * @param {string} file Absolute path to a tree JSON fixture.
 * @return {{status: string, markup: string, invalid: Array<Object>, treeHash: string}} Parsed CLI report.
 */
function assembleWithNodeCli(file) {
	const stdout = execFileSync(
		process.execPath,
		[CLI, 'assemble', file, '--json'],
		{ encoding: 'utf8' }
	);
	return JSON.parse(stdout);
}

/**
 * Polls `wp.blocks.getBlockTypes().length` in the editor page until it is
 * unchanged across three checks 100ms apart — registration races are a
 * known issue in this repo — then confirms `designsetgo/section` made it
 * into the registry and `window.designsetgoEngine` exists.
 *
 * @param {import('@playwright/test').Page} page Editor admin page (top-level, not the canvas iframe).
 * @return {Promise<{count: number, hasSection: boolean, hasEngine: boolean}>} Final block-type count and readiness flags.
 */
async function waitForBlockRegistration(page) {
	return page.evaluate(async () => {
		const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
		let last = -1;
		let stableReadings = 0;
		let iterations = 0;

		while (stableReadings < 3 && iterations < 100) {
			const current = window.wp.blocks.getBlockTypes().length;
			stableReadings = current === last ? stableReadings + 1 : 1;
			last = current;
			iterations++;
			await sleep(100);
		}

		return {
			count: last,
			hasSection: Boolean(window.wp.blocks.getBlockType('designsetgo/section')),
			hasEngine: typeof window.designsetgoEngine !== 'undefined',
		};
	});
}

test.describe('Agent engine — Node vs. browser parity', () => {
	/** @type {Record<string, {status: string, markup: string}>} */
	let nodeReports;

	test.beforeAll(() => {
		nodeReports = {};
		for (const tree of TREES) {
			const report = assembleWithNodeCli(tree.file);
			if (report.status !== 'valid') {
				throw new Error(
					`Fixture "${tree.name}" is not Node-valid; fix the fixture, not this spec: ${JSON.stringify(report.invalid)}`
				);
			}
			nodeReports[tree.file] = report;
		}
	});

	for (const tree of TREES) {
		test(`${tree.name}: DesignSetGo regions match between Node and the editor`, async ({
			page,
		}) => {
			await createNewPost(page);

			const registration = await waitForBlockRegistration(page);
			expect(registration.hasEngine).toBe(true);
			expect(registration.hasSection).toBe(true);

			const treeJson = JSON.parse(fs.readFileSync(tree.file, 'utf8'));
			const browserReport = await page.evaluate(
				(t) => window.designsetgoEngine.assemble(t),
				treeJson
			);

			expect(browserReport.status).toBe('valid');

			const nodeRegions = extractDesignSetGoRegions(
				nodeReports[tree.file].markup
			);
			const browserRegions = extractDesignSetGoRegions(browserReport.markup);

			expect(browserRegions).toEqual(nodeRegions);
		});
	}
});
