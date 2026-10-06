/**
 * Form Builder — "Show this field when…" rule editor.
 *
 * @since 2.10.0
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

const VALUELESS = ['empty', 'not_empty'];

/**
 * @param {string} type Source field type.
 * @return {Array<{value: string, label: string}>} Operators for that type.
 */
export function opsForType(type) {
	if (type === 'checkbox') {
		return [
			{ value: 'not_empty', label: __('is checked', 'designsetgo') },
			{ value: 'empty', label: __('is not checked', 'designsetgo') },
		];
	}
	const is = [
		{ value: 'is', label: __('is', 'designsetgo') },
		{ value: 'is_not', label: __('is not', 'designsetgo') },
	];
	const empties = [
		{ value: 'empty', label: __('is empty', 'designsetgo') },
		{ value: 'not_empty', label: __('is not empty', 'designsetgo') },
	];
	if (type === 'select') {
		return [...is, ...empties];
	}
	if (['number', 'date', 'time'].includes(type)) {
		return [
			...is,
			{ value: 'gt', label: __('is greater than', 'designsetgo') },
			{ value: 'lt', label: __('is less than', 'designsetgo') },
			...empties,
		];
	}
	return [
		...is,
		{ value: 'contains', label: __('contains', 'designsetgo') },
		...empties,
	];
}

const INPUT_TYPE = { number: 'number', date: 'date', time: 'time' };

export default function FieldConditionsControl({
	value,
	onChange,
	fields,
	currentName,
}) {
	const operator = value?.operator === 'OR' ? 'OR' : 'AND';
	const rules = Array.isArray(value?.rules) ? value.rules : [];
	const sources = fields.filter(
		(field) => field.name && field.name !== currentName
	);

	const commit = (nextRules, nextOperator = operator) =>
		onChange(
			nextRules.length
				? { operator: nextOperator, rules: nextRules }
				: null
		);

	const updateRule = (index, patch) =>
		commit(
			rules.map((rule, i) => (i === index ? { ...rule, ...patch } : rule))
		);

	const changeSource = (index, fieldName) => {
		const source = sources.find((field) => field.name === fieldName);
		const ops = opsForType(source?.type).map((op) => op.value);
		const current = rules[index];
		updateRule(index, {
			field: fieldName,
			op: ops.includes(current.op) ? current.op : ops[0],
			value: ops.includes(current.op) ? current.value : '',
		});
	};

	return (
		<div className="dsgo-field-conditions">
			<p className="dsgo-field-conditions__intro">
				{__('Show this field when:', 'designsetgo')}
			</p>
			{rules.length > 1 && (
				<ToggleGroupControl
					label={__('Match', 'designsetgo')}
					value={operator}
					onChange={(next) => commit(rules, next)}
					isBlock
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				>
					<ToggleGroupControlOption
						value="AND"
						label={__('All rules', 'designsetgo')}
					/>
					<ToggleGroupControlOption
						value="OR"
						label={__('Any rule', 'designsetgo')}
					/>
				</ToggleGroupControl>
			)}
			{rules.map((rule, index) => {
				const source = sources.find(
					(field) => field.name === rule.field
				);
				const ops = opsForType(source?.type);
				return (
					<div className="dsgo-field-conditions__rule" key={index}>
						{rule.field && !source && (
							<Notice
								status="warning"
								isDismissible={false}
								spokenMessage={null}
							>
								{__(
									'This field no longer exists, so the rule is ignored.',
									'designsetgo'
								)}
							</Notice>
						)}
						<SelectControl
							label={__('Field', 'designsetgo')}
							value={rule.field}
							options={[
								{
									value: '',
									label: __('Select a field…', 'designsetgo'),
								},
								...sources.map((field) => ({
									value: field.name,
									label: field.label,
								})),
							]}
							onChange={(next) => changeSource(index, next)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={__('Condition', 'designsetgo')}
							value={rule.op}
							options={ops}
							onChange={(op) =>
								updateRule(index, {
									op,
									value: VALUELESS.includes(op)
										? ''
										: rule.value,
								})
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						{!VALUELESS.includes(rule.op) &&
							source?.type === 'select' && (
								<SelectControl
									label={__('Value', 'designsetgo')}
									value={rule.value}
									options={[
										{
											value: '',
											label: __('Select…', 'designsetgo'),
										},
										...source.options.map((option) => ({
											value: option.value,
											label: option.label || option.value,
										})),
									]}
									onChange={(next) =>
										updateRule(index, { value: next })
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							)}
						{!VALUELESS.includes(rule.op) &&
							source?.type !== 'select' && (
								<TextControl
									label={__('Value', 'designsetgo')}
									type={INPUT_TYPE[source?.type] || 'text'}
									value={rule.value}
									onChange={(next) =>
										updateRule(index, { value: next })
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							)}
						<Button
							variant="tertiary"
							isDestructive
							onClick={() =>
								commit(rules.filter((_, i) => i !== index))
							}
							label={__('Remove rule', 'designsetgo')}
						>
							{__('Remove rule', 'designsetgo')}
						</Button>
					</div>
				);
			})}
			<Button
				variant="secondary"
				onClick={() =>
					commit([...rules, { field: '', op: 'is', value: '' }])
				}
				__next40pxDefaultSize
			>
				{__('Add rule', 'designsetgo')}
			</Button>
		</div>
	);
}
