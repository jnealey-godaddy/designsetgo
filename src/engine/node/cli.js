/**
 * Node CLI for the agent block engine.
 *
 * `npm run engine -- assemble tree.json` turns a JSON block tree into
 * editor-valid markup using the plugin's real block registration, outside
 * Jest. `npm run engine -- validate a.html b.html` re-runs the same
 * validity check the editor uses when it decides "Attempt Recovery".
 *
 * CommonJS, and `require('./dom')` is the very first statement — webpack
 * hoists ES `import`s to the top of a module, which would let a later
 * `@wordpress/*` import run before the DOM exists. `require()` has no such
 * hoisting, so the DOM install in `./dom` (pulled in again, idempotently, by
 * `./boot`) always runs before `./boot` requires `@wordpress/blocks`.
 *
 * This file only wires real dependencies (fs, process streams, the real
 * `bootEngine`) into `./run`'s injected-dependency `run()`. All actual
 * command logic — and its test coverage, including the boot-throw and
 * registration-failure branches that are impractical to force through a
 * spawned real bundle — lives in `run.js`.
 */
'use strict';

require('./dom');

const fs = require('fs');
const { bootEngine } = require('./boot');
const { run } = require('./run');

process.exitCode = run(process.argv.slice(2), {
	bootEngine,
	readFile: (file) => fs.readFileSync(file, 'utf8'),
	writeFile: (file, content) => fs.writeFileSync(file, content),
	stdout: (content) => process.stdout.write(content),
	stderr: (content) => process.stderr.write(content),
});
