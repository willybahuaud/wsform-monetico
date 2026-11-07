<?php
/**
 * Payment status block and shortcode output.
 *
 * @package WSForm_Monetico
 *
 * @var array $args {
 *     @type string               $status_label Status label.
 *     @type string               $status_class CSS class of the status.
 *     @type string               $message      Main message.
 *     @type string               $subtitle     Reference line, may be empty.
 *     @type array<string,string> $details      Details indexed by label.
 *     @type bool                 $show_details Whether to show details.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wsf-monetico-status-block">
	<div class="wsf-monetico-status-heading">
		<span class="wsf-monetico-badge <?php echo esc_attr( $args['status_class'] ); ?>">
			<?php echo esc_html( $args['status_label'] ); ?>
		</span>
		<div class="wsf-monetico-heading-text">
			<h3 class="wsf-monetico-status-title"><?php echo esc_html( $args['message'] ); ?></h3>
			<?php if ( ! empty( $args['subtitle'] ) ) : ?>
				<p class="wsf-monetico-status-subtitle"><?php echo esc_html( $args['subtitle'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $args['show_details'] ) && ! empty( $args['details'] ) ) : ?>
		<dl class="wsf-monetico-status-details">
			<?php foreach ( $args['details'] as $wsform_monetico_label => $wsform_monetico_value ) : ?>
				<div class="wsf-monetico-detail-row">
					<dt><?php echo esc_html( $wsform_monetico_label ); ?></dt>
					<dd><?php echo esc_html( $wsform_monetico_value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>
</div>
