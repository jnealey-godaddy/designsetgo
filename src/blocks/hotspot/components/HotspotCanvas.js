import { __, sprintf } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

const clampCoordinate = (value) => Math.max(0, Math.min(100, value));

export default function HotspotCanvas({
	imageUrl,
	imageAlt,
	innerBlocksProps,
	selectedItem,
	onCoordinateChange,
}) {
	const canvasRef = useRef(null);
	const activePointerId = useRef(null);
	const [announcement, setAnnouncement] = useState('');

	const updateFromPointer = (event) => {
		if (!selectedItem) {
			return;
		}
		const rectangle = canvasRef.current?.getBoundingClientRect();
		if (!rectangle?.width || !rectangle.height) {
			return;
		}
		const x = clampCoordinate(
			Math.round(
				((event.clientX - rectangle.left) / rectangle.width) * 100
			)
		);
		const y = clampCoordinate(
			Math.round(
				((event.clientY - rectangle.top) / rectangle.height) * 100
			)
		);
		onCoordinateChange(selectedItem, { x, y });
		setAnnouncement(
			sprintf(
				/* translators: 1: horizontal percentage, 2: vertical percentage. */
				__(
					'Hotspot position: %1$d%% horizontal, %2$d%% vertical.',
					'designsetgo'
				),
				x,
				y
			)
		);
	};

	const handlePointerDown = (event) => {
		const marker = event.target.closest('[data-dsgo-hotspot-item-editor]');
		if (
			!marker ||
			marker.dataset.dsgoHotspotItemEditor !== selectedItem?.clientId
		) {
			return;
		}
		activePointerId.current = event.pointerId;
		event.currentTarget.setPointerCapture?.(event.pointerId);
		updateFromPointer(event);
	};

	const handlePointerMove = (event) => {
		if (activePointerId.current === event.pointerId) {
			updateFromPointer(event);
		}
	};

	const stopDragging = (event) => {
		if (activePointerId.current === event.pointerId) {
			activePointerId.current = null;
			event.currentTarget.releasePointerCapture?.(event.pointerId);
		}
	};

	const handleKeyDown = (event) => {
		const keys = ['ArrowUp', 'ArrowRight', 'ArrowDown', 'ArrowLeft'];
		if (!selectedItem || !keys.includes(event.key)) {
			return;
		}
		const marker = event.target.closest('[data-dsgo-hotspot-item-editor]');
		if (marker?.dataset.dsgoHotspotItemEditor !== selectedItem.clientId) {
			return;
		}
		event.preventDefault();
		const amount = event.shiftKey ? 10 : 1;
		const currentX = Number(selectedItem.attributes.x) || 0;
		const currentY = Number(selectedItem.attributes.y) || 0;
		let x = currentX;
		let y = currentY;
		if (event.key === 'ArrowLeft') {
			x -= amount;
		}
		if (event.key === 'ArrowRight') {
			x += amount;
		}
		if (event.key === 'ArrowUp') {
			y -= amount;
		}
		if (event.key === 'ArrowDown') {
			y += amount;
		}
		x = clampCoordinate(x);
		y = clampCoordinate(y);
		onCoordinateChange(selectedItem, { x, y });
		setAnnouncement(
			sprintf(
				/* translators: 1: horizontal percentage, 2: vertical percentage. */
				__(
					'Hotspot position: %1$d%% horizontal, %2$d%% vertical.',
					'designsetgo'
				),
				x,
				y
			)
		);
	};

	return (
		<>
			{/* The image is a pointer/keyboard coordinate canvas, not a clickable control. */}
			{/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */}
			<div
				className="dsgo-hotspot__image-wrap dsgo-hotspot__image-wrap--editor"
				ref={canvasRef}
				role="group"
				tabIndex={0}
				aria-label={__('Hotspot image editor', 'designsetgo')}
				onPointerDown={handlePointerDown}
				onPointerMove={handlePointerMove}
				onPointerUp={stopDragging}
				onPointerCancel={stopDragging}
				onKeyDown={handleKeyDown}
			>
				{imageUrl ? (
					<img
						className="dsgo-hotspot__image"
						src={imageUrl}
						alt={imageAlt}
					/>
				) : (
					<div className="dsgo-hotspot__image dsgo-hotspot__image--empty">
						{__(
							'Choose an image to place hotspots.',
							'designsetgo'
						)}
					</div>
				)}
				<div {...innerBlocksProps} />
			</div>
			<div
				className="screen-reader-text"
				aria-live="polite"
				aria-atomic="true"
			>
				{announcement}
			</div>
		</>
	);
}
