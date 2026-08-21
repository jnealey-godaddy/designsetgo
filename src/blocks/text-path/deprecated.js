import { useBlockProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import { getTextPathData } from '../../utils/svg-paths';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';
import { getSafeTextPathUrl, getTextPathId } from './utils';

function LegacyTextPathGraphic({ attributes }) {
	const path = getTextPathData(attributes);
	const pathId = getTextPathId(attributes.uniqueId);
	const startOffset = `${Math.max(
		-100,
		Math.min(100, Number(attributes.startOffset) || 0)
	)}%`;
	const fontSize = Math.max(
		1,
		Math.min(400, Number(attributes.pathFontSize) || 54)
	);
	const wordSpacing = Math.max(
		-40,
		Math.min(100, Number(attributes.wordSpacing) || 0)
	);

	return (
		<svg
			viewBox={path.viewBox}
			role="img"
			aria-label={attributes.text || undefined}
		>
			<defs>
				<path id={pathId} d={path.d} />
			</defs>
			{attributes.showPath && (
				<path className="dsgo-text-path__guide" d={path.d} />
			)}
			<text
				direction={attributes.direction === 'rtl' ? 'rtl' : 'ltr'}
				style={{
					fontSize: `${fontSize}px`,
					wordSpacing: `${wordSpacing}px`,
				}}
			>
				<textPath href={`#${pathId}`} startOffset={startOffset}>
					{attributes.text}
				</textPath>
			</text>
		</svg>
	);
}

const v1 = {
	apiVersion: 3,
	attributes: {
		text: { type: 'string', default: 'Text on a path' },
		pathType: { type: 'string', default: 'wave' },
		customPath: { type: 'object', default: null },
		startOffset: { type: 'number', default: 0 },
		showPath: { type: 'boolean', default: false },
		pathFontSize: { type: 'number', default: 54 },
		rotation: { type: 'number', default: 0 },
		wordSpacing: { type: 'number', default: 0 },
		direction: { type: 'string', default: 'ltr' },
		url: { type: 'string', default: '' },
		target: { type: 'boolean', default: false },
		rel: { type: 'string', default: '' },
		uniqueId: { type: 'string', default: '' },
	},
	supports: {
		anchor: true,
		html: false,
		color: { text: true },
		spacing: { margin: true, padding: true },
		typography: {
			fontSize: true,
			lineHeight: true,
			__experimentalFontFamily: true,
			__experimentalFontWeight: true,
			__experimentalLetterSpacing: true,
		},
	},
	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);

		return Boolean(
			innerHTML && !innerHTML.includes('data-dsgo-text-path-offset')
		);
	},
	save({ attributes }) {
		const safeUrl = getSafeTextPathUrl(attributes.url);
		const rotation = Math.max(
			-360,
			Math.min(360, Number(attributes.rotation) || 0)
		);
		const blockProps = useBlockProps.save({
			className: 'dsgo-text-path',
			style: { '--dsgo-text-path-rotation': `${rotation}deg` },
		});
		const svg = <LegacyTextPathGraphic attributes={attributes} />;

		return (
			<div {...blockProps}>
				{safeUrl
					? createElement(
							'a',
							{
								href: safeUrl,
								target: attributes.target
									? '_blank'
									: undefined,
								rel: 'noopener noreferrer',
							},
							svg
						)
					: svg}
			</div>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

export default [v1];
export { v1 };
