<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/admin/helpers/core/comment-on-everything/class-contexts.php';
require_once __DIR__ . '/admin/helpers/core/comment-on-everything/class-discussions.php';
global $wpdb;
$wpmastertoolkit_discussions = new WPMastertoolkit_Collaboration_Discussions( $wpdb, new WPMastertoolkit_Collaboration_Contexts() );
$wpmastertoolkit_discussions->uninstall();

require_once __DIR__ . '/admin/modules/core/class-admin-boost.php';
if ( is_multisite() ) {
	$wpmastertoolkit_boost_offset = 0;
	do {
		$wpmastertoolkit_boost_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $wpmastertoolkit_boost_offset ) );
		foreach ( $wpmastertoolkit_boost_sites as $wpmastertoolkit_boost_site ) {
			switch_to_blog( $wpmastertoolkit_boost_site );
			try {
				WPMastertoolkit_Admin_Boost::uninstall_site();
			} finally {
				restore_current_blog();
			}
		}
		$wpmastertoolkit_boost_offset += 100;
	} while ( 100 === count( $wpmastertoolkit_boost_sites ) );
} else {
	WPMastertoolkit_Admin_Boost::uninstall_site();
}
delete_site_option( WPMastertoolkit_Admin_Boost::OPTION . '_network_generation' );
