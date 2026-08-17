/**
 * Chart Block - Data row editor
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	TextControl,
	Button,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';

/**
 * Edit the chart's data rows.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.value    Rows.
 * @param {Function} props.onChange Receives the next rows array.
 * @return {Element} The editor.
 */
export function DataEditor({ value, onChange }) {
	const rows = Array.isArray(value) ? value : [];

	const update = (index, patch) =>
		onChange(
			rows.map((row, i) => (i === index ? { ...row, ...patch } : row))
		);

	return (
		<Flex direction="column" gap={3}>
			{rows.map((row, index) => (
				<Flex
					// The rows have no stable identity of their own — reordering
					// is not offered, and a label may legitimately be blank.
					// eslint-disable-next-line react/no-array-index-key
					key={index}
					align="flex-end"
					gap={2}
				>
					<FlexBlock>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={__('Label', 'designsetgo')}
							value={row.label ?? ''}
							onChange={(label) => update(index, { label })}
						/>
					</FlexBlock>
					<FlexBlock>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							label={__('Value', 'designsetgo')}
							value={row.value ?? 0}
							onChange={(next) => {
								const parsed = parseFloat(next);
								update(index, {
									value: Number.isNaN(parsed) ? 0 : parsed,
								});
							}}
						/>
					</FlexBlock>
					<FlexItem>
						<Button
							isDestructive
							variant="tertiary"
							size="small"
							label={__('Remove row', 'designsetgo')}
							onClick={() =>
								onChange(rows.filter((_, i) => i !== index))
							}
						>
							{__('Remove row', 'designsetgo')}
						</Button>
					</FlexItem>
				</Flex>
			))}

			<Button
				variant="secondary"
				onClick={() => onChange([...rows, { label: '', value: 0 }])}
			>
				{__('Add row', 'designsetgo')}
			</Button>
		</Flex>
	);
}
