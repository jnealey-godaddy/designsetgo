/**
 * Dashboard Component
 *
 * Overview page with plugin stats, blocks, extensions, and quick links.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Card, CardHeader, CardBody, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { Icon, layout, plugins, edit } from '@wordpress/icons';

const Dashboard = () => {
	const [stats, setStats] = useState(null);
	const [blocks, setBlocks] = useState([]);
	const [extensions, setExtensions] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		Promise.all([
			apiFetch({ path: '/designsetgo/v1/stats' }),
			apiFetch({ path: '/designsetgo/v1/blocks' }),
			apiFetch({ path: '/designsetgo/v1/extensions' }),
			apiFetch({ path: '/designsetgo/v1/settings' }),
		])
			.then(([statsData, blocksData, extensionsData, settingsData]) => {
				// Flatten blocks object into array with category info
				const flatBlocks = [];
				if (blocksData && typeof blocksData === 'object') {
					Object.entries(blocksData).forEach(
						([categoryKey, categoryData]) => {
							if (
								categoryData.blocks &&
								Array.isArray(categoryData.blocks)
							) {
								categoryData.blocks.forEach((block) => {
									flatBlocks.push({
										...block,
										category: categoryKey,
									});
								});
							}
						}
					);
				}

				// Enrich extensions with enabled status
				const enrichedExtensions = Array.isArray(extensionsData)
					? extensionsData.map((ext) => ({
							...ext,
							enabled:
								settingsData.enabled_extensions.length === 0 ||
								settingsData.enabled_extensions.includes(
									ext.name
								),
						}))
					: [];

				setStats(statsData);
				setBlocks(flatBlocks);
				setExtensions(enrichedExtensions);
				setLoading(false);
			})
			.catch(() => {
				setLoading(false);
			});
	}, []);

	if (loading) {
		return (
			<div className="designsetgo-admin-loading">
				<Spinner />
			</div>
		);
	}

	// Calculate block categories (ensure blocks is an array)
	const blocksByCategory = (Array.isArray(blocks) ? blocks : []).reduce(
		(acc, block) => {
			const category = block.category || 'other';
			acc[category] = (acc[category] || 0) + 1;
			return acc;
		},
		{}
	);

	return (
		<div className="designsetgo-dashboard">
			<div className="designsetgo-dashboard__header">
				<div className="designsetgo-dashboard__branding">
					<img
						src={window.designSetGoAdmin?.logoUrl}
						alt="DesignSetGo"
						className="designsetgo-logo"
					/>
					<p className="description">
						{__(
							'Manage your DesignSetGo blocks, extensions, and settings.',
							'designsetgo'
						)}
					</p>
				</div>
			</div>

			{/* Stats Cards */}
			<div className="designsetgo-dashboard__stats">
				<div className="designsetgo-stat-card">
					<div className="designsetgo-stat-card__icon designsetgo-stat-card__icon--blocks">
						<Icon icon={layout} />
					</div>
					<div className="designsetgo-stat-card__content">
						<div className="designsetgo-stat-card__value">
							{stats?.total_blocks || blocks.length || 0}
						</div>
						<div className="designsetgo-stat-card__label">
							{__('Blocks', 'designsetgo')}
						</div>
					</div>
				</div>

				<div className="designsetgo-stat-card">
					<div className="designsetgo-stat-card__icon designsetgo-stat-card__icon--extensions">
						<Icon icon={plugins} />
					</div>
					<div className="designsetgo-stat-card__content">
						<div className="designsetgo-stat-card__value">
							{extensions.length || 0}
						</div>
						<div className="designsetgo-stat-card__label">
							{__('Extensions', 'designsetgo')}
						</div>
					</div>
				</div>

				<div className="designsetgo-stat-card">
					<div className="designsetgo-stat-card__icon designsetgo-stat-card__icon--forms">
						<Icon icon={edit} />
					</div>
					<div className="designsetgo-stat-card__content">
						<div className="designsetgo-stat-card__value">
							{stats?.form_submissions || 0}
						</div>
						<div className="designsetgo-stat-card__label">
							{__('Form Submissions', 'designsetgo')}
						</div>
					</div>
				</div>
			</div>

			{/* Blocks & Extensions Overview */}
			<div className="designsetgo-dashboard__overview">
				<Card>
					<CardHeader>
						<h2>{__('Blocks & Extensions', 'designsetgo')}</h2>
					</CardHeader>
					<CardBody>
						{/* Block Categories */}
						{Object.keys(blocksByCategory).length > 0 && (
							<div className="designsetgo-blocks-categories">
								<h3 className="designsetgo-section-heading">
									{__('Blocks by Category', 'designsetgo')}
								</h3>
								<div className="designsetgo-categories-grid">
									{Object.entries(blocksByCategory).map(
										([category, count]) => (
											<div
												key={category}
												className="designsetgo-category-item"
											>
												<span className="designsetgo-category-name">
													{category
														.replace(/-/g, ' ')
														.replace(/\b\w/g, (l) =>
															l.toUpperCase()
														)}
												</span>
												<span className="designsetgo-category-count">
													{count}
												</span>
											</div>
										)
									)}
								</div>
							</div>
						)}

						{/* Extensions */}
						{extensions.length > 0 && (
							<div className="designsetgo-extensions-list">
								<h3 className="designsetgo-section-heading">
									{__('Extensions', 'designsetgo')}
								</h3>
								<div className="designsetgo-extension-pills">
									{extensions.map((extension) => (
										<span
											key={extension.name}
											className={`designsetgo-extension-pill ${
												extension.enabled
													? 'is-enabled'
													: 'is-disabled'
											}`}
										>
											{extension.title}
											<span className="designsetgo-extension-status">
												{extension.enabled
													? __(
															'Enabled',
															'designsetgo'
														)
													: __(
															'Disabled',
															'designsetgo'
														)}
											</span>
										</span>
									))}
								</div>
							</div>
						)}
					</CardBody>
				</Card>
			</div>

			{/* Quick Links */}
			<div className="designsetgo-dashboard__quick-links">
				<Card>
					<CardHeader>
						<h2>{__('Quick Links', 'designsetgo')}</h2>
					</CardHeader>
					<CardBody>
						<div className="designsetgo-quick-links-grid">
							<a
								href="?page=designsetgo-blocks"
								className="designsetgo-quick-link"
							>
								<span className="dashicons dashicons-layout"></span>
								<h3>{__('Manage Blocks', 'designsetgo')}</h3>
								<p>
									{__(
										'Enable or disable individual blocks and extensions',
										'designsetgo'
									)}
								</p>
							</a>
							<a
								href="edit.php?post_type=dsgo_form_submission"
								className="designsetgo-quick-link"
							>
								<span className="dashicons dashicons-feedback"></span>
								<h3>{__('Form Submissions', 'designsetgo')}</h3>
								<p>
									{__(
										'View and manage form submissions',
										'designsetgo'
									)}
								</p>
							</a>
							<a
								href="?page=designsetgo-settings"
								className="designsetgo-quick-link"
							>
								<span className="dashicons dashicons-admin-settings"></span>
								<h3>{__('Settings', 'designsetgo')}</h3>
								<p>
									{__(
										'Configure performance, forms, and security',
										'designsetgo'
									)}
								</p>
							</a>
							<a
								href={`${window.designSetGoAdmin?.siteUrl}/wp-admin/site-editor.php`}
								className="designsetgo-quick-link"
							>
								<span className="dashicons dashicons-admin-appearance"></span>
								<h3>{__('Global Styles', 'designsetgo')}</h3>
								<p>
									{__(
										'Customize colors, typography, and spacing',
										'designsetgo'
									)}
								</p>
							</a>
						</div>
					</CardBody>
				</Card>
			</div>

			{/* designsetgo.dev callout */}
			<a
				className="designsetgo-dashboard__callout"
				href="https://designsetgo.dev"
				target="_blank"
				rel="noopener noreferrer"
			>
				<span className="designsetgo-dashboard__callout-text">
					<strong>
						{__(
							'Run your vibe-coded apps on your WordPress site',
							'designsetgo'
						)}
					</strong>
					{__(
						'designsetgo.dev is a runtime that hosts the apps you build with AI — wired to your real posts, users, and data.',
						'designsetgo'
					)}
				</span>
				<span className="designsetgo-dashboard__callout-cta">
					{__('Explore designsetgo.dev', 'designsetgo')}
					<span className="dashicons dashicons-external"></span>
				</span>
			</a>
		</div>
	);
};

export default Dashboard;
