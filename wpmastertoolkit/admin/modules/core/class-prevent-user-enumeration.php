<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Prevent User Enumeration
 * Description: Prevent user enumeration via ?author=X and REST API /users/ endpoints.
 * @since 1.15.0
 */
class WPMasterToolKit_Prevent_User_Enumeration {

    /**
     * Constructor to hook into WordPress actions and filters.
     *
     * @since 1.15.0
     */
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'set_404_for_author_pages' ) );
        add_filter( 'author_link', array( $this, 'replace_author_link' ) );
        add_action( 'init', array( $this, 'block_author_page_front' ) );
        add_filter( 'rest_request_before_callbacks', array( $this, 'block_author_page_rest' ) );
    }

    /**
     * Set 404 for author pages accessed via ?author=X.
     *
     * @since 1.15.0
     */
    public function set_404_for_author_pages() {
        global $wp_query;
        if ( is_author() ) {
            $wp_query->set_404();
            status_header( 404 );
            exit;
        }
    }

    /**
     * Replace author links with the home URL to prevent exposing author pages.
     *
     * @since 1.15.0
     * @return string Home URL.
     */
    public function replace_author_link() {
        return home_url();
    }

    /**
     * Block author pages for users without proper permissions on the front end.
     *
     * @since 1.15.0
     */
    public function block_author_page_front() {
        if ( ! current_user_can( 'list_users' ) && is_author() ) {
            wp_die( 
                esc_html__( 'Access denied.', 'wpmastertoolkit' ), 
                esc_html__( 'Forbidden', 'wpmastertoolkit' ), 
                array( 'response' => 403 )
            );
        }
    }

    /**
     * Block user enumeration via the REST API.
     *
     * @param WP_Error|null $response Current response object.
     * @since 1.15.0
     * @return WP_Error|null Modified response object or original response.
     */
    public function block_author_page_rest( $response ) {
        if ( ! current_user_can( 'list_users' ) && strpos( $this->get_current_rest_url(), '/wp/v2/users' ) !== false ) {
            wp_send_json( 
                array(
                    'code'    => 'rest_cannot_access',
                    'message' => __( 'Access to user enumeration is forbidden.', 'wpmastertoolkit' ),
                    'data'    => array( 'status' => 401 ),
                ), 
                401 
            );
        }
        return $response;
    }

    /**
     * Get the current REST API URL.
     *
     * @since 1.15.0
     * @return string Current REST API URL.
     */
    private function get_current_rest_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        return home_url( $request_uri );
    }
}
