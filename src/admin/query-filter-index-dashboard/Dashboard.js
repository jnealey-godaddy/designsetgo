/**
 * Dynamic Query — Filter Index Admin Dashboard component.
 *
 * Three sub-components:
 *   IndexStatusCard       — last rebuild time, row count, rebuild button.
 *   RegisteredFiltersTable — list of registered filters with Remove per row.
 *   AddFilterForm          — form to add a new filter.
 *
 * @package
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	Notice,
	TextControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

const { apiUrl, nonce } = window.dsgoQueryFilterIndexDashboard || {};

/**
 * Shared fetch helper — sets nonce header and parses JSON.
 *
 * Appends query args via addQueryArgs so it works on sites using either
 * pretty permalinks (path style) or default permalinks (?rest_route=…).
 *
 * @param {string} path       URL path relative to apiUrl (no query string).
 * @param {Object} opts       fetch() options overrides.
 * @param {Object} opts.query Optional query parameters to append to path.
 * @return {Promise<any>}     Parsed JSON body.
 */
async function apiFetch(path, opts = {}) {
	const { query, ...fetchOpts } = opts;
	const fullPath = query ? addQueryArgs(path, query) : path;
	const separator = apiUrl.indexOf('?') === -1 ? '' : '&';
	// When apiUrl is the ugly form (?rest_route=/designsetgo/v1), the path's
	// leading "?" would collide with the existing query string — swap to "&".
	const relative =
		separator && fullPath.charAt(0) === '?'
			? separator + fullPath.slice(1)
			: fullPath;

	const response = await fetch(`${apiUrl}${relative}`, {
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
			...(fetchOpts.headers || {}),
		},
		...fetchOpts,
	});

	if (!response.ok) {
		const err = await response.json().catch(() => ({}));
		throw new Error(err.message || `HTTP ${response.status}`);
	}
	return response.json();
}

// ─────────────────────────────────────────────────────────────────────────────
// IndexStatusCard
// ─────────────────────────────────────────────────────────────────────────────

function IndexStatusCard() {
	const [status, setStatus] = useState(null);
	const [error, setError] = useState(null);
	const [rebuilding, setRebuilding] = useState(false);

	const fetchStatus = useCallback(async () => {
		try {
			const data = await apiFetch('/query/filter-status');
			setStatus(data);
		} catch (e) {
			setError(e.message);
		}
	}, []);

	// Initial load.
	useEffect(() => {
		fetchStatus();
	}, [fetchStatus]);

	// Poll every 2 s while in_progress.
	useEffect(() => {
		if (!status?.in_progress) {
			return;
		}
		const id = setInterval(fetchStatus, 2000);
		return () => clearInterval(id);
	}, [status?.in_progress, fetchStatus]);

	const handleRebuild = async () => {
		setRebuilding(true);
		setError(null);
		try {
			// Kick off the rebuild — this call is synchronous server-side.
			// Poll will show intermediate progress if the page polls before
			// the response returns.
			const result = await apiFetch('/query/filter-rebuild', {
				method: 'POST',
			});
			setStatus((prev) => ({
				...(prev || {}),
				...result,
				in_progress: false,
			}));
			// Refresh status to get the final row count.
			await fetchStatus();
		} catch (e) {
			setError(e.message);
		} finally {
			setRebuilding(false);
		}
	};

	const lastRebuilt = status?.last_rebuilt_at
		? new Date(status.last_rebuilt_at * 1000).toLocaleString()
		: __('Never', 'designsetgo');

	return (
		<div className="dsgo-filter-index-card">
			<h2>{__('Index Status', 'designsetgo')}</h2>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{!status && !error && <Spinner />}

			{status && (
				<table className="dsgo-filter-index-status-table">
					<tbody>
						<tr>
							<th>{__('Total rows', 'designsetgo')}</th>
							<td>{status.total_rows}</td>
						</tr>
						<tr>
							<th>{__('Last rebuilt', 'designsetgo')}</th>
							<td>{lastRebuilt}</td>
						</tr>
						<tr>
							<th>{__('Posts processed', 'designsetgo')}</th>
							<td>{status.processed}</td>
						</tr>
					</tbody>
				</table>
			)}

			<div className="dsgo-filter-index-card__actions">
				<div role="status" aria-live="polite" aria-atomic="true">
					{status?.in_progress && (
						<>
							<Spinner />
							<span>
								{__('Rebuild in progress…', 'designsetgo')}
							</span>
						</>
					)}
				</div>
				{!status?.in_progress && (
					<Button
						variant="primary"
						onClick={handleRebuild}
						isBusy={rebuilding}
						disabled={rebuilding}
					>
						{__('Rebuild Index', 'designsetgo')}
					</Button>
				)}
			</div>
		</div>
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// RegisteredFiltersTable
// ─────────────────────────────────────────────────────────────────────────────

function RegisteredFiltersTable({ onChanged }) {
	const [filters, setFilters] = useState(null);
	const [error, setError] = useState(null);
	const [removing, setRemoving] = useState(null);

	const loadFilters = useCallback(async () => {
		try {
			const data = await apiFetch('/query/filters');
			setFilters(data);
		} catch (e) {
			setError(e.message);
		}
	}, []);

	useEffect(() => {
		loadFilters();
	}, [loadFilters]);

	// Refresh when a new filter is added.
	useEffect(() => {
		if (onChanged) {
			onChanged(loadFilters);
		}
	}, [onChanged, loadFilters]);

	const handleRemove = async (key) => {
		setRemoving(key);
		setError(null);
		try {
			await apiFetch('/query/filters', {
				method: 'DELETE',
				query: { filter_key: key },
			});
			setFilters((prev) => {
				const next = { ...prev };
				delete next[key];
				return next;
			});
		} catch (e) {
			setError(e.message);
		} finally {
			setRemoving(null);
		}
	};

	const entries = filters ? Object.entries(filters) : [];

	return (
		<div className="dsgo-filter-index-card">
			<h2>{__('Registered Filters', 'designsetgo')}</h2>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{!filters && !error && <Spinner />}

			{filters && entries.length === 0 && (
				<p className="dsgo-filter-index-empty">
					{__('No filters registered yet.', 'designsetgo')}
				</p>
			)}

			{filters && entries.length > 0 && (
				<table className="dsgo-filter-index-list-table widefat">
					<thead>
						<tr>
							<th>{__('Key', 'designsetgo')}</th>
							<th>{__('Type', 'designsetgo')}</th>
							<th>{__('Source', 'designsetgo')}</th>
							<th>{__('Label', 'designsetgo')}</th>
							<th>
								<span className="screen-reader-text">
									{__('Actions', 'designsetgo')}
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{entries.map(([key, config]) => (
							<tr key={key}>
								<td>
									<code>{key}</code>
								</td>
								<td>{config.type}</td>
								<td>{config.source}</td>
								<td>{config.label || key}</td>
								<td>
									<Button
										variant="secondary"
										isDestructive
										onClick={() => handleRemove(key)}
										isBusy={removing === key}
										disabled={removing !== null}
									>
										{__('Remove', 'designsetgo')}
									</Button>
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}
		</div>
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// AddFilterForm
// ─────────────────────────────────────────────────────────────────────────────

const TYPE_OPTIONS = [
	{ label: __('Taxonomy', 'designsetgo'), value: 'taxonomy' },
	{ label: __('Meta', 'designsetgo'), value: 'meta' },
];

function AddFilterForm({ onAdded }) {
	const [filterKey, setFilterKey] = useState('');
	const [type, setType] = useState('taxonomy');
	const [source, setSource] = useState('');
	const [label, setLabel] = useState('');
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(false);

	const handleSubmit = async (e) => {
		e.preventDefault();
		setSaving(true);
		setError(null);
		setSuccess(false);

		try {
			await apiFetch('/query/filter-register', {
				method: 'POST',
				body: JSON.stringify({
					filter_key: filterKey,
					config: { type, source, label },
				}),
			});

			setFilterKey('');
			setSource('');
			setLabel('');
			setType('taxonomy');
			setSuccess(true);

			if (onAdded) {
				onAdded();
			}
		} catch (err) {
			setError(err.message);
		} finally {
			setSaving(false);
		}
	};

	return (
		<div className="dsgo-filter-index-card">
			<h2>{__('Add Filter', 'designsetgo')}</h2>

			{error && (
				<Notice status="error" onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			{success && (
				<Notice status="success" onRemove={() => setSuccess(false)}>
					{__('Filter registered successfully.', 'designsetgo')}
				</Notice>
			)}

			<form
				onSubmit={handleSubmit}
				className="dsgo-filter-index-add-form"
			>
				<TextControl
					label={__('Filter Key', 'designsetgo')}
					value={filterKey}
					onChange={setFilterKey}
					placeholder="e.g. price"
					required
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={__('Type', 'designsetgo')}
					value={type}
					options={TYPE_OPTIONS}
					onChange={setType}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Source', 'designsetgo')}
					value={source}
					onChange={setSource}
					placeholder="e.g. _price or category"
					required
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Label (optional)', 'designsetgo')}
					value={label}
					onChange={setLabel}
					placeholder={__('Human-readable label', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<Button
					type="submit"
					variant="primary"
					isBusy={saving}
					disabled={saving || !filterKey || !source}
				>
					{__('Add Filter', 'designsetgo')}
				</Button>
			</form>
		</div>
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard (root)
// ─────────────────────────────────────────────────────────────────────────────

export default function Dashboard() {
	// refreshFilters is a ref to RegisteredFiltersTable's loadFilters, so
	// AddFilterForm can trigger a reload after a successful register.
	const [refreshFilters, setRefreshFilters] = useState(null);

	const handleTableReady = (loadFn) => {
		setRefreshFilters(() => loadFn);
	};

	return (
		<div className="dsgo-query-filter-index-dashboard">
			<h1>{__('Dynamic Query — Filter Index', 'designsetgo')}</h1>
			<IndexStatusCard />
			<RegisteredFiltersTable onChanged={handleTableReady} />
			<AddFilterForm onAdded={refreshFilters} />
		</div>
	);
}
