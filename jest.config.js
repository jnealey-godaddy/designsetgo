/**
 * Jest Configuration for DesignSetGo
 *
 * Configuration for JavaScript unit tests using Jest.
 * Extends @wordpress/scripts default Jest configuration.
 *
 * @see https://jestjs.io/docs/configuration
 */

module.exports = {
	// Extend @wordpress/scripts default config
	...require('@wordpress/scripts/config/jest-unit.config'),

	// Use V8 coverage instead of babel-plugin-istanbul
	// (babel-plugin-istanbul is incompatible with glob v10 override)
	coverageProvider: 'v8',

	// Test match patterns
	testMatch: [
		'**/tests/unit/**/*.test.js',
		'**/src/**/__tests__/**/*.js',
		'**/src/**/test/*.js',
	],

	// Setup files
	setupFilesAfterEnv: ['<rootDir>/tests/unit/setup.js'],

	// Module paths
	modulePaths: ['<rootDir>/src'],

	// Transform files
	transform: {
		'^.+\\.[jt]sx?$': ['@wordpress/scripts/config/babel-transform'],
	},

	// parsel-js ships only ESM; transform it so jest.requireActual('@wordpress/block-editor')
	// doesn't crash when @wordpress/block-editor's CJS build pulls it in.
	transformIgnorePatterns: ['/node_modules/(?!(parsel-js)/)'],

	// Module name mapper for CSS and asset files
	moduleNameMapper: {
		'\\.(css|less|scss|sass)$': 'identity-obj-proxy',
		'\\.(jpg|jpeg|png|gif|svg|eot|otf|webp|ttf|woff|woff2|mp4|webm|wav|mp3|m4a|aac|oga)$':
			'<rootDir>/tests/unit/__mocks__/fileMock.js',
		// @wordpress/editor and @wordpress/notices pull in heavy WP store internals
		// that fail in Jest. Stub them with minimal modules exposing store IDs only.
		'^@wordpress/editor$':
			'<rootDir>/tests/unit/__mocks__/wordpressEditorMock.js',
		'^@wordpress/notices$':
			'<rootDir>/tests/unit/__mocks__/wordpressNoticesMock.js',
	},

	// Coverage configuration
	collectCoverageFrom: [
		'src/**/*.{js,jsx}',
		'!src/**/index.js',
		'!src/**/*.stories.{js,jsx}',
		'!**/node_modules/**',
		'!**/vendor/**',
		'!**/build/**',
	],

	// Coverage thresholds
	coverageThreshold: {
		global: {
			branches: 50,
			functions: 50,
			lines: 50,
			statements: 50,
		},
	},

	// Ignore patterns
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'/build/',
		'/tests/e2e/',
	],

	// Verbose output
	verbose: true,
};
