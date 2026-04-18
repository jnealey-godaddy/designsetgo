/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	BlockControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { alignLeft, alignCenter, alignRight } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import './editor.scss';
import ScrollAccordionPlaceholder from './components/ScrollAccordionPlaceholder';

/**
 * Edit component for the Scroll Accordion block.
 * Container for sticky stacking accordion items.
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string}   props.clientId      Block client ID
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
	const { alignItems } = attributes;

	const hasInnerBlocks = useSelect(
		(select) =>
			select(blockEditorStore).getBlock(clientId)?.innerBlocks?.length >
			0,
		[clientId]
	);

	// Calculate inner styles declaratively
	const innerStyles = {
		display: 'flex',
		flexDirection: 'column',
		alignItems: alignItems || 'flex-start',
	};

	const blockProps = useBlockProps({
		className: 'dsgo-scroll-accordion',
		style: {
			width: '100%',
			alignSelf: 'stretch',
		},
	});

	// Initial seeding is handled by ScrollAccordionPlaceholder so authors pick
	// a starter layout instead of landing on a generic three-card template.
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-scroll-accordion__items',
			style: innerStyles,
		},
		{
			allowedBlocks: ['designsetgo/scroll-accordion-item'],
			orientation: 'vertical',
			renderAppender: false,
		}
	);

	if (!hasInnerBlocks) {
		return (
			<div {...blockProps}>
				<ScrollAccordionPlaceholder
					clientId={clientId}
					setAttributes={setAttributes}
				/>
			</div>
		);
	}

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={alignLeft}
						label={__('Align items left', 'designsetgo')}
						isPressed={alignItems === 'flex-start'}
						onClick={() =>
							setAttributes({ alignItems: 'flex-start' })
						}
					/>
					<ToolbarButton
						icon={alignCenter}
						label={__('Align items center', 'designsetgo')}
						isPressed={alignItems === 'center'}
						onClick={() => setAttributes({ alignItems: 'center' })}
					/>
					<ToolbarButton
						icon={alignRight}
						label={__('Align items right', 'designsetgo')}
						isPressed={alignItems === 'flex-end'}
						onClick={() =>
							setAttributes({ alignItems: 'flex-end' })
						}
					/>
				</ToolbarGroup>
			</BlockControls>

			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}
