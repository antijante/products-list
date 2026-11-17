<?php

namespace Antijante\ProductsList\Modules;

use Antijante\ProductsList\Module;
use WP_Post;

/**
 * The product's own block editor, opened from quick edit
 * (resources/edit/description-modal/edit-screen-modal.tsx): the product's
 * edit screen (`post.php?post=…&action=edit&wcpl_embed=1`) in a frame, so
 * the description is edited with every block, pattern, style and setting the
 * site gives that screen, the product as the post in context.
 *
 * In the frame the screen only edits; quick edit saves:
 *
 * - no admin menu, bar, meta boxes, title or Save / Publish buttons;
 * - saving and autosaving are locked, so nothing is written from the frame;
 * - the post lock the screen takes is put back as it was, and the frame's
 *   heartbeat does not renew it: a frame that only looks does not keep
 *   quick edit's Update out (Rest\Concurrency refuses a save while the
 *   product is open in an editor);
 * - the frame tells quick edit when the editor is ready and answers with the
 *   content, and whether anything was changed (`window.antijanteEmbed`);
 * - the frame stays in the page's agent cluster (no Document-Isolation-Policy),
 *   or quick edit could not read it.
 *
 * Quick edit writes the content back only when the editor says it changed,
 * so opening and closing leaves the stored description byte for byte.
 */
class EditScreenEmbed implements Module
{
    public const PARAM = 'wcpl_embed';

    /** The lock the post had before the frame opened, put back when the screen is printed. */
    private ?string $previousLock = null;

    private int $postId = 0;

    public function register(): void
    {
        add_action('admin_init', [$this, 'sameAgent']);
        add_action('load-post.php', [$this, 'load']);
        add_action('wp_ajax_heartbeat', [$this, 'heartbeat'], 0);
    }

    /** Whether this request is the frame: the edit screen of a post the user may edit, asked for embedded. */
    public static function isEmbed(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: what to show.
        if (empty($_GET[self::PARAM]) || ($_GET['action'] ?? '') !== 'edit' || empty($_GET['post'])) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = absint($_GET['post']);

        return $id > 0 && get_post($id) instanceof WP_Post && current_user_can('edit_post', $id);
    }

    /**
     * WordPress 7.1 isolates the block editor's document (Document-Isolation-Policy, for media processed in the
     * browser on Chromium 137+), which also shuts quick edit out of its frame. The frame is left in the page's agent
     * cluster; uploads from it are processed on the server, as before 7.1.
     */
    public function sameAgent(): void
    {
        if (self::isEmbed()) {
            remove_action('load-post.php', 'wp_set_up_cross_origin_isolation');
        }
    }

    public function load(): void
    {
        if (! self::isEmbed()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $this->postId = absint($_GET['post']);
        $lock = get_post_meta($this->postId, '_edit_lock', true);
        $this->previousLock = is_string($lock) ? $lock : '';

        add_filter('admin_body_class', static fn (string $classes) => $classes.' wcpl-embed');
        add_filter('block_editor_settings_all', [$this, 'settings'], 100);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue'], 100);
        add_action('admin_head', [$this, 'style']);
        // post.php takes the lock after this action and before printing the screen.
        add_action('admin_footer', [$this, 'restoreLock']);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function settings(array $settings): array
    {
        // A day: no autosave while the frame is open (saving is locked as well).
        $settings['autosaveInterval'] = DAY_IN_SECONDS;
        $settings['localAutosaveInterval'] = DAY_IN_SECONDS;
        // The title is quick edit's Name; in the canvas it would take typing nothing saves.
        $settings['styles'] = array_merge((array) ($settings['styles'] ?? []), [['css' => '.editor-visual-editor__post-title-wrapper,.editor-post-title{display:none!important}', '__unstableType' => 'theme', 'isGlobalStyles' => false]]);

        return $settings;
    }

    public function restoreLock(): void
    {
        if ($this->postId === 0 || $this->previousLock === null) {
            return;
        }

        if ($this->previousLock === '') {
            delete_post_meta($this->postId, '_edit_lock');
        } else {
            update_post_meta($this->postId, '_edit_lock', $this->previousLock);
        }
    }

    /** The frame's heartbeat (it says so) does not renew the post lock. */
    public function heartbeat(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- heartbeat checks its own nonce; this only skips a refresh.
        $data = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : [];

        if (! empty($data[self::PARAM])) {
            remove_filter('heartbeat_received', 'wp_refresh_post_lock', 10);
        }
    }

    public function style(): void
    {
        echo '<style id="wcpl-embed">'
            .'html.wp-toolbar{padding-top:0!important}'
            .'#wpadminbar,#adminmenumain,#wpfooter,#screen-meta,#screen-meta-links,.notice,.update-nag{display:none!important}'
            .'#wpcontent,#wpfooter{margin-left:0!important}'
            .'.wcpl-embed .interface-interface-skeleton{top:0!important;left:0!important}'
            .'.wcpl-embed .edit-post-meta-boxes-main,.wcpl-embed .edit-post-layout__metaboxes,'
            .'.wcpl-embed .editor-post-publish-button__button,.wcpl-embed .editor-post-publish-panel__toggle,'
            .'.wcpl-embed .editor-post-save-draft,.wcpl-embed .editor-post-saved-state,.wcpl-embed .editor-post-preview,'
            .'.wcpl-embed .editor-preview-dropdown,.wcpl-embed .editor-post-view-link,.wcpl-embed .editor-header__back-button,'
            .'.wcpl-embed .edit-post-fullscreen-mode-close,'
            // The document's own settings (status, slug, trash) are quick edit's: only the block's settings show.
            .'.wcpl-embed .editor-sidebar__panel-tabs [id$="edit-post/document"],.wcpl-embed .editor-post-trash,.wcpl-embed .editor-post-summary,'
            .'.wcpl-embed .editor-document-bar{display:none!important}'
            .'</style>';
    }

    public function enqueue(): void
    {
        wp_register_script('wcpl-embed', false, ['wp-data', 'wp-editor', 'wp-block-editor', 'wp-dom-ready', 'jquery'], (string) filemtime(__FILE__), true);
        wp_enqueue_script('wcpl-embed');
        wp_add_inline_script('wcpl-embed', self::script());
    }

    private static function script(): string
    {
        return <<<'JS'
( function () {
	var wp = window.wp;
	var KEY = 'wcpl-embed';

	// Quick edit saves; the frame never does.
	jQuery( document ).on( 'heartbeat-send', function ( event, data ) {
		data.wcpl_embed = 1;
	} );

	wp.domReady( function () {
		var editor = wp.data.dispatch( 'core/editor' );

		editor.lockPostSaving( KEY );
		editor.lockPostAutosaving( KEY );

		// The block's settings, not the document's (those are quick edit's).
		var post = wp.data.dispatch( 'core/edit-post' );

		if ( post && post.openGeneralSidebar ) {
			post.openGeneralSidebar( 'edit-post/block' );
		}

		var original = null;

		window.antijanteEmbed = {
			// The content as the editor would save it, and whether anything was edited.
			content: function () {
				var select = wp.data.select( 'core/editor' );
				var content = select.getEditedPostContent();

				return { content: content, original: original, changed: select.isEditedPostDirty() && content !== original };
			},
		};

		var told = false;
		var tell = function () {
			var select = wp.data.select( 'core/editor' );

			if ( told || ! select.__unstableIsEditorReady || ! select.__unstableIsEditorReady() ) {
				return;
			}

			told = true;
			original = select.getCurrentPostAttribute( 'content' );
			window.parent.postMessage( { type: 'wcpl-embed-ready', post: select.getCurrentPostId() }, window.location.origin );
		};

		wp.data.subscribe( tell );
		tell();

		// Changes reach quick edit, so it knows to ask before throwing them away.
		var dirty = false;

		wp.data.subscribe( function () {
			var now = wp.data.select( 'core/editor' ).isEditedPostDirty();

			if ( now !== dirty ) {
				dirty = now;
				window.parent.postMessage( { type: 'wcpl-embed-dirty', dirty: dirty }, window.location.origin );
			}
		} );
	} );
} )();
JS;
    }
}
