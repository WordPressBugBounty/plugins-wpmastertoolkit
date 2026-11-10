<?php
/**
 * Admin Boost settings and structured rule editors.
 *
 * @package WPMastertoolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpmtk_boost_pro   = $this->is_pro();
$wpmtk_boost_state = $this->state();
?>
<div class="wpmtk-boost-page">
	<?php require WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php'; ?>
<div class="wpmtk-boost-settings" id="wpmtk-boost-settings">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Admin Boost', 'wpmastertoolkit' ); ?></h1>
	<p class="wpmtk-boost-message" role="status" aria-live="polite"></p>
	<section id="wpmtk-boost-temporary" class="wp-mastertoolkit__section" aria-labelledby="wpmtk-boost-temporary-title">
		<h2 id="wpmtk-boost-temporary-title" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Temporary boost', 'wpmastertoolkit' ); ?></h2>
		<p class="wp-mastertoolkit__section__desc"><?php esc_html_e( 'External license checks, template libraries, statistics and integrations may be unavailable during the boost.', 'wpmastertoolkit' ); ?></p>
		<div class="wp-mastertoolkit__section__body">
		<div class="wp-mastertoolkit__section__body__item" data-boost-statuses <?php echo $wpmtk_boost_state['active'] ? '' : 'hidden'; ?>>
		<div class="wp-mastertoolkit__section__body__item__content">
		<p data-boost-state <?php echo $wpmtk_boost_state['active'] ? '' : 'hidden'; ?>><?php echo $wpmtk_boost_state['active'] ? esc_html__( 'Boost active', 'wpmastertoolkit' ) : ''; ?></p>
		</div>
		</div>
		<div class="wp-mastertoolkit__section__body__item">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-boost-command="start">
			<?php $this->nonce_field(); ?>
			<input type="hidden" name="action" value="wpmtk_admin_boost">
			<input type="hidden" name="command" value="start">
			<label for="wpmtk-boost-duration" class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Duration', 'wpmastertoolkit' ); ?></label>
			<div class="wp-mastertoolkit__section__body__item__content">
			<div class="wp-mastertoolkit__select">
			<select id="wpmtk-boost-duration" name="duration">
				<option value="120"><?php esc_html_e( '2 minutes', 'wpmastertoolkit' ); ?></option>
				<option value="600" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( '10 minutes', 'wpmastertoolkit' ) : esc_html__( '10 minutes (Pro)', 'wpmastertoolkit' ); ?></option>
				<option value="1800" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( '30 minutes', 'wpmastertoolkit' ) : esc_html__( '30 minutes (Pro)', 'wpmastertoolkit' ); ?></option>
				<option value="3600" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( '60 minutes', 'wpmastertoolkit' ) : esc_html__( '60 minutes (Pro)', 'wpmastertoolkit' ); ?></option>
				<option value="0" <?php disabled( ! $wpmtk_boost_pro ); ?>><?php echo $wpmtk_boost_pro ? esc_html__( 'Until stopped or logged out', 'wpmastertoolkit' ) : esc_html__( 'Until stopped or logged out (Pro)', 'wpmastertoolkit' ); ?></option>
			</select>
			</div>
			<div class="wp-mastertoolkit__button wpmtk-boost-toolbar"><button type="submit"><?php esc_html_e( 'Activate boost', 'wpmastertoolkit' ); ?></button></div>
			</div>
		</form>
		<div class="wpmtk-boost-session-controls wp-mastertoolkit__button" <?php echo $wpmtk_boost_state['active'] ? '' : 'hidden'; ?>>
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
					<button class="secondary" type="submit"><?php echo esc_html( $wpmtk_boost_label ); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
		</div>
		</div>
		<?php if ( $wpmtk_boost_pro ) : ?>
			<div id="wpmtk-boost-history-settings">
				<?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/admin-boost/history.php'; ?>
			</div>
		<?php endif; ?>
	</section>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpmtk-boost-settings-form">
		<?php $this->nonce_field(); ?>
		<input type="hidden" name="action" value="wpmtk_admin_boost">
		<input type="hidden" name="command" value="save_rules">
		<section id="wpmtk-boost-rules" class="wp-mastertoolkit__section" aria-labelledby="wpmtk-boost-rules-title">
			<h2 id="wpmtk-boost-rules-title" class="wp-mastertoolkit__section__body__item__title">
				<?php esc_html_e( 'External Request Rules', 'wpmastertoolkit' ); ?>
				<?php if ( ! $wpmtk_boost_pro ) : ?>
					<span class="wp-mastertoolkit__section__body__item__title__tag pro">Pro</span>
				<?php endif; ?>
			</h2>
			<div class="wp-mastertoolkit__section__body">
			<div class="wp-mastertoolkit__section__body__item">
			<div class="wp-mastertoolkit__section__body__item__content">
			<?php if ( $wpmtk_boost_pro ) : ?>
				<div class="wpmtk-boost-toolbar wp-mastertoolkit__button">
					<button class="secondary" type="button" data-add-rule><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add rule', 'wpmastertoolkit' ); ?></button>
				</div>
				<div class="wpmtk-boost-table-scroll"><table class="widefat striped"><caption class="screen-reader-text"><?php esc_html_e( 'Request rules in priority order', 'wpmastertoolkit' ); ?></caption><thead><tr><th scope="col"><?php esc_html_e( 'Enabled', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Name', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Behavior', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Match', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'URL', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Method', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></th></tr></thead><tbody id="wpmtk-boost-rules-body"></tbody></table></div>
				<div class="wp-mastertoolkit__button wpmtk-boost-toolbar"><button type="submit" data-save-rules><?php esc_html_e( 'Save rules', 'wpmastertoolkit' ); ?></button><span data-rules-dirty role="status" aria-live="polite" hidden><?php esc_html_e( 'Unsaved changes', 'wpmastertoolkit' ); ?></span></div>
				<p data-rules-message role="status" aria-live="polite"></p>
				<div data-collection-conflict="rules" hidden><div class="wp-mastertoolkit__button"><button class="secondary" type="button" data-reload-collection="rules"><?php esc_html_e( 'Reload rules', 'wpmastertoolkit' ); ?></button></div></div>
			<?php else : ?>
				<p><?php esc_html_e( 'Targeted allow and block rules require Pro.', 'wpmastertoolkit' ); ?></p>
			<?php endif; ?>
			</div>
			</div>
			</div>
		</section>
		<section id="wpmtk-boost-pages" class="wp-mastertoolkit__section" aria-labelledby="wpmtk-boost-pages-title">
			<h2 id="wpmtk-boost-pages-title" class="wp-mastertoolkit__section__body__item__title">
				<?php esc_html_e( 'Excluded pages', 'wpmastertoolkit' ); ?>
				<?php if ( ! $wpmtk_boost_pro ) : ?>
					<span class="wp-mastertoolkit__section__body__item__title__tag pro">Pro</span>
				<?php endif; ?>
			</h2>
			<div class="wp-mastertoolkit__section__body">
			<div class="wp-mastertoolkit__section__body__item">
			<div class="wp-mastertoolkit__section__body__item__content">
			<?php if ( $wpmtk_boost_pro ) : ?>
				<div class="wp-mastertoolkit__button wpmtk-boost-toolbar"><button class="secondary" type="button" data-add-page><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add page exclusion', 'wpmastertoolkit' ); ?></button></div>
				<p data-pages-message role="status" aria-live="polite"></p>
				<div data-collection-conflict="pages" hidden><div class="wp-mastertoolkit__button"><button class="secondary" type="button" data-reload-collection="pages"><?php esc_html_e( 'Reload exclusions', 'wpmastertoolkit' ); ?></button></div></div>
				<div class="wpmtk-boost-table-scroll"><table class="widefat striped"><caption class="screen-reader-text"><?php esc_html_e( 'Excluded administration pages', 'wpmastertoolkit' ); ?></caption><thead><tr><th scope="col"><?php esc_html_e( 'Page match', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Path or expression', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'URL parameters', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></th></tr></thead><tbody id="wpmtk-boost-pages-body"></tbody></table></div>
			<?php else : ?>
				<p><?php esc_html_e( 'Custom page exclusions require Pro. Technical exclusions remain active in every edition.', 'wpmastertoolkit' ); ?></p>
			<?php endif; ?>
			</div>
			</div>
			</div>
		</section>
	</form>
	<section id="wpmtk-boost-debug" class="wp-mastertoolkit__section" aria-labelledby="wpmtk-boost-debug-title">
		<h2 id="wpmtk-boost-debug-title" class="wp-mastertoolkit__section__body__item__title">
			<?php esc_html_e( 'Debug', 'wpmastertoolkit' ); ?>
			<?php if ( ! $wpmtk_boost_pro ) : ?>
				<span class="wp-mastertoolkit__section__body__item__title__tag pro">Pro</span>
			<?php endif; ?>
		</h2>
		<div class="wp-mastertoolkit__section__body">
		<div class="wp-mastertoolkit__section__body__item">
		<div class="wp-mastertoolkit__section__body__item__content">
		<?php if ( $wpmtk_boost_pro ) : ?>
			<p data-debug-state hidden></p>
			<div class="wpmtk-boost-toolbar wp-mastertoolkit__button">
				<?php
				foreach ( array(
					'debug_start' => __( 'Observe for 10 minutes', 'wpmastertoolkit' ),
					'debug_stop'  => __( 'Stop debug', 'wpmastertoolkit' ),
					'clear'       => __( 'Clear journal', 'wpmastertoolkit' ),
				) as $wpmtk_boost_command => $wpmtk_boost_label ) :
					$wpmtk_boost_command_hidden = ( 'debug_start' === $wpmtk_boost_command && $wpmtk_boost_state['debug_until'] > time() )
						|| ( 'debug_stop' === $wpmtk_boost_command && $wpmtk_boost_state['debug_until'] <= time() )
						|| ( 'clear' === $wpmtk_boost_command && empty( $entries ) );
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-boost-command="<?php echo esc_attr( $wpmtk_boost_command ); ?>" <?php echo $wpmtk_boost_command_hidden ? 'hidden' : ''; ?>>
						<?php $this->nonce_field(); ?>
						<input type="hidden" name="action" value="wpmtk_admin_boost">
						<input type="hidden" name="command" value="<?php echo esc_attr( $wpmtk_boost_command ); ?>">
						<button class="secondary" type="submit"><?php echo esc_html( $wpmtk_boost_label ); ?></button>
					</form>
				<?php endforeach; ?>
			</div>
			<div class="wpmtk-boost-toolbar wp-mastertoolkit__select" id="wpmtk-boost-log-filters">
				<label><?php esc_html_e( 'Domain', 'wpmastertoolkit' ); ?><select data-log-filter="domain"></select></label>
				<label><?php esc_html_e( 'Decision', 'wpmastertoolkit' ); ?><select data-log-filter="decision"></select></label>
				<label><?php esc_html_e( 'Page', 'wpmastertoolkit' ); ?><select data-log-filter="page"></select></label>
				<label><?php esc_html_e( 'Rule', 'wpmastertoolkit' ); ?><select data-log-filter="rule"></select></label>
			</div>
			<div class="wpmtk-boost-table-scroll"><table class="widefat striped"><caption><?php esc_html_e( 'Session journal: up to 500 calls, retained for 24 hours', 'wpmastertoolkit' ); ?></caption><thead><tr><th scope="col"><?php esc_html_e( 'Time', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Remote request', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Administration page', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Decision / reason / rule', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Observed result', 'wpmastertoolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Actions', 'wpmastertoolkit' ); ?></th></tr></thead><tbody id="wpmtk-boost-log-body"></tbody></table></div>
		<?php else : ?>
			<p><?php esc_html_e( 'Session-private HTTP diagnostics require Pro.', 'wpmastertoolkit' ); ?></p>
		<?php endif; ?>
		</div>
		</div>
		</div>
	</section>
	<?php if ( $wpmtk_boost_pro ) : ?>
		<dialog id="wpmtk-boost-confirm-dialog" class="wpmtk-boost-dialog" aria-labelledby="wpmtk-boost-confirm-title" aria-describedby="wpmtk-boost-confirm-message">
			<form method="dialog">
				<h2 id="wpmtk-boost-confirm-title" data-confirm-title><?php esc_html_e( 'Reload section?', 'wpmastertoolkit' ); ?></h2>
				<p id="wpmtk-boost-confirm-message" data-confirm-message></p>
				<button class="button" value="cancel" autofocus><?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></button>
				<button class="button button-primary" value="confirm" data-confirm-submit><?php esc_html_e( 'Discard edits and reload', 'wpmastertoolkit' ); ?></button>
			</form>
		</dialog>
		<dialog id="wpmtk-boost-editor" class="wpmtk-boost-dialog" aria-labelledby="wpmtk-boost-editor-title">
			<form id="wpmtk-boost-editor-form">
				<div class="wpmtk-boost-dialog-heading"><h2 id="wpmtk-boost-editor-title"><?php esc_html_e( 'Add External Request Rule', 'wpmastertoolkit' ); ?></h2><button type="button" class="button wpmtk-boost-icon" data-close-dialog aria-label="<?php esc_attr_e( 'Close', 'wpmastertoolkit' ); ?>" title="<?php esc_attr_e( 'Close', 'wpmastertoolkit' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
				<div data-rule-fields class="wpmtk-boost-fields">
					<div class="wpmtk-boost-rule-heading">
						<label class="wpmtk-boost-rule-enabled"><input type="checkbox" name="enabled" checked> <?php esc_html_e( 'Enabled', 'wpmastertoolkit' ); ?></label>
						<label class="wpmtk-boost-rule-name"><?php esc_html_e( 'Rule name (optional)', 'wpmastertoolkit' ); ?><input name="label" maxlength="80" type="text"></label>
					</div>
					<label><?php esc_html_e( 'Rule behavior', 'wpmastertoolkit' ); ?><select name="behavior" required><option value="allow_boost"><?php esc_html_e( 'Allow during boost', 'wpmastertoolkit' ); ?></option><option value="allow_permanent"><?php esc_html_e( 'Always allow in admin', 'wpmastertoolkit' ); ?></option><option value="block_permanent"><?php esc_html_e( 'Always block in admin', 'wpmastertoolkit' ); ?></option></select></label>
					<label><?php esc_html_e( 'Match', 'wpmastertoolkit' ); ?><select name="kind"><option value="endpoint"><?php esc_html_e( 'Exact URL path', 'wpmastertoolkit' ); ?></option><option value="prefix"><?php esc_html_e( 'URL path prefix', 'wpmastertoolkit' ); ?></option><option value="domain"><?php esc_html_e( 'Exact domain', 'wpmastertoolkit' ); ?></option><option value="subdomains"><?php esc_html_e( 'Domain and subdomains', 'wpmastertoolkit' ); ?></option><option value="regex"><?php esc_html_e( 'Regular expression', 'wpmastertoolkit' ); ?></option></select></label>
					<label><span data-destination-label><?php esc_html_e( 'Website or URL', 'wpmastertoolkit' ); ?></span><input name="value" maxlength="2048" type="text" required autocomplete="off" spellcheck="false" placeholder="https://api.example.com/v1/status"></label>
					<label><?php esc_html_e( 'HTTP method', 'wpmastertoolkit' ); ?><select name="method"><option value="*"><?php esc_html_e( 'All methods', 'wpmastertoolkit' ); ?></option>
					<?php
					foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT' ) as $wpmtk_boost_method ) :
						?>
						<option value="<?php echo esc_attr( $wpmtk_boost_method ); ?>"><?php echo esc_html( $wpmtk_boost_method ); ?></option><?php endforeach; ?></select></label>
					<label><input type="checkbox" name="limit_page"> <?php esc_html_e( 'Only on specific admin pages', 'wpmastertoolkit' ); ?></label>
				</div>
				<fieldset data-page-fields class="wpmtk-boost-fields" aria-label="<?php esc_attr_e( 'Administration page criteria', 'wpmastertoolkit' ); ?>">
					<label><?php esc_html_e( 'Page', 'wpmastertoolkit' ); ?><select name="common"><option value=""><?php esc_html_e( 'Select a page', 'wpmastertoolkit' ); ?></option><option value="custom"><?php esc_html_e( 'Custom', 'wpmastertoolkit' ); ?></option></select></label>
					<div data-subpage-field hidden><label><?php esc_html_e( 'Subpage', 'wpmastertoolkit' ); ?><select name="subpage"></select></label></div>
					<details data-page-advanced>
						<summary><?php esc_html_e( 'Advanced options', 'wpmastertoolkit' ); ?></summary>
						<div class="wpmtk-boost-fields">
							<label><?php esc_html_e( 'Page match', 'wpmastertoolkit' ); ?><select name="page_kind"><option value="exact"><?php esc_html_e( 'Exact path', 'wpmastertoolkit' ); ?></option><option value="prefix"><?php esc_html_e( 'Path prefix', 'wpmastertoolkit' ); ?></option><option value="regex"><?php esc_html_e( 'Regular expression', 'wpmastertoolkit' ); ?></option></select></label>
							<label><?php esc_html_e( 'Path or expression', 'wpmastertoolkit' ); ?><input type="text" name="page_value" maxlength="2048" autocomplete="off" spellcheck="false"></label>
							<label><?php esc_html_e( 'Required URL parameters', 'wpmastertoolkit' ); ?><input type="text" name="page_query" maxlength="2048" placeholder="page=plugin-settings" autocomplete="off" spellcheck="false"></label>
							<label><?php esc_html_e( 'Absent URL parameters', 'wpmastertoolkit' ); ?><input type="text" name="page_absent" maxlength="2048" placeholder="page, post_type" autocomplete="off" spellcheck="false"></label>
						</div>
					</details>
				</fieldset>
				<p class="wpmtk-boost-editor-message" id="wpmtk-boost-editor-message" role="status" aria-live="polite"></p>
				<div class="wpmtk-boost-dialog-actions">
					<button class="button button-primary" type="submit" data-editor-save><?php esc_html_e( 'Add rule', 'wpmastertoolkit' ); ?></button>
					<button class="button" type="button" data-close-dialog data-rule-cancel><?php esc_html_e( 'Cancel', 'wpmastertoolkit' ); ?></button>
				</div>
				<div data-editor-conflict hidden><button class="button" type="button" data-editor-reload><?php esc_html_e( 'Reload exclusions', 'wpmastertoolkit' ); ?></button></div>
			</form>
		</dialog>
	<?php endif; ?>
</div>
</div>
</div>