/**
 * Icon Button - core Button style mirroring is race-proof
 *
 * The Icon Button mirrors core/button's block style variations (Fill, Outline)
 * onto itself so the editor Styles panel lists them. The original implementation
 * read core/button's styles exactly once on domReady; when it lost the
 * registration-order race (core/button's styles not registered yet at that
 * moment) it mirrored nothing and the Icon Button ended up with zero styles for
 * the whole session, with no recovery.
 *
 * This test drives the losing side of that race explicitly: mirroring starts
 * while core/button has no styles, then core/button and its styles register
 * afterwards. The store subscription must catch up and mirror them.
 *
 * @package
 */

const {
	registerBlockType,
	unregisterBlockType,
	registerBlockStyle,
	store: blocksStore,
} = require('@wordpress/blocks');
import { select } from '@wordpress/data';

import { startMirroringButtonStyles } from '../../../../src/blocks/icon-button/mirror-button-styles';

const iconButtonStyleNames = () =>
	(select(blocksStore).getBlockStyles('designsetgo/icon-button') || []).map(
		(style) => style.name
	);

const DUMMY = {
	apiVersion: 3,
	// The custom "designsetgo" category isn't registered in the unit-test env;
	// use a core category to avoid a warning.
	category: 'design',
	attributes: {},
	save: () => null,
};

describe('Icon Button style mirroring survives registration-order races', () => {
	let stop;

	afterEach(() => {
		if (stop) {
			stop();
			stop = null;
		}
		['designsetgo/icon-button', 'core/button'].forEach((name) => {
			try {
				unregisterBlockType(name);
			} catch (e) {
				// not registered — ignore
			}
		});
	});

	it('mirrors core/button styles registered AFTER mirroring starts', () => {
		// Target exists, but core/button does not yet — the race the old code lost.
		registerBlockType('designsetgo/icon-button', {
			...DUMMY,
			title: 'Icon Button',
		});

		stop = startMirroringButtonStyles();

		// core/button and its styles arrive late.
		registerBlockType('core/button', { ...DUMMY, title: 'Button' });
		registerBlockStyle('core/button', {
			name: 'fill',
			label: 'Fill',
			isDefault: true,
		});
		registerBlockStyle('core/button', {
			name: 'outline',
			label: 'Outline',
		});

		expect(iconButtonStyleNames()).toEqual(
			expect.arrayContaining(['fill', 'outline'])
		);
	});

	it('mirrors styles already present when mirroring starts', () => {
		registerBlockType('core/button', { ...DUMMY, title: 'Button' });
		registerBlockStyle('core/button', {
			name: 'fill',
			label: 'Fill',
			isDefault: true,
		});
		registerBlockStyle('core/button', {
			name: 'outline',
			label: 'Outline',
		});
		registerBlockType('designsetgo/icon-button', {
			...DUMMY,
			title: 'Icon Button',
		});

		stop = startMirroringButtonStyles();

		expect(iconButtonStyleNames()).toEqual(
			expect.arrayContaining(['fill', 'outline'])
		);
	});
});
