/**
 * Edit component for Pill Block
 */
import classnames from 'classnames';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseColorProps as useColorProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBorderProps as useBorderProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetSpacingClassesAndStyles as getSpacingClassesAndStyles,
} from '@wordpress/block-editor';
import DsgoJustificationToolbar from '../../components/shared/DsgoJustificationToolbar';
import { getJustificationClass } from '../../utils/justification';

export default function PillEdit({ attributes, setAttributes }) {
	const { content, justification, style } = attributes;

	const blockProps = useBlockProps({
		className: `dsgo-pill dsgo-justify ${getJustificationClass(
			justification
		)}`.trim(),
	});

	// block.json skip-serializes color, border, and spacing.padding off the
	// wrapper, so useBlockProps() above no longer carries them — there is
	// nothing to neutralise. The visible pill is the inner span, so re-derive
	// the same classes/styles with the official block-support helpers
	// (identical to how core/button applies them to its inner link) and apply
	// them there instead, mirroring render.php's designsetgo_route_visual_supports()
	// so the editor canvas matches the frontend. Using the real helpers — rather
	// than sniffing inline styles — is what makes preset picks (including
	// gradients, which are class-driven with no inline style at all) work.
	const colorProps = useColorProps(attributes);
	const borderProps = useBorderProps(attributes);
	const paddingProps = getSpacingClassesAndStyles({
		style: { spacing: { padding: style?.spacing?.padding } },
	});

	const innerClassName = classnames(
		'dsgo-pill__content',
		colorProps.className,
		borderProps.className
	);

	const innerStyle = {
		...borderProps.style,
		...colorProps.style,
		...paddingProps.style,
	};

	return (
		<>
			<DsgoJustificationToolbar
				value={justification}
				onChange={(value) => setAttributes({ justification: value })}
			/>
			<div {...blockProps}>
				<RichText
					tagName="span"
					className={innerClassName}
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
