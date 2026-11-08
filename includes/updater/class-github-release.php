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
		$data = $this->request_json( sprintf( self::API_URL, rawurlencode( $this->owner ), rawurlencode( $this->repository ) ) );

		return is_array( $data ) ? $this->parse( $data ) : false;
	}

	/**
	 * Send a GET request and decode its JSON answer.
	 *
	 * @param string $url URL.
	 *
	 * @return mixed Decoded answer, null on a network error or a status other than 200.
	 */
	private function request_json( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => $this->repository . '/' . $this->current_version,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
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
		$package = $this->find_asset_url( $data, $this->repository . '-' . $version . '.zip' );
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
			$this->read_manifest( $this->find_asset_url( $data, $this->repository . '-' . $version . '.json' ) )
		);
	}

	/**
	 * Find a release asset by its exact name, served by github.com over HTTPS.
	 *
	 * @param array  $data       Decoded API answer.
	 * @param string $asset_name Expected file name.
	 *
	 * @return string Download URL, empty when missing.
	 */
	private function find_asset_url( array $data, $asset_name ) {
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$download_url = (string) ( $asset['browser_download_url'] ?? '' );

			if ( ( $asset['name'] ?? '' ) === $asset_name && 'https' === wp_parse_url( $download_url, PHP_URL_SCHEME ) && 'github.com' === wp_parse_url( $download_url, PHP_URL_HOST ) ) {
				return $download_url;
			}
		}

		return '';
	}

	/**
	 * Read the requirements from the manifest attached to the release.
	 *
	 * The headers of the installed plugin describe the old version, not the
	 * incoming one. Downloading an asset does not count in the API rate limit.
	 *
	 * @param string $manifest_url Manifest URL, empty when the release has none.
	 *
	 * @return array{requires:string,requires_php:string,tested:string} Empty values without a manifest.
	 */
	private function read_manifest( $manifest_url ) {
		$manifest     = '' === $manifest_url ? null : $this->request_json( $manifest_url );
		$requirements = array();

		foreach ( array( 'requires', 'requires_php', 'tested' ) as $key ) {
			$requirements[ $key ] = is_array( $manifest ) && is_string( $manifest[ $key ] ?? null ) && preg_match( '/^[\d.]+$/', $manifest[ $key ] ) ? $manifest[ $key ] : '';
		}

		return $requirements;
	}
}
