<?php

namespace Antijante\ProductsList\Tests\Integration;

use Antijante\ProductsList\Log\Table;
use Antijante\ProductsList\Rest\Concurrency;

/**
 * A site that rewrites descriptions for display (`woocommerce_product_get_description`, as a theme that drops its
 * buy box from what feeds and the Store API read): the editor edits the stored text, so the conflict check and the
 * log read the stored text too. Else every description save would be refused, and an Undo would write the display
 * form over the stored one.
 */
class StoredDescriptionTest extends RestTestCase
{
    private const STORED = "<!-- wp:group --><div>Buy box</div><!-- /wp:group -->\n\n<p>About the boot.</p>";

    public function set_up(): void
    {
        parent::set_up();

        add_filter('woocommerce_product_get_description', static fn (string $text) => trim((string) preg_replace('#<!-- wp:group -->.*?<!-- /wp:group -->#s', '', $text)), 20);
    }

    public function test_a_description_saves_against_its_stored_text_and_undo_restores_that_text(): void
    {
        $product = $this->simpleProduct();
        // Stored as is: saving a product object would pass the text through the display filter first.
        global $wpdb;
        $wpdb->update($wpdb->posts, ['post_content' => self::STORED], ['ID' => $product->get_id()]);
        clean_post_cache($product->get_id());
        $edited = self::STORED.'<p>Now waterproof.</p>';

        $response = $this->request('PUT', '/wc/v3/products/'.$product->get_id(), [
            'description' => $edited,
            Concurrency::EXPECT_KEY => ['description' => self::STORED],
        ]);

        $this->assertStatus(200, $response);
        $this->assertSame($edited, get_post_field('post_content', $product->get_id(), 'raw'));

        $table = Table::name();
        $old = $wpdb->get_var($wpdb->prepare("SELECT old_value FROM {$table} WHERE batch_id = %s AND field = 'description'", $this->batchId())); // phpcs:ignore
        $this->assertSame(self::STORED, $old);

        $this->assertStatus(200, $this->request('POST', '/wc-products-list/v1/log/batch/'.$this->batchId().'/revert'));
        $this->assertSame(self::STORED, get_post_field('post_content', $product->get_id(), 'raw'));
    }
}
