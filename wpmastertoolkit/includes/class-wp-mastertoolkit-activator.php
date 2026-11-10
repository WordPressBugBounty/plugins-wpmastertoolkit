<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Fired during plugin activation
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package    	WPMastertoolkit
 * @subpackage	WP-Mastertoolkit/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package   	WPMastertoolkit
 * @subpackage	WPMastertoolkit/includes
 * @author    	Webdeclic <contact@webdeclic.com>
 */
class WPMastertoolkit_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate( $network_wide = false ) {
		global $wpdb;

		if ( false !== get_option( WPMASTERTOOLKIT_ONBOARDING_OPTION, false ) ) {
			return;
		}

		$has_existing_data = false;
		$footprint_options = array(
			WPMASTERTOOLKIT_PLUGIN_SETTINGS,
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in',
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_promot',
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu',
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_active_super_admins',
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_super_admins',
			'wpmastertoolkit_credentials_tab',
			'wpmastertoolkit_license_options',
		);

		foreach ( $footprint_options as $option_name ) {
			if ( false !== get_option( $option_name, false ) ) {
				$has_existing_data = true;
				break;
			}
		}

		if ( ! $has_existing_data ) {
			$option_like = $wpdb->esc_like( 'wpmastertoolkit_' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_existing_data = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT IN (%s, %s) LIMIT 1",
					$option_like,
					WPMASTERTOOLKIT_ONBOARDING_OPTION,
					'wpmastertoolkit_module_manager_lock'
				)
			);
		}

		if ( ! $has_existing_data ) {
			$table_like = $wpdb->esc_like( $wpdb->prefix . 'wpmastertoolkit_' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_existing_data = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ) );
		}

		$status = 'pending';
		if ( $network_wide || is_multisite() ) {
			$status = 'network_skipped';
		} elseif ( $has_existing_data ) {
			$status = 'legacy_skipped';
		}

		$state = array(
			'status'           => $status,
			'schema_version'   => 1,
			'plugin_version'   => WPMASTERTOOLKIT_VERSION,
			'created_at'       => time(),
			'completed_at'     => 0,
			'selected_presets' => array(),
			'last_result'      => array(),
		);

		add_option( WPMASTERTOOLKIT_ONBOARDING_OPTION, $state, '', false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_bulk_activation = 'activate-selected' === sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
		if ( 'pending' === $status && ! $is_bulk_activation && get_current_user_id() ) {
			set_transient( 'wpmastertoolkit_onboarding_redirect_' . get_current_user_id(), 1, 5 * MINUTE_IN_SECONDS );
		}
	}

}
