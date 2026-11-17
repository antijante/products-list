import { describe, expect, it } from 'vitest';
import { claimFields, scopeDetail, scopeState, scopesOf } from '../../resources/edit/scopes';
import { sectionsOfTab } from '../../resources/edit/form-layouts';
import type { LayoutScope, ProductField, ProductListItem, QuickEditLayout, QuickEditTab } from '../../resources/types';

const scope = ( id: string, tabs: string[], extra: Partial< LayoutScope > = {} ): LayoutScope => ( {
	id,
	label: id,
	detail: '',
	badge: '',
	heading: '',
	intro: '',
	link: null,
	tabs,
	missing: false,
	missingFallback: '',
	...extra,
} );

const field = ( id: string, tab: string, reference?: string ): ProductField =>
	( {
		id,
		label: id,
		edit: { group: tab, tab },
		...( reference !== undefined ? { reference: () => reference } : {} ),
	} ) as unknown as ProductField;

const tab = ( id: string, extra: Partial< QuickEditTab > = {} ): QuickEditTab => ( { id, label: id, ...extra } );

describe( 'declared layout scopes', () => {
	const layout: QuickEditLayout = {
		scopes: [ scope( 'all', [ 'general', 'specs' ] ), scope( 'market-sv', [ 'i18n:sv' ], { missing: true, detail: 'SEK' } ) ],
		tabs: [],
	};

	it( 'puts each tab in the scope naming it, and an unnamed one in the first', () => {
		const scopes = scopesOf( [ tab( 'general' ), tab( 'specs' ), tab( 'i18n:sv' ), tab( 'extra' ) ], { layout } );

		expect( scopes.map( ( entry ) => [ entry.scope.id, entry.tabs.map( ( t ) => t.id ) ] ) ).toEqual( [
			[ 'all', [ 'general', 'specs', 'extra' ] ],
			[ 'market-sv', [ 'i18n:sv' ] ],
		] );
		expect( scopesOf( [ tab( 'general' ) ], { layout: { scopes: [], tabs: [] } } ) ).toEqual( [] );
	} );

	it( 'moves the fields a declared tab takes there', () => {
		const fields = claimFields( [ field( 'name', 'general' ), field( 'sku', 'general' ) ], { layout: { scopes: [], tabs: [ { id: 'default-market', label: null, order: null, fields: [ 'name' ], hide: [], sections: [] } ] } } );

		expect( fields.map( ( f ) => ( f.edit ? f.edit.tab : null ) ) ).toEqual( [ 'default-market', 'general' ] );
	} );

	it( 'counts the empty texts that have a default, from what is typed', () => {
		const fields = [ field( 'i18n:sv.name', 'i18n:sv', 'Aluex 10' ), field( 'i18n:sv.slug', 'i18n:sv', 'aluex-10' ), field( 'i18n:sv.meta_title', 'i18n:sv', '' ) ];
		const [ , market ] = scopesOf( [ tab( 'general' ), tab( 'i18n:sv' ) ], { layout } );
		const item = { id: 1 } as ProductListItem;

		expect( scopeDetail( market!, scopeState( market!, fields, {}, item ) ) ).toBe( 'SEK, not translated' );
		expect( scopeDetail( market!, scopeState( market!, fields, { 'i18n:sv.name': 'Aluex 10 sv' }, item ) ) ).toBe( 'SEK, 1 text missing' );
		expect( scopeDetail( market!, scopeState( market!, fields, { 'i18n:sv.name': 'x', 'i18n:sv.slug': '<p> </p>y' }, item ) ) ).toBe( 'SEK, complete' );
		// A hidden field (a price in a market selling in the shop's currency) does not count.
		const hidden = scopesOf( [ tab( 'i18n:sv', { hide: [ 'i18n:sv.slug' ] } ) ], { layout } )[ 0 ]!;
		expect( scopeState( hidden, fields, {}, item ) ).toEqual( { total: 1, missing: 1 } );
		// No product (a bulk edit): no count.
		expect( scopeDetail( market!, scopeState( market!, fields, {}, undefined ) ) ).toBe( 'SEK' );
	} );

	it( 'lays out a tab by its declared sections first, in their order', () => {
		const fields = [ field( 'i18n:sv.meta_title', 'i18n:sv' ), field( 'i18n:sv.name', 'i18n:sv' ), field( 'i18n:sv.slug', 'i18n:sv' ) ];
		const declared = tab( 'i18n:sv', {
			sections: [
				{ id: 'texts', label: 'Texts', description: '', fields: [ 'i18n:sv.slug', 'i18n:sv.name' ] },
				{ id: 'search', label: 'Search engines', description: '', fields: [ 'i18n:sv.meta_title' ] },
			],
		} );

		expect( sectionsOfTab( fields, declared ) ).toEqual( [
			{ group: 'declared:texts', fields: [ 'i18n:sv.slug', 'i18n:sv.name' ] },
			{ group: 'declared:search', fields: [ 'i18n:sv.meta_title' ] },
		] );
	} );
} );
