<?php

namespace Antijante\ProductsList\Modules;

use Antijante\ProductsList\Module;
use Antijante\ProductsList\Rest\ClientErrorsController;
use Antijante\ProductsList\Rest\CountsController;
use Antijante\ProductsList\Rest\ListQuery;
use Antijante\ProductsList\Rest\Rows;
use Antijante\ProductsList\Rest\TermsController;
use Antijante\ProductsList\Rest\VariationsReadController;

/**
 * The read side of the REST plumbing: the list-mode query parameters, the
 * row enrichment and the wc-products-list/v1 counts, terms and
 * cross-parent variations routes. The write side (the save hook and the
 * change log, the actions route) is registered by the Log and Actions
 * modules.
 */
class Rest implements Module
{
    private ListQuery $listQuery;

    private Rows $rows;

    public function register(): void
    {
        $this->listQuery = new ListQuery;
        $this->listQuery->register();

        $this->rows = new Rows;
        $this->rows->register();

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        (new CountsController)->register_routes();
        (new ClientErrorsController)->register_routes();
        (new TermsController)->register_routes();
        (new VariationsReadController)->register();
    }

    public function listQuery(): ListQuery
    {
        return $this->listQuery;
    }

    public function rows(): Rows
    {
        return $this->rows;
    }
}
