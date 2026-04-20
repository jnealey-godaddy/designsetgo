import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	Button,
	__experimentalHStack as HStack,
} from '@wordpress/components';

const TYPE_OPTIONS = [
	{ value: 'meta', label: __( 'Post Meta', 'designsetgo' ) },
	{ value: 'taxonomy', label: __( 'Taxonomy', 'designsetgo' ) },
	{ value: 'index', label: __( 'Item Index', 'designsetgo' ) },
	{ value: 'auth', label: __( 'Auth State', 'designsetgo' ) },
];

const META_OP_OPTIONS = [
	{ value: 'equals', label: __( 'equals', 'designsetgo' ) },
	{ value: 'not_equals', label: __( 'does not equal', 'designsetgo' ) },
	{ value: 'exists', label: __( 'exists', 'designsetgo' ) },
	{ value: 'not_exists', label: __( 'does not exist', 'designsetgo' ) },
];

const TAXONOMY_OP_OPTIONS = [
	{ value: 'has', label: __( 'has term', 'designsetgo' ) },
	{ value: 'not_has', label: __( 'does not have term', 'designsetgo' ) },
];

const INDEX_OP_OPTIONS = [
	{ value: 'equals', label: __( 'equals', 'designsetgo' ) },
	{ value: 'less_than', label: __( 'less than', 'designsetgo' ) },
	{ value: 'greater_than', label: __( 'greater than', 'designsetgo' ) },
	{ value: 'even', label: __( 'is even', 'designsetgo' ) },
	{ value: 'odd', label: __( 'is odd', 'designsetgo' ) },
];

const AUTH_OP_OPTIONS = [
	{ value: 'logged_in', label: __( 'logged in', 'designsetgo' ) },
	{ value: 'logged_out', label: __( 'logged out', 'designsetgo' ) },
];

/**
 * Renders the extra controls for a meta-type rule.
 *
 * @param {Object} props
 * @param {Object} props.rule
 * @param {Function} props.onChange
 */
function MetaControls( { rule, onChange } ) {
	return (
		<>
			<TextControl
				label={ __( 'Meta Key', 'designsetgo' ) }
				value={ rule.key ?? '' }
				onChange={ ( key ) => onChange( { ...rule, key } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'Condition', 'designsetgo' ) }
				value={ rule.op ?? 'equals' }
				options={ META_OP_OPTIONS }
				onChange={ ( op ) => onChange( { ...rule, op } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			{ ( rule.op === 'equals' || rule.op === 'not_equals' || ! rule.op ) && (
				<TextControl
					label={ __( 'Value', 'designsetgo' ) }
					value={ rule.value ?? '' }
					onChange={ ( value ) => onChange( { ...rule, value } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			) }
		</>
	);
}

/**
 * Renders the extra controls for a taxonomy-type rule.
 *
 * @param {Object} props
 * @param {Object} props.rule
 * @param {Function} props.onChange
 */
function TaxonomyControls( { rule, onChange } ) {
	return (
		<>
			<TextControl
				label={ __( 'Taxonomy Slug', 'designsetgo' ) }
				value={ rule.taxonomy ?? '' }
				onChange={ ( taxonomy ) => onChange( { ...rule, taxonomy } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( 'Condition', 'designsetgo' ) }
				value={ rule.op ?? 'has' }
				options={ TAXONOMY_OP_OPTIONS }
				onChange={ ( op ) => onChange( { ...rule, op } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'Term Slug', 'designsetgo' ) }
				value={ rule.value ?? '' }
				onChange={ ( value ) => onChange( { ...rule, value } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</>
	);
}

/**
 * Renders the extra controls for an index-type rule.
 *
 * @param {Object} props
 * @param {Object} props.rule
 * @param {Function} props.onChange
 */
function IndexControls( { rule, onChange } ) {
	const showValue = [ 'equals', 'less_than', 'greater_than' ].includes( rule.op ?? 'equals' );
	return (
		<>
			<SelectControl
				label={ __( 'Condition', 'designsetgo' ) }
				value={ rule.op ?? 'equals' }
				options={ INDEX_OP_OPTIONS }
				onChange={ ( op ) => onChange( { ...rule, op } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			{ showValue && (
				<TextControl
					label={ __( 'Index Value', 'designsetgo' ) }
					value={ String( rule.value ?? '' ) }
					onChange={ ( value ) => onChange( { ...rule, value } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			) }
		</>
	);
}

/**
 * Renders the extra controls for an auth-type rule.
 *
 * @param {Object} props
 * @param {Object} props.rule
 * @param {Function} props.onChange
 */
function AuthControls( { rule, onChange } ) {
	return (
		<SelectControl
			label={ __( 'Auth State', 'designsetgo' ) }
			value={ rule.op ?? 'logged_in' }
			options={ AUTH_OP_OPTIONS }
			onChange={ ( op ) => onChange( { ...rule, op } ) }
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
}

/**
 * A single visibility rule row.
 *
 * @param {Object}   props
 * @param {Object}   props.rule      Rule object.
 * @param {Function} props.onChange  Called with updated rule object.
 * @param {Function} props.onRemove  Called when the rule is removed.
 */
export default function RuleRow( { rule, onChange, onRemove } ) {
	return (
		<div className="dsgo-visibility-rule-row">
			<HStack alignment="top">
				<div className="dsgo-visibility-rule-row__controls">
					<SelectControl
						label={ __( 'Rule Type', 'designsetgo' ) }
						value={ rule.type ?? 'meta' }
						options={ TYPE_OPTIONS }
						onChange={ ( type ) => onChange( { ...rule, type } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					{ ( rule.type === 'meta' || ! rule.type ) && (
						<MetaControls rule={ rule } onChange={ onChange } />
					) }
					{ rule.type === 'taxonomy' && (
						<TaxonomyControls rule={ rule } onChange={ onChange } />
					) }
					{ rule.type === 'index' && (
						<IndexControls rule={ rule } onChange={ onChange } />
					) }
					{ rule.type === 'auth' && (
						<AuthControls rule={ rule } onChange={ onChange } />
					) }
				</div>
				<Button
					variant="tertiary"
					isDestructive
					onClick={ onRemove }
					aria-label={ __( 'Remove rule', 'designsetgo' ) }
				>
					{ __( 'Remove', 'designsetgo' ) }
				</Button>
			</HStack>
		</div>
	);
}
