<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Xmlrpc
 * Description: Disable xmlrpc for better security
 * @since 1.0.0
 */
class WPMastertoolkit_Disable_Xmlrpc {

    /**
     * Invoke the hooks.
     * 
     * @since   1.0.0
     */
    public function __construct() {
        
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_xmlrpc_server_class', array( $this, 'redirect_to_403' ) );
    }

    /**
     * Redirect to 403.
     * 
     * @since   1.0.0
     */
    public function redirect_to_403( $class ) {

        http_response_code( 403 );
        exit;
    }

}