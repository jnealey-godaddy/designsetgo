/**
 * Exercise wp-env's Git downloader with the security-patched simple-git API.
 * Run outside Jest's module loader to use the installed CommonJS exports.
 */
const { execFileSync } = require('child_process');

test('wp-env can clone, fetch, checkout and reset with simple-git 4', () => {
	execFileSync(
		process.execPath,
		[
			'-e',
			`
			const fs = require('node:fs');
			const os = require('node:os');
			const path = require('node:path');
			const { downloadGitSource } = require(
				'./node_modules/@wordpress/env/lib/download-sources'
			);
			const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-git-compat-'));
			downloadGitSource({
				url: process.cwd(),
				clonePath: path.join(temp, 'clone'),
				ref: 'HEAD',
			}, { onProgress() {}, spinner: {}, debug: false })
				.catch(error => { process.stderr.write(error.toString()); process.exitCode = 1; })
				.finally(() => fs.rmSync(temp, { recursive: true, force: true }));
			`,
		],
		{ cwd: process.cwd(), timeout: 20000, stdio: 'pipe' }
	);
});
