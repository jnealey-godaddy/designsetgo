/**
 * Countdown unit labels follow each language's own plural rule.
 *
 * Uses the real @wordpress/i18n with locale data shaped like the bundled
 * catalogs (languages/designsetgo-*.json).
 */
import { setLocaleData, resetLocaleData } from '@wordpress/i18n';
import {
	formatCountdownDisplay,
	getUnitLabel,
} from '../../src/blocks/countdown-timer/utils/format-time';

function useLocale(pluralForms, messages) {
	resetLocaleData();
	setLocaleData(
		{
			'': { domain: 'designsetgo', plural_forms: pluralForms },
			...messages,
		},
		'designsetgo'
	);
}

describe('countdown unit labels', () => {
	afterEach(() => resetLocaleData());

	it('keeps the English labels', () => {
		expect(getUnitLabel('days', 1)).toBe('Day');
		expect(getUnitLabel('days', 0)).toBe('Days');
		expect(getUnitLabel('hours', 2)).toBe('Hours');
		expect(getUnitLabel('minutes', 1)).toBe('Min');
		expect(getUnitLabel('seconds', 5)).toBe('Sec');
	});

	it('treats 0 as singular in French', () => {
		useLocale('nplurals=2; plural=(n > 1);', {
			Day: ['Jour', 'Jours'],
		});
		expect(getUnitLabel('days', 0)).toBe('Jour');
		expect(getUnitLabel('days', 2)).toBe('Jours');
	});

	it('uses all three Russian forms', () => {
		useLocale(
			'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
			{ Day: ['День', 'Дня', 'Дней'] }
		);
		expect(getUnitLabel('days', 1)).toBe('День');
		expect(getUnitLabel('days', 3)).toBe('Дня');
		expect(getUnitLabel('days', 5)).toBe('Дней');
		expect(getUnitLabel('days', 21)).toBe('День');
	});

	it('gives the editor display the same labels', () => {
		const units = formatCountdownDisplay(
			{ days: 1, hours: 0, minutes: 5, seconds: 9 },
			{
				showDays: true,
				showHours: true,
				showMinutes: true,
				showSeconds: false,
			}
		);
		expect(units).toEqual([
			{ value: 1, label: 'Day', type: 'days' },
			{ value: 0, label: 'Hours', type: 'hours' },
			{ value: 5, label: 'Min', type: 'minutes' },
		]);
	});
});
