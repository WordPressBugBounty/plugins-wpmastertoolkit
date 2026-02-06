<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin settings of the plugin.
 * @since      1.0.0
 */

global $wpmtk_module_settings_submenu_pages;

$wpmtk_pro_modules_count = 0;
$wpmtk_modules           = wpmastertoolkit_options();
$wpmtk_order             = array_column( $wpmtk_modules, 'name' );
array_multisort( $wpmtk_order, SORT_ASC, $wpmtk_modules );
?>

<div class="wrap wp-mastertoolkit">

    <form action="" method="post" enctype="multipart/form-data" >

        <header class="wp-mastertoolkit__header">
    
            <div class="wp-mastertoolkit__header__left">

                <div class="wp-mastertoolkit__header__left__logo">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                    <img height="51" src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/icon-128x128.gif' ); ?>" alt="<?php esc_html_e('WPMasterToolkit', 'wpmastertoolkit'); ?>" />    
                </div>

                <div class="wp-mastertoolkit__header__left__title">
                    <?php esc_html_e( 'WPMasterToolKit', 'wpmastertoolkit' ); ?> <?php echo esc_html( wpmastertoolkit_is_pro() ? __( 'Pro', 'wpmastertoolkit' ) : '' ); ?>
                    <div class="wp-mastertoolkit__header__left__title__version">
                        <?php esc_html_e( 'Version', 'wpmastertoolkit' ); ?> <?php echo esc_html( WPMASTERTOOLKIT_VERSION ); ?> - <a href="#" class="wp-mastertoolkit__header__left__title__version__open-modal-button"><?php esc_html_e("What's new?", 'wpmastertoolkit'); ?></a>
                    </div>
                </div>

            </div>

            <div class="wp-mastertoolkit__header__right">

                <div class="wp-mastertoolkit__header__right__search">
                    <input type="text" placeholder="<?php esc_html_e('Search', 'wpmastertoolkit'); ?>" >
                    <span class="loop">
						<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/loop.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                    </span>
                </div>

                <div class="wp-mastertoolkit__header__right__save">
                    <?php
                        wp_nonce_field( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_action' );
                        submit_button( esc_html__('Save', 'wpmastertoolkit') );
                    ?>
                </div>

            </div>
            
        </header>

        <div class="wp-mastertoolkit__body">

            <div class="wp-mastertoolkit__body__groups">

                <?php foreach ( wpmastertoolkit_settings_groups() as $wpmtk_group_key => $wpmtk_group_data ) : ?>

                    <?php

                        $wpmtk_has_items           = false;
                        $wpmtk_is_exception        = $wpmtk_group_data['exception'] ?? false;
						$wpmtk_show_counter        = ! $wpmtk_is_exception;
						$wpmtk_counter             = 0;
						$wpmtk_counter_activated   = 0;
						$wpmtk_counter_pro_modules = 0;

                        if ( $wpmtk_is_exception ) {
                            $wpmtk_has_items = true;

							if ( $wpmtk_group_key === 'all' ) {
								$wpmtk_show_counter = true;
								$wpmtk_counter      = count( $wpmtk_modules );
							}

                        } else {
                            foreach ( $wpmtk_modules as $wpmtk_option_key => $wpmtk_option_data ) {
                                if ( $wpmtk_option_data['group'] === $wpmtk_group_key ) {
									$wpmtk_counter++;
                                    $wpmtk_has_items = true;
                                }
								$wpmtk_checked = isset( $db_options[$wpmtk_option_key] ) && $db_options[$wpmtk_option_key] === '1';
								if ( $wpmtk_checked ) {
									$wpmtk_counter_activated++;
								}

								if ( $wpmtk_option_data['pro'] ) {
									$wpmtk_counter_pro_modules++;
								}
                            }

							if ( $wpmtk_group_key === 'activated' && $wpmtk_counter_activated > 0 ) {
								$wpmtk_has_items = true;
								$wpmtk_counter   = $wpmtk_counter_activated;
							}

							if ( $wpmtk_group_key === 'pro-modules' && $wpmtk_counter_pro_modules > 0 ) {
								$wpmtk_has_items = true;
								$wpmtk_counter   = $wpmtk_counter_pro_modules;
							}
                        }

                        if ( ! $wpmtk_has_items ) {
                            continue;
                        }
                    ?>

                    <div class="wp-mastertoolkit__body__groups__item" data-key="<?php echo esc_attr($wpmtk_group_key); ?>" >
                        <span class="logo">
                            <?php
                                if ( isset($wpmtk_group_data['logo']) && file_exists(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/' . $wpmtk_group_data['logo'] ) ) {
									echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/' . $wpmtk_group_data['logo'] ), wpmastertoolkit_allowed_tags_for_svg_files() );
                                }
                            ?>
                        </span>
                        <?php echo esc_html($wpmtk_group_data['name'] ?? ''); ?>
						<?php if ( $wpmtk_show_counter ): ?>
							<span class="counter"><?php echo esc_html($wpmtk_counter); ?></span>
						<?php endif; ?>
                    </div>

                <?php endforeach; ?>

            </div>

            <div class="wp-mastertoolkit__body__sections">

                <?php foreach ( $wpmtk_modules as $wpmtk_option_key => $wpmtk_option_data ) :
                        $wpmtk_option_path     = $wpmtk_option_data['path'] ?? '';
                        $wpmtk_coming_soon     = $wpmtk_option_data['coming_soon'] ?? false;
                        $wpmtk_is_addon_module = false;

						if ( $wpmtk_option_data['pro'] ) {
							$wpmtk_pro_modules_count++;
						}

                        /**
                         * Check if is relative path in plugin folder.
                         */
                        if ( strpos( $wpmtk_option_path, 'pro/' ) === 0 || strpos( $wpmtk_option_path, 'core/' ) === 0 ) {
                            $wpmtk_option_path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/' . $wpmtk_option_path;
                        } else {
                            $wpmtk_is_addon_module = true;
                        }
                        $wpmtk_file_exist = is_file( $wpmtk_option_path );
                        $wpmtk_disabled   = $wpmtk_file_exist ? false : true;
                        $wpmtk_checked    = isset($db_options[$wpmtk_option_key]) && $db_options[$wpmtk_option_key] === '1' && !$wpmtk_disabled;
                ?>
                    <div class="wp-mastertoolkit__body__sections__item module-item <?php echo esc_attr( $wpmtk_checked ? 'activated' : '' ); ?> <?php echo esc_attr( $wpmtk_disabled ? 'disabled' : '' ); ?>" data-key="<?php echo esc_attr($wpmtk_option_data['group'] ?? ''); ?><?php echo $wpmtk_checked ? ' activated' : ''; ?><?php echo $wpmtk_option_data['pro'] ? ' pro-modules' : ''; ?>" data-title="<?php echo esc_attr($wpmtk_option_data['name'] ?? ''); ?>" data-originaltitle="<?php echo esc_attr($wpmtk_option_data['original_name'] ?? ''); ?>">
                        <div class="wp-mastertoolkit__body__sections__item__top module-header">
                            <div class="wp-mastertoolkit__body__sections__item__title">
                                <span class="wp-mastertoolkit__body__sections__item__title__text">
                                    <?php echo esc_html($wpmtk_option_data['name'] ?? ''); ?>
                                </span>
                                <span class="wp-mastertoolkit__body__sections__item__title__tags">
                                    <?php if($wpmtk_option_data['pro'] ?? false): ?>
                                        <span class="pro"><?php esc_html_e('PRO', 'wpmastertoolkit'); ?></span>

										<?php if( ! wpmastertoolkit_is_pro() ): ?>
                                        <span class="try">
											<a href="<?php echo esc_url( $try_url ); ?>" target="_blank"><?php esc_html_e('Try 15 days for free', 'wpmastertoolkit'); ?></a>
										</span>
										<?php endif; ?>

                                    <?php endif; ?>
                                    <?php if ( $wpmtk_coming_soon ): ?>
                                        <span class="comming-soon"><?php esc_html_e('coming soon', 'wpmastertoolkit'); ?></span>
                                    <?php endif; ?>
									<?php if ( !$wpmtk_is_addon_module && !$wpmtk_coming_soon ): ?>
										<a class="documentation" href="https://wpmastertoolkit.com/?module_documentation=<?php echo esc_attr( $wpmtk_option_key ); ?>" target="_blank"><?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/images/documentation-icon.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?><?php esc_html_e('DOC', 'wpmastertoolkit'); ?></a>
									<?php endif; ?>
									<?php if ( is_array($wpmtk_module_settings_submenu_pages) && isset($wpmtk_module_settings_submenu_pages[$wpmtk_option_key]) ): ?>
										<a class="module-settings" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $wpmtk_module_settings_submenu_pages[$wpmtk_option_key] ) ); ?>"><?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/gear.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?><?php esc_html_e('SETTINGS', 'wpmastertoolkit'); ?></a>
									<?php endif; ?>
                                </span>
                            </div>
                            <div class="wp-mastertoolkit__body__sections__item__toggle">
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '[' .  $wpmtk_option_key . ']'); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr(WPMASTERTOOLKIT_PLUGIN_SETTINGS . '[' . $wpmtk_option_key . ']'); ?>" value="1" <?php echo esc_attr( checked($wpmtk_checked, true, false) ); ?> <?php echo esc_attr( $wpmtk_disabled ? 'disabled' : '' ); ?>>
                                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="wp-mastertoolkit__body__sections__item__bottom">
                            <div class="wp-mastertoolkit__body__sections__item__description"><?php echo esc_html($wpmtk_option_data['desc'] ?? ''); ?></div>
                        </div>
                    </div>

                <?php endforeach; ?>

                <?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/settings.php'; ?>

                <?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/credits.php'; ?>

                <?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/credentials.php'; ?>

            </div>

        </div>

        <div class="wp-mastertoolkit__save-button">
            <button type="submit">
				<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/save.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
            </button>
        </div>

		<?php if ( $show_opt_in_modal != '1' ) : ?>
			<div class="wp-mastertoolkit__opt-in-modal">
				<div class="wp-mastertoolkit__opt-in-modal__content">
					<div class="wp-mastertoolkit__opt-in-modal__content__header">
						<div class="wp-mastertoolkit__opt-in-modal__content__header__title"><?php esc_html_e( 'Share my configuration', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__opt-in-modal__content__header__subtitle"><?php esc_html_e( 'In order to improve our modules, we would like to know which modules are most used, with the aim of improving the most used modules as a priority. All data is sent anonymously.', 'wpmastertoolkit' ); ?></div>
					</div>

					<div class="wp-mastertoolkit__opt-in-modal__content__body">
						<div class="wp-mastertoolkit__opt-in-modal__content__body__left">

							<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item">
								<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__img">
									<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
									<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/opt-in-data.svg' ); ?>"/>
								</div>
								<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt">
									<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt__title"><?php esc_html_e( 'Data anonymization', 'wpmastertoolkit' ); ?></div>
									<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt__desc"><?php esc_html_e( 'We only send your WPMasterToolKit configuration.', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>

							<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item">
								<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__img">
									<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
									<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/opt-in-personal.svg' ); ?>"/>
								</div>
								<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt">
									<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt__title"><?php esc_html_e( 'Personal Data', 'wpmastertoolkit' ); ?></div>
									<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt__desc"><?php esc_html_e( 'We never share or get your personal information.', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>

							<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item">
								<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__img">
									<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
									<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/opt-in-plugin.svg' ); ?>"/>
								</div>
								<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt">
									<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt__title"><?php esc_html_e( 'Plugin improvement', 'wpmastertoolkit' ); ?></div>
									<div class="wp-mastertoolkit__opt-in-modal__content__body__left__item__txt__desc"><?php esc_html_e( 'This sharing allows us to make the most relevant improvements to the plugin.', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>

						</div>
						<div class="wp-mastertoolkit__opt-in-modal__content__body__right">
							<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
							<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/opt-in-image.png' ); ?>"/>
						</div>
					</div>

					<div class="wp-mastertoolkit__opt-in-modal__content__footer">
						<div class="wp-mastertoolkit__opt-in-modal__content__footer__left">
							<label>
								<input type="checkbox" id="JS-wpmastertoolkit_modal_optin_checkbox" <?php checked( true ); ?>>
								<span class="mark"></span>
								<span class="text"><?php esc_html_e( 'I agree to share my configuration with wpmastertoolkit.com.', 'wpmastertoolkit' ); ?></span>
							</label>
						</div>

						<div class="wp-mastertoolkit__opt-in-modal__content__footer__right">
							<button type="submit" id="JS-wpmastertoolkit_modal_optin_save"><?php esc_html_e( 'Save & continue', 'wpmastertoolkit' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		<?php elseif( $show_promot_modal ): ?>
			<div id="JS-wpmastertoolkit_modal_promot" class="wp-mastertoolkit__promot-modal">
				<div class="wp-mastertoolkit__promot-modal__content">
					<div class="wp-mastertoolkit__promot-modal__content__header">
						<div class="wp-mastertoolkit__promot-modal__content__header__title"><?php esc_html_e( 'Unlock the full potential of WPMasterToolKit', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__promot-modal__content__header__subtitle">
							<?php
								echo esc_html(
									sprintf(
										/* translators: %s: pro modules count */
										__( 'Like the free version of WPMasterToolKit? The PRO version lets you go further with %s additional modules and additional features in the free modules.', 'wpmastertoolkit' ),
										$wpmtk_pro_modules_count,
									)
								);
							?>
						</div>
					</div>

					<div class="wp-mastertoolkit__promot-modal__content__body">
						<div class="wp-mastertoolkit__promot-modal__content__body__left">
							<div class="wp-mastertoolkit__promot-modal__content__body__left__item">
								<div class="wp-mastertoolkit__promot-modal__content__body__left__item__img">
									<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
									<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/promot-1.svg' ); ?>"/>
								</div>
								<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt">
									<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt__title">
										<?php
											echo esc_html(
												sprintf(
													/* translators: %s: pro modules count */
													__( '+%s modules', 'wpmastertoolkit' ),
													$wpmtk_pro_modules_count,
												)
											);
										?>
									</div>
									<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt__desc"><?php esc_html_e( 'Benefit from modules designed 100% for professionals.', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>
							<div class="wp-mastertoolkit__promot-modal__content__body__left__item">
								<div class="wp-mastertoolkit__promot-modal__content__body__left__item__img">
									<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
									<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/promot-2.svg' ); ?>"/>
								</div>
								<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt">
									<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt__title"><?php esc_html_e( 'Additional Features', 'wpmastertoolkit' ); ?></div>
									<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt__desc"><?php esc_html_e( 'Take advantage of PRO features within the free modules, to go further.', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>
							<div class="wp-mastertoolkit__promot-modal__content__body__left__item">
								<div class="wp-mastertoolkit__promot-modal__content__body__left__item__img">
									<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
									<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/promot-3.svg' ); ?>"/>
								</div>
								<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt">
									<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt__title"><?php esc_html_e( 'Improve your performance', 'wpmastertoolkit' ); ?></div>
									<div class="wp-mastertoolkit__promot-modal__content__body__left__item__txt__desc"><?php esc_html_e( 'Further reduce the number of active plugins and benefit from our resource-conscious approach.', 'wpmastertoolkit' ); ?></div>
								</div>
							</div>
						</div>
						<div class="wp-mastertoolkit__promot-modal__content__body__right">
							<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
							<img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/promot-image.png' ); ?>"/>
						</div>
					</div>

					<div class="wp-mastertoolkit__promot-modal__content__footer">
						<div class="wp-mastertoolkit__promot-modal__content__footer__no-longer">
							<button type="submit" name="wpmastertoolkit_promot_modal" value="no-longer"><?php esc_html_e( 'I no longer wish to see this message', 'wpmastertoolkit' ); ?></button>
						</div>
						<div class="wp-mastertoolkit__promot-modal__content__footer__have-license">
							<button type="submit" name="wpmastertoolkit_promot_modal" value="have-license"><?php esc_html_e( 'I already have a license', 'wpmastertoolkit' ); ?></button>
						</div>
						<div class="wp-mastertoolkit__promot-modal__content__footer__try-now">
							<button id="JS-wpmastertoolkit_modal_promot_try_now_hidden" type="submit" name="wpmastertoolkit_promot_modal" value="try-now"><?php esc_html_e( 'Start 15 free trial', 'wpmastertoolkit' ); ?></button>
							<a id="JS-wpmastertoolkit_modal_promot_try_now" href="<?php echo esc_url( $try_url ); ?>" target="_blank"><?php esc_html_e( 'Start 15 free trial', 'wpmastertoolkit' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

    </form>

</div>

<div class="wp-mastertoolkit__changelog-modal">
    <div class="wp-mastertoolkit__changelog-modal__content">
        <div class="wp-mastertoolkit__changelog-modal__content__close"><?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?></div>
        <div>
            <h2><?php echo esc_html(
				sprintf(
					/* translators: %s: WPMasterToolKit version */
					__("What's new in v%s", 'wpmastertoolkit'),
					WPMASTERTOOLKIT_VERSION,
				)); ?>
			</h2>
			<div class="wp-mastertoolkit__changelog-modal__content__body">
				<?php echo do_shortcode( '[wpmtk_changelog limit="3"]' ); ?>

				<div class="wp-mastertoolkit__changelog-modal__content__body__link">
					<a href="<?php echo esc_url( __( 'https://wpmastertoolkit.com/en/changelog/', 'wpmastertoolkit' ) ); ?>" target="_blank"><?php esc_html_e( 'View all changelogs', 'wpmastertoolkit' ); ?></a>
				</div>
			</div>
        </div>
    </div>
</div>
