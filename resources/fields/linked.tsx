/**
 * WooCommerce's own product fields the product screen has and the list did
 * not: the purchase note (Advanced), upsells and cross-sells (Linked
 * products). Products in a picker by name, stored as ids.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { searchProductNames } from '../api/client';
import type { DataFormControlProps } from '../dataviews';
import type { ProductField, ProductListItem, Settings } from '../types';
import { FormTokenField } from '../ui';
import { field, valueOf } from './helpers';

/** Names of products by id, loaded once per id. */
const names = new Map< number, string >();

async function nameIds( ids: number[] ): Promise< void > {
	const missing = ids.filter( ( id ) => ! names.has( id ) );

	if ( missing.length ) {
		for ( const product of await searchProductNames( '', missing ) ) {
			names.set( product.id, product.name );
		}
	}
}

const label = ( id: number ) => names.get( id ) ?? `#${ id }`;

function ProductsEdit( { data, field: current, onChange }: DataFormControlProps< ProductListItem > ) {
	const ids = ( current.getValue( { item: data } ) as number[] | undefined ) ?? [];
	const [ , setLoaded ] = useState( 0 );
	const [ search, setSearch ] = useState( '' );
	const [ found, setFound ] = useState< Array< { id: number; name: string } > >( [] );

	useEffect( () => {
		void nameIds( ids ).then( () => setLoaded( ( n ) => n + 1 ) );
		// Only the ids matter.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ ids.join( ',' ) ] );

	useEffect( () => {
		let live = true;

		searchProductNames( search )
			.then( ( products ) => {
				products.forEach( ( product ) => names.set( product.id, product.name ) );
				if ( live ) {
					setFound( products.filter( ( product ) => product.id !== data.id ) );
				}
			} )
			.catch( () => live && setFound( [] ) );

		return () => {
			live = false;
		};
	}, [ search, data.id ] );

	return (
		<FormTokenField
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ current.label }
			value={ ids.map( label ) }
			suggestions={ found.map( ( product ) => product.name ) }
			onInputChange={ setSearch }
			__experimentalExpandOnFocus
			__experimentalShowHowTo={ false }
			__experimentalValidateInput={ ( input: string ) => found.some( ( product ) => product.name === input ) || ids.some( ( id ) => label( id ) === input ) }
			onChange={ ( tokens ) => {
				const next = tokens
					.map( ( token ) => ( typeof token === 'string' ? token : token.value ) )
					.map( ( name ) => ids.find( ( id ) => label( id ) === name ) ?? found.find( ( product ) => product.name === name )?.id )
					.filter( ( id ): id is number => typeof id === 'number' );

				onChange( current.setValue( { item: data, value: next } ) );
			} }
		/>
	);
}

function linked( id: 'upsell_ids' | 'cross_sell_ids', labelText: string, order: number ): ProductField {
	return field( {
		id,
		type: 'array',
		label: labelText,
		enableSorting: false,
		enableHiding: true,
		filterBy: false,
		Edit: ProductsEdit,
		render: ( { item } ) => {
			const list = ( valueOf( item, id ) as number[] | undefined ) ?? [];

			return list.length ? list.map( label ).join( ', ' ) : '';
		},
		getValue: ( { item } ) => ( valueOf( item, id ) as number[] | undefined ) ?? [],
		setValue: ( { value } ) => ( { [ id ]: value } ) as Partial< ProductListItem >,
		rest: { write: ( value ) => ( { [ id ]: Array.isArray( value ) ? value.map( Number ).filter( ( n ) => n > 0 ) : [] } ), applies: { product: true, variation: false } },
		edit: { group: 'linked', bulk: 'default', order },
	} );
}

export function createLinkedFields( _settings: Settings ): ProductField[] {
	return [
		linked( 'upsell_ids', __( 'Upsells', 'antijante-products-list' ), 110 ),
		linked( 'cross_sell_ids', __( 'Cross-sells', 'antijante-products-list' ), 111 ),
		field( {
			id: 'purchase_note',
			type: 'text',
			label: __( 'Purchase note', 'antijante-products-list' ),
			description: __( 'Sent to the customer after they buy.', 'antijante-products-list' ),
			enableSorting: false,
			enableHiding: true,
			filterBy: false,
			Edit: { control: 'textarea', rows: 3 },
			getValue: ( { item } ) => ( valueOf( item, 'purchase_note' ) as string | undefined ) ?? '',
			rest: { applies: { product: true, variation: false } },
			edit: { group: 'advanced', bulk: 'default', order: 96 },
		} ),
	];
}
