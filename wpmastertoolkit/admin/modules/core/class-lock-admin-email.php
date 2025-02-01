<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Lock Admin Email
 * Description: Lock the admin email to prevent the admin email from being changed
 * @since 1.0.0
 */
class WPMastertoolkit_Lock_Admin_Email {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        add_action( 'admin_head-options-general.php', array( $this, 'lock_admin_email' ) );
        add_filter( 'pre_option_admin_email', array( $this,'lock_admin_email_value' ) ) ;
        add_filter( 'pre_option_new_admin_email', array( $this,'lock_admin_email_value' ) ) ;
        add_filter( 'pre_update_option', array( $this, 'prevent_admin_email_change' ), 10, 3 );
    }

    /**
     * Run on option activation.
     * 
     * @since   1.0.0
     */
    public static function activate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        if( !defined('WPMASTERTOOLKIT_ADMIN_EMAIL') )    WPMastertoolkit_WP_Config::replace_or_add_constant( 'WPMASTERTOOLKIT_ADMIN_EMAIL', get_option('admin_email'), 'string' );
    }

    /**
     * Run on option deactivation.
     * 
     * @since   1.0.0
     */
    public static function deactivate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        WPMastertoolkit_WP_Config::remove_constant('WPMASTERTOOLKIT_ADMIN_EMAIL');
    }
    
    /**
     * Disable the users can register.
     *
     * @return void
     */
    public function lock_admin_email() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/wp-options-general.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_wp-options-general', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/wp-options-general.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_wp-options-general', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/wp-options-general.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        wp_localize_script( 'WPMastertoolkit_wp-options-general', 'wpmastertoolkit_lock_admin_email', array(
            'i18n' => array(
                'disable_email_edit' => esc_js( esc_html__( '🔒 Locked for better security', 'wpmastertoolkit' ) ),
            ),
        ) );
    }
    
    /**
     * lock_admin_email_value
     *
     * @param  mixed $email
     * @return void
     */
    public function lock_admin_email_value( $email ) {
        return $this->get_locked_admin_email();
    }
    
    /**
     * prevent_admin_email_change
     *
     * @param  mixed $value
     * @param  mixed $option
     * @param  mixed $old_value
     * @return void
     */
    public function prevent_admin_email_change( $value, $option, $old_value ) {
        if ( 'admin_email' == $option || 'new_admin_email' == $option ) {
            return $this->get_locked_admin_email();
        }
        return $value;
    }
    
    /**
     * get_locked_admin_email
     *
     * @return void
     */
    private function get_locked_admin_email(){
        global $wpdb;
        if(defined( 'WPMASTERTOOLKIT_ADMIN_EMAIL' )){
            return WPMASTERTOOLKIT_ADMIN_EMAIL;
        }
        return $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'admin_email'" );
    }

}