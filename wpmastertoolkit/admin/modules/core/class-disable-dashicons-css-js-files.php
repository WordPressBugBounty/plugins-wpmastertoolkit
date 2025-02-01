<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable dashicons CSS and JS files
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Dashicons_CSS_JS_files {

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Init the hooks
     */
    public function init() {
        global $pagenow;

        if ( ! is_user_logged_in() ) {

            $current_request_uri = sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' );
            $is_login_page       = false !== strpos( $current_request_uri, 'wp-login.php' ) || 'wp-login.php' === $pagenow;
            $is_protected_page   = false !== strpos( $current_request_uri, 'protected-page=view' );

            if ( ! $is_login_page && ! $is_protected_page ) {

                wp_dequeue_style( 'dashicons' );
                wp_deregister_style( 'dashicons' );
            }
        }
    }
}
