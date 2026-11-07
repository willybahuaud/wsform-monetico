<?php
/**
 * Customizes the WS Form submissions list with Monetico data.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Monetico columns and assets to the WS Form submissions table.
 */
class Submission_List {

	// WS Form prints the submission meta whose key matches the column name.
	const COLUMN_STATUS = Submission_Meta::META_PREFIX . 'payment_status';

	const COLUMN_REFERENCE = Submission_Meta::META_PREFIX . 'payment_reference';

	const SCREEN_SUBMISSIONS = 'ws-form_page_ws-form-submit';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'manage_ws-form_page_ws-form-submit_columns', array( $this, 'register_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Inject Monetico columns into the submissions table.
	 *
	 * @param array $columns Existing column headers.
	 *
	 * @return array
	 */
	public function register_columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}

		if ( isset( $columns[ self::COLUMN_STATUS ] ) || isset( $columns[ self::COLUMN_REFERENCE ] ) ) {
			return $columns;
		}

		$custom_columns = array(
			self::COLUMN_STATUS    => __( 'Statut paiement', 'wsform-monetico' ),
			self::COLUMN_REFERENCE => __( 'Référence Monetico', 'wsform-monetico' ),
		);

		if ( isset( $columns['status'] ) ) {
			return $this->insert_after( $columns, 'status', $custom_columns );
		}

		return array_merge( $columns, $custom_columns );
	}

	/**
	 * Enqueue CSS and JavaScript for the submissions list.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::SCREEN_SUBMISSIONS !== $screen->id ) {
			return;
		}

		$style_url  = Plugin::get_url( 'assets/css/submission-list.css' );
		$script_url = Plugin::get_url( 'assets/js/submission-list.js' );

		wp_enqueue_style(
			'wsform-monetico-submission-list',
			$style_url,
			array(),
			Plugin::VERSION
		);

		wp_enqueue_script(
			'wsform-monetico-submission-list',
			$script_url,
			array(),
			Plugin::VERSION,
			true
		);

		$localized = array(
			'columns'      => array(
				'status'    => self::COLUMN_STATUS,
				'reference' => self::COLUMN_REFERENCE,
			),
			'statusLabels' => array(
				'pending' => __( 'En attente', 'wsform-monetico' ),
				'paid'    => __( 'Payé', 'wsform-monetico' ),
				'failed'  => __( 'Échoué', 'wsform-monetico' ),
			),
			'labels'       => array(
				'na' => __( 'N/A', 'wsform-monetico' ),
			),
		);

		wp_localize_script(
			'wsform-monetico-submission-list',
			'wsformMoneticoSubmissionList',
			$localized
		);
	}

	/**
	 * Insert columns after a given key.
	 *
	 * @param array  $columns     Existing columns.
	 * @param string $after_key The key after which to insert.
	 * @param array  $new_columns  Columns to insert.
	 *
	 * @return array
	 */
	private function insert_after( array $columns, $after_key, array $new_columns ) {
		$output = array();

		foreach ( $columns as $key => $label ) {
			$output[ $key ] = $label;

			if ( $after_key === $key ) {
				foreach ( $new_columns as $new_key => $new_label ) {
					$output[ $new_key ] = $new_label;
				}
			}
		}

		return $output;
	}
}
