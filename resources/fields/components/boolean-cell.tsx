import { __ } from '@wordpress/i18n';

export function BooleanCell( { value }: { value: unknown } ) {
	if ( value === 'parent' ) {
		return <span className="wc-products-list__bool wc-products-list__bool--parent">{ __( 'Parent', 'antijante-products-list' ) }</span>;
	}

	if ( value === undefined || value === null ) {
		return <span className="wc-products-list__bool wc-products-list__bool--empty">—</span>;
	}

	return value ? (
		<span className="wc-products-list__bool wc-products-list__bool--yes">{ __( 'Yes', 'antijante-products-list' ) }</span>
	) : (
		<span className="wc-products-list__bool wc-products-list__bool--no">{ __( 'No', 'antijante-products-list' ) }</span>
	);
}
