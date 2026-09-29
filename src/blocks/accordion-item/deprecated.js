/**
 * Accordion Item Block - Deprecations
 *
 * v1: save() before the icon size moved out of the markup. Every icon SVG
 * carried hard-coded width="16" height="16" attributes; the current save()
 * drops them because style.scss already sizes the icon (1.5rem box, SVG at
 * 100%), so the attributes only ever baked a size into stored markup.
 * Markup-only change with an unchanged attribute schema, so no isEligible:
 * stored HTML no longer matches the current save(), and this frozen copy
 * reproduces it.
 *
 * Context note: save() never receives block context, so the parent-accordion
 * fallbacks below ('chevron', 'right') are what every stored item was
 * written with.
 *
 * @package
 */

import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import classnames from 'classnames';
import metadata from './block.json';

// Icon SVGs - same as edit.js
const ChevronIconV1 = () => (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
		<path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z" />
	</svg>
);

const PlusMinusIconV1 = ({ isOpen }) => (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
		{isOpen ? (
			<path d="M4 8h8v1H4z" />
		) : (
			<>
				<path
					d="M8 4v8M4 8h8"
					stroke="currentColor"
					strokeWidth="1"
					fill="none"
				/>
			</>
		)}
	</svg>
);

const CaretIconV1 = () => (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
		<path d="M6 7l2 2 2-2z" />
	</svg>
);

function saveV1({ attributes, context }) {
	const { title, isOpen, uniqueId } = attributes;

	// Get context from parent accordion (same as edit.js)
	const iconStyle = context?.['designsetgo/accordion/iconStyle'] || 'chevron';
	const iconPosition =
		context?.['designsetgo/accordion/iconPosition'] || 'right';

	// Same classes as edit.js - MUST MATCH
	const itemClasses = classnames('dsgo-accordion-item', {
		'dsgo-accordion-item--open': isOpen,
		'dsgo-accordion-item--closed': !isOpen,
	});

	const blockProps = useBlockProps.save({
		className: itemClasses,
		'data-initially-open': isOpen,
	});

	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-accordion-item__content',
	});

	// Render icon (same logic as edit.js)
	const renderIcon = () => {
		if (iconStyle === 'none') {
			return null;
		}

		let IconComponent;
		switch (iconStyle) {
			case 'plus-minus':
				IconComponent = () => <PlusMinusIconV1 isOpen={isOpen} />;
				break;
			case 'caret':
				IconComponent = CaretIconV1;
				break;
			case 'chevron':
			default:
				IconComponent = ChevronIconV1;
		}

		return (
			<span className="dsgo-accordion-item__icon" aria-hidden="true">
				<IconComponent />
			</span>
		);
	};

	const headerId = `${uniqueId}-header`;
	const panelId = `${uniqueId}-panel`;

	return (
		<div {...blockProps}>
			<div className="dsgo-accordion-item__header">
				<button
					type="button"
					className={classnames('dsgo-accordion-item__trigger', {
						'dsgo-accordion-item__trigger--icon-left':
							iconPosition === 'left',
						'dsgo-accordion-item__trigger--icon-right':
							iconPosition === 'right',
					})}
					aria-expanded={isOpen}
					aria-controls={panelId}
					id={headerId}
				>
					{iconPosition === 'left' && renderIcon()}
					<RichText.Content
						tagName="span"
						className="dsgo-accordion-item__title"
						value={title}
					/>
					{iconPosition === 'right' && renderIcon()}
				</button>
			</div>

			<div
				className="dsgo-accordion-item__panel"
				role="region"
				aria-labelledby={headerId}
				id={panelId}
				hidden={!isOpen}
			>
				<div {...innerBlocksProps} />
			</div>
		</div>
	);
}

const v1 = {
	apiVersion: 3,
	attributes: metadata.attributes,
	supports: metadata.supports,
	save: saveV1,
};

export default [v1];
