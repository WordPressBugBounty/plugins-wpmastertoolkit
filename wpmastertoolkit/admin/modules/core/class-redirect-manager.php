<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Redirect Manager
 * Description: Manage 301 redirects, fix broken links, and improve SEO by directing traffic to the correct pages.
 * @since 2.20.0
 */
class WPMastertoolkit_Redirect_Manager {

	const MODULE_ID = 'Redirect Manager';

	public $menu_slug = 'wp-mastertoolkit-settings-redirect-manager';
	
	private $option_id;
	private $add_redirect_nonce_action;
	private $import_export_nonce_action;
	private $redirects_table;
	private $logs_table;
	private $disable_form;
	private $header_title;
	private $import_csv_header;

	/**
	 * Invoke the hooks.
	 * 
	 * @since   2.20.0
	 */
	public function __construct() {
		$this->option_id                  = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_redirect_manager';
		$this->add_redirect_nonce_action  = $this->option_id . '_add_redirect';
		$this->import_export_nonce_action = $this->option_id . '_import_export';
		$this->redirects_table            = $this->option_id . '_redirects';
		$this->logs_table                 = $this->option_id . '_logs';
		$this->disable_form               = true;
		$this->import_csv_header          = array( 'URL From', 'URL To', 'Params', 'Model', 'Code', 'Regex', 'Internal', 'Status', 'Logs' );

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_redirect' ) );
        add_action( 'admin_init', array( $this, 'handle_import_export' ) );
        add_action( 'admin_init', array( $this, 'delete_redirect' ) );
        add_action( 'admin_init', array( $this, 'delete_log' ) );
        add_action( 'admin_init', array( $this, 'empty_logs_action' ) );
        add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );
		add_action( 'template_redirect', array( $this, 'do_php_redirect' ) );
		add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );
		add_filter( 'removable_query_args', array( $this, 'filter_removable_query_args') );
	}

	/**
     * Initialize the class
	 * 
	 * @since   2.20.0
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Redirect Manager', 'wpmastertoolkit' );
    }

	/**
	 * Activate the module
	 * 
	 * @since   2.20.0
	 */
	public static function activate() {

		$instance = new self();
		$redirects = $instance->get_redirects_from_db(array(
			'search' => '',
			'model'  => '1',
		));

		foreach ( $redirects as $redirect ) {
			$instance->add_to_htaccess( $redirect );
		}
	}

	/**
     * Deactivate the module
     *
	 * @since   2.20.0
     */
    public static function deactivate() {

		$instance  = new self();
		$redirects = $instance->get_redirects_from_db(array(
			'search' => '',
			'model'  => '1',
		));

		foreach ( $redirects as $redirect ) {
			$instance->remove_from_htaccess( $redirect['id'] );
		}
	}

	/**
     * Add a submenu
     * 
     * @since   2.20.0
     */
    public function add_submenu(){
        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            $this->menu_slug,
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Render the submenu
     * 
     * @since   2.20.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/redirect-manager.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/redirect-manager.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/redirect-manager.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
	 * Save a redirect.
	 *
	 * @since   2.20.0
	 */
	public function save_redirect() {

		if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $this->add_redirect_nonce_action ) ) {
            return;
        }

		$id       = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['id'] ?? '' ) );
		$url_from = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['url_from'] ?? '' ) );
		$url_to   = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['url_to'] ?? '' ) );
		$params   = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['params'] ?? '' ) );
		$model    = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['model'] ?? '' ) );
		$code     = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['code'] ?? '' ) );
		$regex    = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['regex'] ?? '' ) );
		$status   = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['status'] ?? '' ) );
		$logs     = sanitize_text_field( wp_unslash( $_POST[ $this->option_id ]['logs'] ?? '' ) );

		$home_url_parsed = wp_parse_url( home_url() );
		$url_from_parsed = wp_parse_url( trim( $url_from ) );
		$url_to_parsed   = wp_parse_url( trim( $url_to ) );

		$url_from = '/' . ltrim( $url_from_parsed['path'] ?? '', '/' );
		if ( ! empty( $url_from_parsed['query'] ) ) {
			$url_from .= '?' . $url_from_parsed['query'];
		}
		$internal = '0';

		if ( $home_url_parsed['host'] === $url_to_parsed['host'] ) {
			$url_to   = '/' . ltrim( $url_to_parsed['path'] ?? '', '/' );
			if ( ! empty( $url_to_parsed['query'] ) ) {
				$url_to .= '?' . $url_to_parsed['query'];
			}
			$internal = '1';
		}

		$allowed_models = $this->get_models();
		if ( $model === '' || ! array_key_exists( $model, $allowed_models ) || ! wpmastertoolkit_is_pro() ) {
			$model = '0';
		}

		$allowed_params = $this->get_params();
		if ( $params === '' || ! array_key_exists( $params, $allowed_params ) || '0' !== $model ) {
			$params = '0';
		}

		$allowed_codes = $this->get_codes();
		if ( $code === '' || ! array_key_exists( $code, $allowed_codes ) ) {
			$code = '301';
		}

		$allowed_statuses = $this->get_statuses();
		if ( $status === '' || ! array_key_exists( $status, $allowed_statuses ) ) {
			$status = '1';
		}

		$allowed_logs = $this->get_logs();
		if ( $logs === '' || ! array_key_exists( $logs, $allowed_logs ) || ! wpmastertoolkit_is_pro() || '0' !== $model ) {
			$logs = '0';
		}

		if ( $regex === '' || ( '0' !== $regex && '1' !== $regex ) ) {
			$regex = '0';
		}

		$redirect_data = array(
			'url_from' => $url_from,
			'url_to'   => $url_to,
			'params'   => $params,
			'model'    => $model,
			'code'     => $code,
			'regex'    => $regex,
			'internal' => $internal,
			'status'   => $status,
			'logs'     => $logs,
		);

		$this->maybe_create_tables();

		$redirect = array(
			'page'                    => $this->menu_slug,
			'wpmastertoolkit_view'    => 'add_redirect',
			'wpmastertoolkit_message' => 'redirect_failed',
		);

		if ( ! empty( $id ) ) {
			$redirect_data['id'] = $id;
			$result = $this->update_redirect( $redirect_data );
			if ( $result ) {
				$redirect = array(
					'page'                    => $this->menu_slug,
					'wpmastertoolkit_view'    => 'add_redirect',
					'wpmastertoolkit_id'      => rawurlencode( $id ),
					'wpmastertoolkit_message' => 'redirect_updated',
				);
			}
		} else {
			$result = $this->insert_redirect( $redirect_data );
			$redirect_data['id'] = $result;
			if ( $result ) {
				$redirect = array(
					'page'                    => $this->menu_slug,
					'wpmastertoolkit_view'    => 'add_redirect',
					'wpmastertoolkit_message' => 'redirect_added',
				);
			}
		}

		if ( '1' === $model ) {
			$this->add_to_htaccess( $redirect_data );
		} else {
			if ( ! empty( $id ) ) {
				$this->remove_from_htaccess( $id );
			}
		}

		wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle import and export of redirects.
	 *
	 * @since   2.20.0
	 */
	public function handle_import_export() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->import_export_nonce_action ) ) {
			return;
		}

		$submit = sanitize_text_field( wp_unslash( $_POST['submit'] ?? '' ) );
		switch ( $submit ) {
			case 'import_redirects':
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$file = $_FILES[ $this->option_id . '_import_file' ] ?? null;
				$this->import_redirects( $file );
			break;
			case 'export_redirects':
				$this->export_redirects();
			break;
			case 'export_template':
				$this->export_template();
			break;
		}
	}

	/**
	 * Import redirects from a csv file.
	 *
	 * @since   2.20.0
	 */
	public function import_redirects( $file ) {

		if ( empty( $file ) || $file['error'] !== UPLOAD_ERR_OK ) {
			wp_safe_redirect( add_query_arg( array(
				'page'                    => $this->menu_slug,
				'wpmastertoolkit_view'    => 'import_export',
				'wpmastertoolkit_message' => 'import_failed',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$file_type = wp_check_filetype( $file['name'] );
		if ( $file_type['ext'] !== 'csv' ) {
			wp_safe_redirect( add_query_arg( array(
				'page'                    => $this->menu_slug,
				'wpmastertoolkit_view'    => 'import_export',
				'wpmastertoolkit_message' => 'import_failed',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file['tmp_name'], 'r' );
		if ( $handle === false ) {
			wp_safe_redirect( add_query_arg( array(
				'page'                    => $this->menu_slug,
				'wpmastertoolkit_view'    => 'import_export',
				'wpmastertoolkit_message' => 'import_failed',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$header = fgetcsv( $handle );
		if ( $header === false || array_diff( $this->import_csv_header, $header ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			wp_safe_redirect( add_query_arg( array(
				'page'                    => $this->menu_slug,
				'wpmastertoolkit_view'    => 'import_export',
				'wpmastertoolkit_message' => 'import_failed',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$redirects_with_errors = array();
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {

			$errors        = array();
			$redirect_data = array_combine( $this->import_csv_header, $row );
			if ( $redirect_data === false ) {
				continue;
			}

			$redirect_data = array_map( 'trim', $redirect_data );
			$redirect_data = array(
				'url_from' => $redirect_data['URL From'] ?? '',
				'url_to'   => $redirect_data['URL To'] ?? '',
				'params'   => $redirect_data['Params'] ?? '0',
				'model'    => $redirect_data['Model'] ?? '0',
				'code'     => $redirect_data['Code'] ?? '301',
				'regex'    => $redirect_data['Regex'] ?? '0',
				'internal' => $redirect_data['Internal'] ?? '0',
				'status'   => $redirect_data['Status'] ?? '1',
				'logs'     => $redirect_data['Logs'] ?? '0',
			);

			$allowed_models = $this->get_models();
			if ( $redirect_data['model'] === '' || ! array_key_exists( $redirect_data['model'], $allowed_models ) || ! wpmastertoolkit_is_pro() ) {
				$redirect_data['model'] = '0';
				$errors[] = 'model_invalid';
			}

			$allowed_params = $this->get_params();
			if ( $redirect_data['params'] === '' || ! array_key_exists( $redirect_data['params'], $allowed_params ) || ( '0' !== $redirect_data['model'] && '0' !== $redirect_data['params'] ) ) {
				$redirect_data['params'] = '0';
				$errors[] = 'params_invalid';
			}

			$allowed_codes = $this->get_codes();
			if ( $redirect_data['code'] === '' || ! array_key_exists( $redirect_data['code'], $allowed_codes ) ) {
				$redirect_data['code'] = '301';
				$errors[] = 'code_invalid';
			}

			$allowed_statuses = $this->get_statuses();
			if ( $redirect_data['status'] === '' || ! array_key_exists( $redirect_data['status'], $allowed_statuses ) ) {
				$redirect_data['status'] = '1';
				$errors[] = 'status_invalid';
			}

			$allowed_logs = $this->get_logs();
			if ( $redirect_data['logs'] === '' || ! array_key_exists( $redirect_data['logs'], $allowed_logs ) || ! wpmastertoolkit_is_pro() || ( '0' !== $redirect_data['model'] && '0' !== $redirect_data['logs'] ) ) {
				$redirect_data['logs'] = '0';
				$errors[] = 'logs_invalid';
			}

			if ( $redirect_data['regex'] === '' || ( '0' !== $redirect_data['regex'] && '1' !== $redirect_data['regex'] ) ) {
				$redirect_data['regex'] = '0';
				$errors[] = 'regex_invalid';
			}

			$this->maybe_create_tables();
			$result = $this->insert_redirect( $redirect_data );

			if ( $result ) {
				$redirect_data['id'] = $result;

				if ( ! empty( $errors ) ) {
					$redirects_with_errors[$result] = $errors;
				}
			}

			if ( '1' === $redirect_data['model'] ) {
				$this->add_to_htaccess( $redirect_data );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		$redirect = array(
			'page'                    => $this->menu_slug,
			'wpmastertoolkit_view'    => 'import_export',
			'wpmastertoolkit_message' => 'import_completed',
		);

		if ( ! empty( $redirects_with_errors ) ) {
			$redirect['wpmastertoolkit_warnings'] = rawurlencode( wp_json_encode( $redirects_with_errors ) );
		}

		wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Export redirects as a csv file.
	 * 
	 * @since   2.20.0
	 */
	public function export_redirects() {

		$redirects = $this->get_redirects_from_db();
		if ( empty( $redirects ) ) {
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpmastertoolkit-redirects-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $this->import_csv_header );

		foreach ( $redirects as $redirect ) {
			fputcsv( $output, array(
				$redirect['url_from'],
				$redirect['url_to'],
				$redirect['params'],
				$redirect['model'],
				$redirect['code'],
				$redirect['regex'],
				$redirect['internal'],
				$redirect['status'],
				$redirect['logs'],
			) );
		}
		exit;
	}

	/**
	 * Export a template for redirects.
	 *
	 * @since   2.20.0
	 */
	public function export_template() {

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpmastertoolkit-redirects-template.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $this->import_csv_header );
		fputcsv( $output, array( '/old-page/', '/new-page/', '0', '0', '301', '0', '1', '1', '0' ) );
		exit;
	}

	/**
	 * Delete a redirect.
	 * 
	 * @since   2.20.0
	 */
	public function delete_redirect() {

		if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit-delete-redirect' ) ) {
            return;
        }

		$id = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_id'] ?? '' ) );
		if ( empty( $id ) ) {
			return;
		}

		$redirect = array(
			'page'                    => $this->menu_slug,
			'wpmastertoolkit_message' => 'redirect_delete_failed',
		);

		$result = $this->delete_redirects( array( $id ) );
		if ( $result ) {

			$this->remove_from_htaccess( $id );

			$redirect = array(
				'page'                    => $this->menu_slug,
				'wpmastertoolkit_message' => 'redirect_deleted',
			);
		}

		wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Handle bulk actions from the redirects list table.
	 *
	 * @since   2.20.0
	 */
	public function handle_bulk_actions() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		if ( $page !== $this->menu_slug ) {
			return;
		}

		// WP_List_Table submits action from top or bottom dropdown.
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '-1' ) );
		if ( '-1' === $action ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action2'] ?? '-1' ) );
		}

		if ( '-1' === $action || empty( $action ) ) {
			return;
		}

		check_admin_referer( 'bulk-wpmastertoolkit-redirects' );

		// IDs submitted by the checkboxes (name="redirect[]").
		$ids = array_filter( array_map( 'absint', (array) ( $_GET['redirect'] ?? array() ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		$message  = 'bulk_action_failed';
		$redirect = array(
			'page' => $this->menu_slug,
		);

		switch ( $action ) {
			case 'bulk_redirects_delete':

				$result = $this->delete_redirects( $ids );
				if ( $result ) {
					foreach ( $ids as $id ) {
						$this->remove_from_htaccess( $id );
					}

					$message = 'bulk_redirects_deleted';
				}

				$redirect = array(
					'page'                    => $this->menu_slug,
					'wpmastertoolkit_message' => $message,
				);

			break;
			case 'bulk_redirects_enable':

				$result = $this->update_redirects_status( $ids, '1' );
				if ( $result ) {
					$message = 'bulk_redirects_enabled';
				}

				$redirect = array(
					'page'                    => $this->menu_slug,
					'wpmastertoolkit_message' => $message,
				);

			break;
			case 'bulk_redirects_disable':

				$result = $this->update_redirects_status( $ids, '0' );
				if ( $result ) {
					$message = 'bulk_redirects_disabled';
				}

				$redirect = array(
					'page'                    => $this->menu_slug,
					'wpmastertoolkit_message' => $message,
				);

			break;
			case 'bulk_logs_delete':

				$result = $this->delete_logs( $ids );
				if ( $result ) {
					$message = 'bulk_logs_deleted';
				}

				$redirect = array(
					'page'                    => $this->menu_slug,
					'wpmastertoolkit_view'    => 'logs',
					'wpmastertoolkit_message' => $message,
				);
			break;
		}

		wp_safe_redirect( add_query_arg(
			$redirect,
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Delete a log.
	 * 
	 * @since   2.20.0
	 */
	public function delete_log() {

		if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit-delete-log' ) ) {
            return;
        }

		$id = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_id'] ?? '' ) );
		if ( empty( $id ) ) {
			return;
		}

		$redirect = array(
			'page'                    => $this->menu_slug,
			'wpmastertoolkit_view'    => 'logs',
			'wpmastertoolkit_message' => 'log_delete_failed',
		);

		$result = $this->delete_logs( array( $id ) );
		if ( $result ) {
			$redirect = array(
				'page'                    => $this->menu_slug,
				'wpmastertoolkit_view'    => 'logs',
				'wpmastertoolkit_message' => 'log_deleted',
			);
		}

		wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Empty all logs.
	 *
	 * @since   2.20.0
	 */
	public function empty_logs_action() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_action'] ?? '' ) );
		if ( 'empty_logs' !== $action ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wpmastertoolkit-empty-logs' ) ) {
			return;
		}

		$redirect = array(
			'page'                    => $this->menu_slug,
			'wpmastertoolkit_view'    => 'logs',
			'wpmastertoolkit_message' => 'logs_empty_failed',
		);

		$result = $this->empty_all_logs();
		if ( $result ) {
			$redirect['wpmastertoolkit_message'] = 'logs_emptied';
		}

		wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**	Filters the list of query arguments which get removed from admin area URLs in WordPress.
	 *
	 * @since   2.20.0
	 */
	public function filter_removable_query_args( array $args ) {
		return array_merge( $args, array(
			'wpmastertoolkit_message',
			'wpmastertoolkit_warnings',
		) );
	}

	/**
	 * Insert a redirect into the database.
	 *
	 * @since   2.20.0
	 */
	public function insert_redirect( $redirect_data ) {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_redirects,
			array(
				'url_from' => $redirect_data['url_from'],
				'url_to'   => $redirect_data['url_to'],
				'params'   => $redirect_data['params'],
				'model'    => $redirect_data['model'],
				'code'     => $redirect_data['code'],
				'regex'    => $redirect_data['regex'],
				'internal' => $redirect_data['internal'],
				'status'   => $redirect_data['status'],
				'logs'     => $redirect_data['logs'],
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
		);

		return $result !== false ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a redirect in the database.
	 * 
	 * @since   2.20.0
	 */
	public function update_redirect( $redirect_data ) {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_redirects,
			array(
				'url_from' => $redirect_data['url_from'],
				'url_to'   => $redirect_data['url_to'],
				'params'   => $redirect_data['params'],
				'model'    => $redirect_data['model'],
				'code'     => $redirect_data['code'],
				'regex'    => $redirect_data['regex'],
				'internal' => $redirect_data['internal'],
				'status'   => $redirect_data['status'],
				'logs'     => $redirect_data['logs'],
			),
			array( 'id' => $redirect_data['id'] ),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete multiple redirects by ID.
	 *
	 * @since   2.20.0
	 */
	public function delete_redirects( array $ids ) {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;
		$placeholders    = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_redirects} WHERE id IN ({$placeholders})", $ids ) );

		return $result !== false;
	}

	/**
	 * Update the status of multiple redirects by ID.
	 * 
	 * @since   2.20.0
	 */
	public function update_redirects_status( array $ids, $status ) {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;
		$placeholders    = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table_redirects} SET status = %d WHERE id IN ({$placeholders})", array_merge( array( $status ), $ids ) ) );

		return $result !== false;
	}

	/**
	 * Get all redirects from the database.
	 *
	 * @since   2.20.0
	 */
	public function get_redirects_from_db( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'search' => '',
			'model'  => null,
		) );

		$search = $args['search'];
		$model  = $args['model'];

		$table_redirects = $wpdb->prefix . $this->redirects_table;

		$where  = array();
		$values = array();

		if ( ! empty( $search ) ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(url_from LIKE %s OR url_to LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		if ( $model !== null ) {
			$where[]  = 'model = %d';
			$values[] = $model;
		}

		$sql = "SELECT * FROM {$table_redirects}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY id DESC';

		if ( ! empty( $values ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $results ?? array();
	}

	/**
	 * Get a redirect by ID from the database.
	 * 
	 * @since   2.20.0
	 */
	public function get_redirect( $redirect_id ) {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table_redirects, $redirect_id ), ARRAY_A );

		return $result;
	}

	/**
	 * Get an exact-match redirect by URL from the database.
	 *
	 * @since   2.22.0
	 */
	public function get_redirect_by_url( $request_path, $full_request ) {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;
		$like_pattern    = $wpdb->esc_like( $request_path ) . '?%';

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE (url_from IN (%s, %s) OR url_from LIKE %s) AND regex = 0 AND model = 0 AND status = 1 ORDER BY id DESC",
				$table_redirects,
				$request_path,
				$full_request,
				$like_pattern
			),
			ARRAY_A
		);

		return $results ?? array();
	}

	/**
	 * Get all regex redirects for PHP model from the database.
	 *
	 * @since   2.22.0
	 */
	public function get_regex_redirects() {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( "SELECT * FROM {$table_redirects} WHERE regex = 1 AND model = 0 AND status = 1 ORDER BY id DESC", ARRAY_A );

		return $results ?? array();
	}

	/**
	 * Insert a log into the database.
	 *
	 * @since   2.20.0
	 */
	public function insert_log_redirect( $redirect_data ) {
		global $wpdb;

		if ( empty( $redirect_data['logs'] ) || '0' === $redirect_data['logs'] ) {
			return false;
		}

		$table_logs = $wpdb->prefix . $this->logs_table;

		$url_from = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( '1' === $redirect_data['internal'] ) {
			$url_to = home_url( $redirect_data['url_to'] );
		} else {
			$url_to = $redirect_data['url_to'];
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_logs,
			array(
				'url_from' => $url_from,
				'url_to'   => $url_to,
				'agent'    => wpmastertoolkit_get_current_user_agent(),
				'ip'       => wpmastertoolkit_get_current_ip(),
				'date'     => time(),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return $result !== false ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete multiple logs by ID.
	 * 
	 * @since   2.20.0
	 */
	public function delete_logs( array $ids ) {
		global $wpdb;

		$table_logs   = $wpdb->prefix . $this->logs_table;
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_logs} WHERE id IN ({$placeholders})", $ids ) );

		return $result !== false;
	}

	/**
	 * Delete all logs from the database.
	 *
	 * @since   2.20.0
	 */
	public function empty_all_logs() {
		global $wpdb;

		$table_logs = $wpdb->prefix . $this->logs_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query( "TRUNCATE TABLE {$table_logs}" );

		return $result !== false;
	}

	/**
	 * Get logs from the database.
	 * 
	 * @since   2.20.0
	 */
	public function get_logs_from_db( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'search' => '',
		) );

		$search = $args['search'];

		$table_logs = $wpdb->prefix . $this->logs_table;

		$where  = array();
		$values = array();

		if ( ! empty( $search ) ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(url_from LIKE %s OR url_to LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$sql = "SELECT * FROM {$table_logs}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY id DESC';

		if ( ! empty( $values ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $results ?? array();
	}

	/**
	 * Maybe create database tables.
	 * 
	 * @since   2.20.0
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$table_redirects = $wpdb->prefix . $this->redirects_table;
		$table_logs      = $wpdb->prefix . $this->logs_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$table_redirects_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_redirects ) ) === $table_redirects;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$table_logs_exists      = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_logs ) ) === $table_logs;

		if ( ! $table_redirects_exists || ! $table_logs_exists ) {
			$this->create_tables();
		}
	}

	/**
	 * Create database tables
	 * 
	 * @since   2.20.0
	 */
	private function create_tables() {
		global $wpdb;
		
		$sql             = array();
		$charset_collate = $wpdb->get_charset_collate();
		$table_redirects = $wpdb->prefix . $this->redirects_table;
		$table_logs      = $wpdb->prefix . $this->logs_table;
		
		// Redirects table
		$sql[] = "CREATE TABLE IF NOT EXISTS {$table_redirects} (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			url_from varchar(250) NOT NULL,
			url_to varchar(250) NOT NULL,
			params tinyint(1) NOT NULL DEFAULT 0,
			model tinyint(1) NOT NULL DEFAULT 0,
			code smallint(4) UNSIGNED NOT NULL DEFAULT 301,
			regex tinyint(1) NOT NULL DEFAULT 0,
			internal tinyint(1) NOT NULL DEFAULT 1,
			status tinyint(1) NOT NULL DEFAULT 1,
			logs tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			INDEX idx_url_from (url_from)
		) {$charset_collate};";

		// Logs table
		$sql[] = "CREATE TABLE IF NOT EXISTS {$table_logs} (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			url_from varchar(250) NOT NULL,
			url_to varchar(250) NOT NULL,
			agent varchar(255) NOT NULL,
			ip varchar(100) NOT NULL,
			date int(11) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id)
		) {$charset_collate};";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Add a redirect rule to the .htaccess file.
	 * 
	 * @since   2.20.0
	 */
	public function add_to_htaccess( $redirect_data ) {
		global $is_apache;

		if ( $is_apache && wpmastertoolkit_is_pro() ) {

			$redirect_id = $redirect_data['id'] ?? '';
			if ( empty( $redirect_id ) ) {
				return;
			}

			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
			WPMastertoolkit_Htaccess::add( $this->get_raw_content_htaccess( $redirect_data ), self::MODULE_ID . '-' . $redirect_id );
		}
	}

	/**
	 * Remove a redirect rule from the .htaccess file.
	 * 
	 * @since   2.20.0
	 */
	public function remove_from_htaccess( $redirect_id ) {
		global $is_apache;

		if ( $is_apache ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
			WPMastertoolkit_Htaccess::remove( self::MODULE_ID . '-' . $redirect_id );
		}
	}

	/**
	 * Get the raw content for the .htaccess rule.
	 * 
	 * @since   2.20.0
	 */
	public function get_raw_content_htaccess( $redirect_data ) {
		$url_from = wp_parse_url( $redirect_data['url_from'], PHP_URL_PATH );
		$url_from = ltrim( $url_from ?? $redirect_data['url_from'], '/' );
		$url_to   = wp_parse_url( $redirect_data['url_to'], PHP_URL_PATH );
		$url_to   = ltrim( $url_to ?? $redirect_data['url_to'], '/' );
		$code     = $redirect_data['code'];

		if ( '1' === $redirect_data['regex'] ) {
			return "RedirectMatch {$code} ^/{$url_from}$ /{$url_to}";
		}

		return "Redirect {$code} /{$url_from} /{$url_to}";
	}

	/**
	 * Runs on template_redirect and performs PHP-based redirects for model = 0 (WordPress).
	 *
	 * @since   2.20.0
	 */
	public function do_php_redirect() {

		if ( is_admin() ) {
			return;
		}

		$request_uri   = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$request_path  = wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path  = '/' . ltrim( $request_path ?? '', '/' );
		$request_query = wp_parse_url( $request_uri, PHP_URL_QUERY );
		$full_request  = $request_path . ( $request_query ? '?' . $request_query : '' );

		// 1. Try exact-match redirects directly from DB (no loop needed).
		$exact_redirects = $this->get_redirect_by_url( $request_path, $full_request );

		foreach ( $exact_redirects as $redirect ) {
			$url_from      = $redirect['url_from'];
			$url_from_path = wp_parse_url( $url_from, PHP_URL_PATH ) ?? $url_from;
			$url_to        = $redirect['url_to'];
			$code          = (int) $redirect['code'];
			$params        = (string) $redirect['params'];
			$internal      = '1' === (string) $redirect['internal'];
			$target        = $internal ? home_url( $url_to ) : $url_to;
			$matched       = false;

			if ( '1' === $params ) {
				// Ignore all query parameters.
				$matched = ( $request_path === $url_from_path );
			} elseif ( '2' === $params ) {
				// Ignore & pass parameters to target.
				$matched = ( $request_path === $url_from_path );
				if ( $matched && ! empty( $request_query ) ) {
					$target = add_query_arg( wp_parse_args( $request_query ), $target );
				}
			} else {
				// Exact match including any query parameters (order-independent).
				$url_from_query = wp_parse_url( $url_from, PHP_URL_QUERY );

				if ( empty( $url_from_query ) ) {
					// No query string in url_from: match on path alone.
					$matched = ( $request_path === $url_from_path );
				} else {
					// Compare query parameters regardless of order.
					parse_str( $url_from_query, $url_from_params );
					parse_str( $request_query ?? '', $request_params );
					ksort( $url_from_params );
					ksort( $request_params );
					$matched = ( $request_path === $url_from_path && $url_from_params === $request_params );
				}
			}

			if ( $matched ) {
				$this->maybe_create_tables();
				$this->insert_log_redirect( $redirect );

				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				wp_redirect( esc_url_raw( $target ), $code );
				exit;
			}
		}

		// 2. Fall back to regex redirects (must loop, but typically a small set).
		$regex_redirects = $this->get_regex_redirects();

		foreach ( $regex_redirects as $redirect ) {
			$url_from = $redirect['url_from'];
			$url_to   = $redirect['url_to'];
			$code     = (int) $redirect['code'];
			$internal = '1' === (string) $redirect['internal'];

			if ( preg_match( '#' . $url_from . '#i', $full_request, $matches ) ) {
				$target = preg_replace( '#' . $url_from . '#i', $internal ? home_url( $url_to ) : $url_to, $full_request );

				$this->maybe_create_tables();
				$this->insert_log_redirect( $redirect );

				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				wp_redirect( esc_url_raw( $target ), $code );
				exit;
			}
		}
	}

	/**
	 * Add code snippets for Nginx configuration.
	 * 
	 * @since   2.20.0
	 */
	public function nginx_code_snippets( $code_snippets ) {
		global $is_nginx;

		if ( $is_nginx && wpmastertoolkit_is_pro() ) {
			$redirects = $this->get_redirects_from_db( array(
				'search' => '',
				'model'  => '2',
			) );

			foreach ( $redirects as $redirect ) {
				$url_from = wp_parse_url( $redirect['url_from'], PHP_URL_PATH );
				$url_from = '/' . ltrim( $url_from ?? $redirect['url_from'], '/' );
				$url_to   = wp_parse_url( $redirect['url_to'], PHP_URL_PATH );
				$url_to   = '/' . ltrim( $url_to ?? $redirect['url_to'], '/' );
				$code     = (int) $redirect['code'];

				if ( '1' === $redirect['regex'] ) {
					// Regex redirect: use a location block with return so any status code is supported.
					$code_snippets[] = "location ~ {$url_from} {\n    return {$code} {$url_to};\n}";
				} else {
					// Exact match: location = is the most efficient; return supports any status code.
					$code_snippets[] = "location = {$url_from} {\n    return {$code} {$url_to};\n}";
				}
			}
		}

		return $code_snippets;
	}

	/**
	 * Initialises and returns the list table for redirects.
	 *
	 * @since    2.20.0
	 */
	public function get_redirects_list_table() {
		static $table = null;

		if ( ! $table ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/redirect-manager/class-redirects-list-table.php';
			$table = new WPMastertoolkit_Redirect_Manager_Redirects_List_Table();
			$table->prepare_items();
		}

		return $table;
	}

	/**
	 * Initialises and returns the list table for logs.
	 *
	 * @since    2.20.0
	 */
	public function get_logs_list_table() {
		static $logs_table = null;

		if ( ! $logs_table ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/redirect-manager/class-logs-list-table.php';
			$logs_table = new WPMastertoolkit_Redirect_Manager_Logs_List_Table();
			$logs_table->prepare_items();
		}

		return $logs_table;
	}

	/**
	 * Renders the redirects view.
	 * 
	 * @since 2.20.0
	 */
	public function get_views() {

		$views   = '';
		$default = 'redirects';
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view    = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_view'] ?? $default ) );
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id      = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_id'] ?? '' ) );
		$url     = admin_url( 'admin.php?page=' . $this->menu_slug );

		$text_add_redirect = __( 'Add Redirect', 'wpmastertoolkit' );
		if ( ! empty( $id ) ) {
			$text_add_redirect = __( 'Edit Redirect', 'wpmastertoolkit' );
		}

		$types   = array(
			'redirects'     => array(
				'label'    => __( 'Redirects', 'wpmastertoolkit' ),
				'disabled' => false,
			),
			'add_redirect'  => array(
				'label'    => $text_add_redirect,
				'disabled' => false,
			),
			'logs'          => array(
				'label'    => __( 'Logs', 'wpmastertoolkit' ),
				'disabled' => false,
			),
			'import_export' => array(
				'label'    => __( 'Import/Export', 'wpmastertoolkit' ),
				'disabled' => false,
			),
		);

		if ( ! wpmastertoolkit_is_pro() ) {
			$types['logs'] = array(
				'label'    => __( 'Logs', 'wpmastertoolkit' ) . '<span class="pro">PRO</span>',
				'disabled' => true,
			);
			$types['import_export'] = array(
				'label'    => __( 'Import/Export', 'wpmastertoolkit' ) . '<span class="pro">PRO</span>',
				'disabled' => true,
			);
		}

		$views .= '<ul class="subsubsub">';
		foreach ( $types as $key => $type ) {

			$views .= '<li>';

			$link = $url;
			if ( $key !== $default ) {
				$link = add_query_arg( 'wpmastertoolkit_view', $key, $url );
			}

			if ( $type['disabled'] ) {
				$views .= sprintf(
					'<span class="disabled">%s</span>',
					wp_kses_post( $type['label'] ),
				);
			} else {
				$views .= sprintf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( $link ),
					$view === $key ? ' class="current"' : '',
					wp_kses_post( $type['label'] ),
				);
			}

			if ( $key !== array_key_last( $types ) ) {
				$views .= ' | ';
			}

			$views .= '</li>';
		}
		$views .= '</ul>';

		echo wp_kses_post( $views );
	}

	/**
	 * Returns an array of statuses for redirects.
	 *
	 * @since 2.20.0
	 */
	public function get_statuses() {
		return array(
			'1' => esc_html__( 'Enabled', 'wpmastertoolkit' ),
			'0' => esc_html__( 'Disabled', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns an array of codes for redirects.
	 *
	 * @since 2.20.0
	 */
	public function get_codes() {
		return array(
			'301' => esc_html__( '301 Moved Permanently', 'wpmastertoolkit' ),
			'302' => esc_html__( '302 Found', 'wpmastertoolkit' ),
			'303' => esc_html__( '303 See Other', 'wpmastertoolkit' ),
			'304' => esc_html__( '304 Not Modified', 'wpmastertoolkit' ),
			'307' => esc_html__( '307 Temporary Redirect', 'wpmastertoolkit' ),
			'308' => esc_html__( '308 Permanent Redirect', 'wpmastertoolkit' ),
			'400' => esc_html__( '400 Bad Request', 'wpmastertoolkit' ),
			'401' => esc_html__( '401 Unauthorized', 'wpmastertoolkit' ),
			'403' => esc_html__( '403 Forbidden', 'wpmastertoolkit' ),
			'404' => esc_html__( '404 Not Found', 'wpmastertoolkit' ),
			'410' => esc_html__( '410 Gone', 'wpmastertoolkit' ),
			'418' => esc_html__( '418 I\'m a teapot', 'wpmastertoolkit' ),
			'451' => esc_html__( '451 Unavailable For Legal Reasons', 'wpmastertoolkit' ),
			'500' => esc_html__( '500 Internal Server Error', 'wpmastertoolkit' ),
			'501' => esc_html__( '501 Not Implemented', 'wpmastertoolkit' ),
			'502' => esc_html__( '502 Bad Gateway', 'wpmastertoolkit' ),
			'503' => esc_html__( '503 Service Unavailable', 'wpmastertoolkit' ),
			'504' => esc_html__( '504 Gateway Timeout', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns an array of models for redirects.
	 *
	 * @since 2.20.0
	 */
	public function get_models() {
		return array(
			'0' => esc_html__( 'WordPress (PHP)', 'wpmastertoolkit' ),
			'1' => esc_html__( 'Apache', 'wpmastertoolkit' ),
			'2' => esc_html__( 'Nginx', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns an array of params for redirects.
	 *
	 * @since 2.20.0
	 */
	public function get_params() {
		return array(
			'0' => esc_html__( 'Exact match in any order', 'wpmastertoolkit' ),
			'1' => esc_html__( 'Ignore all parameters', 'wpmastertoolkit' ),
			'2' => esc_html__( 'Ignore & pass parameters to the target', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns an array of logs for redirects.
	 *
	 * @since 2.20.0
	 */
	public function get_logs() {
		return array(
			'0' => esc_html__( 'Disabled', 'wpmastertoolkit' ),
			'1' => esc_html__( 'Enabled', 'wpmastertoolkit' ),
		);
	}

	/**
	 * Returns a flattened array of redirects.
	 *
	 * @since   2.20.0
	 */
	public function get_items_redirects() {

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$search	      = '';
		if ( ! empty( $search_query ) && is_string( $search_query ) ) {
			$search = strtolower( $search_query );
		}

		$db_items = $this->get_redirects_from_db( array(
			'search' => $search,
		) );

		$items = array();
		foreach ( $db_items as $item ) {
			
			$item['url_from_full'] = home_url( $item['url_from'] );

			if ( '1' === $item['internal'] ) {
				$item['url_to_full'] = home_url( $item['url_to'] );
			} else {
				$item['url_to_full'] = $item['url_to'];
			}
			
			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Returns a flattened array of logs.
	 *
	 * @since 2.20.0
	 */
	public function get_items_logs() {

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$search	      = '';
		if ( ! empty( $search_query ) && is_string( $search_query ) ) {
			$search = strtolower( $search_query );
		}

		$db_items = $this->get_logs_from_db( array(
			'search' => $search,
		) );

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$items       = array();
		foreach ( $db_items as $item ) {
			$date = (int) $item['date'] ?? 0;
			$item['date'] = gmdate( $date_format, $date );
			$items[] = $item;
		}

		return $items;
	}

	/**
     * Add the submenu content
     * 
     * @since   2.20.0
     */
    private function submenu_content() {

		$default = 'redirects';
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view    = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_view'] ?? $default ) );
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_message'] ?? '' ) );
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$warnings = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_warnings'] ?? '' ) );

		$messages    = $this->notice_messages();
		$message_arr = array();

		if ( isset( $messages[ $message ] ) ) {
			$message_arr = $messages[ $message ];
		}

		?><div class="wp-mastertoolkit__section"><?php
		if ( ! empty( $message_arr ) ) {
			?>
			<div class="wp-mastertoolkit__section__notice show <?php echo esc_attr( $message_arr[1] ); ?>">
				<p class="wp-mastertoolkit__section__notice__message">
					<?php echo esc_html( $message_arr[0] ); ?>
				</p>
			</div>
			<?php
		}

		if ( ! empty( $warnings ) ) {
			$warnings_messages = $this->warnings_messages();
			$warnings          = json_decode( $warnings, true );

			foreach ( $warnings as $redirect_id => $redirect_warnings ) {
				?>
				<div class="wp-mastertoolkit__section__notice show warning">
					<p class="wp-mastertoolkit__section__notice__message">
						<?php echo wp_kses_post( sprintf(
							// translators: %s is the redirect ID.
							__( 'Redirect with ID %s has warnings:', 'wpmastertoolkit' ),
							'<strong>'.$redirect_id.'</strong>'
							) );
						?>
					</p>
					<ul>
						<?php foreach ( $redirect_warnings as $warning ) : ?>
							<li><?php echo wp_kses_post( $warnings_messages[ $warning ] ?? '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
			}
		}

		?><div class="wp-mastertoolkit__section__body"><?php

		$this->get_views();

		switch ( $view ) {
			case 'redirects':
				$this->render_redirects_view();
			break;
			case 'add_redirect':
				$this->render_add_redirect_view();
			break;
			case 'logs':
				$this->render_logs_view();
			break;
			case 'import_export':
				$this->render_import_export_view();
			break;
		}

		?></div></div><?php
    }

	/**
	 * Returns an array of notice messages.
	 *
	 * @since 2.20.0
	 */
	private function notice_messages() {
		return array(
			'redirect_added' => array(
				__( 'Redirect added successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'redirect_updated' => array(
				__( 'Redirect updated successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'redirect_failed' => array(
				__( 'Failed to add redirect. Please try again.', 'wpmastertoolkit' ),
				'error',
			),
			'redirect_deleted' => array(
				__( 'Redirect deleted successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'redirect_delete_failed' => array(
				__( 'Failed to delete redirect. Please try again.', 'wpmastertoolkit' ),
				'error',
			),
			'bulk_redirects_deleted' => array(
				__( 'Selected redirects deleted successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'bulk_redirects_enabled' => array(
				__( 'Selected redirects enabled successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'bulk_redirects_disabled' => array(
				__( 'Selected redirects disabled successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'bulk_logs_deleted' => array(
				__( 'Selected logs deleted successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'bulk_action_failed' => array(
				__( 'Bulk action failed. Please try again.', 'wpmastertoolkit' ),
				'error',
			),
			'log_deleted' => array(
				__( 'Log deleted successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'log_delete_failed' => array(
				__( 'Failed to delete log. Please try again.', 'wpmastertoolkit' ),
				'error',
			),
			'logs_emptied' => array(
				__( 'All logs emptied successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'logs_empty_failed' => array(
				__( 'Failed to empty logs. Please try again.', 'wpmastertoolkit' ),
				'error',
			),
			'import_completed' => array(
				__( 'Import completed successfully.', 'wpmastertoolkit' ),
				'success',
			),
			'import_failed' => array(
				__( 'Import failed. Please check the file and try again.', 'wpmastertoolkit' ),
				'error',
			),
		);
	}

	/**
	 * Returns an array of warning messages for redirects.
	 * 
	 * @since 2.20.0
	 */
	private function warnings_messages() {
		return array(
			'model_invalid'  => sprintf(
				// translators: %s is the model name (e.g. "Apache").
				__( 'Model changed to %s.', 'wpmastertoolkit' ),
				'<strong>'.$this->get_models()[0].'</strong>'
			),
			'params_invalid' => sprintf(
				// translators: %s is the query parameters.
				__( 'Query parameters changed to %s.', 'wpmastertoolkit' ),
				'<strong>'.$this->get_params()[0].'</strong>'
			),
			'code_invalid'   => sprintf(
				// translators: %s is the HTTP status code.
				__( 'Code changed to %s.', 'wpmastertoolkit' ),
				'<strong>'.$this->get_codes()['301'].'</strong>'
			),
			'status_invalid' => sprintf(
				// translators: %s is the status.
				__( 'Status changed to %s.', 'wpmastertoolkit' ),
				'<strong>'.$this->get_statuses()['1'].'</strong>'
			),
			'logs_invalid'   => sprintf(
				// translators: %s is the logs.
				__( 'Logs changed to %s.', 'wpmastertoolkit' ),
				'<strong>'.$this->get_logs()['0'].'</strong>'
			),
			'regex_invalid'  => sprintf(
				// translators: %s is the regex.
				__( 'Regex changed to %s.', 'wpmastertoolkit' ),
				'<strong>Disabled</strong>'
			),
		);
	}

	/**
	 * Renders the redirects view.
	 *
	 * @since 2.20.0
	 */
	private function render_redirects_view() {
		$table = $this->get_redirects_list_table();

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

		?>
			<form method="get" action="admin.php">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_slug ); ?>"/>
				
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'wpmastertoolkit' ); ?>">
				</p>

				<?php $table->display(); ?>
			</form>
        <?php
	}

	/**
	 * Renders the add redirect view.
	 *
	 * @since 2.20.0
	 */
	private function render_add_redirect_view() {
		$params = $this->get_params();
		$models = $this->get_models();
		$codes  = $this->get_codes();
		$logs   = $this->get_logs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_id     = sanitize_text_field( wp_unslash( $_GET['wpmastertoolkit_id'] ?? '' ) );
		$redirect_values = array();

		if ( ! empty( $redirect_id ) ) {

			$redirect = $this->get_redirect( $redirect_id );
			if ( $redirect ) {

				if ( '0' == $redirect['regex'] ) {
					$redirect['url_from'] = home_url( $redirect['url_from'] );
				}

				if ( '1' === $redirect['internal'] ) {
					$redirect['url_to'] = home_url( $redirect['url_to'] );
				}

				$redirect_values = $redirect;
			}
		}

		?>
			<div class="clear"></div>

			<form method="post" action="">

				<div class="tablenav top"></div>

				<?php if ( isset( $redirect_values['id'] ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[id]' ); ?>" value="<?php echo esc_attr( $redirect_values['id'] ); ?>">
				<?php endif; ?>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title activable">
						<div>
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[status]" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[status]" value="1" <?php checked( $redirect_values['status'] ?? '1', '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
						</div>
						<div><?php esc_html_e( "Status", 'wpmastertoolkit' ); ?></div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Source URL', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text">
							<input type="text" name="<?php echo esc_attr( $this->option_id . '[url_from]' ); ?>" value="<?php echo esc_attr( $redirect_values['url_from'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'The relative URL you want to redirect from', 'wpmastertoolkit' ); ?>" required>

							<div class="wp-mastertoolkit__regex-options">
								<label title="<?php esc_attr_e( 'Use regular expressions', 'wpmastertoolkit' ); ?>">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id ); ?>[regex]" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id ); ?>[regex]" value="1" <?php checked( $redirect_values['regex'] ?? '0', '1' ); ?> id="JS-checkbox-regex">
									<span class="wp-mastertoolkit__tooltip__trigger">.*</span>
								</label>
							</div>
						</div>
					</div>
				</div>
				
				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Target URL', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__input-text">
							<input type="text" name="<?php echo esc_attr( $this->option_id . '[url_to]' ); ?>" value="<?php echo esc_attr( $redirect_values['url_to'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'The target URL you want to redirect to', 'wpmastertoolkit' ); ?>" required>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Codes', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select">
							<select name="<?php echo esc_attr( $this->option_id . '[code]' ); ?>">
								<?php foreach ( $codes as $key => $name ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $redirect_values['code'] ?? '301', $key ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title">
						<?php esc_html_e( 'Modules', 'wpmastertoolkit' ); ?>
						<?php if ( ! wpmastertoolkit_is_pro() && $key != '0' ) : ?>
							<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select">
							<select name="<?php echo esc_attr( $this->option_id . '[model]' ); ?>" id="JS-select-model">
								<?php foreach ( $models as $key => $name ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $redirect_values['model'] ?? '0', $key ); ?> <?php disabled( ! wpmastertoolkit_is_pro() && $key != '0' ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Query Parameters', 'wpmastertoolkit' ); ?></div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select">
							<select name="<?php echo esc_attr( $this->option_id . '[params]' ); ?>" id="JS-select-params">
								<?php foreach ( $params as $key => $name ) :
									$disabled = ( '0' !== ( $redirect_values['regex'] ?? '0' ) || '0' !== ( $redirect_values['model'] ?? '0' ) ) && $key != '0';
								?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $redirect_values['params'] ?? '0', $key ); ?> <?php disabled( $disabled ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__title">
						<?php esc_html_e( 'Logs', 'wpmastertoolkit' ); ?>
						<?php if ( ! wpmastertoolkit_is_pro() && $key != '0' ) : ?>
							<span class="wp-mastertoolkit__section__body__item__title__tag pro"><?php esc_html_e( 'PRO', 'wpmastertoolkit' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__select">
							<select name="<?php echo esc_attr( $this->option_id . '[logs]' ); ?>" id="JS-select-logs">
								<?php foreach ( $logs as $key => $name ) : 
									$disabled = ( ! wpmastertoolkit_is_pro() || '0' !== ( $redirect_values['model'] ?? '0' ) ) && $key != '0';
								?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $redirect_values['logs'] ?? '0', $key ); ?> <?php disabled( $disabled ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="wp-mastertoolkit__section__body__item">
					<div class="wp-mastertoolkit__section__body__item__content">
						<div class="wp-mastertoolkit__button">
							<?php wp_nonce_field( $this->add_redirect_nonce_action ); ?>
							<?php if ( isset( $redirect_values['id'] ) ) : ?>
								<button type="submit"><?php esc_html_e( 'Update Redirect', 'wpmastertoolkit' ); ?></button>
							<?php else: ?>
								<button type="submit"><?php esc_html_e( 'Add Redirect', 'wpmastertoolkit' ); ?></button>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</form>
        <?php
	}

	/**
	 * Renders the logs view.
	 *
	 * @since 2.20.0
	 */
	private function render_logs_view() {

		if ( ! wpmastertoolkit_is_pro() ) {
			echo '<div class="clear"></div><p>' . esc_html__( 'Logs are available in the Pro version.', 'wpmastertoolkit' ) . '</p>';
			return;
		}

		$table = $this->get_logs_list_table();

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

		?>
			<form method="get" action="admin.php">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_slug ); ?>"/>
				<input type="hidden" name="wpmastertoolkit_view" value="logs"/>

				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'wpmastertoolkit' ); ?>">
				</p>

				<?php $table->display(); ?>
			</form>
        <?php
	}

	/**
	 * Renders the import/export view.
	 *
	 * @since 2.20.0
	 */
	private function render_import_export_view() {

		if ( ! wpmastertoolkit_is_pro() ) {
			echo '<div class="clear"></div><p>' . esc_html__( 'Import/Export is available in the Pro version.', 'wpmastertoolkit' ) . '</p>';
			return;
		}

		$redirects = $this->get_redirects_from_db();

		?>
			<div class="clear"></div>
			<form method="post" action="" enctype="multipart/form-data">
				<div class="tablenav top"></div>

				<?php wp_nonce_field( $this->import_export_nonce_action ); ?>

				<div class="wp-mastertoolkit__body__sections__item show">
					<div class="wp-mastertoolkit__body__sections__item__top">
						<div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Import Redirects', 'wpmastertoolkit' ); ?></div>
					</div>
					<div class="wp-mastertoolkit__body__sections__item__bottom">
						<input type="file" name="<?php echo esc_attr( $this->option_id . '_import_file' ); ?>" accept="application/csv">
						<button class="wp-mastertoolkit__body__sections__item__btn" type="submit" name="submit" value="import_redirects">
							<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/upload.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							<?php esc_html_e( 'Upload', 'wpmastertoolkit' ); ?>
						</button>
					</div>

					<?php if ( ! empty( $redirects ) ): ?>
					<div class="wp-mastertoolkit__body__sections__item__space"></div>
	
					<div class="wp-mastertoolkit__body__sections__item__top">
						<div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Export Redirects', 'wpmastertoolkit' ); ?></div>
					</div>
					<div class="wp-mastertoolkit__body__sections__item__bottom">
						<button class="wp-mastertoolkit__body__sections__item__btn" type="submit" name="submit" value="export_redirects">
							<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/download.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							<?php esc_html_e( 'Download', 'wpmastertoolkit' ); ?>
						</button>
					</div>
					<?php endif; ?>

					<div class="wp-mastertoolkit__body__sections__item__space"></div>
	
					<div class="wp-mastertoolkit__body__sections__item__top">
						<div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Export Template', 'wpmastertoolkit' ); ?></div>
					</div>
					<div class="wp-mastertoolkit__body__sections__item__bottom">
						<button class="wp-mastertoolkit__body__sections__item__btn" type="submit" name="submit" value="export_template">
							<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/download.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							<?php esc_html_e( 'Download Template', 'wpmastertoolkit' ); ?>
						</button>
					</div>
				</div>
			</form>
        <?php
	}
}
