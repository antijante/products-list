<?php

namespace Antijante\ProductsList\Modules;

use Antijante\ProductsList\Actions\Action;
use Antijante\ProductsList\Actions\Delete;
use Antijante\ProductsList\Actions\Duplicate;
use Antijante\ProductsList\Actions\Feature;
use Antijante\ProductsList\Actions\Restore;
use Antijante\ProductsList\Actions\SetStatus;
use Antijante\ProductsList\Actions\Trash;
use Antijante\ProductsList\Module;
use Antijante\ProductsList\Rest\ActionsController;
use Antijante\ProductsList\Rest\Saves;
use Antijante\ProductsList\Rest\VariationsBatchController;

/**
 * Writes: the save hooks (recorder + `antijante_products_list/save`), the
 * built-in row actions, the actions REST route and the cross-parent
 * variations batch route. See docs/contracts.md.
 */
class Actions implements Module
{
    public function register(): void
    {
        Saves::register();

        // Early, so a site can replace a built-in by id at the default priority.
        add_filter(ActionsController::FILTER_HANDLERS, [$this, 'builtIns'], 5);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * @param  array<string, Action>  $handlers
     * @return array<string, Action>
     */
    public function builtIns(array $handlers): array
    {
        foreach ([
            new Trash,
            new Restore,
            new Delete,
            new Duplicate,
            new SetStatus('publish'),
            new SetStatus('draft'),
            new Feature,
        ] as $action) {
            $handlers[$action->id()] = $action;
        }

        return $handlers;
    }

    public function registerRoutes(): void
    {
        (new ActionsController)->register();
        (new VariationsBatchController)->register();
    }
}
