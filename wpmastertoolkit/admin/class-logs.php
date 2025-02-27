<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the logs.
 *
 * @link       https://webdeclic.com
 * @since      2.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Logs {

	/**
	 * add notice
	 * 
	 * @since    2.0.0
	 */
	public static function add_notice( $message ) {
		$message = 'NOTICE: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add error
	 * 
	 * @since    2.0.0
	 */
	public static function add_error( $message ) {
		$message = 'ERROR: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add log
	 * 
	 * @since    2.0.0
	 */
	public static function add_log( $message ) {
		$this_class = new self();
		add_filter( 'wpmastertoolkit/folders', array( $this_class, 'create_folders' ) );

		$file_path = wpmastertoolkit_folders() . '/logs/wpmtk-' . wp_date( 'Y-m-d' ) . '.log';
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$message   = '[' . wp_date( 'd-M-Y H:i:s' ) . '] ' . print_r( $message, true ) . "\n";

		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message, 3, $file_path );
	}

	/**
     * Create logs folders
     *
     * @since    2.0.0
     */
    public function create_folders( $folders ) {
        $folders['wpmastertoolkit']['logs'] = array();
        return $folders;
    }
}
