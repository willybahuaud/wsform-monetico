<?php
/**
 * Bootstrap for unit tests that run without WordPress.
 *
 * @package WSForm_Monetico\Tests
 */

define( 'ABSPATH', __DIR__ . '/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WordPress constant the plugin files check.

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-autoloader.php';

WSForm_Monetico\Autoloader::register();
