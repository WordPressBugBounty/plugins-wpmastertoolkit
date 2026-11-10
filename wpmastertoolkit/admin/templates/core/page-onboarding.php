<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Guided onboarding page.
 *
 * @since 2.25.0
 *
 * @var array $presets        Onboarding presets.
 * @var array $active_modules Active module statuses.
 * @var array $onboarding     Onboarding state.
 * @var string $upgrade_url   Tracked WPMasterToolKit PRO URL.
 */

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wpmtk_onboarding_status = sanitize_key( wp_unslash( $_GET['onboarding_status'] ?? '' ) );
$wpmtk_show_results      = in_array( $wpmtk_onboarding_status, array( 'completed', 'issues', 'error' ), true );
$wpmtk_module_labels     = wpmastertoolkit_options();
$wpmtk_is_pro_version    = wpmastertoolkit_is_pro();
?>

<div class="wrap wp-mastertoolkit wp-mastertoolkit__onboarding">
	<header class="wp-mastertoolkit__onboarding__header">
		<div class="wp-mastertoolkit__onboarding__brand">
			<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
			<img width="51" height="51" src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/icon-128x128.gif' ); ?>" alt="">
			<div>
				<span class="wp-mastertoolkit__onboarding__eyebrow"><?php esc_html_e( 'WPMasterToolKit', 'wpmastertoolkit' ); ?></span>
				<h1><?php esc_html_e( 'Guided setup', 'wpmastertoolkit' ); ?></h1>
			</div>
		</div>
		<?php if ( $wpmtk_show_results ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mastertoolkit-settings' ) ); ?>"><?php esc_html_e( 'Back to modules', 'wpmastertoolkit' ); ?></a>
		<?php endif; ?>
	</header>

	<?php if ( $wpmtk_show_results ) : ?>
		<div class="wp-mastertoolkit__onboarding__main wp-mastertoolkit__onboarding__main--results">
			<section class="wp-mastertoolkit__onboarding__panel" aria-labelledby="wpmtk-onboarding-results-title">
				<div class="wp-mastertoolkit__onboarding__result-icon<?php echo 'completed' === $wpmtk_onboarding_status ? '' : ( 'issues' === $wpmtk_onboarding_status ? ' is-warning' : ' is-error' ); ?>" aria-hidden="true">
					<?php
					$wpmtk_icon_file = 'completed' === $wpmtk_onboarding_status ? 'check.svg' : ( 'issues' === $wpmtk_onboarding_status ? 'triangle-warning.svg' : 'close.svg' );
					echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/' . $wpmtk_icon_file ), wpmastertoolkit_allowed_tags_for_svg_files() );
					?>
				</div>
				<h2 id="wpmtk-onboarding-results-title">
					<?php
					if ( 'completed' === $wpmtk_onboarding_status ) {
						esc_html_e( 'Your setup has been applied', 'wpmastertoolkit' );
					} elseif ( 'issues' === $wpmtk_onboarding_status ) {
						esc_html_e( 'Your setup was applied with some issues', 'wpmastertoolkit' );
					} else {
						esc_html_e( 'The setup could not be completed', 'wpmastertoolkit' );
					}
					?>
				</h2>
				<p><?php esc_html_e( 'Review what changed below. You can adjust every activated module from the main modules screen.', 'wpmastertoolkit' ); ?></p>

				<div class="wp-mastertoolkit__onboarding__results" aria-live="polite">
					<?php if ( empty( $onboarding['last_result'] ) ) : ?>
						<p><?php esc_html_e( 'No module changes were requested.', 'wpmastertoolkit' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $onboarding['last_result'] as $wpmtk_module_id => $wpmtk_result ) : ?>
								<?php
								$wpmtk_result_status = sanitize_key( $wpmtk_result['status'] ?? 'failed' );
								$wpmtk_module_name   = $wpmtk_module_labels[ $wpmtk_module_id ]['name'] ?? esc_html__( 'Guided setup', 'wpmastertoolkit' );
								$wpmtk_status_labels = array(
									'activated'        => esc_html__( 'Activated', 'wpmastertoolkit' ),
									'already_active'   => esc_html__( 'Already active', 'wpmastertoolkit' ),
									'already_inactive' => esc_html__( 'No change', 'wpmastertoolkit' ),
									'deactivated'      => esc_html__( 'Deactivated', 'wpmastertoolkit' ),
									'unavailable'      => esc_html__( 'Unavailable', 'wpmastertoolkit' ),
									'failed'           => esc_html__( 'Failed', 'wpmastertoolkit' ),
								);
								?>
								<li class="is-<?php echo esc_attr( $wpmtk_result_status ); ?>">
									<span><?php echo esc_html( $wpmtk_module_name ); ?></span>
									<strong><?php echo esc_html( $wpmtk_status_labels[ $wpmtk_result_status ] ?? $wpmtk_status_labels['failed'] ); ?></strong>
									<?php if ( ! empty( $wpmtk_result['message'] ) ) : ?>
										<small><?php echo esc_html( $wpmtk_result['message'] ); ?></small>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $onboarding['last_login_url'] ) && in_array( $wpmtk_onboarding_status, array( 'completed', 'issues' ), true ) ) : ?>
					<p><?php esc_html_e( 'Your login URL:', 'wpmastertoolkit' ); ?> <a href="<?php echo esc_url( $onboarding['last_login_url'] ); ?>"><?php echo esc_html( $onboarding['last_login_url'] ); ?></a></p>
				<?php endif; ?>

				<div class="wp-mastertoolkit__onboarding__actions wp-mastertoolkit__button">
					<a class="wpmtk-button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mastertoolkit-settings' ) ); ?>"><?php esc_html_e( 'Manage modules', 'wpmastertoolkit' ); ?></a>
					<a class="wpmtk-button secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPMastertoolkit_Onboarding::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Run guided setup again', 'wpmastertoolkit' ); ?></a>
				</div>
			</section>
		</div>
	<?php else : ?>
		<div class="wp-mastertoolkit__onboarding__main">
			<nav aria-label="<?php esc_attr_e( 'Setup progress', 'wpmastertoolkit' ); ?>">
				<ol class="wp-mastertoolkit__onboarding__steps">
					<li class="is-current" data-step-indicator="1" aria-current="step"><span>1</span><?php esc_html_e( 'Goals', 'wpmastertoolkit' ); ?></li>
					<li data-step-indicator="2"><span>2</span><?php esc_html_e( 'Modules', 'wpmastertoolkit' ); ?></li>
					<li data-step-indicator="3"><span>3</span><?php esc_html_e( 'Review', 'wpmastertoolkit' ); ?></li>
				</ol>
			</nav>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="JS-wpmastertoolkit-onboarding-skip-form" class="wp-mastertoolkit__onboarding__skip-form">
				<input type="hidden" name="action" value="wpmastertoolkit_skip_onboarding">
				<?php wp_nonce_field( 'wpmastertoolkit_skip_onboarding' ); ?>
			</form>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="JS-wpmastertoolkit-onboarding-form">
				<input type="hidden" name="action" value="wpmastertoolkit_apply_onboarding">
				<?php wp_nonce_field( 'wpmastertoolkit_apply_onboarding' ); ?>
				<?php if ( ! empty( $form_state['errors'] ) ) : ?>
					<p class="wp-mastertoolkit__onboarding__form-error" role="alert"><?php esc_html_e( 'Review the highlighted settings before applying your configuration.', 'wpmastertoolkit' ); ?></p>
				<?php endif; ?>

				<section class="wp-mastertoolkit__onboarding__panel" data-step="1" aria-labelledby="wpmtk-onboarding-goals-title">
					<p class="wp-mastertoolkit__onboarding__eyebrow"><?php esc_html_e( 'Start with your priorities', 'wpmastertoolkit' ); ?></p>
					<h2 id="wpmtk-onboarding-goals-title" tabindex="-1"><?php esc_html_e( 'What would you like to improve?', 'wpmastertoolkit' ); ?></h2>
					<p><?php esc_html_e( 'Choose one or more goals. You will review every module before it is activated.', 'wpmastertoolkit' ); ?></p>

					<div class="wp-mastertoolkit__onboarding__goal-grid">
						<?php foreach ( $presets as $wpmtk_preset_id => $wpmtk_preset ) : ?>
							<label class="wp-mastertoolkit__onboarding__goal">
								<input type="checkbox" name="wpmastertoolkit_onboarding_presets[]" value="<?php echo esc_attr( $wpmtk_preset_id ); ?>" data-preset-input="<?php echo esc_attr( $wpmtk_preset_id ); ?>" <?php checked( in_array( $wpmtk_preset_id, $form_state['presets'] ?? array(), true ) ); ?>>
								<span class="wp-mastertoolkit__onboarding__goal__icon" aria-hidden="true">
									<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/' . $wpmtk_preset['icon'] ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</span>
								<span class="wp-mastertoolkit__onboarding__goal__content">
									<strong><?php echo esc_html( $wpmtk_preset['name'] ); ?></strong>
									<small><?php echo esc_html( $wpmtk_preset['description'] ); ?></small>
								</span>
								<span class="wp-mastertoolkit__onboarding__goal__check" aria-hidden="true">
									<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/check.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="wp-mastertoolkit__onboarding__panel" data-step="2" aria-labelledby="wpmtk-onboarding-modules-title">
					<p class="wp-mastertoolkit__onboarding__eyebrow"><?php esc_html_e( 'Review recommendations', 'wpmastertoolkit' ); ?></p>
					<h2 id="wpmtk-onboarding-modules-title" tabindex="-1"><?php esc_html_e( 'Choose the modules to activate', 'wpmastertoolkit' ); ?></h2>
					<p><?php esc_html_e( 'Recommended modules are selected. Optional modules remain off until you choose them.', 'wpmastertoolkit' ); ?></p>

					<nav class="wp-mastertoolkit__onboarding__goal-steps" data-onboarding-goal-progress aria-label="<?php esc_attr_e( 'Selected goal progress', 'wpmastertoolkit' ); ?>" hidden>
						<ol>
							<?php foreach ( $presets as $wpmtk_preset_id => $wpmtk_preset ) : ?>
								<li data-preset-indicator="<?php echo esc_attr( $wpmtk_preset_id ); ?>"><?php echo esc_html( $wpmtk_preset['name'] ); ?></li>
							<?php endforeach; ?>
						</ol>
					</nav>
					<p class="screen-reader-text" data-onboarding-goal-status data-goal-label="<?php esc_attr_e( 'Goal', 'wpmastertoolkit' ); ?>" data-of-label="<?php esc_attr_e( 'of', 'wpmastertoolkit' ); ?>" aria-live="polite" aria-atomic="true"></p>

					<div class="wp-mastertoolkit__onboarding__preset-modules">
						<?php foreach ( $presets as $wpmtk_preset_id => $wpmtk_preset ) : ?>
							<section data-preset-modules="<?php echo esc_attr( $wpmtk_preset_id ); ?>" aria-labelledby="wpmtk-preset-<?php echo esc_attr( $wpmtk_preset_id ); ?>">
								<h3 id="wpmtk-preset-<?php echo esc_attr( $wpmtk_preset_id ); ?>" data-preset-heading tabindex="-1"><?php echo esc_html( $wpmtk_preset['name'] ); ?></h3>
								<div class="wp-mastertoolkit__onboarding__module-list">
									<?php foreach ( $wpmtk_preset['modules'] as $wpmtk_module ) : ?>
										<?php
										$wpmtk_is_active        = isset( $active_modules[ $wpmtk_module['id'] ] ) && '1' === $active_modules[ $wpmtk_module['id'] ];
										$wpmtk_is_checked       = $wpmtk_module['available'] && ( $wpmtk_is_active || ( isset( $form_state['modules'] ) ? in_array( $wpmtk_module['id'], $form_state['modules'], true ) : $wpmtk_module['recommended'] ) );
										$wpmtk_is_disabled      = $wpmtk_is_active || ! $wpmtk_module['available'];
										$wpmtk_is_pro_promotion = ! $wpmtk_is_pro_version && $wpmtk_module['pro'] && ! $wpmtk_module['available'];
										$wpmtk_module_classes   = array( 'wp-mastertoolkit__onboarding__module' );

										if ( $wpmtk_is_active ) {
											$wpmtk_module_classes[] = 'is-active';
										}

										if ( ! $wpmtk_module['available'] ) {
											$wpmtk_module_classes[] = 'is-unavailable';
										}

										if ( $wpmtk_is_pro_promotion ) {
											$wpmtk_module_classes[] = 'is-pro-promotion';
										}
										?>
										<div class="wp-mastertoolkit__onboarding__module-entry" data-module-entry>
										<?php if ( $wpmtk_is_pro_promotion ) : ?>
											<button type="button" class="<?php echo esc_attr( implode( ' ', $wpmtk_module_classes ) ); ?>" data-pro-module-card data-pro-module-name="<?php echo esc_attr( $wpmtk_module['name'] ); ?>" aria-haspopup="dialog" aria-controls="wpmtk-onboarding-pro-dialog">
												<span class="wp-mastertoolkit__onboarding__module__control" aria-hidden="true"></span>
										<?php else : ?>
											<label class="<?php echo esc_attr( implode( ' ', $wpmtk_module_classes ) ); ?>">
												<input type="checkbox" name="wpmastertoolkit_onboarding_modules[]" value="<?php echo esc_attr( $wpmtk_module['id'] ); ?>" data-module-input data-preset="<?php echo esc_attr( $wpmtk_preset_id ); ?>" <?php checked( $wpmtk_is_checked ); ?> <?php disabled( $wpmtk_is_disabled ); ?>>
												<span class="wp-mastertoolkit__onboarding__module__control" aria-hidden="true"></span>
										<?php endif; ?>
											<span class="wp-mastertoolkit__onboarding__module__content">
												<span class="wp-mastertoolkit__onboarding__module__title">
													<strong><?php echo esc_html( $wpmtk_module['name'] ); ?></strong>
													<?php if ( $wpmtk_module['pro'] ) : ?><span class="wp-mastertoolkit__onboarding__badge"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span><?php endif; ?>
													<?php if ( $wpmtk_is_active ) : ?><span class="wp-mastertoolkit__onboarding__status"><?php esc_html_e( 'Already active', 'wpmastertoolkit' ); ?></span><?php endif; ?>
													<?php if ( $wpmtk_is_pro_promotion ) : ?>
														<span class="wp-mastertoolkit__onboarding__status"><?php esc_html_e( 'Upgrade required', 'wpmastertoolkit' ); ?></span>
													<?php elseif ( ! $wpmtk_module['available'] ) : ?>
														<span class="wp-mastertoolkit__onboarding__status"><?php esc_html_e( 'Unavailable', 'wpmastertoolkit' ); ?></span>
													<?php endif; ?>
												</span>
												<small><?php echo esc_html( $wpmtk_module['description'] ); ?></small>
												<?php if ( $wpmtk_module['warning'] ) : ?><span class="wp-mastertoolkit__onboarding__warning"><?php echo esc_html( $wpmtk_module['warning'] ); ?></span><?php endif; ?>
											</span>
										<?php if ( $wpmtk_is_pro_promotion ) : ?>
											</button>
											<noscript>
												<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to PRO', 'wpmastertoolkit' ); ?></a>
											</noscript>
										<?php else : ?>
											</label>
										<?php endif; ?>
										<?php if ( ! $wpmtk_is_active && $wpmtk_module['available'] && ! empty( $wpmtk_module['fields'] ) ) : ?>
											<div class="wp-mastertoolkit__onboarding__module-fields" data-module-fields data-module-id="<?php echo esc_attr( $wpmtk_module['id'] ); ?>" data-preset="<?php echo esc_attr( $wpmtk_preset_id ); ?>" <?php if ( 'WPMastertoolkit_Move_Login_URL' === $wpmtk_module['id'] ) : ?>data-login-url-prefix="<?php echo esc_url( apply_filters( 'wpmastertoolkit/move_login_url/home_url', home_url( '/' ) ) ); ?>" data-login-url-permalinks="<?php echo esc_attr( get_option( 'permalink_structure' ) ? '1' : '0' ); ?>" data-login-url-trailing="<?php echo esc_attr( '/' === substr( get_option( 'permalink_structure' ), -1 ) ? '1' : '0' ); ?>"<?php endif; ?>>
												<?php foreach ( $wpmtk_module['fields'] as $wpmtk_field_key => $wpmtk_field ) : ?>
													<?php
													$wpmtk_field_id    = 'wpmtk-field-' . sanitize_html_class( $wpmtk_preset_id . '-' . $wpmtk_module['id'] . '-' . $wpmtk_field_key );
													$wpmtk_field_name  = 'wpmastertoolkit_onboarding_fields[' . $wpmtk_preset_id . '][' . $wpmtk_module['id'] . '][' . $wpmtk_field_key . ']';
													$wpmtk_field_value = $form_state['values'][ $wpmtk_module['id'] ][ $wpmtk_field_key ] ?? $saved_fields[ $wpmtk_module['id'] ][ $wpmtk_field_key ] ?? $wpmtk_field['default'];
													$wpmtk_field_error = $form_state['errors'][ $wpmtk_module['id'] ][ $wpmtk_field_key ] ?? '';
													$wpmtk_field_value = is_scalar( $wpmtk_field_value ) ? $wpmtk_field_value : $wpmtk_field['default'];
													$wpmtk_describedby = array();
													if ( ! empty( $wpmtk_field['description'] ) ) {
														$wpmtk_describedby[] = $wpmtk_field_id . '-help';
													}
													if ( $wpmtk_field_error ) {
														$wpmtk_describedby[] = $wpmtk_field_id . '-error';
													}
													?>
													<div class="wp-mastertoolkit__onboarding__field<?php echo 'checkbox' === $wpmtk_field['type'] ? ' wp-mastertoolkit__onboarding__field--checkbox' : ''; ?>">
														<?php if ( 'checkbox' !== $wpmtk_field['type'] ) : ?><label for="<?php echo esc_attr( $wpmtk_field_id ); ?>"><?php echo esc_html( $wpmtk_field['label'] ); ?></label><?php endif; ?>
														<?php if ( 'textarea' === $wpmtk_field['type'] ) : ?>
															<textarea id="<?php echo esc_attr( $wpmtk_field_id ); ?>" name="<?php echo esc_attr( $wpmtk_field_name ); ?>" data-module-field data-field-key="<?php echo esc_attr( $wpmtk_field_key ); ?>" data-required="<?php echo esc_attr( $wpmtk_field['required'] ? '1' : '0' ); ?>" <?php if ( $wpmtk_field_error ) : ?>aria-invalid="true"<?php endif; ?> <?php if ( $wpmtk_describedby ) : ?>aria-describedby="<?php echo esc_attr( implode( ' ', $wpmtk_describedby ) ); ?>"<?php endif; ?>><?php echo esc_textarea( $wpmtk_field_value ); ?></textarea>
														<?php elseif ( 'select' === $wpmtk_field['type'] ) : ?>
															<select id="<?php echo esc_attr( $wpmtk_field_id ); ?>" name="<?php echo esc_attr( $wpmtk_field_name ); ?>" data-module-field data-field-key="<?php echo esc_attr( $wpmtk_field_key ); ?>" data-required="<?php echo esc_attr( $wpmtk_field['required'] ? '1' : '0' ); ?>" <?php if ( $wpmtk_field_error ) : ?>aria-invalid="true"<?php endif; ?> <?php if ( $wpmtk_describedby ) : ?>aria-describedby="<?php echo esc_attr( implode( ' ', $wpmtk_describedby ) ); ?>"<?php endif; ?>>
															<?php foreach ( $wpmtk_field['choices'] as $wpmtk_choice => $wpmtk_choice_label ) : ?>
																<option value="<?php echo esc_attr( $wpmtk_choice ); ?>" <?php selected( (string) $wpmtk_field_value, (string) $wpmtk_choice ); ?>><?php echo esc_html( $wpmtk_choice_label ); ?></option>
															<?php endforeach; ?>
														</select>
														<?php else : ?>
															<input id="<?php echo esc_attr( $wpmtk_field_id ); ?>" type="<?php echo esc_attr( $wpmtk_field['type'] ); ?>" name="<?php echo esc_attr( $wpmtk_field_name ); ?>" value="<?php echo esc_attr( 'checkbox' === $wpmtk_field['type'] ? '1' : $wpmtk_field_value ); ?>" data-module-field data-field-key="<?php echo esc_attr( $wpmtk_field_key ); ?>" data-required="<?php echo esc_attr( $wpmtk_field['required'] ? '1' : '0' ); ?>" <?php if ( 'checkbox' === $wpmtk_field['type'] ) : ?><?php checked( (string) $wpmtk_field_value, '1' ); ?><?php endif; ?> <?php if ( 'number' === $wpmtk_field['type'] && isset( $wpmtk_field['min'] ) ) : ?>min="<?php echo esc_attr( $wpmtk_field['min'] ); ?>"<?php endif; ?> <?php if ( 'number' === $wpmtk_field['type'] && isset( $wpmtk_field['max'] ) ) : ?>max="<?php echo esc_attr( $wpmtk_field['max'] ); ?>"<?php endif; ?> <?php if ( $wpmtk_field_error ) : ?>aria-invalid="true"<?php endif; ?> <?php if ( $wpmtk_describedby ) : ?>aria-describedby="<?php echo esc_attr( implode( ' ', $wpmtk_describedby ) ); ?>"<?php endif; ?>>
															<?php if ( 'checkbox' === $wpmtk_field['type'] ) : ?><label for="<?php echo esc_attr( $wpmtk_field_id ); ?>"><?php echo esc_html( $wpmtk_field['label'] ); ?></label><?php endif; ?>
														<?php endif; ?>
														<?php if ( ! empty( $wpmtk_field['description'] ) ) : ?><small id="<?php echo esc_attr( $wpmtk_field_id . '-help' ); ?>"><?php echo esc_html( $wpmtk_field['description'] ); ?></small><?php endif; ?>
														<?php if ( $wpmtk_field_error ) : ?><small class="wp-mastertoolkit__onboarding__field-error" id="<?php echo esc_attr( $wpmtk_field_id . '-error' ); ?>"><?php echo esc_html( $wpmtk_field_error ); ?></small><?php endif; ?>
													</div>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</section>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="wp-mastertoolkit__onboarding__panel" data-step="3" aria-labelledby="wpmtk-onboarding-review-title">
					<p class="wp-mastertoolkit__onboarding__eyebrow"><?php esc_html_e( 'Confirm changes', 'wpmastertoolkit' ); ?></p>
					<h2 id="wpmtk-onboarding-review-title" tabindex="-1"><?php esc_html_e( 'Review your configuration', 'wpmastertoolkit' ); ?></h2>
					<p><?php esc_html_e( 'Only the modules listed here will be enabled. Existing modules and their settings will not be changed.', 'wpmastertoolkit' ); ?></p>
					<div class="wp-mastertoolkit__onboarding__summary" id="JS-wpmastertoolkit-onboarding-summary" data-empty-label="<?php esc_attr_e( 'Select at least one available module before applying the configuration.', 'wpmastertoolkit' ); ?>" data-yes-label="<?php esc_attr_e( 'Yes', 'wpmastertoolkit' ); ?>" data-no-label="<?php esc_attr_e( 'No', 'wpmastertoolkit' ); ?>" aria-live="polite">
						<p><?php esc_html_e( 'Select your goals and modules to build the configuration summary.', 'wpmastertoolkit' ); ?></p>
					</div>
				</section>
			</form>

			<div class="wp-mastertoolkit__onboarding__footer">
				<div class="wp-mastertoolkit__onboarding__footer__secondary wp-mastertoolkit__button">
					<button type="submit" form="JS-wpmastertoolkit-onboarding-skip-form" class="wp-mastertoolkit__onboarding__skip-button"><?php esc_html_e( 'Skip for now', 'wpmastertoolkit' ); ?></button>
					<button type="button" class="secondary" data-onboarding-back data-label-back-to-goals="<?php esc_attr_e( 'Back to goals', 'wpmastertoolkit' ); ?>" data-label-previous-goal="<?php esc_attr_e( 'Previous goal', 'wpmastertoolkit' ); ?>" data-label-back-to-modules="<?php esc_attr_e( 'Back to modules', 'wpmastertoolkit' ); ?>"><?php esc_html_e( 'Back', 'wpmastertoolkit' ); ?></button>
				</div>
				<div class="wp-mastertoolkit__onboarding__footer__primary wp-mastertoolkit__button">
					<button type="button" data-onboarding-next data-label-continue="<?php esc_attr_e( 'Continue', 'wpmastertoolkit' ); ?>" data-label-next-goal="<?php esc_attr_e( 'Next goal', 'wpmastertoolkit' ); ?>" data-label-review="<?php esc_attr_e( 'Review configuration', 'wpmastertoolkit' ); ?>"><?php esc_html_e( 'Continue', 'wpmastertoolkit' ); ?></button>
					<button type="submit" form="JS-wpmastertoolkit-onboarding-form" data-onboarding-apply><?php esc_html_e( 'Apply configuration', 'wpmastertoolkit' ); ?></button>
				</div>
			</div>
		</div>

		<?php if ( ! $wpmtk_is_pro_version ) : ?>
			<div class="wp-mastertoolkit__onboarding__pro-modal" data-pro-upgrade-modal hidden>
				<div class="wp-mastertoolkit__onboarding__pro-modal__backdrop" data-pro-modal-close aria-hidden="true"></div>
				<div id="wpmtk-onboarding-pro-dialog" class="wp-mastertoolkit__onboarding__pro-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="wpmtk-onboarding-pro-title" aria-describedby="wpmtk-onboarding-pro-description" tabindex="-1">
					<button type="button" class="wp-mastertoolkit__onboarding__pro-modal__close" data-pro-modal-close aria-label="<?php esc_attr_e( 'Close', 'wpmastertoolkit' ); ?>">
						<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
					</button>
					<div class="wp-mastertoolkit__onboarding__pro-modal__copy">
						<span class="wp-mastertoolkit__onboarding__eyebrow"><?php esc_html_e( 'WPMasterToolKit PRO', 'wpmastertoolkit' ); ?></span>
						<h2 id="wpmtk-onboarding-pro-title"><?php esc_html_e( 'Unlock', 'wpmastertoolkit' ); ?> <span data-pro-modal-module-name><?php esc_html_e( 'this PRO module', 'wpmastertoolkit' ); ?></span></h2>
						<p id="wpmtk-onboarding-pro-description"><?php esc_html_e( 'Get this module and the complete professional toolkit with WPMasterToolKit PRO.', 'wpmastertoolkit' ); ?></p>
					</div>
					<div class="wp-mastertoolkit__onboarding__pro-modal__action">
						<a class="wp-mastertoolkit__onboarding__pro-modal__upgrade" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Upgrade to PRO', 'wpmastertoolkit' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wpmastertoolkit' ); ?></span>
						</a>
					</div>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>