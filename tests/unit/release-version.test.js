/**
 * Unit tests for scripts/lib/version.js — the shared release-version logic
 * used by scripts/set-version.js and scripts/release-meta.js (the CI guard).
 */

const {
	parseVersion,
	updatePackageJson,
	updatePluginPhp,
	updateReadmeStableTag,
	readPluginPhpVersion,
	readPackageVersion,
	readReadmeStableTag,
	planFileUpdates,
} = require('../../scripts/lib/version');

// Minimal fixtures mirroring the real files' relevant lines.
const PKG = `{
  "name": "designsetgo",
  "version": "2.4.0",
  "description": "Blocks."
}
`;

const PHP = `<?php
/**
 * Plugin Name:       DesignSetGo
 * Version:           2.4.0
 * Requires PHP:      7.4
 */
define( 'DESIGNSETGO_VERSION', '2.4.0' );
define( 'DESIGNSETGO_FILE', __FILE__ );
`;

const README = `=== DesignSetGo ===
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 2.4.0
License: GPLv2 or later
`;

describe('parseVersion', () => {
	it('parses a stable version', () => {
		expect(parseVersion('2.5.0')).toEqual({
			version: '2.5.0',
			isPrerelease: false,
			channel: 'stable',
		});
	});

	it('parses a beta pre-release', () => {
		expect(parseVersion('2.5.0-beta1')).toEqual({
			version: '2.5.0-beta1',
			isPrerelease: true,
			channel: 'beta',
		});
	});

	it('parses a release candidate', () => {
		expect(parseVersion('2.5.0-rc1')).toEqual({
			version: '2.5.0-rc1',
			isPrerelease: true,
			channel: 'rc',
		});
	});

	it('strips a leading v', () => {
		expect(parseVersion('v2.5.0-beta2')).toMatchObject({
			version: '2.5.0-beta2',
			channel: 'beta',
		});
	});

	it.each([
		'2.5',
		'2.5.0.1',
		'2.5.0-alpha1',
		'2.5.0-beta',
		'2.5.0-rc',
		'foo',
		'',
	])('throws on malformed input %p', (bad) => {
		expect(() => parseVersion(bad)).toThrow();
	});

	it('throws on non-string input', () => {
		expect(() => parseVersion(250)).toThrow();
	});
});

describe('updatePluginPhp', () => {
	it('updates both the header line and the DESIGNSETGO_VERSION define', () => {
		const out = updatePluginPhp(PHP, '2.5.0-beta1');
		expect(out).toContain('* Version:           2.5.0-beta1');
		expect(out).toContain(
			"define( 'DESIGNSETGO_VERSION', '2.5.0-beta1' );"
		);
		// Unrelated lines/defines untouched.
		expect(out).toContain('* Requires PHP:      7.4');
		expect(out).toContain("define( 'DESIGNSETGO_FILE', __FILE__ );");
	});

	it('round-trips with readPluginPhpVersion', () => {
		expect(readPluginPhpVersion(updatePluginPhp(PHP, '3.0.0'))).toBe(
			'3.0.0'
		);
	});
});

describe('updateReadmeStableTag', () => {
	it('rewrites the Stable tag line', () => {
		const out = updateReadmeStableTag(README, '2.5.0');
		expect(out).toContain('Stable tag: 2.5.0');
		expect(out).toContain('Tested up to: 7.0');
	});

	it('is case-insensitive on the label', () => {
		const lower = README.replace('Stable tag:', 'Stable Tag:');
		expect(updateReadmeStableTag(lower, '9.9.9')).toContain('9.9.9');
	});

	it('round-trips with readReadmeStableTag', () => {
		expect(
			readReadmeStableTag(updateReadmeStableTag(README, '2.6.0'))
		).toBe('2.6.0');
	});
});

describe('updatePackageJson', () => {
	it('changes only the version and preserves other keys/formatting', () => {
		const out = updatePackageJson(PKG, '2.5.0-rc1');
		expect(readPackageVersion(out)).toBe('2.5.0-rc1');
		expect(out).toContain('"name": "designsetgo"');
		expect(out).toContain('"description": "Blocks."');
	});
});

describe('planFileUpdates', () => {
	const files = { pkg: PKG, php: PHP, readme: README };

	it('updates all three files for a stable version', () => {
		const result = planFileUpdates(files, '2.5.0');
		expect(result.changed.sort()).toEqual(
			['designsetgo.php', 'package.json', 'readme.txt'].sort()
		);
		expect(readReadmeStableTag(result.readme)).toBe('2.5.0');
		expect(readPluginPhpVersion(result.php)).toBe('2.5.0');
		expect(readPackageVersion(result.pkg)).toBe('2.5.0');
	});

	it('leaves readme.txt untouched for a pre-release (the footgun guard)', () => {
		const result = planFileUpdates(files, '2.5.0-beta1');
		expect(result.changed).not.toContain('readme.txt');
		expect(result.readme).toBe(README);
		expect(readReadmeStableTag(result.readme)).toBe('2.4.0');
		// php + package.json still bumped to the beta.
		expect(readPluginPhpVersion(result.php)).toBe('2.5.0-beta1');
		expect(readPackageVersion(result.pkg)).toBe('2.5.0-beta1');
	});

	it('throws on an invalid version', () => {
		expect(() => planFileUpdates(files, '2.5')).toThrow();
	});
});
