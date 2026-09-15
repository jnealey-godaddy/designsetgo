/**
 * `includes/abilities/agent-build/data/attribute-manifest.json` is a
 * generated, committed file — `npm run engine -- attribute-manifest --out
 * <path>` regenerates it from the real block registry (see
 * `src/engine/node/attribute-manifest.js`). PHP's `Tree_Attributes` trusts
 * it to tell "known to JS but not to PHP's own `WP_Block_Type->attributes`"
 * (e.g. `anchor`, or any DesignSetGo extension attribute) apart from a
 * genuinely unknown name — so a stale manifest would silently start letting
 * real typos through PHP's up-front check. This test regenerates the
 * manifest in memory under Jest's own registration and fails loudly if it
 * doesn't match the committed file byte-for-byte.
 */
import fs from 'fs';
import path from 'path';
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { buildAttributeManifest } from '../../../src/engine/node/attribute-manifest';

const MANIFEST_PATH = path.resolve(
	__dirname,
	'../../../includes/abilities/agent-build/data/attribute-manifest.json'
);

describe('attribute manifest freshness', () => {
	beforeAll(() => {
		registerForJest();
	});

	it('the committed manifest file exists', () => {
		expect(fs.existsSync(MANIFEST_PATH)).toBe(true);
	});

	it('matches what buildAttributeManifest() produces right now', () => {
		const committed = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf8'));
		const generated = buildAttributeManifest(blocksApi);

		expect(committed).toEqual(generated);
	});

	it('covers a designsetgo block and a core block the brief calls out', () => {
		const generated = buildAttributeManifest(blocksApi);

		expect(generated['designsetgo/section']).toContain('anchor');
		expect(generated['core/paragraph']).toContain('anchor');
	});
});
