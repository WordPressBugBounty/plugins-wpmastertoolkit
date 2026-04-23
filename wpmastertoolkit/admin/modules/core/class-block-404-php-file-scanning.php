<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Block 404 PHP File Scanning
 * Description: Block requests targeting nonexistent PHP files that resolve as 404.
 * @since 2.20.0
 */
class WPMastertoolkit_Block_404_Php_File_Scanning {

	const LOG_CODE = 'PHP404';

	/**
	 * Invoke the hooks.
	 *
	 * @since 2.20.0
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_block_request' ), 9999999 );
	}

	/**
	 * Block requests to nonexistent PHP files only when WordPress resolved them as 404.
	 *
	 * @since 2.20.0
	 * @return void
	 */
	public function maybe_block_request() {
		if ( is_admin() || $this->is_non_front_request() || ! is_404() ) {
			return;
		}

		$current_url = $this->get_current_url();
		if ( '' === $current_url ) {
			return;
		}

		/**
		 * Allow bypassing the PHP 404 blocking logic.
		 *
		 * @since 2.20.0
		 *
		 * @param bool   $bypass      Whether to bypass the block.
		 * @param string $current_url Current request URL.
		 */
		if ( true === apply_filters( 'wpmastertoolkit/block_404_on_php/bypass', false, $current_url ) ) {
			return;
		}

		$request_path = $this->get_request_path( $current_url );
		if ( '' === $request_path || ! $this->is_php_request( $request_path ) ) {
			return;
		}

		$file_path = $this->get_requested_file_path( $request_path );
		if ( '' === $file_path || file_exists( $file_path ) ) {
			return;
		}

		WPMastertoolkit_Logs::add_notice( sprintf( '[%s] Blocked nonexistent PHP request: %s', self::LOG_CODE, $current_url ) );

		wp_die(
			esc_html__( 'Access denied.', 'wpmastertoolkit' ),
			esc_html__( 'Forbidden', 'wpmastertoolkit' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Build the current request URL.
	 *
	 * @since 2.20.0
	 * @return string
	 */
	private function get_current_url() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || '' === $_SERVER['REQUEST_URI'] ) {
			return '';
		}

		return home_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	/**
	 * Extract the normalized request path.
	 *
	 * @since 2.20.0
	 * @param string $current_url Current request URL.
	 * @return string
	 */
	private function get_request_path( $current_url ) {
		$request_path = wp_parse_url( $current_url, PHP_URL_PATH );

		if ( ! is_string( $request_path ) || '' === $request_path ) {
			return '';
		}

		return wp_normalize_path( rawurldecode( $request_path ) );
	}

	/**
	 * Check if the request targets a PHP file.
	 *
	 * @since 2.20.0
	 * @param string $request_path Request path.
	 * @return bool
	 */
	private function is_php_request( $request_path ) {
		return (bool) preg_match( '/\.php$/i', $request_path );
	}

	/**
	 * Resolve the requested URL path to a file path under the WordPress root.
	 *
	 * @since 2.20.0
	 * @param string $request_path Request path.
	 * @return string
	 */
	private function get_requested_file_path( $request_path ) {
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? wp_normalize_path( $home_path ) : '/';

		if ( '' !== $home_path && '/' !== $home_path ) {
			$home_path = untrailingslashit( $home_path );

			if ( 0 === strpos( $request_path, $home_path ) ) {
				$request_path = substr( $request_path, strlen( $home_path ) );
			}
		}

		$request_path = ltrim( $request_path, '/' );
		if ( '' === $request_path || false !== strpos( $request_path, '../' ) || false !== strpos( $request_path, '..\\' ) ) {
			return '';
		}

		return wp_normalize_path( trailingslashit( ABSPATH ) . $request_path );
	}

	/**
	 * Skip non frontend requests.
	 *
	 * @since 2.20.0
	 * @return bool
	 */
	private function is_non_front_request() {
		return ( defined( 'DOING_CRON' ) && true === DOING_CRON )
			|| ( defined( 'XMLRPC_REQUEST' ) && true === XMLRPC_REQUEST )
			|| ( defined( 'REST_REQUEST' ) && true === REST_REQUEST )
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
			|| ( defined( 'WP_CLI' ) && true === WP_CLI );
	}
}