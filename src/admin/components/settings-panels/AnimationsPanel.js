/**
 * Animations Settings Panel
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { trash } from '@wordpress/icons';
import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	FormTokenField,
	Button,
} from '@wordpress/components';

const ENTRANCE_OPTIONS = [
	{ label: __('Fade In', 'designsetgo'), value: 'fadeIn' },
	{ label: __('Fade In Up', 'designsetgo'), value: 'fadeInUp' },
	{ label: __('Fade In Down', 'designsetgo'), value: 'fadeInDown' },
	{ label: __('Fade In Left', 'designsetgo'), value: 'fadeInLeft' },
	{ label: __('Fade In Right', 'designsetgo'), value: 'fadeInRight' },
	{ label: __('Slide In Up', 'designsetgo'), value: 'slideInUp' },
	{ label: __('Slide In Down', 'designsetgo'), value: 'slideInDown' },
	{ label: __('Slide In Left', 'designsetgo'), value: 'slideInLeft' },
	{ label: __('Slide In Right', 'designsetgo'), value: 'slideInRight' },
	{ label: __('Zoom In', 'designsetgo'), value: 'zoomIn' },
	{ label: __('Bounce In', 'designsetgo'), value: 'bounceIn' },
	{ label: __('Flip In X', 'designsetgo'), value: 'flipInX' },
	{ label: __('Flip In Y', 'designsetgo'), value: 'flipInY' },
];

const TRIGGER_OPTIONS = [
	{ label: __('On Scroll', 'designsetgo'), value: 'scroll' },
	{ label: __('On Load', 'designsetgo'), value: 'load' },
	{ label: __('On Hover', 'designsetgo'), value: 'hover' },
	{ label: __('On Click', 'designsetgo'), value: 'click' },
];

const DURATION_OPTIONS = [
	{ label: __('Fast (300ms)', 'designsetgo'), value: 300 },
	{ label: __('Normal (600ms)', 'designsetgo'), value: 600 },
	{ label: __('Slow (1000ms)', 'designsetgo'), value: 1000 },
	{ label: __('Very Slow (2000ms)', 'designsetgo'), value: 2000 },
];

// Mirrors the PHP sanitizer's pattern (Settings::sanitize_block_animation_targets)
// so a typed token the backend would drop is refused up front rather than
// vanishing silently on save.
const BLOCK_NAME_PATTERN = /^[a-z0-9-]+\/(\*|[a-z0-9-]+)$/;

const NEW_ROW = {
	blocks: [],
	entrance: 'fadeInUp',
	exit: '',
	trigger: 'scroll',
	duration: 600,
	delay: 0,
	easing: 'ease-out',
	offset: 100,
	once: true,
};

const AnimationsPanel = ({ settings, updateSetting }) => {
	// Registered block types for the picker. null = loading, false = failed
	// (falls back to a plain text field), array = loaded.
	const [blockTypes, setBlockTypes] = useState(null);

	useEffect(() => {
		let active = true;
		apiFetch({
			path: '/wp/v2/block-types?context=view&_fields=name,title',
		})
			.then((types) => {
				if (active) {
					setBlockTypes(Array.isArray(types) ? types : []);
				}
			})
			.catch(() => {
				if (active) {
					setBlockTypes(false);
				}
			});
		return () => {
			active = false;
		};
	}, []);

	// Searchable options for the block-type picker: namespace wildcards first
	// (e.g. "core/* — all core blocks"), then every registered block as
	// "Title — name", alphabetically. Empty until the fetch resolves.
	const blockOptions = useMemo(() => {
		if (!Array.isArray(blockTypes)) {
			return [];
		}
		const namespaces = [
			...new Set(blockTypes.map((b) => b.name.split('/')[0])),
		].sort();
		const wildcards = namespaces.map((ns) => ({
			value: `${ns}/*`,
			label: `${ns}/* — ${sprintf(
				// translators: %s is a block namespace, e.g. "core".
				__('all %s blocks', 'designsetgo'),
				ns
			)}`,
		}));
		const blocks = blockTypes
			.map((b) => ({
				value: b.name,
				label: `${b.title} — ${b.name}`,
			}))
			.sort((a, b) => a.label.localeCompare(b.label));
		return [...wildcards, ...blocks];
	}, [blockTypes]);

	// FormTokenField works in display strings, but we persist canonical block
	// names — keep a map each way so tokens round-trip.
	const { labelByName, nameByLabel, suggestions } = useMemo(() => {
		const byName = {};
		const byLabel = {};
		blockOptions.forEach((option) => {
			byName[option.value] = option.label;
			byLabel[option.label] = option.value;
		});
		return {
			labelByName: byName,
			nameByLabel: byLabel,
			suggestions: blockOptions.map((option) => option.label),
		};
	}, [blockOptions]);

	// A token is either a label we offered ("Button — core/button") or a block
	// name typed by hand; both resolve to the canonical name.
	const tokenToName = (token) => {
		const raw = (
			typeof token === 'string' ? token : token?.value || ''
		).trim();
		return nameByLabel[raw] || raw;
	};

	return (
		<Card className="designsetgo-settings-panel">
			<CardHeader>
				<h2>{__('Animations', 'designsetgo')}</h2>
			</CardHeader>
			<CardBody>
				<p className="designsetgo-panel-description">
					{__(
						'Configure default animation settings for block entrance and exit effects.',
						'designsetgo'
					)}
				</p>

				<ToggleControl
					label={__('Enable Animations', 'designsetgo')}
					help={__(
						'Enable block entrance/exit animations globally.',
						'designsetgo'
					)}
					checked={settings?.animations?.enable_animations || false}
					onChange={(value) =>
						updateSetting('animations', 'enable_animations', value)
					}
					__nextHasNoMarginBottom
				/>

				{settings?.animations?.enable_animations && (
					<div className="designsetgo-settings-group">
						<RangeControl
							label={__('Default Duration (ms)', 'designsetgo')}
							help={__(
								'Default animation duration in milliseconds.',
								'designsetgo'
							)}
							value={
								settings?.animations?.default_duration || 600
							}
							onChange={(value) =>
								updateSetting(
									'animations',
									'default_duration',
									value
								)
							}
							min={100}
							max={2000}
							step={100}
							marks={[
								{
									value: 300,
									label: __('Fast', 'designsetgo'),
								},
								{
									value: 600,
									label: __('Normal', 'designsetgo'),
								},
								{
									value: 1000,
									label: __('Slow', 'designsetgo'),
								},
							]}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<SelectControl
							label={__('Default Easing', 'designsetgo')}
							help={__(
								'Default animation timing function.',
								'designsetgo'
							)}
							value={
								settings?.animations?.default_easing ||
								'ease-in-out'
							}
							options={[
								{
									label: __('Ease', 'designsetgo'),
									value: 'ease',
								},
								{
									label: __('Ease In', 'designsetgo'),
									value: 'ease-in',
								},
								{
									label: __('Ease Out', 'designsetgo'),
									value: 'ease-out',
								},
								{
									label: __('Ease In Out', 'designsetgo'),
									value: 'ease-in-out',
								},
								{
									label: __('Linear', 'designsetgo'),
									value: 'linear',
								},
							]}
							onChange={(value) =>
								updateSetting(
									'animations',
									'default_easing',
									value
								)
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<div className="designsetgo-settings-section">
							<h3 className="designsetgo-section-heading">
								{__('Default Hover Effects', 'designsetgo')}
							</h3>

							<SelectControl
								label={__(
									'Icon Button Default Hover',
									'designsetgo'
								)}
								help={__(
									'Set a default hover animation for all Icon Button blocks. Individual blocks can override this setting.',
									'designsetgo'
								)}
								value={
									settings?.animations
										?.default_icon_button_hover ||
									'fill-diagonal'
								}
								options={[
									{
										label: __('None', 'designsetgo'),
										value: 'none',
									},
									{
										label: __(
											'Fill Diagonal',
											'designsetgo'
										),
										value: 'fill-diagonal',
									},
									{
										label: __('Zoom In', 'designsetgo'),
										value: 'zoom-in',
									},
									{
										label: __('Slide Left', 'designsetgo'),
										value: 'slide-left',
									},
									{
										label: __('Slide Right', 'designsetgo'),
										value: 'slide-right',
									},
									{
										label: __('Slide Down', 'designsetgo'),
										value: 'slide-down',
									},
									{
										label: __('Slide Up', 'designsetgo'),
										value: 'slide-up',
									},
									{
										label: __(
											'Border Pulse',
											'designsetgo'
										),
										value: 'border-pulse',
									},
									{
										label: __('Border Glow', 'designsetgo'),
										value: 'border-glow',
									},
									{
										label: __('Lift', 'designsetgo'),
										value: 'lift',
									},
									{
										label: __('Shrink', 'designsetgo'),
										value: 'shrink',
									},
								]}
								onChange={(value) =>
									updateSetting(
										'animations',
										'default_icon_button_hover',
										value
									)
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>

						<div className="designsetgo-settings-section">
							<h3 className="designsetgo-section-heading">
								{__('Accessibility', 'designsetgo')}
							</h3>

							<ToggleControl
								label={__(
									'Respect Prefers Reduced Motion',
									'designsetgo'
								)}
								help={__(
									'Disable animations for users who prefer reduced motion. Highly recommended for accessibility.',
									'designsetgo'
								)}
								checked={
									settings?.animations
										?.respect_prefers_reduced_motion ||
									false
								}
								onChange={(value) =>
									updateSetting(
										'animations',
										'respect_prefers_reduced_motion',
										value
									)
								}
								__nextHasNoMarginBottom
							/>
						</div>

						<div className="designsetgo-settings-section">
							<h3 className="designsetgo-section-heading">
								{__('Theme Animation Defaults', 'designsetgo')}
							</h3>

							<ToggleControl
								label={__(
									'Enable theme animation defaults',
									'designsetgo'
								)}
								help={__(
									'Automatically animate every block of a chosen type. Individual blocks can override or opt out.',
									'designsetgo'
								)}
								checked={
									settings?.animations
										?.block_animations_enabled || false
								}
								onChange={(value) =>
									updateSetting(
										'animations',
										'block_animations_enabled',
										value
									)
								}
								__nextHasNoMarginBottom
							/>

							{settings?.animations?.block_animations_enabled && (
								<div className="designsetgo-block-animations">
									{(
										settings?.animations
											?.block_animations || []
									).map((row, index) => {
										const list =
											settings.animations
												.block_animations;
										const update = (patch) => {
											const next = list.map((r, i) =>
												i === index
													? { ...r, ...patch }
													: r
											);
											updateSetting(
												'animations',
												'block_animations',
												next
											);
										};
										const remove = () =>
											updateSetting(
												'animations',
												'block_animations',
												list.filter(
													(r, i) => i !== index
												)
											);

										// The server accepts any duration in the
										// 100–5000ms range; keep a non-preset value
										// (e.g. one set via the settings REST /
										// abilities API) representable in the select
										// instead of showing nothing selected.
										const durationOptions =
											DURATION_OPTIONS.some(
												(o) => o.value === row.duration
											)
												? DURATION_OPTIONS
												: [
														...DURATION_OPTIONS,
														{
															label: `${row.duration}ms`,
															value: row.duration,
														},
													];

										const blocks = Array.isArray(row.blocks)
											? row.blocks
											: [];
										// Show the friendly label where we have one, else the
										// raw name (a block from a plugin since deactivated).
										const tokens = blocks.map(
											(name) => labelByName[name] || name
										);

										// A block may only be claimed by one rule. The
										// sanitizer resolves a double claim by handing the
										// block to the LAST row and stripping it from the
										// earlier one (dedupe_block_animation_targets) — so
										// keep the conflict from being authored at all
										// rather than letting it resolve invisibly on save.
										// Exact names only: `core/*` and `core/button` are
										// distinct map keys server-side (exact beats
										// wildcard), so they are a legitimate pairing.
										const claimedElsewhere = new Set(
											list
												.filter((r, i) => i !== index)
												.flatMap((r) =>
													Array.isArray(r.blocks)
														? r.blocks
														: []
												)
										);
										const rowSuggestions =
											suggestions.filter(
												(label) =>
													!claimedElsewhere.has(
														nameByLabel[label] ||
															label
													)
											);
										const validateRowToken = (token) => {
											const name = tokenToName(token);
											return (
												BLOCK_NAME_PATTERN.test(name) &&
												!claimedElsewhere.has(name)
											);
										};

										// A list that arrived already-conflicting (saved
										// through the settings REST route / abilities API
										// rather than this UI) can't be fixed by the guards
										// above — say which targets this row is about to
										// lose instead of letting them vanish on reload.
										const losingTo = blocks.filter((name) =>
											list.some(
												(r, i) =>
													i > index &&
													Array.isArray(r.blocks) &&
													r.blocks.includes(name)
											)
										);

										return (
											<div
												key={index}
												className="designsetgo-block-animations__row"
											>
												<div className="designsetgo-block-animations__block">
													<FormTokenField
														className="designsetgo-block-animations__block-input"
														label={__(
															'Block types',
															'designsetgo'
														)}
														value={tokens}
														suggestions={
															rowSuggestions
														}
														onChange={(next) =>
															update({
																blocks: [
																	...new Set(
																		next.map(
																			tokenToName
																		)
																	),
																],
															})
														}
														__experimentalValidateInput={
															validateRowToken
														}
														__experimentalExpandOnFocus
														__experimentalAutoSelectFirstMatch
														__experimentalShowHowTo={
															false
														}
														__next40pxDefaultSize
														__nextHasNoMarginBottom
													/>
													<p className="designsetgo-block-animations__hint">
														{blockTypes === null &&
															__(
																'Loading block types…',
																'designsetgo'
															)}
														{blockTypes !== null &&
															blocks.length ===
																0 &&
															__(
																'Add at least one block type — this rule is skipped until you do.',
																'designsetgo'
															)}
														{blockTypes !== null &&
															blocks.length > 0 &&
															__(
																'Search for blocks to add, or pick a namespace wildcard like designsetgo/*. Every block listed here shares this animation.',
																'designsetgo'
															)}
													</p>
													{losingTo.length > 0 && (
														<p className="designsetgo-block-animations__hint designsetgo-block-animations__hint--warning">
															{sprintf(
																/* translators: %s is a comma-separated list of block names, e.g. "core/button, core/heading". */
																__(
																	'Also claimed by a later rule, which wins: %s. These will be dropped from this rule when you save.',
																	'designsetgo'
																),
																losingTo.join(
																	', '
																)
															)}
														</p>
													)}
												</div>
												<SelectControl
													label={__(
														'Entrance',
														'designsetgo'
													)}
													value={row.entrance}
													options={ENTRANCE_OPTIONS}
													onChange={(value) =>
														update({
															entrance: value,
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<SelectControl
													label={__(
														'Trigger',
														'designsetgo'
													)}
													value={row.trigger}
													options={TRIGGER_OPTIONS}
													onChange={(value) =>
														update({
															trigger: value,
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<SelectControl
													label={__(
														'Duration',
														'designsetgo'
													)}
													value={row.duration}
													options={durationOptions}
													onChange={(value) =>
														update({
															duration: parseInt(
																value,
																10
															),
														})
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<Button
													className="designsetgo-block-animations__remove"
													icon={trash}
													label={__(
														'Remove block type',
														'designsetgo'
													)}
													isDestructive
													onClick={remove}
												/>
											</div>
										);
									})}

									<Button
										variant="secondary"
										onClick={() =>
											updateSetting(
												'animations',
												'block_animations',
												[
													...(settings.animations
														.block_animations ||
														[]),
													{ ...NEW_ROW },
												]
											)
										}
									>
										{__(
											'Add animation rule',
											'designsetgo'
										)}
									</Button>
								</div>
							)}
						</div>
					</div>
				)}
			</CardBody>
		</Card>
	);
};

export default AnimationsPanel;
