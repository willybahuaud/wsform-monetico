<?php
/**
 * Plugin bootstrap.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use WSForm_Monetico\Admin\Settings_Page;
use WSForm_Monetico\Blocks\Payment_Status_Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts the plugin components.
 */
class Plugin {

	const VERSION = '1.0.1';

	const PATH = WSFORM_MONETICO_PATH;

	/**
	 * Start every component.
	 *
	 * Runs on plugins_loaded, once WS Form PRO is known to be active.
	 *
	 * @return void
	 */
	public static function boot() {
		$components = array(
			new Ipn_Endpoint(),
			new Redirect_Page(),
			new Action_Handler(),
			new Email_Sender(),
			new Payment_Status_Block(),
		);

		if ( is_admin() ) {
			$components[] = new Settings_Page();
			$components[] = new Submission_List();
		}

		foreach ( $components as $component ) {
			$component->boot();
		}
	}

	/**
	 * Get the URL of a plugin file.
	 *
	 * @param string $relative_path Path from the plugin folder.
	 *
	 * @return string
	 */
	public static function get_url( $relative_path ) {
		return plugins_url( $relative_path, WSFORM_MONETICO_FILE );
	}
}
