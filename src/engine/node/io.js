/**
 * Shared file I/O and report-formatting helpers for the Node engine CLI's
 * command logic (`run.js`, `lint-command.js`). Split out purely to keep
 * every file in `src/engine/node/` under the project's 300-line cap — no
 * behavior lives here that wasn't previously inline in `run.js`.
 */
'use strict';

/**
 * @param {(file: string) => string} readFile Reads `file` as JSON.
 * @param {string}                   file     Path to read.
 * @return {*} Parsed JSON value.
 * @throws {Error} When `readFile` throws or the contents aren't valid JSON.
 */
function readJsonFile(readFile, file) {
	let raw;
	try {
		raw = readFile(file);
	} catch (error) {
		throw new Error(`Cannot read file "${file}": ${error.message}`);
	}
	try {
		return JSON.parse(raw);
	} catch (error) {
		throw new Error(`Cannot parse JSON in "${file}": ${error.message}`);
	}
}

/**
 * @param {(file: string) => string} readFile Reads `file` as text.
 * @param {string}                   file     Path to read.
 * @return {string} File contents.
 * @throws {Error} When `readFile` throws.
 */
function readTextFile(readFile, file) {
	try {
		return readFile(file);
	} catch (error) {
		throw new Error(`Cannot read file "${file}": ${error.message}`);
	}
}

/**
 * @param {string} file   Source file path (for the text-mode header line).
 * @param {Object} result `{ status, invalid }` from `engine.validate()` (or
 *                        the same shape built from `checkTreeShape()` problems).
 * @return {string} Multi-line text report for one file.
 */
function formatValidateFile(file, result) {
	const lines = [`${file}: ${result.status}`];
	result.invalid.forEach((entry) => {
		lines.push(`  ${entry.path} ${entry.block}: ${entry.reason}`);
	});
	return lines.join('\n');
}

/**
 * @param {(content: string) => void}               stdout    Stdout writer.
 * @param {(file: string, content: string) => void} writeFile File writer.
 * @param {string}                                  content   Text to write to stdout (and `outFile`, if given).
 * @param {string}                                  [outFile] Optional path to also write `content` to.
 */
function emit(stdout, writeFile, content, outFile) {
	stdout(`${content}\n`);
	if (outFile) {
		writeFile(outFile, content);
	}
}

module.exports = { readJsonFile, readTextFile, formatValidateFile, emit };
