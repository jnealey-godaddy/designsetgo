/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { DateTimePicker, SelectControl, Notice } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';
import { getEditorSiteTimezone } from '../../utils/time-calculator';
import {
	formatInZone,
	hasExplicitOffset,
	isUsableZone,
	resolveTargetTimestamp,
	wallClockInZone,
} from '../../utils/timezone';

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

	// Resolved WordPress site timezone (IANA name, or a fixed "+05:30"-style
	// offset for sites without a named zone configured) — the zone that's
	// actually used to interpret targetDateTime when this control is left
	// on "WordPress Default".
	const wpTimezone = getEditorSiteTimezone();

	// The zone the countdown actually uses: the block's own, else the site's
	// (resolveTargetTimestamp applies the same order).
	const blockZoneUsable = isUsableZone(timezone);
	const effectiveZone = blockZoneUsable ? timezone : wpTimezone;
	const overridesSiteZone = blockZoneUsable && timezone !== wpTimezone;

	// A target saved as a fixed instant (with `Z` or an offset) is shown in
	// the effective zone, so picking a date keeps the digits the author sees
	// instead of re-reading browser-local digits in another zone.
	const isFixedInstant = hasExplicitOffset(targetDateTime);
	const targetInstant = resolveTargetTimestamp(
		targetDateTime,
		timezone,
		wpTimezone
	);
	const pickerValue = isFixedInstant
		? wallClockInZone(targetInstant, effectiveZone)
		: targetDateTime;

	const timezoneHelp = (() => {
		if (timezone && !blockZoneUsable) {
			return sprintf(
				/* translators: %s: WordPress site timezone, e.g. America/New_York. */
				__(
					'This timezone is not recognised, so the WordPress site timezone (%s) is used.',
					'designsetgo'
				),
				wpTimezone
			);
		}
		if (timezone) {
			return sprintf(
				/* translators: %s: WordPress site timezone, e.g. America/New_York. */
				__(
					'Overrides the WordPress site timezone (%s).',
					'designsetgo'
				),
				wpTimezone
			);
		}
		return sprintf(
			/* translators: %s: WordPress site timezone, e.g. America/New_York. */
			__('Uses the WordPress site timezone (%s).', 'designsetgo'),
			wpTimezone
		);
	})();

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
				<div
					className={
						overridesSiteZone
							? 'dsgo-countdown-datetime dsgo-countdown-datetime--block-zone'
							: 'dsgo-countdown-datetime'
					}
					style={{ marginTop: '12px', marginBottom: '12px' }}
				>
					<DateTimePicker
						currentDate={pickerValue || null}
						onChange={(newDateTime) =>
							setAttributes({ targetDateTime: newDateTime })
						}
						is12Hour={true}
					/>
					{overridesSiteZone && (
						// The picker's own timezone badge always names the
						// site zone; it is hidden here (editor.scss) and this
						// line names the zone the time is actually in.
						<p className="components-base-control__help">
							{sprintf(
								/* translators: %s: timezone, e.g. Europe/London. */
								__('Time is in %s.', 'designsetgo'),
								effectiveZone
							)}
						</p>
					)}
				</div>
				{targetDateTime && Number.isFinite(targetInstant) && (
					<Notice status="success" isDismissible={false}>
						{sprintf(
							/* translators: 1: date and time, 2: timezone, e.g. Europe/London. */
							__('Countdown ends %1$s (%2$s).', 'designsetgo'),
							formatInZone(targetInstant, effectiveZone),
							effectiveZone
						)}
					</Notice>
				)}
				{isFixedInstant && (
					<Notice status="info" isDismissible={false}>
						{__(
							'This date was saved as a fixed moment, so the timezone setting does not change it. Pick the date again to have it follow the timezone below.',
							'designsetgo'
						)}
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
					help={timezoneHelp}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
