<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Really Simple Discovery (RSD) <link> tag
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Really_Simple_Discovery_Tag {

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

        remove_action( 'wp_head', 'rsd_link' );
    }
}
