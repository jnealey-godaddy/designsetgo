/**
 * Form Builder - Webhook URL field.
 *
 * Says when an address can't receive submissions, after the author leaves
 * the field rather than on every keystroke, so a half-typed URL isn't
 * flagged.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { webhookUrlProblem } from '../utils/webhook-url';

/**
 * Webhook URL text field with an inline problem notice.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.value    Current URL.
 * @param {Function} props.onChange Called with the trimmed URL.
 * @return {JSX.Element} Control.
 */
export default function WebhookUrlControl({ value, onChange }) {
	const [checked, setChecked] = useState(value);
	// Only for the value last checked, so editing hides it until blur.
	const problem =
		checked === value
			? webhookUrlProblem(checked, window.location.hostname)
			: '';

	return (
		<>
			<TextControl
				label={__('Webhook URL', 'designsetgo')}
				value={value}
				onChange={(next) => onChange(next.trim())}
				onBlur={() => setChecked(value)}
				type="url"
				placeholder="https://hooks.example.com/…"
				aria-invalid={problem ? true : undefined}
				help={__(
					'Anyone who can edit this page can see this URL. Each submission is also sent here as JSON (for Zapier, Make, a CRM…). To sign requests, set a secret in DesignSetGo → Settings → Integrations.',
					'designsetgo'
				)}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			{problem && (
				<Notice status="warning" isDismissible={false}>
					{problem}
				</Notice>
			)}
		</>
	);
}
