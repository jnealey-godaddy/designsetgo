/**
 * Lint framework for agent-submitted block trees. Runs a list of rule
 * modules — Tasks 10/11 supply the actual design-mistake rules — against a
 * tree already known to pass `checkTreeShape()` (see `../tree.js`); rules
 * must not throw on a node missing `attributes`/`innerBlocks`, but a rule
 * that throws anyway is caught and reported as a finding rather than
 * aborting the whole lint pass.
 *
 * Plain ES module — no Node-only or WordPress imports — so it runs
 * unmodified in the CLI and the browser editor.
 *
 * A rule module has the shape:
 *
 *   {
 *     id: string,
 *     severity: 'error' | 'warning',
 *     check?( node, ctx ),      // called for every block node, document order
 *     checkTree?( tree, ctx ),  // called once, for page-level rules
 *   }
 *
 * `check(node, ctx)`'s `ctx` is `{ path, parent, ancestors, tree, design, report }`,
 * where `report(message, suggestion?)` appends a finding at the node's own path.
 *
 * `checkTree(tree, ctx)`'s `ctx` is `{ tree, design, report }` — there is no
 * single node path, so its `report` takes the path explicitly:
 * `report(path, message, suggestion?)`.
 *
 * `ctx.design` is `{ palette: Map, spacingSlugs: Set, fontSizeSlugs: Set, presetColor(value) }`,
 * the helpers from `./design.js` bound to the design context passed to `lint()`.
 */
import { walkTree } from '../tree';
import {
	paletteColors,
	spacingSlugs,
	fontSizeSlugs,
	presetColor,
} from './design';
import { rules as defaultRules } from './rules/index';

/**
 * Build the `ctx.design` helpers bound to one design context.
 *
 * @param {Object} designContext A `get-design-context` ability payload.
 * @return {{palette: Map, spacingSlugs: Set, fontSizeSlugs: Set, presetColor: Function}}
 *         Bound design helpers.
 */
function bindDesign(designContext) {
	return {
		palette: paletteColors(designContext),
		spacingSlugs: spacingSlugs(designContext),
		fontSizeSlugs: fontSizeSlugs(designContext),
		presetColor: (value) => presetColor(designContext, value),
	};
}

/**
 * Order key for a lint finding path: the sequence of numeric block/inner-
 * block indices it passes through, e.g. `blocks[10].innerBlocks[2]` →
 * `[10, 2]`. Comparing these lexicographically reproduces document (DFS)
 * order — an ancestor's key is a prefix of its descendants' keys, and
 * `blocks[10]` correctly sorts after `blocks[2]` (unlike a plain string
 * compare, where `"blocks[10]"` < `"blocks[2]"`).
 *
 * @param {string} path Finding path, e.g. `blocks[0].innerBlocks[1]`.
 * @return {number[]} Numeric index sequence.
 */
function pathOrderKey(path) {
	if (typeof path !== 'string') {
		return [];
	}
	const matches = path.match(/\[(\d+)\]/g) || [];
	return matches.map((match) => Number(match.slice(1, -1)));
}

/**
 * Compare two findings' paths in document order.
 *
 * @param {{path: string}} a First finding.
 * @param {{path: string}} b Second finding.
 * @return {number} Negative, zero, or positive per `Array#sort` convention.
 */
function comparePaths(a, b) {
	const keyA = pathOrderKey(a.path);
	const keyB = pathOrderKey(b.path);
	const length = Math.max(keyA.length, keyB.length);

	for (let i = 0; i < length; i++) {
		const valueA = keyA[i];
		const valueB = keyB[i];
		if (valueA === undefined) {
			return -1; // a is an ancestor of (or equal-depth prefix to) b.
		}
		if (valueB === undefined) {
			return 1;
		}
		if (valueA !== valueB) {
			return valueA - valueB;
		}
	}

	return (a.path || '').localeCompare(b.path || '');
}

/**
 * Build a `report()` bound to one rule and one finding path.
 *
 * @param {{id: string, severity: string}} rule Rule whose id/severity to stamp.
 * @param {string}                         path Finding path.
 * @param {object[]}                       sink Findings accumulator.
 * @return {(message: string, suggestion?: string) => void} Bound reporter.
 */
function makeNodeReport(rule, path, sink) {
	return (message, suggestion) => {
		const finding = {
			rule: rule.id,
			severity: rule.severity,
			path,
			message,
		};
		if (suggestion !== undefined) {
			finding.suggestion = suggestion;
		}
		sink.push(finding);
	};
}

/**
 * Build a `report(path, message, suggestion?)` bound to one rule, for
 * page-level `checkTree` rules that have no single node path.
 *
 * @param {{id: string, severity: string}} rule Rule whose id/severity to stamp.
 * @param {object[]}                       sink Findings accumulator.
 * @return {(path: string, message: string, suggestion?: string) => void} Bound reporter.
 */
function makeTreeReport(rule, sink) {
	return (path, message, suggestion) => {
		const finding = {
			rule: rule.id,
			severity: rule.severity,
			path,
			message,
		};
		if (suggestion !== undefined) {
			finding.suggestion = suggestion;
		}
		sink.push(finding);
	};
}

/**
 * Record a rule crash as a finding instead of letting it abort the lint pass.
 *
 * @param {{id: string}} rule  Rule that threw.
 * @param {string}       path  Path to attach the finding to (`''` for a
 *                             `checkTree` rule, which has no single node).
 * @param {Error}        error The thrown error.
 * @param {object[]}     sink  Findings accumulator.
 */
function reportCrash(rule, path, error, sink) {
	sink.push({
		rule: rule.id,
		severity: 'error',
		path,
		message: `lint rule crashed: ${error.message}`,
	});
}

/**
 * Lint an agent-submitted block tree.
 *
 * @param {Object}   tree            A tree already known to pass `checkTreeShape()`.
 * @param {Object}   [designContext] A `designsetgo/get-design-context` ability payload.
 * @param {Object}   [options]
 * @param {object[]} [options.rules] Rule modules to run; defaults to `./rules/index.js`.
 * @return {object[]} Findings, sorted by path in document order.
 */
export function lint(tree, designContext = {}, options = {}) {
	const activeRules = options.rules || defaultRules;
	const design = bindDesign(designContext);
	const findings = [];

	for (const rule of activeRules) {
		if (typeof rule.checkTree !== 'function') {
			continue;
		}
		try {
			rule.checkTree(tree, {
				tree,
				design,
				report: makeTreeReport(rule, findings),
			});
		} catch (error) {
			reportCrash(rule, '', error, findings);
		}
	}

	const blocks = Array.isArray(tree?.blocks) ? tree.blocks : [];

	walkTree(blocks, (node, path, parentNode, ancestors) => {
		for (const rule of activeRules) {
			if (typeof rule.check !== 'function') {
				continue;
			}
			try {
				rule.check(node, {
					path,
					parent: parentNode,
					ancestors,
					tree,
					design,
					report: makeNodeReport(rule, path, findings),
				});
			} catch (error) {
				reportCrash(rule, path, error, findings);
			}
		}
	});

	return findings.sort(comparePaths);
}
