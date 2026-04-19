import { __, sprintf, _n } from '@wordpress/i18n';

export default function ResultCountBadge({ totalItems, loading, error }) {
	if (error) {
		return (
			<span className="dsgo-query__count is-error" aria-live="polite">
				{__('Preview failed', 'designsetgo')}
			</span>
		);
	}
	if (loading) {
		return (
			<span className="dsgo-query__count is-loading" aria-live="polite">
				{__('Loading\u2026', 'designsetgo')}
			</span>
		);
	}
	if (totalItems === null || totalItems === undefined) {
		return null;
	}
	return (
		<span className="dsgo-query__count" aria-live="polite">
			{sprintf(
				/* translators: %d: number of matched items. */
				_n('%d match', '%d matches', totalItems, 'designsetgo'),
				totalItems
			)}
		</span>
	);
}
