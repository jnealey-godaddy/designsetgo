/**
 * Scroll Marquee Block - Deprecations
 *
 * v2: Save before the objectFit control. The frontend CSS hard-coded
 * `object-fit: cover`, so the saved markup never emitted the
 * `--dsgo-marquee-object-fit` custom property. Current saves always
 * emit it, so older blocks need this deprecation to migrate silently.
 *
 * v1: Original save without items schema on the rows attribute.
 * The rows data was not serialized to the block comment because WP
 * could not properly diff the nested array without an items schema.
 * This deprecation uses source: "query" to extract image data from
 * the saved HTML, validate it, and migrate it to the new format
 * where rows data is stored in the block comment.
 *
 * @package
 */

import { useBlockProps } from '@wordpress/block-editor';

const sharedSupports = {
	anchor: true,
	align: false,
	html: false,
	spacing: {
		margin: false,
		padding: false,
		blockGap: true,
	},
	color: {
		background: true,
		text: true,
		gradients: true,
	},
};

const v2 = {
	attributes: {
		rows: {
			type: 'array',
			items: {
				type: 'object',
				properties: {
					images: {
						type: 'array',
						items: {
							type: 'object',
							properties: {
								id: { type: 'number' },
								url: { type: 'string' },
								alt: { type: 'string' },
							},
						},
					},
					direction: { type: 'string' },
				},
			},
			default: [{ images: [], direction: 'left' }],
		},
		scrollSpeed: { type: 'number', default: 0.5 },
		imageHeight: { type: 'string', default: '200px' },
		imageWidth: { type: 'string', default: '300px' },
		gap: { type: 'string', default: '20px' },
		rowGap: { type: 'string', default: '20px' },
		borderRadius: { type: 'string', default: '8px' },
	},
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Old (pre-objectFit) saves never emitted this custom property.
		const hasObjectFitVar =
			typeof innerHTML === 'string' &&
			innerHTML.includes('--dsgo-marquee-object-fit');
		if (hasObjectFitVar) {
			return false;
		}
		// Only claim blocks whose rows are already serialized in the
		// comment (this format). Ancient v1 blocks parse to empty rows
		// here and must fall through to the v1 query-source deprecation.
		return (
			Array.isArray(attributes.rows) &&
			attributes.rows.some(
				(row) => Array.isArray(row.images) && row.images.length > 0
			)
		);
	},
	migrate(attributes) {
		// objectFit's block.json default ('cover') is filled in
		// automatically; the prior hard-coded CSS matched that value.
		return attributes;
	},
	save({ attributes }) {
		const {
			rows,
			scrollSpeed,
			imageHeight,
			imageWidth,
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
	},
};

const v1 = {
	attributes: {
		rows: {
			type: 'array',
			source: 'query',
			selector: '.dsgo-scroll-marquee__row',
			query: {
				direction: {
					type: 'string',
					source: 'attribute',
					attribute: 'data-direction',
				},
				images: {
					type: 'array',
					source: 'query',
					// Only select images from the FIRST track-segment
					// (the other 5 are duplicates for infinite scroll)
					selector:
						'.dsgo-scroll-marquee__track-segment:first-child .dsgo-scroll-marquee__image',
					query: {
						url: {
							type: 'string',
							source: 'attribute',
							attribute: 'src',
						},
						alt: {
							type: 'string',
							source: 'attribute',
							attribute: 'alt',
						},
					},
				},
			},
			default: [],
		},
		scrollSpeed: {
			type: 'number',
			source: 'attribute',
			selector: '.dsgo-scroll-marquee',
			attribute: 'data-scroll-speed',
			default: 0.5,
		},
		imageHeight: {
			type: 'string',
			default: '200px',
		},
		imageWidth: {
			type: 'string',
			default: '300px',
		},
		gap: {
			type: 'string',
			default: '20px',
		},
		rowGap: {
			type: 'string',
			default: '20px',
		},
		borderRadius: {
			type: 'string',
			default: '8px',
		},
	},
	supports: {
		anchor: true,
		align: false,
		html: false,
		spacing: {
			margin: false,
			padding: false,
			blockGap: true,
		},
		color: {
			background: true,
			text: true,
			gradients: true,
		},
	},
	migrate(attributes) {
		// scrollSpeed comes back as a string from the HTML attribute source,
		// convert to number for the new format.
		const scrollSpeed =
			typeof attributes.scrollSpeed === 'string'
				? parseFloat(attributes.scrollSpeed)
				: attributes.scrollSpeed;

		// Add id: 0 to images (not available from HTML, will be 0 until re-selected)
		const rows = attributes.rows.map((row) => ({
			direction: row.direction || 'left',
			images: (row.images || []).map((img) => ({
				id: 0,
				url: img.url || '',
				alt: img.alt || '',
			})),
		}));

		return {
			...attributes,
			rows,
			scrollSpeed:
				isNaN(scrollSpeed) ||
				scrollSpeed === null ||
				scrollSpeed === undefined
					? 0.5
					: scrollSpeed,
		};
	},
	save({ attributes }) {
		const {
			rows,
			scrollSpeed,
			imageHeight,
			imageWidth,
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
	},
};

export default [v2, v1];
