/**
 * Exercise both release packagers with runtime files and development fixtures.
 * Source-mounted WordPress can hide missing assets, so inspect the ZIP itself.
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const AdmZip = require('adm-zip');

const ROOT = path.resolve(__dirname, '../..');
const helper = fs.readFileSync(
	path.join(ROOT, 'includes/patterns/placeholder-images.php'),
	'utf8'
);
const media = [...helper.matchAll(/=>\s*'(placeholder-[^']+)'/g)].map(
	([, file]) => `assets/images/patterns/${file}`
);
const runtime = [
	'designsetgo.php',
	'uninstall.php',
	'readme.txt',
	'includes/patterns/placeholder-images.php',
	'patterns/gallery/gallery-grid.php',
	'build/index.js',
	...media,
];
const development = [
	'.wordpress-org/screenshot-1.png',
	'assets/banner-772x250.png',
	'assets/icon-128x128.png',
	'assets/screenshot-1.gif',
	'images/Accordion.jpg',
	'scripts/patch-wp-env-composer-audit.js',
	'tools/generate-shape-divider-enum.js',
	'tests/unit/release-packaging.test.js',
	'.agents/skills/test/SKILL.md',
	'.Codex/Codex-memory.md',
	'jest.config.js',
	'phpcs.xml',
	'phpunit.xml.dist',
	'phpstan-constants.php',
	'.coderabbit.yaml',
	'.nvmrc',
	'.wp-env.override.json',
	'.distignore',
	'designsetgo.zip',
];
let fixture;

beforeAll(() => {
	expect(media.length).toBeGreaterThan(0);
	fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-package-'));
	for (const file of ['package.json', ...runtime, ...development]) {
		const target = path.join(fixture, 'source', file);
		fs.mkdirSync(path.dirname(target), { recursive: true });
		const source = path.join(ROOT, file);
		if (fs.existsSync(source)) {
			fs.copyFileSync(source, target);
		} else if (file === 'build/index.js' || development.includes(file)) {
			fs.writeFileSync(target, 'Packaging fixture');
		} else {
			throw new Error(`Missing runtime file: ${file}`);
		}
	}
});

afterAll(() => fs.rmSync(fixture, { recursive: true, force: true }));

it('ships mapped pattern media in the WordPress plugin ZIP', () => {
	execFileSync(
		process.execPath,
		[require.resolve('@wordpress/scripts/scripts/plugin-zip')],
		{ cwd: path.join(fixture, 'source'), stdio: 'pipe' }
	);
	const zip = new AdmZip(path.join(fixture, 'source/designsetgo.zip'));
	for (const file of runtime) {
		expect(zip.getEntry(`designsetgo/${file}`)).not.toBeNull();
	}
	for (const file of development) {
		expect(zip.getEntry(`designsetgo/${file}`)).toBeNull();
	}
});

it('ships runtime media without development files through the SVN filter', () => {
	const destination = path.join(fixture, 'svn');
	fs.mkdirSync(destination);
	execFileSync('rsync', [
		'-rc',
		`--exclude-from=${path.join(ROOT, '.distignore')}`,
		`${path.join(fixture, 'source')}/`,
		`${destination}/`,
	]);
	for (const file of runtime) {
		expect(fs.existsSync(path.join(destination, file))).toBe(true);
	}
	for (const file of development) {
		expect(fs.existsSync(path.join(destination, file))).toBe(false);
	}
});
