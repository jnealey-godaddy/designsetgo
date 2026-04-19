/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { DateTimePicker, SelectControl, Notice } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * Common timezone options - WordPress standard timezones
 */
const TIMEZONE_OPTIONS = [
	{ label: __('WordPress Default', 'designsetgo'), value: '' },
	{ label: 'UTC', value: 'UTC' },
	// North America
	{ label: 'America/New_York (EST/EDT)', value: 'America/New_York' },
	{ label: 'America/Chicago (CST/CDT)', value: 'America/Chicago' },
	{ label: 'America/Denver (MST/MDT)', value: 'America/Denver' },
	{ label: 'America/Phoenix (MST)', value: 'America/Phoenix' },
	{ label: 'America/Los_Angeles (PST/PDT)', value: 'America/Los_Angeles' },
	{ label: 'America/Anchorage (AKST/AKDT)', value: 'America/Anchorage' },
	{ label: 'America/Adak (HST/HDT)', value: 'America/Adak' },
	{ label: 'Pacific/Honolulu (HST)', value: 'Pacific/Honolulu' },
	{ label: 'America/Toronto', value: 'America/Toronto' },
	{ label: 'America/Vancouver', value: 'America/Vancouver' },
	{ label: 'America/Mexico_City', value: 'America/Mexico_City' },
	// South America
	{ label: 'America/Sao_Paulo', value: 'America/Sao_Paulo' },
	{ label: 'America/Buenos_Aires', value: 'America/Buenos_Aires' },
	{ label: 'America/Santiago', value: 'America/Santiago' },
	{ label: 'America/Bogota', value: 'America/Bogota' },
	{ label: 'America/Lima', value: 'America/Lima' },
	// Europe
	{ label: 'Europe/London (GMT/BST)', value: 'Europe/London' },
	{ label: 'Europe/Paris (CET/CEST)', value: 'Europe/Paris' },
	{ label: 'Europe/Berlin', value: 'Europe/Berlin' },
	{ label: 'Europe/Rome', value: 'Europe/Rome' },
	{ label: 'Europe/Madrid', value: 'Europe/Madrid' },
	{ label: 'Europe/Amsterdam', value: 'Europe/Amsterdam' },
	{ label: 'Europe/Brussels', value: 'Europe/Brussels' },
	{ label: 'Europe/Vienna', value: 'Europe/Vienna' },
	{ label: 'Europe/Warsaw', value: 'Europe/Warsaw' },
	{ label: 'Europe/Athens', value: 'Europe/Athens' },
	{ label: 'Europe/Istanbul', value: 'Europe/Istanbul' },
	{ label: 'Europe/Moscow', value: 'Europe/Moscow' },
	// Asia
	{ label: 'Asia/Dubai', value: 'Asia/Dubai' },
	{ label: 'Asia/Karachi', value: 'Asia/Karachi' },
	{ label: 'Asia/Kolkata (IST)', value: 'Asia/Kolkata' },
	{ label: 'Asia/Bangkok', value: 'Asia/Bangkok' },
	{ label: 'Asia/Singapore', value: 'Asia/Singapore' },
	{ label: 'Asia/Hong_Kong', value: 'Asia/Hong_Kong' },
	{ label: 'Asia/Shanghai', value: 'Asia/Shanghai' },
	{ label: 'Asia/Tokyo (JST)', value: 'Asia/Tokyo' },
	{ label: 'Asia/Seoul', value: 'Asia/Seoul' },
	{ label: 'Asia/Jakarta', value: 'Asia/Jakarta' },
	{ label: 'Asia/Manila', value: 'Asia/Manila' },
	// Middle East
	{ label: 'Asia/Jerusalem', value: 'Asia/Jerusalem' },
	{ label: 'Asia/Riyadh', value: 'Asia/Riyadh' },
	{ label: 'Asia/Qatar', value: 'Asia/Qatar' },
	// Africa
	{ label: 'Africa/Cairo', value: 'Africa/Cairo' },
	{ label: 'Africa/Johannesburg', value: 'Africa/Johannesburg' },
	{ label: 'Africa/Lagos', value: 'Africa/Lagos' },
	{ label: 'Africa/Nairobi', value: 'Africa/Nairobi' },
	// Australia & Pacific
	{ label: 'Australia/Sydney (AEDT/AEST)', value: 'Australia/Sydney' },
	{ label: 'Australia/Melbourne', value: 'Australia/Melbourne' },
	{ label: 'Australia/Brisbane', value: 'Australia/Brisbane' },
	{ label: 'Australia/Perth', value: 'Australia/Perth' },
	{ label: 'Pacific/Auckland (NZDT/NZST)', value: 'Pacific/Auckland' },
	{ label: 'Pacific/Fiji', value: 'Pacific/Fiji' },
];

/**
 * DateTime Panel component
 *
 * Renders DsgoInspectorPanel.Item entries for target-date + timezone.
 * Meant to be composed inside the Settings DsgoInspectorPanel in
 * countdown-timer/edit.js.
 *
 * @param {Object}   props               - Component properties
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Item fragment
 */
export default function DateTimePanel({ attributes, setAttributes }) {
	const { targetDateTime, timezone } = attributes;

	// Get WordPress timezone setting
	const wpTimezone =
		window?.wp?.date?.getSettings?.()?.timezone?.string || 'UTC';

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Target Date & Time', 'designsetgo')}
				hasValue={() => !!targetDateTime}
				onDeselect={() => setAttributes({ targetDateTime: '' })}
				isShownByDefault
			>
				<Notice status="info" isDismissible={false}>
					{__(
						'Set the target date and time for your countdown.',
						'designsetgo'
					)}
				</Notice>
				<div style={{ marginTop: '12px', marginBottom: '12px' }}>
					<DateTimePicker
						currentDate={targetDateTime || null}
						onChange={(newDateTime) =>
							setAttributes({ targetDateTime: newDateTime })
						}
						is12Hour={true}
					/>
				</div>
				{targetDateTime && (
					<Notice status="success" isDismissible={false}>
						{__('Target date set!', 'designsetgo')}{' '}
						{new Date(targetDateTime).toLocaleString()}
					</Notice>
				)}
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Timezone', 'designsetgo')}
				hasValue={() => timezone !== ''}
				onDeselect={() => setAttributes({ timezone: '' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Timezone', 'designsetgo')}
					value={timezone}
					options={TIMEZONE_OPTIONS}
					onChange={(newTimezone) =>
						setAttributes({ timezone: newTimezone })
					}
					help={
						__('WordPress site timezone:', 'designsetgo') +
						' ' +
						wpTimezone
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
