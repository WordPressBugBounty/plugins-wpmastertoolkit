<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable WordPress shortlink <link> tag
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Shortlink_Tag {

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

        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
        remove_action( 'template_redirect', 'wp_shortlink_header', 100, 0 );
    }
}
