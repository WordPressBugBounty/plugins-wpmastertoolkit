<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Send data to WPMastertoolkit if the user is allowed to do so.
 * No personal information is collected, only general WPMastertoolkit settings.
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Stats {

	/**
	 * URL to the WPMastertoolkit API endpoint.
	 *
	 * @since    1.12.0
	 * @access   private
	 * @var string
	 */
	private $update_url;

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		$this->update_url = 'https://wpmastertoolkit.com/wp-json/wpmastertoolkit-opt-in/v1/update';

		if ( wp_get_environment_type() == 'local' || WPMASTERTOOLKIT_DEV_MOD ) {
			$this->update_url = false;
		}
	}

	/**
	 * Send data to WPMastertoolkit if the user is allowed to do so.
	 */
	public function send_stats() {
		$settings  = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in', array() );
		$value     = $settings['value'] ?? '1';

		if ( $value != '1' ) {
			return;
		}

		$options = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );

		$body = array(
			'options' => $options,
			'version' => WPMASTERTOOLKIT_VERSION,
		);

		if ( $this->update_url ) {
			wp_remote_post( $this->update_url, array(
				'body'    => $body,
				'timeout' => 60,
			));
		}
	}
}
