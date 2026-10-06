/**
 * Form Builder — editor-only badge for fields with conditional logic.
 *
 * @since 2.10.0
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { hasActiveRules, normalizeRules } from '../conditions';
import useFormFields from '../utils/use-form-fields';
import { opsForType } from './FieldConditionsControl';

/**
 * Badge for a field that has rules. Only rules whose source is another field
 * of this form count: the front end ignores the rest, so a field whose rules
 * all point at missing (or its own) fields gets no badge.
 *
 * @param {Object} props            Props.
 * @param {Object} props.conditions Raw dsgoConditions.
 * @param {string} props.clientId   Field block client ID.
 * @return {Element|null} Badge.
 */
function ActiveConditionalBadge({ conditions, clientId }) {
	const fields = useFormFields(clientId);
	const sourceOf = (rule) =>
		fields.find((field) => field.name === rule.field);
	const active = normalizeRules(conditions).rules.filter(sourceOf);
	if (!active.length) {
		return null;
	}

	const [first, ...rest] = active;
	const source = sourceOf(first);
	const opLabel =
		opsForType(source.type).find((op) => op.value === first.op)?.label ||
		first.op;
	let summary = sprintf(
		/* translators: 1: field label, 2: condition such as "is", 3: value */
		__('Shown when %1$s %2$s %3$s', 'designsetgo'),
		source.label,
		opLabel,
		first.value
	).trim();
	if (rest.length) {
		summary +=
			' ' +
			sprintf(
				/* translators: %d: number of additional rules */
				_n(
					'and %d more rule',
					'and %d more rules',
					rest.length,
					'designsetgo'
				),
				rest.length
			);
	}

	// The badge sits over the label and lets clicks through, so a tooltip
	// could never be reached: the summary is visually hidden text instead.
	return (
		<span className="dsgo-conditional-badge">
			{__('Conditional', 'designsetgo')}
			<span className="dsgo-conditional-badge__summary">{summary}</span>
		</span>
	);
}

export default function ConditionalBadge({ conditions, clientId }) {
	// Most fields have no rules: skip the form-wide block lookup for them.
	if (!hasActiveRules(conditions)) {
		return null;
	}
	return (
		<ActiveConditionalBadge conditions={conditions} clientId={clientId} />
	);
}
