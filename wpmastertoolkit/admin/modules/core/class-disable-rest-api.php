<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable REST API
 * Description: Disable REST API access for non-authenticated users and remove URL traces from <head>, HTTP headers and WP RSD endpoint.
 * @since 1.5.0
 */
class WPMastertoolkit_Disable_REST_API {

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {

		if ( version_compare( get_bloginfo( 'version' ), '4.7', '>=' ) ) {
			add_filter( 'rest_authentication_errors', array( $this, 'disable_rest_api' ) );
		} else {
			// REST API 1.x
			add_filter( 'json_enabled', '__return_false' );
			add_filter( 'json_jsonp_enabled', '__return_false' );
			// REST API 2.x
			add_filter( 'rest_enabled', '__return_false' );
			add_filter( 'rest_jsonp_enabled', '__return_false' );
		}

		// Disable REST API links in HTML <head>
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
        // Disable REST API link in HTTP headers
        remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
        // Remove REST API URL from the WP RSD endpoint.
        remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
    }

	/**
	 * Disable REST API
	 * 
	 * @since 1.5.0
	 */
	public function disable_rest_api( $errors ) {

		if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_api_authentication_required',
				__( 'The REST API has been restricted to authenticated users.', 'wp-mastertoolkit' ),
                array(
                    'status' => rest_authorization_required_code()
                )
            );
        }

		return $errors;
	}
}
