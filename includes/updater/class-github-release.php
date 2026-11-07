<?php
/**
 * Latest GitHub release of a plugin.
 *
 * @package WSForm_Monetico\Updater
 */

namespace WSForm_Monetico\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the latest release from the GitHub API, without authentication.
 *
 * Anonymous calls are limited to 60 per hour and per IP, often shared on
 * hosting: answers are cached 12 hours, failures 1 hour.
 */
class Github_Release {

	const API_URL        = 'https://api.github.com/repos/%s/%s/releases/latest';
	const CACHE_DURATION = 12 * HOUR_IN_SECONDS;
	const ERROR_DURATION = HOUR_IN_SECONDS;
	const TIMEOUT        = 5;

	/**
	 * Repository owner.
	 *
	 * @var string
	 */
	private $owner;

	/**
	 * Repository name, also the plugin slug and archive prefix.
	 *
	 * @var string
	 */
	private $repository;

	/**
	 * User agent version, GitHub requires a user agent.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Constructor.
	 *
	 * @param string $owner           Repository owner.
	 * @param string $repository      Repository name.
	 * @param string $current_version Installed version.
	 */
	public function __construct( $owner, $repository, $current_version ) {
		$this->owner           = $owner;
		$this->repository      = $repository;
		$this->current_version = $current_version;
	}

	/**
	 * Get the latest usable release.
	 *
	 * @return array{version:string,package:string,url:string,published_at:string,notes:string,requires:string,requires_php:string,tested:string}|null
	 *     Null when GitHub is unreachable, or the release has no readable tag or no archive.
	 */
	public function get() {
		$release = $this->read_cache();

		return $release ? $release : null;
	}

	/**
	 * Get the cache key.
	 *
	 * @return string
	 */
	public function get_cache_key() {
		return 'wsform_monetico_release_' . md5( $this->owner . '/' . $this->repository );
	}

	/**
	 * Read the release from cache, or from GitHub.
	 *
	 * @return array|false
	 */
	private function read_cache() {
		$cached = get_site_transient( $this->get_cache_key() );
		if ( is_array( $cached ) ) {
			return empty( $cached['version'] ) ? false : $cached;
		}

		$release = $this->fetch();
		set_site_transient(
			$this->get_cache_key(),
			$release ? $release : array( 'version' => '' ),
			$release ? self::CACHE_DURATION : self::ERROR_DURATION
		);

		return $release;
	}

	/**
	 * Call the GitHub API.
	 *
	 * @return array|false
	 */
	private function fetch() {
		$response = wp_remote_get(
			sprintf( self::API_URL, rawurlencode( $this->owner ), rawurlencode( $this->repository ) ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => $this->repository . '/' . $this->current_version,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $this->parse( $data ) : false;
	}

	/**
	 * Keep what the updater needs from the API answer.
	 *
	 * @param array $data Decoded API answer.
	 *
	 * @return array|false False when the tag or the archive is missing.
	 */
	private function parse( array $data ) {
		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return false;
		}

		// Only the archive built by the release workflow: GitHub's zipball holds the whole repository.
		$archive_name = $this->repository . '-' . $version . '.zip';
		$package      = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === $archive_name && 0 === strpos( (string) ( $asset['browser_download_url'] ?? '' ), 'https://github.com/' ) ) {
				$package = $asset['browser_download_url'];
			}
		}

		if ( '' === $package ) {
			return false;
		}

		return array_merge(
			array(
				'version'      => $version,
				'package'      => $package,
				'url'          => esc_url_raw( (string) ( $data['html_url'] ?? '' ) ),
				'published_at' => (string) ( $data['published_at'] ?? '' ),
				'notes'        => (string) ( $data['body'] ?? '' ),
			),
			$this->parse_requirements( (string) ( $data['body'] ?? '' ) )
		);
	}

	/**
	 * Read requirements written by the workflow at the end of the release notes.
	 *
	 * Lines like "Requires at least: 6.0", "Requires PHP: 7.4", "Tested up to: 6.8".
	 *
	 * @param string $notes Release notes.
	 *
	 * @return array{requires:string,requires_php:string,tested:string}
	 */
	private function parse_requirements( $notes ) {
		$labels       = array(
			'requires'     => 'Requires at least',
			'requires_php' => 'Requires PHP',
			'tested'       => 'Tested up to',
		);
		$requirements = array();

		foreach ( $labels as $key => $label ) {
			$requirements[ $key ] = preg_match( '/^' . preg_quote( $label, '/' ) . ':\s*([\d.]+)\s*$/mi', $notes, $match ) ? $match[1] : '';
		}

		return $requirements;
	}
}
