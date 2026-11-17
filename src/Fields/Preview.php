<?php

namespace Antijante\ProductsList\Fields;

use Antijante\ProductsList\Plugin;
use Antijante\ProductsList\Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The preview of a declarative `textarea` field with `preview: true`
 * (docs/contracts.md §6): `POST /wc-products-list/v1/preview` with the field
 * id, the value being typed and the product id. The site renders it through
 * `antijante_products_list/preview`, with the same reader the page uses, so
 * what the editor shows is what the page will show, and says which line it
 * cannot read. A save is refused through `antijante_products_list/save` the
 * same way (the site throws WC_REST_Exception there).
 */
final class Preview
{
    public const FILTER = 'antijante_products_list/preview';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'route']);
    }

    public static function route(): void
    {
        register_rest_route(Plugin::REST_NAMESPACE, '/preview', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'respond'],
            'permission_callback' => static fn () => current_user_can(Plugin::capability()),
            'args' => [
                'field' => ['type' => 'string', 'required' => true],
                'value' => ['type' => 'string', 'default' => ''],
                'id' => ['type' => 'integer', 'default' => 0],
            ],
        ]);
    }

    public static function respond(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $def = Registry::field((string) $request['field']);

        if ($def === null || $def['type'] !== 'textarea' || empty($def['preview'])) {
            return new WP_Error('wc_products_list_no_preview', __('This field has no preview.', 'antijante-products-list'), ['status' => 404]);
        }

        $id = (int) $request['id'];
        $product = $id > 0 ? wc_get_product($id) : null;

        if ($id > 0 && (! $product || ! current_user_can('edit_post', $id))) {
            return new WP_Error('wc_products_list_no_preview', __('No such product.', 'antijante-products-list'), ['status' => 404]);
        }

        /**
         * Renders a textarea field's value as the site will show it.
         *
         * @param  array{html?: string, error?: string, line?: int}|null  $preview  `html` to show under the
         *                                                                          field, or `error` (and the 1-based `line`) when the value cannot be read
         * @param  string  $field  the field id
         * @param  string  $value  the text being typed
         * @param  \WC_Product|null  $product
         */
        $preview = apply_filters(self::FILTER, null, $def['id'], (string) $request['value'], $product ?: null);

        if (! is_array($preview)) {
            return new WP_REST_Response(['html' => '', 'error' => null, 'line' => null]);
        }

        return new WP_REST_Response([
            'html' => isset($preview['html']) ? wp_kses_post((string) $preview['html']) : '',
            'error' => isset($preview['error']) && $preview['error'] !== '' ? (string) $preview['error'] : null,
            'line' => isset($preview['line']) ? (int) $preview['line'] : null,
        ]);
    }
}
