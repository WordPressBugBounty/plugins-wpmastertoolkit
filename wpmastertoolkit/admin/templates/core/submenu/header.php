<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The submenu header
 * 
 * @since      1.0.0
 */
?>

<div class="wrap wp-mastertoolkit">

	<?php if ( ! isset($this->disable_form ) ) : ?>
    <form action="" method="post" enctype="multipart/form-data">
	<?php endif; ?>
        
        <header class="wp-mastertoolkit__header">
            
            <div class="wp-mastertoolkit__header__left">
                <div class="wp-mastertoolkit__header__left__logo">
					<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                    <img src="<?php echo esc_url( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/logo.png' ); ?>" alt="<?php esc_html_e('WPMasterToolkit', 'wpmastertoolkit'); ?>" />
                </div>
            </div>
            
            <div class="wp-mastertoolkit__header__center">
                <div class="wp-mastertoolkit__header__left__title"><?php echo esc_html($this->header_title) ?></div>
            </div>

            <div class="wp-mastertoolkit__header__right">

            <?php if ( isset($this->nonce_action) ) : ?>
                <div class="wp-mastertoolkit__header__right__save">
                    <?php
                        wp_nonce_field( $this->nonce_action );
                        submit_button( esc_html__('Save', 'wpmastertoolkit') );
                    ?>
                </div>
            <?php endif; ?>

            </div>
            
        </header>