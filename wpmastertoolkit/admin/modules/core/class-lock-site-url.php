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
		add_filter( 'pre_option_home', array( $this, 'force_home_from_wp_config' ) );
        add_filter( 'pre_option_siteurl', array( $this, 'force_siteurl_from_wp_config' ) );
		add_filter( 'pre_update_option_home', array( $this, 'block_update' ), 10, 2 );
        add_filter( 'pre_update_option_siteurl', array( $this, 'block_update' ), 10, 2 );
    }

    /**
     * Run on option activation.
     * 
     * @since   1.0.0
     */
    public static function activate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        if( !defined('WP_HOME') )    WPMastertoolkit_WP_Config::replace_or_add_constant('WP_HOME', get_option('home'), 'string' );
        if( !defined('WP_SITEURL') ) WPMastertoolkit_WP_Config::replace_or_add_constant('WP_SITEURL', get_option('siteurl'), 'string' );
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

	/**
	 * Force home url from wp-config
	 * 
	 * @since   2.13.0
	 */
	public function force_home_from_wp_config( $value ) {
		if ( defined( 'WP_HOME' ) ) {
			$value = untrailingslashit( WP_HOME );
		}

		return $value;
	}

	/**
	 * Force site url from wp-config
	 * 
	 * @since   2.13.0
	 */
	public function force_siteurl_from_wp_config( $value ) {
		if ( defined( 'WP_SITEURL' ) ) {
			$value = untrailingslashit( WP_SITEURL );
		}

		return $value;
	}

	/**
	 * Prevent updating the option from general settings
	 * 
	 * @since   2.13.0
	 */
	public function block_update( $new_value, $old_value ) {
		return $old_value;
	}
}
