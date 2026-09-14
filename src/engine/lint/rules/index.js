/**
 * Registered lint rules, in application order.
 *
 * Each rule module exports `{ id, severity, check?(node, ctx), checkTree?(tree, ctx) }`
 * (see `src/engine/lint/index.js` for the exact contract).
 */
import noCustomHtml from './no-custom-html';
import imageAlt from './image-alt';
import headingOrder from './heading-order';
import contrast from './contrast';
import presetValues from './preset-values';
import preferDsgoLayout from './prefer-dsgo-layout';
import topLevelSections from './top-level-sections';
import mobileLayout from './mobile-layout';
import emptyContainer from './empty-container';

export const rules = [
	noCustomHtml,
	imageAlt,
	headingOrder,
	contrast,
	presetValues,
	preferDsgoLayout,
	topLevelSections,
	mobileLayout,
	emptyContainer,
];
