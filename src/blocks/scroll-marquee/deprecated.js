/**
 * Scroll Marquee Block - Deprecations
 *
 * v4: Save before `imageWidth` defaulted to `auto`. The default width was
 * `300px`, which (as a default) is never serialized into the block comment,
 * so default-width blocks baked `--dsgo-marquee-image-width:300px` inline
 * with an empty comment. The current save emits `auto` for that default, so
 * those blocks fail validation against it. This deprecation reproduces the
 * pre-`auto` save (imageWidth default `300px`) and re-parses the empty
 * comment to `imageWidth: '300px'`; the passthrough migrate keeps it as an
 * explicit value so existing designs stay byte-identical (only brand-new
 * blocks pick up the `auto` default). Everything else matches the current
 * save (native border radius applied per image), so only the imageWidth
 * default drives the migration.
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
 * v1ObjectFit: HTML-sourced rows (empty comment, `source: "query"` era)
 * that already carry the `--dsgo-marquee-object-fit` custom property but no
 * `--dsgo-marquee-border-radius`. This shape predates rows being serialized
 * into the comment, so `rows` must be recovered from the markup — but it is
 * newer than v1/v2 (it emits object-fit) and newer than v3 (it omits the
 * border-radius var), so none of those reproduce it. Without this entry the
 * block fails validation ("Attempt Recovery"). Save mirrors v1 but emits
 * object-fit instead of border-radius.
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

// Mirrors the current block.json supports (adds native border radius, applied
// per image with skipSerialization). Used by v4, which reproduces the current
// save verbatim except for the pre-`auto` imageWidth default.
const currentSupports = {
	...sharedSupports,
	__experimentalBorder: {
		radius: true,
		__experimentalSkipSerialization: true,
		__experimentalDefaultControls: {
			radius: true,
		},
	},
};

const v4 = {
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
	},
	supports: currentSupports,
	// No `isEligible`: only genuinely pre-`auto` content needs migrating, and it
	// is invalid against the current save() (which emits `auto` for the default
	// width, not `300px`), so it reaches this deprecation through save()-matching
	// regardless. An `isEligible` would only add the ability to force-migrate
	// ALREADY-valid blocks, and a current block where the author explicitly picks
	// `imageWidth: "300px"` (reachable once "Auto width" is toggled off) is
	// byte-identical to the old default markup — so such a check can't tell them
	// apart and would needlessly route valid content through migrate() on every
	// parse. save()-matching naturally skips valid content and also keeps the
	// older border-radius-bearing shapes routing to their own deprecations.
	migrate(attributes) {
		// Passthrough. An old default-width block re-parses against this
		// schema (imageWidth default '300px') to imageWidth === '300px', which
		// is kept as an explicit value so the current save re-emits 300px and
		// the visual is preserved. Only new blocks inherit the 'auto' default.
		return attributes;
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
		} = attributes;
		const borderRadius = attributes.style?.border?.radius;

		const blockProps = useBlockProps.save({
			className: 'dsgo-scroll-marquee',
			'data-scroll-speed': scrollSpeed,
			style: {
				'--dsgo-marquee-gap': gap,
				'--dsgo-marquee-row-gap': rowGap,
				'--dsgo-marquee-image-height': imageHeight,
				'--dsgo-marquee-image-width': imageWidth,
				'--dsgo-marquee-object-fit': objectFit,
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
											style={
												borderRadius
													? { borderRadius }
													: undefined
											}
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
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
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
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
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

// Shared HTML-source schema for the rows attribute (empty-comment era).
const htmlSourcedRows = {
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
};

// Shared normalization for HTML-sourced deprecations: images come back
// without ids and scrollSpeed as a string from the HTML attribute source.
function migrateHtmlSourced(attributes) {
	const scrollSpeed =
		typeof attributes.scrollSpeed === 'string'
			? parseFloat(attributes.scrollSpeed)
			: attributes.scrollSpeed;

	const rows = (attributes.rows || []).map((row) => ({
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
}

const v1ObjectFit = {
	attributes: {
		rows: htmlSourcedRows,
		scrollSpeed: {
			type: 'number',
			source: 'attribute',
			selector: '.dsgo-scroll-marquee',
			attribute: 'data-scroll-speed',
			default: 0.5,
		},
		// Only rows (query) and scrollSpeed (data-attribute) are recovered
		// from the markup. gap/imageHeight/imageWidth/objectFit/rowGap are
		// deliberately NOT sourced from the inline style: an empty block
		// comment means every comment-backed attribute was at its default
		// when saved (WordPress serializes any non-default value into the
		// comment), so the inline-style vars are the defaults too — resolving
		// to the schema default here is exact, not lossy, for any WP-authored
		// block. (Only hand-crafted markup that baked a non-default var into
		// the style while leaving the comment empty would differ, which no WP
		// save path produces.)
		imageHeight: { type: 'string', default: '200px' },
		imageWidth: { type: 'string', default: '300px' },
		objectFit: {
			type: 'string',
			default: 'cover',
			enum: ['cover', 'contain', 'fill', 'scale-down'],
		},
		gap: { type: 'string', default: '20px' },
		rowGap: { type: 'string', default: '20px' },
	},
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		if (typeof innerHTML !== 'string') {
			return false;
		}
		// This shape emits object-fit but not the border-radius var; the
		// border-radius-bearing shapes are handled by v3/v2 (comment-sourced).
		const hasObjectFit = innerHTML.includes('--dsgo-marquee-object-fit');
		const hasBorderRadius = innerHTML.includes(
			'--dsgo-marquee-border-radius'
		);
		if (!hasObjectFit || hasBorderRadius) {
			return false;
		}
		// Only claim blocks whose images live in the markup (empty comment).
		// A current-format block with rows in the comment validates against
		// the current save() and never reaches this deprecation.
		return (
			Array.isArray(attributes.rows) &&
			attributes.rows.some(
				(row) => Array.isArray(row.images) && row.images.length > 0
			)
		);
	},
	migrate: migrateHtmlSourced,
	save({ attributes }) {
		const {
			rows,
			scrollSpeed,
			imageHeight,
			imageWidth,
			objectFit,
			gap,
			rowGap,
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
		rows: htmlSourcedRows,
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
	// Shared with v1ObjectFit: normalizes HTML-sourced rows (adds id: 0) and
	// coerces the string data-scroll-speed to a number.
	migrate: migrateHtmlSourced,
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

export default [v4, v3, v2, v1ObjectFit, v1];
