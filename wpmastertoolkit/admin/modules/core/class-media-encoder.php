<?php

use function Sodium\compare;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Media Encoder
 * Description: Automatically converts uploaded images to your selected format (WebP or AVIF) for better performance and reduced file size.
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

		$this->allowed_mime_types               = array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' );
		$this->quickwebp_plugin_activated       = false;

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_filter( 'wpmastertoolkit_nginx_code_snippets', array( $this, 'nginx_code_snippets' ) );

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'image_optimization' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'add_already_optimized_meta' ), 10, 3 );
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );
            $this->save_settings( $new_settings );

			if ( 'rewrite' == $new_settings['display_mode']['value'] ) {
				$this->add_to_htaccess();
			} else {
				$this->remove_from_htaccess();
			}

            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
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
			$this->settings         = $this->get_settings();
			$this->default_settings = $this->get_default_settings();
			$display_mode           = $this->settings['display_mode']['value'] ?? $this->default_settings['display_mode']['value'];

			if ( $display_mode == 'rewrite' ) {
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

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$save_original = $this->settings['save_original'] ?? $this->default_settings['save_original'];
		if ( '1' === $save_original ) {
			return $file;
		}

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		if ( 'off' === $mode_enabled ) {
			return $file;
		}

		$image_file = $this->file_is_image( $file );
		if ( ! $image_file ) {
			return $file;
		}
		
		$ignore_same_format = $this->settings['ignore_same_format'] ?? $this->default_settings['ignore_same_format'];
		$mime_type          = wp_get_image_mime( $file['tmp_name'] );
		if ( '1' === $ignore_same_format ) {
			if ( 'webp' == $mode_enabled && 'image/webp' == $mime_type ) {
				return $file;
			} elseif( 'avif' == $mode_enabled && 'image/avif' == $mime_type ) {
				return $file;
			}
		}

		$is_pro  = wpmastertoolkit_is_pro();
		$quality = $this->get_the_quality();

		if ( 'avif' === $mode_enabled && $is_pro ) {
			$avif_image = $this->create_avif_image( $file['tmp_name'], $file['tmp_name'], $quality );
			if ( $avif_image ) {
				$file['size']  = filesize( $file['tmp_name'] );
				$file['type']  = 'image/avif';
				$file['wpmtk'] = 'optimized';
			}
		}

		if ( 'webp' === $mode_enabled ) {
			$webp_image = $this->create_webp_image( $file['tmp_name'], $file['tmp_name'], $quality );
			if ( $webp_image ) {
				$file['size']  = filesize( $file['tmp_name'] );
				$file['type']  = 'image/webp';
				$file['wpmtk'] = 'optimized';
			}
		}

		return $file;
	}

	/**
	 * Add post meta to the attachment that already optimized
	 * 
	 * @since   2.5.0
	 */
	public function add_already_optimized_meta( $metadata, $attachment_id, $context ) {
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$wpmtk = sanitize_text_field( wp_unslash( $_FILES['async-upload']['wpmtk'] ?? '' ) );
		
		if ( 'optimized' == $wpmtk ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing
			$type = sanitize_text_field( wp_unslash( $_FILES['async-upload']['type'] ?? '' ) );

			if ( 'image/webp' == $type ) {
				update_post_meta( $attachment_id, $this->meta_already_optimized, '1' );
			} elseif ( 'image/avif' == $type ) {
				update_post_meta( $attachment_id, $this->meta_already_optimized, '2' );
			}
		}

		return $metadata;
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

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		if ( 'off' === $mode_enabled ) {
			return $metadata;
		}

		$save_original = $this->settings['save_original'] ?? $this->default_settings['save_original'];
		if ( '1' !== $save_original ) {
			return $metadata;
		}

		$ignore_same_format = $this->settings['ignore_same_format'] ?? $this->default_settings['ignore_same_format'];
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mime_type          = sanitize_text_field( wp_unslash( $_FILES['async-upload']['type'] ?? '' ) );
		if ( '1' === $ignore_same_format ) {
			if ( 'webp' == $mode_enabled && 'image/webp' == $mime_type ) {
				return $metadata;
			} elseif( 'avif' == $mode_enabled && 'image/avif' == $mime_type ) {
				return $metadata;
			}
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
			if ( 'webp' == $mode_enabled ) {
				update_post_meta( $attachment_id, $this->meta_already_optimized, '1' );
			} elseif ( 'avif' == $mode_enabled ) {
				update_post_meta( $attachment_id, $this->meta_already_optimized, '2' );
			}
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
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		if ( $this->quickwebp_plugin_activated ) {
			return $quality;
		}

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		if ( 'off' == $mode_enabled ) {
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
		$data = get_post_meta( $post_id, $this->meta_data, true );

		if ( ! empty( $data ) ) {
			$this->remove_related_files( $data );
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

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$html              = '';
		$status            = '0';
		$already_optimized = get_post_meta( $post->ID, $this->meta_already_optimized, true );
		$data              = get_post_meta( $post->ID, $this->meta_data, true );

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];

		if ( '1' == $already_optimized && ( 'webp' == $mode_enabled || 'off' == $mode_enabled ) ) {
			if ( is_array( $data ) ) {
				$status = '1';
			} else {
				$status = '2';
			}
		}

		if ( '2' == $already_optimized && ( 'avif' == $mode_enabled || 'off' == $mode_enabled ) ) {
			if ( is_array( $data ) ) {
				$status = '1';
			} else {
				$status = '2';
			}
		}

		switch ( $status ) {
			case '0':
				$html = $this->optimize_btn( $post->ID );
			break;
			case '1':
				$html = $this->attachment_data( $data, $post->ID );
			break;
			case '2':
				$html = '<br>' . esc_html__( 'Image already optimized.', 'wpmastertoolkit' );
			break;
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

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$status            = '0';
		$already_optimized = get_post_meta( $attachment_id, $this->meta_already_optimized, true );
		$data              = get_post_meta( $attachment_id, $this->meta_data, true );

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];

		if ( '1' == $already_optimized && ( 'webp' == $mode_enabled || 'off' == $mode_enabled ) ) {
			if ( is_array( $data ) ) {
				$status = '1';
			} else {
				$status = '2';
			}
		}

		if ( '2' == $already_optimized && ( 'avif' == $mode_enabled || 'off' == $mode_enabled ) ) {
			if ( is_array( $data ) ) {
				$status = '1';
			} else {
				$status = '2';
			}
		}

		switch ( $status ) {
			case '0':
				echo wp_kses_post( $this->optimize_btn( $attachment_id ) );
			break;
			case '1':
				echo wp_kses_post( $this->attachment_data( $data, $attachment_id ) );
			break;
			case '2':
				echo wp_kses_post( '<br>' . esc_html__( 'Image already optimized.', 'wpmastertoolkit' ) );
			break;
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

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$status            = '0';
		$already_optimized = get_post_meta( $post->ID, $this->meta_already_optimized, true );
		$data              = get_post_meta( $post->ID, $this->meta_data, true );

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];

		if ( '1' == $already_optimized && ( 'webp' == $mode_enabled || 'off' == $mode_enabled ) ) {
			if ( is_array( $data ) ) {
				$status = '1';
			} else {
				$status = '2';
			}
		}

		if ( '2' == $already_optimized && ( 'avif' == $mode_enabled || 'off' == $mode_enabled ) ) {
			if ( is_array( $data ) ) {
				$status = '1';
			} else {
				$status = '2';
			}
		}

		echo '<table><tr><td><div><strong>WPMasterToolkit</strong></div>';
		switch ( $status ) {
			case '0':
				echo wp_kses_post( $this->optimize_btn( $post->ID ) );
			break;
			case '1':
				echo wp_kses_post( $this->attachment_data( $data, $post->ID ) );
			break;
			case '2':
				echo esc_html__( 'Image already optimized.', 'wpmastertoolkit' );
			break;
		}
		echo '</td></tr></table>';
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
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
		
		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		if ( 'off' === $mode_enabled ) {
			$this->end_cron_job();
		}

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

				$data = get_post_meta( $id, $this->meta_data, true );
				if ( ! empty( $data ) ) {
					$this->remove_related_files( $data );
				}

				if ( 'webp' == $mode_enabled ) {
					update_post_meta( $id, $this->meta_already_optimized, '1' );
				} elseif ( 'avif' == $mode_enabled ) {
					update_post_meta( $id, $this->meta_already_optimized, '2' );
				}
				update_post_meta( $id, $this->meta_data, $new_sizes );
				delete_post_meta( $id, $this->meta_has_error );
			} else {
				update_post_meta( $id, $this->meta_has_error, '1' );
				delete_post_meta( $id, $this->meta_already_optimized );
				delete_post_meta( $id, $this->meta_data );
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

				delete_post_meta( $id, $this->meta_already_optimized_quickwebp );
				delete_post_meta( $id, $this->meta_data_quickwebp );
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

		$is_pro       = wpmastertoolkit_is_pro();
		$quality      = $this->get_the_quality();
		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];

		if ( 'avif' === $mode_enabled && $is_pro ) {
			$avif_image = $this->create_avif_image( $image_file['tmp_name'], $image_file['tmp_name'], $quality );
			if ( $avif_image ) {
				$image_file['new_size'] = filesize( $image_file['tmp_name'] );
				$image_file['new_type'] = 'image/avif';
			}
		}

		if ( 'webp' === $mode_enabled ) {
			$webp_image = $this->create_webp_image( $image_file['tmp_name'], $image_file['tmp_name'], $quality );
			if ( $webp_image ) {
				$image_file['new_size'] = filesize( $image_file['tmp_name'] );
				$image_file['new_type'] = 'image/webp';
			}
		}

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

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		if ( 'off' === $mode_enabled ) {
			wp_send_json_error( __( 'Choose an image format and save the settings.', 'wpmastertoolkit' ) );
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
				'progress' => $current . '/' . $total,
				'percent'  => $total ? round( abs( ( $current / $total ) ) * 100 ) . '%' : '0%'
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
	 * Start single optimization callback
	 * 
	 * @since   1.13.0
	 */
	public function start_single_cb() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		if ( 'off' === $mode_enabled ) {
			wp_send_json_error( __( 'Choose an image format and save the settings.', 'wpmastertoolkit' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['attachment_id'] ) ) : false;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'No attachment id.', 'wpmastertoolkit' ) );
		}

		$already_optimized = get_post_meta( $attachment_id, $this->meta_already_optimized, true );

		if ( '1' == $already_optimized && 'webp' == $mode_enabled ) {
			wp_send_json_error( __( 'Already optimized.', 'wpmastertoolkit' ) );
		}

		if ( '2' == $already_optimized && 'avif' == $mode_enabled ) {
			wp_send_json_error( __( 'Already optimized.', 'wpmastertoolkit' ) );
		}

		$post_mime_type = get_post_mime_type( $attachment_id );
		if ( ! in_array( $post_mime_type, $this->allowed_mime_types ) ) {
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

			$data = get_post_meta( $attachment_id, $this->meta_data, true );
			if ( ! empty( $data ) ) {
				$this->remove_related_files( $data );
			}

			if ( 'webp' == $mode_enabled ) {
				update_post_meta( $attachment_id, $this->meta_already_optimized, '1' );
			} elseif ( 'avif' == $mode_enabled ) {
				update_post_meta( $attachment_id, $this->meta_already_optimized, '2' );
			}
			update_post_meta( $attachment_id, $this->meta_data, $new_sizes );
			delete_post_meta( $attachment_id, $this->meta_has_error );
		} else {
			update_post_meta( $attachment_id, $this->meta_has_error, '1' );
			delete_post_meta( $attachment_id, $this->meta_already_optimized );
			delete_post_meta( $attachment_id, $this->meta_data );
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

		$attachment_id = isset( $_POST['attachment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['attachment_id'] ) ) : false;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'No attachment id.', 'wpmastertoolkit' ) );
		}

		$data = get_post_meta( $attachment_id, $this->meta_data, true );
		if ( ! empty( $data ) ) {
			$this->remove_related_files( $data );
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

			delete_post_meta( $attachment_id, $this->meta_already_optimized_quickwebp );
			delete_post_meta( $attachment_id, $this->meta_data_quickwebp );

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
	 * Start buffering the page content
	 * 
	 * @since   1.13.0
	 */
	public function start_content_process() {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		if ( $this->quickwebp_plugin_activated ) {
			return;
		}

		$display_mode = $this->settings['display_mode']['value'] ?? $this->default_settings['display_mode']['value'];
		if ( 'picture' != $display_mode ) {
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
	 * Add the module to htaccess file
	 * 
	 * @since   1.13.0
	 */
	private function add_to_htaccess() {
		global $is_apache;

        if ( $is_apache ) {
			$this->settings         = $this->get_settings();
			$this->default_settings = $this->get_default_settings();
			$display_mode           = $this->settings['display_mode']['value'] ?? $this->default_settings['display_mode']['value'];

			if ( $display_mode == 'rewrite' ) {
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
	 * Content of the .htaccess file
	 * 
	 * @since   1.13.0
	 */
	private function get_raw_content_htaccess() {
		$home_root = wp_parse_url( home_url( '/' ) );
		$home_root = $home_root['path'];

		$content  = "<IfModule mod_setenvif.c>";
		$content .= "\n\tSetEnvIf Request_URI \"\\.(jpg|jpeg|jpe|png)$\" REQUEST_image";
		$content .= "\n</IfModule>";

		$content .= "\n<IfModule mod_rewrite.c>";
		$content .= "\n\tRewriteEngine On";
		$content .= "\n\tRewriteBase $home_root";

		// Serve AVIF if browser supports it and file exists
		$content .= "\n\tRewriteCond %{HTTP_ACCEPT} image/avif";
		$content .= "\n\tRewriteCond %{REQUEST_FILENAME}.avif -f";
		$content .= "\n\tRewriteRule (.+)\\.(jpg|jpeg|jpe|png)$ $1.$2.avif [T=image/avif,NC,E=REQUEST_image:avif,L]";
	
		// Otherwise, serve WebP if browser supports it and file exists
		$content .= "\n\tRewriteCond %{HTTP_ACCEPT} image/webp";
		$content .= "\n\tRewriteCond %{REQUEST_FILENAME}.webp -f";
		$content .= "\n\tRewriteRule (.+)\\.(jpg|jpeg|jpe|png)$ $1.$2.webp [T=image/webp,NC,E=REQUEST_image:webp,L]";

		$content .= "\n</IfModule>";

		$content .= "\n<IfModule mod_headers.c>";
		$content .= "\n\tHeader append Vary Accept env=REQUEST_image";
		$content .= "\n</IfModule>";

		$content .= "\n<IfModule mod_mime.c>";
		$content .= "\n\tAddType image/webp .webp";
		$content .= "\n\tAddType image/avif .avif";
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

		$content  = "location ~* ^($home_root.+)\\.(jpg|jpeg|jpe|png)$ {";
		$content .= "\n\tadd_header Vary Accept;";
	
		// Check for AVIF support and file existence
		$content .= "\n\n\tif (\$http_accept ~* \"avif\") {";
		$content .= "\n\t\tset \$imavif A;";
		$content .= "\n\t}";
		$content .= "\n\tif (-f \$request_filename.avif) {";
		$content .= "\n\t\tset \$imavif \"\${imavif}B\";";
		$content .= "\n\t}";
		$content .= "\n\tif (\$imavif = AB) {";
		$content .= "\n\t\trewrite ^(.*) \$1.avif break;";
		$content .= "\n\t}";
	
		// Check for WebP support and file existence
		$content .= "\n\n\tif (\$http_accept ~* \"webp\") {";
		$content .= "\n\t\tset \$imwebp A;";
		$content .= "\n\t}";
		$content .= "\n\tif (-f \$request_filename.webp) {";
		$content .= "\n\t\tset \$imwebp \"\${imwebp}B\";";
		$content .= "\n\t}";
		$content .= "\n\tif (\$imwebp = AB) {";
		$content .= "\n\t\trewrite ^(.*) \$1.webp break;";
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

            if ( empty( $image['src'] ) ) {
				unset( $images[ $i ] );
				continue;
			}

            if ( empty( $image['srcset'] ) || ! is_array( $image['srcset'] ) ) {
				unset( $images[ $i ]['srcset'] );
				continue;
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

		$avif_url   = $src['src'] . '.avif';
		$webp_url   = $src['src'] . '.webp';
		$avif_path  = $this->url_to_path( $avif_url );
		$webp_path  = $this->url_to_path( $webp_url );
        $avif_url  .= ! empty( $src['query'] ) ? $src['query'] : '';
        $webp_url  .= ! empty( $src['query'] ) ? $src['query'] : '';
		$avif_exist = $avif_path && @file_exists( $avif_path );
		$webp_exist = $webp_path && @file_exists( $webp_path );
		$type       = $avif_exist ? 'image/avif' : ( $webp_exist ? 'image/webp' : '' );
		$new_url    = $avif_exist ? $avif_url : ( $webp_exist ? $webp_url : '' );

        $data = [
			'tag'              => $image,
			'attributes'       => $attributes,
			'src_attribute'    => $src_source,
			'src'              => [
				'url'         => $attributes[ $src_source ],
				'new_url'     => $new_url,
			],
			'srcset_attribute' => false,
			'srcset'           => [],
			'type'             => $type,
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

                $avif_url   = $src['src'] . '.avif';
                $webp_url   = $src['src'] . '.webp';
				$avif_path  = $this->url_to_path( $avif_url );
				$webp_path  = $this->url_to_path( $webp_url );
				$avif_url  .= ! empty( $src['query'] ) ? $src['query'] : '';
				$webp_url  .= ! empty( $src['query'] ) ? $src['query'] : '';
				$avif_exist = $avif_path && @file_exists( $avif_path );
				$webp_exist = $webp_path && @file_exists( $webp_path );
				$type       = $avif_exist ? 'image/avif' : ( $webp_exist ? 'image/webp' : '' );
				$new_url    = $avif_exist ? $avif_url : ( $webp_exist ? $webp_url : '' );

                $data['srcset'][] = [
					'url'        => $srcs[0],
					'descriptor' => $srcs[1],
					'new_url'    => $new_url,
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
			'type'         => $image['type'],
			$srcset_source => [],
		];
        
		if ( ! empty( $image['srcset'] ) ) {
            foreach ( $image['srcset'] as $srcset ) {
                if ( empty( $srcset['new_url'] ) ) {
                    continue;
				}
                
				$attributes[ $srcset_source ][] = $srcset['new_url'] . ' ' . $srcset['descriptor'];
			}
		}

		if ( empty( $attributes[ $srcset_source ] ) ) {
			$attributes[ $srcset_source ][] = $image['src']['new_url'];
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
	 * Check the mimetype of the post
	 * 
	 * @since   1.13.0
	 */
	private function valid_mimetype( $post_id ) {
		$post_mime_type = get_post_mime_type( $post_id );
		return in_array( $post_mime_type, $this->allowed_mime_types );
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
		$this->default_settings = $this->get_default_settings();
		
		$full_image_data = $data['full'] ?? array();
		$format          = $this->default_settings['mode_enabled']['options'][ $full_image_data['format'] ] ?? '';

		if ( ! empty( $full_image_data ) ) {
			ob_start();
				?>
					<div>
						<strong><?php esc_html_e( 'Original Image: ', 'wpmastertoolkit' ); ?></strong>
						<span><?php echo esc_html( round( $full_image_data['original_size'] / 1024, 2 ) . 'KB' ); ?></span>
					</div>
					<div>
						<strong><?php echo esc_html( $format ); ?>: </strong>
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
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$mode_enabled       = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		$ignore_same_format = $this->settings['ignore_same_format'] ?? $this->default_settings['ignore_same_format'];

		$statuses = array(
			'inherit' => 'inherit',
			'private' => 'private',
		);
		$custom_statuses = get_post_stati( array( 'public' => true ) );
		unset( $custom_statuses['publish'] );
		if ( $custom_statuses ) {
			$statuses = array_merge( $statuses, $custom_statuses );
		}

		$allowed_mime_types = $this->allowed_mime_types;
		if ( '1' == $ignore_same_format ) {
			if ( 'webp' == $mode_enabled ) {
				$webp_index = array_search( 'image/webp', $allowed_mime_types );
				unset( $allowed_mime_types[$webp_index] );
			} elseif ( 'avif' == $mode_enabled ) {
				$avif_index = array_search( 'image/avif', $allowed_mime_types );
				unset( $allowed_mime_types[$avif_index] );
			}
		}

		$meta_query_already_optimized = array(
			'relation' => 'OR',
			array(
				'key'     => $this->meta_already_optimized,
				'compare' => 'NOT EXISTS'
			),
		);

		if ( 'webp' == $mode_enabled ) {
			$meta_query_already_optimized[] = array(
				'key'     => $this->meta_already_optimized,
				'compare' => '!=',
				'value'   => '1',
			);
		} elseif ( 'avif' == $mode_enabled ) {
			$meta_query_already_optimized[] = array(
				'key'     => $this->meta_already_optimized,
				'compare' => '!=',
				'value'   => '2',
			);
		}

		$media_ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => $allowed_mime_types,
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
				$meta_query_already_optimized,
			),
		));

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

		$media_ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => $this->allowed_mime_types,
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
			'image/webp' => 'WebP',
			'image/avif' => 'AVIF',
		);

		return $array[$mime_type] ?? '';
	}

	/**
	 * Remove the related files of an optimized attachment
	 * 
	 * @since   1.13.0
	 */
	private function remove_related_files( $data ) {
		foreach ( $data as $value ) {
			$path = $value['path'] ?? '';

			if ( ! empty( $path ) && file_exists( $path ) ) {
				wp_delete_file( $path );
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
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		if ( ! is_file( $size['path'] ) ) {
			return false;
		}

		$real_type = mime_content_type( $size['path'] );
		if ( ! in_array( $real_type, $this->allowed_mime_types ) ) {
			return false;
		}

		$is_pro       = wpmastertoolkit_is_pro();
		$quality      = $this->get_the_quality();
		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		$size_before  = filesize( $size['path'] );
		$new_path     = $size['path'] . '.' . $mode_enabled;

		if ( 'avif' === $mode_enabled && $is_pro ) {
			$avif_image = $this->create_avif_image( $size['path'], $new_path, $quality );
			if ( $avif_image ) {
				$size_after = filesize( $new_path );
			}
		} elseif ( 'webp' === $mode_enabled ) {
			$webp_image = $this->create_webp_image( $size['path'], $new_path, $quality );
			if ( $webp_image ) {
				$size_after = filesize( $new_path );
			}
		}

		$deference = $size_before - $size_after;
		$percent   = $deference / $size_before * 100;

		return array(
			'success'        => 1,
			'original_size'  => $size_before,
			'optimized_size' => $size_after,
			'percent'        => round( $percent, 2 ),
			'path'           => $new_path,
			'format'         => $mode_enabled
		);
	}

	/**
	 * Get the quality
	 * 
	 * @since   2.5.0
	 */
	private function get_the_quality() {
		$this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();

		$result       = 50;
		$mode_enabled = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		$quality      = $this->settings['quality']['value'] ?? $this->default_settings['quality']['value'];

		switch ($quality) {
			case 'low':
				$result = 50;
				if ( 'avif' === $mode_enabled ) {
					$result = 30;
				}
			break;
			case 'medium':
				$result = 75;
				if ( 'avif' === $mode_enabled ) {
					$result = 50;
				}
			break;
			case 'high':
				$result = 90;
				if ( 'avif' === $mode_enabled ) {
					$result = 70;
				}
			break;
		}

		return $result;
	}

	/**
	 * Create the avif image
	 * 
	 * @since   2.5.0
	 */
	private function create_avif_image( $file_path, $output_path, $quality ) {
		$image     = false;
		$mime_type = wp_get_image_mime( $file_path );

		if ( 'image/avif' == $mime_type ) {
			$image = imagecreatefromavif( $file_path );
		} elseif ( 'image/webp' == $mime_type ) {
			$image = imagecreatefromwebp( $file_path );
		} elseif ( 'image/jpeg' == $mime_type ) {
			$image = imagecreatefromjpeg( $file_path );
		} elseif ( 'image/png' == $mime_type ) {
			$image = imagecreatefrompng( $file_path );
		}

		if ( ! $image ) {
			return false;
		}
		
		if ( ! imageistruecolor( $image ) ) {
			$truecolor = imagecreatetruecolor( imagesx( $image ), imagesy( $image ) );
	
			if ( $mime_type === 'image/png' ) {
				imagealphablending( $truecolor, false );
				imagesavealpha( $truecolor, true );
				$transparent = imagecolorallocatealpha( $truecolor, 0, 0, 0, 127 );
				imagefilledrectangle( $truecolor, 0, 0, imagesx( $image ), imagesy( $image ), $transparent );
			}
	
			imagecopy( $truecolor, $image, 0, 0, 0, 0, imagesx( $image ), imagesy( $image ) );
			$image = $truecolor;
		}

		$avif_image = imageavif( $image, $output_path, $quality );
		imagedestroy( $image );
		if ( ! $avif_image ) {
			return false;
		}

		return $avif_image;
	}
	
	/**
	 * Create the webp image
	 * 
	 * @since   2.5.0
	 */
	private function create_webp_image( $file_path, $output_path, $quality ) {
		$image     = false;
		$mime_type = wp_get_image_mime( $file_path );

		if ( 'image/avif' == $mime_type ) {
			$image = imagecreatefromavif( $file_path );
		} elseif ( 'image/webp' == $mime_type ) {
			$image = imagecreatefromwebp( $file_path );
		} elseif ( 'image/jpeg' == $mime_type ) {
			$image = imagecreatefromjpeg( $file_path );
		} elseif ( 'image/png' == $mime_type ) {
			$image = imagecreatefrompng( $file_path );
		}

		if ( ! $image ) {
			return false;
		}
		
		if ( ! imageistruecolor( $image ) ) {
			$truecolor = imagecreatetruecolor( imagesx( $image ), imagesy( $image ) );
	
			if ( $mime_type === 'image/png' ) {
				imagealphablending( $truecolor, false );
				imagesavealpha( $truecolor, true );
				$transparent = imagecolorallocatealpha( $truecolor, 0, 0, 0, 127 );
				imagefilledrectangle( $truecolor, 0, 0, imagesx( $image ), imagesy( $image ), $transparent );
			}
	
			imagecopy( $truecolor, $image, 0, 0, 0, 0, imagesx( $image ), imagesy( $image ) );
			$image = $truecolor;
		}

		$webp_image = imagewebp( $image, $output_path, $quality );
		imagedestroy( $image );
		if ( ! $webp_image ) {
			return false;
		}

		return $webp_image;
	}

	/**
	 * Get settings values from ajax
	 * 
	 * @since   1.13.0
	 */
	private function get_ajax_settings() {
		$this->settings = $this->get_settings();

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings = isset( $_POST['settings'] ) ? sanitize_text_field( wp_unslash( $_POST['settings'] ) ) : array();
		$settings = json_decode( stripslashes( $settings ), true );

		foreach ( $settings as $settings_key => $settings_value ) {
			switch ( $settings_key ) {
				case 'mode_enabled':
					$settings_value = $settings_value == 'off' ? 'webp' : $settings_value;
					$this->settings[$settings_key]['value'] = $settings_value;
				break;
				case 'quality':
					$this->settings[$settings_key]['value'] = $settings_value;
				break;
			}
        }
	}

	/**
     * Sanitize settings
     * 
     * @since   1.13.0
     * @return array
     */
    private function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
			switch ( $settings_key ) {
				case 'mode_enabled':
				case 'quality':
				case 'display_mode':
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
		$settings = get_option( $this->option_id, $this->default_settings );

		if ( ! isset( $settings['mode_enabled'] ) ) {
			$old_enabled = $settings['enabled'];
			$mode_enabled = 'webp';
			if ( '0' == $old_enabled ) {
				$mode_enabled = 'off';
			}
			$settings['mode_enabled'] = array();
			$settings['mode_enabled'] = array( 'value' => $mode_enabled );
		}
		if ( ! isset( $settings['quality']['value'] ) ) {
			$old_quality = $settings['quality'];
			$quality     = 'medium';
			if ( $old_quality < 50 ) {
				$quality = 'low';
			} elseif ( $old_quality > 80 ) {
				$quality = 'high';
			}
			$settings['quality'] = array(
				'value' => $quality,
			);
		}
		if ( ! isset( $settings['ignore_same_format'] ) ) {
			$settings['ignore_same_format'] = $settings['ignore_webp'];
		}
		if ( ! isset( $settings['display_mode'] ) ) {
			$settings['display_mode'] = $settings['display_webp_mode'];
		}

        return $settings;
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
			'mode_enabled'       => array(
				'value'   => 'webp',
				'options' => array(
					'off'  => __( 'Off', 'wpmastertoolkit' ),
					'webp' => __( 'WebP', 'wpmastertoolkit' ),
					'avif' => __( 'AVIF', 'wpmastertoolkit' ),
				)
			),
			'quality'            => array(
				'value'   => 'medium',
				'options' => array(
					'low'    => __( 'Low', 'wpmastertoolkit' ),
					'medium' => __( 'Medium', 'wpmastertoolkit' ),
					'high'   => __( 'High', 'wpmastertoolkit' ),
				)
			),
			'ignore_same_format' => '1',
			'save_original'      => '0',
			'display_mode'       => array(
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
		$is_pro                 = wpmastertoolkit_is_pro();
		$php_compatible         = is_php_version_compatible( '8.1.0' );
		$imageavif_supported 	= function_exists('imageavif');

		$mode_enabled_options = $this->default_settings['mode_enabled']['options'];
		$quality_options      = $this->default_settings['quality']['options'];
		$display_mode_options = $this->default_settings['display_mode']['options'];

        $mode_enabled       = $this->settings['mode_enabled']['value'] ?? $this->default_settings['mode_enabled']['value'];
		$quality            = $this->settings['quality']['value'] ?? $this->default_settings['quality']['value'];
		$ignore_same_format = $this->settings['ignore_same_format'] ?? $this->default_settings['ignore_same_format'];
		$save_original      = $this->settings['save_original'] ?? $this->default_settings['save_original'];
		$display_mode       = $this->settings['display_mode']['value'] ?? $this->default_settings['display_mode']['value'];

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
					<?php esc_html_e( "Automatically converts uploaded images to your selected format (WebP or AVIF) for better performance and reduced file size.", 'wpmastertoolkit'); ?>
				</div>
                <div class="wp-mastertoolkit__section__body">

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Image Format Optimization', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'Select “Off” to disable image optimization, “WebP” for wide browser support, or “AVIF” for best compression and smaller file sizes.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[mode_enabled][value]' ); ?>">
                                    <?php
										foreach ( $mode_enabled_options as $key => $name ) {
											$option_disabled = false;
											$disable_message = '';
											if ( $key == 'avif' && ( ! $is_pro || ! $php_compatible || ! $imageavif_supported ) ) {
												$option_disabled = true;
												$disable_message = __( '(PRO Only)', 'wpmastertoolkit' );
												if( ! $imageavif_supported ) {
													$disable_message = __( '(imageavif() not supported)', 'wpmastertoolkit' );
												}
												if ( ! $php_compatible ) {
													$disable_message = __( '(PHP 8.1 or higher)', 'wpmastertoolkit' );
												}
											}
											?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $mode_enabled, $key ); ?> <?php disabled( $option_disabled, true ); ?>><?php echo esc_html( $name ); ?> <?php echo esc_html( $disable_message ); ?></option>
											<?php
										}
									?>
                                </select>
                            </div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__title">
							<?php esc_html_e( 'Quality', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'Choose the image quality level. Lower quality results in smaller file sizes, while higher quality provides better visuals but larger files.', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__select">
                                <select name="<?php echo esc_attr( $this->option_id . '[quality][value]' ); ?>">
                                    <?php foreach ( $quality_options as $key => $name ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $quality, $key ); ?>><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

					<div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Do not compress images already in same format', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__checkbox">
								<label class="wp-mastertoolkit__checkbox__label">
									<input type="hidden" name="<?php echo esc_attr( $this->option_id . '[ignore_same_format]' ); ?>" value="0">
									<input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[ignore_same_format]' ); ?>" value="1" <?php checked( $ignore_same_format, '1' ); ?>>
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
							<?php esc_html_e( 'Display images with new format on the site', 'wpmastertoolkit' ); ?>
							<div class="wp-mastertoolkit__section__body__item__title__info">
								<div class="wp-mastertoolkit__section__body__item__title__info__icon">
									<?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/info.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
								</div>
								<div class="wp-mastertoolkit__section__body__item__title__info__popup">
									<p><?php esc_html_e( 'This option allows to override the original images by the new version (useless for images converted in import)', 'wpmastertoolkit' ); ?></p>
								</div>
							</div>
						</div>
                        <div class="wp-mastertoolkit__section__body__item__content">
							<div class="wp-mastertoolkit__radio">
								<?php foreach ( $display_mode_options as $key => $name ) : ?>
									<label class="wp-mastertoolkit__radio__label">
										<input type="radio" name="<?php echo esc_attr( $this->option_id . '[display_mode][value]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $display_mode, $key ); ?>>
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
