import { __ } from '@wordpress/i18n';
import { Button } from '../ui';
import type { Settings } from '../types';
import type { StatusTabId } from './default-view';

export interface EmptyStateProps {
	tab: StatusTabId;
	hasQuery: boolean;
	onClear: () => void;
	settings: Settings;
	error?: Error;
}

export function EmptyState( { tab, hasQuery, onClear, settings, error }: EmptyStateProps ) {
	if ( error ) {
		return (
			<div className="wc-products-list__empty wc-products-list__empty--error" role="alert">
				<p>{ __( 'The products could not be loaded.', 'antijante-products-list' ) }</p>
				<p className="wc-products-list__empty-detail">{ error.message }</p>
			</div>
		);
	}

	if ( hasQuery ) {
		return (
			<div className="wc-products-list__empty">
				<p>{ __( 'No products match the search and filters.', 'antijante-products-list' ) }</p>
				<Button variant="secondary" onClick={ onClear }>
					{ __( 'Clear search and filters', 'antijante-products-list' ) }
				</Button>
			</div>
		);
	}

	if ( tab === 'trash' ) {
		return (
			<div className="wc-products-list__empty">
				<p>{ __( 'The trash is empty.', 'antijante-products-list' ) }</p>
			</div>
		);
	}

	return (
		<div className="wc-products-list__empty">
			<p>{ tab === 'all' ? __( 'No products yet.', 'antijante-products-list' ) : __( 'No products with this status.', 'antijante-products-list' ) }</p>
			{ tab === 'all' && settings.caps.edit && (
				<Button variant="primary" href={ settings.links.newProduct }>
					{ __( 'Add new product', 'antijante-products-list' ) }
				</Button>
			) }
		</div>
	);
}
