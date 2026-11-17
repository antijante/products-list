/**
 * The quick edit panel's main level when an extension declares scopes
 * (resources/edit/scopes.ts): a rail of scopes on the panel's left, a
 * select at its top when the panel is narrow (CSS, by the panel's own
 * width). For markets, each shows its currency and how many of its texts
 * are missing, counted from what is typed. Moving between scopes keeps
 * every unsaved edit; one Update saves them all.
 */
import { __ } from '@wordpress/i18n';
import type { KeyboardEvent } from 'react';
import type { ProductField, ProductListItem } from '../types';
import { scopeDetail, scopeState } from './scopes';
import type { PanelScope } from './scopes';

interface Props {
	scopes: PanelScope[];
	current: string;
	onChange( id: string ): void;
	fields: ProductField[];
	/** The form's values by field id, edits included. */
	data: Record< string, unknown >;
	/** The product being edited; none in a bulk edit (no counts). */
	item: ProductListItem | undefined;
	/** Whether a scope holds an unsaved edit. */
	pending( entry: PanelScope ): boolean;
	/** The id of the panel the scopes control. */
	controls: string;
}

export function ScopeRail( { scopes, current, onChange, fields, data, item, pending, controls }: Props ) {
	const details = scopes.map( ( entry ) => scopeDetail( entry, scopeState( entry, fields, data, item ) ) );

	const onKeyDown = ( event: KeyboardEvent< HTMLDivElement > ) => {
		const index = scopes.findIndex( ( entry ) => entry.scope.id === current );
		const last = scopes.length - 1;
		const next = { ArrowDown: Math.min( last, index + 1 ), ArrowUp: Math.max( 0, index - 1 ), Home: 0, End: last }[ event.key as 'ArrowDown' ];

		if ( next === undefined ) {
			return;
		}

		event.preventDefault();

		const target = scopes[ next ];

		if ( target ) {
			onChange( target.scope.id );
			( event.currentTarget.querySelector( `[data-scope="${ target.scope.id }"]` ) as HTMLElement | null )?.focus();
		}
	};

	return (
		<>
			<div className="wc-pl-edit__rail" role="tablist" aria-orientation="vertical" aria-label={ __( 'Where the change applies', 'antijante-products-list' ) } onKeyDown={ onKeyDown }>
				{ scopes.map( ( entry, index ) => {
					const selected = entry.scope.id === current;

					return (
						<div key={ entry.scope.id } className="wc-pl-edit__rail-entry">
							{ entry.scope.heading ? <p className="wc-pl-edit__rail-heading">{ entry.scope.heading }</p> : null }
							<button
								type="button"
								role="tab"
								id={ `wc-pl-edit-scope-${ entry.scope.id }` }
								data-scope={ entry.scope.id }
								aria-selected={ selected }
								aria-controls={ controls }
								tabIndex={ selected ? 0 : -1 }
								className={ `wc-pl-edit__rail-item${ selected ? ' is-active' : '' }` }
								onClick={ () => onChange( entry.scope.id ) }
							>
								<span className="wc-pl-edit__rail-label">
									{ entry.scope.label }
									{ pending( entry ) ? ' •' : '' }
									{ entry.scope.badge ? <span className="wc-pl-edit__rail-badge">{ entry.scope.badge }</span> : null }
								</span>
								{ details[ index ] ? <span className="wc-pl-edit__rail-detail">{ details[ index ] }</span> : null }
							</button>
						</div>
					);
				} ) }
			</div>
			<label className="wc-pl-edit__scope-select">
				<span className="screen-reader-text">{ __( 'Where the change applies', 'antijante-products-list' ) }</span>
				<select value={ current } onChange={ ( event ) => onChange( event.target.value ) }>
					{ scopes.map( ( entry, index ) => (
						<option key={ entry.scope.id } value={ entry.scope.id }>
							{ [ entry.scope.label, entry.scope.badge, details[ index ] ].filter( Boolean ).join( ', ' ) }
						</option>
					) ) }
				</select>
			</label>
		</>
	);
}
