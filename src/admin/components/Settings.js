/**
 * Settings Component
 *
 * Configure performance, forms, animations, sticky header, and security settings.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Notice, TabPanel } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

// Import settings panels
import StickyHeaderPanel from './settings-panels/StickyHeaderPanel';
import PerformancePanel from './settings-panels/PerformancePanel';
import FormsPanel from './settings-panels/FormsPanel';
import AnimationsPanel from './settings-panels/AnimationsPanel';
import SecurityPanel from './settings-panels/SecurityPanel';
import IntegrationsPanel from './settings-panels/IntegrationsPanel';
import DraftModePanel from './settings-panels/DraftModePanel';
import LLMSTxtPanel from './settings-panels/LLMSTxtPanel';

const Settings = () => {
	const [settings, setSettings] = useState(null);
	// Last state the server confirmed, used to send only what changed.
	const [savedSettings, setSavedSettings] = useState(null);
	const [loading, setLoading] = useState(true);
	const [loadFailed, setLoadFailed] = useState(false);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);

	const isDirty =
		settings !== null &&
		savedSettings !== null &&
		JSON.stringify(settings) !== JSON.stringify(savedSettings);

	// Fetch initial data
	const loadSettings = useCallback(() => {
		setLoading(true);
		setLoadFailed(false);

		apiFetch({ path: '/designsetgo/v1/settings' })
			.then((data) => {
				// A non-object body must never become the editable state.
				if (!data || typeof data !== 'object' || Array.isArray(data)) {
					throw new Error('Invalid settings response.');
				}
				setSettings(data);
				setSavedSettings(data);
				setLoading(false);
			})
			.catch(() => {
				setLoadFailed(true);
				setLoading(false);
			});
	}, []);

	useEffect(() => {
		loadSettings();
	}, [loadSettings]);

	// Warn before leaving with unsaved changes.
	useEffect(() => {
		if (!isDirty) {
			return;
		}
		const warn = (event) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener('beforeunload', warn);
		return () => window.removeEventListener('beforeunload', warn);
	}, [isDirty]);

	// Auto-dismiss success notices
	useEffect(() => {
		if (notice?.status === 'success') {
			const timer = setTimeout(() => {
				setNotice(null);
			}, 3000);
			return () => clearTimeout(timer);
		}
	}, [notice]);

	/**
	 * Update a setting value
	 *
	 * @param {string} section - The settings section.
	 * @param {string} key     - The setting key.
	 * @param {*}      value   - The new value.
	 */
	const updateSetting = (section, key, value) => {
		setSettings({
			...settings,
			[section]: {
				...settings[section],
				[key]: value,
			},
		});
	};

	/**
	 * Save settings
	 *
	 * Sends only the top-level sections that changed (the server merges them
	 * into what is stored), so a stale tab cannot clobber sections it never
	 * touched, then adopts the server's sanitized copy.
	 */
	const saveSettings = () => {
		if (!settings || !savedSettings) {
			return;
		}

		const changed = {};
		Object.keys(settings).forEach((key) => {
			if (
				JSON.stringify(settings[key]) !==
				JSON.stringify(savedSettings[key])
			) {
				changed[key] = settings[key];
			}
		});

		if (Object.keys(changed).length === 0) {
			setNotice({
				status: 'success',
				message: __('No changes to save.', 'designsetgo'),
			});
			return;
		}

		setSaving(true);
		setNotice(null);

		apiFetch({
			path: '/designsetgo/v1/settings',
			method: 'POST',
			data: changed,
		})
			.then((response) => {
				const saved = response?.settings;
				if (saved && typeof saved === 'object') {
					setSettings(saved);
					setSavedSettings(saved);
				} else {
					setSavedSettings(settings);
				}
				setNotice({
					status: 'success',
					message: __('Settings saved successfully!', 'designsetgo'),
				});
				setSaving(false);
			})
			.catch((error) => {
				setNotice({
					status: 'error',
					message:
						error.message ||
						__('Failed to save settings.', 'designsetgo'),
				});
				setSaving(false);
			});
	};

	if (loading) {
		return (
			<div className="designsetgo-admin-loading">
				<Spinner />
			</div>
		);
	}

	if (loadFailed || !settings) {
		return (
			<div className="designsetgo-settings">
				<div className="designsetgo-settings__header">
					<h1>{__('Settings', 'designsetgo')}</h1>
				</div>
				<Notice status="error" isDismissible={false}>
					{__(
						'Settings could not be loaded. Saving is disabled until they load.',
						'designsetgo'
					)}
				</Notice>
				<p>
					<Button variant="secondary" onClick={loadSettings}>
						{__('Retry', 'designsetgo')}
					</Button>
				</p>
			</div>
		);
	}

	return (
		<div className="designsetgo-settings">
			<div className="designsetgo-settings__header">
				<h1>{__('Settings', 'designsetgo')}</h1>
				<p className="description">
					{__(
						'Configure performance, forms, animations, sticky header, and security options.',
						'designsetgo'
					)}
				</p>
			</div>

			{notice && (
				<div
					style={{
						position: 'fixed',
						top: '32px',
						right: '20px',
						zIndex: 100000,
						maxWidth: '400px',
						boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
					}}
				>
					<Notice
						status={notice.status}
						onRemove={() => setNotice(null)}
						isDismissible={true}
					>
						{notice.message}
					</Notice>
				</div>
			)}

			<div className="designsetgo-settings-tabs">
				<TabPanel
					className="designsetgo-tab-panel"
					tabs={[
						{
							name: 'features',
							title: __('Features', 'designsetgo'),
							className: 'designsetgo-tab-features',
						},
						{
							name: 'optimization',
							title: __('Optimization', 'designsetgo'),
							className: 'designsetgo-tab-optimization',
						},
						{
							name: 'integrations',
							title: __('Integrations', 'designsetgo'),
							className: 'designsetgo-tab-integrations',
						},
					]}
				>
					{(tab) => (
						<div className="designsetgo-tab-content">
							{tab.name === 'features' && (
								<>
									<StickyHeaderPanel
										settings={settings}
										updateSetting={updateSetting}
									/>
									<DraftModePanel
										settings={settings}
										updateSetting={updateSetting}
									/>
									<FormsPanel
										settings={settings}
										updateSetting={updateSetting}
									/>
									<AnimationsPanel
										settings={settings}
										updateSetting={updateSetting}
									/>
									<LLMSTxtPanel
										settings={settings}
										updateSetting={updateSetting}
									/>
								</>
							)}

							{tab.name === 'optimization' && (
								<>
									<PerformancePanel
										settings={settings}
										updateSetting={updateSetting}
									/>
									<SecurityPanel
										settings={settings}
										updateSetting={updateSetting}
									/>
								</>
							)}

							{tab.name === 'integrations' && (
								<>
									<IntegrationsPanel
										settings={settings}
										updateSetting={updateSetting}
									/>
								</>
							)}
						</div>
					)}
				</TabPanel>
			</div>

			<div className="designsetgo-save-bar">
				<Button
					variant="primary"
					onClick={saveSettings}
					isBusy={saving}
					disabled={saving || !settings}
				>
					{saving
						? __('Saving…', 'designsetgo')
						: __('Save Changes', 'designsetgo')}
				</Button>
			</div>
		</div>
	);
};

export default Settings;
