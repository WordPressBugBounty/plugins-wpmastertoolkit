<?php
/**
 * Toolbar boost controls.
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpmtk_boost_pro   = $this->is_pro();
$wpmtk_boost_state = $this->state();
?>
<span id="wpmtk-boost-final-count" hidden data-count="<?php echo esc_attr( $this->blocked ); ?>"></span>
<dialog id="wpmtk-boost-dialog" class="wpmtk-boost-dialog" aria-labelledby="wpmtk-boost-dialog-title">
	<div class="wpmtk-boost-dialog-heading">
		<h2 id="wpmtk-boost-dialog-title"><?php esc_html_e( 'Boost Admin', 'wpmastertoolkit' ); ?></h2>
		<button type="button" class="button wpmtk-boost-icon" data-close-dialog aria-label="<?php esc_attr_e( 'Close', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Close', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
	</div>
	<p><?php esc_html_e( 'Temporarily speed up administration by blocking requests to external services. License checks, template libraries, statistics and integrations may be unavailable during the boost. You can stop it at any time.', 'wpmastertoolkit' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-boost-command="start">
		<input type="hidden" name="action" value="wpmtk_admin_boost">
		<input type="hidden" name="command" value="start">
		<?php $this->nonce_field(); ?>
		<label for="wpmtk-boost-dialog-duration"><?php esc_html_e( 'Duration', 'wpmastertoolkit' ); ?></label>
		<select id="wpmtk-boost-dialog-duration" name="duration">
			<option value="120"><?php esc_html_e( '2 minutes', 'wpmastertoolkit' ); ?></option>
			<option value="600" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( '10 minutes', 'wpmastertoolkit' ) : esc_html__( '10 minutes (Pro)', 'wpmastertoolkit' ); ?></option>
			<option value="1800" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( '30 minutes', 'wpmastertoolkit' ) : esc_html__( '30 minutes (Pro)', 'wpmastertoolkit' ); ?></option>
			<option value="3600" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( '60 minutes', 'wpmastertoolkit' ) : esc_html__( '60 minutes (Pro)', 'wpmastertoolkit' ); ?></option>
			<option value="0" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( 'Until stopped or logged out', 'wpmastertoolkit' ) : esc_html__( 'Until stopped or logged out (Pro)', 'wpmastertoolkit' ); ?></option>
		</select>
		<button class="button button-primary" type="submit"><?php esc_html_e( 'Activate boost', 'wpmastertoolkit' ); ?></button>
	</form>
	<div class="wpmtk-boost-session-controls" <?php echo $wpmtk_boost_state['active'] ? '' : 'hidden'; ?>>
		<?php
		foreach ( array(
			'stop'   => __( 'Stop boost', 'wpmastertoolkit' ),
			'reload' => __( 'Reload without temporary boost', 'wpmastertoolkit' ),
		) as $wpmtk_boost_command => $wpmtk_boost_label ) :
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-boost-command="<?php echo esc_attr( $wpmtk_boost_command ); ?>">
				<?php $this->nonce_field(); ?>
				<input type="hidden" name="action" value="wpmtk_admin_boost">
				<input type="hidden" name="command" value="<?php echo esc_attr( $wpmtk_boost_command ); ?>">
				<button class="button" type="submit"><?php echo esc_html( $wpmtk_boost_label ); ?></button>
			</form>
		<?php endforeach; ?>
	</div>
	<p class="wpmtk-boost-message" role="status" aria-live="polite"></p>
	<?php if ( $wpmtk_boost_pro ) : ?>
		<?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/admin-boost/history.php'; ?>
	<?php endif; ?>
</dialog>