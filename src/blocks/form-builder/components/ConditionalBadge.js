/**
 * Form Builder — editor-only badge for fields with conditional logic.
 *
 * @since 2.10.0
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { normalizeRules } from '../conditions';
import useFormFields from '../utils/use-form-fields';
import { opsForType } from './FieldConditionsControl';

export default function ConditionalBadge({ conditions, clientId }) {
	const fields = useFormFields(clientId);
	const normalized = normalizeRules(conditions);
	if (!normalized) {
		return null;
	}

	const [first, ...rest] = normalized.rules;
	const source = fields.find((field) => field.name === first.field);
	const opLabel =
		opsForType(source?.type).find((op) => op.value === first.op)?.label ||
		first.op;
	let summary = sprintf(
		/* translators: 1: field label, 2: condition such as "is", 3: value */
		__('Shown when %1$s %2$s %3$s', 'designsetgo'),
		source?.label || first.field,
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

	return (
		<span
			className="dsgo-conditional-badge"
			title={summary}
			aria-label={summary}
		>
			{__('Conditional', 'designsetgo')}
		</span>
	);
}
