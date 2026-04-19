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
import { Notice, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { DsgoInspectorPanel } from '../../components/shared';

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
	// a Notice if legacy content already violates the invariant. We split
	// siblings by position — `earlier` siblings are the ones we treat as
	// "already present" when deciding whether this face is a newly inserted
	// duplicate, while `all` drives the inspector dropdown's occupied-side
	// state (which must consider every sibling regardless of order).
	const { earlierSiblingSides, allSiblingSides } = useSelect(
		(select) => {
			const { getBlockRootClientId, getBlock } =
				select('core/block-editor');
			const parentId = getBlockRootClientId(clientId);
			if (!parentId) {
				return { earlierSiblingSides: [], allSiblingSides: [] };
			}
			const siblings = getBlock(parentId)?.innerBlocks || [];
			const toSide = (sibling) => {
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
			};
			const selfIndex = siblings.findIndex(
				(sibling) => sibling.clientId === clientId
			);
			const earlier = siblings
				.slice(0, selfIndex === -1 ? 0 : selfIndex)
				.map(toSide)
				.filter(Boolean);
			const all = siblings
				.filter((sibling) => sibling.clientId !== clientId)
				.map(toSide)
				.filter(Boolean);
			return { earlierSiblingSides: earlier, allSiblingSides: all };
		},
		[clientId]
	);
	const siblingSides = allSiblingSides;
	const hasDuplicateSide = siblingSides.includes(side);

	// When an author deletes a face and inserts a replacement via the parent's
	// inserter, the new block uses the block.json default `side: 'front'`. If
	// an earlier sibling already occupies this face's side and the opposite
	// side is free, flip *this* face so the parent keeps exactly one front
	// and one back. Using *earlier* siblings (rather than all siblings) is
	// the tie-breaker: without it, two faces with the same side would each
	// try to switch in the same tick and the older face would usually win.
	// Only auto-correct when the opposite side is actually available —
	// otherwise leave the duplicate in place so the Notice guides the author.
	useEffect(() => {
		if (!earlierSiblingSides.includes(side)) {
			return;
		}
		const oppositeSide = side === 'front' ? 'back' : 'front';
		if (!siblingSides.includes(oppositeSide)) {
			setAttributes({ side: oppositeSide });
		}
	}, [earlierSiblingSides, siblingSides, side, setAttributes]);

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
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => setAttributes({ side: 'front' })}
				>
					<DsgoInspectorPanel.Item
						label={__('Side', 'designsetgo')}
						hasValue={() => side !== 'front'}
						onDeselect={() => setAttributes({ side: 'front' })}
						isShownByDefault
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
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>
			<div {...innerBlocksProps} />
		</>
	);
}
