<?php
/**
 * Monetico payment notifications (IPN, "interface retour" or CGI2).
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use DateTime;
use WSForm_Monetico\Monetico\Seal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks a notification and updates the payment status.
 *
 * Order of checks: the seal first, with the key of the TPE named in the
 * notification, then the submission, then TPE and amount against what was
 * stored when the form was submitted. Nothing is trusted before the seal.
 */
class Ipn_Handler {

	/**
	 * Return codes meaning the payment is accepted.
	 */
	const PAID_CODES = array( 'paiement', 'payetest' );

	/**
	 * Return codes meaning the payment is refused or abandoned.
	 */
	const FAILED_CODES = array( 'annulation' );

	/**
	 * Attempts to get the submission lock before giving up.
	 */
	const LOCK_ATTEMPTS = 10;

	/**
	 * Process a notification.
	 *
	 * @param array<string,mixed> $payload POST fields sent by Monetico.
	 *
	 * @return array{accepted:bool,http_status:int}
	 */
	public function process( array $payload ) {
		$fields    = $this->keep_strings( $payload );
		$reference = $fields['reference'] ?? '';
		$received  = $fields['MAC'] ?? '';
		$account   = Accounts::get( $fields['TPE'] ?? '' );

		if ( '' === $reference || '' === $received || null === $account ) {
			return $this->reject( 'Incomplete notification or unknown TPE', $reference, 400 );
		}

		unset( $fields['MAC'] );
		if ( ! Seal::verify( $fields, $account['key'], $received ) ) {
			return $this->reject( 'Invalid seal', $reference, 403 );
		}

		$submission = Submission_Meta::find_by_reference( $reference );
		if ( null === $submission ) {
			return $this->reject( 'Unknown reference', $reference, 404 );
		}

		$submission_id = absint( $submission->id );
		if ( ! $this->wait_for_lock( $submission_id ) ) {
			return $this->reject( 'Submission locked by another notification', $reference, 503 );
		}

		try {
			return $this->apply( $submission_id, $fields );
		} finally {
			Submission_Meta::unlock( $submission_id );
		}
	}

	/**
	 * Check the notification against the stored payment and update the status.
	 *
	 * Runs under the submission lock, on a fresh copy of the submission, so
	 * that two identical notifications cannot both see "pending".
	 *
	 * @param int                  $submission_id Submission ID.
	 * @param array<string,string> $fields        Sealed fields.
	 *
	 * @return array{accepted:bool,http_status:int}
	 */
	private function apply( $submission_id, array $fields ) {
		$submission = Submission_Reader::get( $submission_id );
		$reference  = $fields['reference'];

		if ( null === $submission || Submission_Meta::get( $submission, 'tpe' ) !== $fields['TPE'] ) {
			return $this->reject( 'TPE does not match the submission', $reference, 403 );
		}

		$expected_amount = Amount::normalize_monetico( Submission_Meta::get( $submission, 'amount' ) );
		if ( '' === $expected_amount || Amount::normalize_monetico( $fields['montant'] ?? '' ) !== $expected_amount ) {
			return $this->reject( 'Amount does not match the submission', $reference, 403 );
		}

		$previous_status = Submission_Meta::get( $submission, 'payment_status' );
		$new_status      = $this->get_status( $fields['code-retour'] ?? '' );

		// A paid submission never changes again, and an unknown code changes nothing.
		if ( Submission_Meta::STATUS_PAID === $previous_status || null === $new_status || $new_status === $previous_status ) {
			Logger::info(
				'ipn',
				'Notification acknowledged without status change',
				array(
					'submission_id' => $submission_id,
					'status'        => $previous_status,
					'code_retour'   => sanitize_key( $fields['code-retour'] ?? '' ),
				)
			);

			return $this->accept();
		}

		$this->save( $submission, $new_status, $fields );

		Logger::info(
			'ipn',
			'Payment status updated',
			array(
				'submission_id'   => $submission_id,
				'previous_status' => $previous_status,
				'status'          => $new_status,
			)
		);

		/**
		 * Fires once each time a notification changes a payment status.
		 *
		 * Replayed notifications do not fire it again.
		 *
		 * @param int                  $submission_id   WS Form submission ID.
		 * @param string               $new_status      "paid" or "failed".
		 * @param array<string,string> $fields          Notification fields, MAC removed.
		 * @param string               $previous_status Status before the notification.
		 */
		do_action( 'wsform_monetico_payment_updated', $submission_id, $new_status, $fields, $previous_status );

		return $this->accept();
	}

	/**
	 * Store the new status and payment details.
	 *
	 * @param \WS_Form_Submit      $submission Submission.
	 * @param string               $status     New status.
	 * @param array<string,string> $fields     Sealed fields.
	 *
	 * @return void
	 */
	private function save( $submission, $status, array $fields ) {
		$values = array( 'payment_status' => $status );

		if ( ! empty( $fields['numauto'] ) ) {
			$values['transaction_id'] = $fields['numauto'];
		}

		// Monetico sends "07/11/2026_a_14:52:46", or "07/11/2026:14:52:46", in Paris time.
		$raw_date     = str_replace( '_a_', ':', $fields['date'] ?? '' );
		$payment_date = DateTime::createFromFormat( 'd/m/Y:H:i:s', $raw_date, new \DateTimeZone( 'Europe/Paris' ) );
		if ( $payment_date ) {
			$values['payment_date'] = $payment_date;
		}

		Submission_Meta::set( $submission, $values );
	}

	/**
	 * Map a Monetico return code to a status.
	 *
	 * @param string $return_code Value of code-retour.
	 *
	 * @return string|null Null for codes that must not change the status.
	 */
	private function get_status( $return_code ) {
		$return_code = strtolower( (string) $return_code );

		if ( in_array( $return_code, self::PAID_CODES, true ) ) {
			return Submission_Meta::STATUS_PAID;
		}

		if ( in_array( $return_code, self::FAILED_CODES, true ) ) {
			return Submission_Meta::STATUS_FAILED;
		}

		return null;
	}

	/**
	 * Wait a little for a concurrent notification on the same submission.
	 *
	 * @param int $submission_id Submission ID.
	 *
	 * @return bool
	 */
	private function wait_for_lock( $submission_id ) {
		for ( $attempt = 0; $attempt < self::LOCK_ATTEMPTS; $attempt++ ) {
			if ( Submission_Meta::lock( $submission_id ) ) {
				return true;
			}

			usleep( 200000 );
		}

		return false;
	}

	/**
	 * Keep scalar POST values only, as strings.
	 *
	 * @param array<string,mixed> $payload Raw POST fields.
	 *
	 * @return array<string,string>
	 */
	private function keep_strings( array $payload ) {
		$fields = array();
		foreach ( $payload as $name => $value ) {
			if ( is_scalar( $value ) && '' !== (string) $name ) {
				$fields[ (string) $name ] = (string) $value;
			}
		}

		return $fields;
	}

	/**
	 * Build an accepted result.
	 *
	 * @return array{accepted:bool,http_status:int}
	 */
	private function accept() {
		return array(
			'accepted'    => true,
			'http_status' => 200,
		);
	}

	/**
	 * Log and build a rejected result.
	 *
	 * @param string $reason      Reason, for the log.
	 * @param string $reference   Reference as received, may be forged.
	 * @param int    $http_status HTTP status.
	 *
	 * @return array{accepted:bool,http_status:int}
	 */
	private function reject( $reason, $reference, $http_status ) {
		Logger::warning(
			'ipn',
			'Notification rejected: ' . $reason,
			array( 'reference' => substr( sanitize_text_field( $reference ), 0, 50 ) )
		);

		return array(
			'accepted'    => false,
			'http_status' => $http_status,
		);
	}
}
