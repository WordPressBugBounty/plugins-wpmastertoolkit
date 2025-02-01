<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Upgrade/Install theme by extending the WP Core Theme Upgrader.
 * 
 * @since 1.10.0
 */
class WPMastertoolkit_Plugin_Theme_Rollback_Theme extends Theme_Upgrader {

	/**
	 * Theme rollback.
	 *
	 * @since 1.10.0
	 */
	public function wpmastertoolkit_rollback_module( $theme, $args = array() ) {
		$defaults    = array(
			'clear_update_cache' => true,
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$this->init();
		$this->upgrade_strings();

		$theme_slug        = $this->skin->theme;
		$theme_version     = $this->skin->options['version'];
		$download_endpoint = 'https://downloads.wordpress.org/theme/';
		$url               = $download_endpoint . $theme_slug . '.' . $theme_version . '.zip';

		add_filter( 'upgrader_pre_install', array( $this, 'current_before' ), 10, 2 );
		add_filter( 'upgrader_post_install', array( $this, 'current_after' ), 10, 2 );
		add_filter( 'upgrader_clear_destination', array( $this, 'delete_old_theme' ), 10, 4 );

		// 'source_selection' => array($this, 'source_selection'), 
		//There's a trac ticket to enable zip directory traversal for non-.org plugins.
		$this->run( array(
			'package'           => $url,
			'destination'       => get_theme_root(),
			'clear_destination' => true,
			'clear_working'     => true,
			'hook_extra'        => array(
				'theme'  => $theme,
				'type'   => 'theme',
				'action' => 'update',
			),
		) );

		remove_filter( 'upgrader_pre_install', array( $this, 'current_before' ) );
		remove_filter( 'upgrader_post_install', array( $this, 'current_after' ) );
		remove_filter( 'upgrader_clear_destination', array( $this, 'delete_old_theme' ) );

		if ( ! $this->result || is_wp_error( $this->result ) ) {
			return $this->result;
		}

		wp_clean_themes_cache( $parsed_args['clear_update_cache'] );
		
		return true;
	}
}
