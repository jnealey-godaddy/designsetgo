/**
 * Guard: a static block that saves a translated fallback label must stay valid
 * when the post is reopened in another editor language.
 *
 * `__()` in save() bakes the author's locale into post content. Without the
 * fix, reopening that content under a different locale regenerates the label
 * in the new language, the markup no longer matches, and the editor shows
 * "Attempt Block Recovery". Each fallback is now also read back from the
 * stored markup (a sourced attribute), so save() reproduces what was stored.
 */
import {
	createBlock,
	parse,
	serialize,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { resetLocaleData } from '@wordpress/i18n';

import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';

const GERMAN = {
	Modal: ['Dialogfenster'],
	'Close modal': ['Dialog schließen'],
	Hotspot: ['Markierung'],
	Popular: ['Beliebt'],
	'Get Started': ['Loslegen'],
};

const useLocale = (messages) =>
	resetLocaleData(
		{
			'': { domain: 'designsetgo' },
			...messages,
		},
		'designsetgo'
	);

// [ block, attributes, a fallback label the saved markup must keep ]
const CASES = [
	['designsetgo/modal', { modalId: 'modal-1' }, 'aria-label="Modal"'],
	['designsetgo/modal', { modalId: 'modal-1' }, 'aria-label="Close modal"'],
	['designsetgo/hotspot-item', { uniqueId: 'a1' }, 'aria-label="Hotspot"'],
	[
		'designsetgo/comparison-table',
		{
			columns: [
				{ name: 'Basic', link: '#a', linkText: '', featured: false },
				{ name: 'Pro', link: '', linkText: 'Soon', featured: true },
				{ name: 'Team', link: '#c', linkText: '', featured: false },
			],
		},
		'>Popular</span>',
	],
	[
		'designsetgo/comparison-table',
		{
			columns: [
				{ name: 'Basic', link: '#a', linkText: 'Buy', featured: false },
				{ name: 'Team', link: '#c', linkText: '', featured: false },
			],
		},
		'>Get Started</a>',
	],
];

beforeAll(() => {
	[...new Set(CASES.map(([name]) => name))].forEach(registerDesignSetGoBlock);
});

afterEach(() => useLocale({}));

describe.each(CASES)('%s', (name, attributes, fallback) => {
	it(`keeps ${fallback} valid when reopened in another language`, () => {
		useLocale({});
		const saved = serialize(createBlock(name, attributes));
		expect(saved).toContain(fallback);

		useLocale(GERMAN);
		const [block] = parse(saved);
		expect(block.isValid).toBe(true);
		expect(serialize(block)).toBe(saved);
	});
});

it('saves a new block in the editor language', () => {
	useLocale(GERMAN);
	const saved = serialize(
		createBlock('designsetgo/hotspot-item', { uniqueId: 'a1' })
	);
	expect(saved).toContain('aria-label="Markierung"');
});

it('keeps each CTA fallback with its own column', () => {
	useLocale(GERMAN);
	const saved = serialize(
		createBlock('designsetgo/comparison-table', {
			columns: [
				{ name: 'A', link: '#a', linkText: 'Buy', featured: false },
				{ name: 'B', link: '#b', linkText: '', featured: false },
			],
		})
	);

	useLocale({});
	const [block] = parse(saved);
	expect(block.isValid).toBe(true);
	expect(block.attributes.savedCtaTexts).toEqual([
		{ text: 'Buy' },
		{ text: 'Loslegen' },
	]);
});
