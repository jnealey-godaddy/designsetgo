/**
 * Registered lint rules, in application order.
 *
 * Each rule module exports `{ id, severity, check?(node, ctx), checkTree?(tree, ctx) }`
 * (see `src/engine/lint/index.js` for the exact contract).
 */
import noCustomHtml from './no-custom-html';
import imageAlt from './image-alt';
import headingOrder from './heading-order';

export const rules = [noCustomHtml, imageAlt, headingOrder];
