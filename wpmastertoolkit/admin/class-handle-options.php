<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the options of the plugin.
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Handle_options {

    /**
	 * Include all active options classes
	 *
	 * @since    1.0.0
	 */
    public function __construct() {
        $this->instantiate_active_options();
        $this->instantiate_custom_options();
    }
    
    /**
     * instantiate_active_options
     *
     * @return void
     */
    private function instantiate_active_options(){

        $db_options     = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
        $options_data   = wpmastertoolkit_options( 'normal' );

        foreach ( $db_options as $option_key => $option_status ) {
            
            if ( $option_status == '1' ) {

                $option_data = $options_data[$option_key] ?? array();
                $option_path = $option_data['path'] ?? '';
                
                /**
                 * Check if is relative path in plugin folder.
                 */
                if ( strpos( $option_path, 'pro/' ) === 0 || strpos( $option_path, 'core/' ) === 0 ) {
                    $option_path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/' . $option_path;
                }
                
                if ( is_file( $option_path ) ) {
                    require_once $option_path;

                    // check if the class exists
                    if ( class_exists( $option_key ) ) {
                        new $option_key;
                    }
                }
            }
        }
    }

    /**
     * Instantiate Custom Classes
     */
    private function instantiate_custom_options() {

        $options_path   = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/';
        $custom_options = array(
            'WPMastertoolkit_Nginx_Code_Snippets' => 'core/class-nginx-code-snippets.php'
        );

        foreach ( $custom_options as $class_name => $class_path ) {

            $class_path = $options_path . $class_path;

            if ( is_file( $class_path ) ) {
                require_once $class_path;

                // check if the class exists
                if ( class_exists( $class_name ) ) {
                    new $class_name;
                }
            }
        }

    }
    
    /**
     * require_once_all_options
     *
     * @return void
     */
    public static function require_once_all_options(){

        $options_data = wpmastertoolkit_options( 'normal' );

        foreach ( $options_data as $option_key => $option_data ) {
            
            $option_path = $option_data['path'] ?? '';
            
            /**
             * Check if is relative path in plugin folder.
             */
            if ( strpos( $option_path, 'pro/' ) === 0 || strpos( $option_path, 'core/' ) === 0 ) {
                $option_path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/' . $option_path;
            }

            if ( is_file( $option_path ) ) {
                require_once $option_path;
            }
        }

    }

}
new WPMastertoolkit_Handle_options;