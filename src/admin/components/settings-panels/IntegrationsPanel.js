/**
 * Integrations Settings Panel
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useCopyToClipboard } from '@wordpress/compose';
import {
	Card,
	CardHeader,
	CardBody,
	TextControl,
	ExternalLink,
	Button,
	Notice,
} from '@wordpress/components';

/**
 * A 32-byte random secret, hex-encoded.
 *
 * @return {string} 64 hex characters.
 */
const generateSecret = () => {
	const bytes = new Uint8Array(32);
	window.crypto.getRandomValues(bytes);
	return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
};

const IntegrationsPanel = ({ settings, updateSetting }) => {
	const [generatedSecret, setGeneratedSecret] = useState('');
	const [secretCopied, setSecretCopied] = useState(false);
	const copySecretRef = useCopyToClipboard(generatedSecret, () =>
		setSecretCopied(true)
	);

	return (
		<Card className="designsetgo-settings-panel">
			<CardHeader>
				<h2>{__('Integrations', 'designsetgo')}</h2>
			</CardHeader>
			<CardBody>
				<p className="designsetgo-panel-description">
					{__(
						'Configure third-party service API keys and credentials.',
						'designsetgo'
					)}
				</p>

				<form onSubmit={(e) => e.preventDefault()} autoComplete="off">
					<div className="designsetgo-settings-section">
						<h3 className="designsetgo-section-heading">
							{__('Google Maps', 'designsetgo')}
						</h3>

						<TextControl
							label={__('Google Maps API Key', 'designsetgo')}
							help={
								<>
									{__(
										'Enter your Google Maps JavaScript API key.',
										'designsetgo'
									)}
									<ExternalLink href="https://console.cloud.google.com/apis/credentials">
										{__(
											'Get your API key from Google Cloud Console',
											'designsetgo'
										)}
									</ExternalLink>
								</>
							}
							type="password"
							value={
								settings?.integrations?.google_maps_api_key ||
								''
							}
							onChange={(value) =>
								updateSetting(
									'integrations',
									'google_maps_api_key',
									value
								)
							}
							placeholder={__('AIza…', 'designsetgo')}
							autoComplete="off"
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<div className="designsetgo-settings-note">
							<strong>
								{__(
									'⚠️ Important Security Notice:',
									'designsetgo'
								)}
							</strong>
							<p>
								{__(
									'Always configure HTTP referrer restrictions in the Google Cloud Console to prevent unauthorized use of your API key. This is critical for security and preventing unexpected charges.',
									'designsetgo'
								)}
							</p>
						</div>
					</div>

					<div className="designsetgo-settings-section">
						<h3 className="designsetgo-section-heading">
							{__('Cloudflare Turnstile', 'designsetgo')}
						</h3>

						<p className="designsetgo-section-description">
							{__(
								'Turnstile is a privacy-preserving CAPTCHA alternative that protects your forms from spam without user interaction.',
								'designsetgo'
							)}
						</p>

						<TextControl
							label={__('Site Key', 'designsetgo')}
							help={
								<>
									{__(
										'Your Turnstile site key (public).',
										'designsetgo'
									)}{' '}
									<ExternalLink href="https://dash.cloudflare.com/?to=/:account/turnstile">
										{__(
											'Get your keys from Cloudflare Dashboard',
											'designsetgo'
										)}
									</ExternalLink>
								</>
							}
							value={
								settings?.integrations?.turnstile_site_key || ''
							}
							onChange={(value) =>
								updateSetting(
									'integrations',
									'turnstile_site_key',
									value
								)
							}
							placeholder={__('0x4AAAAAAA…', 'designsetgo')}
							autoComplete="off"
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<TextControl
							label={__('Secret Key', 'designsetgo')}
							help={__(
								'Your Turnstile secret key (private, used for server-side verification).',
								'designsetgo'
							)}
							type="password"
							value={
								settings?.integrations?.turnstile_secret_key ||
								''
							}
							onChange={(value) =>
								updateSetting(
									'integrations',
									'turnstile_secret_key',
									value
								)
							}
							placeholder={__('0x4AAAAAAA…', 'designsetgo')}
							autoComplete="off"
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<div className="designsetgo-settings-note">
							<strong>
								{__(
									'How to get your Turnstile keys:',
									'designsetgo'
								)}
							</strong>
							<ol>
								<li>
									{__(
										'Log in to your Cloudflare Dashboard',
										'designsetgo'
									)}
								</li>
								<li>
									{__(
										'Navigate to Turnstile in the sidebar',
										'designsetgo'
									)}
								</li>
								<li>
									{__(
										'Click "Add Site" and enter your domain',
										'designsetgo'
									)}
								</li>
								<li>
									{__(
										'Copy both the Site Key and Secret Key',
										'designsetgo'
									)}
								</li>
							</ol>
						</div>
					</div>
					<div className="designsetgo-settings-section">
						<h3 className="designsetgo-section-heading">
							{__('Form Webhooks', 'designsetgo')}
						</h3>

						<p className="designsetgo-section-description">
							{__(
								'Forms with a webhook URL send each submission to it as JSON. With a secret set, each request has an X-DSGo-Signature header: "sha256=" plus the HMAC-SHA256 of the X-DSGo-Timestamp header, a period, and the raw request body. Without one, requests are unsigned.',
								'designsetgo'
							)}
						</p>

						<TextControl
							label={__('Signing Secret', 'designsetgo')}
							help={__(
								'Used to sign webhook requests (HMAC-SHA256). It is hidden after you save, so copy it into your receiver first.',
								'designsetgo'
							)}
							type="password"
							value={
								settings?.integrations?.form_webhook_secret ||
								''
							}
							onChange={(value) => {
								setGeneratedSecret('');
								setSecretCopied(false);
								updateSetting(
									'integrations',
									'form_webhook_secret',
									value
								);
							}}
							autoComplete="off"
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<Button
							variant="secondary"
							onClick={() => {
								const secret = generateSecret();
								setGeneratedSecret(secret);
								setSecretCopied(false);
								updateSetting(
									'integrations',
									'form_webhook_secret',
									secret
								);
							}}
							__next40pxDefaultSize
						>
							{__('Generate secret', 'designsetgo')}
						</Button>

						{generatedSecret && (
							<Notice status="warning" isDismissible={false}>
								{__(
									'Copy this secret into your webhook receiver now, then save. It will be hidden afterwards:',
									'designsetgo'
								)}{' '}
								<code>{generatedSecret}</code>{' '}
								<Button
									variant="secondary"
									size="small"
									ref={copySecretRef}
								>
									{secretCopied
										? __('Copied', 'designsetgo')
										: __('Copy secret', 'designsetgo')}
								</Button>
							</Notice>
						)}
					</div>
				</form>
			</CardBody>
		</Card>
	);
};

export default IntegrationsPanel;
