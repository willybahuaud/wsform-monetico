<?php
/**
 * Payment status block and shortcode.
 *
 * @package WSForm_Monetico\Blocks
 */

namespace WSForm_Monetico\Blocks;

use WSForm_Monetico\Amount;
use WSForm_Monetico\Plugin;
use WSForm_Monetico\Redirect_Page;
use WSForm_Monetico\Submission_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the status of a payment on the return pages.
 *
 * Without a reference attribute, the payment comes from the URL Monetico
 * sends the payer back to, which carries the reference and the access key.
 */
class Payment_Status_Block {

	const BLOCK_NAME = 'wsform-monetico/payment-status';
	const SHORTCODE  = 'wsform_monetico_status';
	const HANDLE     = 'wsform-monetico-payment-status';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'register' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register assets and the block type.
	 *
	 * @return void
	 */
	public function register() {
		wp_register_style( self::HANDLE, Plugin::get_url( 'assets/css/blocks/payment-status-block.css' ), array(), Plugin::VERSION );
		wp_register_script(
			self::HANDLE . '-block',
			Plugin::get_url( 'assets/js/blocks/payment-status-block.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ),
			Plugin::VERSION,
			true
		);

		register_block_type(
			self::BLOCK_NAME,
			array(
				'editor_script'   => self::HANDLE . '-block',
				'editor_style'    => self::HANDLE,
				'style'           => self::HANDLE,
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'reference'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'showDetails' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'messageType' => array(
						'type'    => 'string',
						'default' => 'auto',
					),
				),
			)
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string,string>|string $shortcode_attributes Attributes.
	 *
	 * @return string
	 */
	public function render_shortcode( $shortcode_attributes ) {
		$attributes = shortcode_atts(
			array(
				'reference'   => '',
				'showdetails' => 'true',
				'messagetype' => 'auto',
			),
			$shortcode_attributes,
			self::SHORTCODE
		);

		return $this->render(
			array(
				'reference'   => $attributes['reference'],
				'showDetails' => in_array( strtolower( $attributes['showdetails'] ), array( '1', 'true', 'yes', 'on' ), true ),
				'messageType' => $attributes['messagetype'],
			)
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 *
	 * @return string
	 */
	public function render( $attributes ) {
		wp_enqueue_style( self::HANDLE );

		$submission   = $this->find_submission( (string) ( $attributes['reference'] ?? '' ) );
		$show_details = ! empty( $attributes['showDetails'] );
		$status       = $submission ? Submission_Meta::get( $submission, 'payment_status' ) : 'na';
		$status       = '' === $status ? Submission_Meta::STATUS_PENDING : $status;

		ob_start();
		load_template(
			Plugin::PATH . 'templates/payment-status.php',
			false,
			array(
				'status_label' => $this->get_status_label( $status ),
				'status_class' => 'wsf-monetico-status-' . $status,
				'message'      => $submission ? $this->get_message( $status, (string) ( $attributes['messageType'] ?? 'auto' ) ) : __( 'Paiement introuvable.', 'wsform-monetico' ),
				/* translators: %s: payment reference. */
				'subtitle'     => $submission ? sprintf( __( 'Référence : %s', 'wsform-monetico' ), Submission_Meta::get( $submission, 'payment_reference' ) ) : '',
				'details'      => ( $submission && $show_details ) ? $this->get_details( $submission ) : array(),
				'show_details' => $show_details,
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * Find the payment to show.
	 *
	 * @param string $reference Reference set in the block, empty to read the URL.
	 *
	 * @return \WS_Form_Submit|null
	 */
	private function find_submission( $reference ) {
		if ( '' !== $reference ) {
			return Submission_Meta::find_by_reference( $reference );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only page, protected by the access key.
		$reference  = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
		$access_key = isset( $_GET[ Redirect_Page::KEY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ Redirect_Page::KEY_VAR ] ) ) : '';
		// phpcs:enable

		$submission = Submission_Meta::find_by_reference( $reference );
		$stored_key = $submission ? Submission_Meta::get( $submission, 'access_key' ) : '';

		return ( '' !== $stored_key && '' !== $access_key && hash_equals( $stored_key, $access_key ) ) ? $submission : null;
	}

	/**
	 * Build the details list.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 *
	 * @return array<string,string>
	 */
	private function get_details( $submission ) {
		$payment_date = Submission_Meta::get( $submission, 'payment_date' );
		$details      = array(
			__( 'Montant', 'wsform-monetico' )          => Amount::to_display( Submission_Meta::get( $submission, 'amount' ) ),
			__( 'Date de paiement', 'wsform-monetico' ) => $payment_date ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $payment_date ) ) : '',
			__( 'Autorisation', 'wsform-monetico' )     => Submission_Meta::get( $submission, 'transaction_id' ),
		);

		return array_filter( $details, 'strlen' );
	}

	/**
	 * Get the label of a status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			Submission_Meta::STATUS_PAID    => __( 'Payé', 'wsform-monetico' ),
			Submission_Meta::STATUS_FAILED  => __( 'Échoué', 'wsform-monetico' ),
			Submission_Meta::STATUS_PENDING => __( 'En attente', 'wsform-monetico' ),
		);

		return $labels[ $status ] ?? __( 'Inconnu', 'wsform-monetico' );
	}

	/**
	 * Get the main message.
	 *
	 * @param string $status       Status.
	 * @param string $message_type auto, success, failure or pending.
	 *
	 * @return string
	 */
	private function get_message( $status, $message_type ) {
		$messages = array(
			'success' => __( 'Merci, votre paiement est confirmé.', 'wsform-monetico' ),
			'failure' => __( 'Le paiement n’a pas été finalisé.', 'wsform-monetico' ),
			'pending' => __( 'Votre paiement est en cours de validation.', 'wsform-monetico' ),
		);

		if ( isset( $messages[ $message_type ] ) ) {
			return $messages[ $message_type ];
		}

		$message_by_status = array(
			Submission_Meta::STATUS_PAID   => 'success',
			Submission_Meta::STATUS_FAILED => 'failure',
		);

		return $messages[ $message_by_status[ $status ] ?? 'pending' ];
	}
}
