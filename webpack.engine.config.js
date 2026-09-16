/**
 * Webpack config for the Node build of the agent block engine.
 *
 * Separate from webpack.config.js because this bundle targets Node and never
 * ships in the plugin ZIP. Only plugin source is bundled (and transpiled);
 * packages under node_modules are required at runtime from their CommonJS
 * builds — the same modules the Jest suite already runs block save() against.
 */
const path = require('path');
const webpack = require('webpack');

const NESTED_BLOCKS = '@wordpress/block-editor/node_modules/@wordpress/blocks';

// Packages whose real module graph cannot load outside an editor page. Nothing
// in them affects serialization; plugin source only reads their store names.
const STUBS = {
	'@wordpress/editor': './src/engine/node/stubs/editor.js',
	'@wordpress/notices': './src/engine/node/stubs/notices.js',
};

module.exports = {
	mode: 'production',
	target: 'node',
	entry: { node: './src/engine/node/cli.js' },
	output: {
		path: path.resolve(__dirname, 'build/engine'),
		filename: '[name].cjs',
		library: { type: 'commonjs2' },
	},
	optimization: { minimize: false },
	devtool: false,
	resolve: {
		extensions: ['.js', '.jsx', '.json'],
		alias: Object.fromEntries(
			Object.entries(STUBS).map(([name, file]) => [
				`${name}$`,
				path.resolve(__dirname, file),
			])
		),
	},
	externals: [
		({ request }, callback) => {
			if (
				!request ||
				request.startsWith('.') ||
				path.isAbsolute(request)
			) {
				return callback();
			}
			if (STUBS[request]) {
				return callback();
			}
			// One @wordpress/blocks instance for the registry, the parser and
			// useBlockProps.save(). See tests/unit/deprecations-isEligible.test.js.
			if (request === '@wordpress/blocks') {
				return callback(null, `commonjs ${NESTED_BLOCKS}`);
			}
			return callback(null, `commonjs ${request}`);
		},
	],
	module: {
		rules: [
			{
				test: /\.jsx?$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						babelrc: false,
						configFile: false,
						presets: ['@wordpress/babel-preset-default'],
						cacheDirectory: false,
					},
				},
			},
		],
	},
	plugins: [
		new webpack.NormalModuleReplacementPlugin(
			/\.(s?css)$/,
			path.resolve(__dirname, 'src/engine/node/stubs/style.js')
		),
		new webpack.BannerPlugin({ banner: '#!/usr/bin/env node', raw: true }),
		// Dynamic imports in extensions otherwise emit ext-*.cjs side files.
		new webpack.optimize.LimitChunkCountPlugin({ maxChunks: 1 }),
	],
	performance: { hints: false },
	stats: 'errors-warnings',
};
