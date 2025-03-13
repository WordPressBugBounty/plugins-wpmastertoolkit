<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin settings of the plugin.
 * @since      1.0.0
 */
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
                    <?php esc_html_e( 'WPMasterToolKit', 'wpmastertoolkit' ); ?>
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

                <?php foreach ( wpmastertoolkit_settings_groups() as $group_key => $group_data ) : ?>

                    <?php

                        $has_items         = false;
                        $is_exception      = $group_data['exception'] ?? false;
						$show_counter      = ! $is_exception;
						$counter           = 0;
						$counter_activated = 0;

                        if ( $is_exception ) {
                            $has_items = true;

							if ( $group_key === 'all' ) {
								$show_counter = true;
								$counter      = count( wpmastertoolkit_options() );
							}

                        } else {
                            foreach ( wpmastertoolkit_options() as $option_key => $option_data ) {
                                if ( $option_data['group'] === $group_key ) {
									$counter++;
                                    $has_items = true;
                                }

								$checked = isset( $db_options[$option_key] ) && $db_options[$option_key] === '1';
								if ( $checked ) {
									$counter_activated++;
								}
                            }

							if ( $group_key === 'activated' && $counter_activated > 0 ) {
								$has_items = true;
								$counter   = $counter_activated;
							}
                        }

                        if ( ! $has_items ) {
                            continue;
                        }
                    ?>

                    <div class="wp-mastertoolkit__body__groups__item" data-key="<?php echo esc_attr($group_key); ?>" >
                        <span class="logo">
                            <?php
                                if ( isset($group_data['logo']) && file_exists(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/' . $group_data['logo'] ) ) {
									echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/' . $group_data['logo'] ), wpmastertoolkit_allowed_tags_for_svg_files() );
                                }
                            ?>
                        </span>
                        <?php echo esc_html($group_data['name'] ?? ''); ?>
						<?php if ( $show_counter ): ?>
							<span class="counter"><?php echo esc_html($counter); ?></span>
						<?php endif; ?>
                    </div>

                <?php endforeach; ?>

            </div>

            <div class="wp-mastertoolkit__body__sections">

                <?php foreach ( wpmastertoolkit_options() as $option_key => $option_data ) :
                        $option_path     = $option_data['path'] ?? '';
                        $coming_soon     = $option_data['coming_soon'] ?? false;
                        $is_addon_module = false;

                        /**
                         * Check if is relative path in plugin folder.
                         */
                        if ( strpos( $option_path, 'pro/' ) === 0 || strpos( $option_path, 'core/' ) === 0 ) {
                            $option_path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/' . $option_path;
                        } else {
                            $is_addon_module = true;
                        }
                        $file_exist = is_file( $option_path );
                        $disabled   = $file_exist ? false : true;
                        $checked    = isset($db_options[$option_key]) && $db_options[$option_key] === '1' && !$disabled;
                ?>
                    <div class="wp-mastertoolkit__body__sections__item <?php echo esc_attr( $disabled ? 'disabled' : '' ); ?>" data-key="<?php echo esc_attr($option_data['group'] ?? ''); ?><?php echo $checked ? ' activated' : ''; ?>" data-title="<?php echo esc_attr($option_data['name'] ?? ''); ?>" data-originaltitle="<?php echo esc_attr($option_data['original_name'] ?? ''); ?>">
                        <div class="wp-mastertoolkit__body__sections__item__top">
                            <div class="wp-mastertoolkit__body__sections__item__title">
                                <span class="wp-mastertoolkit__body__sections__item__title__text">
                                    <?php echo esc_html($option_data['name'] ?? ''); ?>
                                </span>
                                <span class="wp-mastertoolkit__body__sections__item__title__tags">
                                    <?php if($option_data['pro'] ?? false): ?>
                                        <span class="pro"><?php esc_html_e('PRO', 'wpmastertoolkit'); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $coming_soon ): ?>
                                        <span class="comming-soon"><?php esc_html_e('coming soon', 'wpmastertoolkit'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="wp-mastertoolkit__body__sections__item__toggle">
                                <label class="wp-mastertoolkit__toggle">
                                    <input type="hidden" name="<?php echo esc_attr( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '[' .  $option_key . ']'); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr(WPMASTERTOOLKIT_PLUGIN_SETTINGS . '[' . $option_key . ']'); ?>" value="1" <?php echo esc_attr( checked($checked, true, false) ); ?> <?php echo esc_attr( $disabled ? 'disabled' : '' ); ?>>
                                    <span class="wp-mastertoolkit__toggle__slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="wp-mastertoolkit__body__sections__item__bottom">
                            <div class="wp-mastertoolkit__body__sections__item__description"><?php echo esc_html($option_data['desc'] ?? ''); ?></div>
                            <?php if ( !$is_addon_module ): ?>
                                <div class="wp-mastertoolkit__body__sections__item__documentation"><a href="https://wpmastertoolkit.com/?module_documentation=<?php echo esc_attr( $option_key ); ?>" target="_blank"><?php esc_html_e( 'Learn more', 'wpmastertoolkit' ); ?></a></div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; ?>

                <?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/settings.php'; ?>

                <?php include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings-tabs/credits.php'; ?>

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
		<?php endif; ?>

    </form>

</div>

<div class="wp-mastertoolkit__changelog-modal">
    <div class="wp-mastertoolkit__changelog-modal__content">
        <div class="wp-mastertoolkit__changelog-modal__content__close">
            ✕
        </div>
        <div>
            <h2><?php echo esc_html(
				sprintf(
					/* translators: %s: WPMasterToolKit version */
					__("What's new in v%s", 'wpmastertoolkit'),
					WPMASTERTOOLKIT_VERSION,
				)); ?>
			</h2>
            <hr>
            <?php echo wp_kses_post( WPMastertoolkit_Settings::get_changelog() ); ?>
        </div>
    </div>
</div>
