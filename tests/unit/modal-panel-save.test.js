/**
 * Off-canvas panel mode on the modal block.
 *
 * The first two tests are the ones that matter for existing content: adding
 * displayMode/panelEdge/panelSize must leave a default-mode modal's stored
 * markup character-identical, or every modal already in the wild needs a
 * deprecation. See docs/plans/2026-08-16-offcanvas-panel.md.
 */

// Import the block API from the copy nested under @wordpress/block-editor — the
// SAME instance useBlockProps.save() talks to. Jest resolves two copies of
// @wordpress/blocks and save() renders empty across instances. See the header
// of tests/unit/deprecations-isEligible.test.js for the full explanation.
import {
	createBlock,
	serialize,
	parse,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import '../../src/blocks/modal';

describe('modal panel mode', () => {
	it('emits no panel class in the default dialog mode', () => {
		const block = createBlock('designsetgo/modal', { modalId: 'm1' });
		const html = serialize(block);
		expect(html).not.toContain('dsgo-modal--panel');
	});

	it('writes nothing about displayMode into the block comment by default', () => {
		const block = createBlock('designsetgo/modal', { modalId: 'm1' });
		const html = serialize(block);
		expect(html).not.toContain('displayMode');
		expect(html).not.toContain('panelEdge');
		expect(html).not.toContain('panelSize');
	});

	it('emits no panel size custom property in the default dialog mode', () => {
		const block = createBlock('designsetgo/modal', { modalId: 'm1' });
		expect(serialize(block)).not.toContain('--dsgo-panel-size');
	});

	it('emits the panel classes when panel mode is on', () => {
		const block = createBlock('designsetgo/modal', {
			modalId: 'm1',
			displayMode: 'panel',
			panelEdge: 'left',
		});
		const html = serialize(block);
		expect(html).toContain('dsgo-modal--panel');
		expect(html).toContain('dsgo-modal--panel-left');
	});

	it('sets the panel size custom property in panel mode', () => {
		const block = createBlock('designsetgo/modal', {
			modalId: 'm1',
			displayMode: 'panel',
			panelSize: '30rem',
		});
		expect(serialize(block)).toContain('--dsgo-panel-size:30rem');
	});

	it.each(['left', 'right', 'top', 'bottom'])(
		'round-trips a %s panel without becoming invalid',
		(edge) => {
			const block = createBlock('designsetgo/modal', {
				modalId: 'm1',
				displayMode: 'panel',
				panelEdge: edge,
			});
			const [reparsed] = parse(serialize(block));
			expect(reparsed.isValid).toBe(true);
			expect(reparsed.attributes.panelEdge).toBe(edge);
			expect(reparsed.attributes.displayMode).toBe('panel');
		}
	);

	it('keeps the dialog role and modal semantics in panel mode', () => {
		const block = createBlock('designsetgo/modal', {
			modalId: 'm1',
			displayMode: 'panel',
		});
		const html = serialize(block);
		expect(html).toContain('role="dialog"');
		expect(html).toContain('aria-modal="true"');
	});

	it('keeps the panel size property on the root, where the dialog can read it', () => {
		// transferStylesToContent() moves the wrapper's style object onto
		// .dsgo-modal__content. If --dsgo-panel-size rode along it would land
		// on a descendant of the element that consumes it, and every panel
		// would silently fall back to the stylesheet default.
		const block = createBlock('designsetgo/modal', {
			modalId: 'm1',
			displayMode: 'panel',
			panelSize: '30rem',
		});
		const html = serialize(block);
		const rootTag = html.slice(0, html.indexOf('>', html.indexOf('<div')));
		expect(rootTag).toContain('--dsgo-panel-size:30rem');
	});

	it('leaves the panel classes off the content element', () => {
		const block = createBlock('designsetgo/modal', {
			modalId: 'm1',
			displayMode: 'panel',
			panelEdge: 'left',
		});
		const html = serialize(block);
		const contentIndex = html.indexOf('dsgo-modal__content');
		const contentTagStart = html.lastIndexOf('<div', contentIndex);
		const contentTag = html.slice(
			contentTagStart,
			html.indexOf('>', contentIndex)
		);
		expect(contentTag).not.toContain('dsgo-modal--panel');
	});
});
