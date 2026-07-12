/**
 * Text Style RichText Format
 *
 * Allows users to apply custom inline styles to selected text:
 * - Text color (solid + gradient)
 * - Highlight/background color (solid + gradient)
 * - Font size (presets + custom)
 *
 * @since 1.3.0
 */

import './editor.scss';
// Note: style.scss is imported in src/style.scss for frontend loading

import { __ } from '@wordpress/i18n';
import {
	useState,
	useCallback,
	useRef,
	lazy,
	Suspense,
} from '@wordpress/element';
import {
	registerFormatType,
	applyFormat,
	removeFormat,
	getActiveFormat,
} from '@wordpress/rich-text';
import { BlockControls } from '@wordpress/block-editor';
import {
	Popover,
	ToolbarGroup,
	ToolbarButton,
	Spinner,
} from '@wordpress/components';
import { textColor as textColorIcon } from '@wordpress/icons';

import { FORMAT_NAME, CSS_CLASSES } from './constants';
import { generateStyleString, parseStyleString, hasAnyStyle } from './utils';

// Lazy-load the popover content (SizeSection, ColorSection are large)
const TextStylePopover = lazy(
	() =>
		import(
			/* webpackChunkName: "fmt-text-style" */ './components/TextStylePopover'
		)
);

/**
 * Text Style Edit Component
 *
 * Renders the toolbar button and popover for applying text styles.
 *
 * @param {Object}   props          - Component props from registerFormatType
 * @param {boolean}  props.isActive - Whether the format is currently active
 * @param {Object}   props.value    - RichText value object
 * @param {Function} props.onChange - Callback to update RichText value
 * @return {JSX.Element} The edit component
 */
function TextStyleEdit({ isActive, value, onChange }) {
	const [isPopoverOpen, setIsPopoverOpen] = useState(false);
	const buttonRef = useRef(null);

	// Get current format attributes if active
	const activeFormat = getActiveFormat(value, FORMAT_NAME);
	const currentStyles = parseStyleString(
		activeFormat?.attributes?.style || '',
		activeFormat?.attributes?.class || ''
	);

	/**
	 * Apply styles to selected text
	 */
	const applyStyles = useCallback(
		(newStyles) => {
			const { style, class: className } = generateStyleString(newStyles);

			if (!hasAnyStyle(newStyles)) {
				// Remove format if no styles
				onChange(removeFormat(value, FORMAT_NAME));
			} else {
				// Apply format with new styles
				onChange(
					applyFormat(value, {
						type: FORMAT_NAME,
						attributes: {
							style,
							class: className,
						},
					})
				);
			}
		},
		[value, onChange]
	);

	/**
	 * Clear all styles
	 */
	const clearStyles = useCallback(() => {
		onChange(removeFormat(value, FORMAT_NAME));
		setIsPopoverOpen(false);
	}, [value, onChange]);

	/**
	 * Toggle popover
	 */
	const togglePopover = useCallback(() => {
		setIsPopoverOpen((prev) => !prev);
	}, []);

	return (
		<BlockControls group="inline">
			<ToolbarGroup>
				<ToolbarButton
					ref={buttonRef}
					icon={textColorIcon}
					title={__('Text Style', 'designsetgo')}
					onClick={togglePopover}
					isActive={isActive || isPopoverOpen}
				/>
			</ToolbarGroup>
			{isPopoverOpen && (
				<Popover
					className="dsgo-text-style-popover"
					anchor={buttonRef.current}
					placement="bottom-start"
					onClose={() => setIsPopoverOpen(false)}
					focusOnMount="firstElement"
				>
					<Suspense
						fallback={
							<div
								className="dsgo-text-style-popover__loading"
								// eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex
								tabIndex={0}
								style={{ padding: '16px' }}
							>
								<Spinner />
							</div>
						}
					>
						<TextStylePopover
							styles={currentStyles}
							onChange={applyStyles}
							onClear={clearStyles}
							onClose={() => setIsPopoverOpen(false)}
						/>
					</Suspense>
				</Popover>
			)}
		</BlockControls>
	);
}

/**
 * Register the Text Style format type
 */
registerFormatType(FORMAT_NAME, {
	title: __('Text Style', 'designsetgo'),
	tagName: 'span',
	className: CSS_CLASSES.BASE,
	attributes: {
		style: 'style',
		class: 'class',
	},
	edit: TextStyleEdit,
});
