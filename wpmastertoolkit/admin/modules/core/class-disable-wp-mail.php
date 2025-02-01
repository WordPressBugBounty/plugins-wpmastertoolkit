<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable WP Mail
 * Description: Disable the default WordPress mail function
 * @since 1.0.0
 */
class WPMastertoolkit_Disable_WP_Mail {

    public function __construct() {
        add_filter('pre_wp_mail', '__return_false');
    }
}
