<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name 		= $plugin_name;
		$this->version 			= $version;

	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since	1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

		if ( $hook_suffix === 'toplevel_page_wp-mastertoolkit-settings' ) {

			$settings_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/settings.asset.php' );
			wp_enqueue_style( WPMASTERTOOLKIT_PLUGIN_SETTINGS, WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/settings.css', array(), $settings_assets['version'], 'all' );
			wp_enqueue_script( WPMASTERTOOLKIT_PLUGIN_SETTINGS, WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/settings.js', $settings_assets['dependencies'], $settings_assets['version'], true );
		}
	}
		
	/**
	 * Add the settings menu page
	 *
	 * @since	1.0.0
	 */
	public function add_settings_menu() {
		add_menu_page(
			esc_html__('WPMasterToolkit Settings', 'wpmastertoolkit'),
			esc_html__('WPMasterToolkit', 'wpmastertoolkit'),
			'manage_options',
			'wp-mastertoolkit-settings',
			array( $this, 'render_settings_page' ),
			WPMASTERTOOLKIT_PLUGIN_URL . 'admin/svg/logo-admin.svg',
			100
		);
	}
	
	/**
	 * Render the settings page
	 *
	 * @since	1.0.0
	 */
	public function render_settings_page() {
		$db_options        = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		$opt_in_option     = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in', array() );
		$opt_in_status     = $opt_in_option['value'] ?? '1';
		$show_opt_in_modal = $opt_in_option['already'] ?? '0';

		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/page-settings.php';
	}

	/**
	 * Handle the submit buttons
	 * 
	 * @since	1.0.0
	 */
	public function settings_submit_button() {

		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );
		
		if ( ! wp_verify_nonce($nonce, WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_action') ) {
			return;
		}

		$settings_upload_json 	= isset($_POST['wpmastertoolkit_settings_tab_upload_json_submit']);
		$settings_download_json	= isset($_POST['wpmastertoolkit_settings_tab_download_json_submit']);

		WPMastertoolkit_Handle_options::require_once_all_options();

		if ( $settings_upload_json ) {

			$upload_file = wpmastertoolkit_clean( wp_unslash( $_FILES['wpmastertoolkit_settings_tab_input'] ) );

			if ( $upload_file ) {

				$upload_file_name 		= $upload_file['name'];
				$upload_file_type 		= $upload_file['type'];
				$upload_file_tmp_name	= $upload_file['tmp_name'];
				$upload_file_error 		= $upload_file['error'];
				$upload_file_size		= $upload_file['size'];

				if ( $upload_file_error != 0 ) {
					return;
				}

				if ( $upload_file_type !== 'application/json' ) {
					return;
				}

				if ( $upload_file_size > 1000000 ) {
					return;
				}

				$upload_file_content	= file_get_contents( $upload_file_tmp_name );
				$upload_file_json		= json_decode( $upload_file_content, true );
				
				if ( $upload_file_json ) {
					
					$default_settings   	= array_keys( wpmastertoolkit_options() );
					$sanitized_main_data	= array();
					$sanitized_items_data	= array();

					foreach ( $default_settings as $item ) {
						$sanitized_main_data[$item] = $upload_file_json[$item]['active'] ?? '0';

						if ( isset($upload_file_json[$item]['options']) ) {

							if ( class_exists( $item ) && method_exists( $item, 'sanitize_settings' ) && is_callable( $item . '::sanitize_settings' ) ) {
								$item_class = new $item();
								$sanitized_items_data[$item] = $upload_file_json[$item]['options'];
							}
						}
					}

					foreach ( $sanitized_items_data as $item_key => $item_value ) {
						if ( class_exists( $item_key ) && method_exists( $item_key, 'save_settings' ) && is_callable( $item_key . '::save_settings' ) ) {
							$item_class = new $item_key();
							$item_class->save_settings( $item_value );
						}
					}

					$this->save_main_settings( $sanitized_main_data );

					wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
					exit;
				}
			}

		} else if ( $settings_download_json ) {

			$old_settings		= get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
			$default_settings   = array_keys( wpmastertoolkit_options() );
			$sanitized_data		= array();

			foreach ( $default_settings as $item ) {

				$status = sanitize_text_field($old_settings[$item] ?? '0');

				$sanitized_data[$item]['active'] = $status;

				if ( class_exists( $item ) && method_exists( $item, 'get_settings' ) && is_callable( $item . '::get_settings' ) ) {
					$item_class = new $item();
					$options = $item_class->get_settings();

					$sanitized_data[$item]['options'] = $options;
				}
			}

			$upload_file_name		= 'wp-mastertoolkit-settings-' . date('Y-m-d') . '.json';
			$upload_file_content	= json_encode( $sanitized_data, JSON_PRETTY_PRINT );

			header('Content-Type: application/json');
			header('Content-Disposition: attachment; filename="' . $upload_file_name . '"');
			header('Content-Length: ' . strlen($upload_file_content));
			echo wp_kses_post( $upload_file_content );
			exit;

		} else {

			$new_settings = $this->sanitize_main_settings( $_POST[WPMASTERTOOLKIT_PLUGIN_SETTINGS] ?? array() );
			$this->save_main_settings( $new_settings );
			$this->save_opt_in();

			$class_stats = new WPMastertoolkit_Stats();
			$class_stats->send_stats();
	
			wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
		
	}

	/**
	 * Save the main settings
	 */
	private function save_main_settings( $new_settings ) {

		$old_settings	= get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );

		foreach ( $new_settings as $class => $status ) {

			/**
			 * Compare old and new settings to call activate or deactivate method
			 */
			if ( ( !isset($old_settings[$class]) || $old_settings[$class] !== '1' ) && $status === '1' ) {
				if ( class_exists( $class ) && method_exists( $class, 'activate' ) && is_callable( $class . '::activate' ) ) {
					$class::activate();
				}
			} else if ( isset($old_settings[$class]) && $old_settings[$class] === '1' && $status === '0' ) {
				if ( class_exists( $class ) && method_exists( $class, 'deactivate' ) && is_callable( $class . '::deactivate' ) ) {
					$class::deactivate();
				}
			}
		}

		update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, $new_settings );
	}

	/**
	 * Save the opt-in option
	 */
	private function save_opt_in() {
		$opt_in_option = array(
			'value'   => '0',
			'already' => '1',
		);

		$opt_in_option['value'] = sanitize_text_field( $_POST[WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in'] ?? '0' );

		update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in', $opt_in_option, false );
	}

	/**
	 * Sanitize the main settings
	 */
	private function sanitize_main_settings( $new_settings ) {

		$default_settings   = array_keys( wpmastertoolkit_options() );
		$sanitized_settings	= array();

		foreach ( $default_settings as $item ) {
			$sanitized_settings[$item] = sanitize_text_field($new_settings[$item] ?? '0');
		}

		return $sanitized_settings;
	}
	
	/**
	 * Get the changelog from the README.txt file and convert it to HTML
	 * @since	1.8.0
	 * Usage: WPMastertoolkit_Settings::get_changelog();
	 * @return void
	 */
	public static function get_changelog() {
		$readme_file = WPMASTERTOOLKIT_PLUGIN_PATH . 'README.txt';
		$readme_content = file_get_contents( $readme_file );
		
		if ( ! $readme_content ) return;

		$changelog = explode( '= ' . WPMASTERTOOLKIT_VERSION . ' =', $readme_content );
		$changelog = explode( '= ', $changelog[1] ?? '' );
		$changelog = $changelog[0] ?? '';

		if ( class_exists( 'Parsedown' ) ) {
			$Parsedown = new Parsedown();
			$changelog = $Parsedown->text( $changelog );
		} else {
			$changelog = nl2br( $changelog );
		}

		return $changelog;
	}

}