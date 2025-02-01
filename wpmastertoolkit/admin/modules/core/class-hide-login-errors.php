<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Hide Login Errors
 * Description: Hide login errors from the login page for better security
 * @since 1.0.0
 */
class WPMastertoolkit_Hide_Login_Errors {
	/**
	 * Invoke Wp Hooks
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_filter( 'login_errors', array( $this, 'hide_login_errors_login_errors' ) );
	}
	
	/**
	 * hide_login_errors_login_errors
	 *
	 * @param  mixed $error
	 * @return void
	 */
	public function hide_login_errors_login_errors( $error ) {
		return esc_html__( 'Invalid username or password.', 'wpmastertoolkit' );
	}
}