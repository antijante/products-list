<?php

namespace Antijante\ProductsList\Tests\Integration;

use Antijante\ProductsList\Fields\TermFields;
use Antijante\ProductsList\Registry;
use Antijante\ProductsList\Rest\Saves;
use WC_Product_Attribute;

/**
 * Declarative `terms` fields (Fields\TermFields): the row, a save by name or
 * by number, a refused unknown name, and a revert from History.
 */
class TermFieldsTest extends RestTestCase
{
    private const LOAD = 'pa_load';

    private const FINISH = 'pa_finish';

    public function set_up(): void
    {
        parent::set_up();

        // Each test's rollback removes the attribute rows, not WooCommerce's cached list of them.
        delete_transient('wc_attribute_taxonomies');
        \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

        foreach (['load' => 'Load', 'finish' => 'Finish'] as $slug => $name) {
            global $wpdb;

            if (! $wpdb->get_var($wpdb->prepare("SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s", $slug))) {
                // WooCommerce refuses a slug whose taxonomy is registered (the last test's).
                unregister_taxonomy('pa_'.$slug);
                wc_create_attribute(['name' => $name, 'slug' => $slug]);
            }

            register_taxonomy('pa_'.$slug, ['product'], ['hierarchical' => false]);
            // What WooCommerce fills when it registers the attribute taxonomies on `init`.
            $GLOBALS['wc_product_attributes']['pa_'.$slug] = (object) ['attribute_name' => $slug, 'attribute_label' => $name];
        }

        delete_transient('wc_attribute_taxonomies');
        \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

        Saves::resetWriteKeys();
        add_filter('antijante_products_list/fields', static function (array $fields): array {
            $fields[] = ['id' => 'load', 'type' => 'terms', 'label' => 'Max load', 'taxonomy' => self::LOAD, 'numeric' => true, 'unit' => 't'];
            $fields[] = ['id' => 'finish', 'type' => 'terms', 'label' => 'Finish', 'taxonomy' => self::FINISH];

            return $fields;
        });
        Registry::reset();
    }

    public function tear_down(): void
    {
        Registry::reset();
        Saves::resetWriteKeys();

        parent::tear_down();
    }

    private function productWithLoad(string $load): \WC_Product
    {
        $term = wp_insert_term($load, self::LOAD);
        $product = $this->simpleProduct();
        $attribute = new WC_Product_Attribute;
        $attribute->set_id(wc_attribute_taxonomy_id_by_name(self::LOAD));
        $attribute->set_name(self::LOAD);
        $attribute->set_options([(int) $term['term_id']]);
        $attribute->set_visible(true);
        $product->set_attributes([self::LOAD => $attribute]);
        $product->save();

        return wc_get_product($product->get_id());
    }

    private function load(int $id): string
    {
        clean_post_cache($id);

        return wc_get_product($id)->get_attribute(self::LOAD);
    }

    public function test_the_row_carries_the_number_and_a_save_changes_the_existing_attribute(): void
    {
        $product = $this->productWithLoad('10');

        $row = $this->data($this->request('GET', '/wc/v3/products/'.$product->get_id()));
        $this->assertSame('10', ((array) ($row[TermFields::KEY] ?? []))[self::LOAD] ?? null);

        // A comma, as a person types it; the existing attribute changes (WooCommerce saw no change when
        // the stored attribute object was changed in place).
        $response = $this->request('PUT', '/wc/v3/products/'.$product->get_id(), [TermFields::KEY => [self::LOAD => '12,5']]);
        $this->assertStatus(200, $response);
        $this->assertSame('12.5', $this->load($product->get_id()));
        $this->assertTrue((bool) get_term_by('name', '12.5', self::LOAD));
    }

    public function test_an_unknown_name_is_refused_and_nothing_changes(): void
    {
        $product = $this->productWithLoad('10');

        $response = $this->request('PUT', '/wc/v3/products/'.$product->get_id(), [TermFields::KEY => [self::FINISH => 'Gold plated', self::LOAD => '11']]);
        $this->assertStatus(400, $response);
        $this->assertSame('10', $this->load($product->get_id()));
        $this->assertFalse(get_term_by('name', 'Gold plated', self::FINISH));
    }

    public function test_a_known_name_adds_the_attribute_the_product_did_not_have(): void
    {
        $product = $this->productWithLoad('10');
        wp_insert_term('Anodised', self::FINISH);

        $response = $this->request('PUT', '/wc/v3/products/'.$product->get_id(), [TermFields::KEY => [self::FINISH => 'Anodised']]);
        $this->assertStatus(200, $response);
        clean_post_cache($product->get_id());
        $this->assertSame('Anodised', wc_get_product($product->get_id())->get_attribute(self::FINISH));
    }

    public function test_history_reverts_a_terms_edit(): void
    {
        $product = $this->productWithLoad('10');

        $this->assertStatus(200, $this->request('PUT', '/wc/v3/products/'.$product->get_id(), [TermFields::KEY => [self::LOAD => '15']]));
        $this->assertSame('15', $this->load($product->get_id()));

        $this->assertStatus(200, $this->request('POST', '/wc-products-list/v1/log/batch/'.$this->batchId().'/revert'));
        $this->assertSame('10', $this->load($product->get_id()));
    }
}
