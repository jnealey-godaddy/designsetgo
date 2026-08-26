import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockColors, useUniqueBlockId } from '../../hooks';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import TextPathControls from './components/TextPathControls';
import TextPathGraphic from './components/TextPathGraphic';
import { initTextPathMotion, stopTextPathMotion } from './motion';
import {
	clamp,
	findFirstTextPathClientId,
	getSafeTextPathColor,
	getSafeTextPathUrl,
} from './utils';

export default function TextPathEdit({ attributes, setAttributes, clientId }) {
	useUniqueBlockId({
		clientId,
		attributeName: 'uniqueId',
		value: attributes.uniqueId,
		setAttributes,
		prefix: 'text-path-',
	});
	// Selecting a boolean keeps this block out of the re-render path for every
	// unrelated store change, and the scan reads a flat client-ID list instead
	// of materialising the whole block tree.
	const hasDuplicateUniqueId = useSelect(
		(select) => {
			if (!attributes.uniqueId) {
				return false;
			}

			const {
				getBlockAttributes,
				getBlockName,
				getClientIdsWithDescendants,
			} = select(blockEditorStore);
			const ownerClientId = findFirstTextPathClientId(
				getClientIdsWithDescendants(),
				attributes.uniqueId,
				{ getBlockAttributes, getBlockName }
			);

			return !!ownerClientId && ownerClientId !== clientId;
		},
		[attributes.uniqueId, clientId]
	);

	useEffect(() => {
		if (hasDuplicateUniqueId) {
			setAttributes({ uniqueId: '' });
		}
	}, [hasDuplicateUniqueId, setAttributes]);
	const safeUrl = getSafeTextPathUrl(attributes.url);
	const rotation = clamp(attributes.rotation, -360, 360);
	const safeGuideColor = getSafeTextPathColor(attributes.guideColor);
	const safeCircleBackgroundColor = getSafeTextPathColor(
		attributes.circleBackgroundColor
	);
	const { settings: guideColorSettings, colorGradientSettings } =
		useBlockColors({
			attributes,
			setAttributes,
			entries: [
				{
					label: __('Guide line', 'designsetgo'),
					attribute: 'guideColor',
				},
				...(attributes.pathType === 'circle'
					? [
							{
								label: __('Circle background', 'designsetgo'),
								attribute: 'circleBackgroundColor',
							},
						]
					: []),
			],
		});
	const motionRef = useRef(null);
	const blockProps = useBlockProps({
		ref: motionRef,
		className: `dsgo-text-path${
			attributes.pathAlignment === 'center' ||
			attributes.pathAlignment === 'right'
				? ` dsgo-text-path--align-${attributes.pathAlignment}`
				: ''
		}`,
		style: {
			'--dsgo-text-path-rotation': `${rotation}deg`,
			'--dsgo-text-path-guide-opacity': String(
				Math.max(0, Math.min(1, Number(attributes.guideOpacity) || 0))
			),
			'--dsgo-text-path-guide-stroke-width': String(
				Math.max(
					0,
					Math.min(24, Number(attributes.guideStrokeWidth) || 0)
				)
			),
			'--dsgo-text-path-width': `${Math.max(
				25,
				Math.min(100, Number(attributes.pathWidth) || 100)
			)}%`,
			...(safeGuideColor && {
				'--dsgo-text-path-guide-color':
					convertColorToCSSVar(safeGuideColor),
			}),
			...(safeCircleBackgroundColor && {
				'--dsgo-text-path-circle-background': convertColorToCSSVar(
					safeCircleBackgroundColor
				),
			}),
		},
		...(attributes.motion && {
			'data-dsgo-text-path-motion': 'true',
			'data-dsgo-text-path-motion-duration': String(
				Math.max(
					2,
					Math.min(120, Number(attributes.motionDuration) || 12)
				)
			),
			'data-dsgo-text-path-motion-direction':
				attributes.motionDirection === 'reverse'
					? 'reverse'
					: 'forward',
		}),
	});
	// Preview the motion on the canvas. Without this the Motion toggle looks
	// like it does nothing -- the frontend runs it from view.js, which the
	// editor never loads. Restart on any change that alters the path or the
	// timing, because the engine reads those once when it starts.
	useEffect(() => {
		const node = motionRef.current;

		if (!node) {
			return undefined;
		}

		stopTextPathMotion(node);

		if (attributes.motion) {
			initTextPathMotion(node);
		}

		return () => stopTextPathMotion(node);
	}, [
		attributes.motion,
		attributes.motionDuration,
		attributes.motionDirection,
		attributes.pathType,
		attributes.customPath,
		attributes.arcSize,
		attributes.startOffset,
		attributes.text,
		attributes.pathFontSize,
	]);

	const graphic = <TextPathGraphic attributes={attributes} />;

	return (
		<>
			<InspectorControls>
				<TextPathControls
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Path colors', 'designsetgo')}
					settings={guideColorSettings}
					{...colorGradientSettings}
				/>
			</InspectorControls>
			<div {...blockProps}>
				{safeUrl ? <a href={safeUrl}>{graphic}</a> : graphic}
			</div>
		</>
	);
}
