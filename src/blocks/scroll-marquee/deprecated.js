/**
 * Scroll Marquee Block - Deprecations
 *
 * v3: Save before border-radius moved to native border support. The
 * `borderRadius` attribute (default '8px') was always serialized into a
 * `--dsgo-marquee-border-radius` custom property on the wrapper, so the
 * saved markup could never omit it. Current saves rely on the native
 * `style.border.radius` attribute (via `__experimentalBorder`) applied
 * directly to each image, with no forced default. This deprecation
 * migrates the old attribute value into `style.border.radius`.
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

const v3 = {
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
		objectFit: {
			type: 'string',
			default: 'cover',
			enum: ['cover', 'contain', 'fill', 'scale-down'],
		},
		gap: { type: 'string', default: '20px' },
		rowGap: { type: 'string', default: '20px' },
		borderRadius: { type: 'string', default: '8px' },
	},
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Only the pre-native-border save ever emitted this custom property.
		const hasBorderRadiusVar =
			typeof innerHTML === 'string' &&
			innerHTML.includes('--dsgo-marquee-border-radius');
		if (!hasBorderRadiusVar) {
			return false;
		}
		// Only claim blocks whose rows are already serialized in the
		// comment (this format). Ancient v1 blocks parse to empty rows
		// here and must fall through to the v1 query-source deprecation
		// (see the identical guard on v2 below for the full rationale).
		return (
			Array.isArray(attributes.rows) &&
			attributes.rows.some(
				(row) => Array.isArray(row.images) && row.images.length > 0
			)
		);
	},
	migrate(attributes) {
		const { borderRadius, ...rest } = attributes;
		if (!borderRadius) {
			return rest;
		}
		return {
			...rest,
			style: {
				...rest.style,
				border: {
					...rest.style?.border,
					radius: borderRadius,
				},
			},
		};
	},
	save({ attributes }) {
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
		//
		// Known benign gap: a v2 block whose rows were all emptied of
		// images is indistinguishable from a v1 block at the attribute
		// level (both parse to image-less rows), so it also falls
		// through to v1. v1.migrate is fully defensive and yields valid
		// data, so the output is identical either way. We intentionally
		// gate on row data rather than `rows` presence — `rows` has a
		// default and is never undefined, so a presence check would
		// wrongly claim ancient v1 blocks and discard their images.
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
	supports: sharedSupports,
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

export default [v3, v2, v1];
