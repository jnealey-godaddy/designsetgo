/**
 * Behavior Settings Panel Component
 *
 * Renders DsgoInspectorPanel.Item entries for the modal's behaviour
 * toggles, meant to be composed inside the Settings panel in
 * modal/edit.js.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function BehaviorSettings({ attributes, setAttributes }) {
	const {
		closeOnBackdrop,
		closeOnEsc,
		disableBodyScroll,
		allowHashTrigger,
		updateUrlOnOpen,
	} = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Close on Backdrop Click', 'designsetgo')}
				hasValue={() => closeOnBackdrop !== true}
				onDeselect={() => setAttributes({ closeOnBackdrop: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Close on Backdrop Click', 'designsetgo')}
					checked={closeOnBackdrop}
					onChange={(value) =>
						setAttributes({ closeOnBackdrop: value })
					}
					help={__(
						'Allow closing the modal by clicking outside of it.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Close on ESC Key', 'designsetgo')}
				hasValue={() => closeOnEsc !== true}
				onDeselect={() => setAttributes({ closeOnEsc: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Close on ESC Key', 'designsetgo')}
					checked={closeOnEsc}
					onChange={(value) => setAttributes({ closeOnEsc: value })}
					help={__(
						'Allow closing the modal with the Escape key.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Disable Body Scroll', 'designsetgo')}
				hasValue={() => disableBodyScroll !== true}
				onDeselect={() => setAttributes({ disableBodyScroll: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Disable Body Scroll', 'designsetgo')}
					checked={disableBodyScroll}
					onChange={(value) =>
						setAttributes({ disableBodyScroll: value })
					}
					help={__(
						'Prevent scrolling the page when modal is open.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Allow Hash Trigger', 'designsetgo')}
				hasValue={() => allowHashTrigger !== true}
				onDeselect={() => setAttributes({ allowHashTrigger: true })}
				isShownByDefault
			>
				<ToggleControl
					label={__('Allow Hash Trigger', 'designsetgo')}
					checked={allowHashTrigger}
					onChange={(value) =>
						setAttributes({ allowHashTrigger: value })
					}
					help={__(
						'Open modal when URL hash matches modal ID (e.g., #dsgo-modal-123).',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{allowHashTrigger && (
				<DsgoInspectorPanel.Item
					label={__('Update URL on Open', 'designsetgo')}
					hasValue={() => updateUrlOnOpen !== false}
					onDeselect={() => setAttributes({ updateUrlOnOpen: false })}
					isShownByDefault
				>
					<ToggleControl
						label={__('Update URL on Open', 'designsetgo')}
						checked={updateUrlOnOpen}
						onChange={(value) =>
							setAttributes({ updateUrlOnOpen: value })
						}
						help={__(
							'Update the browser URL with modal ID when opened.',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
}
