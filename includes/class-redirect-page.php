<?php
/**
 * Intermediate page that posts the payment to Monetico.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves /?wsform_monetico_redirect={reference}&key={access key}.
 *
 * WS Form can only redirect with GET, while Monetico expects a POST: this
 * page prints the signed form and submits it.
 */
class Redirect_Page {

	/**
	 * Query argument carrying the reference.
	 */
	const QUERY_VAR = 'wsform_monetico_redirect';

	/**
	 * Query argument carrying the access key.
	 */
	const KEY_VAR = 'monetico_key';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	/**
	 * Build the redirect page URL.
	 *
	 * @param string $base_url   Home URL, with the language prefix on multilingual sites.
	 * @param string $reference  Payment reference.
	 * @param string $access_key Access key.
	 *
	 * @return string
	 */
	public static function get_url( $base_url, $reference, $access_key ) {
		return add_query_arg(
			array(
				self::QUERY_VAR => rawurlencode( $reference ),
				self::KEY_VAR   => rawurlencode( $access_key ),
			),
			$base_url
		);
	}

	/**
	 * Render the page when the query argument is present.
	 *
	 * @return void
	 */
	public function maybe_render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The access key protects this page, the link is opened from an AJAX response.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$reference  = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
		$access_key = isset( $_GET[ self::KEY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::KEY_VAR ] ) ) : '';
		// phpcs:enable

		$view = $this->build_view( $reference, $access_key );

		status_header( $view['error'] ? 404 : 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );

		load_template( Plugin::PATH . 'templates/redirect-monetico.php', false, $view );
		exit;
	}

	/**
	 * Prepare what the template shows.
	 *
	 * @param string $reference  Reference from the URL.
	 * @param string $access_key Access key from the URL.
	 *
	 * @return array{error:string,endpoint:string,fields:array<string,string>,reference:string}
	 */
	private function build_view( $reference, $access_key ) {
		$view = array(
			'error'     => '',
			'endpoint'  => '',
			'fields'    => array(),
			'reference' => '',
		);

		$submission = Submission_Meta::find_by_reference( $reference );
		$stored_key = $submission ? Submission_Meta::get( $submission, 'access_key' ) : '';

		// Same message for an unknown reference and a wrong key: nothing to guess from.
		if ( '' === $stored_key || '' === $access_key || ! hash_equals( $stored_key, $access_key ) ) {
			$view['error'] = __( 'Ce lien de paiement n’est pas valide.', 'wsform-monetico' );
			return $view;
		}

		$view['reference'] = $reference;

		if ( Submission_Meta::STATUS_PAID === Submission_Meta::get( $submission, 'payment_status' ) ) {
			$view['error'] = __( 'Ce paiement a déjà été effectué.', 'wsform-monetico' );
			return $view;
		}

		$settings = Action_Settings::from_submission( $submission );
		if ( null === $settings ) {
			$view['error'] = __( 'Le paiement ne peut pas être préparé. Contactez le site.', 'wsform-monetico' );
			return $view;
		}

		try {
			$request          = Payment_Builder::build_request( $submission, $settings );
			$view['endpoint'] = $request['endpoint'];
			$view['fields']   = $request['fields'];
		} catch ( \Exception $exception ) {
			Logger::error(
				'redirect_page',
				'Unable to build the Monetico request',
				array(
					'submission_id' => absint( $submission->id ),
					'error'         => $exception->getMessage(),
				)
			);
			$view['error'] = __( 'Le paiement ne peut pas être préparé. Contactez le site.', 'wsform-monetico' );
		}

		return $view;
	}
}
