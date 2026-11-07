<?php
/**
 * Payment preparation.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use RuntimeException;
use WSForm_Monetico\Monetico\Payment_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a payment when the form is submitted, then builds the request
 * posted to Monetico from what was stored at that moment.
 */
class Payment_Builder {

	/**
	 * Length of the token that protects the redirect and status pages.
	 */
	const ACCESS_KEY_LENGTH = 32;

	/**
	 * Monetico limit for texte-libre.
	 */
	const TEXTE_LIBRE_MAX_LENGTH = 3200;

	/**
	 * Store the payment data on a new submission.
	 *
	 * The amount, TPE and reference saved here are the values the IPN checks
	 * later, so they are fixed once and for all.
	 *
	 * @param \WS_Form_Submit     $submission Submission.
	 * @param array<string,mixed> $settings   Action settings, see Action_Settings::from_config().
	 *
	 * @return array{reference:string,access_key:string}
	 *
	 * @throws RuntimeException When the action is misconfigured or the amount is invalid.
	 */
	public static function start( $submission, array $settings ) {
		if ( null === Accounts::get( $settings['tpe'] ) ) {
			throw new RuntimeException( 'No Monetico account for the selected TPE.' );
		}

		if ( Submission_Meta::STATUS_PAID === Submission_Meta::get( $submission, 'payment_status' ) ) {
			throw new RuntimeException( 'Submission already paid.' );
		}

		$amount = Amount::parse( Submission_Reader::get_field_value( $submission, $settings['amount_field'] ) );
		if ( $amount <= 0 ) {
			throw new RuntimeException( 'The amount must be greater than zero.' );
		}

		$reference  = sprintf( 'WSF-%d-%d-%d', absint( $submission->form_id ), absint( $submission->id ), time() );
		$access_key = wp_generate_password( self::ACCESS_KEY_LENGTH, false );

		Submission_Meta::set(
			$submission,
			array(
				'payment_reference' => $reference,
				'payment_status'    => Submission_Meta::STATUS_PENDING,
				'tpe'               => $settings['tpe'],
				'environment'       => $settings['environment'],
				'amount'            => Amount::to_monetico( $amount, $settings['currency'] ),
				'access_key'        => $access_key,
				'action_identifier' => $settings['action_identifier'],
			)
		);

		return array(
			'reference'  => $reference,
			'access_key' => $access_key,
		);
	}

	/**
	 * Build the request posted to Monetico.
	 *
	 * Only reads the submission: opening the redirect page again must never
	 * change a payment.
	 *
	 * @param \WS_Form_Submit     $submission Submission.
	 * @param array<string,mixed> $settings   Action settings.
	 *
	 * @return array{endpoint:string,fields:array<string,string>}
	 *
	 * @throws RuntimeException When the account is gone.
	 */
	public static function build_request( $submission, array $settings ) {
		$account = Accounts::get( Submission_Meta::get( $submission, 'tpe' ) );
		if ( null === $account ) {
			throw new RuntimeException( 'No Monetico account for the stored TPE.' );
		}

		$reference  = Submission_Meta::get( $submission, 'payment_reference' );
		$access_key = Submission_Meta::get( $submission, 'access_key' );
		$email      = sanitize_email( Submission_Reader::get_field_text( $submission, $settings['email_field'] ) );

		$fields = Payment_Form::build_fields(
			$account,
			array(
				'reference'   => $reference,
				'amount'      => Submission_Meta::get( $submission, 'amount' ),
				'date'        => new \DateTime( 'now', wp_timezone() ),
				'language'    => $settings['language'],
				'email'       => $email,
				'texte_libre' => self::resolve_texte_libre( $settings['texte_libre'], $submission, $reference ),
				'success_url' => self::add_status_arguments( $settings['success_url'], $reference, $access_key ),
				'failure_url' => self::add_status_arguments( $settings['failure_url'], $reference, $access_key ),
			)
		);

		return array(
			'endpoint' => Payment_Form::get_endpoint( Submission_Meta::get( $submission, 'environment' ) ),
			'fields'   => $fields,
		);
	}

	/**
	 * Add the reference and access key to a return URL, for the status block.
	 *
	 * @param string $url        Return URL from the settings.
	 * @param string $reference  Payment reference.
	 * @param string $access_key Access key.
	 *
	 * @return string Empty when no URL is set.
	 */
	public static function add_status_arguments( $url, $reference, $access_key ) {
		if ( '' === (string) $url ) {
			return '';
		}

		return add_query_arg(
			array(
				'reference'            => rawurlencode( $reference ),
				Redirect_Page::KEY_VAR => rawurlencode( $access_key ),
			),
			$url
		);
	}

	/**
	 * Replace {{field:ID}} and {{reference}} in the free text.
	 *
	 * @param string          $template   Free text from the settings.
	 * @param \WS_Form_Submit $submission Submission.
	 * @param string          $reference  Payment reference.
	 *
	 * @return string
	 */
	private static function resolve_texte_libre( $template, $submission, $reference ) {
		if ( '' === trim( (string) $template ) ) {
			return '';
		}

		$text = preg_replace( '/{{\s*reference\s*}}/i', $reference, $template );
		$text = Submission_Reader::replace_field_tags( (string) $text, $submission );

		$text = trim( (string) preg_replace( '/\s+/', ' ', sanitize_textarea_field( $text ) ) );

		return mb_substr( $text, 0, self::TEXTE_LIBRE_MAX_LENGTH );
	}
}
