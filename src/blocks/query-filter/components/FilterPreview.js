/**
 * FilterPreview — static canvas preview for each filterKind.
 *
 * @since 2.1.0
 */
import { __ } from '@wordpress/i18n';

export default function FilterPreview({ filterKind, label, placeholder }) {
	const displayLabel = label || null;

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
						<option>{__('Category A', 'designsetgo')}</option>
						<option>{__('Category B', 'designsetgo')}</option>
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
		default:
			return (
				<div className="dsgo-query-filter dsgo-query-filter--checkbox dsgo-query-filter--preview">
					{displayLabel && (
						<span className="dsgo-query-filter__label">
							{displayLabel}
						</span>
					)}
					<div className="dsgo-query-filter__checkbox-list">
						{[
							__('Category A', 'designsetgo'),
							__('Category B', 'designsetgo'),
							__('Category C', 'designsetgo'),
						].map((item) => (
							// eslint-disable-next-line jsx-a11y/label-has-associated-control -- static preview; input is nested inside label (valid HTML), readOnly attr confuses the linter
							<label
								key={item}
								className="dsgo-query-filter__checkbox-item"
							>
								<input type="checkbox" readOnly />
								<span>{item}</span>
							</label>
						))}
					</div>
				</div>
			);
	}
}
