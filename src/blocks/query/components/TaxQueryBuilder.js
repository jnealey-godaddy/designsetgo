/* eslint-disable @wordpress/no-unsafe-wp-apis -- experimental layout/control primitives intentionally used; stable replacements not yet available */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import {
	Button,
	SelectControl,
	ToggleControl,
	FormTokenField,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';

const OPERATORS = [
	{ value: 'IN', label: __('In', 'designsetgo') },
	{ value: 'NOT IN', label: __('Not in', 'designsetgo') },
	{ value: 'AND', label: __('All of', 'designsetgo') },
];

export default function TaxQueryBuilder({
	attributes,
	setAttributes,
	clientId,
}) {
	const { postType, taxQuery } = attributes;

	const taxonomies = useSelect(
		(select) => select(coreStore).getTaxonomies({ per_page: -1 }) || [],
		[]
	);
	const relevant = (taxonomies || []).filter(
		(t) => t && t.types && t.types.includes(postType)
	);

	const updateClause = (idx, patch) => {
		const next = [...taxQuery.clauses];
		next[idx] = { ...next[idx], ...patch };
		setAttributes({ taxQuery: { ...taxQuery, clauses: next } });
	};

	const removeClause = (idx) => {
		setAttributes({
			taxQuery: {
				...taxQuery,
				clauses: taxQuery.clauses.filter((_, i) => i !== idx),
			},
		});
	};

	const addClause = () => {
		if (!relevant[0]) {
			return;
		}
		setAttributes({
			taxQuery: {
				...taxQuery,
				clauses: [
					...taxQuery.clauses,
					{ taxonomy: relevant[0].slug, terms: [], operator: 'IN', include_children: true },
				],
			},
		});
	};

	return (
		<DsgoInspectorPanel
			title={__('Taxonomy filters', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() =>
				setAttributes({ taxQuery: { relation: 'AND', clauses: [] } })
			}
		>
			<DsgoInspectorPanel.Item
				label={__('Taxonomy filters', 'designsetgo')}
				hasValue={() => taxQuery.clauses.length > 0}
				onDeselect={() =>
					setAttributes({
						taxQuery: { relation: 'AND', clauses: [] },
					})
				}
				isShownByDefault
			>
				<VStack spacing={3}>
					{taxQuery.clauses.length > 1 && (
						<SelectControl
							label={__('Relation', 'designsetgo')}
							value={taxQuery.relation}
							options={[
								{
									value: 'AND',
									label: __('AND (match all)', 'designsetgo'),
								},
								{
									value: 'OR',
									label: __('OR (match any)', 'designsetgo'),
								},
							]}
							onChange={(v) =>
								setAttributes({
									taxQuery: { ...taxQuery, relation: v },
								})
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					)}

					{taxQuery.clauses.map((clause, idx) => (
						<VStack
							key={idx}
							spacing={2}
							className="dsgo-query-tax-clause"
						>
							<SelectControl
								label={__('Taxonomy', 'designsetgo')}
								value={clause.taxonomy}
								options={relevant.map((t) => ({
									label: t.labels?.singular_name || t.slug,
									value: t.slug,
								}))}
								onChange={(v) =>
									updateClause(idx, {
										taxonomy: v,
										terms: [],
									})
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TermPicker
								taxonomy={clause.taxonomy}
								selected={clause.terms}
								onChange={(ids) =>
									updateClause(idx, { terms: ids })
								}
							/>

							<HStack>
								<SelectControl
									label={__('Operator', 'designsetgo')}
									value={clause.operator || 'IN'}
									options={OPERATORS}
									onChange={(v) =>
										updateClause(idx, { operator: v })
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<Button
									isDestructive
									variant="tertiary"
									onClick={() => removeClause(idx)}
									aria-label={__(
										'Remove taxonomy filter',
										'designsetgo'
									)}
								>
									{__('Remove', 'designsetgo')}
								</Button>
							</HStack>
							<ToggleControl
								label={__('Include child terms', 'designsetgo')}
								checked={clause.include_children ?? true}
								onChange={(val) =>
									updateClause(idx, { include_children: val })
								}
								__nextHasNoMarginBottom
							/>
						</VStack>
					))}

					<Button
						variant="secondary"
						onClick={addClause}
						disabled={relevant.length === 0}
					>
						{__('Add taxonomy filter', 'designsetgo')}
					</Button>
				</VStack>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}

function TermPicker({ taxonomy, selected, onChange }) {
	const terms = useSelect(
		(select) =>
			select(coreStore).getEntityRecords('taxonomy', taxonomy, {
				per_page: -1,
			}) || [],
		[taxonomy]
	);
	const suggestions = (terms || []).map((t) => t.name);
	const selectedNames = (terms || [])
		.filter((t) => selected.includes(t.id))
		.map((t) => t.name);

	return (
		<FormTokenField
			label={__('Terms', 'designsetgo')}
			value={selectedNames}
			suggestions={suggestions}
			onChange={(names) => {
				const ids = (terms || [])
					.filter((t) => names.includes(t.name))
					.map((t) => t.id);
				onChange(ids);
			}}
			__experimentalExpandOnFocus
			__nextHasNoMarginBottom
		/>
	);
}
