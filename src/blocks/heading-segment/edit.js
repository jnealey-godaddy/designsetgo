/**
 * Heading Segment Block - Edit Component
 *
 * An inline text span within an Advanced Heading. Typography (font family,
 * weight, style, transform, decoration, letter-spacing, size, line-height) is
 * provided entirely by WordPress Block Supports in the Inspector's Typography
 * panel — see the `typography` supports in block.json. No custom toolbar
 * controls are needed; they duplicated the native panel and wrote to the same
 * `fontFamily` attribute / `style.typography.*` storage.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Heading Segment Edit Component
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Heading segment edit component
 */
export default function HeadingSegmentEdit({ attributes, setAttributes }) {
	const { content } = attributes;

	const blockProps = useBlockProps({
		className: 'dsgo-heading-segment',
	});

	return (
		<span {...blockProps}>
			<RichText
				tagName="span"
				className="dsgo-heading-segment__text"
				value={content}
				onChange={(newContent) =>
					setAttributes({ content: newContent })
				}
				placeholder={__('Heading text…', 'designsetgo')}
				allowedFormats={[
					'core/bold',
					'core/italic',
					'core/strikethrough',
					'core/superscript',
					'core/subscript',
				]}
			/>
		</span>
	);
}
