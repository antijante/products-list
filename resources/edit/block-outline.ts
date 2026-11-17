/**
 * What a description in block markup is made of, in words: one line per
 * top-level block, its title, and the blocks of note inside it (those not
 * core and not only a part of another, in order). A synced pattern says
 * its title. Read from the block delimiters only, so it needs neither the
 * block editor nor a parse of the HTML.
 */
import { __, sprintf } from '@wordpress/i18n';

export interface BlockTitles {
	types: Record< string, { title: string; part: boolean } >;
	synced: Record< string, string >;
}

export interface OutlineLine {
	title: string;
	inside: string[];
}

const DELIMITER = /<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(\s+(\{[\s\S]*?\}))?\s+(\/)?-->/g;

function fullName( name: string ): string {
	return name.includes( '/' ) ? name : `core/${ name }`;
}

/** The outline of `markup`; empty when it has no blocks. */
export function blockOutline( markup: string, titles: BlockTitles ): OutlineLine[] {
	const lines: Array< OutlineLine & { name: string } > = [];
	let depth = 0;

	for ( const match of markup.matchAll( DELIMITER ) ) {
		const closing = !! match[ 1 ];
		const name = fullName( match[ 2 ] ?? '' );
		const selfClosing = !! match[ 5 ];

		if ( closing ) {
			depth = Math.max( 0, depth - 1 );
			continue;
		}

		const type = titles.types[ name ];
		const title = type?.title ?? name;

		if ( depth === 0 ) {
			let lineTitle = title;

			if ( name === 'core/block' ) {
				let ref = '';

				try {
					ref = String( ( JSON.parse( match[ 4 ] ?? '{}' ) as { ref?: number } ).ref ?? '' );
				} catch {
					ref = '';
				}

				const synced = titles.synced[ ref ];

				/* translators: %s: title of a synced pattern */
				lineTitle = synced ? sprintf( __( 'Synced section: %s', 'antijante-products-list' ), synced ) : __( 'Synced section', 'antijante-products-list' );
			}

			const last = lines[ lines.length - 1 ];

			// Text blocks in a row read as one line: "Paragraph (3)".
			if ( last && last.name === name && name.startsWith( 'core/' ) && name !== 'core/block' ) {
				const count = Number( /\((\d+)\)$/.exec( last.title )?.[ 1 ] ?? 1 ) + 1;

				last.title = `${ title } (${ count })`;
			} else {
				lines.push( { name, title: lineTitle, inside: [] } );
			}
		} else if ( ! name.startsWith( 'core/' ) && type && ! type.part ) {
			const line = lines[ lines.length - 1 ];

			if ( line && title !== line.title && ! line.inside.includes( title ) ) {
				line.inside.push( title );
			}
		}

		if ( ! selfClosing ) {
			depth++;
		}
	}

	return lines.map( ( { title, inside } ) => ( { title, inside } ) );
}
