/**
 * Interaction Layers - One row in the sidebar list.
 *
 * A summary only. Editing happens in InteractionModal, so this stays legible
 * however many fields an interaction grows.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { trash } from '@wordpress/icons';
import { summariseInteraction, summariseTarget } from '../summarise';

/**
 * Render one interaction as a summary row.
 *
 * @param {Object}   props             Component props.
 * @param {Object}   props.interaction Interaction config.
 * @param {Function} props.onEdit      Opens the detail editor.
 * @param {Function} props.onRemove    Called with no arguments.
 * @return {Element} The row.
 */
export function InteractionRow({ interaction, onEdit, onRemove }) {
	const target = summariseTarget(interaction);

	return (
		<HStack
			spacing={1}
			justify="space-between"
			className="dsgo-interaction-row"
		>
			<Button
				className="dsgo-interaction-row__edit"
				onClick={onEdit}
				label={__('Edit interaction', 'designsetgo')}
				showTooltip
			>
				<span className="dsgo-interaction-row__summary">
					{summariseInteraction(interaction)}
					{target && (
						<span className="dsgo-interaction-row__target">
							{target}
						</span>
					)}
				</span>
			</Button>

			<Button
				icon={trash}
				isDestructive
				size="small"
				onClick={onRemove}
				label={__('Remove interaction', 'designsetgo')}
				showTooltip
			/>
		</HStack>
	);
}
