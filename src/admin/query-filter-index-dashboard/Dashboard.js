/**
 * Dynamic Query — Facet Admin Dashboard component.
 *
 * Three sub-components:
 *   IndexStatusCard       — last rebuild time, row count, rebuild button.
 *   RegisteredFacetsTable — list of registered facets with Remove per row.
 *   AddFacetForm          — form to add a new facet.
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
import { __ } from '@wordpress/i18n';

const { apiUrl, nonce } = window.dsgoQueryFacetDashboard || {};

/**
 * Shared fetch helper — sets nonce header and parses JSON.
 *
 * @param {string} path URL path relative to apiUrl.
 * @param {Object} opts fetch() options overrides.
 * @return {Promise<any>}     Parsed JSON body.
 */
async function apiFetch(path, opts = {}) {
	const response = await fetch(`${apiUrl}${path}`, {
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
			...(opts.headers || {}),
		},
		...opts,
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
			const data = await apiFetch('/query/facet-status');
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
			const result = await apiFetch('/query/facet-rebuild', {
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
		<div className="dsgo-facet-card">
			<h2>{__('Index Status', 'designsetgo')}</h2>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{!status && !error && <Spinner />}

			{status && (
				<table className="dsgo-facet-status-table">
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

			<div className="dsgo-facet-card__actions">
				{status?.in_progress ? (
					<>
						<Spinner />
						<span>{__('Rebuild in progress…', 'designsetgo')}</span>
					</>
				) : (
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
// RegisteredFacetsTable
// ─────────────────────────────────────────────────────────────────────────────

function RegisteredFacetsTable({ onChanged }) {
	const [facets, setFacets] = useState(null);
	const [error, setError] = useState(null);
	const [removing, setRemoving] = useState(null);

	const loadFacets = useCallback(async () => {
		try {
			const data = await apiFetch('/query/facets');
			setFacets(data);
		} catch (e) {
			setError(e.message);
		}
	}, []);

	useEffect(() => {
		loadFacets();
	}, [loadFacets]);

	// Refresh when a new facet is added.
	useEffect(() => {
		if (onChanged) {
			onChanged(loadFacets);
		}
	}, [onChanged, loadFacets]);

	const handleRemove = async (key) => {
		setRemoving(key);
		setError(null);
		try {
			await apiFetch(
				`/query/facets?facet_key=${encodeURIComponent(key)}`,
				{
					method: 'DELETE',
				}
			);
			setFacets((prev) => {
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

	const entries = facets ? Object.entries(facets) : [];

	return (
		<div className="dsgo-facet-card">
			<h2>{__('Registered Facets', 'designsetgo')}</h2>

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{!facets && !error && <Spinner />}

			{facets && entries.length === 0 && (
				<p className="dsgo-facet-empty">
					{__('No facets registered yet.', 'designsetgo')}
				</p>
			)}

			{facets && entries.length > 0 && (
				<table className="dsgo-facet-list-table widefat">
					<thead>
						<tr>
							<th>{__('Key', 'designsetgo')}</th>
							<th>{__('Type', 'designsetgo')}</th>
							<th>{__('Source', 'designsetgo')}</th>
							<th>{__('Label', 'designsetgo')}</th>
							<th></th>
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
// AddFacetForm
// ─────────────────────────────────────────────────────────────────────────────

const TYPE_OPTIONS = [
	{ label: __('Taxonomy', 'designsetgo'), value: 'taxonomy' },
	{ label: __('Meta', 'designsetgo'), value: 'meta' },
];

function AddFacetForm({ onAdded }) {
	const [facetKey, setFacetKey] = useState('');
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
			await apiFetch('/query/facet-register', {
				method: 'POST',
				body: JSON.stringify({
					facet_key: facetKey,
					config: { type, source, label },
				}),
			});

			setFacetKey('');
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
		<div className="dsgo-facet-card">
			<h2>{__('Add Facet', 'designsetgo')}</h2>

			{error && (
				<Notice status="error" onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			{success && (
				<Notice status="success" onRemove={() => setSuccess(false)}>
					{__('Facet registered successfully.', 'designsetgo')}
				</Notice>
			)}

			<form onSubmit={handleSubmit} className="dsgo-facet-add-form">
				<TextControl
					label={__('Facet Key', 'designsetgo')}
					value={facetKey}
					onChange={setFacetKey}
					placeholder="e.g. price"
					required
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={__('Type', 'designsetgo')}
					value={type}
					options={TYPE_OPTIONS}
					onChange={setType}
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Source', 'designsetgo')}
					value={source}
					onChange={setSource}
					placeholder="e.g. _price or category"
					required
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={__('Label (optional)', 'designsetgo')}
					value={label}
					onChange={setLabel}
					placeholder={__('Human-readable label', 'designsetgo')}
					__nextHasNoMarginBottom
				/>
				<Button
					type="submit"
					variant="primary"
					isBusy={saving}
					disabled={saving || !facetKey || !source}
				>
					{__('Add Facet', 'designsetgo')}
				</Button>
			</form>
		</div>
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard (root)
// ─────────────────────────────────────────────────────────────────────────────

export default function Dashboard() {
	// refreshFacets is a ref to RegisteredFacetsTable's loadFacets, so
	// AddFacetForm can trigger a reload after a successful register.
	const [refreshFacets, setRefreshFacets] = useState(null);

	const handleTableReady = (loadFn) => {
		setRefreshFacets(() => loadFn);
	};

	return (
		<div className="dsgo-query-facet-dashboard">
			<h1>{__('Dynamic Query — Facet Index', 'designsetgo')}</h1>
			<IndexStatusCard />
			<RegisteredFacetsTable onChanged={handleTableReady} />
			<AddFacetForm onAdded={refreshFacets} />
		</div>
	);
}
