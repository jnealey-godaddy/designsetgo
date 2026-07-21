import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalVStack as VStack,
} from '@wordpress/components';

/**
 * Reusable recursive group shell for tax/meta clause builders.
 *
 * Props:
 *   group        - { relation, clauses }
 *   onChange     - (patch) => void — called with { relation?, clauses? }
 *   onRemove     - () => void | undefined — present on nested groups, absent on root
 *   depth        - number (0 = root)
 *   renderClause - (clause, idx, updateEntry, removeEntry) => JSX — renders one leaf clause
 *   newClause    - object — default shape for a new leaf clause
 * @param {Object}   root0
 * @param {Object}   root0.group
 * @param {Function} root0.onChange
 * @param {Function} [root0.onRemove]
 * @param {number}   root0.depth
 * @param {Function} root0.renderClause
 * @param {Object}   root0.newClause
 * @param {boolean}  [root0.isAddDisabled]
 */
export default function ClauseGroupShell({
	group,
	onChange,
	onRemove,
	depth = 0,
	renderClause,
	newClause,
	isAddDisabled,
}) {
	const { relation = 'AND', clauses = [] } = group;

	const updateEntry = (idx, patch) => {
		const next = clauses.map((c, i) =>
			i === idx ? { ...c, ...patch } : c
		);
		onChange({ clauses: next });
	};

	const replaceEntry = (idx, entry) => {
		const next = clauses.map((c, i) => (i === idx ? entry : c));
		onChange({ clauses: next });
	};

	const removeEntry = (idx) =>
		onChange({ clauses: clauses.filter((_, i) => i !== idx) });

	const addClause = () =>
		onChange({ clauses: [...clauses, { ...newClause }] });

	const addGroup = () =>
		onChange({
			clauses: [
				...clauses,
				{ relation: 'AND', clauses: [{ ...newClause }] },
			],
		});

	const groupLabel =
		depth === 0
			? __('top level', 'designsetgo')
			: sprintf(
					/* translators: %d: nesting depth, where 1 is the first nested group. */
					__('nested group level %d', 'designsetgo'),
					depth
				);

	return (
		<VStack
			spacing={2}
			className={`dsgo-clause-group dsgo-clause-group--depth-${depth}`}
			role="group"
			aria-label={sprintf(
				/* translators: %s: human label for the group's nesting depth. */
				__('Filter clauses, %s', 'designsetgo'),
				groupLabel
			)}
			style={
				depth > 0
					? {
							paddingLeft: '12px',
							borderLeft:
								'2px solid var(--wp-admin-theme-color-darker-10, #ccc)',
						}
					: undefined
			}
		>
			{(clauses.length > 1 || depth > 0) && (
				<SelectControl
					label={__('Match', 'designsetgo')}
					value={relation}
					options={[
						{ label: __('All (AND)', 'designsetgo'), value: 'AND' },
						{ label: __('Any (OR)', 'designsetgo'), value: 'OR' },
					]}
					onChange={(val) => onChange({ relation: val })}
					__nextHasNoMarginBottom
				/>
			)}

			{clauses.map((entry, idx) =>
				Array.isArray(entry.clauses) ? (
					<ClauseGroupShell
						key={idx}
						group={entry}
						onChange={(patch) =>
							replaceEntry(idx, { ...entry, ...patch })
						}
						onRemove={() => removeEntry(idx)}
						depth={depth + 1}
						renderClause={renderClause}
						newClause={newClause}
						isAddDisabled={isAddDisabled}
					/>
				) : (
					renderClause(entry, idx, updateEntry, removeEntry)
				)
			)}

			<div className="dsgo-clause-group__actions">
				<Button
					variant="secondary"
					size="small"
					onClick={addClause}
					disabled={isAddDisabled}
					aria-label={sprintf(
						/* translators: %s: human label for the group's nesting depth. */
						__('Add clause to %s', 'designsetgo'),
						groupLabel
					)}
					__next40pxDefaultSize
				>
					{__('+ Clause', 'designsetgo')}
				</Button>
				<Button
					variant="secondary"
					size="small"
					onClick={addGroup}
					disabled={isAddDisabled}
					aria-label={sprintf(
						/* translators: %s: human label for the group's nesting depth. */
						__('Add group inside %s', 'designsetgo'),
						groupLabel
					)}
					__next40pxDefaultSize
				>
					{__('+ Group', 'designsetgo')}
				</Button>
				{onRemove && (
					<Button
						variant="tertiary"
						isDestructive
						size="small"
						onClick={onRemove}
						aria-label={sprintf(
							/* translators: %s: human label for the group's nesting depth. */
							__('Remove %s', 'designsetgo'),
							groupLabel
						)}
						__next40pxDefaultSize
					>
						{__('Remove group', 'designsetgo')}
					</Button>
				)}
			</div>
		</VStack>
	);
}
