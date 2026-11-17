<?php

namespace Antijante\ProductsList\Fields;

use Antijante\ProductsList\Registry;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;
use WC_REST_Exception;
use WP_REST_Request;
use WP_Term;

/**
 * Declarative `terms` fields (docs/contracts.md §6): a product's terms in one
 * taxonomy, edited by name.
 *
 * - The row carries them under `wcpl_terms.{taxonomy}`: a list of names
 *   (`multiple`), one name, or for a `numeric` field the number the term's
 *   name holds ("12.5" for the term "12.5"), so a person types 12.5 and never
 *   meets a term.
 * - A save sends names (or the number) under the same key. Each is matched to
 *   an existing term by name, slug or, for a numeric field, by value; a
 *   missing one is made when the field allows it (`create`, always for
 *   `numeric`), else the save is refused naming it.
 * - On a product a product attribute taxonomy (`pa_*`) is written through the
 *   product's attributes, so WooCommerce keeps the attribute and its terms in
 *   step; its "used for variations" and "visible" flags are kept. Any other
 *   taxonomy is set on the post after the save.
 * - On a variation the field is the variation's own value of an attribute its
 *   parent uses for variations: one of the parent's terms.
 * - The log reads the same value (`antijante_products_list/log_value`), so
 *   History shows names and a revert writes them back through this save.
 */
final class TermFields
{
    /** Row and request key. */
    public const KEY = 'wcpl_terms';

    public const ERROR = 'wc_products_list_invalid_term';

    /** @var array<int, array{0: int, 1: string, 2: array<int, int>}> taxonomy terms to set once a product is saved */
    private static array $pending = [];

    public static function register(): void
    {
        add_filter('antijante_products_list/row', [self::class, 'row'], 10, 3);
        add_action('antijante_products_list/save', [self::class, 'save'], 10, 3);
        add_filter('antijante_products_list/log_value', [self::class, 'logValue'], 10, 4);
        add_action('woocommerce_rest_insert_product_object', [self::class, 'afterInsert'], 15, 1);
        add_action('woocommerce_rest_insert_product_variation_object', [self::class, 'afterInsert'], 15, 1);
    }

    /**
     * The declared terms fields, by taxonomy.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $out = [];

        foreach (Registry::fields() as $def) {
            if ($def['type'] === 'terms' && isset($def['taxonomy'])) {
                $out[$def['taxonomy']] = $def;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function row(array $data, WC_Product $product, ?WP_REST_Request $request = null): array
    {
        $fields = self::fields();

        if ($fields === [] || ! self::requested($request)) {
            return $data;
        }

        $values = [];

        foreach ($fields as $taxonomy => $def) {
            if (self::applies($def, $product)) {
                $values[$taxonomy] = self::value($product, $def);
            }
        }

        $data[self::KEY] = (object) $values;

        return $data;
    }

    /**
     * The field's value on a product: names, one name, or a number.
     *
     * @param  array<string, mixed>  $def
     */
    public static function value(WC_Product $product, array $def): mixed
    {
        $taxonomy = (string) $def['taxonomy'];

        if ($product instanceof WC_Product_Variation) {
            $slug = (string) ($product->get_attributes()[sanitize_title($taxonomy)] ?? '');
            $term = $slug !== '' ? get_term_by('slug', $slug, $taxonomy) : false;
            $names = $term instanceof WP_Term ? [$term->name] : [];
        } else {
            $terms = wp_get_post_terms($product->get_id(), $taxonomy, ['orderby' => 'term_order']);
            $names = is_array($terms) ? array_values(array_map(static fn (WP_Term $term) => $term->name, $terms)) : [];
        }

        if (! empty($def['numeric'])) {
            return $names === [] ? '' : self::number($names[0]);
        }

        return ! empty($def['multiple']) ? $names : ($names[0] ?? '');
    }

    /**
     * Applies `wcpl_terms` from the request to the product before it is saved.
     */
    public static function save(WC_Product $product, WP_REST_Request $request, bool $creating = false): void
    {
        $input = $request->get_param(self::KEY);

        if (! is_array($input)) {
            return;
        }

        $fields = self::fields();

        foreach ($input as $taxonomy => $value) {
            $def = $fields[(string) $taxonomy] ?? null;

            if ($def === null || ! empty($def['readonly']) || ! self::applies($def, $product)) {
                continue;
            }

            $ids = self::resolve($def, $value, $product);

            if ($product instanceof WC_Product_Variation) {
                self::setVariation($product, (string) $taxonomy, $ids);
            } elseif (taxonomy_is_product_attribute((string) $taxonomy)) {
                self::setAttribute($product, (string) $taxonomy, $ids);
            } else {
                self::$pending[] = [$product->get_id(), (string) $taxonomy, $ids];
            }
        }
    }

    /** Sets the plain taxonomies once the product exists and is saved. */
    public static function afterInsert(WC_Product $product): void
    {
        foreach (self::$pending as $key => [$id, $taxonomy, $ids]) {
            if ($id === 0 || $id === $product->get_id()) {
                wp_set_object_terms($product->get_id(), $ids, $taxonomy);
                unset(self::$pending[$key]);
            }
        }
    }

    /**
     * The logged value of `wcpl_terms.{taxonomy}`, as the row shows it.
     *
     * @param  array<int, string>  $segments
     */
    public static function logValue(mixed $value, string $path, WC_Product $product, array $segments = []): mixed
    {
        if (($segments[0] ?? '') !== self::KEY || ! isset($segments[1])) {
            return $value;
        }

        $def = self::fields()[$segments[1]] ?? null;

        return $def === null ? $value : self::value($product, $def);
    }

    /**
     * Term ids for the sent names or number, made where the field allows it.
     *
     * @param  array<string, mixed>  $def
     * @return array<int, int>
     *
     * @throws WC_REST_Exception
     */
    public static function resolve(array $def, mixed $value, ?WC_Product $product = null): array
    {
        $taxonomy = (string) $def['taxonomy'];
        $numeric = ! empty($def['numeric']);
        $names = array_values(array_filter(
            array_map(static fn ($name) => trim(is_scalar($name) ? (string) $name : ''), is_array($value) ? $value : [$value]),
            static fn (string $name) => $name !== ''
        ));

        if (empty($def['multiple'])) {
            $names = array_slice($names, 0, 1);
        }

        $ids = [];

        foreach ($names as $name) {
            if ($numeric) {
                $number = self::parseNumber($name);

                if ($number === null) {
                    throw new WC_REST_Exception(self::ERROR, sprintf(__('%1$s: "%2$s" is not a number.', 'antijante-products-list'), $def['label'], $name), 400);
                }

                $ids[] = self::numberTerm($taxonomy, $number);

                continue;
            }

            $term = get_term_by('name', $name, $taxonomy) ?: get_term_by('slug', sanitize_title($name), $taxonomy);

            if ($term instanceof WP_Term) {
                $ids[] = $term->term_id;

                continue;
            }

            if (empty($def['create']) || $product instanceof WC_Product_Variation) {
                throw new WC_REST_Exception(self::ERROR, sprintf(__('%1$s: there is no "%2$s".', 'antijante-products-list'), $def['label'], $name), 400);
            }

            $ids[] = self::make($taxonomy, $name);
        }

        return array_values(array_unique($ids));
    }

    /** "12,5", "12.5", " 12.50 " → 12.5; anything else → null. */
    public static function parseNumber(string $text): ?float
    {
        $text = str_replace([' ', "\u{00A0}"], '', trim($text));
        $text = str_replace(',', '.', $text);

        return preg_match('/^-?\d+(\.\d+)?$/', $text) ? (float) $text : null;
    }

    /** A number as a term name: "12.5", "10", "0.54". */
    public static function number(string|float $value): string
    {
        $number = is_float($value) ? $value : self::parseNumber($value);

        if ($number === null) {
            return (string) $value;
        }

        $text = rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /** The term whose name holds this number, made when there is none. */
    private static function numberTerm(string $taxonomy, float $number): int
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

        foreach (is_array($terms) ? $terms : [] as $term) {
            $value = self::parseNumber($term->name);

            if ($value !== null && abs($value - $number) < 0.000001) {
                return $term->term_id;
            }
        }

        return self::make($taxonomy, self::number($number));
    }

    /** @throws WC_REST_Exception */
    private static function make(string $taxonomy, string $name): int
    {
        if (! current_user_can(get_taxonomy($taxonomy)?->cap->manage_terms ?? 'manage_product_terms')) {
            throw new WC_REST_Exception(self::ERROR, sprintf(__('You may not add "%s".', 'antijante-products-list'), $name), 403);
        }

        $made = wp_insert_term($name, $taxonomy);

        if (is_wp_error($made)) {
            $existing = $made->get_error_data('term_exists');

            if (is_numeric($existing)) {
                return (int) $existing;
            }

            throw new WC_REST_Exception(self::ERROR, $made->get_error_message(), 400);
        }

        return (int) $made['term_id'];
    }

    /** @param array<int, int> $ids */
    private static function setAttribute(WC_Product $product, string $taxonomy, array $ids): void
    {
        $attributes = $product->get_attributes();
        $existing = $attributes[$taxonomy] ?? null;

        if ($ids === []) {
            if ($existing instanceof WC_Product_Attribute && $existing->get_variation()) {
                throw new WC_REST_Exception(self::ERROR, __('An attribute the variations are made from cannot be emptied here.', 'antijante-products-list'), 400);
            }

            unset($attributes[$taxonomy]);
            $product->set_attributes($attributes);

            return;
        }

        // A copy: WooCommerce sees a change only when the attribute list differs from the stored one,
        // and changing the stored object in place leaves the two the same.
        $attribute = $existing instanceof WC_Product_Attribute ? clone $existing : new WC_Product_Attribute;

        if (! $existing instanceof WC_Product_Attribute) {
            $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
            $attribute->set_name($taxonomy);
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            $attribute->set_position(count($attributes));
        }

        $attribute->set_options($ids);
        $attributes[$taxonomy] = $attribute;
        $product->set_attributes($attributes);
    }

    /**
     * @param  array<int, int>  $ids
     *
     * @throws WC_REST_Exception
     */
    private static function setVariation(WC_Product_Variation $variation, string $taxonomy, array $ids): void
    {
        $parent = wc_get_product($variation->get_parent_id());
        $allowed = $parent ? wc_get_product_terms($parent->get_id(), $taxonomy, ['fields' => 'ids']) : [];
        $id = $ids[0] ?? 0;

        if ($id !== 0 && ! in_array($id, array_map('intval', $allowed), true)) {
            throw new WC_REST_Exception(self::ERROR, __('Choose one of the values the product has for this attribute.', 'antijante-products-list'), 400);
        }

        $term = $id !== 0 ? get_term($id, $taxonomy) : null;
        $attributes = $variation->get_attributes();
        $attributes[sanitize_title($taxonomy)] = $term instanceof WP_Term ? $term->slug : '';
        $variation->set_attributes($attributes);
    }

    /** @param array<string, mixed> $def */
    private static function applies(array $def, WC_Product $product): bool
    {
        if ($product instanceof WC_Product_Variation) {
            return ! empty($def['applies']['variation']);
        }

        $types = $def['applies']['product'] ?? true;

        return $types === true || in_array($product->get_type(), (array) $types, true);
    }

    /** Whether the row asks for the key: every row, unless `_fields` names others only. */
    private static function requested(?WP_REST_Request $request): bool
    {
        $fields = $request?->get_param('_fields');

        if ($fields === null || $fields === '') {
            return true;
        }

        $list = is_array($fields) ? $fields : explode(',', (string) $fields);

        return in_array(self::KEY, array_map('trim', $list), true);
    }
}
