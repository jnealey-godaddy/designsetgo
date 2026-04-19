import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const TEMPLATE = [
	[ 'core/paragraph', { placeholder: __( 'Nothing found. Try another search.', 'designsetgo' ) } ],
];

export default function NoResultsEdit() {
	const blockProps = useBlockProps( { className: 'dsgo-query-no-results' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: TEMPLATE,
		templateLock: false,
	} );
	return <div { ...innerBlocksProps } />;
}
