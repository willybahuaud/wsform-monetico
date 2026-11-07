<?php
/**
 * Settings of the Monetico WS Form action.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use WSForm_Monetico\Monetico\Payment_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares the action settings and reads them back from a form or a submission.
 *
 * No credential is stored here: the action only names a TPE, whose company
 * code and key come from Accounts.
 */
class Action_Settings {

	/**
	 * WS Form action ID.
	 */
	const ACTION_ID = 'monetico_payment';

	/**
	 * Default values, indexed by setting name.
	 */
	const DEFAULTS = array(
		'tpe'                    => '',
		'environment'            => Submission_Meta::ENVIRONMENT_SANDBOX,
		'currency'               => 'EUR',
		'language'               => 'FR',
		'amount_field'           => '',
		'email_field'            => '',
		'texte_libre'            => '',
		'success_url'            => '',
		'failure_url'            => '',
		'send_email'             => '',
		'email_subject'          => '',
		'email_template'         => '',
		'send_email_admin'       => '',
		'admin_email_subject'    => '',
		'admin_email_template'   => '',
		'admin_email_recipients' => '',
		'email_from_name'        => '',
		'email_from_address'     => '',
	);

	/**
	 * Get the WS Form meta key of a setting.
	 *
	 * @param string $setting_name Setting name from self::DEFAULTS.
	 *
	 * @return string
	 */
	public static function get_meta_key( $setting_name ) {
		return sprintf( 'action_%s_%s', self::ACTION_ID, $setting_name );
	}

	/**
	 * Read and clean the settings of one action row.
	 *
	 * @param array $action_config Action row as WS Form passes it, with a "meta" entry.
	 *
	 * @return array<string,mixed>
	 */
	public static function from_config( array $action_config ) {
		$raw_settings = array();
		foreach ( self::DEFAULTS as $setting_name => $default_value ) {
			$meta_key                      = self::get_meta_key( $setting_name );
			$raw_settings[ $setting_name ] = isset( $action_config['meta'][ $meta_key ] ) ? $action_config['meta'][ $meta_key ] : $default_value;
		}

		return array(
			'tpe'                    => Accounts::sanitize_tpe( $raw_settings['tpe'] ),
			'environment'            => Submission_Meta::ENVIRONMENT_PRODUCTION === $raw_settings['environment'] ? Submission_Meta::ENVIRONMENT_PRODUCTION : Submission_Meta::ENVIRONMENT_SANDBOX,
			'currency'               => Amount::sanitize_currency( $raw_settings['currency'] ),
			'language'               => Payment_Form::sanitize_language( $raw_settings['language'] ),
			'amount_field'           => Submission_Reader::get_meta_key( $raw_settings['amount_field'] ),
			'email_field'            => Submission_Reader::get_meta_key( $raw_settings['email_field'] ),
			'texte_libre'            => sanitize_textarea_field( (string) $raw_settings['texte_libre'] ),
			'success_url'            => esc_url_raw( trim( (string) $raw_settings['success_url'] ) ),
			'failure_url'            => esc_url_raw( trim( (string) $raw_settings['failure_url'] ) ),
			'send_email'             => self::is_checked( $raw_settings['send_email'] ),
			'email_subject'          => sanitize_text_field( (string) $raw_settings['email_subject'] ),
			'email_template'         => wp_kses_post( (string) $raw_settings['email_template'] ),
			'send_email_admin'       => self::is_checked( $raw_settings['send_email_admin'] ),
			'admin_email_subject'    => sanitize_text_field( (string) $raw_settings['admin_email_subject'] ),
			'admin_email_template'   => wp_kses_post( (string) $raw_settings['admin_email_template'] ),
			'admin_email_recipients' => self::parse_emails( $raw_settings['admin_email_recipients'] ),
			'email_from_name'        => sanitize_text_field( (string) $raw_settings['email_from_name'] ),
			'email_from_address'     => sanitize_email( (string) $raw_settings['email_from_address'] ),
			'action_identifier'      => isset( $action_config['row_index'] ) ? 'row_' . absint( $action_config['row_index'] ) : '',
		);
	}

	/**
	 * Read the settings of the action that started a payment.
	 *
	 * WS Form stores a copy of the form actions on each submission, which keeps
	 * the settings used at submission time even if the form changed since.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function from_submission( $submission ) {
		$actions = isset( $submission->actions ) ? $submission->actions : array();
		if ( is_string( $actions ) ) {
			$actions = maybe_unserialize( $actions );
		}

		if ( ! is_array( $actions ) ) {
			return null;
		}

		$action_identifier = Submission_Meta::get( $submission, 'action_identifier' );
		foreach ( $actions as $action_config ) {
			if ( ! is_array( $action_config ) || self::ACTION_ID !== ( $action_config['id'] ?? '' ) ) {
				continue;
			}

			$settings = self::from_config( $action_config );
			if ( '' === $action_identifier || $settings['action_identifier'] === $action_identifier ) {
				return $settings;
			}
		}

		return null;
	}

	/**
	 * Declare the settings shown in the WS Form action panel.
	 *
	 * Hooked to wsf_config_meta_keys.
	 *
	 * @param array $meta_keys Meta keys declared so far.
	 *
	 * @return array
	 */
	public static function declare_meta_keys( $meta_keys = array() ) {
		return array_merge( $meta_keys, Action_Settings_Fields::get_definitions() );
	}

	/**
	 * Get the meta keys of every setting, as WS Form lists them for the action.
	 *
	 * @return array<int,string>
	 */
	public static function get_meta_keys() {
		return array_map( array( __CLASS__, 'get_meta_key' ), array_keys( self::DEFAULTS ) );
	}

	/**
	 * Tell whether a WS Form checkbox setting is checked.
	 *
	 * @param mixed $value Raw value, "on" when checked.
	 *
	 * @return bool
	 */
	private static function is_checked( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'on', 'true', 'yes' ), true );
	}

	/**
	 * Split a list of emails typed in a textarea.
	 *
	 * @param mixed $raw_list Emails separated by commas, semicolons or line breaks.
	 *
	 * @return array<int,string>
	 */
	private static function parse_emails( $raw_list ) {
		$emails = array();
		foreach ( (array) preg_split( '/[\s,;]+/', (string) $raw_list ) as $candidate ) {
			$email = sanitize_email( (string) $candidate );
			if ( '' !== $email ) {
				$emails[] = $email;
			}
		}

		return array_values( array_unique( $emails ) );
	}
}
