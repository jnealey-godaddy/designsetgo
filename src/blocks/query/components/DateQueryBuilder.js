/* eslint-disable @wordpress/no-unsafe-wp-apis -- experimental layout/control primitives intentionally used; stable replacements not yet available */
import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

const COLUMN_OPTIONS = [
	{ value: 'post_date', label: __('Post Date', 'designsetgo') },
	{ value: 'post_modified', label: __('Post Modified', 'designsetgo') },
	{ value: 'post_date_gmt', label: __('Post Date (GMT)', 'designsetgo') },
	{
		value: 'post_modified_gmt',
		label: __('Post Modified (GMT)', 'designsetgo'),
	},
];

const MODE_OPTIONS = [
	{ value: 'after', label: __('After', 'designsetgo') },
	{ value: 'before', label: __('Before', 'designsetgo') },
	{ value: 'between', label: __('Between', 'designsetgo') },
];

const EMPTY_DEFAULT = { relation: 'AND', clauses: [] };

const DEFAULT_CLAUSE = {
	column: 'post_date',
	mode: 'after',
	after: '',
	before: '',
	inclusive: true,
};

export default function DateQueryBuilder({
	attributes,
	setAttributes,
	clientId,
}) {
	const dateQuery = attributes.dateQuery ?? EMPTY_DEFAULT;

	const updateClause = (i, patch) => {
		const next = [...dateQuery.clauses];
		next[i] = { ...next[i], ...patch };
		setAttributes({ dateQuery: { ...dateQuery, clauses: next } });
	};

	const removeClause = (i) => {
		setAttributes({
			dateQuery: {
				...dateQuery,
				clauses: dateQuery.clauses.filter((_, idx) => idx !== i),
			},
		});
	};

	const addClause = () => {
		setAttributes({
			dateQuery: {
				...dateQuery,
				clauses: [...dateQuery.clauses, { ...DEFAULT_CLAUSE }],
			},
		});
	};

	return (
		<DsgoInspectorPanel
			title={__('Date filters', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() => setAttributes({ dateQuery: EMPTY_DEFAULT })}
		>
			<DsgoInspectorPanel.Item
				label={__('Date query', 'designsetgo')}
				hasValue={() => dateQuery.clauses.length > 0}
				onDeselect={() => setAttributes({ dateQuery: EMPTY_DEFAULT })}
				isShownByDefault
			>
				<VStack spacing={3}>
					{dateQuery.clauses.length > 1 && (
						<SelectControl
							label={__('Relation', 'designsetgo')}
							value={dateQuery.relation}
							options={[
								{ value: 'AND', label: 'AND' },
								{ value: 'OR', label: 'OR' },
							]}
							onChange={(v) =>
								setAttributes({
									dateQuery: { ...dateQuery, relation: v },
								})
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					)}

					{dateQuery.clauses.map((clause, i) => {
						const showAfter =
							clause.mode === 'after' ||
							clause.mode === 'between';
						const showBefore =
							clause.mode === 'before' ||
							clause.mode === 'between';
						return (
							<VStack key={i} spacing={2}>
								<HStack>
									<SelectControl
										label={__('Column', 'designsetgo')}
										value={clause.column}
										options={COLUMN_OPTIONS}
										onChange={(v) =>
											updateClause(i, { column: v })
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
									<SelectControl
										label={__('Mode', 'designsetgo')}
										value={clause.mode}
										options={MODE_OPTIONS}
										onChange={(v) =>
											updateClause(i, { mode: v })
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								</HStack>
								{showAfter && (
									<TextControl
										label={__('After', 'designsetgo')}
										value={clause.after}
										placeholder="YYYY-MM-DD or -30 days"
										onChange={(v) =>
											updateClause(i, { after: v })
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								)}
								{showBefore && (
									<TextControl
										label={__('Before', 'designsetgo')}
										value={clause.before}
										placeholder="YYYY-MM-DD or today"
										onChange={(v) =>
											updateClause(i, { before: v })
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
								)}
								<ToggleControl
									label={__('Inclusive', 'designsetgo')}
									checked={clause.inclusive}
									onChange={(v) =>
										updateClause(i, { inclusive: v })
									}
									__nextHasNoMarginBottom
								/>
								<Button
									isDestructive
									variant="tertiary"
									onClick={() => removeClause(i)}
									aria-label={__(
										'Remove date clause',
										'designsetgo'
									)}
									__next40pxDefaultSize
								>
									{__('Remove', 'designsetgo')}
								</Button>
							</VStack>
						);
					})}

					<Button variant="secondary" onClick={addClause} __next40pxDefaultSize>
						{__('Add date clause', 'designsetgo')}
					</Button>
				</VStack>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}
