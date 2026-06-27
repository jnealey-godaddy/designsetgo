import { useBlockProps } from '@wordpress/block-editor';

export default function ScrollMarqueeSave({ attributes }) {
	const {
		rows,
		scrollSpeed,
		imageHeight,
		imageWidth,
		objectFit,
		gap,
		rowGap,
		borderRadius,
	} = attributes;

	const blockProps = useBlockProps.save({
		className: 'dsgo-scroll-marquee',
		'data-scroll-speed': scrollSpeed,
		style: {
			'--dsgo-marquee-gap': gap,
			'--dsgo-marquee-row-gap': rowGap,
			'--dsgo-marquee-image-height': imageHeight,
			'--dsgo-marquee-image-width': imageWidth,
			'--dsgo-marquee-object-fit': objectFit,
			'--dsgo-marquee-border-radius': borderRadius,
		},
	});

	return (
		<div {...blockProps}>
			{rows.map((row, rowIndex) => (
				<div
					key={rowIndex}
					className="dsgo-scroll-marquee__row"
					data-direction={row.direction}
				>
					<div className="dsgo-scroll-marquee__track">
						{/* Render images 6 times for seamless infinite scroll */}
						{[...Array(6)].map((_, repeatIndex) => (
							<div
								key={repeatIndex}
								className="dsgo-scroll-marquee__track-segment"
							>
								{row.images.map((image, imageIndex) => (
									<img
										key={`${repeatIndex}-${imageIndex}`}
										src={image.url}
										alt={image.alt || ''}
										className="dsgo-scroll-marquee__image"
										loading="lazy"
									/>
								))}
							</div>
						))}
					</div>
				</div>
			))}
		</div>
	);
}
