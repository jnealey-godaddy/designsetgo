/**
 * Blocks & Extensions Component
 *
 * Manage enabled/disabled blocks and extensions.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	CheckboxControl,
	Button,
	Spinner,
	Notice,
	TabPanel,
	SearchControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const BlocksExtensions = () => {
	const [blocks, setBlocks] = useState({});
	const [extensions, setExtensions] = useState([]);
	const [settings, setSettings] = useState(null);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [searchTerm, setSearchTerm] = useState('');
	const [newExcludedBlock, setNewExcludedBlock] = useState('');

	// Fetch initial data with resilient error handling
	useEffect(() => {
		const fetchData = async () => {
			const endpoints = [
				{ key: 'blocks', path: '/designsetgo/v1/blocks' },
				{ key: 'extensions', path: '/designsetgo/v1/extensions' },
				{ key: 'settings', path: '/designsetgo/v1/settings' },
			];

			const results = await Promise.allSettled(
				endpoints.map((endpoint) => apiFetch({ path: endpoint.path }))
			);

			const data = {};
			const failedEndpoints = [];

			results.forEach((result, index) => {
				if (result.status === 'fulfilled') {
					data[endpoints[index].key] = result.value;
				} else {
					failedEndpoints.push(endpoints[index].key);
				}
			});

			// Set data for successful fetches
			if (data.blocks) {
				setBlocks(data.blocks);
			}
			if (data.extensions) {
				setExtensions(data.extensions);
			}
			if (data.settings) {
				setSettings(data.settings);
			}

			// Handle errors
			if (failedEndpoints.length > 0) {
				let errorMessage = __(
					'Failed to load data. Some features may not work correctly.',
					'designsetgo'
				);

				// Check for known conflicting plugins
				const conflictPlugins =
					window.designSetGoAdmin?.conflictPlugins || [];
				if (conflictPlugins.length > 0) {
					const pluginNames = conflictPlugins
						.map((p) => p.name)
						.join(', ');
					errorMessage = `${__(
						'Failed to load data.',
						'designsetgo'
					)} ${__(
						'A plugin conflict was detected with:',
						'designsetgo'
					)} ${pluginNames}. ${__(
						'Try temporarily deactivating conflicting plugins to use this page.',
						'designsetgo'
					)}`;
				}

				setNotice({
					status: 'error',
					message: errorMessage,
				});
			}

			setLoading(false);
		};

		fetchData();
	}, []);

	/**
	 * Check if a block is enabled
	 *
	 * @param {string} blockName - The block name to check.
	 * @return {boolean} Whether the block is enabled.
	 */
	const isBlockEnabled = (blockName) => {
		if (!settings) {
			return false;
		}
		return !settings.disabled_blocks.includes(blockName);
	};

	/**
	 * Check if an extension is enabled
	 *
	 * @param {string} extensionName - The extension name to check.
	 * @return {boolean} Whether the extension is enabled.
	 */
	const isExtensionEnabled = (extensionName) => {
		if (!settings) {
			return false;
		}
		return !settings.disabled_extensions.includes(extensionName);
	};

	/**
	 * Toggle a block on/off
	 *
	 * @param {string} blockName - The block name to toggle.
	 */
	const toggleBlock = (blockName) => {
		const disabled = settings.disabled_blocks;

		setSettings({
			...settings,
			disabled_blocks: disabled.includes(blockName)
				? disabled.filter((name) => name !== blockName)
				: [...disabled, blockName],
		});
	};

	/**
	 * Toggle an extension on/off
	 *
	 * @param {string} extensionName - The extension name to toggle.
	 */
	const toggleExtension = (extensionName) => {
		const disabled = settings.disabled_extensions;

		setSettings({
			...settings,
			disabled_extensions: disabled.includes(extensionName)
				? disabled.filter((name) => name !== extensionName)
				: [...disabled, extensionName],
		});
	};

	/**
	 * Enable all blocks in a category
	 *
	 * @param {string} categoryKey - The category key.
	 */
	const enableAllInCategory = (categoryKey) => {
		const categoryBlocks = blocks[categoryKey].blocks.map((b) => b.name);

		setSettings({
			...settings,
			disabled_blocks: settings.disabled_blocks.filter(
				(name) => !categoryBlocks.includes(name)
			),
		});
	};

	/**
	 * Disable all blocks in a category
	 *
	 * @param {string} categoryKey - The category key.
	 */
	const disableAllInCategory = (categoryKey) => {
		const categoryBlocks = blocks[categoryKey].blocks.map((b) => b.name);

		setSettings({
			...settings,
			disabled_blocks: [
				...new Set([...settings.disabled_blocks, ...categoryBlocks]),
			],
		});
	};

	/**
	 * Save settings
	 */
	const saveSettings = () => {
		setSaving(true);
		setNotice(null);

		apiFetch({
			path: '/designsetgo/v1/settings',
			method: 'POST',
			data: settings,
		})
			.then(() => {
				setNotice({
					status: 'success',
					message: __(
						'Settings saved successfully! Please refresh any open editor pages to see the changes.',
						'designsetgo'
					),
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

	/**
	 * Filter blocks by search term
	 *
	 * @param {Array} categoryBlocks - The blocks to filter.
	 * @return {Array} Filtered blocks.
	 */
	const filterBlocks = (categoryBlocks) => {
		if (!searchTerm) {
			return categoryBlocks;
		}
		return categoryBlocks.filter(
			(block) =>
				block.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
				block.description
					.toLowerCase()
					.includes(searchTerm.toLowerCase())
		);
	};

	/**
	 * Add a block to the exclusion list
	 */
	const addExcludedBlock = () => {
		if (!newExcludedBlock.trim() || !settings) {
			return;
		}

		const blockName = newExcludedBlock.trim();

		// Validate block name format: namespace/blockname or namespace/*
		// Allow underscores as they are valid in WordPress block names
		const blockNamePattern = /^[a-z0-9_-]+\/([a-z0-9_-]+|\*)$/;
		if (!blockNamePattern.test(blockName)) {
			setNotice({
				status: 'error',
				message: __(
					'Invalid block name format. Please use "namespace/blockname" (e.g., gravityforms/form) or "namespace/*" for wildcards.',
					'designsetgo'
				),
			});
			return;
		}

		const currentExcluded = settings.excluded_blocks || [];
		if (currentExcluded.includes(blockName)) {
			setNotice({
				status: 'warning',
				message: __(
					'This block is already in the exclusion list.',
					'designsetgo'
				),
			});
			return;
		}

		setSettings({
			...settings,
			excluded_blocks: [...currentExcluded, blockName],
		});
		setNewExcludedBlock('');

		// Show success notice
		setNotice({
			status: 'success',
			message: sprintf(
				/* translators: %s: block name */
				__(
					'Successfully added %s to the exclusion list.',
					'designsetgo'
				),
				blockName
			),
		});
	};

	/**
	 * Remove a block from the exclusion list
	 * @param {string} blockName - Block name to remove
	 */
	const removeExcludedBlock = (blockName) => {
		if (!settings) {
			return;
		}

		const currentExcluded = settings.excluded_blocks || [];
		setSettings({
			...settings,
			excluded_blocks: currentExcluded.filter(
				(name) => name !== blockName
			),
		});
	};

	if (loading) {
		return (
			<div className="designsetgo-admin-loading">
				<Spinner />
			</div>
		);
	}

	// Check for known conflicting plugins
	const conflictPlugins = window.designSetGoAdmin?.conflictPlugins || [];

	return (
		<div className="designsetgo-blocks-extensions">
			<div className="designsetgo-blocks-extensions__header">
				<h1>{__('Blocks & Extensions', 'designsetgo')}</h1>
				<p className="description">
					{__(
						'Enable or disable blocks and extensions to customize your editing experience.',
						'designsetgo'
					)}
				</p>
			</div>

			{conflictPlugins.length > 0 && !notice && (
				<Notice status="warning" isDismissible={false}>
					{__('Potential plugin conflict detected:', 'designsetgo')}{' '}
					{conflictPlugins.map((p) => p.name).join(', ')}.{' '}
					{__(
						'If you experience issues on this page, try temporarily deactivating the conflicting plugin.',
						'designsetgo'
					)}
				</Notice>
			)}

			{notice && (
				<Notice
					status={notice.status}
					onRemove={() => setNotice(null)}
					isDismissible={true}
				>
					{notice.message}
				</Notice>
			)}

			<TabPanel
				className="designsetgo-tabs"
				activeClass="is-active"
				tabs={[
					{
						name: 'blocks',
						title: __('Blocks', 'designsetgo'),
					},
					{
						name: 'extensions',
						title: __('Extensions', 'designsetgo'),
					},
					{
						name: 'exclusions',
						title: __('Exclusions', 'designsetgo'),
					},
				]}
			>
				{(tab) => (
					<div className="designsetgo-tab-content">
						{tab.name === 'blocks' && (
							<div className="designsetgo-blocks-list">
								<div className="designsetgo-search-wrapper">
									<SearchControl
										value={searchTerm}
										onChange={setSearchTerm}
										placeholder={__(
											'Search blocks…',
											'designsetgo'
										)}
									/>
								</div>

								{Object.entries(blocks).map(
									([key, category]) => {
										const filteredBlocks = filterBlocks(
											category.blocks
										);
										if (filteredBlocks.length === 0) {
											return null;
										}

										const allEnabled = filteredBlocks.every(
											(block) =>
												isBlockEnabled(block.name)
										);
										const noneEnabled =
											filteredBlocks.every(
												(block) =>
													!isBlockEnabled(block.name)
											);

										return (
											<Card
												key={key}
												className="designsetgo-category-card"
											>
												<CardHeader>
													<div className="designsetgo-category-header">
														<h2>
															{category.label}
														</h2>
														<div className="designsetgo-category-actions">
															<Button
																variant="secondary"
																size="small"
																onClick={() =>
																	enableAllInCategory(
																		key
																	)
																}
																disabled={
																	allEnabled
																}
															>
																{__(
																	'Enable All',
																	'designsetgo'
																)}
															</Button>
															<Button
																variant="secondary"
																size="small"
																onClick={() =>
																	disableAllInCategory(
																		key
																	)
																}
																disabled={
																	noneEnabled
																}
															>
																{__(
																	'Disable All',
																	'designsetgo'
																)}
															</Button>
														</div>
													</div>
												</CardHeader>
												<CardBody>
													<div className="designsetgo-blocks-grid">
														{filteredBlocks.map(
															(block) => (
																<div
																	key={
																		block.name
																	}
																	className="designsetgo-block-item"
																>
																	<CheckboxControl
																		label={
																			<span className="designsetgo-block-label">
																				<strong>
																					{
																						block.title
																					}
																				</strong>
																				{block.performance ===
																					'high' && (
																					<span className="designsetgo-badge designsetgo-badge--high">
																						{__(
																							'Heavy',
																							'designsetgo'
																						)}
																					</span>
																				)}
																			</span>
																		}
																		help={
																			block.description
																		}
																		checked={isBlockEnabled(
																			block.name
																		)}
																		onChange={() =>
																			toggleBlock(
																				block.name
																			)
																		}
																	/>
																</div>
															)
														)}
													</div>
												</CardBody>
											</Card>
										);
									}
								)}
							</div>
						)}

						{tab.name === 'extensions' && (
							<Card>
								<CardHeader>
									<h2>{__('Extensions', 'designsetgo')}</h2>
								</CardHeader>
								<CardBody>
									<p className="description">
										{__(
											'Extensions add functionality to all blocks. Disabling an extension removes its controls from the editor; content already using it keeps working as saved.',
											'designsetgo'
										)}
									</p>
									<div className="designsetgo-extensions-grid">
										{extensions.map((extension) => (
											<div
												key={extension.name}
												className="designsetgo-extension-item"
											>
												<CheckboxControl
													label={
														<strong>
															{extension.title}
														</strong>
													}
													help={extension.description}
													checked={isExtensionEnabled(
														extension.name
													)}
													onChange={() =>
														toggleExtension(
															extension.name
														)
													}
												/>
											</div>
										))}
									</div>
								</CardBody>
							</Card>
						)}

						{tab.name === 'exclusions' && (
							<Card>
								<CardHeader>
									<h2>
										{__('Excluded Blocks', 'designsetgo')}
									</h2>
								</CardHeader>
								<CardBody>
									<p className="description">
										{__(
											'Prevent DSG extensions from being applied to specific third-party blocks. This is useful for blocks that have compatibility issues with DSG features (e.g., Gravity Forms).',
											'designsetgo'
										)}
									</p>
									<p
										className="description"
										style={{
											marginTop: '8px',
											fontStyle: 'italic',
										}}
									>
										{__(
											'Note: Make sure the block plugin is active before adding it to the exclusion list. DSG will validate the format but cannot verify if the block exists.',
											'designsetgo'
										)}
									</p>

									<div className="designsetgo-exclusion-input-wrapper">
										<div className="designsetgo-exclusion-input-row">
											<div className="designsetgo-exclusion-input-field">
												<input
													type="text"
													className="components-text-control__input"
													value={newExcludedBlock}
													onChange={(e) =>
														setNewExcludedBlock(
															e.target.value
														)
													}
													onKeyDown={(e) => {
														if (e.key === 'Enter') {
															addExcludedBlock();
														}
													}}
													placeholder={__(
														'e.g., gravityforms/form or gravityforms/*',
														'designsetgo'
													)}
													aria-label={__(
														'Block name to exclude',
														'designsetgo'
													)}
													aria-describedby="excluded-block-input-description"
												/>
												<p
													id="excluded-block-input-description"
													className="description"
												>
													{__(
														'Enter block name (e.g., gravityforms/form) or namespace wildcard (e.g., gravityforms/*)',
														'designsetgo'
													)}
												</p>
											</div>
											<Button
												variant="primary"
												onClick={addExcludedBlock}
												disabled={
													!newExcludedBlock.trim()
												}
											>
												{__('Add', 'designsetgo')}
											</Button>
										</div>
									</div>

									{settings?.excluded_blocks &&
									settings.excluded_blocks.length > 0 ? (
										<div className="designsetgo-excluded-blocks-section">
											<h3 className="designsetgo-excluded-blocks-heading">
												{__(
													'Currently Excluded:',
													'designsetgo'
												)}
											</h3>
											<ul
												className="designsetgo-excluded-block-list"
												aria-live="polite"
												aria-relevant="additions removals"
											>
												{settings.excluded_blocks.map(
													(blockName) => (
														<li
															key={blockName}
															className="designsetgo-excluded-block-item"
														>
															<code>
																{blockName}
															</code>
															<Button
																isDestructive
																variant="secondary"
																onClick={() =>
																	removeExcludedBlock(
																		blockName
																	)
																}
																size="small"
															>
																{__(
																	'Remove',
																	'designsetgo'
																)}
															</Button>
														</li>
													)
												)}
											</ul>
										</div>
									) : (
										<p className="description designsetgo-no-exclusions-message">
											{__(
												'No blocks are currently excluded. Add blocks above to prevent DSG extensions from being applied to them.',
												'designsetgo'
											)}
										</p>
									)}

									<div className="designsetgo-info-box">
										<h4>
											{__(
												'Common Blocks to Exclude:',
												'designsetgo'
											)}
										</h4>
										<ul>
											<li>
												<code>gravityforms/*</code> -{' '}
												{__(
													'All Gravity Forms blocks',
													'designsetgo'
												)}
											</li>
											<li>
												<code>mailpoet/*</code> -{' '}
												{__(
													'All MailPoet blocks',
													'designsetgo'
												)}
											</li>
											<li>
												<code>woocommerce/*</code> -{' '}
												{__(
													'All WooCommerce blocks',
													'designsetgo'
												)}
											</li>
											<li>
												<code>jetpack/*</code> -{' '}
												{__(
													'All Jetpack blocks',
													'designsetgo'
												)}
											</li>
										</ul>
									</div>
								</CardBody>
							</Card>
						)}
					</div>
				)}
			</TabPanel>

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
				{!settings && (
					<p className="description">
						{__(
							'Settings could not be loaded. Saving is disabled.',
							'designsetgo'
						)}
					</p>
				)}
			</div>
		</div>
	);
};

export default BlocksExtensions;
