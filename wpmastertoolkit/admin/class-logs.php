<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for handling the logs.
 *
 * @link       https://webdeclic.com
 * @since      2.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Logs {

	private $header_title;
	private $disable_form;

	/**
     * Initialize the class
	 * 
	 * @since 2.21.0
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Logs', 'wpmastertoolkit' );
    }

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since	2.21.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

		if ( $hook_suffix === 'wpmastertoolkit_page_wpmastertoolkit-logs' ) {
			$logs_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/pro/page-logs.asset.php' );
			wp_enqueue_style( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '.logs', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/pro/page-logs.css', array(), $logs_assets['version'], 'all' );
			wp_enqueue_script( WPMASTERTOOLKIT_PLUGIN_SETTINGS . '.logs', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/pro/page-logs.js', $logs_assets['dependencies'], $logs_assets['version'], true );
			wp_localize_script(
				WPMASTERTOOLKIT_PLUGIN_SETTINGS . '.logs',
				'wpmastertoolkit_logs_ajax',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpmastertoolkit_logs' ),
					'i18n'    => array(
						'no_log_files'          => esc_html__( 'No log files available.', 'wpmastertoolkit' ),
						'select_log_file'       => esc_html__( 'Select a log file', 'wpmastertoolkit' ),
						'error_loading_files'   => esc_html__( 'Error loading log files', 'wpmastertoolkit' ),
						'error_loading_content' => esc_html__( 'Error loading log content', 'wpmastertoolkit' ),
						'error_downloading'     => esc_html__( 'Error downloading log file', 'wpmastertoolkit' ),
						'select_file_first'     => esc_html__( 'Please select a log file first', 'wpmastertoolkit' ),
						'delete_older_than'     => esc_html__( 'Delete logs older than how many days?', 'wpmastertoolkit' ),
						'enter_valid_days'      => esc_html__( 'Please enter a valid number of days', 'wpmastertoolkit' ),
						'enter_number_days'     => esc_html__( 'Please enter a number of days', 'wpmastertoolkit' ),
						'confirm_delete_1'      => esc_html__( 'Are you sure you want to delete logs older than', 'wpmastertoolkit' ),
            			'confirm_delete_2'      => esc_html__( 'days?', 'wpmastertoolkit' ),
						'error_clearing'        => esc_html__( 'Error clearing logs', 'wpmastertoolkit' ),
						'new_entries'           => esc_html__( 'New entries detected', 'wpmastertoolkit' ),
            			'no_new_entries'        => esc_html__( 'No new entries', 'wpmastertoolkit' ),
					),
				)
			);
		}
	}

	/**
	 * Add the logs submenu page
	 *
	 * @since	2.21.0
	 */
	public function add_logs_submenu() {
		if ( wpmastertoolkit_is_pro() ) {
			add_submenu_page(
				'wp-mastertoolkit-settings',
				esc_html__( 'Logs', 'wpmastertoolkit' ),
				esc_html__( 'Logs', 'wpmastertoolkit' ),
				'manage_options',
				'wpmastertoolkit-logs',
				array( $this, 'render_logs_page' )
			);
		}
	}

	/**
	 * Render the manage logs page
	 *
	 * @since	2.21.0
	 */
	public function render_logs_page() {
		$this->disable_form = true;
		
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/pro/page-logs.php';
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * AJAX handler to get log files
	 * 
	 * @since	2.21.0
	 */
	public function ajax_get_log_files() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit_logs' ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid nonce. Please refresh the page and try again.', 'wpmastertoolkit' ),
			) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wpmastertoolkit' ) ) );
		}

		$log_dir = wpmastertoolkit_folders() . '/logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_send_json_success( array( 'files' => array() ) );
		}

		$files     = glob( $log_dir . '/*.log' );
		$log_files = array();

		foreach ( $files as $file ) {
			$filename = basename( $file );
			$filesize = filesize( $file );
			$modified = filemtime( $file );
			
			$log_files[] = array(
				'name'               => $filename,
				'path'               => $filename,
				'size'               => size_format( $filesize ),
				'size_bytes'         => $filesize,
				'modified'           => wp_date( 'Y-m-d H:i:s', $modified ),
				'modified_timestamp' => $modified,
			);
		}

		// Sort files by modified timestamp in descending order (newest first)
		usort( $log_files, function( $a, $b ) {
			return $b['modified_timestamp'] - $a['modified_timestamp'];
		} );

		wp_send_json_success( array( 'files' => $log_files ) );
	}

	/**
	 * AJAX handler to get log content
	 * 
	 * @since	2.21.0
	 */
	public function ajax_get_log_content() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit_logs' ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid nonce. Please refresh the page and try again.', 'wpmastertoolkit' ),
			) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wpmastertoolkit' ) ) );
		}

		$lines     = (int) sanitize_text_field( wp_unslash( $_POST['lines'] ?? '0' ) );
		$file_name = sanitize_text_field( wp_unslash( $_POST['file_name'] ?? '' ) );
		if ( empty( $file_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filename', 'wpmastertoolkit' ) ) );
		}

		$file_path = wpmastertoolkit_folders() . '/logs/' . $file_name;
		if ( ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Log file not found', 'wpmastertoolkit' ) ) );
		}

		$content = '';
		
		if ( $lines > 0 ) {
			$file       = new SplFileObject( $file_path, 'r' );
			$file->seek( PHP_INT_MAX );
			$last_line  = $file->key();
			$start_line = max( 0, $last_line - $lines );
			
			$lines_array = array();
			$file->seek( $start_line );

			while ( ! $file->eof() ) {
				$lines_array[] = $file->current();
				$file->next();
			}
			$content = implode( '', $lines_array );
		} else {
			$content = file_get_contents( $file_path );
		}

		wp_send_json_success( array(
			'content'   => $content,
			'file_name' => $file_name,
			'size'      => size_format( filesize( $file_path ) ),
		) );
	}

	/**
	 * AJAX handler to clear log files older than a certain number of days
	 * 
	 * @since	2.21.0
	 */
	public function ajax_clear_log_files() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit_logs' ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid nonce. Please refresh the page and try again.', 'wpmastertoolkit' ),
			) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wpmastertoolkit' ) ) );
		}

		$days = (int) sanitize_text_field( wp_unslash( $_POST['days'] ?? '30' ) );
		
		$deleted_count = 0;
		$log_dir       = wpmastertoolkit_folders() . '/logs';

		if ( file_exists( $log_dir ) ) {
			$files      = glob( $log_dir . '/*.log' );
			$time_limit = time() - ( $days * 24 * 60 * 60 );

			foreach ( $files as $file ) {
				if ( is_file( $file ) && filemtime( $file ) < $time_limit ) {
					if ( wp_delete_file( $file ) ) {
						$deleted_count++;
					}
				}
			}
    	}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d is the number of deleted log files */
				__( '%d log file(s) deleted successfully.', 'wpmastertoolkit' ),
				$deleted_count
			),
			'deleted_count' => $deleted_count,
		) );
	}

	/**
	 * AJAX handler to get log stream (new content since last check)
	 * 
	 * @since	2.21.0
	 */
	public function ajax_get_log_stream() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit_logs' ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid nonce. Please refresh the page and try again.', 'wpmastertoolkit' ),
			) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wpmastertoolkit' ) ) );
		}

		$file_name = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) );
		$last_size = (int) sanitize_text_field( wp_unslash( $_POST['last_size'] ?? '0' ) );

		if ( empty( $file_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid filename', 'wpmastertoolkit' ) ) );
		}

		$log_path = wpmastertoolkit_folders() . '/logs/' . $file_name;
		if ( ! file_exists( $log_path ) ) {

			wp_send_json_success( array( 
				'content'         => '',
				'current_size'    => 0,
				'has_new_content' => false,
			) );
		}

		$current_size = filesize( $log_path );
		$new_content  = '';

		if ( $current_size > $last_size ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$file = fopen( $log_path, 'r' );
			fseek( $file, $last_size );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$new_content = fread( $file, $current_size - $last_size );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $file );
		}

		wp_send_json_success( array(
			'content'         => $new_content,
			'current_size'    => $current_size,
			'has_new_content' => $current_size > $last_size,
		) );
	}

	/**
	 * add notice
	 * 
	 * @since    2.0.0
	 */
	public static function add_notice( $message ) {
		$message = 'NOTICE: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add error
	 * 
	 * @since    2.0.0
	 */
	public static function add_error( $message ) {
		$message = 'ERROR: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add warning
	 * 
	 * @since    2.21.0
	 */
	public static function add_warning( $message ) {
		$message = 'WARNING: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add debug
	 * 
	 * @since    2.21.0
	 */
	public static function add_debug( $message ) {
		$message = 'DEBUG: ' . $message;
		self::add_log( $message );
	}

	/**
	 * add log
	 * 
	 * @since    2.0.0
	 */
	public static function add_log( $message ) {
		$this_class = new self();
		add_filter( 'wpmastertoolkit/folders', array( $this_class, 'create_folders' ) );

		$file_path = wpmastertoolkit_folders() . '/logs/wpmtk-' . wp_date( 'Y-m-d' ) . '.log';
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$message   = '[' . wp_date( 'd-M-Y H:i:s' ) . '] ' . print_r( $message, true ) . "\n";

		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message, 3, $file_path );
	}

	/**
     * Create logs folders
     *
     * @since    2.0.0
     */
    public function create_folders( $folders ) {
        $folders['wpmastertoolkit']['logs'] = array();
        return $folders;
    }
}
