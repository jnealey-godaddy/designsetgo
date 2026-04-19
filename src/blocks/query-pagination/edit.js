import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';

const DEFAULTS = {
	mode: 'numbered',
	labelLoadMore: '',
	labelLoading: '',
	showPrevNext: true,
};

export default function QueryPaginationEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { mode, labelLoadMore, labelLoading, showPrevNext } = attributes;
	const blockProps = useBlockProps( {
		className: 'dsgo-query-pagination is-editor',
	} );

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={ __( 'Settings', 'designsetgo' ) }
					panelName="settings"
					panelId={ clientId }
					resetAll={ () => setAttributes( DEFAULTS ) }
				>
					<DsgoInspectorPanel.Item
						label={ __( 'Mode', 'designsetgo' ) }
						hasValue={ () => mode !== 'numbered' }
						onDeselect={ () =>
							setAttributes( { mode: 'numbered' } )
						}
						isShownByDefault
					>
						<SelectControl
							label={ __( 'Mode', 'designsetgo' ) }
							value={ mode }
							options={ [
								{
									value: 'numbered',
									label: __( 'Numbered', 'designsetgo' ),
								},
								{
									value: 'loadmore',
									label: __( 'Load more', 'designsetgo' ),
								},
							] }
							onChange={ ( v ) =>
								setAttributes( { mode: v } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{ mode === 'numbered' && (
						<DsgoInspectorPanel.Item
							label={ __(
								'Show prev/next arrows',
								'designsetgo'
							) }
							hasValue={ () => showPrevNext !== true }
							onDeselect={ () =>
								setAttributes( { showPrevNext: true } )
							}
						>
							<ToggleControl
								label={ __(
									'Show prev/next arrows',
									'designsetgo'
								) }
								checked={ !! showPrevNext }
								onChange={ ( v ) =>
									setAttributes( { showPrevNext: !! v } )
								}
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					) }

					{ mode === 'loadmore' && (
						<>
							<DsgoInspectorPanel.Item
								label={ __(
									'Load more label',
									'designsetgo'
								) }
								hasValue={ () => labelLoadMore !== '' }
								onDeselect={ () =>
									setAttributes( { labelLoadMore: '' } )
								}
								isShownByDefault
							>
								<TextControl
									label={ __(
										'Load more button label',
										'designsetgo'
									) }
									value={ labelLoadMore }
									onChange={ ( v ) =>
										setAttributes( {
											labelLoadMore: v,
										} )
									}
									placeholder={ __(
										'Load more',
										'designsetgo'
									) }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
							<DsgoInspectorPanel.Item
								label={ __(
									'Loading label',
									'designsetgo'
								) }
								hasValue={ () => labelLoading !== '' }
								onDeselect={ () =>
									setAttributes( { labelLoading: '' } )
								}
							>
								<TextControl
									label={ __(
										'Loading state label',
										'designsetgo'
									) }
									value={ labelLoading }
									onChange={ ( v ) =>
										setAttributes( {
											labelLoading: v,
										} )
									}
									placeholder={ __(
										'Loading\u2026',
										'designsetgo'
									) }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
						</>
					) }
				</DsgoInspectorPanel>
			</InspectorControls>

			<div { ...blockProps }>
				{ mode === 'numbered' ? (
					<span className="dsgo-query-pagination__preview">
						{ showPrevNext ? '\u2190 ' : '' }1 2 3
						{ showPrevNext ? ' \u2192' : '' }
					</span>
				) : (
					<button
						type="button"
						className="dsgo-query-pagination__loadmore"
						disabled
					>
						{ labelLoadMore ||
							__( 'Load more', 'designsetgo' ) }
					</button>
				) }
			</div>
		</>
	);
}
