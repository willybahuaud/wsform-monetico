<?php
/**
 * Class autoloader.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WSForm_Monetico classes to files named after WordPress conventions.
 *
 * WSForm_Monetico\Monetico\Seal lives in includes/monetico/class-seal.php.
 * A hand-written loader keeps Composer out of the distributed plugin.
 */
class Autoloader {

	/**
	 * Root namespace handled by this loader.
	 */
	const NAMESPACE_PREFIX = 'WSForm_Monetico\\';

	/**
	 * Register the loader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file when it belongs to the plugin.
	 *
	 * @param string $class_name Fully qualified class name.
	 *
	 * @return void
	 */
	public static function load( $class_name ) {
		if ( 0 !== strpos( $class_name, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_name = substr( $class_name, strlen( self::NAMESPACE_PREFIX ) );
		$parts         = explode( '\\', strtolower( str_replace( '_', '-', $relative_name ) ) );
		$file_name     = 'class-' . array_pop( $parts ) . '.php';
		$directory     = $parts ? implode( '/', $parts ) . '/' : '';
		$file_path     = __DIR__ . '/' . $directory . $file_name;

		if ( is_readable( $file_path ) ) {
			require_once $file_path;
		}
	}
}
