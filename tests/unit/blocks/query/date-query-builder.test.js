import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	sprintf: ( fmt, ...args ) => {
		let i = 0;
		return fmt.replace( /%[sd]/g, () => String( args[ i++ ] ?? '' ) );
	},
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: () => undefined,
	useDispatch: () => ( {} ),
} ) );

jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( {} ),
	useInnerBlocksProps: ( p = {} ) => ( { ...p, children: null } ),
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	store: 'core/block-editor',
} ) );

jest.mock( '@wordpress/blocks', () => ( {
	createBlocksFromInnerBlocksTemplate: jest.fn( ( t ) => t ),
} ) );

jest.mock( '@wordpress/components', () => {
	const SelectControl = ( { label, value, options, onChange } ) => (
		<label>
			{ label }
			<select
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
				aria-label={ label }
			>
				{ ( options || [] ).map( ( opt ) => (
					<option key={ opt.value } value={ opt.value }>
						{ opt.label }
					</option>
				) ) }
			</select>
		</label>
	);

	const TextControl = ( { label, value, onChange } ) => (
		<label>
			{ label }
			<input
				type="text"
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
				aria-label={ label }
			/>
		</label>
	);

	const Button = ( {
		children,
		onClick,
		disabled,
		'aria-label': ariaLabel,
		isDestructive,
		variant,
	} ) => (
		<button
			type="button"
			onClick={ onClick }
			disabled={ disabled }
			aria-label={ ariaLabel }
			data-destructive={ isDestructive ? 'true' : undefined }
			data-variant={ variant }
		>
			{ children }
		</button>
	);

	const ToggleControl = ( { label, checked, onChange } ) => (
		<label>
			{ label }
			<input
				type="checkbox"
				aria-label={ label }
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
		</label>
	);

	// ToolsPanelItem: isShownByDefault=false items are hidden.
	const ToolsPanelItem = ( { children, isShownByDefault } ) =>
		isShownByDefault !== false ? <>{ children }</> : null;

	const ToolsPanel = ( { children, label } ) => (
		<fieldset aria-label={ label }>{ children }</fieldset>
	);

	const VStack = ( { children } ) => <div>{ children }</div>;
	const HStack = ( { children } ) => <div>{ children }</div>;

	return {
		SelectControl,
		TextControl,
		Button,
		ToggleControl,
		__experimentalToolsPanel: ToolsPanel,
		__experimentalToolsPanelItem: ToolsPanelItem,
		__experimentalHStack: HStack,
		__experimentalVStack: VStack,
	};
} );

jest.mock( '@wordpress/element', () => jest.requireActual( '@wordpress/element' ) );

// ─── Component under test ─────────────────────────────────────────────────────
import DateQueryBuilder from '../../../../src/blocks/query/components/DateQueryBuilder';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const defaultAttrs = {
	dateQuery: { relation: 'AND', clauses: [] },
};

describe( 'DateQueryBuilder', () => {
	it( 'renders empty state with an Add button', () => {
		render(
			<DateQueryBuilder
				attributes={ defaultAttrs }
				setAttributes={ jest.fn() }
				clientId="test"
			/>
		);
		expect(
			screen.getByRole( 'button', { name: /add date clause/i } )
		).toBeInTheDocument();
	} );

	it( 'seeds new clause with post_date / after / inclusive:true defaults', () => {
		const setAttributes = jest.fn();
		render(
			<DateQueryBuilder
				attributes={ defaultAttrs }
				setAttributes={ setAttributes }
				clientId="test"
			/>
		);
		fireEvent.click( screen.getByRole( 'button', { name: /add date clause/i } ) );
		expect( setAttributes ).toHaveBeenCalledWith(
			expect.objectContaining( {
				dateQuery: expect.objectContaining( {
					clauses: [
						expect.objectContaining( {
							column: 'post_date',
							mode: 'after',
							inclusive: true,
						} ),
					],
				} ),
			} )
		);
	} );
} );
