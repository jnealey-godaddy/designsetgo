/**
 * DynamicTagButton — inline "⚡ Connect" trigger that opens the picker.
 *
 * Mirrors Elementor's per-attribute dynamic-tag toggle. Callers pass the
 * current binding value and receive the new value via onChange; this
 * component owns the modal open/close state.
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';

import DynamicTagPicker from './DynamicTagPicker';

const databaseIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
		<path fill="currentColor" d="M12 2C8.13 2 5 3.57 5 5.5V7c0 1.93 3.13 3.5 7 3.5s7-1.57 7-3.5V5.5C19 3.57 15.87 2 12 2zm0 10c-3.87 0-7-1.57-7-3.5V11c0 1.93 3.13 3.5 7 3.5s7-1.57 7-3.5V8.5C19 10.43 15.87 12 12 12zm0 4c-3.87 0-7-1.57-7-3.5V15c0 1.93 3.13 3.5 7 3.5s7-1.57 7-3.5v-2.5c0 1.93-3.13 3.5-7 3.5zm0 4c-3.87 0-7-1.57-7-3.5V19c0 1.93 3.13 3.5 7 3.5s7-1.57 7-3.5v-2.5c0 1.93-3.13 3.5-7 3.5z" />
	</svg>
);

export default function DynamicTagButton( {
	value,
	onChange,
	returns,
	postType,
	postId,
	label = __( 'Connect to Dynamic Tag', 'designsetgo' ),
	connectedLabel,
	size = 'small',
	variant,
} ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const isConnected = Boolean( value?.source );

	const resolvedLabel = isConnected
		? ( connectedLabel
			|| sprintf(
				/* translators: %s: source slug, e.g. designsetgo/post-title */
				__( 'Bound to %s', 'designsetgo' ),
				value.source
			) )
		: label;

	return (
		<>
			<Button
				size={ size }
				variant={ variant || ( isConnected ? 'primary' : 'tertiary' ) }
				onClick={ () => setIsOpen( true ) }
				icon={ <Icon icon={ databaseIcon } /> }
				className={ `dsgo-dynamic-tag-button${ isConnected ? ' is-connected' : '' }` }
				aria-label={ resolvedLabel }
				showTooltip
			>
				{ isConnected ? __( 'Dynamic', 'designsetgo' ) : __( 'Connect', 'designsetgo' ) }
			</Button>
			<DynamicTagPicker
				isOpen={ isOpen }
				onClose={ () => setIsOpen( false ) }
				value={ value }
				onChange={ onChange }
				returns={ returns }
				postType={ postType }
				postId={ postId }
			/>
		</>
	);
}
