<?php
/**
 * Plugin log files.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes daily log files in wp-content/uploads/wsform-monetico/logs/.
 *
 * The uploads folder is public. An .htaccess only protects Apache sites, so
 * each file name also ends with a secret derived from the site salts, like
 * WooCommerce logs: on nginx the URL cannot be guessed. Sensitive values are
 * masked before writing.
 */
class Logger {

	const LOG_DIRECTORY = 'wsform-monetico/logs';
	const LOG_TTL_DAYS  = 30;

	/**
	 * Context keys whose value is never written.
	 */
	const MASKED_KEYS = array( 'mac', 'key', 'hmac_key', 'access_key', 'monetico_key', 'mail', 'email', 'recipients', 'ipclient', 'bincb', 'hpancb' );

	/**
	 * Log an informational message.
	 *
	 * @param string $channel Part of the plugin writing the message.
	 * @param string $message Message, in English.
	 * @param array  $context Values that help understand the message.
	 *
	 * @return void
	 */
	public static function info( $channel, $message, array $context = array() ) {
		self::write( 'INFO', $channel, $message, $context );
	}

	/**
	 * Log a warning.
	 *
	 * @param string $channel Part of the plugin writing the message.
	 * @param string $message Message, in English.
	 * @param array  $context Values that help understand the message.
	 *
	 * @return void
	 */
	public static function warning( $channel, $message, array $context = array() ) {
		self::write( 'WARNING', $channel, $message, $context );
	}

	/**
	 * Log an error.
	 *
	 * @param string $channel Part of the plugin writing the message.
	 * @param string $message Message, in English.
	 * @param array  $context Values that help understand the message.
	 *
	 * @return void
	 */
	public static function error( $channel, $message, array $context = array() ) {
		self::write( 'ERROR', $channel, $message, $context );
	}

	/**
	 * Replace sensitive values, at any depth.
	 *
	 * @param array $context Context values.
	 *
	 * @return array
	 */
	public static function mask( array $context ) {
		foreach ( $context as $name => $value ) {
			if ( in_array( strtolower( (string) $name ), self::MASKED_KEYS, true ) ) {
				$context[ $name ] = '***';
			} elseif ( is_array( $value ) ) {
				$context[ $name ] = self::mask( $value );
			}
		}

		return $context;
	}

	/**
	 * Get the log directory.
	 *
	 * @return string Empty when uploads are not available.
	 */
	public static function get_directory() {
		$uploads = wp_upload_dir( null, false );

		return empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::LOG_DIRECTORY : '';
	}

	/**
	 * Get today's log file path.
	 *
	 * @return string
	 */
	public static function get_file_path() {
		$secret = substr( wp_hash( 'wsform-monetico-logs' ), 0, 20 );

		return self::get_directory() . '/monetico-' . current_time( 'Y-m-d' ) . '-' . $secret . '.log';
	}

	/**
	 * Append a line to today's file.
	 *
	 * @param string $level   Level.
	 * @param string $channel Channel.
	 * @param string $message Message.
	 * @param array  $context Context.
	 *
	 * @return void
	 */
	private static function write( $level, $channel, $message, array $context ) {
		$line = sprintf(
			'[%s] [%s] [%s] %s %s',
			current_time( 'Y-m-d H:i:s' ),
			$level,
			sanitize_key( $channel ),
			wp_strip_all_tags( $message ),
			(string) wp_json_encode( self::mask( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		if ( ! self::prepare_directory() ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Appending to a log, WP_Filesystem has no append mode.
		file_put_contents( self::get_file_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Create the directory, its protections, and delete old files once a day.
	 *
	 * @return bool
	 */
	private static function prepare_directory() {
		$directory = self::get_directory();
		if ( '' === $directory || ! wp_mkdir_p( $directory ) ) {
			return false;
		}

		$protections = array(
			'.htaccess'  => "Require all denied\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			'index.html' => '',
		);

		foreach ( $protections as $file_name => $content ) {
			$file_path = $directory . '/' . $file_name;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			if ( ! file_exists( $file_path ) || file_get_contents( $file_path ) !== $content ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small static file.
				file_put_contents( $file_path, $content );
			}
		}

		self::delete_old_files( $directory );

		return true;
	}

	/**
	 * Delete old files, at most once a day.
	 *
	 * Also deletes files without the secret suffix: earlier versions used
	 * guessable names and logged sensitive values.
	 *
	 * @param string $directory Log directory.
	 *
	 * @return void
	 */
	private static function delete_old_files( $directory ) {
		if ( get_transient( 'wsform_monetico_logs_cleaned' ) ) {
			return;
		}

		set_transient( 'wsform_monetico_logs_cleaned', 1, DAY_IN_SECONDS );

		$threshold = time() - self::LOG_TTL_DAYS * DAY_IN_SECONDS;
		$suffix    = substr( self::get_file_path(), -25 );
		foreach ( (array) glob( $directory . '/monetico-*.log' ) as $file_path ) {
			if ( filemtime( $file_path ) < $threshold || substr( $file_path, -25 ) !== $suffix ) {
				wp_delete_file( $file_path );
			}
		}
	}
}
