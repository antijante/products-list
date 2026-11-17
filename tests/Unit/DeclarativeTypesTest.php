<?php

namespace Antijante\ProductsList\Tests\Unit;

use Antijante\ProductsList\Fields\TermFields;
use Antijante\ProductsList\Registry;
use PHPUnit\Framework\TestCase;

/**
 * The declarative field types the plugin reads and writes itself:
 * `textarea`, `terms` (with `numeric`) and `media` with `accept`.
 */
class DeclarativeTypesTest extends TestCase
{
    public function test_a_terms_field_reads_and_writes_its_taxonomy_through_the_plugins_key(): void
    {
        $def = Registry::normaliseField(['id' => 'max_load', 'type' => 'terms', 'taxonomy' => 'pa_max-load', 'numeric' => true, 'unit' => 't']);

        $this->assertNotNull($def);
        $this->assertSame('wcpl_terms.pa_max-load', $def['path']);
        $this->assertSame('wcpl_terms.pa_max-load', $def['writePath']);
        $this->assertSame('wcpl_terms', $def['writeKey']);
        $this->assertSame(['wcpl_terms'], $def['restFields']);
        $this->assertTrue($def['numeric'] ?? false);
        $this->assertSame('t', $def['unit'] ?? null);
    }

    public function test_a_terms_field_without_a_taxonomy_is_dropped(): void
    {
        $this->assertNull(Registry::normaliseField(['id' => 'broken', 'type' => 'terms']));
        $this->assertNull(Registry::normaliseField(['id' => 'broken', 'type' => 'terms', 'taxonomy' => 'Not A Taxonomy!']));
    }

    public function test_a_textarea_has_rows_and_its_preview_flag(): void
    {
        $def = Registry::normaliseField(['id' => 'table', 'type' => 'textarea', 'preview' => true, 'rows' => 99]);

        $this->assertSame('textarea', $def['type'] ?? null);
        $this->assertSame(30, $def['rows'] ?? null);
        $this->assertTrue($def['preview'] ?? false);
    }

    public function test_media_is_limited_to_its_types(): void
    {
        $def = Registry::normaliseField(['id' => 'sheet', 'type' => 'media', 'accept' => ['application/pdf']]);

        $this->assertSame(['application/pdf'], $def['accept'] ?? null);
    }

    public function test_numbers_are_read_with_a_comma_or_a_point(): void
    {
        $this->assertSame(12.5, TermFields::parseNumber('12,5'));
        $this->assertSame(12.5, TermFields::parseNumber(' 12.50 '));
        $this->assertSame(1050.0, TermFields::parseNumber('1050'));
        $this->assertNull(TermFields::parseNumber('12 t'));
        $this->assertNull(TermFields::parseNumber('abc'));
    }

    public function test_a_number_is_written_as_the_site_writes_term_names(): void
    {
        $this->assertSame('12.5', TermFields::number(12.5));
        $this->assertSame('10', TermFields::number('10,0'));
        $this->assertSame('0.54', TermFields::number('0,54'));
    }
}
