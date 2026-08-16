/**
 * Interaction Layers - Panel
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export in @wordpress/components
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { DEFAULT_INTERACTION } from '../constants';
import { InteractionRow } from './InteractionRow';

/**
 * Generate a short id that is stable across saves.
 *
 * Math.random is fine here: ids only need to be unique within one block's
 * interaction list, never globally or cryptographically.
 *
 * @return {string} Eight hex characters.
 */
function makeId() {
	return Math.random().toString(16).slice(2, 10);
}

/**
 * Render the interactions list.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.value    Current interactions.
 * @param {Function} props.onChange Receives the next array.
 * @return {Element} The panel.
 */
export function InteractionsPanel({ value, onChange }) {
	const interactions = Array.isArray(value) ? value : [];

	// Only one row is expanded at a time; a freshly added one opens itself
	// so the author lands directly on the controls they need to fill in.
	const [openId, setOpenId] = useState(null);

	const add = () => {
		const id = makeId();
		setOpenId(id);
		onChange([...interactions, { ...DEFAULT_INTERACTION, id }]);
	};

	const update = (id) => (next) =>
		onChange(interactions.map((i) => (i.id === id ? next : i)));

	const remove = (id) => () => {
		if (openId === id) {
			setOpenId(null);
		}
		onChange(interactions.filter((i) => i.id !== id));
	};

	const toggle = (id) => () => setOpenId(openId === id ? null : id);

	return (
		<VStack spacing={3} className="dsgo-interactions-panel">
			{0 === interactions.length && (
				<p className="dsgo-interactions-panel__empty">
					{__(
						'Make this block do something when a visitor clicks it, hovers it, or scrolls it into view.',
						'designsetgo'
					)}
				</p>
			)}

			{interactions.map((interaction) => (
				<InteractionRow
					key={interaction.id}
					interaction={interaction}
					isOpen={openId === interaction.id}
					onToggle={toggle(interaction.id)}
					onChange={update(interaction.id)}
					onRemove={remove(interaction.id)}
				/>
			))}

			<Button variant="secondary" onClick={add}>
				{__('Add interaction', 'designsetgo')}
			</Button>
		</VStack>
	);
}
