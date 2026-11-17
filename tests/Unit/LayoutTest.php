<?php

namespace Antijante\ProductsList\Tests\Unit;

use Antijante\ProductsList\Layout;
use PHPUnit\Framework\TestCase;

/**
 * The quick edit panel's declared scopes and tabs (src/Layout.php).
 */
class LayoutTest extends TestCase
{
    public function test_scopes_and_tabs_are_normalised_and_bad_entries_dropped(): void
    {
        $layout = Layout::normalise([
            'scopes' => [
                ['id' => 'all', 'label' => '<b>All markets</b>', 'tabs' => ['general', 'Bad Id', 'specs'], 'link' => ['url' => 'https://example.com/markets', 'label' => 'Markets']],
                ['id' => 'Not valid'],
                'not an array',
            ],
            'tabs' => [
                ['id' => 'i18n:sv', 'order' => '20', 'hide' => ['i18n:sv.regular_price'], 'sections' => [['id' => 'texts', 'label' => 'Texts', 'fields' => ['i18n:sv.name']], ['label' => 'no id']]],
            ],
        ]);

        $this->assertCount(1, $layout['scopes']);
        $this->assertSame('All markets', $layout['scopes'][0]['label']);
        $this->assertSame(['general', 'specs'], $layout['scopes'][0]['tabs']);
        $this->assertSame('Markets', $layout['scopes'][0]['link']['label'] ?? null);
        $this->assertFalse($layout['scopes'][0]['missing']);
        $this->assertSame(20, $layout['tabs'][0]['order']);
        $this->assertNull($layout['tabs'][0]['label']);
        $this->assertSame(['i18n:sv.regular_price'], $layout['tabs'][0]['hide']);
        $this->assertCount(1, $layout['tabs'][0]['sections']);
    }

    public function test_nothing_declared_is_an_empty_layout(): void
    {
        $this->assertSame(['scopes' => [], 'tabs' => []], Layout::normalise([]));
    }
}
