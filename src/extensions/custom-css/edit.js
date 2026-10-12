/**
 * Custom CSS Extension - Editor Controls
 *
 * Inspector panel for custom CSS. Lazy-loaded to reduce initial bundle size.
 * Editor style injection lives in styles.js, which is not lazy.
 *
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	TabPanel,
	TextareaControl,
} from '@wordpress/components';
import {
	breakpointWidth,
	joinResponsiveCSS,
	splitResponsiveCSS,
} from './responsive';

const DEVICE_TABS = [
	{ name: 'desktop', title: __('Desktop', 'designsetgo') },
	{ name: 'tablet', title: __('Tablet', 'designsetgo') },
	{ name: 'mobile', title: __('Mobile', 'designsetgo') },
];

/**
 * Help text for one screen size's CSS field.
 *
 * @param {string} device `desktop`, `tablet` or `mobile`.
 * @return {string} Help text.
 */
function deviceHelp(device) {
	if ('desktop' === device) {
		return __(
			'Applies at every screen size. Use "selector" to target this block, and nested selectors like "selector h3" to target elements inside.',
			'designsetgo'
		);
	}
	return sprintf(
		/* translators: %d: maximum screen width in pixels. */
		__(
			'Applies at %dpx wide and below, after the Desktop CSS. Use "selector" to target this block.',
			'designsetgo'
		),
		breakpointWidth(device)
	);
}

/**
 * Says which other screen sizes already have CSS, since only one tab's
 * field is visible at a time.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.parts   CSS per device.
 * @param {string} props.current Device whose tab is open.
 * @return {JSX.Element|null} Note, or null when no other tab has CSS.
 */
function OtherDevicesNote({ parts, current }) {
	const others = DEVICE_TABS.filter(
		(tab) => tab.name !== current && parts[tab.name].trim()
	).map((tab) => tab.title);
	if (!others.length) {
		return null;
	}
	return (
		<p className="components-base-control__help">
			{sprintf(
				/* translators: %s: list of screen sizes, e.g. "Tablet, Mobile". */
				__('Also has CSS for: %s', 'designsetgo'),
				others.join(', ')
			)}
		</p>
	);
}

/**
 * Custom CSS inspector controls panel
 *
 * @param {Object} props Block props
 */
export function CustomCSSPanel(props) {
	const { attributes, setAttributes } = props;
	const { dsgoCustomCSS } = attributes;
	const parts = splitResponsiveCSS(dsgoCustomCSS);
	// Mirrors the server's edit_css gate (Custom_CSS_Kses), which strips this
	// attribute on save for anyone without it. wp_localize_script() sends the
	// flag as "1" or "", never a boolean, so test truthiness. Absent means an
	// older payload: fall back to editable rather than locking out the people
	// who can.
	const canEditFlag = window.dsgoSettings?.canEditCustomCSS;
	const canEdit = undefined === canEditFlag || !!canEditFlag;

	return (
		<InspectorControls>
			<PanelBody
				title={__('Custom CSS', 'designsetgo')}
				initialOpen={false}
			>
				{!canEdit && (
					<Notice status="warning" isDismissible={false}>
						{__(
							'Your role cannot add custom CSS. Any CSS on this block is removed when you save. Ask an administrator to make CSS changes.',
							'designsetgo'
						)}
					</Notice>
				)}
				<TabPanel className="dsgo-custom-css-tabs" tabs={DEVICE_TABS}>
					{(tab) => (
						<>
							<TextareaControl
								label={__('CSS Code', 'designsetgo')}
								value={parts[tab.name]}
								onChange={(value) =>
									setAttributes({
										dsgoCustomCSS: joinResponsiveCSS({
											...parts,
											[tab.name]: value || '',
										}),
									})
								}
								readOnly={!canEdit}
								placeholder={
									'desktop' === tab.name
										? `selector {\n  background: linear-gradient(45deg, #f00, #00f);\n  padding: 2rem;\n}\n\nselector h3 {\n  color: white;\n  font-size: 2rem;\n}`
										: `selector {\n  padding: 1rem;\n}`
								}
								rows={15}
								help={deviceHelp(tab.name)}
								className="dsgo-custom-css-textarea"
								style={{
									fontFamily: 'monospace',
									fontSize: '13px',
									lineHeight: '1.6',
								}}
							/>
							<OtherDevicesNote
								parts={parts}
								current={tab.name}
							/>
						</>
					)}
				</TabPanel>
				<p className="components-base-control__help">
					<strong>{__('Examples:', 'designsetgo')}</strong>
					<br />
					<code>
						selector {'{'} background: red; {'}'}
					</code>{' '}
					- Style this block
					<br />
					<code>
						selector h3 {'{'} color: white; {'}'}
					</code>{' '}
					- Style H3s inside
					<br />
					<code>
						selector:hover {'{'} opacity: 0.8; {'}'}
					</code>{' '}
					- Hover effect
				</p>
			</PanelBody>
		</InspectorControls>
	);
}
