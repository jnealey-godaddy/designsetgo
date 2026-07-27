/**
 * resolveBlockAnimationDefault() — exclusion parity with the render-time
 * injector.
 *
 * Animation_Defaults_Injector::inject() skips a block that
 * Extension_Attributes::is_block_excluded() claims, so the editor's
 * "Inheriting theme animation" indicator must skip it too — otherwise the
 * editor promises an animation the frontend will never apply.
 */
import { resolveBlockAnimationDefault } from '../../../src/extensions/block-animations/resolve-default';
import { clearExclusionCache } from '../../../src/utils/should-extend-block';

describe('resolveBlockAnimationDefault — exclusions', () => {
	beforeEach(() => {
		clearExclusionCache();
		window.dsgoSettings = {
			blockAnimationsEnabled: true,
			excludedBlocks: [],
			blockAnimations: [
				{ block: 'core/*', entrance: 'fadeInUp', trigger: 'scroll' },
				{ block: 'acme/widget', entrance: 'zoomIn', trigger: 'scroll' },
			],
		};
	});

	afterEach(() => {
		delete window.dsgoSettings;
		clearExclusionCache();
	});

	it('resolves a wildcard entry for a block that is not excluded', () => {
		expect(resolveBlockAnimationDefault('core/heading')?.entrance).toBe(
			'fadeInUp'
		);
	});

	it('returns null for a block the user excluded, even under a matching wildcard', () => {
		// The `core/*` entry still resolves for every other core block, so the
		// exclusion can only be settled against the concrete name — which is
		// exactly the case the server-side list filter cannot cover.
		window.dsgoSettings.excludedBlocks = ['core/heading'];
		clearExclusionCache();

		expect(resolveBlockAnimationDefault('core/heading')).toBeNull();
		expect(resolveBlockAnimationDefault('core/paragraph')?.entrance).toBe(
			'fadeInUp'
		);
	});

	it('honours a namespace exclusion wildcard', () => {
		window.dsgoSettings.excludedBlocks = ['acme/*'];
		clearExclusionCache();

		expect(resolveBlockAnimationDefault('acme/widget')).toBeNull();
	});

	it('returns null for the block-animations extension’s own static exclusions', () => {
		// core/freeform and core-embed/* are excluded by the extension config
		// itself, which the injector reads via get_extension_exclusions().
		expect(resolveBlockAnimationDefault('core/freeform')).toBeNull();
		expect(resolveBlockAnimationDefault('core-embed/twitter')).toBeNull();
	});

	it('still returns null when the feature gate is off', () => {
		window.dsgoSettings.blockAnimationsEnabled = false;
		expect(resolveBlockAnimationDefault('core/heading')).toBeNull();
	});
});
