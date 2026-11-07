<?php
/**
 * Monetico accounts (TPE, company code, key).
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use WSForm_Monetico\Monetico\Seal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads Monetico credentials, kept away from WS Form forms.
 *
 * Keys never live in a form: WS Form copies form settings into exports,
 * clones, published form data and every submission row. Accounts come from
 * the WSFORM_MONETICO_ACCOUNTS constant first, then from the settings page.
 *
 * Constant format, in wp-config.php:
 *
 *     define( 'WSFORM_MONETICO_ACCOUNTS', array(
 *         '1234567' => array( 'company' => 'mycompany', 'key' => '40 hex characters' ),
 *     ) );
 */
class Accounts {

	/**
	 * Option storing accounts saved from the settings page.
	 */
	const OPTION_NAME = 'wsform_monetico_accounts';

	/**
	 * Optional constant holding accounts, it wins over the option.
	 */
	const CONSTANT_NAME = 'WSFORM_MONETICO_ACCOUNTS';

	/**
	 * Source label for accounts defined in wp-config.php.
	 */
	const SOURCE_CONSTANT = 'constant';

	/**
	 * Source label for accounts saved from the settings page.
	 */
	const SOURCE_OPTION = 'option';

	/**
	 * Get every usable account, indexed by TPE.
	 *
	 * @return array<string,array{tpe:string,company:string,key:string,source:string}>
	 */
	public static function get_all() {
		$accounts = self::normalize_list( self::get_option_accounts(), self::SOURCE_OPTION );

		// The constant comes last so that it overrides the option for the same TPE.
		foreach ( self::normalize_list( self::get_constant_accounts(), self::SOURCE_CONSTANT ) as $tpe => $account ) {
			$accounts[ $tpe ] = $account;
		}

		ksort( $accounts, SORT_STRING );

		return $accounts;
	}

	/**
	 * Get one account.
	 *
	 * @param string $tpe TPE number.
	 *
	 * @return array{tpe:string,company:string,key:string,source:string}|null
	 */
	public static function get( $tpe ) {
		$accounts = self::get_all();
		$tpe      = self::sanitize_tpe( $tpe );

		return isset( $accounts[ $tpe ] ) ? $accounts[ $tpe ] : null;
	}

	/**
	 * Get accounts saved from the settings page, as stored.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_option_accounts() {
		$stored = get_option( self::OPTION_NAME, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Get accounts declared in wp-config.php.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_constant_accounts() {
		if ( ! defined( self::CONSTANT_NAME ) ) {
			return array();
		}

		$declared = constant( self::CONSTANT_NAME );

		return is_array( $declared ) ? $declared : array();
	}

	/**
	 * Clean a TPE number.
	 *
	 * @param mixed $tpe Raw TPE.
	 *
	 * @return string Alphanumeric TPE, or an empty string.
	 */
	public static function sanitize_tpe( $tpe ) {
		$tpe = trim( (string) $tpe );

		return preg_match( '/^[A-Za-z0-9]{1,20}$/', $tpe ) ? $tpe : '';
	}

	/**
	 * Clean a company code.
	 *
	 * @param mixed $company Raw company code.
	 *
	 * @return string
	 */
	public static function sanitize_company( $company ) {
		$company = trim( (string) $company );

		return preg_match( '/^[A-Za-z0-9._-]{1,50}$/', $company ) ? $company : '';
	}

	/**
	 * Clean a Monetico key.
	 *
	 * @param mixed $key Raw key.
	 *
	 * @return string Key without spaces, or an empty string when unusable.
	 */
	public static function sanitize_key( $key ) {
		$key = preg_replace( '/\s+/', '', (string) $key );

		return Seal::is_valid_key( $key ) ? $key : '';
	}

	/**
	 * Keep only complete accounts and index them by TPE.
	 *
	 * @param array  $raw_accounts Accounts indexed by TPE.
	 * @param string $source       Where the accounts come from.
	 *
	 * @return array<string,array{tpe:string,company:string,key:string,source:string}>
	 */
	private static function normalize_list( array $raw_accounts, $source ) {
		$accounts = array();

		foreach ( $raw_accounts as $raw_tpe => $raw_account ) {
			if ( ! is_array( $raw_account ) ) {
				continue;
			}

			$tpe     = self::sanitize_tpe( $raw_tpe );
			$company = self::sanitize_company( $raw_account['company'] ?? '' );
			$key     = self::sanitize_key( $raw_account['key'] ?? '' );

			if ( '' === $tpe || '' === $company || '' === $key ) {
				continue;
			}

			$accounts[ $tpe ] = array(
				'tpe'     => $tpe,
				'company' => $company,
				'key'     => $key,
				'source'  => $source,
			);
		}

		return $accounts;
	}
}
