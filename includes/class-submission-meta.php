<?php
/**
 * Monetico data stored on WS Form submissions.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use DateTimeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the Monetico meta of a submission.
 *
 * Everything the IPN checks (reference, TPE, amount) is written here when the
 * form is submitted, so that the IPN never trusts a value it could not verify.
 */
class Submission_Meta {

	const META_PREFIX = '_wsform_monetico_';

	const STATUS_PENDING = 'pending';
	const STATUS_PAID    = 'paid';
	const STATUS_FAILED  = 'failed';

	const ENVIRONMENT_SANDBOX    = 'sandbox';
	const ENVIRONMENT_PRODUCTION = 'production';

	/**
	 * Seconds after which an IPN lock is considered abandoned.
	 */
	const LOCK_TIMEOUT = 60;

	/**
	 * Stored keys, without prefix.
	 */
	const KEYS = array(
		'payment_reference',
		'payment_status',
		'tpe',
		'environment',
		'amount',
		'access_key',
		'action_identifier',
		'transaction_id',
		'payment_date',
	);

	/**
	 * Read a Monetico meta.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 * @param string          $key        One of self::KEYS.
	 *
	 * @return string Empty string when missing.
	 */
	public static function get( $submission, $key ) {
		$meta_key = self::META_PREFIX . $key;

		return isset( $submission->meta[ $meta_key ] ) && is_scalar( $submission->meta[ $meta_key ] )
			? (string) $submission->meta[ $meta_key ]
			: '';
	}

	/**
	 * Write Monetico meta values.
	 *
	 * @param \WS_Form_Submit      $submission Submission, updated in place.
	 * @param array<string,string> $values     Values indexed by key from self::KEYS.
	 *
	 * @return void
	 */
	public static function set( $submission, array $values ) {
		$prefixed_values = array();

		foreach ( $values as $key => $value ) {
			if ( ! in_array( $key, self::KEYS, true ) ) {
				continue;
			}

			if ( $value instanceof DateTimeInterface ) {
				$value = $value->format( DATE_ATOM );
			}

			$prefixed_values[ self::META_PREFIX . $key ] = sanitize_text_field( (string) $value );
		}

		if ( empty( $prefixed_values ) ) {
			return;
		}

		$meta_handler            = new \WS_Form_Submit_Meta();
		$meta_handler->parent_id = absint( $submission->id );
		$meta_handler->db_update_from_array( $prefixed_values, ! empty( $submission->encrypted ) );

		foreach ( $prefixed_values as $meta_key => $value ) {
			$submission->meta[ $meta_key ] = $value;
		}
	}

	/**
	 * Find the submission a reference belongs to.
	 *
	 * References embed the submission ID (WSF-{form}-{submission}-{time}), so
	 * no meta lookup is needed, which also works on encrypted submissions.
	 *
	 * @param string $reference Monetico reference.
	 *
	 * @return \WS_Form_Submit|null
	 */
	public static function find_by_reference( $reference ) {
		$reference = (string) $reference;
		if ( ! preg_match( '/^WSF-\d+-(\d+)-\d+$/', $reference, $matches ) ) {
			return null;
		}

		$submission = Submission_Reader::get( $matches[1] );
		if ( ! $submission ) {
			return null;
		}

		$stored_reference = self::get( $submission, 'payment_reference' );

		return ( '' !== $stored_reference && hash_equals( $stored_reference, $reference ) ) ? $submission : null;
	}

	/**
	 * Take an exclusive lock on a submission, to process one IPN at a time.
	 *
	 * The add_option() call fails when the option exists, which makes it atomic.
	 *
	 * @param int $submission_id Submission ID.
	 *
	 * @return bool False when another request holds the lock.
	 */
	public static function lock( $submission_id ) {
		$option_name = self::get_lock_name( $submission_id );

		if ( add_option( $option_name, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( $option_name, 0 );
		if ( $locked_at > 0 && time() - $locked_at < self::LOCK_TIMEOUT ) {
			return false;
		}

		delete_option( $option_name );

		return add_option( $option_name, time(), '', false );
	}

	/**
	 * Release the lock taken by lock().
	 *
	 * @param int $submission_id Submission ID.
	 *
	 * @return void
	 */
	public static function unlock( $submission_id ) {
		delete_option( self::get_lock_name( $submission_id ) );
	}

	/**
	 * Get the lock option name.
	 *
	 * @param int $submission_id Submission ID.
	 *
	 * @return string
	 */
	private static function get_lock_name( $submission_id ) {
		return 'wsform_monetico_lock_' . absint( $submission_id );
	}
}
