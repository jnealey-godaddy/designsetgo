/**
 * Interaction Layers - One interaction's controls.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	Button,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { TRIGGERS, ACTIONS, TARGET_MODES } from '../constants';

// Actions whose `value` field is a class name.
const CLASS_ACTIONS = ['toggleClass', 'addClass', 'removeClass'];

/**
 * Render the controls for a single interaction.
 *
 * @param {Object}   props             Component props.
 * @param {Object}   props.interaction Interaction config.
 * @param {Function} props.onChange    Receives the updated interaction.
 * @param {Function} props.onRemove    Called with no arguments.
 * @return {Element} The row.
 */
export function InteractionRow({ interaction, onChange, onRemove }) {
	const set = (key) => (val) => onChange({ ...interaction, [key]: val });

	const valueLabel = CLASS_ACTIONS.includes(interaction.action)
		? __('Class name', 'designsetgo')
		: __('Value', 'designsetgo');

	return (
		<VStack spacing={2} className="dsgo-interaction-row">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('When', 'designsetgo')}
				value={interaction.trigger}
				options={TRIGGERS}
				onChange={set('trigger')}
			/>

			{'keydown' === interaction.trigger && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={__('Key', 'designsetgo')}
					help={__('For example: Escape, Enter, a', 'designsetgo')}
					value={interaction.attributeName || ''}
					onChange={set('attributeName')}
				/>
			)}

			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Affect', 'designsetgo')}
				value={interaction.targetMode}
				options={TARGET_MODES}
				onChange={set('targetMode')}
			/>

			{'self' !== interaction.targetMode && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={__('CSS selector', 'designsetgo')}
					value={interaction.targetSelector || ''}
					onChange={set('targetSelector')}
				/>
			)}

			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Do', 'designsetgo')}
				value={interaction.action}
				options={ACTIONS}
				onChange={set('action')}
			/>

			{'setAttribute' === interaction.action && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={__('Attribute name', 'designsetgo')}
					value={interaction.attributeName || ''}
					onChange={set('attributeName')}
				/>
			)}

			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={valueLabel}
				value={interaction.value || ''}
				onChange={set('value')}
			/>

			<ToggleControl
				__nextHasNoMarginBottom
				label={__('Only once', 'designsetgo')}
				checked={!!interaction.once}
				onChange={set('once')}
			/>

			<Button isDestructive variant="tertiary" size="small" onClick={onRemove}>
				{__('Remove interaction', 'designsetgo')}
			</Button>
		</VStack>
	);
}
