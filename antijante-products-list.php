<?php

/*
Plugin Name:  Antijante Products List
Plugin URI:   https://github.com/antijante/products-list
Description:  A fast DataViews product catalog for WooCommerce with variations inline, bulk editing, scheduled sales and a change log. Antijante's fork of Genero's WooCommerce Products List.
Version:      0.5.2
Requires at least: 6.8
Requires PHP: 8.2
Requires Plugins: woocommerce
WC requires at least: 11.0
Author:       Antijante, after Genero's original
Author URI:   https://github.com/antijante/products-list
License:      MIT
Text Domain:  antijante-products-list
Domain Path:  /languages
*/

use Antijante\ProductsList\Compat;
use Antijante\ProductsList\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

define('ANTIJANTE_PRODUCTS_LIST_VERSION', '0.5.2');
define('ANTIJANTE_PRODUCTS_LIST_FILE', __FILE__);
define('ANTIJANTE_PRODUCTS_LIST_PATH', __DIR__);

if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require_once __DIR__.'/vendor/autoload.php';
}

// Installed without its own vendor/ and outside a site-wide composer install
// (a source zip, or a plain checkout into plugins/): load the classes from
// src/ directly rather than fatal.
if (! class_exists(Plugin::class)) {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Antijante\\ProductsList\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $file = __DIR__.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($file)) {
            require_once $file;
        }
    });
}

// The plugin's names from before the fork keep answering (src/Compat.php).
Compat::boot();

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);

Plugin::getInstance();
