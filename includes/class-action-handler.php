<?php
/**
 * WS Form action starting a Monetico payment.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Paiement Monetico" action and runs it on submit.
 *
 * Property names and methods follow what WS_Form_Action expects.
 */
class Action_Handler extends \WS_Form_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	public $id = Action_Settings::ACTION_ID;

	/**
	 * Requires WS Form PRO.
	 *
	 * @var bool
	 */
	public $pro_required = true;

	/**
	 * Label in the actions list.
	 *
	 * @var string
	 */
	public $label = '';

	/**
	 * Label in the action select.
	 *
	 * @var string
	 */
	public $label_action = '';

	/**
	 * Runs on submit only, not on save.
	 *
	 * @var array<int,string>
	 */
	public $events = array( 'submit' );

	/**
	 * Several Monetico actions may coexist, with conditional logic.
	 *
	 * @var bool
	 */
	public $multiple = true;

	/**
	 * Has settings.
	 *
	 * @var bool
	 */
	public $configured = true;

	/**
	 * Runs after the "Save to Submissions" action, which has priority 100.
	 *
	 * @var int
	 */
	public $priority = 150;

	/**
	 * A payment must not be started again from the submissions list.
	 *
	 * @var bool
	 */
	public $can_repost = false;

	/**
	 * Not added to new forms.
	 *
	 * @var bool
	 */
	public $form_add = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'wsf_config_meta_keys', array( Action_Settings::class, 'declare_meta_keys' ), 10, 1 );
		add_action( 'init', array( $this, 'register_action' ) );
	}

	/**
	 * Register the action with WS Form.
	 *
	 * @return void
	 */
	public function register_action() {
		$this->label        = __( 'Paiement Monetico', 'wsform-monetico' );
		$this->label_action = __( 'Paiement Monetico', 'wsform-monetico' );

		parent::register( $this );
	}

	/**
	 * Start the payment and send the visitor to the redirect page.
	 *
	 * Called by WS Form on submit.
	 *
	 * @param \WS_Form_Form   $form   Form.
	 * @param \WS_Form_Submit $submit Submission.
	 * @param array           $config Action row.
	 *
	 * @return void
	 */
	public function post( $form, &$submit, $config ) {
		$submission_id = isset( $submit->id ) ? absint( $submit->id ) : 0;
		if ( $submission_id <= 0 ) {
			Logger::error( 'action', 'The submission is not saved, add the "Save to Submissions" action' );
			parent::error( __( 'Le paiement n’a pas pu être lancé. Merci de réessayer ou de contacter le site.', 'wsform-monetico' ) );
			return;
		}

		try {
			$payment = Payment_Builder::start( $submit, Action_Settings::from_config( $config ) );

			Logger::info( 'action', 'Payment started', array( 'submission_id' => $submission_id ) );

			parent::success(
				__( 'Redirection vers la page de paiement sécurisée…', 'wsform-monetico' ),
				array(
					array(
						'action' => 'redirect',
						'url'    => Redirect_Page::get_url( $this->get_home_url(), $payment['reference'], $payment['access_key'] ),
					),
				)
			);
		} catch ( \Exception $exception ) {
			Logger::error(
				'action',
				'Unable to start the payment',
				array(
					'submission_id' => $submission_id,
					'error'         => $exception->getMessage(),
				)
			);

			parent::error( __( 'Le paiement n’a pas pu être lancé. Merci de réessayer ou de contacter le site.', 'wsform-monetico' ) );
		}
	}

	/**
	 * Describe the action settings for WS Form.
	 *
	 * @return object
	 */
	public function get_action_settings() {
		$settings = parent::get_settings_wrapper( array( 'meta_keys' => Action_Settings::get_meta_keys() ) );

		$settings->label        = $this->label;
		$settings->label_action = $this->label_action;
		$settings->multiple     = $this->multiple;
		$settings->events       = $this->events;
		$settings->can_repost   = $this->can_repost;

		return $settings;
	}

	/**
	 * Get the home URL in the visitor's language.
	 *
	 * The form is submitted with AJAX, where Polylang may not know the
	 * language yet: it is then read from the page the form was on.
	 *
	 * @return string
	 */
	private function get_home_url() {
		if ( ! function_exists( 'pll_home_url' ) || ! function_exists( 'pll_languages_list' ) ) {
			return home_url( '/' );
		}

		$language = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
		if ( ! $language ) {
			$referer_path = (string) wp_parse_url( (string) wp_get_referer(), PHP_URL_PATH );
			foreach ( pll_languages_list( array( 'fields' => 'slug' ) ) as $slug ) {
				if ( preg_match( '#^/' . preg_quote( $slug, '#' ) . '(/|$)#', $referer_path ) ) {
					$language = $slug;
					break;
				}
			}
		}

		return $language ? pll_home_url( $language ) : home_url( '/' );
	}
}
