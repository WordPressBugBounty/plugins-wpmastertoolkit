<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable WP Sitemap
 * Description: Disable the default WordPress sitemap
 * @since 1.0.0
 */
class WPMastertoolkit_Disable_WP_Sitemap {
    /**
     * Invoke the hooks
     * 
     * @since    1.0.0
     */
    public function __construct() {
        add_filter( 'wp_sitemaps_enabled', '__return_false' );
    }
}