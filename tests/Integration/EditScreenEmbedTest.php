<?php

namespace Antijante\ProductsList\Tests\Integration;

use Antijante\ProductsList\Modules\EditScreenEmbed;

/**
 * The product's edit screen in quick edit's frame (Modules\EditScreenEmbed):
 * only for an editable post asked for embedded, and it leaves the post lock
 * as it was, so quick edit's Update is not kept out by its own frame.
 */
class EditScreenEmbedTest extends TestCase
{
    public function tear_down(): void
    {
        unset($_GET['post'], $_GET['action'], $_GET[EditScreenEmbed::PARAM]);

        parent::tear_down();
    }

    private function ask(int $id): void
    {
        $_GET['post'] = (string) $id;
        $_GET['action'] = 'edit';
        $_GET[EditScreenEmbed::PARAM] = '1';
    }

    public function test_only_an_editable_post_asked_for_embedded_is_the_frame(): void
    {
        $product = $this->simpleProduct();

        $this->actAs('administrator');
        $this->assertFalse(EditScreenEmbed::isEmbed());

        $this->ask($product->get_id());
        $this->assertTrue(EditScreenEmbed::isEmbed());

        $this->actAs('subscriber');
        $this->assertFalse(EditScreenEmbed::isEmbed());
    }

    public function test_the_lock_the_screen_takes_is_put_back(): void
    {
        $this->actAs('administrator');
        $free = $this->simpleProduct();
        $held = $this->simpleProduct();
        update_post_meta($held->get_id(), '_edit_lock', '1700000000:7');

        foreach ([[$free, ''], [$held, '1700000000:7']] as [$product, $before]) {
            $this->ask($product->get_id());
            $embed = new EditScreenEmbed;
            $embed->load();
            // What post.php does between the load action and printing the screen.
            wp_set_post_lock($product->get_id());
            $embed->restoreLock();

            $this->assertSame($before, (string) get_post_meta($product->get_id(), '_edit_lock', true));
        }
    }

    public function test_the_frame_is_left_out_of_document_isolation(): void
    {
        $this->actAs('administrator');
        $this->ask($this->simpleProduct()->get_id());
        add_action('load-post.php', 'wp_set_up_cross_origin_isolation');

        (new EditScreenEmbed)->sameAgent();

        $this->assertFalse(has_action('load-post.php', 'wp_set_up_cross_origin_isolation'));
    }
}
