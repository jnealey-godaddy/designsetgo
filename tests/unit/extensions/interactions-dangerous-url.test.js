import {
	isDangerousUrl,
	isAttributeAllowed,
} from '../../../src/extensions/interactions/actions';

// Built from char codes so the test file itself stays free of raw control
// characters.
const TAB = String.fromCharCode(9);
const LF = String.fromCharCode(10);
const CR = String.fromCharCode(13);
const NUL = String.fromCharCode(0);

describe('isDangerousUrl', () => {
	it.each([
		['plain', 'javascript:alert(1)'],
		['uppercase', 'JAVASCRIPT:alert(1)'],
		['mixed case', 'JaVaScRiPt:alert(1)'],
		['leading space', '  javascript:alert(1)'],
		['data URL', 'data:text/html,<script>alert(1)</script>'],
		['vbscript', 'vbscript:msgbox(1)'],
	])('rejects %s', (_name, value) => {
		expect(isDangerousUrl(value)).toBe(true);
	});

	// The evasion this guard exists for. Browsers strip ASCII tab, newline and
	// carriage return from anywhere in a URL before resolving the scheme, so
	// these are inert to a naive regex but live script to the browser.
	it.each([
		['embedded tab', 'jav' + TAB + 'ascript:alert(1)'],
		['embedded newline', 'jav' + LF + 'ascript:alert(1)'],
		['embedded carriage return', 'java' + CR + 'script:alert(1)'],
		['tab before colon', 'javascript' + TAB + ':alert(1)'],
		[
			'multiple splits',
			'j' + TAB + 'av' + LF + 'ascr' + CR + 'ipt:alert(1)',
		],
		['leading NUL', NUL + 'javascript:alert(1)'],
		['split data URL', 'da' + TAB + 'ta:text/html,<script>x</script>'],
	])('rejects %s', (_name, value) => {
		expect(isDangerousUrl(value)).toBe(true);
	});

	it.each([
		['https', 'https://example.com'],
		['relative', '/about'],
		['fragment', '#section'],
		['mailto', 'mailto:hi@example.com'],
		['tel', 'tel:+15551234'],
		// "javascript" appearing later in the URL is not a scheme.
		['path mentioning javascript', 'https://example.com/javascript:foo'],
		['query mentioning javascript', 'https://example.com/?x=javascript:1'],
	])('allows %s', (_name, value) => {
		expect(isDangerousUrl(value)).toBe(false);
	});

	it('handles a missing value', () => {
		expect(isDangerousUrl(undefined)).toBe(false);
		expect(isDangerousUrl(null)).toBe(false);
		expect(isDangerousUrl('')).toBe(false);
	});
});

describe('isAttributeAllowed with obfuscated URLs', () => {
	it('refuses href with a tab-split scheme', () => {
		expect(
			isAttributeAllowed('href', 'jav' + TAB + 'ascript:alert(1)')
		).toBe(false);
	});

	it('refuses formaction with a newline-split scheme', () => {
		expect(
			isAttributeAllowed('formaction', 'jav' + LF + 'ascript:alert(1)')
		).toBe(false);
	});

	it('still allows a normal URL containing whitespace elsewhere', () => {
		// Stripping is only used to resolve the scheme; the value itself is
		// written unchanged.
		expect(isAttributeAllowed('href', 'https://example.com/a b')).toBe(
			true
		);
	});

	it('leaves non-URL attributes unaffected by the scheme check', () => {
		expect(
			isAttributeAllowed('title', 'jav' + TAB + 'ascript:alert(1)')
		).toBe(true);
	});
});
