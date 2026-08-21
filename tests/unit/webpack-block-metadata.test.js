const fs = require('fs');
const path = require('path');

describe('webpack block metadata', () => {
	it('copies every block manifest into the generated build directory', () => {
		const config = fs.readFileSync(
			path.join(__dirname, '../../webpack.config.js'),
			'utf8'
		);

		expect(config).toMatch(/from:\s*'src\/blocks\/\*\/block\.json'/);
	});
});
