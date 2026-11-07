<?php
/**
 * Monetico accounts settings page.
 *
 * @package WSForm_Monetico
 *
 * @var array $args {
 *     @type array  $option_accounts   Accounts saved from this page, indexed by TPE.
 *     @type array  $constant_accounts Accounts declared in wp-config.php, indexed by TPE.
 *     @type string $ipn_url           URL to declare in the Monetico back office.
 *     @type string $option_name       Option name used for field names.
 *     @type string $settings_group    Settings API group.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wsform_monetico_rows   = $args['option_accounts'];
$wsform_monetico_rows[] = array();
$wsform_monetico_index  = 0;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Comptes Monetico', 'wsform-monetico' ); ?></h1>
	<?php settings_errors(); ?>

	<p>
		<?php esc_html_e( 'URL de l’interface retour (IPN) à communiquer à Monetico :', 'wsform-monetico' ); ?>
		<code><?php echo esc_html( $args['ipn_url'] ); ?></code>
	</p>

	<?php if ( $args['constant_accounts'] ) : ?>
		<h2><?php esc_html_e( 'Comptes déclarés dans wp-config.php', 'wsform-monetico' ); ?></h2>
		<p><?php esc_html_e( 'Ces comptes sont prioritaires sur ceux de cette page pour le même TPE.', 'wsform-monetico' ); ?></p>
		<ul>
			<?php foreach ( array_keys( $args['constant_accounts'] ) as $wsform_monetico_tpe ) : ?>
				<li><code><?php echo esc_html( (string) $wsform_monetico_tpe ); ?></code></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Comptes enregistrés ici', 'wsform-monetico' ); ?></h2>
	<p><?php esc_html_e( 'La clé enregistrée n’est jamais réaffichée. Laissez le champ vide pour la conserver.', 'wsform-monetico' ); ?></p>

	<form method="post" action="options.php">
		<?php settings_fields( $args['settings_group'] ); ?>
		<table class="widefat striped" role="presentation">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'TPE', 'wsform-monetico' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Code société', 'wsform-monetico' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Clé de sécurité', 'wsform-monetico' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Supprimer', 'wsform-monetico' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wsform_monetico_rows as $wsform_monetico_tpe => $wsform_monetico_account ) : ?>
					<?php
					$wsform_monetico_is_new = empty( $wsform_monetico_account );
					$wsform_monetico_name   = sprintf( '%s[rows][%d]', $args['option_name'], $wsform_monetico_index++ );
					?>
					<tr>
						<td>
							<?php if ( $wsform_monetico_is_new ) : ?>
								<input type="text" name="<?php echo esc_attr( $wsform_monetico_name . '[tpe]' ); ?>" class="regular-text" autocomplete="off" aria-label="<?php esc_attr_e( 'Nouveau TPE', 'wsform-monetico' ); ?>">
							<?php else : ?>
								<code><?php echo esc_html( (string) $wsform_monetico_tpe ); ?></code>
								<input type="hidden" name="<?php echo esc_attr( $wsform_monetico_name . '[tpe]' ); ?>" value="<?php echo esc_attr( (string) $wsform_monetico_tpe ); ?>">
							<?php endif; ?>
						</td>
						<td>
							<input type="text" name="<?php echo esc_attr( $wsform_monetico_name . '[company]' ); ?>" value="<?php echo esc_attr( $wsform_monetico_account['company'] ?? '' ); ?>" class="regular-text" autocomplete="off" aria-label="<?php esc_attr_e( 'Code société', 'wsform-monetico' ); ?>">
						</td>
						<td>
							<input type="password" name="<?php echo esc_attr( $wsform_monetico_name . '[key]' ); ?>" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $wsform_monetico_is_new ? '' : esc_attr__( 'Clé enregistrée', 'wsform-monetico' ); ?>" aria-label="<?php esc_attr_e( 'Clé de sécurité', 'wsform-monetico' ); ?>">
						</td>
						<td>
							<?php if ( ! $wsform_monetico_is_new ) : ?>
								<input type="checkbox" name="<?php echo esc_attr( $wsform_monetico_name . '[delete]' ); ?>" value="1" aria-label="<?php esc_attr_e( 'Supprimer ce compte', 'wsform-monetico' ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
