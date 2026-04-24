/**
 * FilterPreview — static canvas preview for each filterKind.
 *
 * @since 2.1.0
 */
import { __ } from '@wordpress/i18n';

// Placeholder term list shown when terms haven't resolved yet or no taxonomy
// terms exist on the site. Keeps the preview visually stable (prevents the
// block from collapsing to zero height) while the REST request is in flight.
const PLACEHOLDER_TERMS = [
	{ id: 0, slug: 'a', name: __('Category A', 'designsetgo') },
	{ id: 1, slug: 'b', name: __('Category B', 'designsetgo') },
	{ id: 2, slug: 'c', name: __('Category C', 'designsetgo') },
];

export default function FilterPreview({
	filterKind,
	label,
	placeholder,
	orientation = 'vertical',
	filterStyle = 'default',
	terms = null,
	termsLoading = false,
}) {
	const displayLabel = label || null;
	// Use real terms when available, otherwise fall back to placeholders so
	// the block always renders something recognisable in the editor.
	const effectiveTerms =
		Array.isArray(terms) && terms.length > 0 ? terms : PLACEHOLDER_TERMS;

	switch (filterKind) {
		case 'search':
			return (
				<div className="dsgo-query-filter dsgo-query-filter--search dsgo-query-filter--preview">
					{displayLabel && (
						<span className="dsgo-query-filter__label">
							{displayLabel}
						</span>
					)}
					<div className="dsgo-query-filter__search-row">
						<input
							type="search"
							readOnly
							placeholder={
								placeholder || __('Search\u2026', 'designsetgo')
							}
							className="dsgo-query-filter__search-input"
						/>
						<button
							type="button"
							className="dsgo-query-filter__submit"
							disabled
						>
							{__('Search', 'designsetgo')}
						</button>
					</div>
				</div>
			);

		case 'sort':
			return (
				<div className="dsgo-query-filter dsgo-query-filter--sort dsgo-query-filter--preview">
					{displayLabel && (
						<span className="dsgo-query-filter__label">
							{displayLabel}
						</span>
					)}
					<select className="dsgo-query-filter__sort" disabled>
						<option>{__('Default order', 'designsetgo')}</option>
						<option>{__('Newest', 'designsetgo')}</option>
						<option>{__('Oldest', 'designsetgo')}</option>
						<option>{__('A\u2013Z', 'designsetgo')}</option>
					</select>
				</div>
			);

		case 'select':
			return (
				<div className="dsgo-query-filter dsgo-query-filter--select dsgo-query-filter--preview">
					{displayLabel && (
						<span className="dsgo-query-filter__label">
							{displayLabel}
						</span>
					)}
					<select className="dsgo-query-filter__select" disabled>
						<option>{__('All', 'designsetgo')}</option>
						{effectiveTerms.map((term) => (
							<option key={term.id}>{term.name}</option>
						))}
					</select>
				</div>
			);

		case 'active':
			return (
				<div className="dsgo-query-filter dsgo-query-filter--active dsgo-query-filter--preview">
					{displayLabel && (
						<span className="dsgo-query-filter__label">
							{displayLabel}
						</span>
					)}
					<span className="dsgo-query-filter__chip">
						{__('tech', 'designsetgo')}
						<span aria-hidden="true">&nbsp;&times;</span>
					</span>
					<span className="dsgo-query-filter__chip">
						{__('news', 'designsetgo')}
						<span aria-hidden="true">&nbsp;&times;</span>
					</span>
				</div>
			);

		case 'reset':
			return (
				<div className="dsgo-query-filter dsgo-query-filter--reset dsgo-query-filter--preview">
					<button
						type="button"
						className="dsgo-query-filter__reset"
						disabled
					>
						{label || __('Reset filters', 'designsetgo')}
					</button>
				</div>
			);

		case 'checkbox':
		default: {
			const isPill = filterStyle === 'pill';
			const isUnderline = filterStyle === 'underline';
			const isHorizontal =
				orientation === 'horizontal' || isPill || isUnderline;
			const listClass = [
				'dsgo-query-filter__checkbox-list',
				isHorizontal ? 'is-horizontal' : '',
				isPill ? 'is-style-pill' : '',
				isUnderline ? 'is-style-underline' : '',
			]
				.filter(Boolean)
				.join(' ');
			return (
				<div className="dsgo-query-filter dsgo-query-filter--checkbox dsgo-query-filter--preview">
					{displayLabel && (
						<span className="dsgo-query-filter__label">
							{displayLabel}
						</span>
					)}
					<div
						className={listClass}
						aria-busy={termsLoading || undefined}
					>
						{effectiveTerms.map((term) => (
							// eslint-disable-next-line jsx-a11y/label-has-associated-control -- static preview; input is nested inside label (valid HTML), readOnly attr confuses the linter
							<label
								key={term.id}
								className="dsgo-query-filter__checkbox-item"
							>
								<input type="checkbox" readOnly />
								<span>{term.name}</span>
							</label>
						))}
					</div>
				</div>
			);
		}
	}
}
