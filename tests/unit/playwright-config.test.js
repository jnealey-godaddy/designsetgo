const fs = require('fs');
const path = require('path');

describe('Playwright configuration', () => {
	it('does not abort the full cross-browser matrix with a global timeout', () => {
		const config = fs.readFileSync(
			path.join(__dirname, '../../playwright.config.js'),
			'utf8'
		);

		expect(config).toMatch(/globalTimeout:\s*0/);
	});
});
