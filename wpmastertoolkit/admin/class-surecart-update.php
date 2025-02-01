<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the surecart update.
 *
 * @link       https://webdeclic.com
 * @since      1.15.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Surecart_Update extends Plugin_Upgrader {

	/**
	 * Update from surecart.
	 * 
	 * @since 1.15.0
	 */
	public function update_from_surecart( $package ) {
		$this->init();
		$this->upgrade_strings();

		add_filter( 'upgrader_pre_install', array( $this, 'active_before' ), 10, 2 );
        add_filter( 'upgrader_post_install', array( $this, 'active_after' ), 10, 2 );

		$this->run( array(
			'package'           => $package,
			'destination'       => WP_PLUGIN_DIR,
			'clear_destination' => true,
			'clear_working'     => true,
			'hook_extra'        => array(
				'plugin' => 'wpmastertoolkit',
				'type'   => 'plugin',
				'action' => 'update',
				'bulk'   => 'false',
			),
		) );

		remove_filter( 'upgrader_pre_install', array( $this, 'active_before' ) );
        remove_filter( 'upgrader_post_install', array( $this, 'active_after' ) );
	}
}
