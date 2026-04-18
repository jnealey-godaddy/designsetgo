/**
 * Flip Card Face - Edit Component
 *
 * Replaces the separate flip-card-front and flip-card-back blocks. The
 * `side` attribute (front|back) controls placement inside the parent
 * flip card. Kept as a child-only block so it can only appear inside
 * designsetgo/flip-card.
 *
 * @since 2.0.51
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function FlipCardFaceEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const side = attributes.side === 'back' ? 'back' : 'front';

	// The parent flip-card's view script and stylesheet assume exactly one
	// front face and one back face — a duplicate side (two fronts, or the
	// front face renamed to back while a sibling already holds back) would
	// break the flip animation. Track which side(s) siblings occupy so we
	// can disable the matching option in the Side dropdown and fall back to
	// a Notice if legacy content already violates the invariant.
	const siblingSides = useSelect(
		(select) => {
			const { getBlockRootClientId, getBlock } =
				select('core/block-editor');
			const parentId = getBlockRootClientId(clientId);
			if (!parentId) {
				return [];
			}
			const siblings = getBlock(parentId)?.innerBlocks || [];
			return siblings
				.filter((sibling) => sibling.clientId !== clientId)
				.map((sibling) => {
					if (sibling.name === 'designsetgo/flip-card-face') {
						return sibling.attributes?.side === 'back'
							? 'back'
							: 'front';
					}
					if (sibling.name === 'designsetgo/flip-card-front') {
						return 'front';
					}
					if (sibling.name === 'designsetgo/flip-card-back') {
						return 'back';
					}
					return null;
				})
				.filter(Boolean);
		},
		[clientId]
	);
	const hasDuplicateSide = siblingSides.includes(side);

	const blockProps = useBlockProps({
		className: `dsgo-flip-card__face dsgo-flip-card__${side}`,
	});

	// Template is seeded once on first insertion; switching `side` later does
	// not re-template, so a face relabelled "back" will still show the
	// original "Front of Card" placeholder heading until the author edits it.
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		template: [
			[
				'core/heading',
				{
					content:
						side === 'back'
							? __('Back of Card', 'designsetgo')
							: __('Front of Card', 'designsetgo'),
					level: 2,
					textAlign: 'center',
				},
			],
			[
				'core/paragraph',
				{
					content: __('Add any blocks you want here…', 'designsetgo'),
					align: 'center',
				},
			],
		],
		templateLock: false,
	});

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Face Settings', 'designsetgo')}
					initialOpen={true}
				>
					<SelectControl
						label={__('Side', 'designsetgo')}
						value={side}
						options={[
							{
								label: __('Front', 'designsetgo'),
								value: 'front',
								disabled:
									side !== 'front' &&
									siblingSides.includes('front'),
							},
							{
								label: __('Back', 'designsetgo'),
								value: 'back',
								disabled:
									side !== 'back' &&
									siblingSides.includes('back'),
							},
						]}
						onChange={(value) => setAttributes({ side: value })}
						help={__(
							'Choose whether this face shows on the front or back of the flip card.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					{hasDuplicateSide && (
						<Notice status="warning" isDismissible={false}>
							{side === 'back'
								? __(
										'Another face on this flip card is already set to Back. Change one of them to Front so the card can flip.',
										'designsetgo'
									)
								: __(
										'Another face on this flip card is already set to Front. Change one of them to Back so the card can flip.',
										'designsetgo'
									)}
						</Notice>
					)}
				</PanelBody>
			</InspectorControls>
			<div {...innerBlocksProps} />
		</>
	);
}
