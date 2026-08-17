/**
 * Interaction Layers - Human-readable summaries
 *
 * A collapsed interaction row shows one line of text instead of seven
 * controls. That line is the only thing distinguishing two interactions in
 * the list, so it has to name the trigger, the action, and the payload.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';
import { TRIGGERS, ACTIONS, TARGET_MODES } from './constants';

const labelFor = (table, value) =>
	table.find((entry) => entry.value === value)?.label || value || '';

/**
 * Describe an interaction in one short line.
 *
 * @param {Object} interaction Interaction config.
 * @return {string} Summary such as "Click → Toggle class is-open".
 */
export function summariseInteraction(interaction = {}) {
	const trigger = labelFor(TRIGGERS, interaction.trigger);
	const action = labelFor(ACTIONS, interaction.action);

	const payload =
		'setAttribute' === interaction.action
			? [interaction.attributeName, interaction.value]
					.filter(Boolean)
					.join('=')
			: interaction.value;

	const doing = payload
		? sprintf(
				/* translators: 1: action name, 2: the value it acts on. */
				__('%1$s %2$s', 'designsetgo'),
				action,
				payload
			)
		: action;

	return sprintf(
		/* translators: 1: trigger name, 2: action description. */
		__('%1$s → %2$s', 'designsetgo'),
		trigger,
		doing
	);
}

/**
 * Describe an interaction's target in one short line.
 *
 * @param {Object} interaction Interaction config.
 * @return {string} Target description, empty when it targets itself.
 */
export function summariseTarget(interaction = {}) {
	if (!interaction.targetMode || 'self' === interaction.targetMode) {
		return '';
	}

	if (!interaction.targetSelector) {
		return sprintf(
			/* translators: %s: target mode name. */
			__('%s (none set)', 'designsetgo'),
			labelFor(TARGET_MODES, interaction.targetMode)
		);
	}

	return interaction.targetSelector;
}
