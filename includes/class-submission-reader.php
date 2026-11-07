<?php
/**
 * Read access to WS Form submissions.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads submissions and reads field values through the WS Form public API.
 */
class Submission_Reader {

	/**
	 * Load a submission.
	 *
	 * @param int $submission_id Submission ID.
	 *
	 * @return \WS_Form_Submit|null
	 */
	public static function get( $submission_id ) {
		$submission_id = absint( $submission_id );
		if ( $submission_id <= 0 ) {
			return null;
		}

		try {
			$submission = wsf_submit_get_object( $submission_id );
		} catch ( \Throwable $exception ) {
			// WS Form throws when the ID does not exist.
			return null;
		}

		return ( $submission && absint( $submission->id ) === $submission_id ) ? $submission : null;
	}

	/**
	 * Read a field value.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 * @param mixed           $field_key  Field ID ("12") or meta key ("field_12").
	 *
	 * @return mixed Field value, or null when the key is empty.
	 */
	public static function get_field_value( $submission, $field_key ) {
		$meta_key = self::get_meta_key( $field_key );
		if ( '' === $meta_key ) {
			return null;
		}

		return wsf_submit_get_value( $submission, $meta_key, null );
	}

	/**
	 * Read a field value as text, flattening checkboxes and selects.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 * @param mixed           $field_key  Field ID or meta key.
	 *
	 * @return string
	 */
	public static function get_field_text( $submission, $field_key ) {
		$value = self::get_field_value( $submission, $field_key );

		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Replace {field:ID} and {{field:ID}} tags with field values.
	 *
	 * @param string          $text       Text with tags.
	 * @param \WS_Form_Submit $submission Submission.
	 * @param bool            $escape     Escape values for HTML, for email bodies.
	 *
	 * @return string
	 */
	public static function replace_field_tags( $text, $submission, $escape = false ) {
		if ( ! preg_match_all( '/{{?\s*field:([^}]+?)\s*}}?/i', $text, $matches, PREG_SET_ORDER ) ) {
			return $text;
		}

		foreach ( $matches as $match ) {
			$value = self::get_field_text( $submission, $match[1] );
			$text  = str_replace( $match[0], $escape ? esc_html( $value ) : $value, $text );
		}

		return $text;
	}

	/**
	 * Get the form label of a submission.
	 *
	 * @param \WS_Form_Submit $submission Submission.
	 *
	 * @return string
	 */
	public static function get_form_label( $submission ) {
		$form_id = isset( $submission->form_id ) ? absint( $submission->form_id ) : 0;

		try {
			$form = $form_id ? wsf_form_get_object( $form_id, false, false ) : null;
		} catch ( \Throwable $exception ) {
			$form = null;
		}

		return ( $form && ! empty( $form->label ) ) ? sanitize_text_field( $form->label ) : get_bloginfo( 'name' );
	}

	/**
	 * Convert a field reference from the action settings to a WS Form meta key.
	 *
	 * @param mixed $field_key Field ID or meta key.
	 *
	 * @return string
	 */
	public static function get_meta_key( $field_key ) {
		if ( is_numeric( $field_key ) ) {
			return absint( $field_key ) > 0 ? 'field_' . absint( $field_key ) : '';
		}

		return sanitize_key( (string) $field_key );
	}
}
