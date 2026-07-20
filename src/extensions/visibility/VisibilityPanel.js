import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalVStack as VStack,
} from '@wordpress/components';
import RuleRow from './RuleRow';

const DEFAULT_RULE = { type: 'meta', key: '', op: 'equals', value: '' };

const OPERATOR_OPTIONS = [
	{ value: 'AND', label: __('All rules must match (AND)', 'designsetgo') },
	{ value: 'OR', label: __('Any rule must match (OR)', 'designsetgo') },
];

/**
 * Inspector panel for configuring block visibility rules.
 *
 * @param {Object}      props
 * @param {Object|null} props.value    Current visibility config ({ operator, rules }) or null.
 * @param {Function}    props.onChange Called with the new visibility config.
 */
export default function VisibilityPanel({ value, onChange }) {
	const rules = value?.rules ?? [];
	const operator = value?.operator ?? 'AND';
	const hasRules = rules.length > 0;

	function handleAddRule() {
		onChange({
			operator,
			rules: [...rules, { ...DEFAULT_RULE }],
		});
	}

	function handleOperatorChange(newOperator) {
		onChange({ ...value, operator: newOperator });
	}

	function handleRuleChange(index, updatedRule) {
		const newRules = rules.map((r, i) => (i === index ? updatedRule : r));
		onChange({ operator, rules: newRules });
	}

	function handleRuleRemove(index) {
		const newRules = rules.filter((_, i) => i !== index);
		if (newRules.length === 0) {
			onChange(null);
		} else {
			onChange({ operator, rules: newRules });
		}
	}

	return (
		<VStack spacing={3} className="dsgo-visibility-panel">
			{!hasRules && (
				<p className="dsgo-visibility-panel__empty-state">
					{__('Always visible', 'designsetgo')}
				</p>
			)}

			{hasRules && rules.length > 1 && (
				<SelectControl
					label={__('Combine Rules', 'designsetgo')}
					value={operator}
					options={OPERATOR_OPTIONS}
					onChange={handleOperatorChange}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			)}

			{hasRules &&
				rules.map((rule, index) => (
					<RuleRow
						key={index}
						rule={rule}
						onChange={(updated) => handleRuleChange(index, updated)}
						onRemove={() => handleRuleRemove(index)}
					/>
				))}

			<Button variant="secondary" onClick={handleAddRule}>
				{__('Add rule', 'designsetgo')}
			</Button>
		</VStack>
	);
}
