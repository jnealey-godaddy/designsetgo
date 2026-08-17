module.exports = {
	// Stop ESLint walking above the project. Git worktrees live under
	// .claude/worktrees/, so without this ESLint also loads the parent
	// checkout's config and reports a duplicate-plugin error.
	root: true,
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
	rules: {
		'import/no-extraneous-dependencies': 'off',
		'import/no-unresolved': 'off',
		'jsdoc/require-param-description': 'off',
		'jsdoc/no-undefined-types': [
			'error',
			{
				definedTypes: [
					'JSX',
					'Element',
					'HTMLElement',
					'HTMLImageElement',
					'IntersectionObserver',
					'NodeList',
					'KeyboardEvent',
					'Document',
				],
			},
		],
	},
	overrides: [
		{
			files: ['tests/**/*.js', '**/*.test.js', '**/*.spec.js'],
			env: {
				jest: true,
			},
			rules: {
				'no-unused-vars': [
					'error',
					{
						varsIgnorePattern: '^_',
						argsIgnorePattern: '^_',
						caughtErrorsIgnorePattern: '^_',
					},
				],
				'jsx-a11y/label-has-associated-control': 'off',
			},
		},
	],
};
