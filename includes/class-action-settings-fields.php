<?php
/**
 * Fields of the Monetico action panel.
 *
 * @package WSForm_Monetico
 */

namespace WSForm_Monetico;

use WSForm_Monetico\Monetico\Payment_Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes each action setting in the format WS Form expects.
 */
class Action_Settings_Fields {

	/**
	 * Get the field definitions, indexed by meta key.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_definitions() {
		$placeholders_help = __( 'Variables : {reference}, {amount}, {date}, {form_name}, {submission_id}, {field:ID}', 'wsform-monetico' );

		$definitions = array(
			'tpe'                    => array(
				'label'         => __( 'Compte Monetico (TPE)', 'wsform-monetico' ),
				'type'          => 'select',
				'options'       => self::get_account_options(),
				'options_blank' => __( 'Sélectionner…', 'wsform-monetico' ),
				'help'          => __( 'Les comptes se déclarent dans WS Form > Monetico, ou dans wp-config.php.', 'wsform-monetico' ),
			),
			'environment'            => array(
				'label'   => __( 'Environnement', 'wsform-monetico' ),
				'type'    => 'select',
				'options' => array(
					array(
						'value' => Submission_Meta::ENVIRONMENT_SANDBOX,
						'text'  => __( 'Test', 'wsform-monetico' ),
					),
					array(
						'value' => Submission_Meta::ENVIRONMENT_PRODUCTION,
						'text'  => __( 'Production', 'wsform-monetico' ),
					),
				),
				'default' => Submission_Meta::ENVIRONMENT_SANDBOX,
			),
			'currency'               => array(
				'label'   => __( 'Devise', 'wsform-monetico' ),
				'type'    => 'select',
				'options' => self::to_options( array_combine( Amount::CURRENCIES, Amount::CURRENCIES ) ),
				'default' => 'EUR',
			),
			'language'               => array(
				'label'   => __( 'Langue de la page de paiement', 'wsform-monetico' ),
				'type'    => 'select',
				'options' => self::to_options( array_combine( Payment_Form::LANGUAGES, Payment_Form::LANGUAGES ) ),
				'default' => 'FR',
			),
			'amount_field'           => array(
				'label'                      => __( 'Champ montant', 'wsform-monetico' ),
				'type'                       => 'select',
				'options'                    => 'fields',
				'options_blank'              => __( 'Sélectionner…', 'wsform-monetico' ),
				'help'                       => __( 'Champ qui contient le montant TTC à payer.', 'wsform-monetico' ),
				'fields_filter_type_exclude' => array( 'html', 'captcha', 'submit' ),
			),
			'email_field'            => array(
				'label'              => __( 'Champ e-mail du payeur', 'wsform-monetico' ),
				'type'               => 'select',
				'options'            => 'fields',
				'options_blank'      => __( 'Sélectionner…', 'wsform-monetico' ),
				'fields_filter_type' => array( 'email' ),
			),
			'texte_libre'            => array(
				'label'       => __( 'Texte libre (optionnel)', 'wsform-monetico' ),
				'type'        => 'textarea',
				'help'        => __( 'Renvoyé par Monetico et visible dans votre back-office. Un champ s’insère avec {{field:ID}}.', 'wsform-monetico' ),
				'placeholder' => __( 'Devis {{field:12}}', 'wsform-monetico' ),
			),
			'success_url'            => array(
				'label'       => __( 'URL de retour après paiement (optionnelle)', 'wsform-monetico' ),
				'type'        => 'text',
				'placeholder' => home_url( '/paiement-ok/' ),
			),
			'failure_url'            => array(
				'label'       => __( 'URL de retour après échec (optionnelle)', 'wsform-monetico' ),
				'type'        => 'text',
				'placeholder' => home_url( '/paiement-ko/' ),
			),
			'send_email'             => array(
				'label' => __( 'Envoyer un e-mail de confirmation au payeur', 'wsform-monetico' ),
				'type'  => 'checkbox',
			),
			'email_subject'          => array(
				'label'       => __( 'Sujet de l’e-mail au payeur', 'wsform-monetico' ),
				'type'        => 'text',
				'placeholder' => Email_Sender::get_default( 'email_subject' ),
				'help'        => $placeholders_help,
			),
			'email_template'         => array(
				'label'       => __( 'Contenu de l’e-mail au payeur (HTML)', 'wsform-monetico' ),
				'type'        => 'textarea',
				'placeholder' => Email_Sender::get_default( 'email_template' ),
				'help'        => $placeholders_help,
			),
			'send_email_admin'       => array(
				'label' => __( 'Envoyer un e-mail à l’équipe', 'wsform-monetico' ),
				'type'  => 'checkbox',
			),
			'admin_email_subject'    => array(
				'label'       => __( 'Sujet de l’e-mail à l’équipe', 'wsform-monetico' ),
				'type'        => 'text',
				'placeholder' => Email_Sender::get_default( 'admin_email_subject' ),
				'help'        => $placeholders_help,
			),
			'admin_email_template'   => array(
				'label'       => __( 'Contenu de l’e-mail à l’équipe (HTML)', 'wsform-monetico' ),
				'type'        => 'textarea',
				'placeholder' => Email_Sender::get_default( 'admin_email_template' ),
				'help'        => $placeholders_help,
			),
			'admin_email_recipients' => array(
				'label'       => __( 'Destinataires de l’e-mail à l’équipe', 'wsform-monetico' ),
				'type'        => 'textarea',
				'placeholder' => 'compta@example.com, contact@example.com',
			),
			'email_from_name'        => array(
				'label'       => __( 'Nom de l’expéditeur', 'wsform-monetico' ),
				'type'        => 'text',
				'placeholder' => get_bloginfo( 'name' ),
			),
			'email_from_address'     => array(
				'label'       => __( 'Adresse de l’expéditeur', 'wsform-monetico' ),
				'type'        => 'text',
				'placeholder' => get_option( 'admin_email' ),
			),
		);

		$definitions_by_meta_key = array();
		foreach ( $definitions as $setting_name => $definition ) {
			$definitions_by_meta_key[ Action_Settings::get_meta_key( $setting_name ) ] = $definition;
		}

		return $definitions_by_meta_key;
	}

	/**
	 * List the declared accounts for the TPE select.
	 *
	 * @return array<int,array{value:string,text:string}>
	 */
	private static function get_account_options() {
		$labels = array();
		foreach ( Accounts::get_all() as $tpe => $account ) {
			$labels[ $tpe ] = sprintf( '%s (%s)', $tpe, $account['company'] );
		}

		return self::to_options( $labels );
	}

	/**
	 * Convert a value => label map to WS Form select options.
	 *
	 * @param array<string,string> $labels Labels indexed by value.
	 *
	 * @return array<int,array{value:string,text:string}>
	 */
	private static function to_options( array $labels ) {
		$options = array();
		foreach ( $labels as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'text'  => $label,
			);
		}

		return $options;
	}
}
