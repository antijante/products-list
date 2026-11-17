import { __ } from '@wordpress/i18n';
import type { ProductField, Settings } from '../types';
import { OptionCell } from './components/option-cell';
import { field } from './helpers';

const VARIATION_STATUSES = [
	{ value: 'publish', label: __( 'Active', 'antijante-products-list' ) },
	{ value: 'private', label: __( 'Inactive', 'antijante-products-list' ) },
];

/** Products use the post statuses; a variation is Active (publish) or Inactive (private). */
export function createStatusField( settings: Settings ): ProductField {
	return field( {
		id: 'status',
		type: 'text',
		label: __( 'Status', 'antijante-products-list' ),
		elements: settings.statuses,
		// The status tabs are the filter; sorting groups the "All" tab by status.
		filterBy: false,
		enableSorting: true,
		render: ( { item } ) => <OptionCell value={ item.status } options={ item._kind === 'variation' ? VARIATION_STATUSES : settings.statuses } />,
		getValue: ( { item } ) => item.status ?? '',
		rest: { sortParam: 'post_status', applies: { product: true, variation: true } },
		edit: { group: 'visibility', bulk: 'default', order: 49 },
	} );
}
