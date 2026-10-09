import { useEffect, useState } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { labels, valueLabels } from './labels';

/**
 * Keep invalid drafts local, so CSS functions can be entered character by character.
 * @param {Object}        root0            Component props.
 * @param {string}        root0.field      Property key.
 * @param {Object}        root0.definition Shared property definition.
 * @param {string|number} root0.value      Authored value.
 * @param {string|number} root0.inherited  Larger-viewport value.
 * @param {Function}      root0.onChange   Validated setter.
 */
export default function LayoutField({
	field,
	definition,
	value,
	inherited,
	onChange,
}) {
	const [draft, setDraft] = useState(value ?? '');
	const [invalid, setInvalid] = useState(false);
	useEffect(() => {
		setDraft(value ?? '');
		setInvalid(false);
	}, [value]);
	const change = (next) => {
		setDraft(next);
		const numeric = ['integer', 'number'].includes(definition.type);
		const candidate = numeric && next !== '' ? Number(next) : next;
		setInvalid(!onChange(candidate));
	};
	const common = {
		label: labels[field],
		value: draft,
		onChange: change,
		__next40pxDefaultSize: true,
		__nextHasNoMarginBottom: true,
	};
	if (definition.type === 'enum') {
		return (
			<SelectControl
				{...common}
				options={[
					{ label: __('Inherit', 'designsetgo'), value: '' },
					...definition.values.map((entry) => ({
						label: valueLabels[entry],
						value: entry,
					})),
				]}
			/>
		);
	}
	let help = __('Leave empty to use the block default.', 'designsetgo');
	if (inherited !== undefined) {
		help = sprintf(
			/* translators: %s: CSS value inherited from a larger viewport. */
			__('Inherited: %s', 'designsetgo'),
			String(inherited)
		);
	}
	if (invalid) {
		help = __(
			'Enter a valid CSS value. The previous value is still applied.',
			'designsetgo'
		);
	}
	return (
		<TextControl
			{...common}
			help={help}
			aria-invalid={invalid || undefined}
			placeholder={inherited === undefined ? '' : String(inherited)}
		/>
	);
}
