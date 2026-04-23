<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Custom COOKIEHASH
 * Description: Generate and inject a random COOKIEHASH constant in wp-config.php
 * @since 2.20.0
 */
class WPMastertoolkit_Custom_COOKIEHASH {

    /**
     * Run on option activation.
     *
     * @return void
     */
    public static function activate() {
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

        WPMastertoolkit_WP_Config::replace_or_add_constant( 'COOKIEHASH', self::generate_cookiehash(), 'string' );
    }

    /**
     * Run on option deactivation.
     *
     * @return void
     */
    public static function deactivate() {
        require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-wp-config.php';

        WPMastertoolkit_WP_Config::remove_constant( 'COOKIEHASH' );
    }

    /**
     * Build a random hash value for COOKIEHASH.
     *
     * @return string
     */
    private static function generate_cookiehash() {
        try {
            return hash( 'sha256', random_bytes( 64 ) );
        } catch ( Exception $e ) {
            return wp_generate_password( 64, true, true );
        }
    }
}
