<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable All Updates
 * Description: Completely disable core, theme and plugin updates and auto-updates. Will also disable update checks, notices and emails.
 * @since 1.5.0
 */
class WPMastertoolkit_Disable_All_Updates {

	/**
     * Invoke the hooks
     * 
     * @since    1.5.0
     */
    public function __construct() {
		$pre_transient_prefix          = 'pre_transient';//phpcs:ignore prefix to ignore the error
		$pre_site_transient_prefix     = 'pre_site_transient';//phpcs:ignore prefix to ignore the error
		$pre_set_site_transient_prefix = 'pre_set_site_transient';//phpcs:ignore prefix to ignore the error

		add_action( 'admin_init', array( $this, 'disable_update_notices_version_checks' ) );
		// Disable core update
		add_filter( $pre_transient_prefix . '_update_core', array( $this, 'override_version_check_info' ) );
		add_filter( $pre_site_transient_prefix . '_update_core', array( $this, 'override_version_check_info' ) );
		// Disable theme updates
		add_filter( $pre_transient_prefix . '_update_themes', array( $this, 'override_version_check_info' ) );
		add_filter( $pre_site_transient_prefix . '_update_themes', array( $this, 'override_version_check_info' ) );
		add_action( $pre_set_site_transient_prefix . '_update_themes', array( $this, 'override_version_check_info' ), 20 );
		// Disable plugin updates
		add_filter( $pre_transient_prefix . '_update_plugins', array( $this, 'override_version_check_info' ) );
		add_filter( $pre_site_transient_prefix . '_update_plugins', array( $this, 'override_version_check_info' ) );
		add_action( $pre_set_site_transient_prefix . '_update_plugins', array( $this, 'override_version_check_info' ), 20 );
		// Disable auto updates
		add_filter( 'automatic_updater_disabled', '__return_true' );
		if ( ! defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
			define( 'AUTOMATIC_UPDATER_DISABLED', true );
		}
		if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			define( 'WP_AUTO_UPDATE_CORE', false );
		}
		add_filter( 'auto_update_core', '__return_false' );
		add_filter( 'wp_auto_update_core', '__return_false' );
		add_filter( 'allow_minor_auto_core_updates', '__return_false' );
		add_filter( 'allow_major_auto_core_updates', '__return_false' );
		add_filter( 'allow_dev_auto_core_updates', '__return_false' );
		$auto_update_prefix = 'auto_update';//phpcs:ignore prefix to ignore the error
		add_filter( $auto_update_prefix . '_plugin', '__return_false' );
		add_filter( $auto_update_prefix . '_theme', '__return_false' );
		add_filter( $auto_update_prefix . '_translation', '__return_false' );
		remove_action( 'init', 'wp_schedule_update_checks' );
		// Disable update emails
		add_filter( 'auto_core_update_send_email', '__return_false' );
		add_filter( 'send_core_update_notification_email', '__return_false' );
		add_filter( 'automatic_updates_send_debug_email', '__return_false' );
		// Remove Dashboard >> Updates menu
		add_action( 'admin_menu', array( $this, 'remove_updates_menu' ) );
	}

	/**
	 * Disable update checks, notices and emails
	 * 
	 * @since 1.5.0
	 */
    public function disable_update_notices_version_checks() {
		// Remove nags
        remove_action( 'admin_notices', 'update_nag', 3 );
        remove_action( 'admin_notices', 'maintenance_nag' );
        // Disable WP version check
        remove_action( 'wp_version_check', 'wp_version_check' );
        remove_action( 'admin_init', 'wp_version_check' );
        wp_clear_scheduled_hook( 'wp_version_check' );
        add_filter( 'pre_option_update_core', '__return_null' );
        // Disable theme version checks
        remove_action( 'wp_update_themes', 'wp_update_themes' );
        remove_action( 'admin_init', '_maybe_update_themes' );
        wp_clear_scheduled_hook( 'wp_update_themes' );
        remove_action( 'load-themes.php', 'wp_update_themes' );
        remove_action( 'load-update.php', 'wp_update_themes' );
        remove_action( 'load-update-core.php', 'wp_update_themes' );
        // Disable plugin version checks
        remove_action( 'wp_update_plugins', 'wp_update_plugins' );
        remove_action( 'admin_init', '_maybe_update_plugins' );
        wp_clear_scheduled_hook( 'wp_update_plugins' );
        remove_action( 'load-plugins.php', 'wp_update_plugins' );
        remove_action( 'load-update.php', 'wp_update_plugins' );
        remove_action( 'load-update-core.php', 'wp_update_plugins' );
        // Disable auto updates
        wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
        remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
        remove_action( 'admin_init', 'wp_maybe_auto_update' );
        remove_action( 'admin_init', 'wp_auto_update_core' );
        // Disable Site Health checks
        add_filter( 'site_status_tests', array( $this, 'disable_update_checks_in_site_health' ) );
	}

	/**
	 * Disable Updates in Site Health tests
	 * 
	 * @since 1.5.0
	 */
	public function disable_update_checks_in_site_health( $tests ) {
        unset( $tests['async']['background_updates'] );
        unset( $tests['direct']['plugin_theme_auto_updates'] );

        return $tests;
    }

	/**
	 * Override version check info
	 * 
	 * @since 1.5.0
	 */
	public function override_version_check_info() {
        include( ABSPATH . WPINC . '/version.php' ); // get $wp_version from here

        $current = (object)array(); // create empty object
        $current->updates = array();
        $current->response = array();
        $current->version_checked = $wp_version;
        $current->last_checked = time();

        return $current;
    }

	/**
	 * Remove Dashboard >> Updates menu
	 * 
	 * @since 1.5.0
	 */
	public function remove_updates_menu() {
        remove_submenu_page( 'index.php', 'update-core.php' );
    }
}
