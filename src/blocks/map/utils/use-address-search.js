/**
 * useAddressSearch
 *
 * Geocodes an address and writes the result to the block. Shared by the
 * inspector's address field and the first-insert placeholder.
 */

import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import { geocodeAddress } from './geocoding';

/**
 * @param {Function} setAttributes Block setAttributes.
 * @return {Object} `{ search, isSearching, searchError, setSearchError }`.
 */
export default function useAddressSearch(setAttributes) {
	const [isSearching, setIsSearching] = useState(false);
	const [searchError, setSearchError] = useState('');

	const search = useCallback(
		async (address) => {
			if (!address || address.trim() === '') {
				setSearchError(
					__('Please enter an address to search.', 'designsetgo')
				);
				return;
			}

			setIsSearching(true);
			setSearchError('');

			try {
				const result = await geocodeAddress(address);

				if (result) {
					setAttributes({
						dsgoLatitude: result.lat,
						dsgoLongitude: result.lng,
						dsgoAddress: result.display_name,
					});
				} else {
					setSearchError(
						__(
							'Address not found. Please try a different search.',
							'designsetgo'
						)
					);
				}
			} catch (error) {
				setSearchError(
					__(
						'Failed to search address. Please try again.',
						'designsetgo'
					)
				);
			} finally {
				setIsSearching(false);
			}
		},
		[setAttributes]
	);

	return { search, isSearching, searchError, setSearchError };
}
