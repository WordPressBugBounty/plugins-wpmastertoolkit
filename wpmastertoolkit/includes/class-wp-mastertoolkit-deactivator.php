<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Fired during plugin deactivation
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package    	WPMastertoolkit
 * @subpackage	WP-Mastertoolkit/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package   	WPMastertoolkit
 * @subpackage	WPMastertoolkit/includes
 * @author    	Webdeclic <contact@webdeclic.com>
 */
class WPMastertoolkit_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		self::delete_limit_login_attempts_table();
	}

	/**
	 * Delete table
	 * 
	 * @since 1.5.0
	 */
	public static function delete_limit_login_attempts_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_limit_login_attempts';
        $wpdb->query( "DROP TABLE IF EXISTS `". $table_name ."`" );
	}
}
