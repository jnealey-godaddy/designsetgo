/**
 * Form Builder — "Conditional logic" item for a field's Settings panel.
 *
 * @since 2.10.0
 */

import { __ } from '@wordpress/i18n';
import { DsgoInspectorPanel } from '../../../components/shared';
import FieldConditionsControl from './FieldConditionsControl';
import useFormFields from '../utils/use-form-fields';
import { hasActiveRules } from '../conditions';

export default function FieldConditionsItem({
	attributes,
	setAttributes,
	clientId,
}) {
	const fields = useFormFields(clientId);
	return (
		<DsgoInspectorPanel.Item
			label={__('Conditional logic', 'designsetgo')}
			hasValue={() => hasActiveRules(attributes.dsgoConditions)}
			onDeselect={() => setAttributes({ dsgoConditions: null })}
			isShownByDefault
		>
			<FieldConditionsControl
				value={attributes.dsgoConditions}
				onChange={(dsgoConditions) => setAttributes({ dsgoConditions })}
				fields={fields}
				currentName={attributes.fieldName}
			/>
		</DsgoInspectorPanel.Item>
	);
}
