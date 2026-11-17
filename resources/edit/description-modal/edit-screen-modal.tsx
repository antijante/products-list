/**
 * The product's own block editor over quick edit: its edit screen in a frame
 * (src/Modules/EditScreenEmbed.php), so every block the site registers, its
 * patterns, the theme's styles, the inspector, the list view and undo work
 * as on the edit screen. The frame only edits: Done puts the content into
 * the form (saved with Update, logged in History like any field); Cancel
 * drops it, after asking when something was changed.
 *
 * Done writes the content back only when the editor says something changed,
 * so opening and closing leaves the stored description as it was, byte for
 * byte. Loaded on first use; the frame is gone once the modal closes.
 */
import { Button, Modal, Spinner, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

interface EmbedApi {
	content(): { content: string; original: string | null; changed: boolean };
}

export interface EditScreenModalProps {
	/** The edit screen's address in embed mode. */
	url: string;
	productName: string;
	/** The description in the form now: when it was edited here already (and not saved), the frame would not show it. */
	formValue: string;
	/** The description as stored (the form's base). */
	storedValue: string;
	onApply( content: string ): void;
	onClose(): void;
}

const stop = ( event: { stopPropagation(): void } ) => event.stopPropagation();

export function EditScreenModal( { url, productName, formValue, storedValue, onApply, onClose }: EditScreenModalProps ) {
	const frame = useRef< HTMLIFrameElement >( null );
	const [ ready, setReady ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ failed, setFailed ] = useState( false );
	// The frame edits what is stored. A description already changed in the form (Code) would be lost: say so first.
	const pendingFormEdit = formValue !== storedValue;

	useEffect( () => {
		const onMessage = ( event: MessageEvent ) => {
			if ( event.origin !== window.location.origin || event.source !== frame.current?.contentWindow ) {
				return;
			}

			const data = event.data as { type?: string; dirty?: boolean };

			if ( data.type === 'wcpl-embed-ready' ) {
				setReady( true );
			} else if ( data.type === 'wcpl-embed-dirty' ) {
				setDirty( !! data.dirty );
			}
		};
		// The screen not telling it is ready within a minute (it errored, or a plugin stopped it): say so.
		const timer = window.setTimeout( () => setFailed( true ), 60000 );

		window.addEventListener( 'message', onMessage );

		return () => {
			window.removeEventListener( 'message', onMessage );
			window.clearTimeout( timer );
		};
	}, [] );

	const [ unreadable, setUnreadable ] = useState( false );
	const api = (): EmbedApi | undefined => {
		try {
			return ( frame.current?.contentWindow as unknown as { antijanteEmbed?: EmbedApi } | null )?.antijanteEmbed;
		} catch {
			// A frame the page may not read (another origin, or isolated): nothing is taken from it.
			return undefined;
		}
	};

	const done = () => {
		const embed = api();

		// An edit that cannot be read is never dropped quietly: the modal stays open and says so.
		if ( ! embed ) {
			if ( dirty ) {
				setUnreadable( true );

				return;
			}

			onClose();

			return;
		}

		const result = embed.content();

		if ( result.changed ) {
			onApply( result.content );
		}

		onClose();
	};

	const cancel = () => {
		if ( dirty ) {
			setConfirming( true );

			return;
		}

		onClose();
	};

	return (
		<Modal
			className="wc-pl-edit-screen-modal"
			/* translators: %s: product name */
			title={ sprintf( __( 'Description of %s', 'antijante-products-list' ), productName ) }
			onRequestClose={ cancel }
			isFullScreen
			shouldCloseOnClickOutside={ false }
			// The panel under it has its own key handling (Enter submits): the modal keeps its keys.
			onKeyDown={ stop }
		>
			<div className="wc-pl-edit-screen-modal__body">
				{ pendingFormEdit ? (
					<p className="wc-pl-edit-screen-modal__note">
						{ __( 'The description has an edit that is not saved yet. The block editor shows the saved description; Done replaces the edit.', 'antijante-products-list' ) }
					</p>
				) : null }
				{ ! ready && ! failed ? (
					<div className="wc-pl-edit-screen-modal__loading">
						<Spinner />
						{ __( 'Opening the block editor…', 'antijante-products-list' ) }
					</div>
				) : null }
				{ unreadable ? (
					<p className="wc-pl-edit-screen-modal__note" role="alert">
						{ __( 'The edit could not be taken from the block editor. Copy it, or make it on the product’s own screen.', 'antijante-products-list' ) }
					</p>
				) : null }
				{ failed && ! ready ? (
					<p className="wc-pl-edit-screen-modal__note">{ __( 'The block editor did not open. Edit the description on the product’s own screen.', 'antijante-products-list' ) }</p>
				) : null }
				<iframe ref={ frame } src={ url } title={ __( 'Block editor', 'antijante-products-list' ) } className={ `wc-pl-edit-screen-modal__frame${ ready ? ' is-ready' : '' }` } />
			</div>
			<div className="wc-pl-edit-screen-modal__footer">
				<Button variant="primary" onClick={ done } disabled={ ! ready } __next40pxDefaultSize>
					{ __( 'Done', 'antijante-products-list' ) }
				</Button>
				<Button variant="tertiary" onClick={ cancel } __next40pxDefaultSize>
					{ __( 'Cancel', 'antijante-products-list' ) }
				</Button>
				<span className="wc-pl-edit-screen-modal__hint">{ __( 'Done puts the description in the form. Update saves it.', 'antijante-products-list' ) }</span>
			</div>
			{ confirming ? (
				<ConfirmDialog
					isOpen
					confirmButtonText={ __( 'Discard changes', 'antijante-products-list' ) }
					cancelButtonText={ __( 'Keep editing', 'antijante-products-list' ) }
					onConfirm={ onClose }
					onCancel={ () => setConfirming( false ) }
				>
					{ __( 'Discard the changes to the description?', 'antijante-products-list' ) }
				</ConfirmDialog>
			) : null }
		</Modal>
	);
}
