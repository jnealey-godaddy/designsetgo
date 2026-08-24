export const HIGHLIGHT_PATHS = {
	circle: 'M5 54C10 13 87 2 96 43C105 84 8 96 4 52',
	curly: 'M4 55C20 17 34 90 51 50C68 10 82 87 96 45',
	underline: 'M4 78C27 67 68 69 96 76',
	double: 'M4 72C30 59 66 61 96 70M4 84C31 73 70 74 96 81',
	'double-underline': 'M4 75C29 67 67 67 96 75M4 87C30 79 69 79 96 87',
	'underline-zigzag': 'M4 80L16 68L28 80L40 68L52 80L64 68L76 80L88 68L96 76',
	zigzag: 'M4 78L20 65L36 81L52 66L68 82L84 66L96 76',
	diagonal: 'M4 84L96 16',
	strikethrough: 'M4 50L96 50',
	x: 'M14 15L86 85M86 15L14 85',
};

/**
 * Render a static, non-focusable decorative highlight behind a heading segment.
 *
 * @param {Object} props       Component props.
 * @param {string} props.shape Allowlisted highlight shape.
 * @return {JSX.Element} Decorative SVG highlight.
 */
export default function HighlightShape({ shape }) {
	return (
		<svg
			className="dsgo-heading-segment__highlight"
			aria-hidden="true"
			focusable="false"
			viewBox="0 0 100 100"
			preserveAspectRatio="none"
		>
			<path d={HIGHLIGHT_PATHS[shape]} />
		</svg>
	);
}
