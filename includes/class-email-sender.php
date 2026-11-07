<?php
/**
 * Payment confirmation emails.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends the payer and team emails once a payment is confirmed by the IPN.
 */
class Email_Sender {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'wsform_monetico_payment_updated', array( $this, 'on_payment_updated' ), 10, 2 );
	}

	/**
	 * Get a default subject or template.
	 *
	 * @param string $setting_name email_subject, email_template, admin_email_subject or admin_email_template.
	 *
	 * @return string
	 */
	public static function get_default( $setting_name ) {
		$defaults = array(
			'email_subject'        => __( 'Confirmation de paiement {reference}', 'wsform-monetico' ),
			'email_template'       => '<p>' . __( 'Votre paiement {reference} de {amount} a bien été reçu le {date}. Merci !', 'wsform-monetico' ) . '</p>',
			'admin_email_subject'  => __( 'Nouveau paiement {reference}', 'wsform-monetico' ),
			'admin_email_template' => sprintf(
				'<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul>',
				__( 'Paiement reçu.', 'wsform-monetico' ),
				__( 'Référence : {reference}', 'wsform-monetico' ),
				__( 'Montant : {amount}', 'wsform-monetico' ),
				__( 'Date : {date}', 'wsform-monetico' ),
				__( 'Formulaire : {form_name}', 'wsform-monetico' )
			),
		);

		return $defaults[ $setting_name ] ?? '';
	}

	/**
	 * Send the emails when a payment becomes paid.
	 *
	 * Hooked to wsform_monetico_payment_updated, which fires once per status
	 * change: a replayed notification sends nothing.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $status        New status.
	 *
	 * @return void
	 */
	public function on_payment_updated( $submission_id, $status ) {
		if ( Submission_Meta::STATUS_PAID !== $status ) {
			return;
		}

		$submission = Submission_Reader::get( $submission_id );
		$settings   = $submission ? Action_Settings::from_submission( $submission ) : null;
		if ( null === $settings ) {
			return;
		}

		if ( $settings['send_email'] ) {
			$payer_email = sanitize_email( Submission_Reader::get_field_text( $submission, $settings['email_field'] ) );
			$this->send( 'email', $payer_email ? array( $payer_email ) : array(), $submission, $settings );
		}

		if ( $settings['send_email_admin'] ) {
			$this->send( 'admin_email', $settings['admin_email_recipients'], $submission, $settings );
		}
	}

	/**
	 * Build and send one email.
	 *
	 * @param string              $prefix     "email" for the payer, "admin_email" for the team.
	 * @param array<int,string>   $recipients Recipients.
	 * @param \WS_Form_Submit     $submission Submission.
	 * @param array<string,mixed> $settings   Action settings.
	 *
	 * @return void
	 */
	private function send( $prefix, array $recipients, $submission, array $settings ) {
		$submission_id = absint( $submission->id );
		if ( empty( $recipients ) ) {
			Logger::warning( 'email', 'No recipient for ' . $prefix, array( 'submission_id' => $submission_id ) );
			return;
		}

		$subject  = $settings[ $prefix . '_subject' ] ? $settings[ $prefix . '_subject' ] : self::get_default( $prefix . '_subject' );
		$template = $settings[ $prefix . '_template' ] ? $settings[ $prefix . '_template' ] : self::get_default( $prefix . '_template' );

		$subject = $this->fill( $subject, $submission, false );
		$content = $this->fill( $template, $submission, true );

		/**
		 * Filter the subject of a payment email.
		 *
		 * @param string $subject       Subject.
		 * @param int    $submission_id Submission ID.
		 * @param array  $settings      Action settings.
		 * @param string $prefix        "email" for the payer, "admin_email" for the team.
		 */
		$subject = apply_filters( 'wsform_monetico_email_subject', $subject, $submission_id, $settings, $prefix );

		/**
		 * Filter the HTML content of a payment email.
		 *
		 * @param string $content       Content.
		 * @param int    $submission_id Submission ID.
		 * @param array  $settings      Action settings.
		 * @param string $prefix        "email" for the payer, "admin_email" for the team.
		 */
		$content = apply_filters( 'wsform_monetico_email_content', $content, $submission_id, $settings, $prefix );

		$sent = wp_mail( $recipients, $subject, $content, $this->get_headers( $settings ) );

		Logger::info(
			'email',
			$sent ? 'Email sent: ' . $prefix : 'Email failed: ' . $prefix,
			array( 'submission_id' => $submission_id )
		);
	}

	/**
	 * Replace the variables of a subject or template.
	 *
	 * @param string          $text       Subject or template.
	 * @param \WS_Form_Submit $submission Submission.
	 * @param bool            $is_html    Escape values for an HTML body.
	 *
	 * @return string
	 */
	private function fill( $text, $submission, $is_html ) {
		$payment_date = Submission_Meta::get( $submission, 'payment_date' );
		$values       = array(
			'{reference}'     => Submission_Meta::get( $submission, 'payment_reference' ),
			'{amount}'        => Amount::to_display( Submission_Meta::get( $submission, 'amount' ) ),
			// Numeric date: month names are not translated on some multilingual sites.
			'{date}'          => $payment_date ? wp_date( 'd.m.Y H:i', (int) strtotime( $payment_date ) ) : '',
			'{form_name}'     => Submission_Reader::get_form_label( $submission ),
			'{submission_id}' => (string) absint( $submission->id ),
		);

		if ( $is_html ) {
			$values = array_map( 'esc_html', $values );
		}

		$text = Submission_Reader::replace_field_tags( strtr( $text, $values ), $submission, $is_html );

		// A subject is a single header line.
		return $is_html ? $text : trim( (string) preg_replace( '/[\r\n]+/', ' ', $text ) );
	}

	/**
	 * Build the email headers.
	 *
	 * @param array<string,mixed> $settings Action settings.
	 *
	 * @return array<int,string>
	 */
	private function get_headers( array $settings ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( $settings['email_from_address'] ) {
			$from_name = $settings['email_from_name'] ? $settings['email_from_name'] : get_bloginfo( 'name' );
			$headers[] = sprintf( 'From: %s <%s>', str_replace( array( '"', '<', '>' ), '', $from_name ), $settings['email_from_address'] );
		}

		return $headers;
	}
}
