/**
 * Compact color dropdown control.
 *
 * Renders a button with a color indicator swatch that opens
 * a popover containing a ColorPalette.
 *
 * @package
 */

import {
	ColorPalette,
	ColorIndicator,
	Dropdown,
	Button,
} from '@wordpress/components';

const ColorDropdownControl = ({ label, value, onChange, colors, help }) => {
	return (
		<Dropdown
			className="designsetgo-color-dropdown"
			popoverProps={{
				placement: 'bottom-start',
			}}
			renderToggle={({ isOpen, onToggle }) => (
				<Button
					onClick={onToggle}
					className="designsetgo-color-dropdown__toggle"
					aria-expanded={isOpen}
				>
					<ColorIndicator colorValue={value} />
					<span>{label}</span>
				</Button>
			)}
			renderContent={() => (
				<div className="designsetgo-color-dropdown__content">
					{help && (
						<p className="designsetgo-color-dropdown__help">
							{help}
						</p>
					)}
					<ColorPalette
						value={value}
						onChange={(color) => onChange(color || '')}
						colors={colors}
						clearable
					/>
				</div>
			)}
		/>
	);
};

export default ColorDropdownControl;
