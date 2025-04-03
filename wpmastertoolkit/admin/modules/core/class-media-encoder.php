<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Intervention\Image\ImageManager;

/**
 * Module Name: Media Encoder
 * Description: Automatically converts images to WebP when they are uploaded to the media library.
 * @since 1.13.0
 */
class WPMastertoolkit_Media_Encoder {

	const MODULE_ID = 'Media Encoder';

	private $option_id;
    private $header_title;
    private $nonce_action;
	private $settings;
    private $default_settings;
	private $meta_already_optimized_quickwebp;
	private $meta_data_quickwebp;
	private $meta_has_error_quickwebp;
	private $meta_already_optimized;
	private $meta_data;
	private $meta_has_error;
	private $option_bulk_status;
	private $option_bulk_total;
	private $option_bulk_current;
	private $cron_recurrence_bulk;
	private $cron_hook_bulk;

	private $option_migrate_status;
	private $option_migrate_total;
	private $option_migrate_current;
	private $cron_recurrence_migrate;
	private $cron_hook_migrate;

	private $allowed_mime_types;
	private $quickwebp_plugin_activated;

	/**
     * Invoke the hooks.
     * 
     * @since   1.13.0
     */
    public function __construct() {
        $this->option_id   	                    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_media_encoder';
        $this->nonce_action	                    = $this->option_id . '_action';
        $this->meta_already_optimized_quickwebp = 'quickwebp_already_optimized';
        $this->meta_data_quickwebp              = 'quickwebp_data';
        $this->meta_has_error_quickwebp         = 'quickwebp_has_error';
        $this->meta_already_optimized           = $this->option_id . '_already_optimized';
        $this->meta_data                        = $this->option_id . '_data';
        $this->meta_has_error                   = $this->option_id . '_has_error';
        $this->option_bulk_status               = $this->option_id . '_bulk_status';
        $this->option_bulk_total                = $this->option_id . '_bulk_total';
        $this->option_bulk_current              = $this->option_id . '_bulk_current';
		$this->cron_recurrence_bulk             = $this->option_id . '_bulk_optimization';
		$this->cron_hook_bulk                   = $this->option_id . '_bulk_optimization_hook';

        $this->option_migrate_status            = $this->option_id . '_migrate_status';
        $this->option_migrate_total             = $this->option_id . '_migrate_total';
        $this->option_migrate_current           = $this->option_id . '_migrate_current';
		$this->cron_recurrence_migrate          = $this->option_id . '_migrate_optimization';
		$this->cron_hook_migrate                = $this->option_id . '_migrate_optimization_hook';

		$this->allowed_mime_types               = array( 'image/jpeg', 'image/png', 'image/webp' );
		$this->quickwebp_plugin_activated       = false;

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'image_optimization' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'image_optimization_saving_original' ), 10, 3 );
		add_filter( 'wp_editor_set_quality', array( $this, 'change_image_compression_quality' ), PHP_INT_MAX );
		add_action( 'delete_attachment', array( $this, 'before_delete_attachment' ) );

		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_fields_to_edit' ), PHP_INT_MAX, 2 );
		add_filter( 'manage_media_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'add_media_custom_column' ), 10, 2 );
		add_action( 'attachment_submitbox_misc_actions', array( $this, 'add_attachment_submitbox_misc_actions' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		add_filter( 'cron_schedules', array( $this, 'crons_registrations' ) );
		add_action( $this->cron_hook_bulk, array( $this, 'excute_bulk_cron' ) );
		add_action( $this->cron_hook_migrate, array( $this, 'excute_migrate_cron' ) );

		add_action( 'wp_ajax_wpmtk_media_encoder_preview_mode', array( $this, 'preview_mode_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_start_bulk', array( $this, 'start_bulk_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_stop_bulk', array( $this, 'stop_bulk_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_progress_bulk', array( $this, 'progress_bulk_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_start_single', array( $this, 'start_single_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_undo_single', array( $this, 'undo_single_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_start_single_migration', array( $this, 'start_single_migration_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_start_bulk_migration', array( $this, 'start_bulk_migration_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_stop_bulk_migration', array( $this, 'stop_bulk_migration_cb' ) );
		add_action( 'wp_ajax_wpmtk_media_encoder_progress_bulk_migration', array( $this, 'progress_bulk_migration_cb' ) );

		add_action( 'template_redirect', array( $this, 'start_content_process' ), -1000 );
    }

	/**
     * Initialize the class
	 * 
	 * @since   1.13.0
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Media Encoder', 'wpmastertoolkit' );

		$active_plugins = get_option( 'active_plugins' );
		if ( is_array( $active_plugins ) && in_array( 'quickwebp/quickwebp.php', $active_plugins ) ) {
			$this->quickwebp_plugin_activated = true;
		}
    }

	/**
     * activate
     *
     * @since   1.13.0
     */
    public static function activate(){
		$this_class = new self();
		$this_class->add_to_htaccess();
    }

	/**
     * deactivate
     *
     * @since   1.13.0
     */
    public static function deactivate(){
		$this_class = new self();
		$this_class->remove_from_htaccess();
    }

	/**
     * Add a submenu
     * 
     * @since   1.13.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-media-encoder',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Save the submenu option
     * 
     * @since   1.13.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );

			if ( 'rewrite' == $new_settings['display_webp_mode']['value'] ) {
				$this->add_to_htaccess();
			} else {
				$this->remove_from_htaccess();
			}

            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

	/**
	 * Add the module to htaccess file
	 * 
	 * @since   1.13.0
	 */
	private function add_to_htaccess() {
		global $is_apache;

        if ( $is_apache ) {
			$this->settings = $this->get_settings();

			if ( 'rewrite' == $this->settings['display_webp_mode']['value'] ) {
				require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
				WPMastertoolkit_Htaccess::add( $this->get_raw_content_htaccess(), self::MODULE_ID );
			}
        }
	}

	/**
	 * Remove the module from htaccess file
	 * 
	 * @since   1.13.0
	 */
	private function remove_from_htaccess() {
		global $is_apache;

        if ( $is_apache ) {
            require_once WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/class-htaccess.php';
            WPMastertoolkit_Htaccess::remove( self::MODULE_ID );
        }
	}

	/**
	 * Add the nginx code snippets
	 *
	 * @since   1.13.0
	 */
	public function nginx_code_snippets( $code_snippets ) {
		global $is_nginx;

		if ( $is_nginx ) {
			$this->settings = $this->get_settings();

			if ( 'rewrite' == $this->settings['display_webp_mode']['value'] ) {
				$code_snippets[self::MODULE_ID] = self::get_raw_content_nginx();
			}
		}

		return $code_snippets;
	}

	/**
	 * Optimize an image added in wp_media
	 * 
	 * @since   1.13.0
	 */
	public function image_optimization( $file ) {

		if ( $this->quickwebp_plugin_activated ) {
			return $file;
		}

		$this->settings = $this->get_settings();

		if ( $this->settings['enabled'] != '1' ) {
			return $file;
		}

		$image_file = $this->file_is_image( $file );
		if ( ! $image_file ) {
			return $file;
		}

		if ( $this->settings['save_original'] == '1' ) {
			return $image_file;
		}

		$extension_to_use = $this->image_extension_loaded();
		if ( ! $extension_to_use ) {
			return $image_file;
		}

		$exif_meta = wp_read_image_metadata( $image_file['tmp_name'] );
		
		if ( ! empty( $exif_meta['orientation'] ) ) {
			$orientation = $exif_meta['orientation'];
			if ( $orientation > 1 ) {
				$manager = new ImageManager( array( 'driver' => $extension_to_use ) );
				$image   = $manager->make( $image_file['tmp_name'] );
				$image->orientate();
				$image->save( $image_file['tmp_name'], 100, $image_file['type'] );
				$image_file['tmp_name'] = $image->basePath();
			}
		}

		$manager = new ImageManager( array( 'driver' => $extension_to_use ) );
		$image   = $manager->make( $image_file['tmp_name'] );
		$quality = $this->get_quality( $image_file['tmp_name'] );

		// $image->sharpen( $this->settings['sharpen'] );
		$image->save( $image_file['tmp_name'], $quality, 'webp' );

		$image_file['size'] = $image->filesize();
		$image_file['type'] = $image->mime();

		return $image_file;
	}

	/**
	 * Generate an optimized version of the image and keeping the original
	 * 
	 * @since   1.13.0
	 */
	public function image_optimization_saving_original( $metadata, $attachment_id, $context ) {

		if ( $this->quickwebp_plugin_activated ) {
			return $metadata;
		}

		if ( $context != 'create' ) {
			return $metadata;
		}

		$this->settings = $this->get_settings();

		if ( $this->settings['enabled'] != '1' ) {
			return $metadata;
		}

		if ( $this->settings['save_original'] != '1' ) {
			return $metadata;
		}

		$sizes     = $this->get_media_files( $attachment_id );
		$new_sizes = array();

		foreach ( $sizes as $key => $size ) {
			$result = $this->optimize_local_file( $size );

			if ( $result ) {
				$new_sizes[$key] = $result;
			}
		}

		if ( ! empty( $new_sizes ) ) {
			update_post_meta( $attachment_id, $this->meta_already_optimized, '1' );
			update_post_meta( $attachment_id, $this->meta_data, $new_sizes );
			delete_post_meta( $attachment_id, $this->meta_has_error );
		} else {
			update_post_meta( $attachment_id, $this->meta_has_error, '1' );
		}

		return $metadata;
	}

	/**
	 * Change the image compression quality to be 100 if this module is active.
	 * 
	 * @since   1.13.0
	 */
	public function change_image_compression_quality( $quality ) {
		$this->settings = $this->get_settings();

		if ( $this->quickwebp_plugin_activated ) {
			return $quality;
		}

		if ( $this->settings['enabled'] != '1' ) {
			return $quality;
		}

		return 100;
	}

	/**
	 * Clean the attachment before delete
	 * 
	 * @since   1.13.0
	 */
	public function before_delete_attachment( $post_id ) {
		$already_optimized = get_post_meta( $post_id, $this->meta_already_optimized, true );

		if ( '1' == $already_optimized ) {
			$this->remove_related_files( $post_id );
		}
	}

	/**
	 * Add column in the Media Uploader
	 * 
	 * @since   1.13.0
	 */
	public function add_attachment_fields_to_edit( $form_fields, $post ) {
		global $pagenow;

		if ( $this->quickwebp_plugin_activated ) {
			return $form_fields;
		}

		if ( 'post.php' === $pagenow ) {
			return $form_fields;
		}

		if ( ! $this->valid_mimetype( $post->ID ) ) {
			return $form_fields;
		}

		$data      = get_post_meta( $post->ID, $this->meta_data, true );
		$has_error = get_post_meta( $post->ID, $this->meta_has_error, true );

		if ( ! is_array( $data ) ) {
			$html = $this->optimize_btn( $post->ID );

			if ( ! empty( $has_error ) ) {
				$html .= '<br>' . esc_html__( 'Error attempting to optimize this image', 'wpmastertoolkit' );
			}
		} else {
			$html = $this->attachment_data( $data, $post->ID );
		}
		
		$form_fields['wpmtk_media_encoder'] = array(
			'label' 		=> 'WPMastertoolkit',
			'input' 		=> 'html',
			'html' 			=> $html,
			'show_in_edit'	=> true,
			'show_in_modal'	=> true
		);

		return $form_fields;
	}

	/**
	 * Add column in upload.php
	 * 
	 * @since   1.13.0
	 */
	public function add_media_columns( $columns ) {

		if ( $this->quickwebp_plugin_activated ) {
			return $columns;
		}

		$columns['wpmastertoolkit'] = __( 'WPMasterToolkit', 'wpmastertoolkit' );

		return $columns;
	}

	/**
	 * Add column in upload.php
	 * 
	 * @since   1.13.0
	 */
	public function add_media_custom_column( $column_name, $attachment_id ) {

		if ( $this->quickwebp_plugin_activated ) {
			return;
		}

		if ( 'wpmastertoolkit' !== $column_name ) {
			return;
		}

		if ( ! $this->valid_mimetype( $attachment_id ) ) {
			esc_html_e( 'Image type not supported', 'wpmastertoolkit' );
			return;
		}

		$data      = get_post_meta( $attachment_id, $this->meta_data, true );
		$has_error = get_post_meta( $attachment_id, $this->meta_has_error, true );

		if ( ! is_array( $data ) ) {
			echo wp_kses_post( $this->optimize_btn( $attachment_id ) );

			if ( ! empty( $has_error ) ) {
				echo esc_html__( 'Error attempting to optimize this image', 'wpmastertoolkit' );
			}
		} else {
			echo wp_kses_post( $this->attachment_data( $data, $attachment_id ) );
		}
	}

	/**
	 * Add column in the attachment submit area.
	 * 
	 * @since   1.13.0
	 */
	public function add_attachment_submitbox_misc_actions() {
		global $post;

		if ( $this->quickwebp_plugin_activated ) {
			return;
		}

		if ( ! $this->valid_mimetype( $post->ID ) ) {
			return;
		}

		$data      = get_post_meta( $post->ID, $this->meta_data, true );
		$has_error = get_post_meta( $post->ID, $this->meta_has_error, true );

		if ( ! is_array( $data ) ) {
			echo '<table><tr><td><div><strong>WPMasterToolkit</strong></div>';
			echo wp_kses_post( $this->optimize_btn( $post->ID ) );

			if ( ! empty( $has_error ) ) {
				echo esc_html__( 'Error attempting to optimize this image', 'wpmastertoolkit' );
			}

			echo '</td></tr></table>';
		} else {
			echo '<table><tr><td><div><strong>WPMasterToolkit</strong></div>' . wp_kses_post( $this->attachment_data( $data, $post->ID ) ) . '</td></tr></table>';
		}
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since   1.13.0
	 */
	public function enqueue_scripts_styles( $suffix ) {
		global $post_type;

		if ( 'upload.php' == $suffix || ( 'post.php' == $suffix && 'attachment' == $post_type ) ) {
			$submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/media-encoder-wpmedia.asset.php' );
			wp_enqueue_style( 'WPMastertoolkit_wpmedia', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-encoder-wpmedia.css', array(), $submenu_assets['version'], 'all' );
			wp_enqueue_script( 'WPMastertoolkit_wpmedia', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-encoder-wpmedia.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
			wp_localize_script( 'WPMastertoolkit_wpmedia', 'WPMastertoolkit_media_encoder_wpmedia', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( $this->nonce_action ),
			));
		}
	}

	/**
	 * Register cron jobs
	 * 
	 * @since   1.13.0
	 */
	public function crons_registrations( $schedules ) {
		$schedules[ $this->cron_recurrence_bulk ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'wpmastertoolkit' )
		);

		$schedules[ $this->cron_recurrence_migrate ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'wpmastertoolkit' )
		);

		return $schedules;
	}

	/**
	 * Execute the cron job to optimize images
	 * 
	 * @since   1.13.0
	 */
	public function excute_bulk_cron() {
		$time_start = microtime(true);
		$media_ids  = $this->get_unoptimized_media_ids();

		if ( empty( $media_ids ) ) {
			$this->end_cron_job();
		}

		$status = get_option( $this->option_bulk_status, 'finish' );
		if ( $status != 'running' ) {
			$this->end_cron_job();
		}

		$current = (int)get_option( $this->option_bulk_current, 0 );

		foreach ( $media_ids as $id ) {
			if ( $time_start + 55 < microtime(true) ) {
				exit;
			}

			$sizes     = $this->get_media_files( $id );
    		$new_sizes = array();

			foreach ( $sizes as $key => $size ) {
				$result = $this->optimize_local_file( $size );
		
				if ( $result ) {
					$new_sizes[$key] = $result;
				}
			}

			if ( ! empty( $new_sizes ) ) {
				update_post_meta( $id, $this->meta_already_optimized, '1' );
				update_post_meta( $id, $this->meta_data, $new_sizes );
			}

			$current++;
			update_option( $this->option_bulk_current, $current );
		}

		$this->end_cron_job();
	}

	/**
	 * Execute the cron job to migrate images
	 */
	public function excute_migrate_cron() {
		$time_start = microtime(true);
		$media_ids  = $this->get_not_migrated_media_ids();

		if ( empty( $media_ids ) ) {
			$this->end_cron_job_migration();
		}

		$status = get_option( $this->option_migrate_status, 'finish' );
		if ( $status != 'running' ) {
			$this->end_cron_job_migration();
		}

		$current = (int)get_option( $this->option_migrate_current, 0 );

		foreach ( $media_ids as $id ) {
			if ( $time_start + 55 < microtime(true) ) {
				exit;
			}

			$already_optimized_quickwebp = get_post_meta( $id, $this->meta_already_optimized_quickwebp, true );
			$data_quickweb               = get_post_meta( $id, $this->meta_data_quickwebp, true );

			if ( $already_optimized_quickwebp === '1' && ! empty( $data_quickweb ) ) {
				update_post_meta( $id, $this->meta_already_optimized, '1' );
				update_post_meta( $id, $this->meta_data, $data_quickweb );
			}

			$current++;
			update_option( $this->option_migrate_current, $current );
		}

		$this->end_cron_job_migration();
	}

	/**
	 * Preview mode callback
	 * 
	 * @since   1.13.0
	 */
	public function preview_mode_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$file = count( $_FILES ) > 0 ? array_shift( $_FILES ) : array();
		if ( empty( $file ) ) {
			wp_send_json_error( __( 'No image uploaded, try again.', 'wpmastertoolkit' ) );
		}

		$this->get_ajax_settings();

		$image_file = $this->file_is_image( $file );
		if ( ! $image_file ) {
			wp_send_json_error( __( 'No image uploaded, try again.', 'wpmastertoolkit' ) );
		}

		$extension_to_use = $this->image_extension_loaded();
		if ( ! $image_file ) {
			wp_send_json_error( __( 'This library does not exist.', 'wpmastertoolkit' ) );
		}

		$manager = new ImageManager( array( 'driver' => $extension_to_use ) );
		$image   = $manager->make( $image_file['tmp_name'] );
		$quality = $this->get_quality( $image_file['tmp_name'] );

		$image->sharpen( $this->settings['sharpen'] );
		$image->save( $image_file['tmp_name'], $quality, 'webp' );

		$image_file['new_size'] = $image->filesize();
		$image_file['new_type'] = $image->mime();

		$return = array(
			'size'          => $image_file['size'],
			'type'          => $this->type_from_mime_type( $image_file['type'] ),
			'mime_type'     => $image_file['type'],
			'image'         => 'data:' . $image_file['new_type'].';base64,' . base64_encode( file_get_contents( $image_file['tmp_name'] ) ),
			'name'          => $image_file['name'],
			'new_size'      => $image_file['new_size'],
			'new_type'      => $this->type_from_mime_type( $image_file['new_type'] ),
			'new_mime_type' => $image_file['new_type'],
		);
		wp_send_json_success( $return );
	}

	/**
	 * Start bulk optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function start_bulk_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$media_ids = $this->get_unoptimized_media_ids();
		if ( empty( $media_ids ) ) {
			wp_send_json_error( __( 'No images to optimize.', 'wpmastertoolkit' ) );
		}

		$status = get_option( $this->option_bulk_status, 'finish' );
		if ( $status != 'finish' ) {
			wp_send_json_error( __( 'Bulk optimization is already running.', 'wpmastertoolkit' ) );
		}

		if ( ! wp_next_scheduled( $this->cron_hook_bulk ) ) {
			wp_schedule_event( time(), $this->cron_recurrence_bulk, $this->cron_hook_bulk );

			$total 		= count( $media_ids );
			$current	= 0;

			update_option( $this->option_bulk_total, $total );
			update_option( $this->option_bulk_current, $current );
			update_option( $this->option_bulk_status, 'running' );

			$data = array(
				'progress'	=> $current . '/' . $total,
				'percent'	=> $total ? round( abs( ( $current / $total ) ) * 100 ) . '%' : '0%'
			);
			wp_send_json_success( $data );
		}

		wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
	}

	/**
	 * Stop bulk optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function stop_bulk_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		if ( $this->clear_bulk_optimization() ) {
			wp_send_json_success( __( 'Bulk optimization stopped.', 'wpmastertoolkit' ) );
		}

		wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
	}

	/**
	 * Check the progress of bulk optimization
	 * 
	 * @since   1.13.0
	 */
	public function progress_bulk_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$status     = get_option( $this->option_bulk_status, '' );
		$total      = (int)get_option( $this->option_bulk_total, 0 );
		$current    = (int)get_option( $this->option_bulk_current, 0 );
		$is_running = $status == 'running' ? true : false;

		$data = array(
			'running'  => $is_running,
			'progress' => $current . '/' . $total,
			'percent'  => $total ? round( abs( ( $current / $total ) ) * 100 ) . '%' : '0%'
		);
		wp_send_json_success( $data );
	}

	/**
	 * Check the progress of bulk migration
	 * 
	 * @since   1.13.0
	 */
	public function progress_bulk_migration_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$status     = get_option( $this->option_migrate_status, '' );
		$total      = (int)get_option( $this->option_migrate_total, 0 );
		$current    = (int)get_option( $this->option_migrate_current, 0 );
		$is_running = $status == 'running' ? true : false;

		$data = array(
			'running'  => $is_running,
			'progress' => $current . '/' . $total,
			'percent'  => $total ? round( abs( ( $current / $total ) ) * 100 ) . '%' : '0%'
		);
		wp_send_json_success( $data );
	}

	/**
	 * Start single optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function start_single_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['attachment_id'] ) ) : false;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'No attachment id.', 'wpmastertoolkit' ) );
		}

		$already_optimized = get_post_meta( $attachment_id, $this->meta_already_optimized, true );
		if ( $already_optimized === '1' ) {
			wp_send_json_error( __( 'Already optimized.', 'wpmastertoolkit' ) );
		}

		$mime_types	= $this->allowed_mime_types;
		$index 		= array_search( 'image/webp', $mime_types );
		unset( $mime_types[$index] );
		$mime_type 	= get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime_type, $mime_types ) ) {
			wp_send_json_error( __( 'Not a valid image.', 'wpmastertoolkit' ) );
		}

		$sizes     = $this->get_media_files( $attachment_id );
		$new_sizes = array();

		foreach ( $sizes as $key => $size ) {
			$result = $this->optimize_local_file( $size );

			if ( $result ) {
				$new_sizes[$key] = $result;
			}
		}

		if ( ! empty( $new_sizes ) ) {
			update_post_meta( $attachment_id, $this->meta_already_optimized, '1' );
			update_post_meta( $attachment_id, $this->meta_data, $new_sizes );
			delete_post_meta( $attachment_id, $this->meta_has_error );
		} else {
			update_post_meta( $attachment_id, $this->meta_has_error, '1' );
		}

		$html = $this->attachment_data( $new_sizes, $attachment_id );
		wp_send_json_success( $html );
	}

	/**
	 * Undo single optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function undo_single_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$attachment_id	= isset( $_POST['attachment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['attachment_id'] ) ) : false;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'No attachment id.', 'wpmastertoolkit' ) );
		}

		$already_optimized = get_post_meta( $attachment_id, $this->meta_already_optimized, true );
		if ( $already_optimized === '1' ) {
			$this->remove_related_files( $attachment_id );
			delete_post_meta( $attachment_id, $this->meta_already_optimized );
			delete_post_meta( $attachment_id, $this->meta_data );

			$html = $this->optimize_btn( $attachment_id );
			wp_send_json_success( $html );
		} else {
			wp_send_json_error( __( 'Not optimized.', 'wpmastertoolkit' ) );
		}
	}

	/**
	 * Migrate the data from QuickWebp to WPMasterToolkit
	 * 
	 * @since   1.13.0
	 */
	public function start_single_migration_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['attachment_id'] ) ) : false;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'No attachment id.', 'wpmastertoolkit' ) );
		}

		$already_optimized_quickwebp = get_post_meta( $attachment_id, $this->meta_already_optimized_quickwebp, true );
		$data_quickweb               = get_post_meta( $attachment_id, $this->meta_data_quickwebp, true );

		if ( $already_optimized_quickwebp === '1' && ! empty( $data_quickweb ) ) {
			update_post_meta( $attachment_id, $this->meta_already_optimized, '1' );
			update_post_meta( $attachment_id, $this->meta_data, $data_quickweb );

			$html = $this->attachment_data( $data_quickweb, $attachment_id );
		} else {
			$html = $this->optimize_btn( $attachment_id );
		}

		wp_send_json_success( $html );
	}

	/**
	 * Start bulk optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function start_bulk_migration_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$media_ids = $this->get_not_migrated_media_ids();
		if ( empty( $media_ids ) ) {
			wp_send_json_error( __( 'No images to migrate.', 'wpmastertoolkit' ) );
		}

		$status = get_option( $this->option_migrate_status, 'finish' );
		if ( $status != 'finish' ) {
			wp_send_json_error( __( 'Bulk migration is already running.', 'wpmastertoolkit' ) );
		}

		if ( ! wp_next_scheduled( $this->cron_hook_migrate ) ) {
			wp_schedule_event( time(), $this->cron_recurrence_migrate, $this->cron_hook_migrate );

			$total 		= count( $media_ids );
			$current	= 0;

			update_option( $this->option_migrate_total, $total );
			update_option( $this->option_migrate_current, $current );
			update_option( $this->option_migrate_status, 'running' );

			$data = array(
				'progress'	=> $current . '/' . $total,
				'percent'	=> $total ? round( abs( ( $current / $total ) ) * 100 ) . '%' : '0%'
			);
			wp_send_json_success( $data );
		}

		wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
	}

	/**
	 * Stop bulk optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function stop_bulk_migration_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		if ( $this->clear_bulk_migration() ) {
			wp_send_json_success( __( 'Bulk migration stopped.', 'wpmastertoolkit' ) );
		}

		wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
	}

	/**
	 * Start buffering the page content
	 * 
	 * @since   1.13.0
	 */
	public function start_content_process() {
		$this->settings = $this->get_settings();

		if ( $this->quickwebp_plugin_activated ) {
			return;
		}

		if ( 'picture' != $this->settings['display_webp_mode']['value'] ) {
			return;
		}

		ob_start( array( $this, 'maybe_process_buffer' ) );
	}

	/**
     * Render the submenu
     * 
     * @since   1.13.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/media-encoder.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-encoder.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-encoder.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'WPMastertoolkit_media_encoder', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( $this->nonce_action ),
		));

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
	 * Content of the .htaccess file
	 * 
	 * @since   1.13.0
	 */
	private function get_raw_content_htaccess() {
		$home_root = wp_parse_url( home_url( '/' ) );
		$home_root = $home_root['path'];

		$content  = "<IfModule mod_setenvif.c>";
		$content .= "\n\tSetEnvIf Request_URI \"\.(jpg|jpeg|jpe|png)$\" REQUEST_image";
		$content .= "\n</IfModule>";
		$content .= "\n<IfModule mod_rewrite.c>";
		$content .= "\n\tRewriteEngine On";
		$content .= "\n\tRewriteBase $home_root";
		$content .= "\n\tRewriteCond %{HTTP_ACCEPT} image/webp";
		$content .= "\n\tRewriteCond %{REQUEST_FILENAME}.webp -f";
		$content .= "\n\tRewriteRule (.+)\.(jpg|jpeg|jpe|png)$ $1.$2.webp [T=image/webp,NC]";
		$content .= "\n</IfModule>";
		$content .= "\n<IfModule mod_headers.c>";
		$content .= "\n\tHeader append Vary Accept env=REQUEST_image";
		$content .= "\n</IfModule>";
		$content .= "\n<IfModule mod_mime.c>";
		$content .= "\n\tAddType image/webp .webp";
		$content .= "\n</IfModule>";

        return trim( $content );
    }

	/**
     * Content of the nginx.conf file
	 * 
	 * @since   1.13.0
     */
    private function get_raw_content_nginx() {
		$home_root = wp_parse_url( home_url( '/' ) );
		$home_root = $home_root['path'];

        $content  = "location ~* ^($home_root.+)\\\.(jpg|jpeg|jpe|png)$ {";
		$content .= "\n\tadd_header Vary Accept;";
		$content .= "\n\n\tif (\$http_accept ~* \"webp\"){";
		$content .= "\n\t\tset \$imwebp A;";
		$content .= "\n\t}";
		$content .= "\n\tif (-f \$request_filename.webp) {";
		$content .= "\n\t\tset \$imwebp  \"\${imwebp}B\";";
		$content .= "\n\t}";
		$content .= "\n\tif (\$imwebp = AB) {";
		$content .= "\n\t\trewrite ^(.*) $1.webp;";
		$content .= "\n\t}";
		$content .= "\n}";

        return trim( $content );
    }

	/**
     * Maybe process the page content
	 * 
	 * @since   1.13.0
     */
	private function maybe_process_buffer( $buffer ) {
        if ( ! $this->is_html( $buffer ) ) {
            return $buffer;
        }

        if ( strlen( $buffer ) <= 255 ) {
			return $buffer;
		}

        $buffer = $this->process_content( $buffer );
        return $buffer;
    }

	/**
     * Tell if a content is HTML
	 * 
	 * @since   1.13.0
     */
    private function is_html( $content ) {
		return preg_match( '/<\/html>/i', $content );
	}

	/**
     * Process the content
	 * 
	 * @since   1.13.0
     */
    private function process_content( $content ) {
		$html_no_picture_tags = $this->remove_picture_tags( $content );
        $images               = $this->get_images( $html_no_picture_tags );

        if ( ! $images ) {
            return $content;
        }

        foreach ( $images as $image ) {
			$tag     = $this->build_picture_tag( $image );
			$content = str_replace( $image['tag'], $tag, $content );
		}

        return $content;
    }

	/**
     * Remove pre-existing <picture> tags.
	 * 
	 * @since   1.13.0
     */
    private function remove_picture_tags( $html ) {
		$replace = preg_replace( '#<picture[^>]*>.*?<\/picture\s*>#mis', '', $html );

		if ( null !== $replace ) {
			return $html;
		}

		return $replace;
	}

	/**
     * Get a list of images in a content.
	 * 
	 * @since   1.13.0
     */
    private function get_images( $content ) {
		$content = preg_replace( '/<!--(.*)-->/Uis', '', $content );

		if ( ! preg_match_all( '/<img\s.*>/isU', $content, $matches ) ) {
			return [];
		}

        $images = array_map( array( $this, 'process_image' ), $matches[0] );
        $images = array_filter( $images );

        if ( ! $images || ! is_array( $images ) ) {
			return [];
		}

		foreach ( $images as $i => $image ) {
            if ( empty( $image['src']['webp_exists'] ) || empty( $image['src']['webp_url'] ) ) {
				unset( $images[ $i ] );
				continue;
			}

			unset( $images[ $i ]['src']['webp_path'], $images[ $i ]['src']['webp_exists'] );

            if ( empty( $image['srcset'] ) || ! is_array( $image['srcset'] ) ) {
				unset( $images[ $i ]['srcset'] );
				continue;
			}

            foreach ( $image['srcset'] as $j => $srcset ) {

				if ( ! is_array( $srcset ) ) {
					continue;
				}

				if ( empty( $srcset['webp_exists'] ) || empty( $srcset['webp_url'] ) ) {
					unset( $images[ $i ]['srcset'][ $j ]['webp_url'] );
				}

				unset( $images[ $i ]['srcset'][ $j ]['webp_path'], $images[ $i ]['srcset'][ $j ]['webp_exists'] );
			}
        }

        return $images;
	}

	/**
     * Process an image tag and get an array containing some data.
	 * 
	 * @since   1.13.0
     */
	private function process_image( $image ) {
		$atts_pattern = '/(?<name>[^\s"\']+)\s*=\s*(["\'])\s*(?<value>.*?)\s*\2/s';

        if ( ! preg_match_all( $atts_pattern, $image, $tmp_attributes, PREG_SET_ORDER ) ) {
			// No attributes?
			return false;
		}

        $attributes = [];

        foreach ( $tmp_attributes as $attribute ) {
			$attributes[ $attribute['name'] ] = $attribute['value'];
		}

        if ( ! empty( $attributes['class'] ) && strpos( $attributes['class'], 'wpmastertoolkit-no-webp' ) !== false ) {
			// Has the 'wpmastertoolkit-no-webp' class.
			return false;
		}

        // Deal with the src attribute.
		$src_source = false;

		foreach ( [ 'data-lazy-src', 'data-src', 'src' ] as $src_attr ) {
			if ( ! empty( $attributes[ $src_attr ] ) ) {
				$src_source = $src_attr;
				break;
			}
		}

        if ( ! $src_source ) {
			// No src attribute.
			return false;
		}

        $extensions = 'jpg|jpeg|jpe|png';

        if ( ! preg_match( '@^(?<src>(?:(?:https?:)?//|/).+\.(?<extension>' . $extensions . '))(?<query>\?.*)?$@i', $attributes[ $src_source ], $src ) ) {
			// Not a supported image format.
			return false;
		}

		$webp_url  = $src['src'] . '.webp';
		$webp_path = $this->url_to_path( $webp_url );
        $webp_url .= ! empty( $src['query'] ) ? $src['query'] : '';

        $data = [
			'tag'              => $image,
			'attributes'       => $attributes,
			'src_attribute'    => $src_source,
			'src'              => [
				'url'         => $attributes[ $src_source ],
				'webp_url'    => $webp_url,
				'webp_path'   => $webp_path,
				'webp_exists' => $webp_path && @file_exists( $webp_path )
			],
			'srcset_attribute' => false,
			'srcset'           => []
		];

        // Deal with the srcset attribute.
		$srcset_source = false;

		foreach ( [ 'data-lazy-srcset', 'data-srcset', 'srcset' ] as $srcset_attr ) {
			if ( ! empty( $attributes[ $srcset_attr ] ) ) {
				$srcset_source = $srcset_attr;
				break;
			}
		}

        if ( $srcset_source ) {
			$data['srcset_attribute'] = $srcset_source;

			$srcset = explode( ',', $attributes[ $srcset_source ] );

            foreach ( $srcset as $srcs ) {
                $srcs = preg_split( '/\s+/', trim( $srcs ) );

                if ( count( $srcs ) > 2 ) {
					// Not a good idea to have space characters in file name.
					$descriptor = array_pop( $srcs );
					$srcs       = [ implode( ' ', $srcs ), $descriptor ];
				}

                if ( empty( $srcs[1] ) ) {
					$srcs[1] = '1x';
				}

                if ( ! preg_match( '@^(?<src>(?:https?:)?//.+\.(?<extension>' . $extensions . '))(?<query>\?.*)?$@i', $srcs[0], $src ) ) {
					// Not a supported image format.
					$data['srcset'][] = [
						'url'        => $srcs[0],
						'descriptor' => $srcs[1],
					];
					continue;
				}

                $webp_url  = $src['src'] . '.webp';
				$webp_path = $this->url_to_path( $webp_url );
				$webp_url .= ! empty( $src['query'] ) ? $src['query'] : '';

                $data['srcset'][] = [
					'url'         => $srcs[0],
					'descriptor'  => $srcs[1],
					'webp_url'    => $webp_url,
					'webp_path'   => $webp_path,
					'webp_exists' => $webp_path && @file_exists( $webp_path )
				];
            }
        }
        
        if ( ! $data || ! is_array( $data ) ) {
            return false;
        }

        if ( ! isset( $data['tag'], $data['attributes'], $data['src_attribute'], $data['src'], $data['srcset_attribute'], $data['srcset'] ) ) {
            return false;
        }

        return $data;
    }

	/**
     * Convert a file URL to an absolute path.
	 * 
	 * @since   1.13.0
     */
    private function url_to_path( $url ) {
        static $uploads_url;
		static $uploads_dir;
		static $root_url;
		static $root_dir;
		static $domain_url;

        if ( ! isset( $uploads_url ) ) {
            $uploads = wp_upload_dir();
            $uploads_url = false;
            $uploads_dir = false;

            if ( false === $uploads['error'] ) {
                $uploads_url = set_url_scheme( trailingslashit( $uploads['baseurl'] ) );
                $uploads_dir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
            }

            $current_network = false;
            if ( function_exists( 'get_network' ) ) {
                $current_network = get_network();
            } elseif ( function_exists( 'get_current_site' ) ) {
                $current_network = get_current_site();
            }

            if ( ! is_multisite() || is_main_site() || ! $current_network ) {
                $root_url = home_url( '/' );
            } else {

                $root_url = is_ssl() ? 'https' : 'http';
                $root_url = set_url_scheme( 'http://' . $current_network->domain . $current_network->path, $root_url );
                $root_url = set_url_scheme( trailingslashit( $root_url ) );
            }

            $home    = set_url_scheme( untrailingslashit( get_option( 'home' ) ), 'http' );
		    $siteurl = set_url_scheme( untrailingslashit( get_option( 'siteurl' ) ), 'http' );

            if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {

                $wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
                $pos                 = strripos( str_replace( '\\', '/', ABSPATH ), trailingslashit( $wp_path_rel_to_home ) );
                $root_path           = substr( ABSPATH, 0, $pos );
                $root_dir            = trailingslashit( wp_normalize_path( $root_path ) );

            } elseif ( ! defined( 'PATH_CURRENT_SITE' ) || ! is_multisite() || is_main_site()) {

                $root_dir = trailingslashit( wp_normalize_path( ABSPATH ) );

            } else {

				$document_root     = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ?? '' ) );
                $document_root     = realpath( $document_root );
                $document_root     = trailingslashit( str_replace( '\\', '/', $document_root ) );
                $path_current_site = trim( str_replace( '\\', '/', PATH_CURRENT_SITE ), '/' );
                $root_dir          = trailingslashit( wp_normalize_path( $document_root . $path_current_site ) );
            }

            $domain_url  = wp_parse_url( $root_url );

            if ( ! empty( $domain_url['scheme'] ) && ! empty( $domain_url['host'] ) ) {
				$domain_url = $domain_url['scheme'] . '://' . $domain_url['host'] . '/';
			} else {
				$domain_url = false;
			}

        }

        // Get the right URL format.
		if ( $domain_url && strpos( $url, '/' ) === 0 ) {
			// URL like `/path/to/image.jpg.webp`.
			$url = $domain_url . ltrim( $url, '/' );
		}

        $url = set_url_scheme( $url );

        // Return the path.
		if ( stripos( $url, $uploads_url ) === 0 ) {
			return str_ireplace( $uploads_url, $uploads_dir, $url );
		}

        if ( stripos( $url, $root_url ) === 0 ) {
			return str_ireplace( $root_url, $root_dir, $url );
		}

		return false;
    }

	/**
     * Build a <picture> tag to insert.
	 * 
	 * @since   1.13.0
     */
    private function build_picture_tag( $image ) {
        $to_remove = array(
			'alt'              => '',
			'height'           => '',
			'width'            => '',
			'data-lazy-src'    => '',
			'data-src'         => '',
			'src'              => '',
			'data-lazy-srcset' => '',
			'data-srcset'      => '',
			'srcset'           => '',
			'data-lazy-sizes'  => '',
			'data-sizes'       => '',
			'sizes'            => '',
		);

        $attributes = array_diff_key( $image['attributes'], $to_remove );

        /**
		 * Remove Gutenberg specific attributes from picture tag, leave them on img tag.
		 * Optional: $attributes['class'] = 'imagify-webp-cover-wrapper'; for website admin styling ease.
		 */
		if ( ! empty( $image['attributes']['class'] ) && strpos( $image['attributes']['class'], 'wp-block-cover__image-background' ) !== false ) {
			unset( $attributes['style'] );
			unset( $attributes['class'] );
			unset( $attributes['data-object-fit'] );
			unset( $attributes['data-object-position'] );
		}

        $output = '<picture' . $this->build_attributes( $attributes ) . ">\n";
        $output .= $this->build_source_tag( $image );
		$output .= $this->build_img_tag( $image );
		$output .= "</picture>\n";

        return $output;
    }

	/**
     * Create HTML attributes from an array.
	 * 
	 * @since   1.13.0
     */
    private function build_attributes( $attributes ) {
		if ( ! $attributes || ! is_array( $attributes ) ) {
			return '';
		}

		$out = '';

		foreach ( $attributes as $attribute => $value ) {
			$out .= ' ' . $attribute . '="' . esc_attr( $value ) . '"';
		}

		return $out;
	}

	/**
     * Build the <source> tag to insert in the <picture>.
	 * 
	 * @since   1.13.0
     */
    private function build_source_tag( $image ) {
		$srcset_source = ! empty( $image['srcset_attribute'] ) ? $image['srcset_attribute'] : $image['src_attribute'] . 'set';
		$attributes    = [
			'type'         => 'image/webp',
			$srcset_source => [],
		];
        
		if ( ! empty( $image['srcset'] ) ) {
            foreach ( $image['srcset'] as $srcset ) {
                if ( empty( $srcset['webp_url'] ) ) {
                    continue;
				}
                
				$attributes[ $srcset_source ][] = $srcset['webp_url'] . ' ' . $srcset['descriptor'];
			}
		}

		if ( empty( $attributes[ $srcset_source ] ) ) {
			$attributes[ $srcset_source ][] = $image['src']['webp_url'];
		}

		$attributes[ $srcset_source ] = implode( ', ', $attributes[ $srcset_source ] );

		foreach ( [ 'data-lazy-srcset', 'data-srcset', 'srcset' ] as $srcset_attr ) {
			if ( ! empty( $image['attributes'][ $srcset_attr ] ) && $srcset_attr !== $srcset_source ) {
				$attributes[ $srcset_attr ] = $image['attributes'][ $srcset_attr ];
			}
		}

		if ( 'srcset' !== $srcset_source && empty( $attributes['srcset'] ) && ! empty( $image['attributes']['src'] ) ) {
			// Lazyload: the "src" attr should contain a placeholder (a data image or a blank.gif ).
			$attributes['srcset'] = $image['attributes']['src'];
		}

		foreach ( [ 'data-lazy-sizes', 'data-sizes', 'sizes' ] as $sizes_attr ) {
			if ( ! empty( $image['attributes'][ $sizes_attr ] ) ) {
				$attributes[ $sizes_attr ] = $image['attributes'][ $sizes_attr ];
			}
		}

		return '<source' . $this->build_attributes( $attributes ) . "/>\n";
	}

	/**
     * Build the <img> tag to insert in the <picture>.
	 * 
	 * @since   1.13.0
     */
    private function build_img_tag( $image ) {
		/**
		 * Gutenberg fix.
		 * Check for the 'wp-block-cover__image-background' class on the original image, and leave that class and style attributes if found.
		 */
		if ( ! empty( $image['attributes']['class'] ) && strpos( $image['attributes']['class'], 'wp-block-cover__image-background' ) !== false ) {
			$to_remove = [
				'id'     => '',
				'title'  => '',
			];

			$attributes = array_diff_key( $image['attributes'], $to_remove );
		} else {
			$to_remove = [
				'class'  => '',
				'id'     => '',
				'style'  => '',
				'title'  => '',
			];

			$attributes = array_diff_key( $image['attributes'], $to_remove );
		}

		return '<img' . $this->build_attributes( $attributes ) . "/>\n";
	}

	/**
	 * Check the mimetype of the file
	 * 
	 * @since   1.13.0
	 */
	private function valid_mimetype( $post_id ) {
		$mime_types = $this->allowed_mime_types;
		$index      = array_search( 'image/webp', $mime_types );
		unset( $mime_types[$index] );
		$mime_type  = get_post_mime_type( $post_id );

		return in_array( $mime_type, $mime_types );
	}

	/**
	 * Display optimize button in the media uploader
	 * 
	 * @since   1.13.0
	 */
	private function optimize_btn( $attachment_id ) {

		$quickweb_data            = get_post_meta( $attachment_id, $this->meta_data_quickwebp, true );
		$optimized_with_quickwebp = false;

		if ( is_array( $quickweb_data ) ) {
			$has_all_sizes = true;
			foreach ( $quickweb_data as $size ) {
				$path = $size['path'] ?? '';
				
				if ( empty( $path ) ) {
					$has_all_sizes = false;
					break;
				}

				if ( ! file_exists( $path ) ) {
					$has_all_sizes = false;
					break;
				}
			}

			if ( $has_all_sizes ) {
				$optimized_with_quickwebp = true;
			}
		}

		$class = 'wpmastertoolkit-single-optimization-btn';
		$text  = __( 'Optimize', 'wpmastertoolkit' );

		if ( $optimized_with_quickwebp ) {
			$class = 'wpmastertoolkit-single-migrate-btn';
			$text  = __( 'Migrate from quickWebP', 'wpmastertoolkit' );
		}

		ob_start();
			?>
				<button type="button" class="button button-sacondary <?php echo esc_attr( $class ); ?>" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
					<?php echo esc_html( $text ); ?>
					<div class="spinner"></div>
				</button>
				<div class="wpmastertoolkit-single-optimization-msg"></div>
			<?php
		return ob_get_clean();
	}

	/**
	 * Get all data to display for a specific media
	 * 
	 * @since   1.13.0
	 */
	private function attachment_data( $data, $attachment_id ) {
		$full_image_data = $data['full'] ?? array();

		if ( ! empty( $full_image_data ) ) {
			ob_start();
				?>
					<div>
						<strong><?php esc_html_e( 'Original Image: ', 'wpmastertoolkit' ); ?></strong>
						<span><?php echo esc_html( round( $full_image_data['original_size'] / 1024, 2 ) . 'KB' ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Webp: ', 'wpmastertoolkit' ); ?></strong>
						<span><?php echo esc_html( round( $full_image_data['optimized_size'] / 1024, 2 ) . 'KB' ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Save: ', 'wpmastertoolkit' ); ?></strong>
						<span><?php echo esc_html( $full_image_data['percent'] . '%' ); ?></span>
					</div>
					<div>
						<button type="button" class="button button-sacondary wpmastertoolkit-undo-single-optimization-btn" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
							<?php esc_html_e( 'Undo optimization', 'wpmastertoolkit' ); ?>
							<div class="spinner"></div>
						</button>
						<div class="wpmastertoolkit-undo-single-optimization-msg"></div>
					</div>
				<?php
			return ob_get_clean();
		}
	}

	/**
	 * End cron job
	 * 
	 * @since   1.13.0
	 */
	private function end_cron_job() {
		$this->clear_bulk_optimization();
		exit;
	}

	/**
	 * Clear bulk optimization
	 * 
	 * @since   1.13.0
	 */
	private function clear_bulk_optimization() {
		update_option( $this->option_bulk_status, 'finish' );

		if ( wp_next_scheduled( $this->cron_hook_bulk ) ) {
			wp_clear_scheduled_hook( $this->cron_hook_bulk );
			return true;
		}

		return false;
	}

	/**
	 * End cron job for migration
	 * 
	 * @since   1.13.0
	 */
	private function end_cron_job_migration() {
		$this->clear_bulk_migration();
		exit;
	}
	
	/**
	 * Clear bulk optimization
	 * 
	 * @since   1.13.0
	 */
	private function clear_bulk_migration() {
		update_option( $this->option_migrate_status, 'finish' );

		if ( wp_next_scheduled( $this->cron_hook_migrate ) ) {
			wp_clear_scheduled_hook( $this->cron_hook_migrate );
			return true;
		}

		return false;
	}

	/**
	 * Get the unoptimized media ids
	 * 
	 * @since   1.13.0
	 */
	private function get_unoptimized_media_ids() {
		$statuses = array(
			'inherit' => 'inherit',
			'private' => 'private',
		);
		$custom_statuses = get_post_stati( array( 'public' => true ) );
		unset( $custom_statuses['publish'] );
		if ( $custom_statuses ) {
			$statuses = array_merge( $statuses, $custom_statuses );
		}

		$mime_types	= $this->allowed_mime_types;
		$index 		= array_search( 'image/webp', $mime_types );
		unset( $mime_types[$index] );

		$media_ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => $mime_types,
			'post_status'    => array_keys( $statuses ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => $this->meta_has_error,
					'compare' => 'NOT EXISTS'
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => $this->meta_already_optimized,
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'     => $this->meta_already_optimized,
						'compare' => '=',
						'value'   => '0'
					)
				),
			),
		) );

		return $media_ids;
	}

	/**
	 * Get media ids that need migration
	 * 
	 * @since   1.13.0
	 */
	private function get_not_migrated_media_ids() {
		$statuses = array(
			'inherit' => 'inherit',
			'private' => 'private',
		);
		$custom_statuses = get_post_stati( array( 'public' => true ) );
		unset( $custom_statuses['publish'] );
		if ( $custom_statuses ) {
			$statuses = array_merge( $statuses, $custom_statuses );
		}

		$mime_types	= $this->allowed_mime_types;
		$index 		= array_search( 'image/webp', $mime_types );
		unset( $mime_types[$index] );

		$media_ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => $mime_types,
			'post_status'    => array_keys( $statuses ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => $this->meta_already_optimized,
					'compare' => 'NOT EXISTS'
				),
				array(
					'key'     => $this->meta_already_optimized_quickwebp,
					'compare' => '=',
					'value'   => '1'
				)
			),
		) );

		$media_has_file = array();
		foreach ( $media_ids as $media_id ) {
			$quickweb_data = get_post_meta( $media_id, $this->meta_data_quickwebp, true );
			
			if ( is_array( $quickweb_data ) ) {
				$has_all_sizes = true;
				foreach ( $quickweb_data as $size ) {
					$path = $size['path'] ?? '';
					
					if ( empty( $path ) ) {
						$has_all_sizes = false;
						break;
					}

					if ( ! file_exists( $path ) ) {
						$has_all_sizes = false;
						break;
					}
				}

				if ( $has_all_sizes ) {
					$media_has_file[] = $media_id;
				}
			}
		}

		return $media_has_file;
	}

	/**
	 * Get the type from the mime type
	 * 
	 * @since   1.13.0
	 */
	private function type_from_mime_type( $mime_type ) {
		$array = array(
			'image/jpeg' => 'JPEG',
			'image/png'  => 'PNG',
			'image/webp' => 'WebP'
		);

		return $array[$mime_type] ?? '';
	}

	/**
	 * Remove the related files of an optimized attachment
	 * 
	 * @since   1.13.0
	 */
	private function remove_related_files( $id ) {
		$data = get_post_meta( $id, $this->meta_data, true );
			
		if ( ! empty( $data ) ) {
			foreach ( $data as $value ) {
				$path = $value['path'] ?? '';

				if ( ! empty( $path ) && file_exists( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
	}

	/**
	 * Check if the file is an image
	 * 
	 * @since   1.13.0
	 */
	private function file_is_image( $file ) {
		$file_tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( empty( $file_tmp_name ) ) {
			return false;
		}

		$is_image  = wp_getimagesize( $file_tmp_name );
		$mime_type = wp_get_image_mime( $file_tmp_name );

		if ( ! $is_image || ! in_array( $mime_type, $this->allowed_mime_types ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Get the package used by the server
	 * 
	 * @since   1.13.0
	 */
	private function image_extension_loaded() {
		$this->settings = $this->get_settings();
		$library_to_use = $this->settings['library']['value'];

		if ( $library_to_use == 'gd' ) {
			if ( extension_loaded( 'gd' ) ) {
				$library_to_use = 'gd';
			} elseif ( extension_loaded( 'imagick' ) ) {
				$library_to_use = 'imagick';
			} else {
				$library_to_use = false;
			}
		}

		if ( $library_to_use == 'imagick' ) {
			if ( extension_loaded( 'imagick' ) ) {
				$library_to_use = 'imagick';
			} elseif ( extension_loaded( 'gd' ) ) {
				$library_to_use = 'gd';
			} else {
				$library_to_use = false;
			}
		}
		
		return $library_to_use;
	}

	/**
	 * Get the quality
	 * 
	 * @since   1.13.0
	 */
	private function get_quality( $file ) {
		$this->settings = $this->get_settings();
		$ignore_webp    = $this->settings['ignore_webp'];
		$quality        = $this->settings['quality'];
		$mime_type      = wp_get_image_mime( $file );

		switch ( $mime_type ) {
			case 'image/webp':
				if ( $ignore_webp == '1' ) {
					$quality = 100;
				}
			break;
		}

		return $quality;
	}

	/**
	 * Get the list of media files
	 * 
	 * @since   1.13.0
	 */
	private function get_media_files( $media_id ) {
		$fullsize_path = get_attached_file( $media_id );

		if ( ! $fullsize_path ) {
			return array();
		}

		$media_data = wp_get_attachment_image_src( $media_id, 'full' );
		$file_type  = wp_check_filetype( $fullsize_path );

		$all_sizes  = array(
			'full' => array(
				'size'      => 'full',
				'path'      => $fullsize_path,
				'width'     => $media_data[1],
				'height'    => $media_data[2],
				'mime-type' => $file_type['type'],
				'disabled'  => false,
			),
		);

		$sizes = wp_get_attachment_metadata( $media_id, true );
		$sizes = ! empty( $sizes['sizes'] ) && is_array( $sizes['sizes'] ) ? $sizes['sizes'] : [];

		$dir_path = trailingslashit( dirname( $fullsize_path ) );

		foreach ( $sizes as $size => $size_data ) {
			$all_sizes[ $size ] = array(
				'size'      => $size,
				'path'      => $dir_path . $size_data['file'],
				'width'     => $size_data['width'],
				'height'    => $size_data['height'],
				'mime-type' => $size_data['mime-type'],
				'disabled'  => false
			);
		}

		return $all_sizes;
	}

	/**
	 * Optimize a local file
	 * 
	 * @since   1.13.0
	 */
	private function optimize_local_file( $size ) {
		$this->settings = $this->get_settings();

		$extension_to_use = $this->image_extension_loaded();
		if ( ! $extension_to_use ) {
			return false;
		}

		if ( ! is_file( $size['path'] ) ) {
			return false;
		}

		$real_type = mime_content_type( $size['path'] );
		if ( ! in_array( $real_type, $this->allowed_mime_types ) ) {
			return false;
		}

		try {
			$size_before = filesize( $size['path'] );
			$manager     = new ImageManager( array( 'driver' => $extension_to_use ) );
			$image       = $manager->make( $size['path'] );
			$quality     = $this->get_quality( $size['path'] );
			$webp_path   = $size['path'] . '.webp';
	
			$image->sharpen( $this->settings['sharpen'] );
			$image->save( $webp_path, $quality, 'webp' );

			$size_after = $image->filesize();
			$image->destroy();
	
			$deference = $size_before - $size_after;
			$percent   = $deference / $size_before * 100;
	
			return array(
				'success'        => 1,
				'original_size'  => $size_before,
				'optimized_size' => $size_after,
				'percent'        => round( $percent, 2 ),
				'path'           => $webp_path
			);
		} catch ( \Throwable $th ) {
			return false;
		}
	}

	/**
	 * Get settings values from ajax
	 * 
	 * @since   1.13.0
	 */
	private function get_ajax_settings() {
		$this->settings = $this->get_settings();

		//phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$settings = isset( $_POST['settings'] ) ? sanitize_text_field( $_POST['settings'] ) : array();
		$settings = json_decode( stripslashes( $settings ), true );

		foreach ( $settings as $settings_key => $settings_value ) {
			switch ( $settings_key ) {
				case 'library':
					$this->settings[$settings_key]['value'] = $settings_value['value'];
				break;
				default:
					$this->settings[ $settings_key ] = $settings_value;
				break;
			}
        }
	}

	/**
     * sanitize_settings
     * 
     * @since   1.13.0
     * @return array
     */
    private function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			switch ( $settings_key ) {
				case 'library':
				case 'display_webp_mode':
					$sanitized_settings[$settings_key]['value'] = sanitize_text_field( $new_settings[$settings_key]['value'] ?? $this->default_settings[$settings_key]['value'] );
				break;
				default:
					$sanitized_settings[ $settings_key ] = sanitize_text_field( $new_settings[ $settings_key ] ?? $settings_value );
				break;
			}
        }

        return $sanitized_settings;
    }

	/**
     * get_settings
	 * 
	 * @since   1.13.0
     */
    private function get_settings(){
		if ( $this->settings !== null ) {
			return $this->settings;
		}

        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   1.13.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
	 * @since   1.13.0
     * @return array
     */
    private function get_default_settings(){

        if ( $this->default_settings !== null ) {
			return $this->default_settings;
		}

        return array(
			'enabled'           => '1',
			'quality'           => '75',
			'sharpen'           => '0',
			'ignore_webp'       => '1',
			'save_original'     => '0',
			'library'           => array(
				'value'   => 'gd',
				'options' => array(
					'gd'      => __( 'GD', 'wpmastertoolkit' ),
					'imagick' => __( 'Imagick', 'wpmastertoolkit' ),
				),
			),
			'display_webp_mode' => array(
				'value'   => 'disabled',
				'options' => array(
					'disabled' => __( 'Deactivate', 'wpmastertoolkit' ),
					'picture'  => __( 'Use <picture> tags', 'wpmastertoolkit' ),
					'rewrite'  => __( 'Use rewrite rules', 'wpmastertoolkit' ),
				),
			),
        );
    }

	/**
     * Add the submenu content
     * 
     * @since   1.13.0
     * @return void
     */
    private function submenu_content() {

		if ( $this->quickwebp_plugin_activated ) {
			?>
				<div class="wp-mastertoolkit__section">
					<div class="wp-mastertoolkit__section__notice show">
						<p class="wp-mastertoolkit__section__notice__message">
							<?php 
							echo wp_kses_post( sprintf( 
								/* translators: %s: plugin name */
								__( "Please deactivate %s plugin.", 'wpmastertoolkit' ), 
								'<strong>QuickWebP - Compress / Optimize Images & Convert WebP | SEO Friendly</strong>' 
							) ); 
							?>
						</p>
					</div>
				</div>
			<?php
			return;
		}

        $this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$library_options           = $this->default_settings['library']['options'];
		$display_webp_mode_options = $this->default_settings['display_webp_mode']['options'];

        $enabled           = $this->settings['enabled'] ?? $this->default_settings['enabled'];
		$quality           = $this->settings['quality'] ?? $this->default_settings['quality'];
		$sharpen           = $this->settings['sharpen'] ?? $this->default_settings['sharpen'];
		$ignore_webp       = $this->settings['ignore_webp'] ?? $this->default_settings['ignore_webp'];
		$save_original     = $this->settings['save_original'] ?? $this->default_settings['save_original'];
		$library           = $this->settings['library']['value'] ?? $this->default_settings['library']['value'];
		$display_webp_mode = $this->settings['display_webp_mode']['value'] ?? $this->default_settings['display_webp_mode']['value'];

		$status     = get_option( $this->option_bulk_status, '' );
		$is_running = $status == 'running' ? true : false;
		$is_finish  = $status == 'finish' ? true : false;
		$total      = (int)get_option( $this->option_bulk_total, 0 );
		$current    = (int)get_option( $this->option_bulk_current, 0 );
		$percent    = $total ? round( abs( ( $current / $total ) ) * 100 ) . '%' : '0%';
		$progress   = $current . '/' . $total;

		$media_ids_to_migrate = $this->get_not_migrated_media_ids();
		$status_migration     = get_option( $this->option_migrate_status, '' );
		$is_running_migration = $status_migration == 'running' ? true : false;
		$is_finish_migration  = $status_migration == 'finish' ? true : false;
		$total_migration      = (int)get_option( $this->option_migrate_total, 0 );
		$current_migration    = (int)get_option( $this->option_migrate_current, 0 );
		$percent_migration    = $total_migration ? round( abs( ( $current_migration / $total_migration ) ) * 100 ) . '%' : '0%';
		$progress_migration   = $current_migration . '/' . $total_migration;


        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc">
					<?php esc_html_e( "Automatically converts images to WebP when they are uploaded to the media library.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Enable/disable image conversion to WEBP format', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<label class="wp-mastertoolkit__toggle">
								<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[enabled]' ); ?>" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[enabled]' ); ?>" value="1" <?php checked( $enabled, '1' ); ?>>
								<span class="wp-mastertoolkit__toggle__slider round"></span>
							</label>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Quality', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__rang-slider" style="width:300px;">
								<input type="range" name="<?php echo esc_attr( $this->option_id . '[quality]' ); ?>" value="<?php echo esc_attr( $quality ); ?>" min="0" max="100" step="1">
								<span class="value">
									<span class="value-num"><?php echo esc_html( $quality ); ?></span>
									<span class="value-unit">%</span>
								</span>
								<span class="progress"></span>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Sharpen', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__rang-slider" style="width:300px;">
								<input type="range" name="<?php echo esc_attr( $this->option_id . '[sharpen]' ); ?>" value="<?php echo esc_attr( $sharpen ); ?>" min="0" max="100" step="1">
								<span class="value">
									<span class="value-num"><?php echo esc_html( $sharpen ); ?></span>
									<span class="value-unit">%</span>
								</span>
								<span class="progress"></span>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Do not compress images already in WebP', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[ignore_webp]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[ignore_webp]' ); ?>" value="1" <?php checked( $ignore_webp, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Save original images', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[save_original]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[save_original]' ); ?>" value="1" <?php checked( $save_original, '1' ); ?>>
									<span class="mark"></span>
								</label>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Library to use', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'We use the GD library as the default option. However, if the GD library is not available, we will use Imagick instead.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[library][value]' ); ?>">
                                    <?php foreach ( $library_options as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $library, $key ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Display images in WebP format on the site', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'This option allows to override the original images by the webp version (useless for images converted in import)', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__radio">
								<?php foreach ( $display_webp_mode_options as $key => $name ) : ?>
									<label class="wp-mastertoolkit__radio__label">
										<input type="radio" name="<?php echo esc_attr( $this->option_id . '[display_webp_mode][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $display_webp_mode, $key ); ?>>
										<span class="mark"></span>
										<span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $name ); ?></span>
									</label>
								<?php endforeach; ?>
                            </div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Before saving, test your configuration with preview mode', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__button">
								<button type="button" class="wp-mastertoolkit__button__open-preview secondary"><?php esc_html_e( 'Preview', 'wpmastertoolkit' ); ?></button>
							</div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Bulk optimization', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">

							<div class="wp-mastertoolkit__section__bulk">
								<div class="wp-mastertoolkit__section__bulk__top">
									<div class="wp-mastertoolkit__button">
										<button type="button" class="secondary start <?php echo $is_running ? '' : 'show'; ?>">
											<?php esc_html_e( 'Start', 'wpmastertoolkit' ); ?>
											<span class="spinner"></span>
										</button>
									</div>

									<div class="wp-mastertoolkit__button">
										<button type="button" class="danger stop <?php echo $is_running ? 'show' : ''; ?>">
											<?php esc_html_e( 'Stop', 'wpmastertoolkit' ); ?>
										</button>
									</div>
								</div>

								<div class="wp-mastertoolkit__section__bulk__bottom">
									<div class="wp-mastertoolkit__section__bulk__progress <?php echo $is_running ? 'show' : ''; ?>">
										<div class="wp-mastertoolkit__section__bulk__progress__inner" style="width:<?php echo esc_attr( $percent ); ?>;"></div>
										<span class="wp-mastertoolkit__section__bulk__progress__progress"><?php echo esc_html( $progress ); ?></span>
									</div>

									<div class="wp-mastertoolkit__section__bulk__message description"></div>
								</div>
							</div>

                        </div>
                    </div>

					<?php if ( ! empty( $media_ids_to_migrate ) ) : ?>
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Bulk migration from QuickWebP', 'wpmastertoolkit' ); ?></div>
						<div class="wp-mastertoolkit__section__body__item__content">

							<div class="wp-mastertoolkit__section__migrate">
								<div class="wp-mastertoolkit__section__migrate__top">

									<div class="wp-mastertoolkit__button">
										<button type="button" class="secondary start <?php echo $is_running_migration ? '' : 'show'; ?>">
											<?php esc_html_e( 'Start', 'wpmastertoolkit' ); ?>
											<span class="spinner"></span>
										</button>
									</div>
									<div class="wp-mastertoolkit__button">
										<button type="button" class="danger stop <?php echo $is_running_migration ? 'show' : ''; ?>">
											<?php esc_html_e( 'Stop', 'wpmastertoolkit' ); ?>
										</button>
									</div>

								</div>
								<div class="wp-mastertoolkit__section__migrate__bottom">
									<div class="wp-mastertoolkit__section__migrate__progress <?php echo $is_running_migration ? 'show' : ''; ?>">
										<div class="wp-mastertoolkit__section__migrate__progress__inner" style="width:<?php echo esc_attr( $percent_migration ); ?>;"></div>
										<span class="wp-mastertoolkit__section__migrate__progress__progress"><?php echo esc_html( $progress_migration ); ?></span>
									</div>

									<div class="wp-mastertoolkit__section__migrate__message description"></div>
								</div>
							</div>

						</div>
					</div>
					<?php endif; ?>

                </div>

				<div class="wp-mastertoolkit__section__preview">
					<div class="wp-mastertoolkit__section__preview__file show">
						<div class="wp-mastertoolkit__section__preview__file__btn">
							<?php echo file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/add-img.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php esc_html_e( 'Add image', 'wpmastertoolkit' ); ?></span>
						</div>
						<input type="file" class="wp-mastertoolkit__section__preview__file__input" accept='image/*'>
					</div>

					<div class="wp-mastertoolkit__section__preview__compare">
						<div class="wp-mastertoolkit__section__preview__compare__images">
							<div class="wp-mastertoolkit__section__preview__compare__images__original">
								<div class="image"></div>
							</div>
							<div class="wp-mastertoolkit__section__preview__compare__images__new">
								<div class="image"></div>
							</div>
						</div>

						<div class="wp-mastertoolkit__section__preview__compare__handle">
							<div class="wp-mastertoolkit__section__preview__compare__handle__svg">
								<?php echo file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/resize.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<div class="wp-mastertoolkit__section__preview__compare__data">

							<div class="wp-mastertoolkit__section__preview__compare__data__original">
								<div class="wp-mastertoolkit__section__preview__compare__data__original__type"><?php esc_html_e( 'Original Image', 'wpmastertoolkit' ); ?></div>
								<div class="wp-mastertoolkit__section__preview__compare__data__original__size"></div>
							</div>

							<div class="wp-mastertoolkit__section__preview__compare__data__new">
								<div class="wp-mastertoolkit__section__preview__compare__data__new__type"></div>
								<div class="wp-mastertoolkit__section__preview__compare__data__new__size"></div>
								<div class="wp-mastertoolkit__section__preview__compare__data__new__gain"></div>
							</div>

						</div>
					</div>

					<div class="wp-mastertoolkit__section__preview__spiner">
						<div class="wp-mastertoolkit__section__preview__spiner__circle"></div>
					</div>

					<div class="wp-mastertoolkit__section__preview__close">
            			<button type="button" class="wp-mastertoolkit__section__preview__close__btn"><?php echo file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/times.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
        			</div>
				</div>
            </div>
        <?php
    }
}
