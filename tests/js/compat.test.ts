/**
 * The hook names and the global from before the fork keep answering beside
 * the new ones (resources/extensions/compat.ts).
 */
import { addAction, addFilter, applyFilters, doAction, removeAction, removeFilter } from '@wordpress/hooks';
import { describe, expect, it, vi } from 'vitest';
import { oldName } from '../../resources/extensions/compat';
import { ACTIONS, FILTERS } from '../../resources/extensions/hooks';

describe( 'compat: the names from before the fork', () => {
	it( 'maps each new hook to its old name', () => {
		expect( oldName( FILTERS.query ) ).toBe( 'wcProductsList.query' );
		expect( oldName( FILTERS.quickEditTabs ) ).toBe( 'wcProductsList.quickEdit.tabs' );
		expect( oldName( ACTIONS.ready ) ).toBe( 'wcProductsList.ready' );
	} );

	it( 'runs a filter on the old name after the new one, passing the value on', () => {
		addFilter( FILTERS.query, 'test/new', ( params: Record< string, unknown > ) => ( { ...params, fresh: 1 } ) );
		addFilter( 'wcProductsList.query', 'test/old', ( params: Record< string, unknown > ) => ( { ...params, old: 1 } ) );

		expect( applyFilters( FILTERS.query, { page: 1 }, {} ) ).toEqual( { page: 1, fresh: 1, old: 1 } );

		removeFilter( FILTERS.query, 'test/new' );
		removeFilter( 'wcProductsList.query', 'test/old' );
	} );

	it( 'runs an action on the old name with the same arguments', () => {
		const old = vi.fn();
		addAction( 'wcProductsList.saved', 'test/old', old );

		doAction( ACTIONS.saved, { updated: 1 }, { source: 'test' } );

		expect( old ).toHaveBeenCalledWith( { updated: 1 }, { source: 'test' } );
		removeAction( 'wcProductsList.saved', 'test/old' );
	} );
} );
