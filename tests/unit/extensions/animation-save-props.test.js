/**
 * Save-props regression tests for the animation extensions.
 *
 * Both cases here are about markup the editor must NOT emit:
 * an attribute that would invalidate already-saved content, and an
 * animation the frontend can never fire.
 */

import { applyFilters } from '@wordpress/hooks';

import '../../../src/extensions/block-animations/editor';
import '../../../src/extensions/text-reveal/editor';

const FILTER = 'blocks.getSaveContent.extraProps';

const save = (blockName, attributes) =>
	applyFilters(FILTER, {}, { name: blockName }, attributes);

describe('text-reveal save props', () => {
	const base = {
		dsgoTextRevealEnabled: true,
		dsgoTextRevealColor: '#2563eb',
		dsgoTextRevealSplitMode: 'word',
		dsgoTextRevealTransition: 150,
	};

	it('omits the effect attribute at its default so existing content stays valid', () => {
		const props = save('core/paragraph', {
			...base,
			dsgoTextRevealEffect: 'color',
		});

		expect(props).not.toHaveProperty('data-dsgo-text-reveal-effect');
		expect(props['data-dsgo-text-reveal-enabled']).toBe('true');
	});

	it('omits the effect attribute when the value is absent entirely', () => {
		const props = save('core/paragraph', base);

		expect(props).not.toHaveProperty('data-dsgo-text-reveal-effect');
	});

	it('emits the effect attribute once it differs from the default', () => {
		const props = save('core/paragraph', {
			...base,
			dsgoTextRevealEffect: 'rise',
		});

		expect(props['data-dsgo-text-reveal-effect']).toBe('rise');
	});
});

describe('block-animations save props', () => {
	const base = {
		dsgoAnimationEnabled: true,
		dsgoEntranceAnimation: 'fade-in',
		dsgoAnimationTrigger: 'scroll',
		dsgoAnimationDuration: 600,
		dsgoAnimationDelay: 0,
		dsgoAnimationEasing: 'ease-out',
		dsgoAnimationOffset: 0,
		dsgoAnimationOnce: true,
	};

	it('emits the exit animation normally', () => {
		const props = save('core/group', {
			...base,
			dsgoExitAnimation: 'fade-out',
		});

		expect(props['data-dsgo-exit-animation']).toBe('fade-out');
		expect(props.className).toContain('dsgo-animation-exit-fade-out');
	});

	it('drops the exit animation when scrubbing, which never fires it', () => {
		const props = save('core/group', {
			...base,
			dsgoExitAnimation: 'fade-out',
			dsgoScrollLinked: true,
		});

		expect(props['data-dsgo-scroll-linked']).toBe('true');
		expect(props).not.toHaveProperty('data-dsgo-exit-animation');
		expect(props.className).not.toContain('dsgo-animation-exit-fade-out');
	});

	it('keeps the exit animation when scrubbing has no entrance to drive', () => {
		const props = save('core/group', {
			...base,
			dsgoEntranceAnimation: '',
			dsgoExitAnimation: 'fade-out',
			dsgoScrollLinked: true,
		});

		expect(props).not.toHaveProperty('data-dsgo-scroll-linked');
		expect(props['data-dsgo-exit-animation']).toBe('fade-out');
	});
});
