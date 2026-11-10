<?php
/**
 * Shared PRO blocked-request history view.
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $this->is_pro() ) {
	return;
}
?>
<div class="wpmtk-boost-history" data-boost-history <?php echo $this->state()['active'] ? '' : 'hidden'; ?>>
	<h3><?php esc_html_e( 'Blocked requests', 'wpmastertoolkit' ); ?></h3>
	<p data-history-status role="status" aria-live="polite"><?php esc_html_e( 'Loading blocked requests...', 'wpmastertoolkit' ); ?></p>
	<noscript><p><?php esc_html_e( 'JavaScript is required to display blocked-request history.', 'wpmastertoolkit' ); ?></p></noscript>
	<div class="wpmtk-boost-history-scroll" data-history-table hidden tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Blocked requests', 'wpmastertoolkit' ); ?>">
		<table class="widefat striped">
			<caption class="screen-reader-text"><?php esc_html_e( 'Latest blocked requests, newest first', 'wpmastertoolkit' ); ?></caption>
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Time', 'wpmastertoolkit' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Destination', 'wpmastertoolkit' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Method', 'wpmastertoolkit' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Administration page', 'wpmastertoolkit' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Blocking reason', 'wpmastertoolkit' ); ?></th>
			</tr></thead>
			<tbody data-history-rows></tbody>
		</table>
	</div>
</div>