<?php
/**
 * Simulated GitHub API for the updater scenarios.
 *
 * @package WSForm_Monetico\Tests
 */

/**
 * Simulated GitHub answer and count of API calls.
 */
class WSForm_Monetico_Fake_Github {

	/**
	 * Answer to return: array for JSON, int for an HTTP status, WP_Error for a network failure.
	 *
	 * @var array|int|WP_Error
	 */
	public static $answer = 404;

	/**
	 * Calls made to the GitHub API.
	 *
	 * @var int
	 */
	public static $calls = 0;

	/**
	 * Answer HTTP requests instead of the network.
	 *
	 * Hooked to pre_http_request.
	 *
	 * @param false|array $preempt Response so far.
	 * @param array       $args    Request arguments.
	 * @param string      $url     URL.
	 *
	 * @return false|array|WP_Error
	 */
	public static function answer( $preempt, $args, $url ) {
		if ( 0 === strpos( $url, 'https://api.wordpress.org/' ) ) {
			return self::response(
				200,
				array(
					'plugins'      => array(),
					'no_update'    => array(),
					'translations' => array(),
				)
			);
		}

		if ( 0 !== strpos( $url, 'https://api.github.com/repos/willybahuaud/wsform-monetico/' ) ) {
			return $preempt;
		}

		++self::$calls;

		if ( is_wp_error( self::$answer ) ) {
			return self::$answer;
		}

		return is_int( self::$answer ) ? self::response( self::$answer, array( 'message' => 'Not Found' ) ) : self::response( 200, self::$answer );
	}

	/**
	 * Build a release as the GitHub API returns it.
	 *
	 * @param string $tag          Tag name.
	 * @param bool   $with_archive Attach the built archive.
	 *
	 * @return array
	 */
	public static function release( $tag, $with_archive = true ) {
		$version = ltrim( $tag, 'v' );
		$assets  = array(
			array(
				'name'                 => 'source-notes.txt',
				'browser_download_url' => 'https://github.com/willybahuaud/wsform-monetico/releases/download/' . $tag . '/source-notes.txt',
			),
		);

		if ( $with_archive ) {
			$assets[] = array(
				'name'                 => 'wsform-monetico-' . $version . '.zip',
				'browser_download_url' => 'https://github.com/willybahuaud/wsform-monetico/releases/download/' . $tag . '/wsform-monetico-' . $version . '.zip',
			);
		}

		return array(
			'tag_name'     => $tag,
			'html_url'     => 'https://github.com/willybahuaud/wsform-monetico/releases/tag/' . $tag,
			'published_at' => '2027-02-01T10:00:00Z',
			'body'         => "- Fix something\n\nRequires at least: 6.2\nRequires PHP: 8.0\nTested up to: 7.1",
			'zipball_url'  => 'https://api.github.com/repos/willybahuaud/wsform-monetico/zipball/' . $tag,
			'assets'       => $assets,
		);
	}

	/**
	 * Build an HTTP response array.
	 *
	 * @param int   $status HTTP status.
	 * @param array $body   Body, encoded as JSON.
	 *
	 * @return array
	 */
	private static function response( $status, array $body ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
