<?php

namespace Antijante\ProductsList\Tests\Unit;

use Antijante\ProductsList\Compat;
use Antijante\ProductsList\Log\Logger;
use Antijante\ProductsList\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * The names from before the fork keep answering beside the new ones
 * (src/Compat.php), so extensions written for them work unchanged.
 */
class CompatTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        spl_autoload_register([Compat::class, 'aliasOldClass']);
    }

    public function test_an_old_class_name_is_the_new_class(): void
    {
        $old = 'GeneroWP\\ProductsList\\Log\\Logger';

        $this->assertTrue(class_exists($old));
        $this->assertSame(Logger::class, (new \ReflectionClass($old))->getName());
        // Extending, importing or calling an old name loads it; `instanceof` and is_a() do not.
        $this->assertTrue(class_exists('GeneroWP\\ProductsList\\Plugin'));
        $this->assertTrue(is_a(Plugin::class, 'GeneroWP\\ProductsList\\Plugin', true));
    }

    public function test_a_class_that_never_existed_stays_missing(): void
    {
        $this->assertFalse(class_exists('GeneroWP\\ProductsList\\NoSuchClass'));
    }

    public function test_every_hook_has_its_old_name(): void
    {
        $this->assertSame('wc_products_list/fields', Compat::oldHook('antijante_products_list/fields'));
        $this->assertSame('wc_products_list/save', Compat::oldHook('antijante_products_list/save'));
        $this->assertNull(Compat::oldHook('woocommerce_init'));
    }

    public function test_the_bridged_hooks_are_every_hook_the_plugin_runs(): void
    {
        $source = '';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $source .= file_get_contents($file->getPathname());
            }
        }
        preg_match_all("#'antijante_products_list/([a-z_/]+)'#", $source, $found);
        $run = array_values(array_unique($found[1]));
        sort($run);

        $bridged = [...Compat::FILTERS, ...Compat::ACTIONS];
        sort($bridged);

        $this->assertSame($run, $bridged, 'A hook added to src/ needs its old name in Compat::FILTERS or Compat::ACTIONS, or the list is out of date.');
    }

    public function test_what_never_changed_keeps_its_name(): void
    {
        $this->assertSame('wc-products-list/v1', Plugin::REST_NAMESPACE);
        $this->assertSame('wc-products-list', Plugin::PAGE);
        $this->assertSame('antijante-products-list', Plugin::HANDLE);
        $this->assertSame('wc-products-list', Compat::OLD_SCRIPT_HANDLE);
    }
}
