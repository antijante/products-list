<?php

namespace Antijante\ProductsList;

/**
 * The names this plugin had before it became Antijante Products List, kept
 * answering beside the new ones so extensions written for it (gds-woo-i18n,
 * antijante/woo-i18n) keep working unchanged. All of them are deprecated:
 * use the `Antijante\ProductsList\` classes, the `antijante_products_list/`
 * hooks, the `antijanteProductsList` script global and hooks, the
 * `antijante-products-list` script handle and the `ANTIJANTE_PRODUCTS_LIST_`
 * constants.
 *
 * - Classes: `GeneroWP\ProductsList\…` is an alias of `Antijante\ProductsList\…`.
 * - Hooks: each `antijante_products_list/{name}` also runs the callbacks on
 *   `wc_products_list/{name}`, after the new name's callbacks at the same
 *   priority (10), with the same arguments; a filter passes its value on.
 * - Constants: `WC_PRODUCTS_LIST_{NAME}` has the same value.
 * - The script handle `wc-products-list` and the `wcProductsList` globals and
 *   hooks: see Admin::enqueue() and resources/extensions/compat.ts.
 *
 * What never changed keeps its name: the REST namespace `wc-products-list/v1`,
 * the error codes, the log table, options, meta keys, capabilities, CSS classes
 * and the admin page.
 */
final class Compat
{
    public const OLD_NAMESPACE = 'GeneroWP\\ProductsList\\';

    public const OLD_HOOK_PREFIX = 'wc_products_list/';

    public const HOOK_PREFIX = 'antijante_products_list/';

    public const OLD_SCRIPT_HANDLE = 'wc-products-list';

    /** Hooks that are actions; every other hook this plugin runs is a filter. */
    public const ACTIONS = ['activate', 'deactivate', 'enqueue', 'logged', 'save'];

    /** Every filter this plugin runs, by its name after the prefix. */
    public const FILTERS = [
        'action_batch_size',
        'action_handlers',
        'actions',
        'active',
        'allow_hard_delete',
        'batch_running_ttl',
        'bootstrap',
        'capability',
        'counts',
        'drop_gallery',
        'fields',
        'filters',
        'history_options',
        'lock_timeout',
        'log_batch_summary',
        'log_capability',
        'log_context',
        'log_retention_days',
        'log_value',
        'modules',
        'product_query_args',
        'revert_chunk',
        'revert_nullable_fields',
        'revert_relative_fields',
        'row',
        'variation_query_args',
        'variation_term_taxonomies',
        'write_keys',
    ];

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        spl_autoload_register([self::class, 'aliasOldClass']);

        foreach (['VERSION', 'FILE', 'PATH', 'HISTORY'] as $name) {
            if (defined('ANTIJANTE_PRODUCTS_LIST_'.$name) && ! defined('WC_PRODUCTS_LIST_'.$name)) {
                define('WC_PRODUCTS_LIST_'.$name, constant('ANTIJANTE_PRODUCTS_LIST_'.$name));
            }
        }

        if (function_exists('add_filter')) {
            self::bridgeHooks();
        }
    }

    /** `GeneroWP\ProductsList\X` loads as an alias of `Antijante\ProductsList\X`. */
    public static function aliasOldClass(string $class): void
    {
        if (strncmp($class, self::OLD_NAMESPACE, strlen(self::OLD_NAMESPACE)) !== 0) {
            return;
        }

        $new = __NAMESPACE__.'\\'.substr($class, strlen(self::OLD_NAMESPACE));

        if (class_exists($new) || interface_exists($new) || trait_exists($new) || enum_exists($new)) {
            class_alias($new, $class);
        }
    }

    /** The old hook name for a new one, or null for a hook that is not this plugin's. */
    public static function oldHook(string $hook): ?string
    {
        return str_starts_with($hook, self::HOOK_PREFIX)
            ? self::OLD_HOOK_PREFIX.substr($hook, strlen(self::HOOK_PREFIX))
            : null;
    }

    private static function bridgeHooks(): void
    {
        foreach (self::FILTERS as $name) {
            add_filter(
                self::HOOK_PREFIX.$name,
                static fn (...$args) => apply_filters_ref_array(self::OLD_HOOK_PREFIX.$name, $args),
                10,
                99,
            );
        }

        foreach (self::ACTIONS as $name) {
            add_action(
                self::HOOK_PREFIX.$name,
                static function (...$args) use ($name): void {
                    do_action_ref_array(self::OLD_HOOK_PREFIX.$name, $args);
                },
                10,
                99,
            );
        }
    }
}
