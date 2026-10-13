import { webhookUrlProblem } from '../webhook-url';

describe('webhookUrlProblem', () => {
	it.each([
		'',
		'https://hooks.zapier.com/hooks/catch/123/abc/',
		'http://example.com/receiver',
		'https://8.8.8.8/hook',
		'https://172.32.0.1/hook',
	])('accepts %p', (url) => {
		expect(webhookUrlProblem(url)).toBe('');
	});

	it.each([
		'not a url',
		'ftp://example.com/x',
		'example.com/hook',
		'https://',
	])('asks for a full web address for %p', (url) => {
		expect(webhookUrlProblem(url)).toMatch(/full web address/);
	});

	it.each([
		'http://169.254.169.254/latest/meta-data',
		'http://100.64.0.1/x',
		'http://10.0.0.5/x',
		'http://192.168.1.10/x',
		'http://172.16.0.1/x',
		'http://127.0.0.1:8080/x',
		'http://0.0.0.0/x',
		'http://localhost/x',
		'http://app.localhost/x',
		'http://[::1]/x',
	])('flags the private address %p', (url) => {
		expect(webhookUrlProblem(url)).toMatch(/private network/);
	});

	it('lets the site post to itself, as the server does', () => {
		expect(
			webhookUrlProblem('http://localhost/?rest_route=/x', 'localhost')
		).toBe('');
	});
});
