/**
 * Background Video Extension - Editor Preview
 *
 * Mirrors the front end: the block itself carries `dsgo-has-video-background`
 * and the video sits in a layer that is its first child (frontend.js inserts
 * `.dsgo-video-background` the same way).
 *
 * Loaded eagerly and rendered for every allowed block, video or not. The
 * block stays at the same place in the React tree and the layer is portalled
 * into it, so adding or removing a video doesn't remount the block (which
 * reset its inspector and dropped focus after the media picker closed).
 */

import { __ } from '@wordpress/i18n';
import { createPortal, useLayoutEffect, useState } from '@wordpress/element';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Inline so a block's own `> *:not(…)` stacking rules can't turn the layer
 * into a flow element.
 */
const LAYER_STYLE = {
	position: 'absolute',
	top: 0,
	left: 0,
	width: '100%',
	height: '100%',
	zIndex: 0,
	overflow: 'hidden',
	pointerEvents: 'none',
};

/**
 * Find a block's element in the editor canvas.
 *
 * @param {string} clientId Block client ID.
 * @return {HTMLElement|null} Block element.
 */
function findBlockElement(clientId) {
	const id = `block-${clientId}`;
	const canvas = document.querySelector(
		'iframe[name="editor-canvas"]'
	)?.contentDocument;
	return canvas?.getElementById(id) || document.getElementById(id);
}

/**
 * Video layer, portalled in as the block element's first child.
 *
 * @param {Object} props            Component props.
 * @param {string} props.clientId   Block client ID.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element|null} Portal, once the block element exists.
 */
function VideoLayer({ clientId, attributes }) {
	const { dsgoVideoUrl, dsgoVideoPoster, dsgoVideoOverlayColor } = attributes;
	const [layer, setLayer] = useState(null);

	// Runs after every render: the block element is replaced when, say, a
	// Section's HTML tag changes, and the layer has to follow it. setLayer()
	// only fires when the node changes, so this settles after one pass.
	// eslint-disable-next-line react-hooks/exhaustive-deps
	useLayoutEffect(() => {
		const block = findBlockElement(clientId);
		if (!block) {
			return;
		}
		let node = layer;
		if (!node || node.ownerDocument !== block.ownerDocument) {
			node = block.ownerDocument.createElement('div');
			node.className = 'dsgo-video-background-editor';
			Object.assign(node.style, LAYER_STYLE);
		}
		if (node.parentNode !== block) {
			block.insertBefore(node, block.firstChild);
		}
		if (node !== layer) {
			setLayer(node);
		}
	});

	useLayoutEffect(() => () => layer?.remove(), [layer]);

	if (!layer) {
		return null;
	}

	return createPortal(
		<>
			<video
				src={dsgoVideoUrl}
				poster={dsgoVideoPoster}
				autoPlay
				loop
				muted
				playsInline
				style={{ width: '100%', height: '100%', objectFit: 'cover' }}
			/>
			{dsgoVideoOverlayColor && (
				<div
					className="dsgo-video-overlay"
					style={{
						backgroundColor: convertColorToCSSVar(
							dsgoVideoOverlayColor
						),
						opacity: 0.7,
					}}
				/>
			)}
			<span className="dsgo-video-background-editor__label">
				{__('Video Background', 'designsetgo')}
			</span>
		</>,
		layer
	);
}

/**
 * BlockListBlock wrapper for blocks that can have a background video.
 *
 * @param {Object} props                Block list block props.
 * @param {Object} props.BlockListBlock Original BlockListBlock component.
 * @return {JSX.Element} Block, plus its video layer when it has a video.
 */
export function BackgroundVideoBlock({ BlockListBlock, ...props }) {
	const hasVideo = !!props.attributes.dsgoVideoUrl;
	return (
		<>
			<BlockListBlock
				{...props}
				className={
					hasVideo
						? `${props.className || ''} dsgo-has-video-background`.trim()
						: props.className
				}
			/>
			{hasVideo && (
				<VideoLayer
					clientId={props.clientId}
					attributes={props.attributes}
				/>
			)}
		</>
	);
}
