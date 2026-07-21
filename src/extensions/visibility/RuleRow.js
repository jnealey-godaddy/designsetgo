import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalHStack as HStack,
} from '@wordpress/components';

const TYPE_OPTIONS = [
	{ value: 'meta', label: __('Post Meta', 'designsetgo') },
	{ value: 'taxonomy', label: __('Taxonomy', 'designsetgo') },
	{ value: 'index', label: __('Item Index', 'designsetgo') },
	{ value: 'auth', label: __('Auth State', 'designsetgo') },
];

const META_OP_OPTIONS = [
	{ value: 'equals', label: __('equals', 'designsetgo') },
	{ value: 'not_equals', label: __('does not equal', 'designsetgo') },
	{ value: 'contains', label: __('contains', 'designsetgo') },
	{ value: 'not_empty', label: __('is set', 'designsetgo') },
	{ value: 'empty', label: __('is not set', 'designsetgo') },
];

const TAXONOMY_OP_OPTIONS = [
	{ value: 'has', label: __('has term', 'designsetgo') },
	{ value: 'not_has', label: __('does not have term', 'designsetgo') },
];

const INDEX_OP_OPTIONS = [
	{ value: 'equals', label: __('is', 'designsetgo') },
	{ value: 'not_equals', label: __('is not', 'designsetgo') },
	{ value: 'lt', label: __('less than', 'designsetgo') },
	{ value: 'gt', label: __('greater than', 'designsetgo') },
];

/**
 * Renders the extra controls for a meta-type rule.
 *
 * @param {Object}   props
 * @param {Object}   props.rule
 * @param {Function} props.onChange
 */
function MetaControls({ rule, onChange }) {
	return (
		<>
			<TextControl
				label={__('Meta Key', 'designsetgo')}
				value={rule.key ?? ''}
				onChange={(key) => onChange({ ...rule, key })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={__('Condition', 'designsetgo')}
				value={rule.op ?? 'equals'}
				options={META_OP_OPTIONS}
				onChange={(op) => onChange({ ...rule, op })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			{!['empty', 'not_empty'].includes(rule.op) && (
				<TextControl
					label={__('Value', 'designsetgo')}
					value={rule.value ?? ''}
					onChange={(value) => onChange({ ...rule, value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			)}
		</>
	);
}

/**
 * Renders the extra controls for a taxonomy-type rule.
 *
 * @param {Object}   props
 * @param {Object}   props.rule
 * @param {Function} props.onChange
 */
function TaxonomyControls({ rule, onChange }) {
	return (
		<>
			<TextControl
				label={__('Taxonomy Slug', 'designsetgo')}
				value={rule.taxonomy ?? ''}
				onChange={(taxonomy) => onChange({ ...rule, taxonomy })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={__('Condition', 'designsetgo')}
				value={rule.op ?? 'has'}
				options={TAXONOMY_OP_OPTIONS}
				onChange={(op) => onChange({ ...rule, op })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={__('Term Slug', 'designsetgo')}
				value={rule.value ?? ''}
				onChange={(value) => onChange({ ...rule, value })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</>
	);
}

/**
 * Renders the extra controls for an index-type rule.
 *
 * @param {Object}   props
 * @param {Object}   props.rule
 * @param {Function} props.onChange
 */
function IndexControls({ rule, onChange }) {
	return (
		<>
			<SelectControl
				label={__('Condition', 'designsetgo')}
				value={rule.op ?? 'equals'}
				options={INDEX_OP_OPTIONS}
				onChange={(op) => onChange({ ...rule, op })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={__('Index Value', 'designsetgo')}
				value={String(rule.value ?? '')}
				onChange={(value) => onChange({ ...rule, value })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</>
	);
}

/**
 * Renders the extra controls for an auth-type rule.
 *
 * @param {Object}   props
 * @param {Object}   props.rule
 * @param {Function} props.onChange
 */
function AuthControls({ rule, onChange }) {
	return (
		<SelectControl
			label={__('Visibility requires', 'designsetgo')}
			value={JSON.stringify(!!rule.value)}
			options={[
				{ value: 'true', label: __('Logged-in user', 'designsetgo') },
				{
					value: 'false',
					label: __('Logged-out visitor', 'designsetgo'),
				},
			]}
			onChange={(v) => onChange({ ...rule, value: JSON.parse(v) })}
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
}

/**
 * Returns the default rule shape for a given type.
 *
 * @param {string} type Rule type.
 * @return {Object} Default rule object.
 */
function getDefaultRule(type) {
	switch (type) {
		case 'meta':
			return { type: 'meta', key: '', op: 'equals', value: '' };
		case 'taxonomy':
			return { type: 'taxonomy', taxonomy: '', op: 'has', value: '' };
		case 'index':
			return { type: 'index', op: 'equals', value: 0 };
		case 'auth':
			return { type: 'auth', value: true };
		default:
			return { type, op: 'equals', value: '' };
	}
}

/**
 * A single visibility rule row.
 *
 * @param {Object}   props
 * @param {Object}   props.rule     Rule object.
 * @param {Function} props.onChange Called with updated rule object.
 * @param {Function} props.onRemove Called when the rule is removed.
 */
export default function RuleRow({ rule, onChange, onRemove }) {
	return (
		<div className="dsgo-visibility-rule-row">
			<HStack alignment="top">
				<div className="dsgo-visibility-rule-row__controls">
					<SelectControl
						label={__('Rule Type', 'designsetgo')}
						value={rule.type ?? 'meta'}
						options={TYPE_OPTIONS}
						onChange={(type) => onChange(getDefaultRule(type))}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					{(rule.type === 'meta' || !rule.type) && (
						<MetaControls rule={rule} onChange={onChange} />
					)}
					{rule.type === 'taxonomy' && (
						<TaxonomyControls rule={rule} onChange={onChange} />
					)}
					{rule.type === 'index' && (
						<IndexControls rule={rule} onChange={onChange} />
					)}
					{rule.type === 'auth' && (
						<AuthControls rule={rule} onChange={onChange} />
					)}
				</div>
				<Button
					variant="tertiary"
					isDestructive
					onClick={onRemove}
					aria-label={__('Remove rule', 'designsetgo')}
				>
					{__('Remove', 'designsetgo')}
				</Button>
			</HStack>
		</div>
	);
}
