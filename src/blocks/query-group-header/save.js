import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save( {
		className: 'dsgo-query-group-header',
	} );
	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}
