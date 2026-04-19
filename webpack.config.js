/**
 * WordPress Scripts Webpack Config - Optimized for WordPress
 *
 * Optimized configuration that reduces bundle size while maintaining
 * full compatibility with WordPress's asset management system.
 *
 * Strategy:
 * - Treat WordPress packages as externals (already loaded by WordPress)
 * - No common chunks (WordPress doesn't handle these well)
 * - Individual block optimization via tree shaking
 * - Expected: 30-40% reduction in bundle size
 *
 * @package
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const glob = require('glob');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

// Auto-detect all blocks with index.js files
const blockEntries = glob
	.sync('src/blocks/*/index.js')
	.reduce((entries, file) => {
		const blockName = file.match(/\/blocks\/([^/]+)\/index\.js$/)[1];
		entries[`blocks/${blockName}/index`] = path.resolve(
			process.cwd(),
			file
		);
		return entries;
	}, {});

// Auto-detect all blocks with view.js files (frontend scripts).
// Blocks using the Interactivity API (viewScriptModule) must build as ES
// modules and live in `moduleViewEntries`; legacy IIFE view.js stays in
// `viewEntries`. Opt-in via `src/blocks/<name>/view.module` marker file,
// which tells webpack to emit the script as a real ES module.
const moduleViewEntries = {};
const viewEntries = glob
	.sync('src/blocks/*/view.js')
	.reduce((entries, file) => {
		const blockName = file.match(/\/blocks\/([^/]+)\/view\.js$/)[1];
		const markerPath = path.resolve(path.dirname(file), 'view.module');
		if (require('fs').existsSync(markerPath)) {
			moduleViewEntries[`blocks/${blockName}/view`] = path.resolve(
				process.cwd(),
				file
			);
		} else {
			entries[`blocks/${blockName}/view`] = path.resolve(
				process.cwd(),
				file
			);
		}
		return entries;
	}, {});

// Auto-detect all blocks with style.scss files (frontend CSS)
const styleEntries = glob
	.sync('src/blocks/*/style.scss')
	.reduce((entries, file) => {
		const blockName = file.match(/\/blocks\/([^/]+)\/style\.scss$/)[1];
		entries[`blocks/${blockName}/style-index`] = path.resolve(
			process.cwd(),
			file
		);
		return entries;
	}, {});

// Build config for script-module view.js (IAPI blocks). Outputs native ES
// modules that import `@wordpress/interactivity` via the browser's native
// module resolver (resolved by WP's importmap). This is a separate webpack
// config from the main one because @wordpress/scripts' default externals
// rewrite imports to `window.wp.interactivity` (wrong target for modules).
const scriptModuleConfig = {
	mode: defaultConfig.mode,
	entry: moduleViewEntries,
	output: {
		filename: '[name].js',
		path: path.resolve(process.cwd(), 'build'),
		library: { type: 'module' },
		module: true,
		environment: { module: true, dynamicImport: true },
	},
	experiments: { outputModule: true },
	externalsType: 'module',
	externals: {
		'@wordpress/interactivity': '@wordpress/interactivity',
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: require.resolve('babel-loader'),
					options: {
						presets: [
							require.resolve('@wordpress/babel-preset-default'),
						],
					},
				},
			},
		],
	},
	plugins: [
		// DependencyExtractionWebpackPlugin auto-generates the `.asset.php`
		// manifest (dependencies + version) so WP knows what modules to
		// load. `useDefaults: false` keeps the default behavior of treating
		// `@wordpress/*` imports as externals, which combined with our
		// explicit `externals` map emits the correct dep id.
		new (require('@wordpress/dependency-extraction-webpack-plugin'))({
			useDefaults: false,
			requestToExternalModule(request) {
				if (request === '@wordpress/interactivity') {
					return request;
				}
			},
		}),
	],
	optimization: {
		minimize: defaultConfig.mode === 'production',
		usedExports: true,
		sideEffects: false,
	},
	performance: { hints: false },
};

module.exports = [
	{
		...defaultConfig,
		// Disable the default clean behaviour. We emit into `build/` from two
		// parallel webpack configs (this one + `scriptModuleConfig`), and the
		// default `output.clean` would wipe the second config's output after
		// it has already been written. The dev and CI workflows both call
		// `npm run clean` (or `build:clean`) when a full reset is needed.
		output: {
			...defaultConfig.output,
			clean: false,
		},
		module: {
			...defaultConfig.module,
			rules: defaultConfig.module.rules.map((rule) => {
				// Add url:false to css-loader for Leaflet's CSS so image references
				// are left as-is (layers.png, marker-icon.png are optional UI chrome)
				if (
					rule.test &&
					/css/.test(rule.test.toString()) &&
					!/scss|sass|pcss/.test(rule.test.toString())
				) {
					return {
						...rule,
						use: rule.use.map((loader) => {
							if (
								typeof loader === 'object' &&
								loader.loader &&
								loader.loader.includes('/css-loader/') &&
								!loader.loader.includes('postcss')
							) {
								return {
									...loader,
									options: {
										...loader.options,
										url: {
											filter: (url, resourcePath) =>
												!resourcePath.includes(
													'leaflet'
												),
										},
									},
								};
							}
							return loader;
						}),
					};
				}
				return rule;
			}),
		},
		// Enable persistent filesystem caching for faster rebuilds
		cache: {
			type: 'filesystem',
			cacheDirectory: path.resolve(
				__dirname,
				'node_modules/.cache/webpack'
			),
			buildDependencies: {
				config: [__filename],
			},
		},
		entry: {
			// Main entry point for extensions and variations (editor)
			index: path.resolve(process.cwd(), 'src', 'index.js'),
			// Frontend CSS entry point
			'style-index': path.resolve(process.cwd(), 'src', 'style.scss'),
			// Frontend entry point for frontend-only scripts
			frontend: path.resolve(process.cwd(), 'src', 'frontend.js'),
			// Admin dashboard entry point
			admin: path.resolve(process.cwd(), 'src', 'admin', 'index.js'),
			// Branded SVG icon for the DesignSetGo block category.
			'block-category-icon': path.resolve(
				process.cwd(),
				'src',
				'block-category-icon.js'
			),
			// Sticky header utility script
			'utils/sticky-header': path.resolve(
				process.cwd(),
				'src',
				'utils',
				'sticky-header.js'
			),
			// Icon injector frontend script
			'frontend/lazy-icon-injector': path.resolve(
				process.cwd(),
				'src',
				'frontend',
				'lazy-icon-injector.js'
			),
			// llms.txt editor panel
			'llms-txt': path.resolve(
				process.cwd(),
				'src',
				'llms-txt',
				'index.js'
			),
			// Overlay header editor panel
			'overlay-header': path.resolve(
				process.cwd(),
				'src',
				'overlay-header',
				'index.js'
			),
			// Dynamic Query facet admin dashboard
			'admin/query-facet-dashboard': path.resolve(
				process.cwd(),
				'src',
				'admin',
				'query-facet-dashboard',
				'index.js'
			),
			// Block-specific entries (auto-detected from src/blocks/*/index.js)
			...blockEntries,
			// Block-specific view scripts (auto-detected from src/blocks/*/view.js)
			...viewEntries,
			// Block-specific frontend styles (auto-detected from src/blocks/*/style.scss)
			...styleEntries,
		},
		// Use default externals from @wordpress/scripts
		// WordPress packages are already externalized by default
		plugins: [
			...defaultConfig.plugins,
			// Copy block style variations and admin assets to build directory
			new CopyWebpackPlugin({
				patterns: [
					{
						from: 'src/blocks/*/styles/*.json',
						to: ({ absoluteFilename }) => {
							// Extract block name and filename from the absolute path
							const match = absoluteFilename.match(
								/blocks\/([^/]+)\/styles\/(.+)$/
							);
							if (match) {
								return `blocks/${match[1]}/styles/${match[2]}`;
							}
							return 'blocks/[name][ext]';
						},
						noErrorOnMissing: true, // Don't error if no files match
					},
					{
						from: 'src/admin/assets/*',
						to: 'admin/assets/[name][ext]',
						noErrorOnMissing: true,
					},
					// Copy query block helper PHP files to build so the REST endpoint
					// and PHPUnit tests can require_once them from build/blocks/query/.
					{
						from: 'src/blocks/query/render-helpers.php',
						to: 'blocks/query/render-helpers.php',
						noErrorOnMissing: true,
					},
					{
						from: 'src/blocks/query/render-posts.php',
						to: 'blocks/query/render-posts.php',
						noErrorOnMissing: true,
					},
					{
						from: 'src/blocks/query/render-users.php',
						to: 'blocks/query/render-users.php',
						noErrorOnMissing: true,
					},
					{
						from: 'src/blocks/query/render-terms.php',
						to: 'blocks/query/render-terms.php',
						noErrorOnMissing: true,
					},
				],
			}),
			// Bundle analyzer - run with: ANALYZE=true npm run build
			// Opens interactive visualization of bundle sizes
			...(process.env.ANALYZE
				? [
						new BundleAnalyzerPlugin({
							analyzerMode: 'static',
							reportFilename: 'bundle-report.html',
							openAnalyzer: true,
							generateStatsFile: true,
							statsFilename: 'bundle-stats.json',
						}),
					]
				: []),
		],
		optimization: {
			...defaultConfig.optimization,
			// PERFORMANCE: Only enable code splitting in production mode
			splitChunks:
				defaultConfig.mode === 'production'
					? {
							cacheGroups: {
								// Extract icon library for editor use only
								// Frontend uses PHP wp_localize_script for lazy loading
								iconLibrary: {
									test: /svg-icons\.js$/,
									name: 'shared-icon-library-static',
									chunks: 'initial', // Only extract from initial chunks (not lazy loaded)
									enforce: true,
									priority: 20,
								},
								// Extract IconPicker component
								iconPicker: {
									test: /IconPicker\.js$/,
									name: 'shared-icon-picker',
									chunks: 'all',
									enforce: true,
									priority: 15,
								},
								// Extract other shared utilities if needed
								sharedUtils: {
									test: /[\\/]src[\\/]blocks[\\/][^\/]+[\\/](utils|components)[\\/]/,
									name: 'shared-block-utils',
									chunks: 'all',
									minChunks: 3, // Only extract if used by 3+ blocks
									priority: 10,
								},
							},
						}
					: false,
			// Enable aggressive tree shaking
			usedExports: true,
			sideEffects: false,
			// Minimize bundle size in production
			minimize: defaultConfig.mode === 'production',
		},
		performance: {
			// ===================================================================
			// PERFORMANCE BUDGETS (Updated 2026-02-07)
			// ===================================================================
			// After lazy-loading optimizations:
			// - Extension edit panels: Lazy-loaded as separate chunks (~1-17 KiB each)
			// - Individual blocks: Target <15KB JS, <10KB CSS (raw)
			// - Shared chunks: Max 50KB (icon library at ~50KB)
			// - Entry points: Max 250KB (editor entry with extension wrappers)
			//
			// Main entry (index.js) contains synchronous extension wrappers:
			//   attribute registration, save filters, and lazy-load HOC shells.
			//   Heavy editor UI is deferred to lazy chunks (ext-*.js, fmt-*.js).
			//
			// Run `ANALYZE=true npm run build` to visualize bundle sizes.
			// ===================================================================
			hints: defaultConfig.mode === 'production' ? 'warning' : false,
			maxEntrypointSize: 250000, // 250KB - main editor entry (all extensions)
			maxAssetSize: 50000, // 50KB - individual blocks/chunks
			// Filter out files that shouldn't trigger performance warnings:
			// - asset.php manifests, source maps, CSS (loaded async, cacheable)
			// - Main entry points (covered by maxEntrypointSize instead)
			// - Shared icon library (code-split, conditionally loaded)
			assetFilter: (assetFilename) => {
				// Skip non-JS assets
				if (
					assetFilename.endsWith('.asset.php') ||
					assetFilename.endsWith('.map') ||
					assetFilename.endsWith('.css')
				) {
					return false;
				}
				// Skip main entry points (monitored by maxEntrypointSize)
				if (/^(index|frontend|admin)\.js$/.test(assetFilename)) {
					return false;
				}
				// Skip shared icon library (conditionally loaded, acceptable at ~50KB)
				if (assetFilename === 'shared-icon-library-static.js') {
					return false;
				}
				// Skip map view script — Leaflet (~40KB gzipped) is intentionally bundled
				// to avoid CSP violations from external CDN loads; only loaded on map pages
				if (assetFilename === 'blocks/map/view.js') {
					return false;
				}
				return true;
			},
		},
	},
	scriptModuleConfig,
];
