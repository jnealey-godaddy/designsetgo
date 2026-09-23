/**
 * Extension toggles
 *
 * Switching an extension off on the Blocks & Extensions screen must remove
 * its editor controls, and nothing else: its attributes and save props stay
 * registered so content already built with it still validates.
 */

import { isExtensionEnabled } from '../../../src/utils/is-extension-enabled';

// [ module, extension names that gate it, controls filter, save-props filter ]
const EXTENSIONS = [
	[
		'block-animations/editor',
		['animation', 'block-animations'],
		'designsetgo/block-animations/with-controls',
		'designsetgo/block-animations/save-props',
	],
	[
		'background-video/index',
		['background-video'],
		'designsetgo/background-video-controls',
		'designsetgo/background-video-save-props',
	],
	[
		'clickable-group/index',
		['clickable-group'],
		'designsetgo/clickable-group-controls',
		'designsetgo/clickable-group-save-props',
	],
	[
		'custom-css/index',
		['custom-css'],
		'designsetgo/add-custom-css-control',
		'designsetgo/apply-custom-css-class',
	],
	[
		'grid-span/index',
		['grid-span'],
		'designsetgo/add-grid-span-controls',
		'designsetgo/apply-grid-span-styles',
	],
	[
		'max-width/index',
		['max-width'],
		'designsetgo/add-max-width-control',
		'designsetgo/apply-max-width-styles',
	],
	[
		'responsive/index',
		['responsive'],
		'designsetgo/add-responsive-visibility-control',
		'designsetgo/apply-responsive-visibility-classes',
	],
	[
		'reveal-control/index',
		['reveal-control'],
		'designsetgo/reveal-control-edit',
		'designsetgo/reveal-control-save',
	],
	[
		'sticky-header-controls/index',
		['sticky-header-controls'],
		'designsetgo/sticky-header-controls',
		'designsetgo/sticky-header-classes',
	],
	[
		'text-alignment-inheritance/index',
		['text-alignment-inheritance'],
		'designsetgo/with-alignment-inheritance',
		null,
	],
	[
		'expanding-background/editor',
		['expanding-background'],
		'designsetgo/expanding-background-controls',
		'designsetgo/expanding-background-save-props',
	],
	[
		'text-reveal/editor',
		['text-reveal'],
		'designsetgo/text-reveal/with-controls',
		'designsetgo/text-reveal/save-props',
	],
	[
		'vertical-scroll-parallax/editor',
		['vertical-scroll-parallax'],
		'designsetgo/vertical-scroll-parallax/controls',
		'designsetgo/vertical-scroll-parallax/save-props',
	],
];

/**
 * Load an extension module in isolation with the given disabled list and
 * return the hooks instance it registered on.
 *
 * @param {string}   module             Path under src/extensions/.
 * @param {string[]} disabledExtensions Value for dsgoSettings.disabledExtensions.
 * @return {Object} The module's @wordpress/hooks instance.
 */
function loadWith(module, disabledExtensions) {
	window.dsgoSettings = { disabledExtensions };
	let hooks;
	jest.isolateModules(() => {
		hooks = require('@wordpress/hooks');
		require(`../../../src/extensions/${module}`);
	});
	return hooks;
}

describe('isExtensionEnabled', () => {
	afterEach(() => {
		delete window.dsgoSettings;
	});

	it('treats an extension as enabled when nothing is localized', () => {
		expect(isExtensionEnabled('custom-css')).toBe(true);
	});

	it('is false only for listed extensions', () => {
		window.dsgoSettings = { disabledExtensions: ['custom-css'] };
		expect(isExtensionEnabled('custom-css')).toBe(false);
		expect(isExtensionEnabled('max-width')).toBe(true);
		expect(isExtensionEnabled('added-in-a-later-release')).toBe(true);
	});

	it('requires every name when given several', () => {
		window.dsgoSettings = { disabledExtensions: ['animation'] };
		expect(isExtensionEnabled('animation', 'block-animations')).toBe(false);
	});
});

describe.each(EXTENSIONS)(
	'%s',
	(module, names, controlsFilter, savePropsFilter) => {
		afterEach(() => {
			delete window.dsgoSettings;
		});

		it('registers its controls when enabled', () => {
			const hooks = loadWith(module, []);
			expect(hooks.hasFilter('editor.BlockEdit', controlsFilter)).toBe(
				true
			);
		});

		it.each(names)(
			'drops only its controls when %s is disabled',
			(name) => {
				const hooks = loadWith(module, [name]);
				expect(
					hooks.hasFilter('editor.BlockEdit', controlsFilter)
				).toBe(false);
				expect(hooks.hasFilter('blocks.registerBlockType')).toBe(true);
				if (savePropsFilter) {
					expect(
						hooks.hasFilter(
							'blocks.getSaveContent.extraProps',
							savePropsFilter
						)
					).toBe(true);
				}
			}
		);
	}
);
