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
	 * Schedule CRON event for daily module assets regeneration
	 * 
	 * @since	2.14.0
	 */
	public function schedule_cron_event() {
		if ( ! wp_next_scheduled( 'wpmastertoolkit_daily_regenerate_assets' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmastertoolkit_daily_regenerate_assets' );
		}
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since	1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

	if ( $hook_suffix === 'toplevel_page_wp-mastertoolkit-settings' ) {

		$settings_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/settings.asset.php' );
		wp_enqueue_style( WPMASTERTOOLKIT_PLUGIN_SETTINGS, WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/settings.css', array(), $settings_assets['version'], 'all' );
		wp_enqueue_script( WPMASTERTOOLKIT_PLUGIN_SETTINGS, WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/settings.js', $settings_assets['dependencies'], $settings_assets['version'], true );
		wp_localize_script(
			WPMASTERTOOLKIT_PLUGIN_SETTINGS,
			'wpmastertoolkit_settings',
			array(
				'pluginUrl' => WPMASTERTOOLKIT_PLUGIN_URL,
			)
		);
		wp_localize_script(
			WPMASTERTOOLKIT_PLUGIN_SETTINGS,
			'wpmastertoolkit_settings_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpmastertoolkit_settings' ),
			)
		);
	}
		$settings_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/global-admin.asset.php' );
		wp_enqueue_style( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '.global-admin', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.css', array(), $settings_assets['version'], 'all' );
		wp_enqueue_script( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '.global-admin', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/global-admin.js', $settings_assets['dependencies'], $settings_assets['version'], true );
		wp_localize_script(
			WPMASTERTOOLKIT_PLUGIN_SETTINGS . '.global-admin',
			'wpmtk_global_admin_object',
			array(
				'use_wp_submenu' => get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', 1 ),
				'i18n'          => array(
					'Modules'           => esc_html__( 'Modules', 'wpmastertoolkit' ),
				),
			)
		);
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
	 * add_submenu_page
	 *
	 * @param  mixed $parent_slug
	 * @param  mixed $page_title
	 * @param  mixed $menu_title
	 * @param  mixed $capability
	 * @param  mixed $menu_slug
	 * @param  mixed $function
	 * @return void
	 */
	public static function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
		global $wpmtk_module_settings_submenu_pages;
		if ( ! is_array( $wpmtk_module_settings_submenu_pages ) ) {
			$wpmtk_module_settings_submenu_pages = array();
		}
		$module_class = is_array( $function ) && isset( $function[0] ) ? get_class( $function[0] ) : false;
		if ( $module_class ) {
			$wpmtk_module_settings_submenu_pages[ $module_class ] = $menu_slug;
		}
		add_submenu_page(
			$parent_slug,
			$page_title,
			'↳ ' . $menu_title,
			$capability,
			$menu_slug,
			$function
		);
	}
	
	/**
	 * Render the settings page
	 *
	 * @since	1.0.0
	 */
	public function render_settings_page() {
		$db_options              = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
		$opt_in_option           = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in', array() );
		$promot_option           = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_promot', array() );
		$use_wp_submenu_status   = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', 1 );
		$opt_in_status           = $opt_in_option['value'] ?? '1';
		$show_opt_in_modal       = $opt_in_option['already'] ?? '0';
		$promot_option_status    = $promot_option['status'] ?? '';
		$promot_option_last_show = $promot_option['last_show'] ?? '0';
		$promot_delay_to_show    = DAY_IN_SECONDS * 45;
		$promot_show_now         = ( time() - $promot_delay_to_show ) > $promot_option_last_show;
		$show_promot_modal       = !wpmastertoolkit_is_pro() && $show_opt_in_modal === '1' && $promot_option_status !== 'no-longer' && $promot_show_now;
		$try_url                 = 'https://wpmastertoolkit.com/en/wpmtk-products/wpmastertoolkit-pro/';
		if ( get_locale() === 'fr_FR' ) {
			$try_url = 'https://wpmastertoolkit.com/fr/produits/wpmastertoolkit-pro/';
		}

		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/page-settings.php';
	}

	/**
	 * Handle the submit buttons
	 * 
	 * @since	1.0.0
	 */
	public function settings_submit_button() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( ! wp_verify_nonce($nonce, WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_action') ) {
			return;
		}

		$promot_modal_clicked   = isset($_POST['wpmastertoolkit_promot_modal']);
		$settings_upload_json 	= isset($_POST['wpmastertoolkit_settings_tab_upload_json_submit']);
		$settings_download_json	= isset($_POST['wpmastertoolkit_settings_tab_download_json_submit']);

		WPMastertoolkit_Handle_options::require_once_all_options();

		if ( $promot_modal_clicked ) {

			$promot_modal       = sanitize_text_field( wp_unslash( $_POST['wpmastertoolkit_promot_modal'] ) );
			$promot_option      = array();
			$manage_license_url = false;

			if ( 'no-longer' == $promot_modal ){
				$promot_option = array(
					'status' => 'no-longer'
				);
			} else if ( 'have-license' == $promot_modal ) {
				$promot_option = array(
					'status'    => '',
					'last_show' => time(),
				);
				$manage_license_url = admin_url( 'admin.php?page=wpmastertoolkit-manage-license' );
			} else if ( 'try-now' == $promot_modal ) {
				$promot_option = array(
					'status'    => '',
					'last_show' => time(),
				);
				$manage_license_url = admin_url( 'admin.php?page=wpmastertoolkit-manage-license' );
			}
			
			update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_promot', $promot_option, false );
			if ( $manage_license_url ) {
				wp_safe_redirect( $manage_license_url );
				exit;
			}

		} else if ( $settings_upload_json ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$upload_file = wpmastertoolkit_clean( wp_unslash( $_FILES['wpmastertoolkit_settings_tab_input'] ?? '' ) );

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

					wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
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

			$upload_file_name		= 'wp-mastertoolkit-settings-' . wp_date('Y-m-d') . '.json';
			$upload_file_content	= json_encode( $sanitized_data, JSON_PRETTY_PRINT );

			header('Content-Type: application/json');
			header('Content-Disposition: attachment; filename="' . $upload_file_name . '"');
			header('Content-Length: ' . strlen($upload_file_content));
			echo wp_kses_post( $upload_file_content );
			exit;

		} else {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_settings = $this->sanitize_main_settings( wp_unslash( $_POST[WPMASTERTOOLKIT_PLUGIN_SETTINGS] ?? array() ) );
			$this->save_main_settings( $new_settings );
			$this->save_opt_in();
			$this->save_credentials();
			$this->save_use_wp_submenu();

			$class_stats = new WPMastertoolkit_Stats();
			$class_stats->send_stats();
	
			wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
	}

	/**
	 * Add shortcodes
	 * 
	 * @since 2.14.0
	 */
	public function add_shortcodes() {
		add_shortcode( 'wpmtk_changelog', array( $this, 'wpmtk_changelog_cb' ) );
	}

	/**
	 * Changelog shortcode callback
	 * 
	 * @since 2.14.0
	 */
	public function wpmtk_changelog_cb( $atts ) {
		if ( ! file_exists( WPMASTERTOOLKIT_PLUGIN_PATH . 'changelog.txt' ) ) {
			return '';
		}

		$settings_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/shortcode-changelog.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_shortcode_changelog', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/shortcode-changelog.css', array(), $settings_assets['version'], 'all' );

		$atts = shortcode_atts( array(
			'limit' => '-1',
		), $atts );

		$limit    = $atts['limit'];
		$is_limit = $limit !== '-1';
		$content  = file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'changelog.txt' );
        $parsed   = $this->parse_changelog( $content );

		if ( $is_limit ) {
			$parsed = array_slice( $parsed, 0, $limit );
		}

		ob_start();
		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/changelog.php';
		return ob_get_clean();
	}

	/**
	 * Parse the changelog
	 * 
	 * @since 2.14.0
	 */
	private function parse_changelog( $text ) {
		$text  = trim( $text );
		$text  = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$lines = preg_split( "/\r\n|\n|\r/", $text );

		$versions        = array();
		$current_version = null;

		foreach ( $lines as $raw ) {
			$line = trim( $raw );

			if ( $line === '' ) {
				continue;
			}

			// skip header lines
			if ( preg_match( '/^={2,}\s*Changelog\s*={2,}$/i', $line ) ) {
				continue;
			}

			// version line like: = 2.13.0 =
			if ( preg_match( '/^=\s*([0-9a-zA-Z\.\-_]+)\s*=?\s*=$/', $line, $version_matches ) ) {
				$current_version              = $version_matches[1];
				$versions[ $current_version ] = [];
				continue;
			}

			// If we don't yet have a version, skip
			if ( ! $current_version ) {
				continue;
			}

			// 1) Extract type and the rest (type is required)
			if ( ! preg_match( '/^(Fix|Tweak|Add|Build|Docs|Test|Perf|Feat|Remove|Security|Refactor|Update)\s*:\s*(.+)$/i', $line, $type_matches ) ) {
				continue;
			}

			$type = ucfirst( strtolower( $type_matches[1] ) );
        	$rest = trim( $type_matches[2] );

			// 2) Check for leading "Pro" (e.g. "Pro Module: ...") — remove it if present
			$is_pro = false;
			if ( preg_match( '/^Pro\s+(.*)$/i', $rest, $pro_matches ) ) {
				$is_pro = true;
				$rest   = trim( $pro_matches[1] );
			}

			// 3) Check if it starts with "Module:" (case-insensitive)
			if ( preg_match( '/^Module\s*:\s*(.+)$/i', $rest, $module_matches ) ) {
				$afterModule = $module_matches[1];

				// module name is everything up to the FIRST ':' — description is after that
				$pos = strpos( $afterModule, ':' );

				if ( $pos === false ) {
					$module_name = trim( $afterModule );
					$text_desc   = '';
				} else {
					$module_name = trim( substr( $afterModule, 0, $pos ) );
					$text_desc   = trim( substr( $afterModule, $pos + 1 ) );
				}

			} else {
				// No "Module:" present — this is a global line: the rest is the description
				$module_name = '';
				$text_desc   = $rest;
			}

			// Push to versions
			$versions[ $current_version ][] = array(
				'type'   => $type,
				'pro'    => (bool) $is_pro,
				'module' => $module_name,
				'text'   => $text_desc,
			);
		}

		return $versions;
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

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$opt_in_option['value'] = sanitize_text_field( wp_unslash( $_POST[WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in'] ?? '0' ) );

		update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_opt_in', $opt_in_option, false );
	}
	
	/**
	 * save_use_wp_submenu
	 *
	 * @return void
	 */
	private function save_use_wp_submenu() {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$use_wp_submenu = sanitize_text_field( wp_unslash( $_POST[WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu'] ?? '0' ) );
		update_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_use_wp_submenu', $use_wp_submenu, false );
	}

	/**
	 * Save the credentials
	 */
	private function save_credentials() {
		$new_settings = array();
		foreach ( wpmastertoolkit_ai_modules() as $ai_module_key => $ai_module ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing
			$new_settings[$ai_module_key] = sanitize_text_field( wp_unslash( $_POST['wpmastertoolkit_credentials_tab'][$ai_module_key] ?? '' ) );
		}

		update_option( 'wpmastertoolkit_credentials_tab', $new_settings );
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
		if( file_exists( WPMASTERTOOLKIT_PLUGIN_PATH . 'release.json' ) ) {
			$release_json = json_decode( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'release.json' ), true );
			if ( !empty( $release_json['sections']['changelog'] ) ) {
				return $release_json['sections']['changelog'];
			}
		}

		if( file_exists( WPMASTERTOOLKIT_PLUGIN_PATH . 'README.txt' ) ) {
			$readme_file = WPMASTERTOOLKIT_PLUGIN_PATH . 'README.txt';
			$readme_content = file_get_contents( $readme_file );
			
			if ( ! $readme_content ) return;
	
			$changelog = explode( '= ' . WPMASTERTOOLKIT_VERSION . ' =', $readme_content );
			$changelog = explode( '= ', $changelog[1] ?? '' );
			$changelog = $changelog[0] ?? '';
	
			if ( class_exists( 'Parsedown' ) ) {
				$Parsedown = new Parsedown();
				$changelog = $Parsedown->text( esc_html( $changelog ) );
			} else {
				$changelog = nl2br( esc_html( $changelog ) );
			}
	
			return $changelog;
		}

		return '';
	}

	/**
	 * Regenerate assets for all active modules
	 * Forces activation/deactivation hooks to regenerate module assets
	 * 
	 * @since	2.14.0
	 * @return	int|WP_Error Number of modules processed or WP_Error on failure
	 */
	public static function regenerate_module_assets() {
		try {
			// Get all active modules
			$db_options = get_option( WPMASTERTOOLKIT_PLUGIN_SETTINGS, array() );
			$options_data = wpmastertoolkit_options( 'normal' );

			// Require all module files
			WPMastertoolkit_Handle_options::require_once_all_options();

			$regenerated_count = 0;

			foreach ( $db_options as $option_key => $option_status ) {
				if ( $option_status == '1' ) {
					$option_data = $options_data[$option_key] ?? array();
					
					if ( class_exists( $option_key ) ) {
						// Check if the module has deactivate method
						if ( method_exists( $option_key, 'deactivate' ) ) {
							call_user_func( array( $option_key, 'deactivate' ) );
							$regenerated_count++;
						}

						// Check if the module has activate method
						if ( method_exists( $option_key, 'activate' ) ) {
							call_user_func( array( $option_key, 'activate' ) );
							$regenerated_count++;
						}
					}
				}
			}

			return $regenerated_count;

		} catch ( Exception $e ) {
			return new WP_Error( 'regenerate_assets_failed', $e->getMessage() );
		}
	}

	/**
	 * CRON handler to regenerate module assets
	 * 
	 * @since	2.14.0
	 */
	public function cron_regenerate_assets() {
		$result = self::regenerate_module_assets();

		if ( is_wp_error( $result ) ) {
			WPMastertoolkit_Logs::add_error( 
				esc_html__( 'Scheduled module assets regeneration failed: ', 'wpmastertoolkit' ) . $result->get_error_message()
			);
		} else {
			WPMastertoolkit_Logs::add_notice( 
				/* translators: %d: number of modules processed */
				esc_html__( 'Scheduled module assets regeneration completed.', 'wpmastertoolkit' ) . ' ' . sprintf( esc_html__( '%d modules processed.', 'wpmastertoolkit' ), $result )
			);
		}
	}

	/**
	 * AJAX handler to regenerate module assets
	 * 
	 * @since	2.14.0
	 */
	public function ajax_regenerate_assets() {
		// Verify nonce for security
		check_ajax_referer( 'wpmastertoolkit_settings', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpmastertoolkit' ) 
			) );
		}

		$result = self::regenerate_module_assets();

		if ( is_wp_error( $result ) ) {
			WPMastertoolkit_Logs::add_error( 
				esc_html__( 'Module assets regeneration failed: ', 'wpmastertoolkit' ) . $result->get_error_message()
			);

			wp_send_json_error( array(
				'message' => esc_html__( 'An error occurred while regenerating module assets. Please check the logs.', 'wpmastertoolkit' )
			) );
		}

		// Log the action
		WPMastertoolkit_Logs::add_notice( 
			/* translators: %d: number of modules processed */
			esc_html__( 'Module assets regenerated successfully.', 'wpmastertoolkit' ) . ' ' . sprintf( esc_html__( '%d modules processed.', 'wpmastertoolkit' ), $result )
		);

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of modules processed */
				esc_html__( 'Module assets regenerated successfully. %d modules processed.', 'wpmastertoolkit' ),
				$result
			)
		) );
	}

	/**
	 * Get system information for support
	 * 
	 * @since	2.16.0
	 * @return array System information data
	 */
	private static function get_system_info() {
		global $wpdb;

		$system_info = array();

		// PHP & Server Information
		$system_info['php_server'] = array(
			'php_version' => phpversion(),
			'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'N/A',
			'php_memory_limit' => ini_get('memory_limit'),
			'php_max_execution_time' => ini_get('max_execution_time'),
			'php_max_input_vars' => ini_get('max_input_vars'),
			'php_post_max_size' => ini_get('post_max_size'),
			'php_upload_max_filesize' => ini_get('upload_max_filesize'),
			'mysql_version' => $wpdb->db_version(),
			'server_ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'N/A',
		);

		// WordPress Information
		$system_info['wordpress'] = array(
			'version' => get_bloginfo('version'),
			'site_url' => get_site_url(),
			'home_url' => get_home_url(),
			'is_multisite' => is_multisite(),
			'wp_debug' => defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'undefined',
			'wp_debug_log' => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'undefined',
			'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'true' : 'false') : 'undefined',
			'script_debug' => defined('SCRIPT_DEBUG') ? (SCRIPT_DEBUG ? 'true' : 'false') : 'undefined',
			'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'undefined',
			'wp_max_memory_limit' => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'undefined',
			'table_prefix' => $wpdb->prefix,
		);

		// Active Theme
		$theme = wp_get_theme();
		$system_info['active_theme'] = array(
			'name' => $theme->get('Name'),
			'version' => $theme->get('Version'),
			'author' => $theme->get('Author'),
			'template' => $theme->get('Template'),
			'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : 'N/A',
		);

		// Active Plugins
		$active_plugins = get_option('active_plugins', array());
		$system_info['active_plugins'] = array();
		
		foreach ($active_plugins as $plugin) {
			$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
			$system_info['active_plugins'][] = array(
				'name' => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'author' => $plugin_data['Author'],
				'plugin_file' => $plugin,
			);
		}

		// MU Plugins
		$mu_plugins = get_mu_plugins();
		$system_info['mu_plugins'] = array();
		
		foreach ($mu_plugins as $plugin_file => $plugin_data) {
			$system_info['mu_plugins'][] = array(
				'name' => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'plugin_file' => $plugin_file,
			);
		}

		// WPMasterToolkit Configuration
		$system_info['wpmastertoolkit'] = array(
			'version' => WPMASTERTOOLKIT_VERSION,
			'is_pro' => wpmastertoolkit_is_pro(),
			'enabled_modules' => array(),
		);

		// Get enabled modules
		$db_options = get_option(WPMASTERTOOLKIT_PLUGIN_SETTINGS, array());
		$modules = wpmastertoolkit_options();
		
		foreach ($modules as $module_key => $module_data) {
			if (isset($db_options[$module_key]) && $db_options[$module_key] === '1') {
				$system_info['wpmastertoolkit']['enabled_modules'][] = array(
					'key' => $module_key,
					'name' => $module_data['name'],
					'pro' => $module_data['pro'] ?? false,
				);
			}
		}

		return $system_info;
	}

	/**
	 * Format system information as readable text
	 * 
	 * @since	2.16.0
	 * @param array $system_info System information array
	 * @return string Formatted text
	 */
	private static function format_system_info($system_info) {
		$output = "=== WPMasterToolkit System Information ===\n\n";
		
		// PHP & Server
		$output .= "--- PHP & Server ---\n";
		foreach ($system_info['php_server'] as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			$output .= "$label: $value\n";
		}
		
		// WordPress
		$output .= "\n--- WordPress ---\n";
		foreach ($system_info['wordpress'] as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			$value_display = is_bool($value) ? ($value ? 'Yes' : 'No') : $value;
			$output .= "$label: $value_display\n";
		}
		
		// Active Theme
		$output .= "\n--- Active Theme ---\n";
		foreach ($system_info['active_theme'] as $key => $value) {
			$label = ucwords(str_replace('_', ' ', $key));
			$output .= "$label: $value\n";
		}
		
		// Active Plugins
		$output .= "\n--- Active Plugins (" . count($system_info['active_plugins']) . ") ---\n";
		foreach ($system_info['active_plugins'] as $plugin) {
			$output .= "- {$plugin['name']} (v{$plugin['version']}) by {$plugin['author']}\n";
		}
		
		// MU Plugins
		if (!empty($system_info['mu_plugins'])) {
			$output .= "\n--- Must-Use Plugins (" . count($system_info['mu_plugins']) . ") ---\n";
			foreach ($system_info['mu_plugins'] as $plugin) {
				$output .= "- {$plugin['name']} (v{$plugin['version']})\n";
			}
		}
		
		// WPMasterToolkit
		$output .= "\n--- WPMasterToolkit Configuration ---\n";
		$output .= "Version: {$system_info['wpmastertoolkit']['version']}\n";
		$output .= "Pro: " . ($system_info['wpmastertoolkit']['is_pro'] ? 'Yes' : 'No') . "\n";
		$output .= "Enabled Modules (" . count($system_info['wpmastertoolkit']['enabled_modules']) . "):\n";
		foreach ($system_info['wpmastertoolkit']['enabled_modules'] as $module) {
			$pro_badge = $module['pro'] ? ' [PRO]' : '';
			$output .= "  - {$module['name']}{$pro_badge}\n";
		}
		
		$output .= "\n=== End System Information ===";
		
		return $output;
	}

	/**
	 * AJAX handler to get system information
	 * 
	 * @since	2.16.0
	 */
	public function ajax_get_system_info() {
		// Verify nonce for security
		check_ajax_referer('wpmastertoolkit_settings', 'nonce');

		// Check user capabilities
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array(
				'message' => esc_html__('You do not have permission to perform this action.', 'wpmastertoolkit')
			));
		}

		try {
			$system_info = self::get_system_info();
			$formatted_info = self::format_system_info($system_info);

			wp_send_json_success(array(
				'data' => $system_info,
				'formatted' => $formatted_info,
			));
		} catch (Exception $e) {
			wp_send_json_error(array(
				'message' => esc_html__('An error occurred while gathering system information.', 'wpmastertoolkit')
			));
		}
	}

}