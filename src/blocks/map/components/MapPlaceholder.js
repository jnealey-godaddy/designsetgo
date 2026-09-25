/**
 * Map Placeholder
 *
 * Shown while a map has no location: coordinates 0,0 and no address, which
 * is how the inserter creates the block (see index.js). Searching an address
 * sets the coordinates and hands over to the normal preview; typing
 * coordinates in the inspector does the same.
 */

import { __ } from '@wordpress/i18n';
import {
	Placeholder,
	TextControl,
	Button,
	Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import useAddressSearch from '../utils/use-address-search';

/**
 * Whether a map has no location to show yet.
 *
 * View.js geocodes `dsgoAddress` when the coordinates are 0,0, so an address
 * on its own is a location. Mirrors the empty check in render.php.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True when the map has nothing to point at.
 */
export function hasNoLocation(attributes) {
	const { dsgoLatitude, dsgoLongitude, dsgoAddress } = attributes;
	return (
		dsgoLatitude === 0 &&
		dsgoLongitude === 0 &&
		(!dsgoAddress || dsgoAddress.trim() === '')
	);
}

/**
 * @param {Object}   props               Component props.
 * @param {Function} props.setAttributes Block setAttributes.
 * @return {JSX.Element} Placeholder UI.
 */
export default function MapPlaceholder({ setAttributes }) {
	// Local until the search succeeds: writing dsgoAddress on every keystroke
	// would make hasNoLocation() false and swap the placeholder out mid-typing.
	const [address, setAddress] = useState('');
	const { search, isSearching, searchError, setSearchError } =
		useAddressSearch(setAttributes);

	const onSubmit = (event) => {
		event.preventDefault();
		search(address);
	};

	return (
		<Placeholder
			icon="location-alt"
			label={__('Map', 'designsetgo')}
			instructions={__(
				'Search for an address, or enter latitude and longitude in the block settings.',
				'designsetgo'
			)}
			className="dsgo-map__placeholder"
		>
			<form className="dsgo-map__placeholder-form" onSubmit={onSubmit}>
				<TextControl
					label={__('Address', 'designsetgo')}
					hideLabelFromVision
					value={address}
					onChange={(value) => {
						setAddress(value);
						setSearchError('');
					}}
					placeholder={__(
						'Enter an address or location',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					type="submit"
					isBusy={isSearching}
					disabled={!address.trim() || isSearching}
					__next40pxDefaultSize
				>
					{isSearching
						? __('Searching…', 'designsetgo')
						: __('Search', 'designsetgo')}
				</Button>
			</form>
			{searchError && (
				<Notice status="error" isDismissible={false}>
					{searchError}
				</Notice>
			)}
		</Placeholder>
	);
}
