<?php
/**
 * Amount parsing and formatting.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts amounts between form values, Monetico format and display.
 *
 * Monetico expects and returns amounts like "12.50EUR".
 */
class Amount {

	/**
	 * Currencies offered in the action settings.
	 */
	const CURRENCIES = array( 'EUR', 'USD', 'GBP', 'CHF', 'CAD' );

	/**
	 * Read an amount typed or computed in a form field.
	 *
	 * Accepts "12.5", "12,50", "1 234,56", "1.234,56", "1,234.56" or "12,50 €".
	 * When both separators appear, the last one is the decimal separator.
	 *
	 * @param mixed $raw_value Field value.
	 *
	 * @return float Amount, or 0 when the value is not a positive amount.
	 */
	public static function parse( $raw_value ) {
		if ( is_array( $raw_value ) ) {
			$raw_value = reset( $raw_value );
		}

		$digits_and_separators = preg_replace( '/[^0-9.,]/', '', (string) $raw_value );
		$decimal_position      = max( (int) strrpos( $digits_and_separators, ',' ), (int) strrpos( $digits_and_separators, '.' ) );
		$has_separator         = false !== strpbrk( $digits_and_separators, ',.' );

		if ( $has_separator && strlen( $digits_and_separators ) - $decimal_position - 1 <= 2 ) {
			$integer_part  = preg_replace( '/[^0-9]/', '', substr( $digits_and_separators, 0, $decimal_position ) );
			$decimal_part  = substr( $digits_and_separators, $decimal_position + 1 );
			$numeric_value = $integer_part . '.' . $decimal_part;
		} else {
			// No separator, or three digits after it: a thousands separator.
			$numeric_value = preg_replace( '/[^0-9]/', '', $digits_and_separators );
		}

		$amount = round( (float) $numeric_value, 2 );

		return $amount > 0 ? $amount : 0.0;
	}

	/**
	 * Format an amount for Monetico.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO 4217 code.
	 *
	 * @return string For example "12.50EUR".
	 */
	public static function to_monetico( $amount, $currency ) {
		return number_format( (float) $amount, 2, '.', '' ) . self::sanitize_currency( $currency );
	}

	/**
	 * Normalize a Monetico amount so that "5EUR" and "5.00EUR" compare equal.
	 *
	 * @param string $monetico_amount Amount as sent by Monetico.
	 *
	 * @return string Normalized amount, or an empty string when malformed.
	 */
	public static function normalize_monetico( $monetico_amount ) {
		if ( ! preg_match( '/^(\d+)(?:\.(\d{1,2}))?([A-Z]{3})$/', strtoupper( trim( (string) $monetico_amount ) ), $parts ) ) {
			return '';
		}

		return $parts[1] . '.' . str_pad( $parts[2], 2, '0' ) . $parts[3];
	}

	/**
	 * Format a Monetico amount for people.
	 *
	 * @param string $monetico_amount For example "1234.50EUR".
	 *
	 * @return string For example "1 234,50 EUR", or an empty string.
	 */
	public static function to_display( $monetico_amount ) {
		$normalized = self::normalize_monetico( $monetico_amount );
		if ( '' === $normalized ) {
			return '';
		}

		$currency = substr( $normalized, -3 );
		$value    = (float) substr( $normalized, 0, -3 );

		return number_format_i18n( $value, 2 ) . ' ' . $currency;
	}

	/**
	 * Keep a currency from the supported list.
	 *
	 * @param mixed $currency Raw currency.
	 *
	 * @return string Supported currency, EUR otherwise.
	 */
	public static function sanitize_currency( $currency ) {
		$currency = strtoupper( trim( (string) $currency ) );

		return in_array( $currency, self::CURRENCIES, true ) ? $currency : 'EUR';
	}
}
