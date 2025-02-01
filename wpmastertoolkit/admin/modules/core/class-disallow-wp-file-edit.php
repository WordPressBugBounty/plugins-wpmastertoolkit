<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disallow WP File Edit
 * Description: Disallow access to the wp file editor in the admin area
 * @since 1.0.0
 */
class WPMastertoolkit_Disallow_WP_File_Edit {

    /**
     * Invoke the hooks
     * 
     * @since    1.0.0
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'disable_wp_file_edit' ) );
    }
    
    /**
     * activate
     *
     * @return void
     */
    public static function activate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        if( !defined('DISALLOW_FILE_EDIT') )    WPMastertoolkit_WP_Config::replace_or_add_constant('DISALLOW_FILE_EDIT', true );
    }
    
    /**
     * deactivate
     *
     * @return void
     */
    public static function deactivate(){
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';
        WPMastertoolkit_WP_Config::remove_constant('DISALLOW_FILE_EDIT');
    }

    /**
     * Disable the wp file edit
     *
     * @return void
     */
    public function disable_wp_file_edit() {
        if( !defined('DISALLOW_FILE_EDIT') ) define( 'DISALLOW_FILE_EDIT', true );
    }

}