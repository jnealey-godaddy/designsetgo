/**
 * Custom CSS Extension - Editor Style Injection
 *
 * Loaded eagerly, unlike the inspector panel: the BlockListBlock wrapper must
 * be the same component whether or not a block has CSS, or the first
 * keystroke in an empty CSS field swaps the element type, remounts the block
 * and sends the rest of the typing into the block's content.
 *
 * @since 1.0.0
 */

import { useEffect } from '@wordpress/element';

/**
 * Dangerous CSS patterns that could be used for injection
 */
const DANGEROUS_CSS_PATTERNS =
	/javascript\s*:|expression\s*\(|behavior\s*:|url\s*\(\s*['"]?\s*data:/i;

/**
 * Whole `@import` statements, removed outright to match the front end (the
 * server strips every import: the footer stylesheet concatenates all blocks'
 * CSS, so an import is only valid for whichever block comes first).
 */
const IMPORT_STATEMENT = /@import\b[^;{}]*(?:;|$)/gim;

/**
 * Strings, comments and attribute selectors (returned untouched) or a
 * standalone `selector` word. Mirrors Custom_CSS_Renderer::replace_selector().
 */
const SELECTOR_TOKENS =
	/("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\/\*[\s\S]*?\*\/|\[[^\]]*\])|(?<![\w.#\\-])selector(?![\w-])/g;

/**
 * Sanitize CSS by removing dangerous patterns
 *
 * @param {string} css - Raw CSS string
 * @return {string} Sanitized CSS
 */
function sanitizeCSS(css) {
	if (!css) {
		return '';
	}
	return css
		.replace(IMPORT_STATEMENT, '')
		.replace(/@import\b/gi, '')
		.replace(DANGEROUS_CSS_PATTERNS, '/* removed */');
}

/**
 * Replace "selector" keyword with actual CSS class
 *
 * @param {string} css       - User's CSS code
 * @param {string} className - Actual CSS class to use
 * @return {string} Processed CSS with selector replaced
 */
export function replaceSelector(css, className) {
	if (!css) {
		return '';
	}
	return sanitizeCSS(css).replace(SELECTOR_TOKENS, (match, protectedToken) =>
		protectedToken ? protectedToken : `.${className}`
	);
}

/**
 * Custom CSS editor style injection wrapper
 *
 * Renders for every block that supports custom CSS; it adds the class and
 * style element only while the block has CSS.
 *
 * @param {Object} props                Block list block props
 * @param {Object} props.BlockListBlock Original BlockListBlock component
 */
export function CustomCSSStyles({ BlockListBlock, ...props }) {
	const { attributes, clientId } = props;
	const { dsgoCustomCSS } = attributes;

	const customClassName = `dsgo-custom-css-${clientId}`;
	const styleId = `dsgo-custom-css-style-${clientId}`;

	// Inject styles into editor with "selector" replaced by actual class
	useEffect(() => {
		if (!dsgoCustomCSS) {
			return;
		}

		const editorDocument =
			document.querySelector('iframe[name="editor-canvas"]')
				?.contentDocument || document;

		const existingStyle = editorDocument.getElementById(styleId);
		if (existingStyle) {
			existingStyle.remove();
		}

		const styleElement = editorDocument.createElement('style');
		styleElement.id = styleId;
		styleElement.textContent = replaceSelector(
			dsgoCustomCSS,
			customClassName
		);
		editorDocument.head.appendChild(styleElement);

		return () => {
			const styleToRemove = editorDocument.getElementById(styleId);
			if (styleToRemove) {
				styleToRemove.remove();
			}
		};
	}, [dsgoCustomCSS, clientId, styleId, customClassName]);

	if (!dsgoCustomCSS) {
		return <BlockListBlock {...props} />;
	}

	return (
		<BlockListBlock
			{...props}
			className={`${props.className || ''} ${customClassName}`.trim()}
		/>
	);
}
