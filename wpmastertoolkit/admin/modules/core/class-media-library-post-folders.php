<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Media Library & Post Folders
 * Description: Organize your media library and posts into folders for better management and accessibility.
 * @since 2.17.0
 */
class WPMastertoolkit_Media_Library_Post_Folders {

	private $option_id;
	private $header_title;
	private $nonce_action;
	private $settings;
    private $default_settings;
	private $folders_table;
	private $attachment_folder_table;
	private $post_folder_table;
	private $default_folders_color;

	/**
	 * Invoke the hooks.
	 * 
	 * @since   2.17.0
	 */
	public function __construct() {

		$this->option_id               = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_media_library_post_folders';
		$this->nonce_action            = $this->option_id . '_action';
		$this->folders_table           = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_mlpf_folders';
		$this->attachment_folder_table = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_mlpf_attachment_folder';
		$this->post_folder_table       = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_mlpf_post_folder';
		$this->default_folders_color   = '#818181';

		add_action( 'init', array( $this, 'class_init' ) );
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
		add_action( 'init', array( $this, 'maybe_create_tables' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_ajax_query_attachments_args' ), 20 );
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_list_view_query' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_sidebar_get_folders', array( $this, 'ajax_get_folders' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_update_position', array( $this, 'ajax_update_folder_position' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_save_opened_state', array( $this, 'ajax_save_folder_opened_state' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_create_folder', array( $this, 'ajax_create_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_rename_folder', array( $this, 'ajax_rename_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_delete_folder', array( $this, 'ajax_delete_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_update_folder_color', array( $this, 'ajax_update_folder_color' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_add_attachment', array( $this, 'ajax_add_attachment_to_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_remove_attachment', array( $this, 'ajax_remove_attachment_from_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_add_post', array( $this, 'ajax_add_post_to_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_remove_post', array( $this, 'ajax_remove_post_from_folder' ) );
		add_action( 'wp_ajax_wpmtk_media_library_post_folders_sortby_folders', array( $this, 'ajax_sortby_folders' ) );
	}

	/**
     * Initialize the class
	 * 
	 * @since   2.17.0
     */
    public function class_init() {
		$this->header_title	= esc_html__( 'Media Library & Post Folders', 'wpmastertoolkit' );
    }

	/**
     * Add a submenu
     * 
     * @since   2.17.0
     */
    public function add_submenu(){
        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-media-library-post-folders',
            array( $this, 'render_submenu' ),
            null
        );
    }

	/**
     * Save the submenu option
     * 
     * @since   2.17.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

	/**
     * sanitize_settings
     * 
     * @since   2.17.0
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'post_types':
                    foreach ( $settings_value as $post_type => $post_status ) {
                        $sanitized_settings[ $settings_key ][ $post_type ] = sanitize_text_field( $new_settings[ $settings_key ][ $post_type ] ?? '0' );
                    }
                break;
            }
        }

        return $sanitized_settings;
    }

	/**
     * Render the submenu
     * 
     * @since   2.17.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/media-library-post-folders.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-library-post-folders.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-library-post-folders.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_submenu', 'WPMastertoolkit_media_encoder', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( $this->nonce_action ),
		));

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

	/**
     * get_settings
     *
     * @since   2.17.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

	/**
     * Save settings
     * 
     * @since   2.17.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

	/**
     * get_default_settings
     *
	 * @since   2.17.0
     */
    private function get_default_settings(){

        if ( $this->default_settings !== null ) {
			return $this->default_settings;
		}

        return array(
			'post_types' => $this->get_post_types_settings(),
        );
    }

	/**
     * Get the post types settings
     * 
     * @since   2.17.0
     */
    private function get_post_types_settings() {
        $result = array();

        foreach ( $this->get_post_types() as $post_type ) {
            $slug = $post_type->name;

			$checked = '0';
			if ( 'attachment' === $slug ) {
				$checked = '1';	
			}

            $result[$slug] = $checked;
        }

        return $result;
    }

	/**
     * Get the post types with default value
     * 
     * @since   2.17.0
     */
    private function get_post_types() {
        $result = array();

        foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			$result[] = get_post_type_object( $post_type );
        }

        return $result;
    }

	/**
     * Add the submenu content
     * 
     * @since   2.17.0
     */
    private function submenu_content() {
        $this->settings         = $this->get_settings();
		$this->default_settings = $this->get_default_settings();
		$post_types             = $this->get_post_types( false );

		?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Organize your media library and posts into folders for better management and accessibility.", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $post_types as $post_type ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .']' ); ?>" value="1"<?php checked( $this->settings['post_types'][$post_type->name]??'', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html($post_type->label); ?> <span>(<?php echo esc_html($post_type->name); ?>)</span></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        <?php
    }

	/**
	 * Check if tables exist and create them if they don't
	 * 
	 * @since   2.17.0
	 */
	public function maybe_create_tables() {
		global $wpdb;
		
		$table_folders           = $wpdb->prefix . $this->folders_table;
		$table_attachment_folder = $wpdb->prefix . $this->attachment_folder_table;
		$table_post_folder       = $wpdb->prefix . $this->post_folder_table;
		
		// Check if tables exist
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$folders_table_exists           = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_folders ) ) === $table_folders;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$attachment_folder_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_attachment_folder ) ) === $table_attachment_folder;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$post_folder_table_exists       = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_post_folder ) ) === $table_post_folder;
		
		// Create tables if they don't exist
		if ( ! $folders_table_exists || ! $attachment_folder_table_exists || ! $post_folder_table_exists ) {
			$this->create_tables();
		}

		// Run migrations for existing installs (e.g. adding new columns)
		$this->maybe_alter_tables();
	}

	/**
	 * Run migrations on existing tables (add columns that didn't exist in older versions)
	 *
	 * @since   2.17.0
	 */
	private function maybe_alter_tables() {
		global $wpdb;

		$table_folders = $wpdb->prefix . $this->folders_table;

		// Add post_type column if it doesn't exist yet (migration from single-tree to per-post-type trees)
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'post_type'",
			DB_NAME,
			$table_folders
		) );

		if ( empty( $column_exists ) ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN post_type varchar(20) NOT NULL DEFAULT 'attachment'", $table_folders ) );
		}
	}

	/**
	 * Enqueue scripts and styles
	 * 
	 * @since   2.17.0
	 */
	public function enqueue_scripts_styles( $suffix ) {

		$suffixes = array( 'upload.php', 'edit.php' );
		
		if ( in_array( $suffix, $suffixes ) ) {

			$this->settings         = $this->get_settings();
			$this->default_settings = $this->get_default_settings();
			$post_types             = $this->settings['post_types'] ?? $this->default_settings['post_types'] ?? array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_post_type      = 'edit.php' === $suffix ? ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post' ) : 'attachment';

			if ( empty( $post_types[ $current_post_type ] ) || '1' !== $post_types[ $current_post_type ] ) {
				return;
			}

			wp_enqueue_style('wp-color-picker');
			wp_enqueue_script('wp-color-picker');

			$assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/media-library-post-folders-sidebar.asset.php' );
			wp_enqueue_style( 'WPMastertoolkit_media_library_post_folders_sidebar', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-library-post-folders-sidebar.css', array(), $assets['version'], 'all' );
			wp_enqueue_script( 'WPMastertoolkit_media_library_post_folders_sidebar', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/media-library-post-folders-sidebar.js', $assets['dependencies'], $assets['version'], true );
			wp_localize_script( 'WPMastertoolkit_media_library_post_folders_sidebar', 'WPMastertoolkit_media_library_post_folders_sidebar', array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( $this->nonce_action ),
				'isPro'           => wpmastertoolkit_is_pro(),
				'currentPage'     => 'edit.php' === $suffix ? 'edit' : 'upload',
				'currentPostType' => $current_post_type,
				'mediaMode'       => get_user_option( 'media_library_mode', get_current_user_id() ),
				'i18n'            => array(
					'allFiles'           => esc_html__( 'All Files', 'wpmastertoolkit' ),
					'uncategorized'      => esc_html__( 'Uncategorized', 'wpmastertoolkit' ),
					'newFolder'          => esc_html__( 'New Folder', 'wpmastertoolkit' ),
					'enterFolderName'    => esc_html__( 'Enter folder name:', 'wpmastertoolkit' ),
					'rename'             => esc_html__( 'Rename', 'wpmastertoolkit' ),
					'enterNewFolderName' => esc_html__( 'Enter new folder name:', 'wpmastertoolkit' ),
					'delete'             => esc_html__( 'Delete', 'wpmastertoolkit' ),
					'confirmDelete'      => esc_html__( 'Are you sure you want to delete this folder?', 'wpmastertoolkit' ),
					'changeColor'        => esc_html__( 'Change Color', 'wpmastertoolkit' ),
					'uploadedByMe'       => esc_html__( 'Uploaded By Me', 'wpmastertoolkit' ),
					'withoutAlt'         => esc_html__( 'Without Alt Text', 'wpmastertoolkit' ),
					'withoutDescription' => esc_html__( 'Without Description', 'wpmastertoolkit' ),
					'withoutCaption'     => esc_html__( 'Without Caption', 'wpmastertoolkit' ),
					'sortFiles'          => esc_html__( 'Sort Files', 'wpmastertoolkit' ),
					'sortFolders'        => esc_html__( 'Sort Folders', 'wpmastertoolkit' ),
					'dateAscending'      => esc_html__( 'Date Ascending', 'wpmastertoolkit' ),
					'dateDescending'     => esc_html__( 'Date Descending', 'wpmastertoolkit' ),
					'nameAscending'      => esc_html__( 'Name Ascending', 'wpmastertoolkit' ),
					'nameDescending'     => esc_html__( 'Name Descending', 'wpmastertoolkit' ),
					'searchPlaceholder'  => esc_html__( 'Search folders...', 'wpmastertoolkit' ),
					'upgradeNotice'      => esc_html__( 'To go further, upgrade to the version', 'wpmastertoolkit' ),
					'pro'                => esc_html__( 'PRO', 'wpmastertoolkit' ),
				),
				'svg'             => array(
					'chevronRight'       => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/chevron-right.svg' ),
					'allFiles'           => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-all-files.svg' ),
					'uncategorized'      => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-uncategorized.svg' ),
					'folderAddNew'       => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-add-new.svg' ),
					'folderRename'       => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-rename.svg' ),
					'folderDelete'       => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-delete.svg' ),
					'folderChangeColor'  => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-change-color.svg' ),
					'uploadedByMe'       => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-uploaded-by-me.svg' ),
					'moreOptions'        => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-more-options.svg' ),
					'withoutAlt'         => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-without-alt.svg' ),
					'withoutDescription' => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-without-description.svg' ),
					'withoutCaption'     => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-without-caption.svg' ),
					'sortFilter'         => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-sort-filter.svg' ),
					'sortFiles'          => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-sort-files.svg' ),
					'sortFolders'        => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-sort-folders.svg' ),
					'dateAscending'      => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-date-ascending.svg' ),
					'dateDescending'     => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-date-descending.svg' ),
					'nameAscending'      => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-name-ascending.svg' ),
					'nameDescending'     => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-name-descending.svg' ),
					'search'             => file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-search.svg' ),
				),
			));
		}
	}

	/**
	 * Filter: Handle folder filter for Media Library list view
	 * Reads the wpmtk_folder URL parameter and injects it into the main WP_Query
	 * so that filter_posts_clauses can apply the SQL filter.
	 *
	 * @since   2.17.0
	 */
	public function filter_list_view_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wpmtk_folder'] ) ) {
			return;
		}

		$pagenow   = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$folder_id = sanitize_text_field( wp_unslash( $_GET['wpmtk_folder'] ) );

		if ( 'upload.php' === $pagenow && $query->get( 'post_type' ) === 'attachment' ) {
			$query->set( 'wpmtk_folder', $folder_id );
		} elseif ( 'edit.php' === $pagenow ) {
			$query->set( 'wpmtk_folder', $folder_id );
		}
	}

	/**
	 * Filter: Intercept WordPress media query to add folder parameter
	 * This is called when the media library loads attachments via AJAX
	 * 
	 * @since   2.17.0
	 */
	public function filter_ajax_query_attachments_args( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$folder_id = sanitize_text_field( wp_unslash( $_POST['query']['wpmtk_folder'] ?? '' ) );
		if ( ! empty( $folder_id ) ) {
			$query['wpmtk_folder'] = $folder_id;
		}
		
		return $query;
	}

	/**
	 * Filter: Modify SQL query to filter attachments by folder
	 * This modifies the actual database query to only return attachments in the selected folder
	 * 
	 * @since   2.17.0
	 */
	public function filter_posts_clauses( $clauses, $query ) {
		global $wpdb;

		$post_type = $query->get( 'post_type' );
		$folder_id = $query->get( 'wpmtk_folder' );

		// If no folder specified, return all
		if ( $folder_id === '' || $folder_id === null ) {
			return $clauses;
		}

		if ( $post_type === 'attachment' ) {
			$attachment_table = $wpdb->prefix . $this->attachment_folder_table;
			// Handle "uncategorized" filter (show attachments NOT in any folder)
			if ( 'uncategorized' === $folder_id ) {
				$clauses['join'] .= " LEFT JOIN {$attachment_table} AS wpmtk_af ON wpmtk_af.attachment_id = {$wpdb->posts}.ID";
				$clauses['where'] .= " AND wpmtk_af.attachment_id IS NULL";
			} else if ( 'name-asc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_title ASC";
			} else if ( 'name-desc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_title DESC";
			} else if ( 'date-asc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_date ASC";
			} else if ( 'date-desc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_date DESC";
			} else if ( 'uploaded-by-me' === $folder_id ) {
				$current_user_id = get_current_user_id();
				$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $current_user_id );
			} else if ( 'without-alt' === $folder_id ) {
				$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS wpmtk_pm ON wpmtk_pm.post_id = {$wpdb->posts}.ID AND wpmtk_pm.meta_key = '_wp_attachment_image_alt'";
				$clauses['where'] .= " AND (wpmtk_pm.meta_value IS NULL OR wpmtk_pm.meta_value = '')";
			} else if ( 'without-description' === $folder_id ) {
				$clauses['where'] .= " AND ({$wpdb->posts}.post_content IS NULL OR {$wpdb->posts}.post_content = '')";
			} else if ( 'without-caption' === $folder_id ) {
				$clauses['where'] .= " AND ({$wpdb->posts}.post_excerpt IS NULL OR {$wpdb->posts}.post_excerpt = '')";
			} else {
				$folder_id = intval( $folder_id );
				$clauses['join'] .= $wpdb->prepare(
					" LEFT JOIN %i AS wpmtk_af ON wpmtk_af.attachment_id = {$wpdb->posts}.ID AND wpmtk_af.folder_id = %d",
					$attachment_table,
					$folder_id
				);
				$clauses['where'] .= " AND wpmtk_af.folder_id IS NOT NULL";
			}
		} else if ( ! empty( $post_type ) ) {
			$post_table = $wpdb->prefix . $this->post_folder_table;
			if ( 'uncategorized' === $folder_id ) {
				$clauses['join'] .= " LEFT JOIN {$post_table} AS wpmtk_pf ON wpmtk_pf.post_id = {$wpdb->posts}.ID";
				$clauses['where'] .= " AND wpmtk_pf.post_id IS NULL";
			} else if ( 'name-asc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_title ASC";
			} else if ( 'name-desc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_title DESC";
			} else if ( 'date-asc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_date ASC";
			} else if ( 'date-desc' === $folder_id ) {
				$clauses['orderby'] = "{$wpdb->posts}.post_date DESC";
			} else if ( 'uploaded-by-me' === $folder_id ) {
				$current_user_id = get_current_user_id();
				$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $current_user_id );
			} else {
				$folder_id_int = intval( $folder_id );
				$clauses['join'] .= $wpdb->prepare(
					" LEFT JOIN %i AS wpmtk_pf ON wpmtk_pf.post_id = {$wpdb->posts}.ID AND wpmtk_pf.folder_id = %d",
					$post_table,
					$folder_id_int
				);
				$clauses['where'] .= " AND wpmtk_pf.folder_id IS NOT NULL";
			}
		}

		return $clauses;
	}

	/**
	 * AJAX: Get folders
	 * 
	 * @since   2.17.0
	 */
	public function ajax_get_folders() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$is_pro         = wpmastertoolkit_is_pro();
		$table_name     = $wpdb->prefix . $this->folders_table;
		$options        = get_option( $this->option_id, array() );
		$sortby_folders = isset( $options['sortby_folders'] ) ? $options['sortby_folders'] : 'date-asc';
		$post_type      = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'attachment';
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$folders        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE post_type = %s", $table_name, $post_type ) );

		// Ensure folders is an array
		if ( ! is_array( $folders ) ) {
			$folders = array();
		}

		// Prepare tree data
		$prepared_folders = array();
		if ( is_array( $folders ) ) {
			foreach ( $folders as $k => $v ) {
				// Skip if essential properties are missing
				if ( ! isset( $v->id ) || ! isset( $v->name ) ) {
					continue;
				}

				$has_children = false;
				foreach ( $folders as $potential_child ) {
					if ( isset( $potential_child->parent ) && $potential_child->parent == $v->id ) {
						$has_children = true;
						break;
					}
				}
				
				$prepared_folders[ $k ] = array(
					'id'        => (int) $v->id,
					'parent'    => isset( $v->parent ) ? (int) $v->parent : 0,
					'text'      => $v->name,
					'dataCount' => 0,
					'opened'    => isset( $v->opened ) ? (bool) $v->opened : false,
					'icon'      => $has_children ? file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder-has-children.svg' ) : file_get_contents( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/folder.svg' ),
					'color'     => isset( $v->color ) && $is_pro ? sanitize_hex_color( $v->color ) : $this->default_folders_color,
				);
			}
		}

		// Get folder counts for the current post type
		$folder_counts = $this->get_folder_counts_for_post_type( $post_type );

		// Add counts to prepared folders (flat structure)
		foreach ( $prepared_folders as $key => $folder ) {
			$folder_id = $folder['id'];
			$count     = 0;
			
			if ( isset( $folder_counts['display'][ $folder_id ] ) ) {
				$count = $folder_counts['display'][ $folder_id ];
			} elseif ( isset( $folder_counts['actual'][ $folder_id ] ) ) {
				$count = $folder_counts['actual'][ $folder_id ];
			}
			
			$prepared_folders[ $key ]['dataCount'] = $count;
		}

		// Get total and by-me counts for the current post type
		$all_attachments_count = $this->get_all_count_for_post_type( $post_type );
		$by_me_count           = $this->get_written_by_me_count( $post_type );

		// Re-index array to ensure it's a proper indexed array
		$tree = array_values( $prepared_folders );

		// Return response with flat tree structure
		wp_send_json_success( array(
			'tree'                    => $tree,
			'allAttachmentsCount'     => $all_attachments_count,
			'attachmentsCount'        => is_array( $folder_counts ) ? $folder_counts : array( 'display' => array(), 'actual' => array() ),
			'uploadedByMeCount'       => $by_me_count,
			'withoutAltCount'         => $post_type === 'attachment' ? $this->get_attachments_without_alt_count() : 0,
			'withoutDescriptionCount' => $post_type === 'attachment' ? $this->get_attachments_without_description_count() : 0,
			'withoutCaptionCount'     => $post_type === 'attachment' ? $this->get_attachments_without_caption_count() : 0,
			'sortbyFolders'           => $sortby_folders,
		) );
	}

	/**
	 * AJAX: Update folder position
	 * 
	 * @since   2.17.0
	 */
	public function ajax_update_folder_position() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		$parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;

		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder ID.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->folders_table;

		// First, update the parent of the moved folder
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table_name,
			array( 'parent' => $parent_id ),
			array( 'id' => $folder_id ),
			array( '%d' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => __( 'Folder position updated successfully.', 'wpmastertoolkit' ) ) );
	}

	/**
	 * AJAX: Save folder opened state
	 * 
	 * @since   2.17.0
	 */
	public function ajax_save_folder_opened_state() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		$opened    = isset( $_POST['opened'] ) ? intval( $_POST['opened'] ) : 0;

		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder ID.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->folders_table;

		// Update the opened state
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table_name,
			array( 'opened' => $opened ),
			array( 'id' => $folder_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update folder opened state.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Folder opened state updated successfully.', 'wpmastertoolkit' ) ) );
	}

	/**
	 * AJAX: Create a new folder
	 * 
	 * @since   2.17.0
	 */
	public function ajax_create_folder() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$folder_name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
		$parent_id   = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'attachment';

		if ( empty( $folder_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Folder name is required.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->folders_table;

		// Insert the new folder
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			array(
				'name'       => $folder_name,
				'parent'     => $parent_id,
				'type'       => 0,
				'opened'     => 0,
				'created_by' => get_current_user_id(),
				'post_type'  => $post_type,
			),
			array( '%s', '%d', '%d', '%d', '%d', '%s' )
		);

		// Make the parent state opened
		if ( $parent_id > 0 ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$table_name,
				array( 'opened' => 1 ),
				array( 'id' => $parent_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create folder.', 'wpmastertoolkit' ) ) );
		}

		$new_folder_id = $wpdb->insert_id;

		wp_send_json_success( array( 
			'message' => __( 'Folder created successfully.', 'wpmastertoolkit' ),
			'data'    => array(
				'id'     => $new_folder_id,
				'name'   => $folder_name,
				'parent' => $parent_id,
			)
		) );
	}

	/**
	 * AJAX: Rename a folder
	 * 
	 * @since   2.17.0
	 */
	public function ajax_rename_folder() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$folder_id   = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		$folder_name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';

		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder ID.', 'wpmastertoolkit' ) ) );
		}

		if ( empty( $folder_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Folder name is required.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->folders_table;

		// Update the folder name
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table_name,
			array( 'name' => $folder_name ),
			array( 'id' => $folder_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to rename folder.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array( 
			'message' => __( 'Folder renamed successfully.', 'wpmastertoolkit' ),
			'data'    => array(
				'id'   => $folder_id,
				'name' => $folder_name,
			)
		) );
	}

	/**
	 * AJAX: Delete a folder
	 * 
	 * @since   2.17.0
	 */
	public function ajax_delete_folder() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder ID.', 'wpmastertoolkit' ) ) );
		}

		// Get all folder IDs to delete (including subfolders recursively)
		$folder_ids_to_delete = $this->get_folder_with_children( $folder_id );
		if ( empty( $folder_ids_to_delete ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to retrieve folder information.', 'wpmastertoolkit' ) ) );
		}

		$table_name       = $wpdb->prefix . $this->folders_table;
		$attachment_table = $wpdb->prefix . $this->attachment_folder_table;
		$post_table       = $wpdb->prefix . $this->post_folder_table;

		// Delete all attachment associations for this folder and its subfolders
		$folder_ids_placeholder = implode( ',', array_fill( 0, count( $folder_ids_to_delete ), '%d' ) );
		
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM %i WHERE folder_id IN ($folder_ids_placeholder)",
				$attachment_table,
			)
		);

		// Delete all post associations for this folder and its subfolders
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM %i WHERE folder_id IN ($folder_ids_placeholder)",
				$table_name,
			)
		);

		// Delete all folders (parent and children)
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM %i WHERE id IN ($folder_ids_placeholder)",
				$table_name,
			)
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete folder.', 'wpmastertoolkit' ) ) );
		}

		$deleted_count = count( $folder_ids_to_delete );
		$message = $deleted_count > 1 
			// Translators: %d is the number of subfolders that were deleted along with the main folder.
			? sprintf( __( 'Folder and %d subfolder(s) deleted successfully.', 'wpmastertoolkit' ), $deleted_count - 1 )
			: __( 'Folder deleted successfully.', 'wpmastertoolkit' );

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX: Update folder color
	 * 
	 * @since   2.17.0
	 */
	public function ajax_update_folder_color() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		$color     = isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '';

		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder ID.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->folders_table;

		// Update the color
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table_name,
			array( 'color' => $color ),
			array( 'id' => $folder_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update folder color.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Folder color updated successfully.', 'wpmastertoolkit' ) ) );
	}

	/**
	 * AJAX handler to add an attachment to a folder
	 * 
	 * @since   2.17.0
	 */
	public function ajax_add_attachment_to_folder() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'wpmastertoolkit' ) ) );
		}

		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder ID.', 'wpmastertoolkit' ) ) );
		}

		// Verify attachment exists
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			wp_send_json_error( array( 'message' => __( 'Attachment not found.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->attachment_folder_table;

		// Check if attachment is already in a folder
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$existing = $wpdb->get_var( 
			$wpdb->prepare(
				"SELECT folder_id FROM %i WHERE attachment_id = %d",
				$table_name,
				$attachment_id
			)
		);

		if ( $existing ) {
			// Update existing association
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$table_name,
				array( 'folder_id' => $folder_id ),
				array( 'attachment_id' => $attachment_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			// Insert new association
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$table_name,
				array(
					'attachment_id' => $attachment_id,
					'folder_id' => $folder_id
				),
				array( '%d', '%d' )
			);
		}

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to add attachment to folder.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array( 
			'message' => __( 'Attachment added to folder successfully.', 'wpmastertoolkit' ),
			'attachment_id' => $attachment_id,
			'folder_id' => $folder_id
		) );
	}

	/**
	 * AJAX handler to remove an attachment from its folder (move to uncategorized)
	 * 
	 * @since   2.17.0
	 */
	public function ajax_remove_attachment_from_folder() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'wpmastertoolkit' ) ) );
		}

		// Verify attachment exists
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			wp_send_json_error( array( 'message' => __( 'Attachment not found.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->attachment_folder_table;

		// Delete the attachment-folder association
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			$table_name,
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to remove attachment from folder.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array( 
			'message' => __( 'Attachment removed from folder successfully.', 'wpmastertoolkit' ),
			'attachment_id' => $attachment_id
		) );
	}

	/**
	 * AJAX: Sort folders
	 * 
	 * @since   2.17.0
	 */
	public function ajax_sortby_folders() {
		// Verify nonce
		check_ajax_referer( $this->nonce_action, 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		$allowed_sortby = array( 'date-asc', 'date-desc', 'name-asc', 'name-desc' );
		$sortby_folders = isset( $_POST['sortby_folders'] ) ? sanitize_text_field( wp_unslash( $_POST['sortby_folders'] ) ) : 'date-asc';
		if ( ! in_array( $sortby_folders, $allowed_sortby, true ) ) {
			$sortby_folders = 'date-asc';
		}

		$existing_options                   = get_option( $this->option_id, array() );
		$existing_options['sortby_folders'] = $sortby_folders;
		update_option( $this->option_id, $existing_options );

		wp_send_json_success( array( 
			'message' => __( 'Folder sort order updated successfully.', 'wpmastertoolkit' ),
		) );
	}

	/**
	 * Create database tables
	 * 
	 * @since   2.17.0
	 */
	private function create_tables() {
		global $wpdb;
		
		$sql                     = array();
		$charset_collate         = $wpdb->get_charset_collate();
		$table_folders           = $wpdb->prefix . $this->folders_table;
		$table_attachment_folder = $wpdb->prefix . $this->attachment_folder_table;
		$table_post_folder       = $wpdb->prefix . $this->post_folder_table;
		
		// Folders table
		$sql[] = "CREATE TABLE IF NOT EXISTS {$table_folders} (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(250) NOT NULL,
			parent int(11) NOT NULL DEFAULT 0,
			type int(2) NOT NULL DEFAULT 0,
			opened tinyint(1) NOT NULL DEFAULT 0,
			color varchar(7) NULL DEFAULT '{$this->default_folders_color}',
			created_by int(11) NULL DEFAULT 0,
			post_type varchar(20) NOT NULL DEFAULT 'attachment',
			PRIMARY KEY (id),
			UNIQUE KEY id (id),
			KEY post_type_idx (post_type)
		) {$charset_collate};";
		
		// Attachment-Folder relationship table
		$sql[] = "CREATE TABLE IF NOT EXISTS {$table_attachment_folder} (
			folder_id int(11) UNSIGNED NOT NULL,
			attachment_id bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY (folder_id, attachment_id)
		) {$charset_collate};";

		// Post-Folder relationship table (for non-attachment post types)
		$sql[] = "CREATE TABLE IF NOT EXISTS {$table_post_folder} (
			folder_id int(11) UNSIGNED NOT NULL,
			post_id bigint(20) UNSIGNED NOT NULL,
			post_type varchar(20) NOT NULL DEFAULT 'post',
			PRIMARY KEY (post_id),
			KEY folder_idx (folder_id)
		) {$charset_collate};";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Get folder and all its children recursively
	 * 
	 * @since   2.17.0
	 * @param   int $folder_id The folder ID
	 * @return  array Array of folder IDs (including the parent)
	 */
	private function get_folder_with_children( $folder_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->folders_table;
		$folder_ids = array( $folder_id );
		$to_process = array( $folder_id );

		// Recursively get all children
		while ( ! empty( $to_process ) ) {
			$current_id = array_shift( $to_process );
			
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$children = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM %i WHERE parent = %d",
					$table_name,
					$current_id
				)
			);

			if ( ! empty( $children ) ) {
				foreach ( $children as $child_id ) {
					$folder_ids[] = intval( $child_id );
					$to_process[] = intval( $child_id );
				}
			}
		}

		return $folder_ids;
	}

	/**
	 * Get folder counts for all folders
	 * 
	 * @since   2.17.0
	 */
	private function get_folder_counts() {
		global $wpdb;

		$query = "SELECT folder_id, count(attachment_id) as counter
				FROM {$wpdb->prefix}posts AS `posts`
				INNER JOIN {$wpdb->prefix}{$this->attachment_folder_table} AS `folder_rel` ON (folder_rel.attachment_id = posts.ID AND posts.post_type = 'attachment')
				WHERE posts.post_status != 'trash'
				GROUP BY folder_id";

		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$counters          = $wpdb->get_results( $query, OBJECT_K );
		$formatted_counter = array();
		$actual_counter    = array();

		foreach ( $counters as $counter ) {
			$actual_counter[ $counter->folder_id ] = $counter->counter;
		}

		return array(
			'display' => array_replace( $actual_counter, $formatted_counter ),
			'actual'  => $actual_counter,
		);
	}

	/**
	 * Get all attachments count
	 * 
	 * @since   2.17.0
	 */
	private function get_all_attachments_count() {
		global $wpdb;

		$query = "SELECT COUNT(*) FROM {$wpdb->posts} 
				WHERE post_type = 'attachment' 
				AND (post_status = 'inherit' OR post_status = 'private')";

		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var( $query );
	}

		/**
	 * Get total count of all attachments uploaded by the current user
	 * 
	 * @since   2.17.0
	 */
	private function get_uploaded_by_me_attachments_count() {
		global $wpdb;

		$current_user_id = get_current_user_id();

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_author = %d",
				$current_user_id
			)
		);

		return intval( $count );
	}

	/**
	 * Get total count of all attachments without alt text
	 * 
	 * @since   2.17.0
	 */
	private function get_attachments_without_alt_count() {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} AS p
			LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment' AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);

		return intval( $count );
	}

	/**
	 * Get total count of all attachments without description
	 * 
	 * @since   2.17.0
	 */
	private function get_attachments_without_description_count() {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' AND (post_content IS NULL OR post_content = '')"
		);
		return intval( $count );
	}

	/**
	 * Get total count of all attachments without caption
	 * 
	 * @since   2.17.0
	 */
	private function get_attachments_without_caption_count() {
		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' AND (post_excerpt IS NULL OR post_excerpt = '')"
		);
		return intval( $count );
	}

	/**
	 * AJAX: Add a post to a folder (for non-attachment post types)
	 *
	 * @since   2.17.0
	 */
	public function ajax_add_post_to_folder() {
		check_ajax_referer( $this->nonce_action, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;

		if ( ! $post_id || ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post or folder ID.', 'wpmastertoolkit' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type === 'attachment' ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->post_folder_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT folder_id FROM %i WHERE post_id = %d",
			$table_name,
			$post_id
		) );

		if ( $existing !== null ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$table_name,
				array( 'folder_id' => $folder_id ),
				array( 'post_id'   => $post_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$table_name,
				array( 'post_id' => $post_id, 'folder_id' => $folder_id, 'post_type' => $post->post_type ),
				array( '%d', '%d', '%s' )
			);
		}

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to add post to folder.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array(
			'message'   => __( 'Post added to folder successfully.', 'wpmastertoolkit' ),
			'post_id'   => $post_id,
			'folder_id' => $folder_id,
		) );
	}

	/**
	 * AJAX: Remove a post from its folder (for non-attachment post types)
	 *
	 * @since   2.17.0
	 */
	public function ajax_remove_post_from_folder() {
		check_ajax_referer( $this->nonce_action, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpmastertoolkit' ) ) );
		}

		global $wpdb;

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'wpmastertoolkit' ) ) );
		}

		$table_name = $wpdb->prefix . $this->post_folder_table;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			$table_name,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to remove post from folder.', 'wpmastertoolkit' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Post removed from folder successfully.', 'wpmastertoolkit' ),
			'post_id' => $post_id,
		) );
	}

	/**
	 * Get folder counts for a specific post type
	 *
	 * @since   2.17.0
	 */
	private function get_folder_counts_for_post_type( $post_type ) {
		if ( $post_type === 'attachment' ) {
			return $this->get_folder_counts();
		}

		global $wpdb;

		$post_table = $wpdb->prefix . $this->post_folder_table;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$counters   = $wpdb->get_results( $wpdb->prepare(
			"SELECT pf.folder_id, COUNT(pf.post_id) AS counter
			FROM {$wpdb->posts} AS p
			INNER JOIN %i AS pf ON pf.post_id = p.ID
			WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')
			GROUP BY pf.folder_id",
			$post_table,
			$post_type
		), OBJECT_K );

		$actual_counter = array();
		foreach ( $counters as $counter ) {
			$actual_counter[ $counter->folder_id ] = $counter->counter;
		}

		return array( 'display' => $actual_counter, 'actual' => $actual_counter );
	}

	/**
	 * Get total item count for any post type
	 *
	 * @since   2.17.0
	 */
	private function get_all_count_for_post_type( $post_type ) {
		if ( $post_type === 'attachment' ) {
			return $this->get_all_attachments_count();
		}

		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft')",
			$post_type
		) );
	}

	/**
	 * Get count of items authored by the current user for any post type
	 *
	 * @since   2.17.0
	 */
	private function get_written_by_me_count( $post_type ) {
		if ( $post_type === 'attachment' ) {
			return $this->get_uploaded_by_me_attachments_count();
		}

		global $wpdb;

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d AND post_status NOT IN ('trash', 'auto-draft')",
			$post_type,
			get_current_user_id()
		) );
	}
}
