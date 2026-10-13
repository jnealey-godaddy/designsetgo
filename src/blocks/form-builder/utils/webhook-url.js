/**
 * Form Builder - why a webhook URL can't receive submissions.
 *
 * A hint for the editor only. The server decides at delivery
 * (Form_Webhook_Request::is_safe_url(): core's wp_http_validate_url() plus
 * the reserved ranges WordPress before 7.1 lets through), and it also
 * resolves host names, which the editor can't. This catches what an author
 * can fix while typing: a malformed address, a non-web scheme, or a private
 * or reserved IP address and localhost name.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

/**
 * Whether an IPv4 address is private, loopback or otherwise reserved.
 *
 * @param {string} host Host name.
 * @return {boolean} True for a reserved IPv4 literal.
 */
function isReservedIPv4(host) {
	const match = host.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
	if (!match) {
		return false;
	}
	const [a, b] = match.slice(1, 3).map(Number);
	return (
		a === 0 || // 0.0.0.0/8
		a === 10 || // 10.0.0.0/8
		a === 127 || // loopback
		(a === 100 && b >= 64 && b <= 127) || // CGNAT
		(a === 169 && b === 254) || // link-local, cloud metadata
		(a === 172 && b >= 16 && b <= 31) || // 172.16.0.0/12
		(a === 192 && b === 168) // 192.168.0.0/16
	);
}

/**
 * Explain why a webhook URL can't receive submissions.
 *
 * @param {string} value    Webhook URL as typed.
 * @param {string} siteHost This site's host name; the site may post to itself.
 * @return {string} Problem to show, or '' when the URL looks deliverable.
 */
export function webhookUrlProblem(value, siteHost = '') {
	const url = String(value || '').trim();
	if (!url) {
		return '';
	}

	let parsed;
	try {
		parsed = new URL(url);
	} catch {
		return __(
			'Enter a full web address, starting with https://',
			'designsetgo'
		);
	}
	if (!['http:', 'https:'].includes(parsed.protocol) || !parsed.hostname) {
		return __(
			'Enter a full web address, starting with https://',
			'designsetgo'
		);
	}

	const host = parsed.hostname.toLowerCase().replace(/^\[|\]$/g, '');
	if (siteHost && host === siteHost.toLowerCase()) {
		return '';
	}
	if (
		host === 'localhost' ||
		host.endsWith('.localhost') ||
		host === '::1' ||
		isReservedIPv4(host)
	) {
		return __(
			'This address is on a private network, so submissions can’t be delivered to it. Use the public URL your service gives you.',
			'designsetgo'
		);
	}

	return '';
}
