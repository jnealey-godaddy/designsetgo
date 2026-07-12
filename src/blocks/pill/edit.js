/**
 * Edit component for Pill Block
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import DsgoJustificationToolbar from '../../components/shared/DsgoJustificationToolbar';
import { getJustificationClass } from '../../utils/justification';

export default function PillEdit({ attributes, setAttributes }) {
	const { content, justification } = attributes;

	const blockProps = useBlockProps({
		className: `dsgo-pill dsgo-justify ${getJustificationClass(
			justification
		)}`.trim(),
	});

	// The visible pill is the inner span, so move colour / background / border
	// inline styles off the wrapper and onto it — mirroring render.php so the
	// editor matches the frontend. Prefix-match the camelCase style keys (rather
	// than an exact-name list) so unlinked per-corner radius (borderTopLeftRadius)
	// and per-side borders (borderTopColor, …) transfer too.
	const wrapperStyle = { ...(blockProps.style || {}) };
	const innerStyle = {};

	Object.keys(wrapperStyle).forEach((key) => {
		if (/^(color|background|border)/.test(key)) {
			innerStyle[key] = wrapperStyle[key];
			delete wrapperStyle[key];
		}
	});

	// Update blockProps with cleaned style
	blockProps.style = wrapperStyle;

	return (
		<>
			<DsgoJustificationToolbar
				value={justification}
				onChange={(value) => setAttributes({ justification: value })}
			/>
			<div {...blockProps}>
				<RichText
					tagName="span"
					className="dsgo-pill__content"
					value={content}
					onChange={(newContent) =>
						setAttributes({ content: newContent })
					}
					placeholder={__('Add pill text…', 'designsetgo')}
					allowedFormats={['core/bold', 'core/italic']}
					style={innerStyle}
				/>
			</div>
		</>
	);
}
