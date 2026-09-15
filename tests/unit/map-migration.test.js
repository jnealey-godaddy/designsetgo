/**
 * Historical maps must migrate regardless of the editor's current language.
 */
import {
	parse,
	serialize,
	unregisterBlockType,
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { resetLocaleData } from '@wordpress/i18n';
import { registerDesignSetGoBlock } from '../../tools/regenerate-patterns';
import fs from 'fs';
import path from 'path';
import { createHash } from 'crypto';

const name = 'designsetgo/map';
// Handwritten historical contact-pattern markup, including its border style.
const legacy = `<!-- wp:designsetgo/map {"dsgoAddress":"{{address}}","dsgoLatitude":0,"dsgoLongitude":0,"style":{"border":{"radius":"16px"}}} -->
<div class="wp-block-designsetgo-map dsgo-map" style="border-radius:16px;height:400px" data-dsgo-provider="openstreetmap" data-dsgo-lat="0" data-dsgo-lng="0" data-dsgo-zoom="13" data-dsgo-address="{{address}}" data-dsgo-marker-icon="📍" data-dsgo-marker-color="#e74c3c" data-dsgo-privacy-mode="false" data-dsgo-map-style="standard"><div class="dsgo-map__container" role="region" aria-label="Map showing {{address}}"></div></div>
<!-- /wp:designsetgo/map -->`;
const icon =
	'<svg class="dsgo-map__privacy-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>';
const privacy = legacy
	.replace(
		'"dsgoLatitude":0',
		'"dsgoPrivacyMode":true,"dsgoPrivacyNotice":"Our privacy notice","dsgoLatitude":0'
	)
	.replace('dsgo-map" style=', 'dsgo-map dsgo-map--privacy-mode" style=')
	.replace('data-dsgo-privacy-mode="false"', 'data-dsgo-privacy-mode="true"')
	.replace(
		'<div class="dsgo-map__container" role="region" aria-label="Map showing {{address}}"></div>',
		'<div class="dsgo-map__privacy-overlay"><div class="dsgo-map__privacy-content">' +
			icon +
			'<p class="dsgo-map__privacy-text">Our privacy notice</p><button class="dsgo-map__load-button" type="button" aria-label="Load map. This will connect to external map services.">Load Map</button></div></div>'
	);
const v1 = legacy
	.replace(
		'"dsgoLatitude":0',
		'"dsgoMarkerPopup":"Old popup","dsgoGrayscale":true,"dsgoLatitude":0'
	)
	.replace('dsgo-map" style=', 'dsgo-map dsgo-map--grayscale" style=')
	.replace(
		'data-dsgo-privacy-mode=',
		'data-dsgo-marker-popup="Old popup" data-dsgo-grayscale="true" data-dsgo-privacy-mode='
	);

beforeAll(() => registerDesignSetGoBlock(name));
afterAll(() => unregisterBlockType(name));
afterEach(() => resetLocaleData({}, 'designsetgo'));

function loadCatalog(locale) {
	const hash = createHash('md5')
		.update('build/blocks/map/index.js')
		.digest('hex');
	const catalog = JSON.parse(
		fs.readFileSync(
			path.join(
				__dirname,
				'../../languages',
				`designsetgo-${locale}-${hash}.json`
			),
			'utf8'
		)
	);
	resetLocaleData(catalog.locale_data.messages, 'designsetgo');
}

describe.each(['en_US', 'de_DE', 'fr_FR'])(
	'with %s editor translations',
	(locale) => {
		beforeEach(() => {
			if (locale !== 'en_US') {
				loadCatalog(locale);
			}
		});
		it.each([
			['English map', legacy],
			[
				'French map',
				legacy.replace(
					'Map showing {{address}}',
					'Carte de {{address}}'
				),
			],
			[
				'addressless map',
				legacy
					.replace('"dsgoAddress":"{{address}}"', '"dsgoAddress":""')
					.replace(
						'data-dsgo-address="{{address}}"',
						'data-dsgo-address=""'
					)
					.replace('Map showing {{address}}', 'Interactive map'),
			],
			['English privacy overlay', privacy],
			[
				'French privacy overlay',
				privacy
					.replace('>Load Map<', '>Charger la carte<')
					.replace(
						'Load map. This will connect to external map services.',
						'Charger la carte depuis des services externes.'
					),
			],
			['v1 map with removed attributes', v1],
		])(
			'migrates %s without saving historical UI labels',
			(description, html) => {
				const [block] = parse(html);
				expect(block.isValid).toBe(true);
				expect(block.validationIssues).toEqual([]);
				expect(console).toHaveInformed();
				expect(block.attributes).toMatchObject({
					dsgoLatitude: 0,
					dsgoLongitude: 0,
					style: { border: { radius: '16px' } },
				});
				if (html.includes('Our privacy notice')) {
					expect(block.attributes.dsgoPrivacyNotice).toBe(
						'Our privacy notice'
					);
				}
				expect(block.attributes).not.toHaveProperty('dsgoMarkerPopup');
				expect(block.attributes).not.toHaveProperty('dsgoGrayscale');
				const saved = serialize(block);
				expect(saved).not.toContain('<div');
				expect(saved).not.toMatch(
					/legacy|Karte von|Map showing|Charger la carte/
				);
				const [reopened] = parse(saved);
				expect(reopened.isValid).toBe(true);
				expect(reopened.attributes).toEqual(block.attributes);
			}
		);
	}
);
