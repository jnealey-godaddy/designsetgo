/**
 * SourceSidebar — searchable grouped list of Dynamic Tag sources.
 */
import { useMemo } from '@wordpress/element';
import {
	Button,
	SearchControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SourceSidebar( {
	status,
	groups,
	sources,
	search,
	onSearchChange,
	selectedSource,
	onSelectSource,
} ) {
	const filteredSources = useMemo( () => {
		if ( ! search ) {
			return sources;
		}
		const needle = search.toLowerCase();
		return sources.filter(
			( s ) => s.label.toLowerCase().includes( needle ) || s.slug.toLowerCase().includes( needle )
		);
	}, [ sources, search ] );

	const groupedSources = useMemo( () => {
		const bucket = {};
		filteredSources.forEach( ( s ) => {
			( bucket[ s.group ] = bucket[ s.group ] || [] ).push( s );
		} );
		return groups
			.filter( ( g ) => bucket[ g.slug ]?.length )
			.map( ( g ) => ( { ...g, sources: bucket[ g.slug ] } ) );
	}, [ filteredSources, groups ] );

	return (
		<div className="dsgo-dynamic-tag-picker__sidebar">
			<SearchControl
				value={ search }
				onChange={ onSearchChange }
				placeholder={ __( 'Search sources…', 'designsetgo' ) }
				__nextHasNoMarginBottom
			/>

			{ status === 'loading' && (
				<div className="dsgo-dynamic-tag-picker__loading"><Spinner /></div>
			) }

			{ status === 'error' && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'Unable to load Dynamic Tag sources.', 'designsetgo' ) }
				</Notice>
			) }

			{ status === 'ready' && groupedSources.length === 0 && (
				<p className="dsgo-dynamic-tag-picker__empty">
					{ __( 'No sources match.', 'designsetgo' ) }
				</p>
			) }

			{ groupedSources.map( ( group ) => (
				<div key={ group.slug } className="dsgo-dynamic-tag-picker__group">
					<h3 className="dsgo-dynamic-tag-picker__group-title">{ group.label }</h3>
					<ul className="dsgo-dynamic-tag-picker__source-list">
						{ group.sources.map( ( source ) => (
							<li key={ source.slug }>
								<Button
									variant={ source.slug === selectedSource ? 'primary' : 'tertiary' }
									onClick={ () => onSelectSource( source.slug ) }
									className="dsgo-dynamic-tag-picker__source-button"
								>
									{ source.label }
								</Button>
							</li>
						) ) }
					</ul>
				</div>
			) ) }
		</div>
	);
}
