/**
 * Interaction Layers - Detail editor
 *
 * One interaction's full configuration. Lives in a modal rather than the
 * sidebar because the field count grows with the action vocabulary and a
 * 280px column cannot hold it legibly.
 *
 * @package
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useState } from '@wordpress/element';
import {
	Modal,
	SelectControl,
	TextControl,
	ToggleControl,
	Button,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalVStack as VStack,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalHStack as HStack,
} from '@wordpress/components';
import {
	TRIGGERS,
	ACTIONS,
	ACTION_GROUPS,
	TARGET_MODES,
	ACTION_VALUE_FIELD,
	OFFSET_ACTIONS,
	HIDDEN_CLASS,
} from '../constants';

import { useSelectorMatchCount } from '../useSelectorMatchCount';
import { useCanvasPicker } from '../useCanvasPicker';

/** Actions whose payload is an attribute name. */
const ATTRIBUTE_ACTIONS = ['setAttribute', 'removeAttribute'];

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
 * Edit a single interaction.
 *
 * @param {Object}   props             Component props.
 * @param {Object}   props.interaction Interaction config.
 * @param {Function} props.onChange    Receives the updated interaction.
 * @param {Function} props.onClose     Closes the modal.
 * @return {Element} The modal.
 */
export function InteractionModal({ interaction, onChange, onClose }) {
	const set = (key) => (val) => onChange({ ...interaction, [key]: val });

	const usesSelector = 'self' !== interaction.targetMode;

	// Set when a picked block cannot carry a generated class, so the picker
	// produced nothing usable. Telling the author why beats a silently
	// unchanged field.
	const [pickFailed, setPickFailed] = useState(false);

	const handlePick = useCallback(
		(selector) => {
			if (!selector) {
				setPickFailed(true);
				return;
			}
			setPickFailed(false);
			onChange({
				...interaction,
				targetMode:
					'parent' === interaction.targetMode ? 'parent' : 'selector',
				targetSelector: selector,
			});
		},
		[interaction, onChange]
	);

	const { isPicking, startPicking, cancelPicking } =
		useCanvasPicker(handlePick);

	const matchCount = useSelectorMatchCount(
		interaction.targetSelector,
		usesSelector && !isPicking
	);

	const valueField = ACTION_VALUE_FIELD[interaction.action];

	// While picking, the modal steps out of the way so the canvas is
	// clickable. It is not unmounted — that would lose in-progress edits.
	if (isPicking) {
		return (
			<Modal
				title={__('Pick a target', 'designsetgo')}
				onRequestClose={cancelPicking}
				className="dsgo-interaction-modal is-picking"
				overlayClassName="dsgo-interaction-modal__overlay is-picking"
				isDismissible={false}
				shouldCloseOnClickOutside={false}
			>
				<VStack spacing={3}>
					<p>
						{__(
							'Click any block in the editor to target it. Blocks without an anchor or class will be given one automatically.',
							'designsetgo'
						)}
					</p>
					<Button variant="secondary" onClick={cancelPicking}>
						{__('Cancel (Esc)', 'designsetgo')}
					</Button>
				</VStack>
			</Modal>
		);
	}

	return (
		<Modal
			title={__('Edit interaction', 'designsetgo')}
			onRequestClose={onClose}
			className="dsgo-interaction-modal"
		>
			<VStack spacing={4}>
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
					<VStack spacing={2}>
						<HStack alignment="bottom" spacing={2}>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								className="dsgo-interaction-modal__selector"
								label={__('CSS selector', 'designsetgo')}
								placeholder=".my-panel"
								value={interaction.targetSelector || ''}
								onChange={set('targetSelector')}
							/>
							<Button variant="secondary" onClick={startPicking}>
								{__('Pick on canvas', 'designsetgo')}
							</Button>
						</HStack>
						{pickFailed && (
							<Notice
								status="warning"
								isDismissible={false}
								onRemove={() => setPickFailed(false)}
							>
								{__(
									'That block cannot be given a CSS class, so it cannot be targeted automatically. Give it an HTML anchor under Advanced, then enter #your-anchor here.',
									'designsetgo'
								)}
							</Notice>
						)}
						{!pickFailed && selectorHelp(matchCount) && (
							<p className="dsgo-interaction-modal__hint">
								{selectorHelp(matchCount)}
							</p>
						)}
					</VStack>
				)}

				{/* Grouped rather than a flat list: there are enough actions
				   now that scanning one long select is the slow part. */}
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={__('Do', 'designsetgo')}
					value={interaction.action}
					onChange={set('action')}
				>
					{ACTION_GROUPS.map((group) => {
						const inGroup = ACTIONS.filter(
							(a) => a.group === group.key
						);
						if (!inGroup.length) {
							return null;
						}
						return (
							<optgroup key={group.key} label={group.label}>
								{inGroup.map((a) => (
									<option key={a.value} value={a.value}>
										{a.label}
									</option>
								))}
							</optgroup>
						);
					})}
				</SelectControl>

				{ATTRIBUTE_ACTIONS.includes(interaction.action) && (
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

				{'show' === interaction.action && (
					<Notice status="info" isDismissible={false}>
						{sprintf(
							/* translators: %s: CSS class name. */
							__(
								'For this to reveal anything, the target must start hidden. Add the class %s to it under Advanced → Additional CSS class(es).',
								'designsetgo'
							),
							HIDDEN_CLASS
						)}
					</Notice>
				)}

				{'click' === interaction.trigger && (
					<Notice status="info" isDismissible={false}>
						{__(
							'Blocks that are not already buttons or links will be given keyboard and screen-reader button semantics automatically.',
							'designsetgo'
						)}
					</Notice>
				)}

				<HStack justify="flex-end">
					<Button variant="primary" onClick={onClose}>
						{__('Done', 'designsetgo')}
					</Button>
				</HStack>
			</VStack>
		</Modal>
	);
}
