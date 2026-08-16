/**
 * Interaction Layers - One interaction's controls.
 *
 * Collapsed by default: a list of five interactions is unreadable if every
 * one of them is a stack of seven controls, so each row summarises itself
 * and expands only when the author is editing it.
 *
 * @package
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalVStack as VStack,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { chevronDown, chevronUp, trash } from '@wordpress/icons';
import {
	TRIGGERS,
	ACTIONS,
	TARGET_MODES,
	ACTION_VALUE_FIELD,
	OFFSET_ACTIONS,
} from '../constants';
import { summariseInteraction, summariseTarget } from '../summarise';
import { useSelectorMatchCount } from '../useSelectorMatchCount';

/**
 * Help text describing how many elements the selector currently matches.
 *
 * @param {number|null} count Match count from useSelectorMatchCount.
 * @return {string|undefined} Help string, or undefined when there is nothing to say.
 */
function selectorHelp(count) {
	if (null === count) {
		return undefined;
	}
	if (-1 === count) {
		return __('That is not a valid CSS selector.', 'designsetgo');
	}
	if (0 === count) {
		return __('No elements on this page match.', 'designsetgo');
	}
	return sprintf(
		/* translators: %d: number of matching elements. */
		_n('Matches %d element.', 'Matches %d elements.', count, 'designsetgo'),
		count
	);
}

/**
 * Render the controls for a single interaction.
 *
 * @param {Object}   props             Component props.
 * @param {Object}   props.interaction Interaction config.
 * @param {boolean}  props.isOpen      Whether the row is expanded.
 * @param {Function} props.onToggle    Toggles expansion.
 * @param {Function} props.onChange    Receives the updated interaction.
 * @param {Function} props.onRemove    Called with no arguments.
 * @return {Element} The row.
 */
export function InteractionRow({
	interaction,
	isOpen,
	onToggle,
	onChange,
	onRemove,
}) {
	const set = (key) => (val) => onChange({ ...interaction, [key]: val });

	const usesSelector = 'self' !== interaction.targetMode;
	const matchCount = useSelectorMatchCount(
		interaction.targetSelector,
		usesSelector && isOpen
	);

	const valueField = ACTION_VALUE_FIELD[interaction.action];
	const target = summariseTarget(interaction);

	return (
		<VStack spacing={0} className="dsgo-interaction-row">
			<HStack
				spacing={1}
				justify="space-between"
				className="dsgo-interaction-row__header"
			>
				<Button
					className="dsgo-interaction-row__toggle"
					icon={isOpen ? chevronUp : chevronDown}
					onClick={onToggle}
					aria-expanded={isOpen}
					label={
						isOpen
							? __('Collapse interaction', 'designsetgo')
							: __('Expand interaction', 'designsetgo')
					}
					showTooltip
				>
					<span className="dsgo-interaction-row__summary">
						{summariseInteraction(interaction)}
						{target && (
							<span className="dsgo-interaction-row__target">
								{target}
							</span>
						)}
					</span>
				</Button>

				<Button
					icon={trash}
					isDestructive
					size="small"
					onClick={onRemove}
					label={__('Remove interaction', 'designsetgo')}
					showTooltip
				/>
			</HStack>

			{isOpen && (
				<VStack spacing={3} className="dsgo-interaction-row__body">
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
							help={__(
								'For example: Escape, Enter, a. Leave empty for any key.',
								'designsetgo'
							)}
							value={interaction.key || ''}
							onChange={set('key')}
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

					{usesSelector && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('CSS selector', 'designsetgo')}
							placeholder=".my-panel"
							help={selectorHelp(matchCount)}
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
							help={__(
								'For example: aria-expanded. Event handlers are not allowed.',
								'designsetgo'
							)}
							value={interaction.attributeName || ''}
							onChange={set('attributeName')}
						/>
					)}

					{valueField && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={valueField.label}
							help={valueField.help}
							value={interaction.value || ''}
							onChange={set('value')}
						/>
					)}

					{OFFSET_ACTIONS.includes(interaction.action) && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							label={__('Offset (px)', 'designsetgo')}
							help={__(
								'Stop this far above the target — useful to clear a sticky header.',
								'designsetgo'
							)}
							value={interaction.offset ?? 0}
							onChange={(val) => set('offset')(Number(val) || 0)}
						/>
					)}

					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Only once', 'designsetgo')}
						help={__(
							'Run the first time it is triggered, then never again.',
							'designsetgo'
						)}
						checked={!!interaction.once}
						onChange={set('once')}
					/>
				</VStack>
			)}
		</VStack>
	);
}
