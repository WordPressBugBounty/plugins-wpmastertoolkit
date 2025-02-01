<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Block-Based Widgets Settings Screen
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Disable_Block_Based_Widgets_Settings_Screen {

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

        add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
        add_filter( 'use_widgets_block_editor', '__return_false' );
    }
}
