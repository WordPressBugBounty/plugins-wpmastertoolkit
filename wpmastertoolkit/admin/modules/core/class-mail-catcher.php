<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Mail catcher
 * Description: Track every email WordPress sends with an easy-to-use admin viewer.
 * @since 2.14.0
 */
class WPMastertoolkit_Mail_Catcher {

	private $option_id;
    private $nonce;
    private $nonce_name;
	private $header_title;
	public $page_id;

	/**
     * Invoke the hooks.
     * 
     * @since   2.14.0
     */
    public function __construct() {
		$this->option_id  = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_mail_catcher';
        $this->nonce      = $this->option_id . '_action';
        $this->nonce_name = $this->option_id . '_name';
		$this->page_id    = 'wp-mastertoolkit-settings-mail-catcher';

		add_action( 'init', array( $this, 'class_init' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'admin_init', array( $this, 'show_email_message' ) );
		add_filter( 'wp_mail', array( $this, 'save_email_log' ), PHP_INT_MAX );
		add_action( 'wp_mail_failed', array( $this, 'save_email_failed_log' ) );
		add_action( 'wp_ajax_wpmtk_mail_catcher_preview', array( $this, 'mail_catcher_preview' ) );
	}

	/**
     * Initialize the class
	 * 
	 * @since   2.14.0
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Mail catcher', 'wpmastertoolkit' );
    }

	/**
	 * Add admin body class
	 * 
	 * @since   2.14.0
	 */
	public function admin_body_class( $classes ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && $_GET['page'] === $this->page_id ) {
			$classes .= ' wpmtk-modern-post-list';
		}

		return $classes;
	}

	/**
	 * Save email log
	 * 
	 * @since   2.14.0
	 */
	public function save_email_log( $atts ) {
		global $wpdb, $wpmastertoolkit_current_mail_id;

		if ( $this->is_reach_limit() ) {
			return $atts;
		}

		if ( ! is_array( $atts ) ) {
            return $atts;
        }

		try {
			$receiver    = $this->get_mail_receiver( $atts );
			$subject     = $this->get_mail_subject( $atts );
			$message     = $this->get_mail_message( $atts );
			$headers     = $this->get_mail_headers( $atts );
			$attachments = $this->get_mail_attachments( $atts );
			$host        = sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ?? '' ) );

			if ( ! $this->is_table_exist() ) {
				$this->create_table();
			}

			$data = array(
				'receiver'    => $receiver,
				'subject'     => $subject,
				'message'     => $message,
				'headers'     => $headers,
				'attachments' => $attachments,
				'error'       => '',
				'host'        => $host,
				'unixtime'    => time(),
			);
			$data_format = array(
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%s', // string
				'%d', // integer
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$this->get_table_name(),
				$data,
				$data_format
			);

			$wpmastertoolkit_current_mail_id = $wpdb->insert_id;	
		} catch (\Throwable $th) {
		}

		$this->add_to_limit_counter();

		return $atts;
	}

	/**
	 * Save email failed log
	 * 
	 * @since   2.14.0
	 */
	public function save_email_failed_log( $error ) {
		global $wpdb, $wpmastertoolkit_current_mail_id;

		if ( ! isset( $wpmastertoolkit_current_mail_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->get_table_name(),
			array( 'error' => $error->get_error_message() ),
			array( 'id' => $wpmastertoolkit_current_mail_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mail catcher preview AJAX
	 * 
	 * @since   2.14.0
	 */
	public function mail_catcher_preview() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$email_id = sanitize_text_field( wp_unslash( $_POST['email_id'] ?? '' ) );
		if ( empty( $email_id ) ) {
			wp_send_json_error( __( 'This email does not exist.', 'wpmastertoolkit' ) );
		}

		$item = $this->render_preview( $email_id );
		if ( empty( $item ) ) {
			wp_send_json_error( __( 'This email does not exist.', 'wpmastertoolkit' ) );
		}

		wp_send_json_success( $item );
	}

	/**
	 * Get logs items
	 * 
	 * @since   2.14.0
	 */
	public function get_logs_items( $per_page, $offset, $count_only = false ) {
		global $wpdb;

		if ( $this->is_table_exist() ) {
			// phpcs:disable
			$status       = sanitize_text_field( wp_unslash( $_GET['status'] ?? '0' ) );
			$order_by     = sanitize_text_field( wp_unslash( $_GET['orderby'] ?? 'id' ) );
			$order        = sanitize_text_field( wp_unslash( $_GET['order'] ?? 'desc' ) );
			$search_place = sanitize_text_field( wp_unslash( $_REQUEST['search']['place'] ?? '' ) );
			$search       = sanitize_text_field( wp_unslash( $_REQUEST['search']['term'] ?? '' ) );
			// phpcs:enable

			// Build `SELECT` clause.
			if ( $count_only ) {
				$select = 'SELECT COUNT(*) ';
			} else {
				$select = 'SELECT * ';
			}
			$select .= 'FROM ' . esc_sql( $this->get_table_name() );

			$status_where = '';
			switch( $status ) {
				case 1:
					$status_where .= " WHERE `error` IS NULL OR `error` = ''";
				break;
				case 2:
					$status_where .= " WHERE `error` IS NOT NULL AND `error` != ''";
				break;
			}

			$search_where = '';
			if ( ! empty( $search ) ) {
				if ( empty( $status_where ) ) {
					$search_where = ' WHERE (';
				} else {
					$search_where .= ' AND (';
				}

				$search_where .=  '`' . esc_sql( $search_place ) . '` LIKE "%' . esc_sql( $search ) . '%" OR ';

				// Remove the last ' OR ' and add the closing ')';
				$search_where = substr( $search_where, 0, -4 ) . ')';
			}

			// Build query.
        	$query = $select . $status_where . $search_where;

			if ( $count_only ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            	$results = $wpdb->get_var( $query );
			} else {
				// SORT BY, LIMIT, and ORDER are only applicable if we're not counting the results.
				if ( ! empty( $order_by ) ) {
					$query .= ' ORDER BY `' . esc_sql( $order_by ) . '`';
	
					if ( ! empty( $order ) && in_array( $order, array( 'asc', 'desc' ), true ) ) {
						$query .= ' ' . esc_sql( $order );
					}
				}
	
				if ( ! empty( $per_page ) ) {
					$query .= ' LIMIT ' . absint( $per_page );
				}
	
				if ( ! empty( $offset ) ) {
					$query .= ' OFFSET ' . absint( $offset );
				}
	
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$results = $wpdb->get_results( $query, ARRAY_A );
			}

			if ( empty( $results ) ) {
				if ( $count_only ) {
					return 0;
				}

				return array();
			}

			return $results;
		} else {
			if ( $count_only ) {
				return 0;
			}

			return array();
		}
	}

	/**
	 * Get logs count
	 * 
	 * @since   2.14.0
	 */
	public function get_logs_count( $status = null ) {
		global $wpdb;

		if ( $this->is_table_exist() ) {

			$status_where = '';
			switch( $status ) {
				case 1:
					$status_where .= " WHERE `error` IS NULL OR `error` = ''";
				break;
				case 2:
					$status_where .= " WHERE `error` IS NOT NULL AND `error` != ''";
				break;
			}

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_name()} {$status_where}" );
		} else {
			return 0;
		}
	}

	/**
	 * Add submenu
	 * 
	 * @since   2.14.0
	 */
	public function add_submenu() {
		WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
			$this->header_title,
			$this->header_title,
			'manage_options',
			$this->page_id,
			array( $this, 'render_submenu'),
			null
		);
	}

	/**
	 * Render the submenu
	 * 
	 * @since   2.14.0
	 */
	public function render_submenu() {
		wp_enqueue_style( 'WPMastertoolkit_submenu_fontawesome', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/lib/core/font-awesome.min.css', array(), '4.7.0', 'all' );

		$replace_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/mail-catcher.asset.php' );
		wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/mail-catcher.css', array(), $replace_assets['version'], 'all' );
		wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/mail-catcher.js', $replace_assets['dependencies'], $replace_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'WPMastertoolkitSubmenu', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( $this->nonce ),
		));

		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
		$this->submenu_content();
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
	 * Save the submenu option
	 * 
	 * @since   2.14.0
	 */
	public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $this->nonce_name ] ?? '' ) );
		if ( wp_verify_nonce( $nonce, $this->nonce ) ) {
			if ( isset( $_POST['delete'] ) ) {
				$this->delete_log( $_POST );
			}
		}
	}

	/**
	 * Show email message
	 * 
	 * @since   2.14.0
	 */
	public function show_email_message() {
		$nonce_get = sanitize_text_field( wp_unslash( $_GET[ $this->nonce_name ] ?? '' ) );
		if ( wp_verify_nonce( $nonce_get, $this->nonce ) ) {
			$email_id = sanitize_text_field( wp_unslash( $_GET['email_id'] ?? '' ) );
			if ( ! empty( $email_id ) ) {
				$item = $this->get_one_item_by_id( $email_id );

				if ( ! empty( $item ) ) {
					$message = $item['message'] ?? '';

					// Strip <xml> and comment tags.
        			$message = preg_replace( '/<xml\b[^>]*>(.*?)<\/xml>/is', '', $message );
        			$message = preg_replace( '/<!--(.*?)-->/', '', $message );

					$allowed_html              = wp_kses_allowed_html( 'post' );
        			$allowed_html['style'][''] = true;
					echo wp_kses( $message, $allowed_html, wp_allowed_protocols() );
					exit();
				}
			}
		}
	}

	/**
	 * Get mail receiver
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_receiver( $mail_data ) {
		$receiver = $mail_data['to'];

		if( is_array( $receiver ) ) {
			$receiver_array = $receiver;
		} else {
			$receiver_array = preg_split( "/(,|,\s)/", $receiver );
		}

		return implode( ',\n', $receiver_array );
	}

	/**
	 * Check reach limit
	 * 
	 * @since   2.14.0
	 */
	private function is_reach_limit() {
		if ( wpmastertoolkit_is_pro() ) {
			return false;
		}

		$today   = wp_date( 'Y-m-d' );
		$data    = get_option( $this->option_id, array() );
		$counter = $data['counter'] ?? 0;
		$date    = $data['date'] ?? '';

		if ( $date !== $today ) {
			return false;
		}

		return $counter >= 5;
	}

	/**
	 * Add to limit counter
	 * 
	 * @since   2.14.0
	 */
	private function add_to_limit_counter() {
		$today   = wp_date( 'Y-m-d' );
		$data    = get_option( $this->option_id, array() );
		$counter = $data['counter'] ?? 0;
		$date    = $data['date'] ?? '';

		if ( $date !== $today ) {
			$counter = 0;
		}

		$data = array(
			'counter' => $counter + 1,
			'date'    => $today,
		);

		update_option( $this->option_id, $data );
	}

	/**
	 * Get mail subject
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_subject( $mail_data ) {
		if ( ! empty( $mail_data['subject'] ) && mb_strlen( $mail_data['subject'] ) > 200 ) {
            $mail_data['subject'] = mb_substr( $mail_data['subject'], 0, 195 ) . '...';
        }

		return $mail_data['subject'];
	}

	/**
	 * Get mail message
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_message( $mail_data ) {
		if ( isset( $mail_data['message'] ) ) {
			return $mail_data['message'];
		} elseif ( isset( $mail_data['html'] ) ) {
			return $mail_data['html'];
		}
		return '';
	}

	/**
	 * Get mail headers
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_headers( $mail_data ) {
		
		$mail_headers = '';
		//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$content_type = 'Content-Type: ' . apply_filters( 'wp_mail_content_type', 'text/html' );
		if ( empty( $mail_data['headers'] ) ) {
            $mail_headers = [ $content_type ];
        }

		$mail_headers = $mail_data['headers'];
		if ( ! is_array( $mail_headers ) ) {
			$clean_headers = str_replace(
				array(
					"\\r\\n",
					"\r\n",
					",\n",
					",\\n"
				),
				"\n",
				$mail_headers
			);

			$headers = explode( "\n", $clean_headers );

			$mail_headers = array_filter( array_map( function( $header ) {
				return rtrim( $header, "," );
			}, $headers ) );
		}

        if ( empty( $mail_headers ) ) {
            $mail_headers = [ $content_type ];
        }

		$should_force_add_content_type = true;
        foreach ( $mail_headers as $mail_header ) {
            $header_arr = explode( ":", $mail_header );

            if ( ! empty( $header_arr[0] ) && strtolower( $header_arr[0] ) === 'content-type' ) {
                $should_force_add_content_type = false;
            }
        }

		if ( $should_force_add_content_type ) {
            $mail_headers[] = $content_type;
        }

		if ( is_array( $mail_headers ) ) {
			$mail_headers = implode( ',\n', $mail_headers );
		}

		return $mail_headers;
	}

	/**
	 * Get mail attachments
	 * 
	 * @since   2.14.0
	 */
	private function get_mail_attachments( $mail_data ) {
		$attachment_abs_paths = isset( $mail_data['attachments'] ) ? $mail_data['attachments'] : array();

		if( ! is_array( $attachment_abs_paths ) ) {
            $attachment_abs_paths = preg_split( "/(,|,\s)/", $attachment_abs_paths );
        }

		$attachment_urls = [];
        foreach ( $attachment_abs_paths as $attachment_abs_path ) {
			$attachment_urls[] = str_replace( ABSPATH, '', $attachment_abs_path );
        }

		return implode( ',\n', $attachment_urls );
	}

	/**
	 * Delete attempt
	 * 
	 * @since 2.14.0
	 */
	private function delete_log( $data ) {
		global $wpdb;

		$table_name   = $this->get_table_name();
		$log_id       = (int) $data['delete'] ?? '';
		$where        = array( 'id' => $log_id );
        $where_format = array( '%d' );

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
            $table_name,
            $where,
            $where_format
        );
	}

	/**
	 * Get table name
	 * 
	 * @since 2.14.0
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->option_id;
	}

	/**
	 * Check if table exist
	 * 
	 * @since 2.14.0
	 */
	private function is_table_exist() {
		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->get_table_name() ) ) ) === $this->get_table_name();
	}

	/**
	 * Create table
	 * 
	 * @since 2.14.0
	 */
	private function create_table() {
		global $wpdb;

        if ( ! empty( $wpdb->charset ) ) {
            $charset_collation_sql = "DEFAULT CHARACTER SET $wpdb->charset";
        }

        if ( ! empty( $wpdb->collate ) ) {
            $charset_collation_sql .= " COLLATE $wpdb->collate";
        }

		$table_name = $this->get_table_name();

		// Drop table if already exists
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );

		// Create database table.
		$sql =
        "CREATE TABLE {$table_name} (
            id int(6) unsigned NOT NULL auto_increment,
            receiver varchar(200) NOT NULL DEFAULT '',
            subject varchar(200) NOT NULL DEFAULT '',
            message text NULL,
            headers text NULL,
            attachments varchar(800) NOT NULL DEFAULT '',
            error varchar(400) NULL DEFAULT '',
            host varchar(200) NOT NULL DEFAULT '',
            unixtime int(10) NOT NULL DEFAULT '0',
            PRIMARY KEY (id),
			FULLTEXT KEY idx_message (message)
        ) {$charset_collation_sql}";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get one item by id
	 * 
	 * @since 2.14.0
	 */
	private function get_one_item_by_id( $id ) {
		global $wpdb;

		if ( $this->is_table_exist() ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ), ARRAY_A );
		} else {
			return false;
		}
	}

	/**
	 * Get iframe src
	 * 
	 * @since 2.14.0
	 */
	private function get_iframe_src( $item ) {
		$admin_base = admin_url( 'admin.php' );
		$admin_base = add_query_arg( 'page', $this->page_id, $admin_base );

		return add_query_arg(
			array( 'email_id' => $item['id'] ),
			wp_nonce_url( $admin_base, $this->nonce, $this->nonce_name )
		);
	}

	/**
	 * Get attachments html
	 * 
	 * @since 2.14.0
	 */
	private function get_attachments_html( $item ) {

		$attachments = $item['attachments'] ?? '';
		if ( empty( $attachments ) ) {
			return false;
		}

		$attachment_append    = '';
        $attachment_rel_paths = explode( ',\n', $item['attachments'] );
        $attachment_rel_paths = is_array( $attachment_rel_paths ) ? $attachment_rel_paths : array( $attachment_rel_paths );
        $attachment_rel_paths = array_filter( $attachment_rel_paths );

		foreach ( $attachment_rel_paths as $attachment_rel_path ) {
			$attachment_title    = basename( $attachment_rel_path );
			$attachment_abs_path = ABSPATH . $attachment_rel_path;

			if ( file_exists( $attachment_abs_path ) ) {
				$icon           = $this->determine_mime_icon( $attachment_abs_path );
				$attachment_url = str_replace( ABSPATH, home_url('/') , $attachment_abs_path );
				$attachment_append .= '<a target="_blank" href="' . $attachment_url . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></a> ';
			} else {
				/* Translators: %s attachment title */
				$message = sprintf( __( 'Attachment %s is not present', 'wpmastertoolkit' ), $attachment_title );
				$attachment_append .= '<i class="fa fa-times" title="' . $message . '"></i>';
			}
		}

		return $attachment_append;
	}

	/**
	 * Determine mime icon
	 * 
	 * @since 2.14.0
	 */
	private function determine_mime_icon( $file_path ) {

		$icon_class = 'file';
		if( function_exists('mime_content_type') ) {
			
			$mime = mime_content_type( $file_path );
			if ( false !== $mime ) {
				
				$mime_parts = explode( '/', $mime );
				$attribute  = $mime_parts[0];
				$supported  = array(
					'archive' => array(
						'application/zip',
						'application/x-rar-compressed',
						'application/x-rar',
						'application/x-gzip',
						'application/x-msdownload',
						'application/x-msdownload',
						'application/vnd.ms-cab-compressed',
					),
					'audio',
					'code' => array(
						'text/x-c',
						'text/x-c++'
					),
					'excel' => array( 'application/vnd.ms-excel' ),
					'image',
					'text',
					'movie',
					'pdf' => array( 'application/pdf' ),
					'photo',
					'picture',
					'powerpoint' => array( 'application/vnd.ms-powerpoint' ),
					'sound',
					'video',
					'word' => array( 'application/msword' ),
					'zip',
				);

				if ( in_array( $attribute, $supported ) ) {
					$icon_class = $attribute;
				} else {
					foreach ( $supported as $key => $value ) {
						if ( $mime === $value ) {
							$icon_class = $key;
						}
					}
				}
			}
		}

		$supported = array(
			'archive' => 'media-archive',
			'audio'   => 'media-audio',
			'code'    => 'media-code',
			'excel'   => 'media-spreadsheet',
			'image'   => 'format-image',
			'movie'   => 'media-video',
			'pdf'     => 'pdf',
			'photo'   => 'format-image',
			'picture' => 'format-image',
			'sound'   => 'media-audio',
			'video'   => 'media-video',
			'zip'     => 'media-archive',
		);

		if ( ! array_key_exists( $icon_class, $supported ) ) {
			return 'media-document';
		}

		return $supported[ $icon_class ];
	}

	/**
	 * Render preview
	 * 
	 * @since 2.14.0
	 */
	private function render_preview( $id ) {
		$item = $this->get_one_item_by_id( $id );
		if ( empty( $item ) ) {
			return false;
		}

		$iframe_src       = $this->get_iframe_src( $item );
		$attachments_html = $this->get_attachments_html( $item );

		ob_start();
		?>
			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Time', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__time"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['unixtime'] ) ); ?></div>
				</div>
			</div>

			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Receiver', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__receiver"><?php echo wp_kses_post( nl2br( str_replace( '\n', "\n", $item['receiver'] ) ) ); ?></div>
				</div>
			</div>

			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Subject', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__subject"><?php echo esc_html( $item['subject'] ); ?></div>
				</div>
			</div>

			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Headers', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__headers"><?php echo wp_kses_post( nl2br( str_replace( '\n', "\n", $item['headers'] ) ) ); ?></div>
				</div>
			</div>

			<?php if ( ! empty( $item['error'] ) ): ?>
			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Error', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__error"><?php echo esc_html( $item['error'] ); ?></div>
				</div>
			</div>
			<?php endif; ?>

			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Message', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__message">
						<iframe src="<?php echo esc_url( $iframe_src ); ?>" frameborder="0"></iframe>
					</div>
				</div>
			</div>

			<?php if ( $attachments_html ): ?>
			<div class="wpmtk-email-preview__content__item">
				<div class="wpmtk-email-preview__content__item__title"><?php esc_html_e( 'Attachments', 'wpmastertoolkit' ); ?></div>
				<div class="wpmtk-email-preview__content__item__content">
					<div class="wpmtk-email-preview__content__item__content__attachments"><?php echo wp_kses_post( $attachments_html ); ?></div>
				</div>
			</div>
			<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add the submenu content
	 * 
	 * @since   2.14.0
	 */
	private function submenu_content() {
		// phpcs:disable
		$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
		$page   = sanitize_text_field( wp_unslash( $_REQUEST['page'] ?? '' ) );
		// phpcs:enable
		$table  = $this->get_list_table();
		$table->prepare_items( $search );
		$table->display_notices();

		$is_pro		    = wpmastertoolkit_is_pro();
		$data           = get_option( $this->option_id, array() );
		$counter        = $data['counter'] ?? 0;
		$number         = $counter >= 5 ? 5 : $counter;
		$is_reach_limit = $counter >= 5;
		$counter        = str_pad( $number, 2, '0', STR_PAD_LEFT );
		?>
			<?php if ( ! $is_pro ): ?>
			<div class="wpmtk-banner">
				<div class="wpmtk-banner__content">
					<div class="wpmtk-banner__content__message <?php echo $is_reach_limit ? 'limit-reached' : ''; ?>">
						<div class="wpmtk-banner__content__message__text">
							<?php if ( $is_reach_limit ): ?>
								⚠️ <?php
									echo wp_kses_post( sprintf( 
										/* Translators: %s strong tag open and close */
										__( 'You have reached the limit of %1$s 5 emails %2$s captured per day. To go further, upgrade to the next version.', 'wpmastertoolkit' ),
										'<span class="strong">',
										'</span>'
									) );
								?>
							<?php else: ?>
								<?php
									echo wp_kses_post( sprintf(
										/* Translators: %s strong tag open and close */
										__( 'Sending is limited to %1$s 5 emails %2$s per day. For %3$s unlimited %4$s sending, upgrade to the next version.', 'wpmastertoolkit' ),
										'<span class="strong">',
										'</span>',
										'<span class="strong">',
										'</span>'
									) );
								?>
							<?php endif; ?>
							<span class="wpmtk-banner__content__message__text__pro"><?php esc_html_e( 'Pro', 'wpmastertoolkit' ); ?></span>
						</div>
						<div class="wpmtk-banner__content__message__counter">
							<div class="wpmtk-banner__content__message__counter__title"><?php esc_html_e( 'Captured emails', 'wpmastertoolkit' ); ?></div>
							<div class="wpmtk-banner__content__message__counter__number">
								<span class="wpmtk-banner__content__message__counter__number__value <?php echo $is_reach_limit ? 'limit-reached' : ''; ?>"><?php echo esc_html( $counter ); ?></span>
								<span class="wpmtk-banner__content__message__counter__number__base"><?php echo esc_html( '/05' ); ?></span>
								<span class="wpmtk-banner__content__message__counter__number__symbol"><?php esc_html_e( 'Free', 'wpmastertoolkit' ); ?></span>
							</div>
						</div>
					</div>
					<div class="wpmtk-banner__content__cta" style="background-image: url(<?php echo esc_attr( WPMASTERTOOLKIT_PLUGIN_URL . 'admin/images/button-bg.webp' ); ?>);">
						<div class="wpmtk-banner__content__cta__text">
							<?php
								echo wp_kses_post( sprintf(
									/* Translators: %s line break */
									__( 'Unlock %s more emails', 'wpmastertoolkit' ),
									'<br>'
								) );
							?>
						</div>
						<div class="wpmtk-banner__content__cta__btn wp-mastertoolkit__button">
							<a href="https://wpmastertoolkit.com/" target="_blank" class="wpmtk-button">
								<?php esc_html_e( 'Try for 15 days', 'wpmastertoolkit' ); ?>
								<span class="wpmtk-button__pro"><?php esc_html_e( 'Pro', 'wpmastertoolkit' ); ?></span>
							</a>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<form id="email-list" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<?php
					wp_nonce_field( $this->nonce, $this->nonce_name );
					$table->search_box( __( 'Search', 'wpmastertoolkit' ), 's' );
					$table->views();
					$table->display();
				?>
			</form>

			<div class="wpmtk-popup">
				<div class="wpmtk-popup__overlay" id="JS-popup-overlay"></div>
				<div class="wpmtk-popup__content">
					<div class="wpmtk-popup__header">
						<div class="wpmtk-popup__header__left">
							<div class="wpmtk-popup__header__title"><?php esc_html_e( 'Email Preview', 'wpmastertoolkit' ); ?></div>
						</div>
						<div class="wpmtk-popup__header__right">
							<div class="wpmtk-popup__header__close" id="JS-close-popup">
								<?php echo wp_kses( file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' ), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
							</div>
						</div>
					</div>
					<div class="wpmtk-popup__body">
						<div class="wpmtk-popup__body__content">
							<div class="wpmtk-email-preview">
								<div id="JS-wpmtk-email-preview" class="wpmtk-email-preview__content"></div>
								<div id="JS-wpmtk-email-loader" class="wpmtk-email-preview__loader"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * Initialises and returns the list table for logs.
	 * 
	 * @since    2.14.0
	 */
	private function get_list_table() {
		static $table = null;

		if ( ! $table ) {
			require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/mail-catcher/class-logs-list-table.php';
			$table = new WPMastertoolkit_Mail_Catcher_Logs_List_Table();
		}

		return $table;
	}
}
