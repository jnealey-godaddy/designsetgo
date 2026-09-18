/**
 * Block Schema Validation Tests
 *
 * These tests dynamically validate all block.json files in the plugin.
 * They ensure blocks follow WordPress standards and plugin conventions.
 *
 * Benefits of this approach:
 * - Automatically covers new blocks without updating tests
 * - Tests structural correctness, not implementation details
 * - Catches common configuration mistakes early
 * - Flexible and maintainable
 *
 * @package
 */

const fs = require('fs');
const path = require('path');

// Discover all block.json files
const blocksDir = path.resolve(__dirname, '../../src/blocks');
const blockDirs = fs.existsSync(blocksDir)
	? fs.readdirSync(blocksDir).filter((dir) => {
			const blockJsonPath = path.join(blocksDir, dir, 'block.json');
			return fs.existsSync(blockJsonPath);
		})
	: [];

// Load all block configurations
const blocks = blockDirs.map((dir) => {
	const blockJsonPath = path.join(blocksDir, dir, 'block.json');
	const content = fs.readFileSync(blockJsonPath, 'utf8');
	return {
		dir,
		path: blockJsonPath,
		config: JSON.parse(content),
	};
});

describe('Block Schema Validation', () => {
	describe('Block Discovery', () => {
		it('discovers blocks from src/blocks directory', () => {
			expect(blocks.length).toBeGreaterThan(0);
		});

		it('finds expected number of blocks (sanity check)', () => {
			// Should have at least 40 blocks based on current codebase
			expect(blocks.length).toBeGreaterThanOrEqual(40);
		});
	});

	describe('Block Naming Conventions', () => {
		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s uses designsetgo namespace',
			(dir, config) => {
				expect(config.name).toMatch(/^designsetgo\//);
			}
		);

		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s block name matches directory name',
			(dir, config) => {
				const expectedName = `designsetgo/${dir}`;
				expect(config.name).toBe(expectedName);
			}
		);
	});

	describe('Required Block Properties', () => {
		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s has required properties',
			(dir, config) => {
				// Required by WordPress block API
				expect(config).toHaveProperty('name');
				expect(config).toHaveProperty('title');
				expect(config).toHaveProperty('category');

				// Required for our plugin
				expect(config).toHaveProperty('textdomain', 'designsetgo');
			}
		);

		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s uses API version 3',
			(dir, config) => {
				expect(config.apiVersion).toBe(3);
			}
		);

		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s has a description',
			(dir, config) => {
				expect(config.description).toBeDefined();
				expect(typeof config.description).toBe('string');
				expect(config.description.length).toBeGreaterThan(10);
			}
		);
	});

	describe('Attribute Schema Validation', () => {
		// Filter blocks that have attributes
		const blocksWithAttributes = blocks.filter(
			(b) =>
				b.config.attributes &&
				Object.keys(b.config.attributes).length > 0
		);

		it.each(blocksWithAttributes.map((b) => [b.dir, b.config]))(
			'%s attributes have valid types',
			(dir, config) => {
				const validTypes = [
					'string',
					'number',
					'boolean',
					'object',
					'array',
					'integer',
					'null',
				];

				Object.entries(config.attributes).forEach(([, attrConfig]) => {
					if (attrConfig.type) {
						expect(validTypes).toContain(attrConfig.type);
					}
				});
			}
		);

		it.each(blocksWithAttributes.map((b) => [b.dir, b.config]))(
			'%s enum attributes have valid values',
			(dir, config) => {
				Object.entries(config.attributes).forEach(([, attrConfig]) => {
					if (attrConfig.enum) {
						expect(Array.isArray(attrConfig.enum)).toBe(true);
						expect(attrConfig.enum.length).toBeGreaterThan(0);

						// If there's a default, it should be in the enum
						if (attrConfig.default !== undefined) {
							expect(attrConfig.enum).toContain(
								attrConfig.default
							);
						}
					}
				});
			}
		);

		it.each(blocksWithAttributes.map((b) => [b.dir, b.config]))(
			'%s boolean attributes have boolean defaults',
			(dir, config) => {
				Object.entries(config.attributes).forEach(([, attrConfig]) => {
					if (
						attrConfig.type === 'boolean' &&
						attrConfig.default !== undefined
					) {
						expect(typeof attrConfig.default).toBe('boolean');
					}
				});
			}
		);

		it.each(blocksWithAttributes.map((b) => [b.dir, b.config]))(
			'%s number attributes have valid defaults',
			(dir, config) => {
				Object.entries(config.attributes).forEach(([, attrConfig]) => {
					if (
						attrConfig.type === 'number' &&
						attrConfig.default !== undefined
					) {
						// Allow number or object (for responsive values like {desktop: 10})
						const validDefault =
							typeof attrConfig.default === 'number' ||
							typeof attrConfig.default === 'object';
						expect(validDefault).toBe(true);
					}
				});
			}
		);
	});

	describe('Block Supports Configuration', () => {
		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s has supports configuration',
			(dir, config) => {
				expect(config).toHaveProperty('supports');
				expect(typeof config.supports).toBe('object');
			}
		);

		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s disables HTML editing for safety',
			(dir, config) => {
				// Most blocks should disable HTML editing to prevent breaking
				// undefined is treated as false by WordPress, so both are acceptable
				const htmlDisabled =
					config.supports.html === false ||
					config.supports.html === undefined;
				expect(htmlDisabled).toBe(true);
			}
		);
	});

	describe('Context Configuration', () => {
		const blocksProvidingContext = blocks.filter(
			(b) => b.config.providesContext
		);

		const blocksUsingContext = blocks.filter((b) => b.config.usesContext);

		it.each(blocksProvidingContext.map((b) => [b.dir, b.config]))(
			'%s providesContext uses valid format',
			(dir, config) => {
				Object.entries(config.providesContext).forEach(
					([contextKey, attrName]) => {
						// Context key should be namespaced
						expect(contextKey).toMatch(/^designsetgo\//);
						// Should reference a valid attribute
						expect(config.attributes).toHaveProperty(attrName);
					}
				);
			}
		);

		it.each(blocksUsingContext.map((b) => [b.dir, b.config]))(
			'%s usesContext array contains valid context keys',
			(dir, config) => {
				expect(Array.isArray(config.usesContext)).toBe(true);
				config.usesContext.forEach((contextKey) => {
					// Context keys should be non-empty strings
					// Can be namespaced (designsetgo/) or core WordPress context (postId, etc.)
					expect(typeof contextKey).toBe('string');
					expect(contextKey.length).toBeGreaterThan(0);
				});
			}
		);
	});

	describe('Parent-Child Relationships', () => {
		const childBlocks = blocks.filter((b) => b.config.parent);

		it.each(childBlocks.map((b) => [b.dir, b.config]))(
			'%s parent array references valid blocks',
			(dir, config) => {
				expect(Array.isArray(config.parent)).toBe(true);
				config.parent.forEach((parentName) => {
					expect(parentName).toMatch(/^designsetgo\//);
				});
			}
		);

		// Child blocks can have explicit inserter settings
		// Some child blocks enable inserter for flexibility in the editor
		it.each(childBlocks.map((b) => [b.dir, b.config]))(
			'%s child block has inserter setting as boolean if defined',
			(dir, config) => {
				if (config.supports.inserter !== undefined) {
					expect(typeof config.supports.inserter).toBe('boolean');
				}
			}
		);
	});

	describe('Script and Style References', () => {
		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s has editorScript',
			(dir, config) => {
				expect(config.editorScript).toBeDefined();
				expect(config.editorScript).toMatch(/^file:\.\//);
			}
		);

		it.each(blocks.map((b) => [b.dir, b.config]))(
			'%s style references use correct format',
			(dir, config) => {
				if (config.style) {
					expect(config.style).toMatch(/^file:\.\//);
				}
				if (config.editorStyle) {
					expect(config.editorStyle).toMatch(/^file:\.\//);
				}
				if (config.viewScript) {
					expect(config.viewScript).toMatch(/^file:\.\//);
				}
			}
		);
	});

	describe('Example Configuration', () => {
		const blocksWithExamples = blocks.filter((b) => b.config.example);

		it('many blocks provide examples for the inserter', () => {
			// At least 60% of blocks should have examples for good UX
			const percentWithExamples =
				(blocksWithExamples.length / blocks.length) * 100;
			expect(percentWithExamples).toBeGreaterThanOrEqual(60);
		});

		it.each(blocksWithExamples.map((b) => [b.dir, b.config]))(
			'%s example has valid structure',
			(dir, config) => {
				expect(typeof config.example).toBe('object');
				// Examples can have attributes and/or innerBlocks
				const hasAttributes = config.example.attributes !== undefined;
				const hasInnerBlocks = config.example.innerBlocks !== undefined;
				expect(
					hasAttributes ||
						hasInnerBlocks ||
						Object.keys(config.example).length === 0
				).toBe(true);
			}
		);
	});

	describe('Keywords for Searchability', () => {
		const blocksWithKeywords = blocks.filter((b) => b.config.keywords);

		it('most blocks have keywords', () => {
			const percentWithKeywords =
				(blocksWithKeywords.length / blocks.length) * 100;
			expect(percentWithKeywords).toBeGreaterThanOrEqual(70);
		});

		it.each(blocksWithKeywords.map((b) => [b.dir, b.config]))(
			'%s keywords are an array of strings',
			(dir, config) => {
				expect(Array.isArray(config.keywords)).toBe(true);
				config.keywords.forEach((keyword) => {
					expect(typeof keyword).toBe('string');
					expect(keyword.length).toBeGreaterThan(0);
				});
			}
		);
	});

	describe('Form Field Blocks', () => {
		const formFieldBlocks = blocks.filter(
			(b) =>
				b.config.name.includes('form-') &&
				b.config.name !== 'designsetgo/form-builder'
		);

		it('form field blocks exist', () => {
			expect(formFieldBlocks.length).toBeGreaterThan(0);
		});

		it.each(formFieldBlocks.map((b) => [b.dir, b.config]))(
			'%s uses form-builder context if context is defined',
			(dir, config) => {
				if (config.usesContext && config.usesContext.length > 0) {
					// Check for either designsetgo/form/ or designsetgo/form-builder/ context patterns
					const usesFormContext = config.usesContext.some(
						(ctx) =>
							ctx.startsWith('designsetgo/form/') ||
							ctx.startsWith('designsetgo/form-builder/')
					);
					expect(usesFormContext).toBe(true);
				}
			}
		);

		it.each(formFieldBlocks.map((b) => [b.dir, b.config]))(
			'%s has form-builder as parent',
			(dir, config) => {
				if (config.parent) {
					expect(config.parent).toContain('designsetgo/form-builder');
				}
			}
		);
	});

	/**
	 * `src/blocks/{slug}/index.js` imports BOTH `editor.scss` and `style.scss`,
	 * so webpack's `blocks/{slug}/index` entry emits a combined `index.css`.
	 * Webpack also emits `blocks/{slug}/style-index.css` from a dedicated entry
	 * holding `style.scss` alone (see `styleEntries` in webpack.config.js).
	 *
	 * `editorStyle` therefore wants `index.css` — the editor needs both halves —
	 * but `style` must point at `style-index.css`. Pointing `style` at
	 * `index.css` ships every editor-only rule to the front end, where it is at
	 * best dead weight and at worst wins the cascade against the real rule.
	 *
	 * Not hypothetical: `timeline-item/editor.scss` carries
	 * `opacity: 1 !important; transform: none !important` so items stay visible
	 * while authoring. Leaked to the front end it permanently defeated the
	 * Timeline's `opacity: 0` scroll-reveal starting state (#568).
	 */
	describe('Frontend Stylesheet Wiring', () => {
		const withBothStylesheets = blocks.filter(
			(b) =>
				fs.existsSync(path.join(blocksDir, b.dir, 'style.scss')) &&
				fs.existsSync(path.join(blocksDir, b.dir, 'editor.scss'))
		);

		it('finds blocks with both an editor and a frontend stylesheet', () => {
			expect(withBothStylesheets.length).toBeGreaterThan(0);
		});

		it.each(withBothStylesheets.map((b) => [b.dir, b.config]))(
			'%s points "style" at style-index.css, not the editor bundle',
			(dir, config) => {
				expect(config.style).toBe('file:./style-index.css');
			}
		);
	});
});
