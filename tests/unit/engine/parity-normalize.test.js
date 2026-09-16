/**
 * Unit coverage for `tests/e2e/helpers/engine-parity.js`, the normalizer the
 * Node/browser parity e2e spec (`tests/e2e/agent-engine-parity.spec.js`)
 * relies on to compare only DesignSetGo block structure while ignoring core
 * block markup, which can differ between Node's pinned WordPress 6.7 and
 * whatever WordPress the live editor runs. This is the normalizer's only
 * direct test — the e2e spec must not be the sole thing exercising it.
 */

const {
	CORE_PLACEHOLDER,
	extractDesignSetGoRegions,
} = require('../../e2e/helpers/engine-parity');

describe('extractDesignSetGoRegions', () => {
	it('returns a single top-level DesignSetGo region untouched when it holds no core blocks', () => {
		const markup =
			'<!-- wp:designsetgo/row -->\n<div class="dsgo-row"><!-- wp:designsetgo/icon-button /--></div>\n<!-- /wp:designsetgo/row -->';

		expect(extractDesignSetGoRegions(markup)).toEqual([markup]);
	});

	it('collapses a core block (comment pair + inner HTML) to a single placeholder', () => {
		const markup =
			'<!-- wp:designsetgo/section -->\n<div class="dsgo-section"><!-- wp:core/paragraph -->\n<p>Hello there</p>\n<!-- /wp:core/paragraph --></div>\n<!-- /wp:designsetgo/section -->';

		const [region] = extractDesignSetGoRegions(markup);

		expect(region).toContain(CORE_PLACEHOLDER);
		expect(region).not.toContain('Hello there');
		expect(region).not.toContain('wp:core/paragraph');
	});

	it('collapses a core block nested inside another core block to a single placeholder (no double-collapse)', () => {
		const markup =
			'<!-- wp:designsetgo/section -->' +
			'<!-- wp:core/group --><div class="wp-block-group">' +
			'<!-- wp:core/paragraph --><p>Nested</p><!-- /wp:core/paragraph -->' +
			'</div><!-- /wp:core/group -->' +
			'<!-- /wp:designsetgo/section -->';

		const [region] = extractDesignSetGoRegions(markup);
		const placeholderCount = region.split(CORE_PLACEHOLDER).length - 1;

		expect(placeholderCount).toBe(1);
		expect(region).not.toContain('Nested');
		expect(region).not.toContain('wp:core/group');
	});

	it('keeps a DesignSetGo block nested inside a DesignSetGo block and collapses core content within it', () => {
		const markup =
			'<!-- wp:designsetgo/accordion -->' +
			'<!-- wp:designsetgo/accordion-item {"title":"Q1"} -->' +
			'<!-- wp:core/paragraph --><p>A1</p><!-- /wp:core/paragraph -->' +
			'<!-- /wp:designsetgo/accordion-item -->' +
			'<!-- /wp:designsetgo/accordion -->';

		const [region] = extractDesignSetGoRegions(markup);

		expect(region).toContain(
			'<!-- wp:designsetgo/accordion-item {"title":"Q1"} -->'
		);
		expect(region).toContain('<!-- /wp:designsetgo/accordion-item -->');
		expect(region).toContain(CORE_PLACEHOLDER);
		expect(region).not.toContain('A1');
	});

	it("preserves a DesignSetGo block nested inside a core block, collapsing only the core block's own markup (core-in-DesignSetGo-in-core)", () => {
		// Outer DesignSetGo > core/group > inner DesignSetGo > core/paragraph.
		// The core/group's own comment + HTML collapses, but the DesignSetGo
		// block nested one level deeper is real DesignSetGo structure and must
		// still surface in the comparison — the walk does not stop at the
		// first collapsed ancestor. Its own core child ("Deep") still
		// collapses, since that one has no further DesignSetGo descendant.
		const markup =
			'<!-- wp:designsetgo/section -->' +
			'<!-- wp:core/group -->' +
			'<!-- wp:designsetgo/icon-button -->' +
			'<!-- wp:core/paragraph --><p>Deep</p><!-- /wp:core/paragraph -->' +
			'<!-- /wp:designsetgo/icon-button -->' +
			'<!-- /wp:core/group -->' +
			'<!-- /wp:designsetgo/section -->';

		const [region] = extractDesignSetGoRegions(markup);

		expect(region).toContain(
			'<!-- wp:designsetgo/icon-button -->' +
				CORE_PLACEHOLDER +
				'<!-- /wp:designsetgo/icon-button -->'
		);
		expect(region).not.toContain('Deep');
		// One placeholder for core/group's own markup, one for the collapsed
		// core/paragraph nested inside the preserved icon-button.
		const placeholderCount = region.split(CORE_PLACEHOLDER).length - 1;
		expect(placeholderCount).toBe(2);
	});

	it('produces unequal output when a byte differs inside a DesignSetGo block nested in core', () => {
		const build = (text) =>
			'<!-- wp:designsetgo/section -->' +
			'<!-- wp:core/group -->' +
			`<!-- wp:designsetgo/pill {"content":"${text}"} /-->` +
			'<!-- /wp:core/group -->' +
			'<!-- /wp:designsetgo/section -->';

		const [regionA] = extractDesignSetGoRegions(build('Alpha'));
		const [regionB] = extractDesignSetGoRegions(build('Alphb'));

		expect(regionA).not.toEqual(regionB);
	});

	it('returns a top-level self-closing (void) DesignSetGo block unchanged', () => {
		const markup = '<!-- wp:designsetgo/spacer {"height":"40px"} /-->';

		expect(extractDesignSetGoRegions(markup)).toEqual([markup]);
	});

	it('collapses a self-closing core block nested inside a DesignSetGo block', () => {
		const markup =
			'<!-- wp:designsetgo/row -->' +
			'<div><!-- wp:core/separator /--></div>' +
			'<!-- /wp:designsetgo/row -->';

		const [region] = extractDesignSetGoRegions(markup);

		expect(region).toContain(CORE_PLACEHOLDER);
		expect(region).not.toContain('core/separator');
	});

	it('extracts multiple top-level regions in document order and drops non-DesignSetGo top-level content', () => {
		const markup =
			'<!-- wp:core/paragraph --><p>Intro</p><!-- /wp:core/paragraph -->' +
			'<!-- wp:designsetgo/section {"id":"one"} --><p>One</p><!-- /wp:designsetgo/section -->' +
			'<!-- wp:core/more /-->' +
			'<!-- wp:designsetgo/section {"id":"two"} --><p>Two</p><!-- /wp:designsetgo/section -->';

		const regions = extractDesignSetGoRegions(markup);

		expect(regions).toHaveLength(2);
		expect(regions[0]).toContain('"id":"one"');
		expect(regions[1]).toContain('"id":"two"');
	});

	it('correctly walks deeply nested attrs JSON (objects and arrays) without losing tag boundaries', () => {
		const markup =
			'<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","values":[1,2,3]}}}} -->' +
			'<!-- wp:core/paragraph --><p>Body</p><!-- /wp:core/paragraph -->' +
			'<!-- /wp:designsetgo/section -->';

		const [region] = extractDesignSetGoRegions(markup);

		expect(region).toContain(
			'<!-- wp:designsetgo/section {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","values":[1,2,3]}}}} -->'
		);
		expect(region).toContain(CORE_PLACEHOLDER);
	});

	it('does not miscount braces that appear inside a quoted attribute string value', () => {
		const markup =
			'<!-- wp:designsetgo/pill {"content":"Use {curly} braces"} --><p>x</p><!-- /wp:designsetgo/pill -->';

		expect(extractDesignSetGoRegions(markup)).toEqual([markup]);
	});

	it('throws a clear error on a block comment missing its closing pair', () => {
		const markup = '<!-- wp:designsetgo/section --><p>Body</p>';

		expect(() => extractDesignSetGoRegions(markup)).toThrow(
			/missing its closing comment/
		);
	});
});
