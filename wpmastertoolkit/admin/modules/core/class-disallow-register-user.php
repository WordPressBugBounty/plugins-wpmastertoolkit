<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disallow Register User
 * Description: Disallow register user option in the admin area for better security
 * @since 1.0.0
 */
class WPMastertoolkit_Disallow_Register_User {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_filter( 'pre_option_users_can_register', '__return_zero' );
        add_action( 'admin_head-options-general.php', array( $this, 'disable_users_can_register' ) );
    }
    
    /**
     * Disable the users can register.
     *
     * @return void
     */
    public function disable_users_can_register() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/wp-options-general.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_wp-options-general', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/wp-options-general.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_wp-options-general', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/wp-options-general.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        wp_localize_script( 'WPMastertoolkit_wp-options-general', 'wpmastertoolkit_disallow_register_user', array(
            'i18n' => array(
                'disable_users_can_register' => esc_js( esc_html__( '🔒 Disabled for better security', 'wpmastertoolkit' ) ),
            ),
        ) );
    }
}