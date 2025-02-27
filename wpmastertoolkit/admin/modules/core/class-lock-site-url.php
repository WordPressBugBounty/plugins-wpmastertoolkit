<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Lock Site URL
 * Description: Lock the site url to prevent the site url from being changed
 * @since 1.0.0
 */
class WPMastertoolkit_Lock_Site_URL {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_action( 'admin_head-options-general.php', array( $this, 'disable_users_can_register' ) );
        remove_all_filters( 'pre_option_home' );
        remove_all_filters( 'option_home' );
        remove_all_filters( 'home_url' );
        remove_all_filters( 'pre_option_siteurl' );
        remove_all_filters( 'option_siteurl' );
        remove_all_filters( 'site_url' );
    }

    /**
     * Run on option activation.
     * 
     * @since   1.0.0
     */
    public static function activate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        if( !defined('WP_HOME') )    WPMastertoolkit_WP_Config::replace_or_add_constant('WP_HOME', get_home_url(), 'string' );
        if( !defined('WP_SITEURL') ) WPMastertoolkit_WP_Config::replace_or_add_constant('WP_SITEURL', get_site_url(), 'string' );
        if( !defined('RELOCATE') )   WPMastertoolkit_WP_Config::replace_or_add_constant('RELOCATE', false );
    }

    /**
     * Run on option deactivation.
     * 
     * @since   1.0.0
     */
    public static function deactivate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        WPMastertoolkit_WP_Config::remove_constant('WP_HOME');
        WPMastertoolkit_WP_Config::remove_constant('WP_SITEURL');
        WPMastertoolkit_WP_Config::remove_constant('RELOCATE');
    }
    
    /**
     * Disable the users can register.
     *
     * @return void
     */
    public function disable_users_can_register() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/wp-options-general.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_wp-options-general', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wp-options-general.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_wp-options-general', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/wp-options-general.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        wp_localize_script( 'WPMastertoolkit_wp-options-general', 'wpmastertoolkit_lock_site_url', array(
            'i18n' => array(
                'disable_site_url' => esc_js( esc_html__( '🔒 Locked for better security', 'wpmastertoolkit' ) ),
            ),
        ) );
    }
}