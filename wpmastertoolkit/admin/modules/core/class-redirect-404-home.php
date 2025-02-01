<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Redirect 404 to Homepage
 * Description:
 * @since 1.3.0
 */
class WPMastertoolkit_Redirect_404_Home {

    /**
     * Invoke the hooks
     */
    public function __construct() {

        add_filter( 'wp', array( $this, 'redirect_to_homepage' ) );
    }

    /**
     * Redirect 404 page to homepage
     * 
     * @return void
     */
    public function redirect_to_homepage() {
        
        if ( is_admin() || ( defined( 'DOING_CRON' ) && true === DOING_CRON ) || ( defined( 'XMLRPC_REQUEST' ) && true === XMLRPC_REQUEST ) || ! is_404() ) {
            return;
        } else {
            header( 'HTTP/1.1 301 Moved Permanently');
            header( 'Location: ' . home_url() );
            exit();
        }
    }
}
