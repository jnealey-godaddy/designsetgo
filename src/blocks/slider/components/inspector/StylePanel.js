/**
 * Slider — Style panel.
 *
 * Visual appearance: height/aspect ratio, gap, and arrow/dot appearance.
 * Whether navigation shows at all lives in SettingsPanel.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { DsgoInspectorPanel } from '../../../../components/shared';
import SizingFields from './SizingFields';
import ArrowAppearanceFields from './ArrowAppearanceFields';
import DotAppearanceFields from './DotAppearanceFields';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {string}   props.clientId      Block client id.
 * @return {JSX.Element} Style panel.
 */
export default function StylePanel({ attributes, setAttributes, clientId }) {
	return (
		<InspectorControls>
			<DsgoInspectorPanel
				title={__('Style', 'designsetgo')}
				panelName="style"
				panelId={clientId}
				resetAll={() =>
					setAttributes({
						useAspectRatio: false,
						aspectRatio: '16/9',
						height: '',
						gap: '20px',
						arrowStyle: 'default',
						arrowPosition: 'sides',
						arrowVerticalPosition: 'center',
						arrowSize: '24px',
						arrowPadding: '',
						dotStyle: 'default',
						dotPosition: 'inside',
					})
				}
			>
				<SizingFields
					attributes={attributes}
					setAttributes={setAttributes}
				/>

				<ArrowAppearanceFields
					attributes={attributes}
					setAttributes={setAttributes}
				/>

				<DotAppearanceFields
					attributes={attributes}
					setAttributes={setAttributes}
				/>
			</DsgoInspectorPanel>
		</InspectorControls>
	);
}
