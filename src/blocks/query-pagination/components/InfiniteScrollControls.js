/**
 * InfiniteScrollControls — Inspector controls for the infinite-scroll variation.
 *
 * Renders three controls inside the Settings panel only when
 * paginationKind === 'infinite'. Follows the DsgoInspectorPanel.Item convention.
 *
 * @since 2.2.0
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- NumberControl is stable in practice
	__experimentalNumberControl as NumberControl,
	TextControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

export default function InfiniteScrollControls({
	attributes,
	setAttributes,
	panelId,
	// True when the enclosing query presents its results in a carousel, where
	// the sentinel this block would emit cannot work — the front end renders a
	// Load more button instead (see CarouselNotice). The two sentinel settings
	// have nothing to act on in that case, so only the button label is offered.
	sentinelDisabled = false,
}) {
	const {
		paginationKind,
		autoPauseAfter,
		sentinelOffsetPx,
		buttonLabelWhenPaused,
	} = attributes;

	if (paginationKind !== 'infinite') {
		return null;
	}

	return (
		<>
			{!sentinelDisabled && (
				<>
					<DsgoInspectorPanel.Item
						label={__('Auto-pause after', 'designsetgo')}
						hasValue={() => autoPauseAfter !== 3}
						onDeselect={() => setAttributes({ autoPauseAfter: 3 })}
						isShownByDefault
						panelId={panelId}
					>
						<NumberControl
							label={__(
								'Auto-pause after (loads)',
								'designsetgo'
							)}
							help={__(
								'Number of automatic loads before showing the button.',
								'designsetgo'
							)}
							value={autoPauseAfter}
							min={1}
							max={20}
							onChange={(v) =>
								setAttributes({
									autoPauseAfter: parseInt(v, 10) || 3,
								})
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Sentinel offset', 'designsetgo')}
						hasValue={() => sentinelOffsetPx !== 200}
						onDeselect={() =>
							setAttributes({ sentinelOffsetPx: 200 })
						}
						isShownByDefault
						panelId={panelId}
					>
						<NumberControl
							label={__('Sentinel offset (px)', 'designsetgo')}
							help={__(
								'How many pixels before the bottom to trigger auto-load.',
								'designsetgo'
							)}
							value={sentinelOffsetPx}
							min={0}
							max={1000}
							onChange={(v) =>
								setAttributes({
									sentinelOffsetPx: parseInt(v, 10) || 200,
								})
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</>
			)}

			<DsgoInspectorPanel.Item
				label={__('Button label', 'designsetgo')}
				hasValue={() => buttonLabelWhenPaused !== 'Load more'}
				onDeselect={() =>
					setAttributes({ buttonLabelWhenPaused: 'Load more' })
				}
				isShownByDefault
				panelId={panelId}
			>
				<TextControl
					label={__('Button label (when paused)', 'designsetgo')}
					value={buttonLabelWhenPaused}
					onChange={(v) =>
						setAttributes({ buttonLabelWhenPaused: v })
					}
					placeholder={__('Load more', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
