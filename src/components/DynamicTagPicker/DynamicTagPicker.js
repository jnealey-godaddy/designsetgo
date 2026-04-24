/**
 * DynamicTagPicker — modal-based source + field picker.
 *
 * Consumed by both the Dynamic Image block's inspector and the extension
 * that binds dynamic tags to core/heading, core/paragraph, core/image etc.
 *
 * Public props:
 *  - value:     { source, args } | null
 *  - onChange:  fn({ source, args } | null)
 *  - returns:   return-type filter ('text' | 'image' | 'url' | …)
 *  - postId:    preview post ID (defaults to the current editor post)
 *  - postType:  for field discovery
 *  - title:     modal title
 *  - allowClear: boolean
 *  - isOpen:    boolean (controlled)
 *  - onClose:   fn()
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Modal,
	Button,
	SelectControl,
	TextControl,
	SearchControl,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

import { useDynamicTagSources } from './useDynamicTagSources';
import { useDynamicTagFields } from './useDynamicTagFields';
import { useDynamicTagPreview } from './useDynamicTagPreview';
import './style.scss';

const DEFAULT_RETURNS = [ 'text', 'url', 'image', 'number', 'date' ];

export default function DynamicTagPicker( {
	value,
	onChange,
	returns = DEFAULT_RETURNS,
	postId: postIdProp,
	postType: postTypeProp,
	title = __( 'Dynamic Tag', 'designsetgo' ),
	allowClear = true,
	isOpen,
	onClose,
} ) {
	const editorContext = useSelect( ( select ) => {
		const editor = select( editorStore );
		if ( ! editor ) {
			return {};
		}
		return {
			postId: editor.getCurrentPostId?.(),
			postType: editor.getCurrentPostType?.(),
		};
	}, [] );

	const postId = postIdProp || editorContext.postId;
	const postType = postTypeProp || editorContext.postType || 'post';

	const { status: sourcesStatus, groups, sources, error: sourcesError } = useDynamicTagSources( { returns } );

	const [ selectedSource, setSelectedSource ] = useState( value?.source || '' );
	const [ draftArgs, setDraftArgs ] = useState( value?.args || {} );
	const [ search, setSearch ] = useState( '' );

	useEffect( () => {
		if ( isOpen ) {
			setSelectedSource( value?.source || '' );
			setDraftArgs( value?.args || {} );
			setSearch( '' );
		}
	}, [ isOpen, value?.source, value?.args ] );

	const activeSource = useMemo(
		() => sources.find( ( s ) => s.slug === selectedSource ) || null,
		[ sources, selectedSource ]
	);

	const fieldDiscovery = useDynamicTagFields( {
		source: selectedSource,
		postType,
		returns: Array.isArray( returns ) ? returns[ 0 ] : returns,
		supportsFieldDiscovery: activeSource?.supportsFieldDiscovery || false,
	} );

	const preview = useDynamicTagPreview( {
		source: selectedSource,
		args: draftArgs,
		postId,
		size: draftArgs?.size,
	} );

	const filteredSources = useMemo( () => {
		if ( ! search ) {
			return sources;
		}
		const needle = search.toLowerCase();
		return sources.filter( ( s ) => s.label.toLowerCase().includes( needle ) || s.slug.toLowerCase().includes( needle ) );
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

	if ( ! isOpen ) {
		return null;
	}

	const handleApply = () => {
		if ( ! selectedSource ) {
			return;
		}
		onChange( {
			source: selectedSource,
			args: draftArgs,
		} );
		onClose?.();
	};

	const handleClear = () => {
		onChange( null );
		onClose?.();
	};

	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			className="dsgo-dynamic-tag-picker"
			size="large"
		>
			<div className="dsgo-dynamic-tag-picker__layout">
				<div className="dsgo-dynamic-tag-picker__sidebar">
					<SearchControl
						value={ search }
						onChange={ setSearch }
						placeholder={ __( 'Search sources…', 'designsetgo' ) }
						__nextHasNoMarginBottom
					/>

					{ sourcesStatus === 'loading' && (
						<div className="dsgo-dynamic-tag-picker__loading"><Spinner /></div>
					) }

					{ sourcesStatus === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ __( 'Unable to load Dynamic Tag sources.', 'designsetgo' ) }
						</Notice>
					) }

					{ sourcesStatus === 'ready' && groupedSources.length === 0 && (
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
											onClick={ () => {
												setSelectedSource( source.slug );
												setDraftArgs( {} );
											} }
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

				<div className="dsgo-dynamic-tag-picker__detail">
					{ ! activeSource && (
						<p className="dsgo-dynamic-tag-picker__prompt">
							{ __( 'Pick a source on the left to continue.', 'designsetgo' ) }
						</p>
					) }

					{ activeSource && (
						<VStack spacing={ 4 }>
							<header>
								<h2 className="dsgo-dynamic-tag-picker__title">{ activeSource.label }</h2>
								<p className="dsgo-dynamic-tag-picker__subtitle">
									<code>{ activeSource.slug }</code>
								</p>
							</header>

							<SourceArgsForm
								source={ activeSource }
								args={ draftArgs }
								onChange={ setDraftArgs }
								fieldDiscovery={ fieldDiscovery }
							/>

							<PreviewPanel preview={ preview } returns={ activeSource.returns } />
						</VStack>
					) }
				</div>
			</div>

			<HStack justify="flex-end" className="dsgo-dynamic-tag-picker__footer">
				{ allowClear && value && (
					<Button variant="tertiary" isDestructive onClick={ handleClear }>
						{ __( 'Remove binding', 'designsetgo' ) }
					</Button>
				) }
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'designsetgo' ) }
				</Button>
				<Button variant="primary" onClick={ handleApply } disabled={ ! selectedSource }>
					{ __( 'Use this source', 'designsetgo' ) }
				</Button>
			</HStack>
		</Modal>
	);
}

function SourceArgsForm( { source, args, onChange, fieldDiscovery } ) {
	const schema = source.args || {};
	const entries = Object.entries( schema );

	if ( entries.length === 0 ) {
		return null;
	}

	const setArg = ( key, value ) => {
		const next = { ...args };
		if ( value === '' || value === undefined || value === null ) {
			delete next[ key ];
		} else {
			next[ key ] = value;
		}
		onChange( next );
	};

	return (
		<VStack spacing={ 3 }>
			{ entries.map( ( [ argName, argSchema ] ) => {
				// ACF/meta "key" field — surface discovered fields as a select when available.
				if ( argName === 'key' && source.supportsFieldDiscovery ) {
					const fieldOptions = [
						{ label: __( '— Select a field —', 'designsetgo' ), value: '' },
						...fieldDiscovery.fields.map( ( f ) => ( {
							label: f.group ? `${ f.group } — ${ f.label }` : f.label,
							value: f.key,
						} ) ),
					];
					return (
						<div key={ argName }>
							{ fieldDiscovery.status === 'loading' ? (
								<Spinner />
							) : (
								<SelectControl
									label={ __( 'Field', 'designsetgo' ) }
									value={ args[ argName ] || '' }
									options={ fieldOptions }
									onChange={ ( value ) => setArg( argName, value ) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							) }
							<TextControl
								label={ __( 'Or enter a field key manually', 'designsetgo' ) }
								value={ args[ argName ] || '' }
								onChange={ ( value ) => setArg( argName, value ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>
					);
				}

				if ( Array.isArray( argSchema.enum ) && argSchema.enum.length > 0 ) {
					return (
						<SelectControl
							key={ argName }
							label={ argLabel( argName ) }
							value={ args[ argName ] ?? argSchema.default ?? '' }
							options={ [
								{ label: __( '— Default —', 'designsetgo' ), value: '' },
								...argSchema.enum.map( ( v ) => ( { label: v, value: v } ) ),
							] }
							onChange={ ( value ) => setArg( argName, value ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					);
				}

				return (
					<TextControl
						key={ argName }
						label={ argLabel( argName ) }
						help={ argSchema.description || '' }
						value={ args[ argName ] ?? '' }
						onChange={ ( value ) => setArg( argName, value ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				);
			} ) }
		</VStack>
	);
}

function argLabel( name ) {
	const map = {
		key: __( 'Field key', 'designsetgo' ),
		subkey: __( 'Sub-value', 'designsetgo' ),
		size: __( 'Image size', 'designsetgo' ),
		scope: __( 'Scope', 'designsetgo' ),
		format: __( 'Date format', 'designsetgo' ),
		taxonomy: __( 'Taxonomy', 'designsetgo' ),
		separator: __( 'Separator', 'designsetgo' ),
	};
	return map[ name ] || name;
}

function PreviewPanel( { preview, returns = [] } ) {
	const isImage = returns.includes( 'image' );

	if ( preview.status === 'idle' ) {
		return null;
	}

	if ( preview.status === 'loading' ) {
		return (
			<div className="dsgo-dynamic-tag-picker__preview">
				<Spinner />
			</div>
		);
	}

	if ( preview.status === 'empty' ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __( 'Preview is empty for the current post.', 'designsetgo' ) }
			</Notice>
		);
	}

	if ( preview.status === 'unauthorized' ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __( 'Preview is hidden because the post is password-protected or private.', 'designsetgo' ) }
			</Notice>
		);
	}

	if ( preview.status !== 'resolved' ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Unable to preview this source.', 'designsetgo' ) }
			</Notice>
		);
	}

	if ( isImage && preview.value && typeof preview.value === 'object' ) {
		return (
			<div className="dsgo-dynamic-tag-picker__preview">
				<h4>{ __( 'Preview', 'designsetgo' ) }</h4>
				<img src={ preview.value.url } alt={ preview.value.alt || '' } />
				<p className="dsgo-dynamic-tag-picker__preview-meta">
					{ sprintf(
						/* translators: %1$s image width, %2$s image height */
						__( '%1$s × %2$s', 'designsetgo' ),
						preview.value.width || '?',
						preview.value.height || '?'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="dsgo-dynamic-tag-picker__preview">
			<h4>{ __( 'Preview', 'designsetgo' ) }</h4>
			<p className="dsgo-dynamic-tag-picker__preview-value">{ String( preview.value ) }</p>
		</div>
	);
}
