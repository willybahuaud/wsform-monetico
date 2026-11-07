<?php
/**
 * REST route receiving Monetico notifications.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes POST /wp-json/wsform-monetico/v1/ipn.
 *
 * A REST route rather than admin-ajax.php, because firewalls often block
 * server to server calls to /wp-admin/.
 */
class Ipn_Endpoint {

	const ROUTE_NAMESPACE = 'wsform-monetico/v1';
	const ROUTE_PATH      = '/ipn';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_plain_text' ), 10, 3 );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_route() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_PATH,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				// Monetico calls without credentials: the seal authenticates the request.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle a notification.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$handler = new Ipn_Handler();
		$result  = $handler->process( (array) $request->get_body_params() );

		return new WP_REST_Response( $result['accepted'] ? "version=2\ncdr=0\n" : "version=2\ncdr=1\n", $result['http_status'] );
	}

	/**
	 * Send the answer as plain text instead of JSON, as Monetico expects.
	 *
	 * Hooked to rest_pre_serve_request.
	 *
	 * @param bool             $served  Whether the request was already served.
	 * @param WP_REST_Response $result  Response.
	 * @param WP_REST_Request  $request Request.
	 *
	 * @return bool
	 */
	public function serve_plain_text( $served, $result, $request ) {
		if ( $served || self::ROUTE_NAMESPACE . self::ROUTE_PATH !== ltrim( $request->get_route(), '/' ) ) {
			return $served;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed plain text answer.

		return true;
	}

	/**
	 * Get the URL to declare in the Monetico back office.
	 *
	 * @return string
	 */
	public static function get_url() {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH );
	}
}
