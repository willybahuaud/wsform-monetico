<?php
/**
 * Updates from GitHub releases.
 *
 * @package WSForm_Monetico\Updater
 */

namespace WSForm_Monetico\Updater;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugs GitHub releases into the WordPress plugin updates.
 *
 * Relies on the "Update URI" plugin header: WordPress then asks the
 * update_plugins_github.com filter instead of WordPress.org, and files the
 * answer under "response" or "no_update" itself, which keeps the
 * auto-update toggle visible when the plugin is up to date.
 *
 * Works only while the repository is public: no token ships with the plugin.
 */
class Updater {

	/**
	 * Release reader.
	 *
	 * @var Github_Release
	 */
	private $release;

	/**
	 * Plugin basename, "folder/file.php".
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Slug used by the details popup.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Name shown in the details popup.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Repository URL, same as the Update URI header.
	 *
	 * @var string
	 */
	private $repository_url;

	/**
	 * Constructor.
	 *
	 * @param string $owner           GitHub owner.
	 * @param string $repository      GitHub repository, also the archive prefix.
	 * @param string $plugin_file     Main plugin file path.
	 * @param string $current_version Installed version.
	 * @param string $plugin_name     Name shown in the details popup.
	 */
	public function __construct( $owner, $repository, $plugin_file, $current_version, $plugin_name ) {
		$this->release         = new Github_Release( $owner, $repository, $current_version );
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = $repository;
		$this->plugin_name     = $plugin_name;
		$this->repository_url  = 'https://github.com/' . $owner . '/' . $repository;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'update_plugins_github.com', array( $this, 'get_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'get_details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'keep_folder_name' ), 10, 4 );
	}

	/**
	 * Describe the latest release to WordPress.
	 *
	 * Hooked to update_plugins_github.com, called for every plugin whose
	 * Update URI points to github.com.
	 *
	 * @param array|false $update      Update data found so far.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 *
	 * @return array|false
	 */
	public function get_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->plugin_basename || ( $plugin_data['UpdateURI'] ?? '' ) !== $this->repository_url ) {
			return $update;
		}

		$release = $this->release->get();
		if ( null === $release ) {
			return $update;
		}

		return array(
			'id'           => $this->repository_url,
			'slug'         => $this->slug,
			'version'      => $release['version'],
			'url'          => $this->repository_url,
			'package'      => $release['package'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
			'tested'       => $release['tested'],
		);
	}

	/**
	 * Fill the "View details" popup.
	 *
	 * Hooked to plugins_api.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action API action.
	 * @param object             $args   Request arguments.
	 *
	 * @return false|object|array
	 */
	public function get_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || $this->slug !== $args->slug ) {
			return $result;
		}

		$release = $this->release->get();
		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => $this->plugin_name,
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://wabeo.fr/">Willy Bahuaud</a>',
			'homepage'      => $this->repository_url,
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'tested'        => $this->get_tested_version( $release['tested'] ),
			'last_updated'  => $release['published_at'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => Release_Notes::to_html( $release['notes'] ),
			),
		);
	}

	/**
	 * Get the tested version shown in the details popup.
	 *
	 * "Tested up to: 7.1" covers the whole branch, but the popup compares
	 * versions as they are and would wrongly warn on WordPress 7.1.4.
	 *
	 * @param string $tested Version declared by the release.
	 *
	 * @return string
	 */
	private function get_tested_version( $tested ) {
		$wordpress_version = get_bloginfo( 'version' );

		return '' !== $tested && 0 === strpos( $wordpress_version . '.', $tested . '.' ) ? $wordpress_version : $tested;
	}

	/**
	 * Install the new version in the folder the plugin already uses.
	 *
	 * The archive holds a "wsform-monetico/" folder; a site may have installed
	 * the plugin under another folder name, which must not change.
	 *
	 * Hooked to upgrader_source_selection.
	 *
	 * @param string|WP_Error $source        Extracted folder.
	 * @param string          $remote_source Temporary parent folder.
	 * @param object          $upgrader      Upgrader.
	 * @param array           $hook_extra    Context, with the plugin basename.
	 *
	 * @return string|WP_Error
	 */
	public function keep_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( is_wp_error( $source ) || ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_basename ) {
			return $source;
		}

		$expected_source = trailingslashit( $remote_source ) . dirname( $this->plugin_basename ) . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $expected_source ) ) {
			return $source;
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $expected_source, true ) ) {
			return $expected_source;
		}

		return new WP_Error( 'wsform_monetico_rename_failed', __( 'Impossible de renommer le dossier du plugin pendant la mise à jour.', 'wsform-monetico' ) );
	}
}
