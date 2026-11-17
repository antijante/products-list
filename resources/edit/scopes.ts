/**
 * The quick edit panel's main level, when an extension declares one
 * (`antijante_products_list/layout`, src/Layout.php): scopes in a rail (for
 * a translated shop, "All markets" and then each market), each holding tabs.
 * A tab no scope names goes to the first scope. Without a declared layout
 * there are no scopes and the panel is its tab strip.
 */
import { _n, __, sprintf } from '@wordpress/i18n';
import type { LayoutScope, ProductField, ProductListItem, QuickEditTab, Settings } from '../types';
import { fieldsOfTab } from './form-layouts';

/** A scope with the tabs of the selection it holds. */
export interface PanelScope {
	scope: LayoutScope;
	tabs: QuickEditTab[];
}

/**
 * Fields a declared tab takes (`layout.tabs[].fields`) move there: their
 * `edit.tab` becomes the tab, so every place that asks which tab a field is
 * on agrees.
 */
export function claimFields( fields: ProductField[], settings: Pick< Settings, 'layout' > ): ProductField[] {
	const claims = new Map< string, string >();

	for ( const tab of settings.layout?.tabs ?? [] ) {
		for ( const id of tab.fields ) {
			claims.set( id, tab.id );
		}
	}

	if ( ! claims.size ) {
		return fields;
	}

	return fields.map( ( field ) => {
		const tab = claims.get( field.id );

		return tab && field.edit ? { ...field, edit: { ...field.edit, tab } } : field;
	} );
}

/** The scopes that hold at least one of `tabs`, each with its tabs in the tab order; empty without a declared layout. */
export function scopesOf( tabs: QuickEditTab[], settings: Pick< Settings, 'layout' > ): PanelScope[] {
	const declared = settings.layout?.scopes ?? [];

	if ( ! declared.length ) {
		return [];
	}

	const owner = new Map< string, string >();

	for ( const scope of declared ) {
		for ( const id of scope.tabs ) {
			if ( ! owner.has( id ) ) {
				owner.set( id, scope.id );
			}
		}
	}

	const first = declared[ 0 ]!.id;

	return declared
		.map( ( scope ) => ( { scope, tabs: tabs.filter( ( tab ) => ( owner.get( tab.id ) ?? first ) === scope.id ) } ) )
		.filter( ( entry ) => entry.tabs.length > 0 );
}

/** The scope holding a tab. */
export function scopeOfTab( scopes: PanelScope[], tabId: string ): PanelScope | undefined {
	return scopes.find( ( entry ) => entry.tabs.some( ( tab ) => tab.id === tabId ) ) ?? scopes[ 0 ];
}

function isEmpty( value: unknown ): boolean {
	if ( value === null || value === undefined ) {
		return true;
	}

	if ( typeof value === 'string' ) {
		return value.replace( /<[^>]*>/g, '' ).replace( /&nbsp;/g, ' ' ).trim() === '';
	}

	return Array.isArray( value ) ? value.length === 0 : false;
}

export interface ScopeState {
	/** Texts with a default to fall back on. */
	total: number;
	/** Of them, the empty ones. */
	missing: number;
}

/**
 * How many of a scope's texts are empty where there is a default to fall
 * back on: the rule that marks a translation missing. Read from the form's
 * values (keyed by field id), so the count follows what is typed. Null when the scope does not
 * count (`missing: false`).
 */
export function scopeState( entry: PanelScope, fields: ProductField[], data: Record< string, unknown >, item: ProductListItem | undefined ): ScopeState | null {
	if ( ! entry.scope.missing || ! item ) {
		return null;
	}

	let total = 0;
	let missing = 0;

	// What the scope's tabs show: a hidden field (a price in a market that sells in the shop's currency) is not missing.
	for ( const field of entry.tabs.flatMap( ( tab ) => fieldsOfTab( fields, tab ) ) ) {
		const reference = ( field as ProductField & { reference?: ( row: ProductListItem ) => unknown } ).reference;

		if ( ! reference || isEmpty( reference( item ) ) ) {
			continue;
		}

		total++;

		if ( isEmpty( data[ field.id ] ) ) {
			missing++;
		}
	}

	return { total, missing };
}

/** The rail's second line: the scope's detail, then its state ("SEK, 3 texts missing"). */
export function scopeDetail( entry: PanelScope, state: ScopeState | null ): string {
	const parts = [ entry.scope.detail ];

	if ( state && state.total > 0 ) {
		if ( state.missing === 0 ) {
			parts.push( __( 'complete', 'antijante-products-list' ) );
		} else if ( state.missing === state.total ) {
			parts.push( __( 'not translated', 'antijante-products-list' ) );
		} else {
			/* translators: %d: number of texts not written in this market's language */
			parts.push( sprintf( _n( '%d text missing', '%d texts missing', state.missing, 'antijante-products-list' ), state.missing ) );
		}
	}

	return parts.filter( Boolean ).join( ', ' );
}
