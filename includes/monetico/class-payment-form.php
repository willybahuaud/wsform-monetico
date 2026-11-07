<?php
/**
 * Monetico payment page request.
 *
 * @package WSForm_Monetico\Monetico
 */

namespace WSForm_Monetico\Monetico;

use DateTimeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the signed fields posted to the Monetico payment page.
 */
class Payment_Form {

	const ENDPOINT_PRODUCTION = 'https://p.monetico-services.com/paiement.cgi';
	const ENDPOINT_SANDBOX    = 'https://p.monetico-services.com/test/paiement.cgi';

	/**
	 * Monetico protocol version.
	 */
	const VERSION = '3.0';

	/**
	 * Payment page languages offered in the action settings.
	 */
	const LANGUAGES = array( 'FR', 'EN', 'DE', 'ES', 'IT', 'NL', 'PT' );

	/**
	 * Get the payment page URL.
	 *
	 * @param string $environment "sandbox" or "production".
	 *
	 * @return string
	 */
	public static function get_endpoint( $environment ) {
		return 'sandbox' === $environment ? self::ENDPOINT_SANDBOX : self::ENDPOINT_PRODUCTION;
	}

	/**
	 * Build the fields, MAC included.
	 *
	 * @param array{tpe:string,company:string,key:string} $account Monetico account.
	 * @param array                                       $order {
	 *     Order data.
	 *
	 *     @type string            $reference   Unique reference.
	 *     @type string            $amount      Amount in Monetico format, "12.50EUR".
	 *     @type DateTimeInterface $date        Order date.
	 *     @type string            $language    One of self::LANGUAGES.
	 *     @type string            $email       Payer email, may be empty.
	 *     @type string            $texte_libre Free text sent back in the IPN, may be empty.
	 *     @type string            $success_url Return URL after payment, may be empty.
	 *     @type string            $failure_url Return URL after failure, may be empty.
	 * }
	 *
	 * @return array<string,string>
	 */
	public static function build_fields( array $account, array $order ) {
		$fields = array(
			'TPE'               => $account['tpe'],
			'societe'           => $account['company'],
			'version'           => self::VERSION,
			'lgue'              => self::sanitize_language( $order['language'] ?? '' ),
			'reference'         => $order['reference'],
			'date'              => $order['date']->format( 'd/m/Y:H:i:s' ),
			'montant'           => $order['amount'],
			'contexte_commande' => self::build_order_context( $order['email'] ?? '' ),
		);

		$optional_fields = array(
			'mail'           => $order['email'] ?? '',
			'texte-libre'    => $order['texte_libre'] ?? '',
			'url_retour_ok'  => $order['success_url'] ?? '',
			'url_retour_err' => $order['failure_url'] ?? '',
		);

		foreach ( $optional_fields as $name => $value ) {
			if ( '' !== (string) $value ) {
				$fields[ $name ] = (string) $value;
			}
		}

		$fields['MAC'] = Seal::compute( $fields, $account['key'] );

		return $fields;
	}

	/**
	 * Keep a supported language.
	 *
	 * @param mixed $language Raw language code.
	 *
	 * @return string
	 */
	public static function sanitize_language( $language ) {
		$language = strtoupper( trim( (string) $language ) );

		return in_array( $language, self::LANGUAGES, true ) ? $language : 'FR';
	}

	/**
	 * Build the base64 order context required for 3-D Secure 2.
	 *
	 * The form does not collect a billing address, so placeholders are sent,
	 * exactly as the Monetico PHP kit setup this plugin started from.
	 *
	 * @param string $email Payer email.
	 *
	 * @return string
	 */
	private static function build_order_context( $email ) {
		$billing = array(
			'addressLine1' => 'N/A',
			'city'         => 'N/A',
			'postalCode'   => '00000',
			'country'      => 'FR',
		);

		if ( '' !== (string) $email ) {
			$billing['email'] = (string) $email;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding required by Monetico.
		return base64_encode( (string) wp_json_encode( array( 'billing' => $billing ) ) );
	}
}
