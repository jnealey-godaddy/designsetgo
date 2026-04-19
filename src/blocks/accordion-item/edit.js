import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { useEffect, useMemo } from '@wordpress/element';
import { Icon, ToggleControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useSelect, useDispatch } from '@wordpress/data';
import classnames from 'classnames';

// Icon components for different styles
const ChevronIcon = () => (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
		<path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z" />
	</svg>
);

const PlusMinusIcon = ({ isOpen }) => (
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

const CaretIcon = () => (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
		<path d="M6 7l2 2 2-2z" />
	</svg>
);

export default function AccordionItemEdit({
	attributes,
	setAttributes,
	context,
	clientId,
}) {
	const { title, isOpen, uniqueId } = attributes;

	// Get context from parent accordion
	const iconStyle = context['designsetgo/accordion/iconStyle'] || 'chevron';
	const iconPosition =
		context['designsetgo/accordion/iconPosition'] || 'right';
	const allowMultipleOpen =
		context['designsetgo/accordion/allowMultipleOpen'] || false;

	const { updateBlockAttributes } = useDispatch('core/block-editor');

	// Get sibling accordion IDs as a string for stable comparison
	const { siblingsString } = useSelect(
		(select) => {
			const { getBlockParents, getBlockOrder, getBlockName } =
				select('core/block-editor');
			const parents = getBlockParents(clientId);
			const parentId = parents[parents.length - 1];

			if (!parentId) {
				return { siblingsString: '' };
			}

			// Get all child block IDs and filter to accordion items
			const childOrder = getBlockOrder(parentId);
			const accordionSiblings = childOrder.filter(
				(id) =>
					id !== clientId &&
					getBlockName(id) === 'designsetgo/accordion-item'
			);

			return {
				// Join to string for stable value comparison
				siblingsString: accordionSiblings.join(','),
			};
		},
		[clientId]
	);

	// Parse string back to array with useMemo for stable reference
	const siblingClientIds = useMemo(
		() => (siblingsString ? siblingsString.split(',') : []),
		[siblingsString]
	);

	// Handle accordion item click
	const handleToggle = (e) => {
		e.preventDefault();
		e.stopPropagation();

		// Toggle current item
		const newIsOpen = !isOpen;
		setAttributes({ isOpen: newIsOpen });

		// If opening and single mode, close all siblings
		if (newIsOpen && !allowMultipleOpen) {
			siblingClientIds.forEach((siblingId) => {
				updateBlockAttributes(siblingId, { isOpen: false });
			});
		}
	};

	// Generate unique ID for accessibility
	useEffect(() => {
		if (!uniqueId) {
			setAttributes({
				uniqueId: `accordion-item-${Math.random().toString(36).substr(2, 9)}`,
			});
		}
	}, [uniqueId, setAttributes]);

	// Declaratively calculate classes
	const itemClasses = classnames('dsgo-accordion-item', {
		'dsgo-accordion-item--open': isOpen,
		'dsgo-accordion-item--closed': !isOpen,
	});

	const blockProps = useBlockProps({
		className: itemClasses,
	});

	// Inner blocks for accordion content
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-accordion-item__content',
		},
		{
			template: [
				[
					'core/paragraph',
					{
						placeholder: __('Add content…', 'designsetgo'),
					},
				],
			],
		}
	);

	// Render the appropriate icon
	const renderIcon = () => {
		if (iconStyle === 'none') {
			return null;
		}

		let IconComponent;
		switch (iconStyle) {
			case 'plus-minus':
				IconComponent = () => <PlusMinusIcon isOpen={isOpen} />;
				break;
			case 'caret':
				IconComponent = CaretIcon;
				break;
			case 'chevron':
			default:
				IconComponent = ChevronIcon;
		}

		return (
			<span className="dsgo-accordion-item__icon" aria-hidden="true">
				<Icon icon={<IconComponent />} />
			</span>
		);
	};

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => setAttributes({ isOpen: false })}
				>
					<DsgoInspectorPanel.Item
						label={__('Open by Default', 'designsetgo')}
						hasValue={() => isOpen !== false}
						onDeselect={() => setAttributes({ isOpen: false })}
						isShownByDefault
					>
						<ToggleControl
							label={__('Open by Default', 'designsetgo')}
							help={
								isOpen
									? __(
											'This panel will be open when the page loads',
											'designsetgo'
										)
									: __(
											'This panel will be closed when the page loads',
											'designsetgo'
										)
							}
							checked={isOpen}
							onChange={(value) =>
								setAttributes({ isOpen: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

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
						onClick={handleToggle}
					>
						{iconPosition === 'left' && renderIcon()}
						<RichText
							tagName="span"
							className="dsgo-accordion-item__title"
							value={title}
							onChange={(value) =>
								setAttributes({ title: value })
							}
							placeholder={__(
								'Accordion Item Title',
								'designsetgo'
							)}
							allowedFormats={['core/bold', 'core/italic']}
						/>
						{iconPosition === 'right' && renderIcon()}
					</button>
				</div>

				<div className="dsgo-accordion-item__panel">
					<div {...innerBlocksProps} />
				</div>
			</div>
		</>
	);
}
