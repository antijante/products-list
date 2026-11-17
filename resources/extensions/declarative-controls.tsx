/**
 * Edit controls of the declarative field types the plugin carries itself
 * (docs/contracts.md §6):
 *
 * - `textarea`: several lines of plain text. With `preview`, the site renders
 *   what is typed as the page will show it (POST /preview), and says which
 *   line it cannot read; the save refuses the same text.
 * - `terms`: terms of one taxonomy by name. One or several, suggestions from
 *   the taxonomy, a new name allowed when the field may make one. `numeric`:
 *   a number with its unit; the server finds or makes the term holding it.
 * - `media`: an attachment from the media library, limited to `accept`.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import type { ComponentType } from 'react';
import { getFieldPreview, getTerms } from '../api/client';
import type { FieldPreview } from '../api/client';
import type { DataFormControlProps } from '../dataviews';
import { pickMedia } from '../media/media-frame';
import type { ProductListItem } from '../types';
import type { DeclarativeField } from '../types/extension';
import { Button, FormTokenField, InputControl, TextareaControl } from '../ui';

type Props = DataFormControlProps< ProductListItem >;

const controlId = ( field: { id: string }, reactId: string ) => `wc-pl-${ field.id.replace( /[^a-z0-9_-]+/gi, '-' ) }-${ reactId.replace( /:/g, '' ) }`;

/** How long typing pauses before the preview is asked for (ms). */
export const PREVIEW_DELAY = 350;

export function createTextareaEdit( def: DeclarativeField ): ComponentType< Props > {
	return function DeclarativeTextareaEdit( { data, field, onChange, hideLabelFromVision }: Props ) {
		const value = String( field.getValue( { item: data } ) ?? '' );
		const id = controlId( field, useId() );
		const [ preview, setPreview ] = useState< FieldPreview | null >( null );
		const timer = useRef< number >( 0 );

		useEffect( () => {
			if ( ! def.preview ) {
				return undefined;
			}

			const controller = new AbortController();

			window.clearTimeout( timer.current );
			timer.current = window.setTimeout( () => {
				getFieldPreview( def.id, value, data.id, { signal: controller.signal } )
					.then( setPreview )
					.catch( () => {
						// No preview (offline, aborted): the save still checks the text.
					} );
			}, PREVIEW_DELAY );

			return () => {
				window.clearTimeout( timer.current );
				controller.abort();
			};
		}, [ value, data.id ] );

		const error = preview?.error ? ( preview.line ? sprintf( /* translators: 1: a line number, 2: what is wrong with it */ __( 'Line %1$d: %2$s', 'antijante-products-list' ), preview.line, preview.error ) : preview.error ) : null;

		return (
			<div className="wc-products-list__textarea-edit">
				<TextareaControl
					__nextHasNoMarginBottom
					id={ id }
					label={ field.label }
					hideLabelFromVision={ hideLabelFromVision }
					help={ error ?? ( field.description || undefined ) }
					rows={ def.rows ?? 6 }
					value={ value }
					className={ error ? 'is-invalid' : undefined }
					onChange={ ( next: string ) => onChange( field.setValue( { item: data, value: next } ) ) }
				/>
				{ def.preview && preview?.html && ! error ? (
					<div className="wc-products-list__preview" aria-live="polite">
						<span className="wc-products-list__preview-label">{ __( 'As the page shows it', 'antijante-products-list' ) }</span>
						{ /* Sanitised by the server (wp_kses_post). */ }
						<div dangerouslySetInnerHTML={ { __html: preview.html } } />
					</div>
				) : null }
			</div>
		);
	};
}

/** The names of a taxonomy matching a search, for suggestions. */
async function suggest( taxonomy: string, search: string ): Promise< string[] > {
	const result = await getTerms( taxonomy, { search, perPage: 50 } );

	return result.items.map( ( term ) => term.name );
}

export function createTermsEdit( def: DeclarativeField ): ComponentType< Props > {
	if ( def.numeric ) {
		return function DeclarativeNumberTermEdit( { data, field, onChange, hideLabelFromVision }: Props ) {
			const value = field.getValue( { item: data } );
			const id = controlId( field, useId() );

			return (
				<InputControl
					__next40pxDefaultSize
					id={ id }
					label={ field.label }
					hideLabelFromVision={ hideLabelFromVision }
					help={ field.description || undefined }
					inputMode="decimal"
					value={ value === null || value === undefined ? '' : String( value ) }
					suffix={ def.unit ? <span className="wc-products-list-currency-suffix">{ def.unit }</span> : undefined }
					onChange={ ( next?: string ) => onChange( field.setValue( { item: data, value: ( next ?? '' ).trim() } ) ) }
				/>
			);
		};
	}

	return function DeclarativeTermsEdit( { data, field, onChange }: Props ) {
		const raw = field.getValue( { item: data } );
		const value = ( Array.isArray( raw ) ? raw : raw ? [ raw ] : [] ).map( String );
		const [ suggestions, setSuggestions ] = useState< string[] >( [] );
		const [ search, setSearch ] = useState( '' );

		useEffect( () => {
			let current = true;

			suggest( def.taxonomy ?? '', search )
				.then( ( names ) => current && setSuggestions( names ) )
				.catch( () => current && setSuggestions( [] ) );

			return () => {
				current = false;
			};
		}, [ search ] );

		return (
			<div className="wc-products-list__terms-edit">
			<FormTokenField
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ field.label }
				value={ value }
				suggestions={ suggestions }
				// One-term fields take a new pick in place of the term (onChange keeps the last), so suggestions stay open.
				onInputChange={ setSearch }
				__experimentalExpandOnFocus
				__experimentalValidateInput={ ( input: string ) => def.create || suggestions.includes( input ) }
				__experimentalShowHowTo={ false }
				onChange={ ( tokens ) => {
					const names = tokens.map( ( token ) => ( typeof token === 'string' ? token : token.value ) );

					onChange( field.setValue( { item: data, value: def.multiple ? names : ( names[ names.length - 1 ] ?? '' ) } ) );
				} }
			/>
			{ field.description ? <p className="components-base-control__help">{ field.description }</p> : null }
			</div>
		);
	};
}

/** A media field's value: the attachment id, or what the row says about it. */
export interface MediaValue {
	id: number;
	title?: string;
	url?: string;
	thumb?: string;
}

export function mediaId( value: unknown ): number {
	if ( typeof value === 'number' ) {
		return value;
	}

	if ( typeof value === 'string' ) {
		return Number.parseInt( value, 10 ) || 0;
	}

	return value && typeof value === 'object' ? Number( ( value as MediaValue ).id ) || 0 : 0;
}

export function createMediaEdit( def: DeclarativeField ): ComponentType< Props > {
	return function DeclarativeMediaEdit( { data, field, onChange }: Props ) {
		const value = field.getValue( { item: data } ) as unknown;
		const id = mediaId( value );
		const shown = value && typeof value === 'object' ? ( value as MediaValue ) : null;

		const pick = async () => {
			const picked = await pickMedia( {
				title: field.label,
				button: __( 'Use this file', 'antijante-products-list' ),
				library: def.accept ?? [],
				selected: id ? [ id ] : [],
				postId: data.id,
			} );
			const first = picked?.[ 0 ];

			if ( first ) {
				const next: MediaValue = { id: first.id, title: first.title || first.filename || '', url: first.url ?? '', thumb: first.sizes?.thumbnail?.url ?? first.icon ?? '' };

				onChange( field.setValue( { item: data, value: next } ) );
			}
		};

		return (
			<fieldset className="wc-products-list__media-edit">
				<legend className="components-base-control__label">{ field.label }</legend>
				{ id ? (
					<p className="wc-products-list__media-edit-current">
						{ shown?.thumb ? <img src={ shown.thumb } alt="" width={ 40 } height={ 40 } /> : null }
						{ shown?.url ? (
							<a href={ shown.url } target="_blank" rel="noreferrer">
								{ shown.title || sprintf( /* translators: %d: an attachment id */ __( 'File #%d', 'antijante-products-list' ), id ) }
							</a>
						) : (
							<span>{ shown?.title || sprintf( /* translators: %d: an attachment id */ __( 'File #%d', 'antijante-products-list' ), id ) }</span>
						) }
					</p>
				) : (
					<p className="wc-products-list__media-edit-current is-empty">{ __( 'None', 'antijante-products-list' ) }</p>
				) }
				<Button variant="secondary" size="compact" onClick={ () => void pick() }>
					{ id ? __( 'Replace', 'antijante-products-list' ) : __( 'Choose', 'antijante-products-list' ) }
				</Button>{ ' ' }
				{ id ? (
					<Button variant="tertiary" size="compact" isDestructive onClick={ () => onChange( field.setValue( { item: data, value: 0 } ) ) }>
						{ __( 'Remove', 'antijante-products-list' ) }
					</Button>
				) : null }
				{ field.description ? <p className="components-base-control__help">{ field.description }</p> : null }
			</fieldset>
		);
	};
}
