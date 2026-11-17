<?php

namespace Antijante\ProductsList\Modules;

use Antijante\ProductsList\Fields\Preview;
use Antijante\ProductsList\Fields\TermFields;
use Antijante\ProductsList\Module;

/**
 * Declarative field types the plugin reads and writes itself: `terms`
 * (Fields\TermFields) and the preview route of `textarea` fields
 * (Fields\Preview). See docs/contracts.md §6.
 */
class Fields implements Module
{
    public function register(): void
    {
        TermFields::register();
        Preview::register();
    }
}
