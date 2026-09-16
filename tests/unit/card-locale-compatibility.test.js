/** Saved card labels must survive a change of editor language. */
import {
	createBlock,
	parse,
	serialize,
	unregisterBlockType,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { resetLocaleData } from '@wordpress/i18n';
import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';
import fs from 'fs';
import path from 'path';
import { createHash } from 'crypto';

const name = 'designsetgo/card';
const patternSlugs = [
	'content/content-portfolio-cards',
	'pricing/pricing-cards',
	'team/team-grid',
];
const fixtures = patternSlugs.flatMap((slug) => {
	const source = fs.readFileSync(
		path.join(__dirname, '../../patterns', `${slug}.php`),
		'utf8'
	);
	return [
		...source.matchAll(
			/<!-- wp:designsetgo\/card [\s\S]*?<!-- \/wp:designsetgo\/card -->/g
		),
	].map(([html], index) => [
		`${slug} #${index + 1}`,
		// Isolate the card; E2E checks cover the nested CTA blocks.
		html
			.replace(
				/<!-- wp:designsetgo\/icon-button [\s\S]*?<!-- \/wp:designsetgo\/icon-button -->/g,
				''
			)
			.replace(
				/\{\{dsgo:placeholder-[^}]+\}\}/g,
				'https://example.com/image.jpg'
			),
	]);
});

function loadLocale(locale) {
	const hash = createHash('md5')
		.update('build/blocks/card/index.js')
		.digest('hex');
	const messages =
		locale === 'en_US'
			? {}
			: JSON.parse(
					fs.readFileSync(
						path.join(
							__dirname,
							'../../languages',
							`designsetgo-${locale}-${hash}.json`
						),
						'utf8'
					)
				).locale_data.messages;
	resetLocaleData(messages, 'designsetgo');
}

beforeAll(() => registerDesignSetGoBlock(name));
afterAll(() => unregisterBlockType(name));
afterEach(() => resetLocaleData({}, 'designsetgo'));

describe.each(['en_US', 'de_DE', 'fr_FR'])(
	'opening existing content in %s',
	(locale) => {
		beforeEach(() => loadLocale(locale));
		it.each(fixtures)(
			'preserves %s and its saved labels',
			(description, html) => {
				const [block] = parse(html);
				expect(block.isValid).toBe(true);
				expect(block.validationIssues).toEqual([]);
				const saved = serialize(block);
				// Comparing the HTML also protects authored text, styles, and alt text.
				const content = (value) => {
					const element = document.createElement('template');
					element.innerHTML = value
						.replace(/^<!--[\s\S]*?-->\s*/, '')
						.replace(/\s*<!-- \/wp:designsetgo\/card -->$/, '')
						.replace(/>\s+</g, '><');
					return element.innerHTML;
				};
				expect(content(saved)).toBe(content(html));
				loadLocale(locale === 'en_US' ? 'de_DE' : 'en_US');
				const [reopened] = parse(saved);
				expect(reopened.isValid).toBe(true);
				expect(serialize(reopened)).toBe(saved);
			}
		);
	}
);

it.each([
	['en_US', 'de_DE'],
	['de_DE', 'fr_FR'],
	['fr_FR', 'en_US'],
])(
	'preserves a newly saved %s card when reopened in %s',
	(authorLocale, editorLocale) => {
		loadLocale(authorLocale);
		const saved = serialize(
			createBlock(name, {
				badgeText: 'Featured',
				title: 'Our team',
				imageUrl: 'https://example.com/team.jpg',
				showCta: false,
			})
		);
		loadLocale(editorLocale);
		const [block] = parse(saved);
		expect(block.isValid).toBe(true);
		expect(serialize(block)).toBe(saved);
	}
);

it('keeps authored alt text editable and separate from decorative fallback text', () => {
	loadLocale('en_US');
	const saved = serialize(
		createBlock(name, {
			imageUrl: 'https://example.com/team.jpg',
			imageAlt: 'Our team outside the studio',
			showCta: false,
		})
	);
	loadLocale('de_DE');
	const [block] = parse(saved);
	expect(block.isValid).toBe(true);
	expect(block.attributes.imageAlt).toBe('Our team outside the studio');
	expect(block.attributes.imageFallbackAlt).toBeUndefined();
	block.attributes.imageAlt = '';
	const cleared = serialize(block);
	expect(cleared).not.toContain('Our team outside the studio');
	expect(cleared).toContain('aria-hidden="true"');
	expect(parse(cleared)[0].isValid).toBe(true);
});

it('migrates hidden legacy text without invalidating its English image label', () => {
	loadLocale('de_DE');
	const original = fixtures.find(
		([label]) => label === 'team/team-grid #1'
	)[1];
	const html = original
		.replace(
			'"title":"Alex Morgan"',
			'"showTitle":false,"title":"Alex Morgan"'
		)
		.replace('<h3 class="dsgo-card__title">Alex Morgan</h3>', '');
	const [block] = parse(html);
	expect(block.isValid).toBe(true);
	expect(block.attributes.title).toBe('Alex Morgan');
	expect(block.attributes.showTitle).toBe(false);
	const saved = serialize(block);
	expect(saved).toContain('dsgo-card__title--hidden');
	loadLocale('fr_FR');
	expect(parse(saved)[0].isValid).toBe(true);
});
