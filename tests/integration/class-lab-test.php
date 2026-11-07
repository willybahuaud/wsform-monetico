<?php
/**
 * Helpers for integration tests run inside a WordPress site with WS Form PRO.
 *
 * @package WSForm_Monetico\Tests
 */

use WSForm_Monetico\Action_Settings;
use WSForm_Monetico\Accounts;
use WSForm_Monetico\Ipn_Handler;
use WSForm_Monetico\Payment_Builder;
use WSForm_Monetico\Submission_Reader;

/**
 * Fixtures, assertions and captures shared by the scenarios.
 *
 * No PHPUnit here: WS Form PRO cannot be installed by a test runner, so the
 * scenarios run with "wp eval-file" on a local site.
 */
class WSForm_Monetico_Lab_Test {

	/**
	 * Made-up accounts, generated for each run.
	 *
	 * @var array<string,array{company:string,key:string}>
	 */
	public static $accounts = array();

	/**
	 * Form used by every submission.
	 *
	 * @var int
	 */
	public static $form_id = 0;

	/**
	 * Emails captured instead of being sent.
	 *
	 * @var array<int,array>
	 */
	public static $emails = array();

	/**
	 * Calls of the wsform_monetico_payment_updated hook.
	 *
	 * @var array<int,array>
	 */
	public static $status_changes = array();

	/**
	 * Failed assertions.
	 *
	 * @var array<int,string>
	 */
	public static $failures = array();

	/**
	 * Number of assertions.
	 *
	 * @var int
	 */
	public static $assertions = 0;

	/**
	 * Option value before the run, restored at the end.
	 *
	 * @var mixed
	 */
	private static $saved_accounts;

	/**
	 * Create the form and two accounts, and start capturing emails and hooks.
	 *
	 * @return void
	 */
	public static function set_up() {
		wp_set_current_user( 1 );

		self::$saved_accounts = get_option( Accounts::OPTION_NAME, null );
		self::$accounts       = array(
			'7000001' => array(
				'company' => 'labsocieteA',
				'key'     => strtoupper( bin2hex( random_bytes( 20 ) ) ),
			),
			'7000002' => array(
				'company' => 'labsocieteB',
				'key'     => strtoupper( bin2hex( random_bytes( 20 ) ) ),
			),
		);
		update_option( Accounts::OPTION_NAME, self::$accounts, false );

		$form = new WS_Form_Form();
		$form->db_create();
		self::$form_id = (int) $form->id;

		add_filter( 'pre_wp_mail', array( __CLASS__, 'capture_email' ), 10, 2 );
		add_action( 'wsform_monetico_payment_updated', array( __CLASS__, 'capture_status_change' ), 10, 4 );
	}

	/**
	 * Delete the form and restore the accounts option.
	 *
	 * @return void
	 */
	public static function tear_down() {
		$form     = new WS_Form_Form();
		$form->id = self::$form_id;
		$form->db_delete( true );

		if ( null === self::$saved_accounts ) {
			delete_option( Accounts::OPTION_NAME );
		} else {
			update_option( Accounts::OPTION_NAME, self::$saved_accounts, false );
		}
	}

	/**
	 * Create a submission and start its payment, as the action does on submit.
	 *
	 * @param string $tpe          TPE selected in the action.
	 * @param string $amount_value Value typed in the amount field.
	 *
	 * @return \WS_Form_Submit
	 */
	public static function create_paying_submission( $tpe, $amount_value = '12,50' ) {
		$action_config = array(
			'id'        => Action_Settings::ACTION_ID,
			'row_index' => 1,
			'meta'      => array(
				Action_Settings::get_meta_key( 'tpe' ) => $tpe,
				Action_Settings::get_meta_key( 'environment' ) => 'sandbox',
				Action_Settings::get_meta_key( 'amount_field' ) => '1',
				Action_Settings::get_meta_key( 'email_field' ) => '2',
				Action_Settings::get_meta_key( 'send_email' ) => 'on',
				Action_Settings::get_meta_key( 'send_email_admin' ) => 'on',
				Action_Settings::get_meta_key( 'admin_email_recipients' ) => 'team@example.test',
				Action_Settings::get_meta_key( 'email_template' ) => '<p>{field:3} {reference}</p>',
			),
		);

		$submit                     = new WS_Form_Submit();
		$submit->form_id            = self::$form_id;
		$submit->date_added         = current_time( 'mysql', true );
		$submit->date_updated       = $submit->date_added;
		$submit->status             = 'publish';
		$submit->actions            = maybe_serialize( array( $action_config ) );
		$submit->section_repeatable = '';
		$submit->db_create( false );

		$meta_handler            = new WS_Form_Submit_Meta();
		$meta_handler->parent_id = $submit->id;
		$meta_handler->db_update_from_array(
			array(
				'field_1' => array(
					'id'    => 1,
					'value' => $amount_value,
					'type'  => 'text',
				),
				'field_2' => array(
					'id'    => 2,
					'value' => 'payer@example.test',
					'type'  => 'email',
				),
				'field_3' => array(
					'id'    => 3,
					'value' => '<script>alert(1)</script>',
					'type'  => 'text',
				),
			)
		);

		$submission = Submission_Reader::get( $submit->id );
		Payment_Builder::start( $submission, Action_Settings::from_config( $action_config ) );

		return Submission_Reader::get( $submit->id );
	}

	/**
	 * Build notification fields as Monetico sends them, sealed.
	 *
	 * The seal is computed here with hash_hmac, not with the plugin code, so
	 * that a mistake in Seal cannot hide itself.
	 *
	 * @param \WS_Form_Submit $submission  Submission.
	 * @param string          $return_code code-retour.
	 * @param array           $overrides   Fields to change before sealing.
	 * @param string|null     $signing_key Key used to seal, defaults to the account key.
	 *
	 * @return array<string,string>
	 */
	public static function build_notification( $submission, $return_code, array $overrides = array(), $signing_key = null ) {
		$tpe    = $overrides['TPE'] ?? WSForm_Monetico\Submission_Meta::get( $submission, 'tpe' );
		$fields = array_merge(
			array(
				'TPE'          => $tpe,
				'date'         => '07/11/2026_a_14:52:46',
				'montant'      => WSForm_Monetico\Submission_Meta::get( $submission, 'amount' ),
				'reference'    => WSForm_Monetico\Submission_Meta::get( $submission, 'payment_reference' ),
				'texte-libre'  => '',
				'code-retour'  => $return_code,
				'cvx'          => 'oui',
				'vld'          => '1228',
				'brand'        => 'VI',
				'numauto'      => 'paiement' === $return_code ? '010101' : '',
				'motifrefus'   => 'paiement' === $return_code ? '' : 'Refus',
				'ipclient'     => '203.0.113.7',
				'hpancb'       => 'F6FBF44A7EC30941DA2E411AA8A50C77F174B2BB',
				'modepaiement' => 'CB',
			),
			$overrides
		);

		$key = null !== $signing_key ? $signing_key : ( self::$accounts[ $tpe ]['key'] ?? str_repeat( '0', 40 ) );
		ksort( $fields, SORT_STRING );
		$pairs = array();
		foreach ( $fields as $name => $value ) {
			$pairs[] = $name . '=' . $value;
		}
		$fields['MAC'] = hash_hmac( 'sha1', implode( '*', $pairs ), hex2bin( $key ) );

		return $fields;
	}

	/**
	 * Send a notification to the handler.
	 *
	 * @param array<string,string> $fields Notification fields.
	 *
	 * @return array{accepted:bool,http_status:int}
	 */
	public static function notify( array $fields ) {
		$handler = new Ipn_Handler();

		return $handler->process( $fields );
	}

	/**
	 * Read the current status from the database.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 *
	 * @return string
	 */
	public static function status_of( $submission ) {
		return WSForm_Monetico\Submission_Meta::get( Submission_Reader::get( $submission->id ), 'payment_status' );
	}

	/**
	 * Record an email instead of sending it.
	 *
	 * Hooked to pre_wp_mail.
	 *
	 * @param null|bool $short_circuit Null to send.
	 * @param array     $attributes    wp_mail() arguments.
	 *
	 * @return bool
	 */
	public static function capture_email( $short_circuit, $attributes ) {
		self::$emails[] = $attributes;

		return true;
	}

	/**
	 * Record a status change.
	 *
	 * Hooked to wsform_monetico_payment_updated.
	 *
	 * @param int    $submission_id   Submission ID.
	 * @param string $status          New status.
	 * @param array  $fields          Notification fields.
	 * @param string $previous_status Previous status.
	 *
	 * @return void
	 */
	public static function capture_status_change( $submission_id, $status, $fields, $previous_status ) {
		self::$status_changes[] = array(
			'submission_id'   => $submission_id,
			'status'          => $status,
			'fields'          => $fields,
			'previous_status' => $previous_status,
		);
	}

	/**
	 * Check a condition.
	 *
	 * @param bool   $condition   Condition.
	 * @param string $description What is checked.
	 *
	 * @return void
	 */
	public static function check( $condition, $description ) {
		++self::$assertions;
		if ( ! $condition ) {
			self::$failures[] = $description;
		}
	}

	/**
	 * Check two values are identical.
	 *
	 * @param mixed  $expected    Expected value.
	 * @param mixed  $actual      Actual value.
	 * @param string $description What is checked.
	 *
	 * @return void
	 */
	public static function same( $expected, $actual, $description ) {
		self::check( $expected === $actual, sprintf( '%s (expected %s, got %s)', $description, wp_json_encode( $expected ), wp_json_encode( $actual ) ) );
	}
}
