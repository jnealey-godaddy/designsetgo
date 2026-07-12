/**
 * DsgoJustificationToolbar
 *
 * Toolbar control for the `justification` attribute.
 *
 * `@wordpress/block-editor` 14.21 does not export a public JustifyToolbar, so
 * this is a thin ToolbarGroup over the same icons core uses.
 */

import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup } from '@wordpress/components';
import { JUSTIFICATION_OPTIONS } from '../../../utils/justification';

/**
 * @param {Object}   props          Component props.
 * @param {string}   props.value    Current justification.
 * @param {Function} props.onChange Called with the next justification value.
 * @return {JSX.Element} Toolbar group inside BlockControls.
 */
export default function DsgoJustificationToolbar({ value, onChange }) {
	return (
		<BlockControls group="block">
			<ToolbarGroup
				controls={JUSTIFICATION_OPTIONS.map((option) => ({
					icon: option.icon,
					title: option.label,
					isActive: value === option.value,
					onClick: () => onChange(option.value),
				}))}
			/>
		</BlockControls>
	);
}
