/**
 * WordPress dependencies
 */
import { __, _n } from '@wordpress/i18n';

/**
 * Format countdown time for display
 *
 * @param {Object} timeData           - Object with days, hours, minutes, seconds
 * @param {Object} visibilitySettings - Object with showDays, showHours, showMinutes, showSeconds
 * @return {Array} Array of visible time units with labels
 */
export function formatCountdownDisplay(timeData, visibilitySettings) {
	const units = [];
	const add = (type, value) =>
		units.push({ value, label: getUnitLabel(type, value), type });

	if (visibilitySettings.showDays) {
		add('days', timeData.days);
	}

	if (visibilitySettings.showHours) {
		add('hours', timeData.hours);
	}

	if (visibilitySettings.showMinutes) {
		add('minutes', timeData.minutes);
	}

	if (visibilitySettings.showSeconds) {
		add('seconds', timeData.seconds);
	}

	return units;
}

/**
 * Get unit label (singular or plural)
 *
 * Days and hours go through _n() so each language applies its own plural
 * rule: French treats 0 as singular, Russian has three forms. Minutes and
 * seconds are abbreviations with no plural.
 *
 * @param {string} unitType - Type of unit (days, hours, minutes, seconds)
 * @param {number} value    - Value of the unit
 * @return {string} Label for the unit
 */
export function getUnitLabel(unitType, value) {
	switch (unitType) {
		case 'days':
			return _n('Day', 'Days', value, 'designsetgo');
		case 'hours':
			return _n('Hour', 'Hours', value, 'designsetgo');
		case 'minutes':
			return __('Min', 'designsetgo');
		case 'seconds':
			return __('Sec', 'designsetgo');
	}

	return '';
}
