<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Obfuscate Author Slugs
 * Description: Obfuscate publicly exposed author page URLs that shows the user slugs / usernames, e.g. sitename.com/author/username1/ into sitename.com/author/a6r5b8ytu9gp34bv/, and output 404 errors for the original URLs. Also obfuscates in /wp-json/wp/v2/users/ REST API endpoint.
 * @since 1.5.0
 */
class WPMastertoolkit_Obfuscate_Author_Slugs {

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'alter_author_query' ), 10 );
		add_filter( 'author_link', array( $this, 'alter_author_link' ), 10, 3 );
		add_filter( 'rest_prepare_user', array( $this, 'alter_json_users' ), 10, 3 );
	}

	/**
	 * Obfuscate author page URLs
	 * 
	 * @since 1.5.0
	 */
	function alter_author_query( $query ) {
        // Check if it's a query for author data, and that 'author_name' is not empty
        if ( $query->is_author() && $query->query_vars['author_name'] != '' ) {
            // Check for character(s) representing a hexadecimal digit
            if ( ctype_xdigit( $query->query_vars['author_name'] ) ) {
            	// Get user by the decrypted user ID
            	$user = get_user_by( 'id', $this->decrypt( $query->query_vars['author_name'] ) );
                if ( $user ) {
                    $query->set( 'author_name', $user->user_nicename );
                } else {
                    // No user found
                    $query->is_404 = true;
                    $query->is_author = false;
                    $query->is_archive = false;
                }
            } else {
                // No hexadecimal digit detected in URL, i.e. someone is trying to access URL with original author slug
                $query->is_404 = true;
                $query->is_author = false;
                $query->is_archive = false;
            }
        }
        
        return;
    }

	/**
	 * Replace author slug in author link to encrypted value
	 * 
	 * @since 1.5.0
	 */
	function alter_author_link( $link, $user_id, $author_slug ) {
        $encrypted_author_slug = $this->encrypt( $user_id );

        return str_replace ( '/' . $author_slug, '/' . $encrypted_author_slug, $link );
    }

	/**
	 * Replace author slug in REST API /users/ endpoint to encrypted value
	 * 
	 * @since 1.5.0
	 */
	function alter_json_users($response, $user, $request) {
        $data = $response->get_data();
        $data['slug'] = $this->encrypt($data['id']);
        $response->set_data($data);

        return $response;
    }

	/**
	 * Encrypted user ID
	 * 
	 * @since 1.5.0
	 */
	private function encrypt( $user_id ) {
        // Returns encrypted encrypted author slug from user ID, e.g. encrypt user ID 3 to author slug 4e3062d8c8626a14
        return bin2hex( openssl_encrypt( base_convert( $user_id, 10, 36 ), 'DES-EDE3', md5( WPMASTERTOOLKIT_PLUGIN_URL ), OPENSSL_RAW_DATA ) );
    }

	/**
	 * Decrypted user ID
	 * 
	 * @since 1.5.0
	 */
	private function decrypt( $encrypted_author_slug ) {
        // Returns user ID, e.g. decrypts author slug 4e3062d8c8626a14 into user ID 3
        return base_convert( openssl_decrypt( pack('H*', $encrypted_author_slug), 'DES-EDE3', md5( WPMASTERTOOLKIT_PLUGIN_URL ), OPENSSL_RAW_DATA ), 36, 10 );
    }
}
