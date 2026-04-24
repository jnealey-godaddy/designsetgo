/* eslint-disable @wordpress/no-unsafe-wp-apis -- experimental layout/control primitives intentionally used; stable replacements not yet available */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared';
import ClauseGroupShell from './ClauseGroupShell';

const COMPARE_OPTIONS = [
	{ value: '=', label: '=' },
	{ value: '!=', label: '!=' },
	{ value: '>', label: '>' },
	{ value: '>=', label: '>=' },
	{ value: '<', label: '<' },
	{ value: '<=', label: '<=' },
	{ value: 'LIKE', label: 'LIKE' },
	{ value: 'NOT LIKE', label: 'NOT LIKE' },
	{ value: 'IN', label: 'IN' },
	{ value: 'NOT IN', label: 'NOT IN' },
	{ value: 'EXISTS', label: 'EXISTS' },
	{ value: 'NOT EXISTS', label: 'NOT EXISTS' },
];

const TYPE_OPTIONS = [
	{ value: 'CHAR', label: __('Text', 'designsetgo') },
	{ value: 'NUMERIC', label: __('Numeric', 'designsetgo') },
	{ value: 'DATE', label: __('Date', 'designsetgo') },
];

const EMPTY_DEFAULT = { relation: 'AND', clauses: [] };
const NEW_CLAUSE = { key: '', compare: '=', value: '', type: 'CHAR' };

export default function MetaQueryBuilder({
	attributes,
	setAttributes,
	clientId,
}) {
	const rawMetaQuery = attributes.metaQuery ?? EMPTY_DEFAULT;
	const metaQuery = {
		relation: rawMetaQuery.relation ?? 'AND',
		clauses: Array.isArray(rawMetaQuery.clauses)
			? rawMetaQuery.clauses
			: [],
	};

	const renderClause = (clause, idx, updateEntry, removeEntry) => {
		const hideValue =
			clause.compare === 'EXISTS' || clause.compare === 'NOT EXISTS';
		return (
			<VStack key={idx} spacing={2}>
				<TextControl
					label={__('Key', 'designsetgo')}
					value={clause.key}
					onChange={(v) => updateEntry(idx, { key: v })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<HStack>
					<SelectControl
						label={__('Compare', 'designsetgo')}
						value={clause.compare}
						options={COMPARE_OPTIONS}
						onChange={(v) => updateEntry(idx, { compare: v })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={__('Type', 'designsetgo')}
						value={clause.type}
						options={TYPE_OPTIONS}
						onChange={(v) => updateEntry(idx, { type: v })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</HStack>
				{!hideValue && (
					<TextControl
						label={__('Value', 'designsetgo')}
						value={clause.value}
						onChange={(v) => updateEntry(idx, { value: v })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				)}
				<Button
					isDestructive
					variant="tertiary"
					onClick={() => removeEntry(idx)}
					aria-label={sprintf(
						/* translators: %s: meta key being removed, or "(empty)" when blank. */
						__('Remove meta filter for "%s"', 'designsetgo'),
						clause.key || __('(empty)', 'designsetgo')
					)}
					__next40pxDefaultSize
				>
					{__('Remove', 'designsetgo')}
				</Button>
			</VStack>
		);
	};

	return (
		<DsgoInspectorPanel
			title={__('Meta filters', 'designsetgo')}
			panelName="settings"
			panelId={clientId}
			resetAll={() => setAttributes({ metaQuery: EMPTY_DEFAULT })}
		>
			<DsgoInspectorPanel.Item
				label={__('Meta query', 'designsetgo')}
				hasValue={() => metaQuery.clauses.length > 0}
				onDeselect={() => setAttributes({ metaQuery: EMPTY_DEFAULT })}
				isShownByDefault
			>
				<ClauseGroupShell
					group={metaQuery}
					onChange={(patch) =>
						setAttributes({ metaQuery: { ...metaQuery, ...patch } })
					}
					depth={0}
					newClause={NEW_CLAUSE}
					renderClause={renderClause}
				/>
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
}
