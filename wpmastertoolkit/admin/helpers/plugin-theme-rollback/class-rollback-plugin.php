<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Upgrade/Install plugin by extending the WP Core Plugin Upgrader.
 * 
 * @since 1.10.0
 */
class WPMastertoolkit_Plugin_Theme_Rollback_Plugin extends Plugin_Upgrader {

	/**
	 * Plugin rollback.
	 * 
	 * @since 1.10.0
	 */
	public function wpmastertoolkit_rollback_module( $plugin, $args = array() ) {
		$defaults    = array(
			'clear_update_cache' => true,
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$this->init();
		$this->upgrade_strings();

		$plugin_slug       = $this->skin->plugin;
		$plugin_version    = $this->skin->options['version'];
		$download_endpoint = 'https://downloads.wordpress.org/plugin/';
		$url               = $download_endpoint . $plugin_slug . '.' . $plugin_version . '.zip';
		$is_plugin_active  = is_plugin_active( $plugin );

		add_filter( 'upgrader_pre_install', array( $this, 'active_before' ), 10, 2 );
        add_filter( 'upgrader_post_install', array( $this, 'active_after' ), 10, 2 );

		$this->run( array(
			'package'           => $url,
			'destination'       => WP_PLUGIN_DIR,
			'clear_destination' => true,
			'clear_working'     => true,
			'hook_extra'        => array(
				'plugin' => $plugin,
				'type'   => 'plugin',
				'action' => 'update',
				'bulk'   => 'false',
			),
		) );

		remove_filter( 'upgrader_pre_install', array( $this, 'active_before' ) );
        remove_filter( 'upgrader_post_install', array( $this, 'active_after' ) );

		if ( ! $this->result || is_wp_error( $this->result ) ) {
			return $this->result;
		}

		if ( $is_plugin_active ) {
            activate_plugin( $plugin );
        }

		return true;
	}
}
