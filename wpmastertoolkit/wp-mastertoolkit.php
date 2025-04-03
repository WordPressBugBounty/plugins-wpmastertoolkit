<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WPMasterToolKit
 * Plugin URI:        https://wpmastertoolkit.com/
 * Description:       WPMasterToolKit enhances your WordPress administration experience by providing a powerful suite of features designed to optimize and streamline your website management. From media enhancements to user experience improvements and security fortifications, this toolkit is essential for any WordPress site owner looking to elevate their admin interface. With easy-to-use settings and impactful tweaks, you can tailor your site's backend to your specific needs.
 * Version:           2.5.2
 * Author:            Webdeclic
 * Author URI:        https://webdeclic.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpmastertoolkit
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if your are in local or production environment
 */
$is_local = isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1');

$version  = get_file_data( __FILE__, array( 'Version' => 'Version' ), false )['Version'];

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WPMASTERTOOLKIT_VERSION', $version );

/**
 * You can use this const for check if you are in local environment
 */
define( 'WPMASTERTOOLKIT_DEV_MOD', $is_local );

/**
 * Plugin File
 */
define( 'WPMASTERTOOLKIT_PLUGIN_FILE', __FILE__ );

/**
 * Plugin Name Path for plugin includes.
 */
define( 'WPMASTERTOOLKIT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin Name URL for plugin sources (css, js, images etc...).
 */
define( 'WPMASTERTOOLKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin Name Basename for plugin sources (css, js, images etc...).
 */
define( 'WPMASTERTOOLKIT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin Settings Name
 */
define ( 'WPMASTERTOOLKIT_PLUGIN_SETTINGS', 'wpmastertoolkit_settings' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-mastertoolkit-activator.php
 */
register_activation_hook( __FILE__, function(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-mastertoolkit-activator.php';
	WPMastertoolkit_Activator::activate();
} );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-mastertoolkit-deactivator.php
 */
register_deactivation_hook( __FILE__, function(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-mastertoolkit-deactivator.php';
	WPMastertoolkit_Deactivator::deactivate();
} );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-mastertoolkit.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wpmastertoolkit() {

	$plugin = new WPMastertoolkit();
	$plugin->run();

}
run_wpmastertoolkit();
