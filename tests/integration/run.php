<?php
/**
 * Integration scenarios, run on a local site with WS Form PRO.
 *
 * Run with: wp eval-file wp-content/plugins/wsform-monetico/tests/integration/run.php
 *
 * Each scenario checks one expected behaviour of the plugin. Accounts are
 * made up for each run: no real Monetico credential is needed.
 *
 * @package WSForm_Monetico\Tests
 */

use WSForm_Monetico\Accounts;
use WSForm_Monetico\Admin\Settings_Page;
use WSForm_Monetico\Logger;
use WSForm_Monetico\Payment_Builder;
use WSForm_Monetico\Redirect_Page;
use WSForm_Monetico\Submission_Meta;
use WSForm_Monetico\Submission_Reader;

require_once __DIR__ . '/class-lab-test.php';
require_once __DIR__ . '/updater-scenarios.php';

/**
 * A payment goes from pending to paid, and emails are sent once.
 *
 * @return void
 */
function wsform_monetico_test_paid() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000001', '1 234,50 €' );
	WSForm_Monetico_Lab_Test::same( 'pending', Submission_Meta::get( $submission, 'payment_status' ), 'paid: starts pending' );
	WSForm_Monetico_Lab_Test::same( '1234.50EUR', Submission_Meta::get( $submission, 'amount' ), 'paid: amount stored at submit' );

	$result = WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' ) );
	WSForm_Monetico_Lab_Test::same( true, $result['accepted'], 'paid: notification accepted' );
	WSForm_Monetico_Lab_Test::same( 'paid', WSForm_Monetico_Lab_Test::status_of( $submission ), 'paid: status paid' );
	WSForm_Monetico_Lab_Test::same( '010101', Submission_Meta::get( Submission_Reader::get( $submission->id ), 'transaction_id' ), 'paid: authorization stored' );
	WSForm_Monetico_Lab_Test::same( 2, count( WSForm_Monetico_Lab_Test::$emails ), 'paid: payer and team emails sent' );
	WSForm_Monetico_Lab_Test::check( false === strpos( WSForm_Monetico_Lab_Test::$emails[0]['message'] ?? '', '<script>' ), 'paid: field values escaped in email body' );
	WSForm_Monetico_Lab_Test::check( false === isset( WSForm_Monetico_Lab_Test::$status_changes[0]['fields']['MAC'] ), 'paid: hook does not receive the MAC' );
}

/**
 * A replayed notification changes nothing and sends nothing.
 *
 * @return void
 */
function wsform_monetico_test_replay() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000001' );
	$paid       = WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' );
	WSForm_Monetico_Lab_Test::notify( $paid );

	$emails_before = count( WSForm_Monetico_Lab_Test::$emails );
	$hooks_before  = count( WSForm_Monetico_Lab_Test::$status_changes );

	$result = WSForm_Monetico_Lab_Test::notify( $paid );
	WSForm_Monetico_Lab_Test::same( true, $result['accepted'], 'replay: acknowledged with cdr=0' );
	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'Annulation' ) );
	WSForm_Monetico_Lab_Test::same( 'paid', WSForm_Monetico_Lab_Test::status_of( $submission ), 'replay: paid never goes back' );
	WSForm_Monetico_Lab_Test::same( $emails_before, count( WSForm_Monetico_Lab_Test::$emails ), 'replay: no duplicate email' );
	WSForm_Monetico_Lab_Test::same( $hooks_before, count( WSForm_Monetico_Lab_Test::$status_changes ), 'replay: hook not fired again' );
}

/**
 * A refusal gives failed, and a later successful attempt still gives paid.
 *
 * @return void
 */
function wsform_monetico_test_failed_then_paid() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000002' );

	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'Annulation' ) );
	WSForm_Monetico_Lab_Test::same( 'failed', WSForm_Monetico_Lab_Test::status_of( $submission ), 'failed: refusal gives failed' );

	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'attente_partenaire' ) );
	WSForm_Monetico_Lab_Test::same( 'failed', WSForm_Monetico_Lab_Test::status_of( $submission ), 'failed: unknown code changes nothing' );

	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' ) );
	WSForm_Monetico_Lab_Test::same( 'paid', WSForm_Monetico_Lab_Test::status_of( $submission ), 'failed: new attempt can pay' );
}

/**
 * Forged or inconsistent notifications are rejected without changes.
 *
 * @return void
 */
function wsform_monetico_test_rejections() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000001' );
	$hooks      = count( WSForm_Monetico_Lab_Test::$status_changes );

	$cases = array(
		'wrong seal'            => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array(), str_repeat( 'AB', 20 ) ),
		'other account key'     => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array(), WSForm_Monetico_Lab_Test::$accounts['7000002']['key'] ),
		'amount changed'        => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array( 'montant' => '1.00EUR' ) ),
		'currency changed'      => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array( 'montant' => '12.50USD' ) ),
		'other TPE, valid seal' => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array( 'TPE' => '7000002' ) ),
		'unknown TPE'           => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array( 'TPE' => '9999999' ) ),
		'unknown reference'     => WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement', array( 'reference' => 'WSF-1-999999-1' ) ),
	);

	$tampered                             = WSForm_Monetico_Lab_Test::build_notification( $submission, 'Annulation' );
	$tampered['code-retour']              = 'paiement';
	$cases['field changed after sealing'] = $tampered;

	foreach ( $cases as $label => $fields ) {
		$result = WSForm_Monetico_Lab_Test::notify( $fields );
		WSForm_Monetico_Lab_Test::same( false, $result['accepted'], 'rejection: ' . $label );
	}

	WSForm_Monetico_Lab_Test::same( 'pending', WSForm_Monetico_Lab_Test::status_of( $submission ), 'rejection: status unchanged' );
	WSForm_Monetico_Lab_Test::same( $hooks, count( WSForm_Monetico_Lab_Test::$status_changes ), 'rejection: no hook fired' );
}

/**
 * Two forms on two TPE work side by side.
 *
 * @return void
 */
function wsform_monetico_test_two_accounts() {
	$first  = WSForm_Monetico_Lab_Test::create_paying_submission( '7000001' );
	$second = WSForm_Monetico_Lab_Test::create_paying_submission( '7000002', '30' );

	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $second, 'paiement' ) );
	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $first, 'Annulation' ) );

	WSForm_Monetico_Lab_Test::same( 'failed', WSForm_Monetico_Lab_Test::status_of( $first ), 'two accounts: first follows its own TPE' );
	WSForm_Monetico_Lab_Test::same( 'paid', WSForm_Monetico_Lab_Test::status_of( $second ), 'two accounts: second follows its own TPE' );

	$request = Payment_Builder::build_request( $second, WSForm_Monetico\Action_Settings::from_submission( $second ) );
	WSForm_Monetico_Lab_Test::same( '7000002', $request['fields']['TPE'], 'two accounts: request uses the stored TPE' );
	WSForm_Monetico_Lab_Test::same( 'labsocieteB', $request['fields']['societe'], 'two accounts: request uses the account company' );
}

/**
 * The redirect page needs the access key and never changes a payment.
 *
 * @return void
 */
function wsform_monetico_test_redirect_page() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000001' );
	$reference  = Submission_Meta::get( $submission, 'payment_reference' );
	$access_key = Submission_Meta::get( $submission, 'access_key' );
	$home       = home_url( '/' );

	$valid = wsform_monetico_test_get( Redirect_Page::get_url( $home, $reference, $access_key ) );
	WSForm_Monetico_Lab_Test::check( false !== strpos( $valid, 'name="MAC"' ), 'redirect: valid link prints the signed form' );
	WSForm_Monetico_Lab_Test::check( false !== strpos( $valid, 'test/paiement.cgi' ), 'redirect: sandbox endpoint' );

	$wrong_key = wsform_monetico_test_get( Redirect_Page::get_url( $home, $reference, 'nope' ) );
	WSForm_Monetico_Lab_Test::check( false === strpos( $wrong_key, 'name="MAC"' ), 'redirect: wrong key gets no form' );

	$no_key = wsform_monetico_test_get( add_query_arg( Redirect_Page::QUERY_VAR, $reference, $home ) );
	WSForm_Monetico_Lab_Test::check( false === strpos( $no_key, 'name="MAC"' ), 'redirect: missing key gets no form' );

	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' ) );
	$after_payment = wsform_monetico_test_get( Redirect_Page::get_url( $home, $reference, $access_key ) );
	WSForm_Monetico_Lab_Test::check( false === strpos( $after_payment, 'name="MAC"' ), 'redirect: paid submission gets no form' );
	WSForm_Monetico_Lab_Test::same( 'paid', WSForm_Monetico_Lab_Test::status_of( $submission ), 'redirect: visiting does not reset a paid status' );
}

/**
 * The REST route answers in plain text, as Monetico expects.
 *
 * @return void
 */
function wsform_monetico_test_rest_route() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000002' );
	$fields     = WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' );

	$accepted = wp_remote_post(
		WSForm_Monetico\Ipn_Endpoint::get_url(),
		array(
			'body'      => $fields,
			'sslverify' => false,
		)
	);
	WSForm_Monetico_Lab_Test::same( "version=2\ncdr=0\n", wp_remote_retrieve_body( $accepted ), 'REST: accepted answer' );
	WSForm_Monetico_Lab_Test::same( 200, wp_remote_retrieve_response_code( $accepted ), 'REST: accepted status' );

	$fields['MAC'] = str_repeat( '0', 40 );
	$rejected      = wp_remote_post(
		WSForm_Monetico\Ipn_Endpoint::get_url(),
		array(
			'body'      => $fields,
			'sslverify' => false,
		)
	);
	WSForm_Monetico_Lab_Test::same( "version=2\ncdr=1\n", wp_remote_retrieve_body( $rejected ), 'REST: rejected answer' );

	$get = wp_remote_get( WSForm_Monetico\Ipn_Endpoint::get_url(), array( 'sslverify' => false ) );
	WSForm_Monetico_Lab_Test::same( 404, wp_remote_retrieve_response_code( $get ), 'REST: GET not allowed' );
}

/**
 * A notification waits while another one holds the submission.
 *
 * @return void
 */
function wsform_monetico_test_lock() {
	$submission = WSForm_Monetico_Lab_Test::create_paying_submission( '7000001' );
	Submission_Meta::lock( $submission->id );

	$started = microtime( true );
	$result  = WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' ) );
	WSForm_Monetico_Lab_Test::same( 503, $result['http_status'], 'lock: busy submission answers 503' );
	WSForm_Monetico_Lab_Test::check( microtime( true ) - $started >= 1.5, 'lock: waited before giving up' );
	WSForm_Monetico_Lab_Test::same( 'pending', WSForm_Monetico_Lab_Test::status_of( $submission ), 'lock: nothing changed' );

	Submission_Meta::unlock( $submission->id );
	WSForm_Monetico_Lab_Test::notify( WSForm_Monetico_Lab_Test::build_notification( $submission, 'paiement' ) );
	WSForm_Monetico_Lab_Test::same( 'paid', WSForm_Monetico_Lab_Test::status_of( $submission ), 'lock: processed once released' );
}

/**
 * Settings page: blank key keeps the stored one, a typo deletes nothing.
 *
 * @return void
 */
function wsform_monetico_test_settings_sanitize() {
	$page   = new Settings_Page();
	$stored = Accounts::get_option_accounts();
	$result = $page->sanitize(
		array(
			'rows' => array(
				array(
					'tpe'     => '7000001',
					'company' => 'renamed',
					'key'     => '',
				),
				array(
					'tpe'     => '7000002',
					'company' => 'labsocieteB',
					'key'     => 'typo',
				),
				array(
					'tpe'     => '7000003',
					'company' => 'new',
					'key'     => str_repeat( 'c', 40 ),
				),
				array(
					'tpe'     => '',
					'company' => '',
					'key'     => '',
				),
			),
		)
	);

	WSForm_Monetico_Lab_Test::same( $stored['7000001']['key'], $result['7000001']['key'] ?? '', 'settings: blank key keeps the stored key' );
	WSForm_Monetico_Lab_Test::same( 'renamed', $result['7000001']['company'] ?? '', 'settings: company updated' );
	WSForm_Monetico_Lab_Test::same( $stored['7000002'], $result['7000002'] ?? null, 'settings: invalid key keeps the account' );
	WSForm_Monetico_Lab_Test::check( isset( $result['7000003'] ), 'settings: new account added' );

	$deleted = $page->sanitize(
		array(
			'rows' => array(
				array(
					'tpe'     => '7000001',
					'company' => 'x',
					'delete'  => '1',
				),
			),
		)
	);
	WSForm_Monetico_Lab_Test::check( ! isset( $deleted['7000001'] ), 'settings: account deleted' );
}

/**
 * No secret ends up in logs, forms, submissions or exports.
 *
 * @return void
 */
function wsform_monetico_test_no_secret_leak() {
	global $wpdb;

	$log = (string) file_get_contents( Logger::get_file_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	WSForm_Monetico_Lab_Test::check( '' !== $log, 'leak: log written' );

	foreach ( WSForm_Monetico_Lab_Test::$accounts as $account ) {
		WSForm_Monetico_Lab_Test::check( false === stripos( $log, $account['key'] ), 'leak: key not in log' );

		$tables = array(
			'wsf_form_meta'   => 'meta_value',
			'wsf_form'        => 'published',
			'wsf_submit'      => 'actions',
			'wsf_submit_meta' => 'meta_value',
		);
		foreach ( $tables as $table => $column ) {
			$found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$column} LIKE %s", '%' . $wpdb->esc_like( $account['key'] ) . '%' ) ); // phpcs:ignore WordPress.DB
			WSForm_Monetico_Lab_Test::same( 0, $found, 'leak: key not in ' . $table );
		}
	}

	WSForm_Monetico_Lab_Test::check( ! preg_match( '/"MAC"\s*:\s*"[0-9a-f]{40}"/i', $log ), 'leak: no MAC in log' );
	WSForm_Monetico_Lab_Test::check( false === strpos( $log, 'payer@example.test' ), 'leak: no payer email in log' );
	WSForm_Monetico_Lab_Test::check( false === strpos( $log, '203.0.113.7' ), 'leak: no client IP in log' );

	$public_url = str_replace( wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], Logger::get_file_path() );
	$guessed    = preg_replace( '/-[^-]+\.log$/', '.log', $public_url );
	WSForm_Monetico_Lab_Test::same( 404, wp_remote_retrieve_response_code( wp_remote_get( $guessed, array( 'sslverify' => false ) ) ), 'leak: log name cannot be guessed' );
}

/**
 * The constant wins over the settings page for the same TPE.
 *
 * Runs last: a constant cannot be removed once defined.
 *
 * @return void
 */
function wsform_monetico_test_constant_priority() {
	$constant_key = str_repeat( 'D', 40 );
	define(
		'WSFORM_MONETICO_ACCOUNTS',
		array(
			'7000001' => array(
				'company' => 'fromconfig',
				'key'     => $constant_key,
			),
			'7000009' => array(
				'company' => 'onlyconfig',
				'key'     => $constant_key,
			),
		)
	);

	WSForm_Monetico_Lab_Test::same( $constant_key, Accounts::get( '7000001' )['key'] ?? '', 'constant: overrides the option' );
	WSForm_Monetico_Lab_Test::same( 'constant', Accounts::get( '7000001' )['source'] ?? '', 'constant: source reported' );
	WSForm_Monetico_Lab_Test::same( WSForm_Monetico_Lab_Test::$accounts['7000002']['key'], Accounts::get( '7000002' )['key'] ?? '', 'constant: other option accounts kept' );
	WSForm_Monetico_Lab_Test::check( null !== Accounts::get( '7000009' ), 'constant: adds its own accounts' );
}

/**
 * Fetch a page of the site.
 *
 * @param string $url URL.
 *
 * @return string Body.
 */
function wsform_monetico_test_get( $url ) {
	return wp_remote_retrieve_body( wp_remote_get( $url, array( 'sslverify' => false ) ) );
}

WSForm_Monetico_Lab_Test::set_up();
add_filter( 'pre_http_request', array( 'WSForm_Monetico_Fake_Github', 'answer' ), 10, 3 );

$wsform_monetico_scenarios = array(
	'wsform_monetico_test_paid',
	'wsform_monetico_test_replay',
	'wsform_monetico_test_failed_then_paid',
	'wsform_monetico_test_rejections',
	'wsform_monetico_test_two_accounts',
	'wsform_monetico_test_redirect_page',
	'wsform_monetico_test_rest_route',
	'wsform_monetico_test_lock',
	'wsform_monetico_test_settings_sanitize',
	'wsform_monetico_test_no_secret_leak',
	'wsform_monetico_test_updater_newer_release',
	'wsform_monetico_test_updater_up_to_date',
	'wsform_monetico_test_updater_invalid_releases',
	'wsform_monetico_test_updater_failures',
	'wsform_monetico_test_updater_no_token',
	'wsform_monetico_test_constant_priority',
);

try {
	foreach ( $wsform_monetico_scenarios as $wsform_monetico_scenario ) {
		$wsform_monetico_scenario();
	}
} finally {
	WSForm_Monetico_Lab_Test::tear_down();
}

foreach ( WSForm_Monetico_Lab_Test::$failures as $wsform_monetico_failure ) {
	WP_CLI::warning( $wsform_monetico_failure );
}

if ( WSForm_Monetico_Lab_Test::$failures ) {
	WP_CLI::error( sprintf( '%d of %d checks failed.', count( WSForm_Monetico_Lab_Test::$failures ), WSForm_Monetico_Lab_Test::$assertions ) );
}

WP_CLI::success( sprintf( '%d checks passed.', WSForm_Monetico_Lab_Test::$assertions ) );
