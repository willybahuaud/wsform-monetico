<?php
/**
 * Monetico HMAC v3 seal (MAC field).
 *
 * @package WSForm_Monetico\Monetico
 */

namespace WSForm_Monetico\Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes and verifies the MAC sent to and received from Monetico.
 *
 * Rules from the Monetico "version 3.0" documentation: every field except MAC,
 * sorted by name in ASCII order, joined as key=value with "*", signed with
 * HMAC-SHA1 using the binary form of the 40 hex characters key.
 */
class Seal {

	/**
	 * Length of a Monetico key, in hexadecimal characters.
	 */
	const KEY_LENGTH = 40;

	/**
	 * Seal a set of fields.
	 *
	 * @param array<string,string> $fields Fields to seal, without MAC.
	 * @param string               $key    Monetico key.
	 *
	 * @return string Lowercase hexadecimal seal, or an empty string when the key is unusable.
	 */
	public static function compute( array $fields, $key ) {
		$binary_key = self::get_binary_key( $key );
		if ( '' === $binary_key ) {
			return '';
		}

		return hash_hmac( 'sha1', self::get_string_to_seal( $fields ), $binary_key );
	}

	/**
	 * Check a received seal in constant time.
	 *
	 * @param array<string,string> $fields        Received fields, without MAC.
	 * @param string               $key           Monetico key.
	 * @param string               $received_seal MAC sent by Monetico.
	 *
	 * @return bool
	 */
	public static function verify( array $fields, $key, $received_seal ) {
		$expected_seal = self::compute( $fields, $key );
		if ( '' === $expected_seal ) {
			return false;
		}

		return hash_equals( $expected_seal, strtolower( (string) $received_seal ) );
	}

	/**
	 * Build the string Monetico signs.
	 *
	 * @param array<string,string> $fields Fields without MAC.
	 *
	 * @return string
	 */
	public static function get_string_to_seal( array $fields ) {
		unset( $fields['MAC'] );
		ksort( $fields, SORT_STRING );

		$pairs = array();
		foreach ( $fields as $name => $value ) {
			$pairs[] = $name . '=' . $value;
		}

		return implode( '*', $pairs );
	}

	/**
	 * Tell whether a key can be used to seal.
	 *
	 * @param string $key Monetico key.
	 *
	 * @return bool
	 */
	public static function is_valid_key( $key ) {
		return '' !== self::get_binary_key( $key );
	}

	/**
	 * Convert the key to binary.
	 *
	 * Some old keys end with two non hexadecimal characters. Monetico documents
	 * how to convert them: subtract 23 from the ASCII code of the next to last
	 * character, and replace a final "M" with "0".
	 *
	 * @param string $key Monetico key as provided in the merchant back office.
	 *
	 * @return string Binary key, or an empty string when the key is invalid.
	 */
	private static function get_binary_key( $key ) {
		$key = preg_replace( '/\s+/', '', (string) $key );
		if ( self::KEY_LENGTH !== strlen( $key ) ) {
			return '';
		}

		$next_to_last = $key[38];
		$last         = $key[39];

		if ( ! ctype_xdigit( $next_to_last ) ) {
			$next_to_last = chr( ord( $next_to_last ) - 23 );
		}

		if ( 'M' === $last ) {
			$last = '0';
		}

		$key = substr( $key, 0, 38 ) . $next_to_last . $last;
		if ( ! ctype_xdigit( $key ) ) {
			return '';
		}

		return (string) hex2bin( $key );
	}
}
