<?php
/**
 * Updater scenarios with simulated GitHub answers.
 *
 * Included by run.php. Nothing reaches GitHub or WordPress.org.
 *
 * @package WSForm_Monetico\Tests
 */

use WSForm_Monetico\Plugin;
use WSForm_Monetico\Updater\Github_Release;

require_once __DIR__ . '/class-fake-github.php';

/**
 * Run a plugin update check with a simulated GitHub answer.
 *
 * @param array|int|WP_Error $answer      Simulated answer.
 * @param bool               $clear_cache Forget the cached release first.
 *
 * @return array{response:?object,no_update:?object,calls:int}
 */
function wsform_monetico_test_check_updates( $answer, $clear_cache = true ) {
	$release = new Github_Release( 'willybahuaud', 'wsform-monetico', Plugin::VERSION );
	if ( $clear_cache ) {
		delete_site_transient( $release->get_cache_key() );
	}

	WSForm_Monetico_Fake_Github::$answer = $answer;
	WSForm_Monetico_Fake_Github::$calls  = 0;
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();

	$basename = plugin_basename( WSFORM_MONETICO_FILE );
	$updates  = get_site_transient( 'update_plugins' );

	return array(
		'response'  => $updates->response[ $basename ] ?? null,
		'no_update' => $updates->no_update[ $basename ] ?? null,
		'calls'     => WSForm_Monetico_Fake_Github::$calls,
	);
}

/**
 * A newer release with its archive is offered, with its requirements.
 *
 * @return void
 */
function wsform_monetico_test_updater_newer_release() {
	$check = wsform_monetico_test_check_updates( WSForm_Monetico_Fake_Github::release( 'v9.0.0' ) );

	WSForm_Monetico_Lab_Test::same( '9.0.0', $check['response']->new_version ?? '', 'updater: newer version offered' );
	WSForm_Monetico_Lab_Test::same( 'https://github.com/willybahuaud/wsform-monetico/releases/download/v9.0.0/wsform-monetico-9.0.0.zip', $check['response']->package ?? '', 'updater: package is the built archive' );
	WSForm_Monetico_Lab_Test::same( '8.0', $check['response']->requires_php ?? '', 'updater: PHP requirement read from the release' );

	$cached = wsform_monetico_test_check_updates( WSForm_Monetico_Fake_Github::release( 'v9.0.0' ), false );
	WSForm_Monetico_Lab_Test::same( 0, $cached['calls'], 'updater: release cached between checks' );

	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	$details = plugins_api( 'plugin_information', array( 'slug' => 'wsform-monetico' ) );
	WSForm_Monetico_Lab_Test::same( '9.0.0', $details->version ?? '', 'updater: details popup filled' );
	WSForm_Monetico_Lab_Test::check( false !== strpos( $details->sections['changelog'] ?? '', '<li>Fix something</li>' ), 'updater: release notes shown' );
}

/**
 * Up to date: listed in no_update, so the auto-update toggle shows.
 *
 * @return void
 */
function wsform_monetico_test_updater_up_to_date() {
	$check = wsform_monetico_test_check_updates( WSForm_Monetico_Fake_Github::release( 'v' . Plugin::VERSION ) );

	WSForm_Monetico_Lab_Test::same( null, $check['response'], 'updater: same version not offered' );
	WSForm_Monetico_Lab_Test::check( null !== $check['no_update'], 'updater: listed in no_update for the auto-update toggle' );
}

/**
 * Releases without archive or with an unreadable tag are ignored.
 *
 * @return void
 */
function wsform_monetico_test_updater_invalid_releases() {
	$without_archive = wsform_monetico_test_check_updates( WSForm_Monetico_Fake_Github::release( 'v9.0.0', false ) );
	WSForm_Monetico_Lab_Test::same( null, $without_archive['response'], 'updater: release without archive ignored' );

	$bad_tag = wsform_monetico_test_check_updates( WSForm_Monetico_Fake_Github::release( 'latest' ) );
	WSForm_Monetico_Lab_Test::same( null, $bad_tag['response'], 'updater: unreadable tag ignored' );
}

/**
 * GitHub failures stay silent, fast, and cached.
 *
 * @return void
 */
function wsform_monetico_test_updater_failures() {
	$failures = array(
		'404 private or missing repository' => 404,
		'403 rate limit'                    => 403,
		'timeout'                           => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
	);

	foreach ( $failures as $label => $answer ) {
		$started = microtime( true );
		$check   = wsform_monetico_test_check_updates( $answer );
		WSForm_Monetico_Lab_Test::same( null, $check['response'], 'updater: ' . $label . ' offers nothing' );
		WSForm_Monetico_Lab_Test::same( 1, $check['calls'], 'updater: ' . $label . ' calls GitHub once' );
		WSForm_Monetico_Lab_Test::check( microtime( true ) - $started < 2, 'updater: ' . $label . ' does not slow the admin' );

		$again = wsform_monetico_test_check_updates( WSForm_Monetico_Fake_Github::release( 'v9.0.0' ), false );
		WSForm_Monetico_Lab_Test::same( 0, $again['calls'], 'updater: ' . $label . ' cached' );
	}
}

/**
 * The plugin code holds no GitHub token.
 *
 * @return void
 */
function wsform_monetico_test_updater_no_token() {
	$sources = '';
	foreach ( array( 'wsform-monetico.php', 'includes/updater/class-github-release.php', 'includes/updater/class-updater.php' ) as $relative_path ) {
		$sources .= (string) file_get_contents( WSFORM_MONETICO_PATH . $relative_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	}

	WSForm_Monetico_Lab_Test::check( ! preg_match( '/Authorization|gh[pousr]_[A-Za-z0-9]{20,}|github_pat_/', $sources ), 'updater: no token in the code' );
}
