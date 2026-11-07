<?php
/**
 * Intermediate page that posts the payment form to Monetico.
 *
 * @package WSForm_Monetico
 *
 * @var array $args {
 *     @type string               $error     Message to show instead of redirecting.
 *     @type string               $endpoint  Monetico payment page URL.
 *     @type array<string,string> $fields    Signed fields.
 *     @type string               $reference Payment reference, empty when the link is invalid.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wsform_monetico_title = $args['error'] ? __( 'Paiement indisponible', 'wsform-monetico' ) : __( 'Redirection vers Monetico', 'wsform-monetico' );
$wsform_monetico_ready = ! $args['error'] && $args['fields'] && $args['endpoint'];

// No wp_head(): this page only lasts a second and must not load theme or third party scripts.
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $wsform_monetico_title ); ?></title>
	<style>
		.wsform-monetico-body {
			margin: 0;
			background: #f3f4f6;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #0f172a;
		}
		.wsform-monetico-card {
			background: #fff;
			border-radius: 12px;
			padding: 2.5rem 3rem;
			max-width: 520px;
			width: 90%;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
			text-align: center;
		}
		.wsform-monetico-spinner {
			width: 48px;
			height: 48px;
			border: 4px solid #e2e8f0;
			border-top-color: #2563eb;
			border-radius: 50%;
			margin: 0 auto 1.5rem;
			animation: wsform-monetico-spin 0.8s linear infinite;
		}
		.wsform-monetico-reference {
			font-size: 0.95rem;
			color: #475569;
			margin-top: 1rem;
			word-break: break-all;
		}
		.wsform-monetico-button {
			margin-top: 1.5rem;
			background: #2563eb;
			color: #fff;
			border: none;
			padding: 0.85rem 1.75rem;
			border-radius: 999px;
			font-size: 1rem;
			cursor: pointer;
			box-shadow: 0 15px 30px -12px rgba(37, 99, 235, 0.75);
		}
		.wsform-monetico-error {
			color: #b91c1c;
		}
		@keyframes wsform-monetico-spin {
			from { transform: rotate(0); }
			to { transform: rotate(360deg); }
		}
	</style>
</head>
<body class="wsform-monetico-body">
	<main class="wsform-monetico-card">
		<?php if ( ! $wsform_monetico_ready ) : ?>
			<h1 class="wsform-monetico-error"><?php echo esc_html( $wsform_monetico_title ); ?></h1>
			<p><?php echo esc_html( $args['error'] ); ?></p>
		<?php else : ?>
			<div class="wsform-monetico-spinner" aria-hidden="true"></div>
			<h1><?php esc_html_e( 'Redirection vers la page de paiement sécurisée…', 'wsform-monetico' ); ?></h1>
			<form id="wsform-monetico-form" method="post" action="<?php echo esc_url( $args['endpoint'] ); ?>">
				<?php foreach ( $args['fields'] as $wsform_monetico_name => $wsform_monetico_value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $wsform_monetico_name ); ?>" value="<?php echo esc_attr( $wsform_monetico_value ); ?>">
				<?php endforeach; ?>
				<button class="wsform-monetico-button" type="submit"><?php esc_html_e( 'Continuer vers le paiement', 'wsform-monetico' ); ?></button>
			</form>
		<?php endif; ?>
		<?php if ( $args['reference'] ) : ?>
			<p class="wsform-monetico-reference">
				<?php
				/* translators: %s: payment reference. */
				printf( esc_html__( 'Référence : %s', 'wsform-monetico' ), esc_html( $args['reference'] ) );
				?>
			</p>
		<?php endif; ?>
	</main>
	<?php
	if ( $wsform_monetico_ready ) {
		wp_print_inline_script_tag( 'document.getElementById("wsform-monetico-form").submit();' );
	}
	?>
</body>
</html>
