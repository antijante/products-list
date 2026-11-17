/**
 * The names from before the fork (WooCommerce Products List), kept answering
 * beside the new ones so extensions written for it keep working unchanged.
 * Deprecated: use `window.antijanteProductsList` and the
 * `antijanteProductsList.*` hooks. See src/Compat.php for the PHP side.
 *
 * Each new hook also runs the callbacks on the old name, after the new name's
 * callbacks at the same priority (10); a filter passes its value on.
 */
import { addAction, addFilter, applyFilters, doAction } from '@wordpress/hooks';
import { ACTIONS, FILTERS } from './hooks';

export const OLD_PREFIX = 'wcProductsList.';
const NEW_PREFIX = 'antijanteProductsList.';
const COMPAT_NAMESPACE = 'antijanteProductsList/compat';

export const oldName = ( name: string ): string => OLD_PREFIX + name.slice( NEW_PREFIX.length );

let bridged = false;

export function bridgeOldHooks(): void {
	if ( bridged ) {
		return;
	}
	bridged = true;

	for ( const name of Object.values( FILTERS ) ) {
		addFilter( name, COMPAT_NAMESPACE, ( value: unknown, ...args: unknown[] ) => applyFilters( oldName( name ), value, ...args ) );
	}
	for ( const name of Object.values( ACTIONS ) ) {
		addAction( name, COMPAT_NAMESPACE, ( ...args: unknown[] ) => doAction( oldName( name ), ...args ) );
	}
}

bridgeOldHooks();
