<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable jQuery Migrate
 * Description: Removes the jQuery Migrate script from the frontend of your site.
 * @since 1.10.0
 */
class WPMastertoolkit_Disable_jQuery_Migrate {

    /**
     * Invoke the hooks
     * 
     * @since    1.10.0
     */
    public function __construct() {
		add_action( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
    }

	/**
	 * Remove jQuery Migrate
	 * 
	 * @since    1.10.0
	 */
	public function remove_jquery_migrate( $scripts ) {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$script = $scripts->registered['jquery'];

			if ( ! empty( $script->deps ) ) {
				$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
			}
		}
	}
}
