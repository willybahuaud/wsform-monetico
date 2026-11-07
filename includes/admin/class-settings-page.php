<?php
/**
 * Settings page for Monetico accounts.
 *
 * @package WSForm_Monetico\Admin
 */

namespace WSForm_Monetico\Admin;

use WSForm_Monetico\Accounts;
use WSForm_Monetico\Ipn_Endpoint;
use WSForm_Monetico\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Monetico" page under the WS Form menu to manage accounts.
 */
class Settings_Page {

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'wsform-monetico';

	/**
	 * Settings API group.
	 */
	const SETTINGS_GROUP = 'wsform_monetico';

	/**
	 * WS Form top level menu slug.
	 */
	const PARENT_SLUG = 'ws-form';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'add_page' ), 100 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Add the submenu page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Comptes Monetico', 'wsform-monetico' ),
			__( 'Monetico', 'wsform-monetico' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the accounts option.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::SETTINGS_GROUP,
			Accounts::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
	}

	/**
	 * Turn the submitted rows into the stored accounts.
	 *
	 * A blank key field keeps the stored key, so that the page never has to
	 * print a key back.
	 *
	 * @param mixed $submitted Raw option value.
	 *
	 * @return array<string,array{company:string,key:string}>
	 */
	public function sanitize( $submitted ) {
		$stored = Accounts::get_option_accounts();

		if ( ! is_array( $submitted ) || ! isset( $submitted['rows'] ) || ! is_array( $submitted['rows'] ) ) {
			return $stored;
		}

		$accounts = array();
		foreach ( $submitted['rows'] as $row ) {
			$account = $this->sanitize_row( is_array( $row ) ? $row : array(), $stored );
			if ( null !== $account ) {
				$accounts[ $account['tpe'] ] = array(
					'company' => $account['company'],
					'key'     => $account['key'],
				);
			}
		}

		return $accounts;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$template_variables = array(
			'option_accounts'   => Accounts::get_option_accounts(),
			'constant_accounts' => Accounts::get_constant_accounts(),
			'ipn_url'           => Ipn_Endpoint::get_url(),
			'option_name'       => Accounts::OPTION_NAME,
			'settings_group'    => self::SETTINGS_GROUP,
		);

		load_template( Plugin::PATH . 'templates/admin-settings.php', false, $template_variables );
	}

	/**
	 * Validate one submitted row.
	 *
	 * @param array<string,string>               $row    Submitted row.
	 * @param array<string,array<string,string>> $stored Accounts before saving.
	 *
	 * @return array{tpe:string,company:string,key:string}|null Null when the row is empty, deleted or invalid.
	 */
	private function sanitize_row( array $row, array $stored ) {
		$raw_tpe = trim( (string) ( $row['tpe'] ?? '' ) );
		if ( '' === $raw_tpe || ! empty( $row['delete'] ) ) {
			return null;
		}

		$tpe        = Accounts::sanitize_tpe( $raw_tpe );
		$company    = Accounts::sanitize_company( $row['company'] ?? '' );
		$raw_key    = trim( (string) ( $row['key'] ?? '' ) );
		$stored_key = $stored[ $tpe ]['key'] ?? '';
		$key        = '' === $raw_key ? $stored_key : Accounts::sanitize_key( $raw_key );

		if ( '' === $tpe || '' === $company || '' === $key ) {
			add_settings_error(
				Accounts::OPTION_NAME,
				'invalid-account',
				sprintf(
					/* translators: %s: TPE number as typed by the user. */
					__( 'Compte %s non enregistré : vérifiez le TPE, le code société et la clé (40 caractères hexadécimaux).', 'wsform-monetico' ),
					sanitize_text_field( $raw_tpe )
				)
			);

			// A typo must not delete an account that already works.
			return isset( $stored[ $tpe ] ) ? array_merge( array( 'tpe' => $tpe ), $stored[ $tpe ] ) : null;
		}

		return array(
			'tpe'     => $tpe,
			'company' => $company,
			'key'     => $key,
		);
	}
}
