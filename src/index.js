/**
 * DesignSetGo Blocks - Modern Gutenberg Block Library
 *
 * Primary Architecture: Custom blocks with full React control
 * Secondary: Minimal extensions for simple enhancements
 *
 * Architecture:
 * - Blocks: Custom blocks (95% of codebase)
 * - Extensions: Light enhancements to core blocks (5%)
 * - Variations: Pre-configured layouts
 * - Styles: Global styles and utilities
 *
 * @package
 * @version 2.0
 */

// Global styles (variables, mixins, utilities)
import './styles/editor.scss';
// Note: Frontend styles are in src/style.scss (auto-compiled to build/style-index.css)

// ===== EXTENSIONS (Must load BEFORE blocks) =====
// Block animations - adds entrance/exit animations to all blocks
import './extensions/block-animations';

// Max width - adds max-width control to all blocks
import './extensions/max-width';

// Custom CSS - adds custom CSS control to all blocks
import './extensions/custom-css';

// Background Video - adds background video to all blocks
import './extensions/background-video';

// Grid Span - adds column span control to blocks inside Grid containers
import './extensions/grid-span';

// Grid Mobile Order - adds mobile reordering control to blocks inside Grid containers
import './extensions/grid-mobile-order';

// Text Alignment Inheritance - sets text-align based on parent container's alignItems
import './extensions/text-alignment-inheritance';

// Reveal Control - adds reveal on hover control to blocks inside Reveal containers
import './extensions/reveal-control';

// Clickable Group - makes core/group blocks clickable with link functionality
import './extensions/clickable-group';

// Sticky Header Controls - adds sticky header configuration to template parts in Site Editor
import './extensions/sticky-header-controls';

// Responsive Visibility - adds device-based visibility controls to all blocks
import './extensions/responsive';

// Text Reveal - scroll-triggered reading progress effect for text blocks
import './extensions/text-reveal';

// Expanding Background - adds scroll-driven expanding background effect
import './extensions/expanding-background';

// SVG Patterns - adds repeatable SVG background patterns to sections
import './extensions/svg-patterns';

// Vertical Scroll Parallax - adds scroll-based vertical movement to blocks
import './extensions/vertical-scroll-parallax';

// Draft Mode - adds "draft mode" functionality for published pages
import './extensions/draft-mode';

// Hover Effects - adds subtle hover micro-interactions to common core blocks
import './extensions/hover-effects';

// Conditional Visibility - registers dsgoVisibility attribute for query-driven inner-block visibility
import './extensions/visibility';

// Style Binding - maps CSS properties to dynamic data sources (post-meta, ACF, MetaBox, Pods, JetEngine)
import './extensions/style-binding';

// Dynamic Tags - Elementor-style per-attribute dynamic bindings on core blocks
import './extensions/dynamic-tags';

// ===== RICH TEXT FORMATS =====
// Text Style - adds inline text styling (color, gradient, size) to selected text
import './formats/text-style';

// ===== DEFAULT PADDING FOR ROOT CONTAINERS =====
// Set default padding for container blocks, but only when inserted at root level
import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';

/**
 * Set default padding for container blocks at root level
 * Top/Bottom: xxxl (64px), Left/Right: md (16px)
 */
addFilter(
	'blocks.getBlockAttributes',
	'designsetgo/set-container-default-padding',
	(attributes, blockType, clientId) => {
		// Only apply to container blocks
		const containerBlocks = [
			'designsetgo/stack', // Legacy - will be deprecated
			'designsetgo/flex', // Legacy - will be deprecated
			'designsetgo/grid',
			'designsetgo/section', // New name for stack
			'designsetgo/row', // New name for flex
		];
		if (!containerBlocks.includes(blockType.name)) {
			return attributes;
		}

		// Check if block already has padding set
		if (attributes?.style?.spacing?.padding) {
			return attributes;
		}

		// Check if this is a root-level block (no parent)
		const blockEditor = select('core/block-editor');
		if (!blockEditor || !clientId) {
			return attributes;
		}

		const parents = blockEditor.getBlockParents(clientId);

		// If block has no parents, it's root-level - set default padding
		if (parents.length === 0) {
			return {
				...attributes,
				style: {
					...attributes?.style,
					spacing: {
						...attributes?.style?.spacing,
						padding: {
							top: 'var(--wp--preset--spacing--70)',
							bottom: 'var(--wp--preset--spacing--70)',
							left: 'var(--wp--preset--spacing--30)',
							right: 'var(--wp--preset--spacing--30)',
						},
					},
				},
			};
		}

		return attributes;
	}
);

// ===== CUSTOM BLOCKS (Primary Architecture) =====
// Blocks are loaded via block.json (editorScript: "file:./index.js")
// Webpack creates individual entries for each block (see webpack.config.js)
// This prevents duplicate code and reduces main bundle size

// NOTE: Do NOT import blocks here! They are auto-loaded by WordPress via:
// 1. webpack.config.js creates individual entries (blocks/*/index.js)
// 2. block.json specifies editorScript: "file:./index.js"
// 3. PHP registers blocks via register_block_type_from_metadata()
//
// Importing blocks here would duplicate code in the bundle!
