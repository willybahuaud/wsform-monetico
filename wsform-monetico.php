<?php
/**
 * Plugin Name:       Paiement Monetico pour WS Form
 * Plugin URI:        https://github.com/willybahuaud/wsform-monetico
 * Description:       Ajoute une action de paiement par carte bancaire Monetico aux formulaires WS Form PRO.
 * Version:           1.0.1
 * Author:            Willy Bahuaud
 * Author URI:        https://wabeo.fr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wsform-monetico
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Update URI:        https://github.com/willybahuaud/wsform-monetico
 *
 * @package WSForm_Monetico
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSFORM_MONETICO_FILE', __FILE__ );
define( 'WSFORM_MONETICO_PATH', plugin_dir_path( __FILE__ ) );

require_once WSFORM_MONETICO_PATH . 'includes/class-autoloader.php';
WSForm_Monetico\Autoloader::register();

add_action( 'plugins_loaded', 'wsform_monetico_bootstrap' );
add_action( 'init', 'wsform_monetico_boot_updater' );

/**
 * Plug GitHub releases into plugin updates.
 *
 * Booted even without WS Form PRO, so that a broken install can still update.
 *
 * @return void
 */
function wsform_monetico_boot_updater() {
	$updater = new WSForm_Monetico\Updater\Updater(
		'willybahuaud',
		'wsform-monetico',
		WSFORM_MONETICO_FILE,
		WSForm_Monetico\Plugin::VERSION,
		__( 'Paiement Monetico pour WS Form', 'wsform-monetico' )
	);
	$updater->boot();
}

/**
 * Start the plugin when WS Form PRO is active.
 *
 * @return void
 */
function wsform_monetico_bootstrap() {
	// WS_Form_Action only ships with WS Form PRO, and the action extends it.
	if ( ! class_exists( 'WS_Form_Action' ) || ! defined( 'WS_FORM_EDITION' ) || 'pro' !== WS_FORM_EDITION ) {
		add_action( 'admin_notices', 'wsform_monetico_missing_ws_form_notice' );
		return;
	}

	WSForm_Monetico\Plugin::boot();
}

/**
 * Tell administrators that WS Form PRO is required.
 *
 * @return void
 */
function wsform_monetico_missing_ws_form_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Paiement Monetico pour WS Form a besoin de WS Form PRO pour fonctionner.', 'wsform-monetico' )
	);
}
