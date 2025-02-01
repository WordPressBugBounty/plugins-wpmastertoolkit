<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Windows Live Writer (WLW) manifest <link> tag
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Windows_Live_Writer_Tag {

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

        /**
         * WordPress 6.3.0 already removed the wlwmanifest_link
         * https://developer.wordpress.org/reference/functions/wlwmanifest_link/
         */
        remove_action( 'wp_head', 'wlwmanifest_link' );
    }
}
