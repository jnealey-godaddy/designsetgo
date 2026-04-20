import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save({
		className: 'dsgo-query-no-results',
	});
	return (
		<div {...blockProps}>
			<InnerBlocks.Content />
		</div>
	);
}
