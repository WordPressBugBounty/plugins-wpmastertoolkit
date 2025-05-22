<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Register Custom Post Type
 * Description: Register Custom Post Type, Taxonomy, or Option Page
 * @since 2.6.0
 */
class WPMastertoolkit_Register_Custom_Content_Types {

    protected $post_type             = 'wpmtk-content-type';
    protected $meta_click_count      = 'click_count';
    protected $content_type_settings = 'content_type_settings';
	protected $export_submenu        = 'wp-mastertoolkit-settings-register-custom-content-types-export';
    
	private $option_id;
	private $header_title;
	private $disable_form;
    private $nonce_action_02;
    private $code_folder_path;
    private $all_wp_capabilities;

	private $cron_cpt_migrate_hook;
	private $cron_cpt_migrate_recurrence;
	private $cron_cpt_delete_hook;
	private $cron_cpt_delete_recurrence;

	private $cron_taxonomy_migrate_hook;
	private $cron_taxonomy_migrate_recurrence;
	private $cron_taxonomy_delete_hook;
	private $cron_taxonomy_delete_recurrence;

	private $option_cpt_migrate_status;
	private $option_cpt_migrate_posts;
	private $option_cpt_delete_status;
	private $option_cpt_delete_posts;

	private $option_taxonomy_migrate_status;
	private $option_taxonomy_migrate_terms;
	private $option_taxonomy_delete_status;
	private $option_taxonomy_delete_terms;

    /**
     * Constructor.
     * 
     * @since 2.6.0
     */
    public function __construct() {
		$this->option_id                        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_register_custom_content_types';
		$this->nonce_action_02                  = $this->option_id . '_action';

		$this->cron_cpt_migrate_hook            = $this->option_id . '_cron_cpt_migrate_hook';
		$this->cron_cpt_migrate_recurrence      = $this->option_id . '_cron_cpt_migrate_recurrence';
		$this->cron_cpt_delete_hook             = $this->option_id . '_cron_cpt_delete_hook';
		$this->cron_cpt_delete_recurrence       = $this->option_id . '_cron_cpt_delete_recurrence';

		$this->cron_taxonomy_migrate_hook       = $this->option_id . '_cron_taxonomy_migrate_hook';
		$this->cron_taxonomy_migrate_recurrence = $this->option_id . '_cron_taxonomy_migrate_recurrence';
		$this->cron_taxonomy_delete_hook        = $this->option_id . '_cron_taxonomy_delete_hook';
		$this->cron_taxonomy_delete_recurrence  = $this->option_id . '_cron_taxonomy_delete_recurrence';

		$this->option_cpt_migrate_status        = $this->option_id . '_option_cpt_migrate_status';
		$this->option_cpt_migrate_posts         = $this->option_id . '_option_cpt_migrate_posts';
		$this->option_cpt_delete_status         = $this->option_id . '_option_cpt_delete_status';
		$this->option_cpt_delete_posts          = $this->option_id . '_option_cpt_delete_posts';

		$this->option_taxonomy_migrate_status   = $this->option_id . '_option_taxonomy_migrate_status';
		$this->option_taxonomy_migrate_terms    = $this->option_id . '_option_taxonomy_migrate_terms';
		$this->option_taxonomy_delete_status    = $this->option_id . '_option_taxonomy_delete_status';
		$this->option_taxonomy_delete_terms     = $this->option_id . '_option_taxonomy_delete_terms';
        
        add_action( 'init', array( $this, 'register_content_type_cpt' ) );
        add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
        add_filter( 'wp_sitemaps_post_types', array( $this, 'remove_from_sitemap' ), 10, 2 );
        add_action( 'edit_form_top', array( $this, 'render_edit_post_html' ) );
        add_action( 'save_post_' . $this->post_type, array( $this, 'save_post' ) );
        
        add_action( 'before_delete_post', array( $this, 'delete_file_on_delete_post' ) );
        add_action( 'wp_trash_post', array( $this, 'delete_file_on_delete_post' ) );

        add_filter( 'wpmastertoolkit/folders', array( $this, 'create_folders' ) );

		add_action( 'admin_footer', array( $this, 'render_key_change_popup' ) );
		add_action( 'wp_ajax_wpmtk_register_custom_content_types', array( $this, 'handle_ajax_actions' ) );

		add_filter( 'cron_schedules', array( $this, 'crons_registrations' ) );
		add_action( $this->cron_cpt_migrate_hook, array( $this, 'excute_cron_cpt_migrate' ) );
		add_action( $this->cron_cpt_delete_hook, array( $this, 'excute_cron_cpt_delete' ) );
		add_action( $this->cron_taxonomy_migrate_hook, array( $this, 'excute_cron_taxonomy_migrate' ) );
		add_action( $this->cron_taxonomy_delete_hook, array( $this, 'excute_cron_taxonomy_delete' ) );

		add_filter( 'bulk_actions-edit-' . $this->post_type, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . $this->post_type, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );

        $this->custom_content_types_loader();

    }

    /**
     * activate
     *
     * @return void
     */
    public static function activate(){

    }

     /**
     * create_folders
     *
     * @param  mixed $folders
     * @return void
     */
    public function create_folders( $folders ) {
        $folders['wpmastertoolkit']['register-custom-content-types'] = array();
        return $folders;
    }
    
    /**
     * Register Custom Post Type for Content Types.
     */
    public function register_content_type_cpt() {
        $labels = array(
            'name'               => __( 'Content Types', 'wpmastertoolkit' ),
            'singular_name'      => __( 'Content Type', 'wpmastertoolkit' ),
            'add_new'            => __( 'Add New', 'wpmastertoolkit' ),
            'add_new_item'       => __( 'Create New', 'wpmastertoolkit' ),
            'edit_item'          => __( 'Edit', 'wpmastertoolkit' ),
            'new_item'           => __( 'New', 'wpmastertoolkit' ),
            'all_items'          => __( 'All', 'wpmastertoolkit' ),
            'view_item'          => __( 'View', 'wpmastertoolkit' ),
            'search_items'       => __( 'Search', 'wpmastertoolkit' ),
            'not_found'          => __( 'No Content Types Found', 'wpmastertoolkit' ),
            'not_found_in_trash' => __( 'No Content Types Found in Trash', 'wpmastertoolkit' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'has_archive'         => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'query_var'           => true,
            'rewrite'             => false,
            'supports'            => array( 'none' ),
            'menu_icon'           => 'dashicons-welcome-widgets-menus',
            'menu_position'       => 101,
        );

        register_post_type( $this->post_type, $args );
    }

    /**
     * Add Custom Columns in Admin List.
     */
    public function add_custom_columns( $columns ) {
        unset( $columns['date'] );

        $columns['content_type']    = __( 'Content Type', 'wpmastertoolkit' );
        $columns['status']          = __( 'Status', 'wpmastertoolkit' );
        $columns['export_code']     = __( 'Export code', 'wpmastertoolkit' );

        return $columns;
    }

    /**
     * Populate Custom Column Content.
     */
    public function custom_column_content( $column, $post_id ) {
        if ( $column === 'content_type' ) {
            switch(get_post_meta( $post_id, 'content_type', true )){
                case 'cpt':
                    echo wp_kses_post( '<span class="dashicons dashicons-admin-post"></span> ' . __( 'Custom Post Type', 'wpmastertoolkit' ) );
                    break;
                case 'taxonomy':
                    echo wp_kses_post( '<span class="dashicons dashicons-category"></span> ' . __( 'Custom Taxonomy', 'wpmastertoolkit' ) );
                    break;
                case 'option_page':
                    echo wp_kses_post( '<span class="dashicons dashicons-admin-generic"></span> ' . __( 'Option Page', 'wpmastertoolkit' ) );
                    break;
                default:
                    echo wp_kses_post( '<span class="dashicons dashicons-admin-generic"></span> ' . __( 'Unknown', 'wpmastertoolkit' ) );
                    break;
            }
        } elseif ( $column === 'status' ) {
            $status = get_post_status( $post_id );
            switch( $status ) {
                case 'publish':
                    echo wp_kses_post( '<span class="dashicons dashicons-yes"></span> ' . __( 'Published', 'wpmastertoolkit' ) );
                    break;
                case 'draft':
                    echo wp_kses_post( '<span class="dashicons dashicons-no"></span> ' . __( 'Draft', 'wpmastertoolkit' ) );
                    break;
                case 'trash':
                    echo wp_kses_post( '<span class="dashicons dashicons-trash"></span> ' . __( 'Trashed', 'wpmastertoolkit' ) );
                    break;
                default:
                    echo wp_kses_post( '<span class="dashicons dashicons-yes"></span> ' . __( 'Unknown', 'wpmastertoolkit' ) );
                    break;
            }
		} elseif ( $column === 'export_code' ) {

			if ( ! wpmastertoolkit_is_pro() ) {
				?>
					<a href="javascript:void(0);" class="button" disabled><?php esc_html_e( 'Export Code (Pro Only)', 'wpmastertoolkit' ); ?></a>
				<?php
				return;
			}

			$content_type  = get_post_meta( $post_id, 'content_type', true );
			$post_ids      = array( $post_id );
			$content_types = array( $content_type );

			$url = add_query_arg(
				array(
					'post_type'     => $this->post_type,
					'page'          => $this->export_submenu,
					'nonce'         => wp_create_nonce( $this->nonce_action_02 ),
					'content_types' => implode( ',', $content_types ),
					'post_ids'      => implode( ',', $post_ids ),
				),
				admin_url( 'edit.php' ),
			);
			?>
				<a href="<?php echo esc_url( $url ); ?>" class="button"><?php esc_html_e( 'Export Code', 'wpmastertoolkit' ); ?></a>
			<?php
		}
    }
    
    /**
     * meta_boxes
     *
     * @return void
     */
    public function meta_boxes() {
        remove_meta_box( 'submitdiv', $this->post_type, 'side' );
        remove_meta_box( 'slugdiv', $this->post_type, 'normal' );
    }

    /**
     * admin_body_class
     *
     * @param  mixed $classes
     * @return void
     */
    public function admin_body_class( $classes ) {
        global $pagenow;
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === $this->post_type ) {
            $classes .= ' wpmtk-modern-post-list';
        }
        return $classes;
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( $hook === 'edit.php' ) {
            if ( get_post_type() !== $this->post_type ) return;

            $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/modern-post-list.asset.php' );
            wp_enqueue_style( 'WPMastertoolkit_modern_post_list', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/modern-post-list.css', array(), $assets['version'], 'all' );
        }
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            if ( get_post_type() !== $this->post_type ) return;

            require_once( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/select-dashicon/class-select-dashicon.php' );
            wp_enqueue_style('dashicons');
            
			$settings = $this->get_settings_cpt( get_the_ID() );
            $assets   = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/register-custom-content-types.asset.php' );
            wp_enqueue_style( 'WPMastertoolkit_custom_content_types_post', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/register-custom-content-types.css', array(), $assets['version'], 'all' );
            wp_enqueue_script( 'WPMastertoolkit_custom_content_types_post', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/register-custom-content-types.js', $assets['dependencies'], $assets['version'], true );
            wp_localize_script( 'WPMastertoolkit_custom_content_types_post', 'wpmtk_custom_content_types', array(
                'content_type' => get_post_meta( get_the_ID(), 'content_type', true ),
				'post_type'    => $settings['post_type'] ?? '',
				'taxonomy'     => $settings['taxonomy'] ?? '',
				'nonce'        => wp_create_nonce( $this->nonce_action_02 ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            ) );
        }
    }

    /**
     * Remove Content Types from Sitemap.
     */
    public function remove_from_sitemap( $post_types ) {
        
        if ( isset( $post_types[$this->post_type] ) ) {
            unset( $post_types[$this->post_type] );
        }

        return $post_types;
    }

    /**
     * Render Edit Post HTML.
     */
    public function render_edit_post_html() {
        global $post;

        if ( $post->post_type !== $this->post_type ) return;

        $content_type = get_post_meta( $post->ID, 'content_type', true );

        switch ( $content_type ) {
            case 'cpt':
                include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/edit-post-cpt.php' );
                break;
            case 'taxonomy':
                include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/edit-taxonomy.php' );
                break;
            case 'option_page':
                break;
            default:
                include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/edit-post-select-type.php' );
                break;
        }

    }
    
    /**
     * get_cpt_labels
     *
     * @param  mixed $filter
     * @return void
     */
    public function get_cpt_labels( $filter = false ) {
        $labels = array(
            'name' => array(
                "label"       => __( 'Plural Label', 'wpmastertoolkit' ),
                "required"    => true,
                "placeholder" => __( 'Movies', 'wpmastertoolkit' ),
                "description" => __( 'The plural label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'singular_name' => array(
                "label"       => __( 'Singular Label', 'wpmastertoolkit' ),
                "required"    => true,
                "placeholder" => __( 'Movie', 'wpmastertoolkit' ),
                "description" => __( 'The singular label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'post_type' => array(
                "label"       => __( 'Post Type Key', 'wpmastertoolkit' ),
                "required"    => true,
                "placeholder" => __( 'movie', 'wpmastertoolkit' ),
                "description" => __( 'The post type key for the custom post type.', 'wpmastertoolkit' ),
            ),
            'description' => array(
                "label"       => __( 'Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'This content type is used to...', 'wpmastertoolkit' ),
                "description" => __( 'A short description of the custom post type.', 'wpmastertoolkit' ),
            ),
            'menu_name' => array(
                "label"       => __( 'Menu Name', 'wpmastertoolkit' ),
                "placeholder" => __( 'Posts', 'wpmastertoolkit' ),
                "description" => __( 'The menu name for the custom post type.', 'wpmastertoolkit' ),
            ),
            'all_items' => array(
                "label"       => __( 'All Items', 'wpmastertoolkit' ),
                "placeholder" => __( 'All Posts', 'wpmastertoolkit' ),
                "description" => __( 'The all items label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'edit_item' => array(
                "label"       => __( 'Edit Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Edit Post', 'wpmastertoolkit' ),
                "description" => __( 'The edit item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'view_item' => array(
                "label"       => __( 'View Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'View Post', 'wpmastertoolkit' ),
                "description" => __( 'The view item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'add_new_item' => array(
                "label"       => __( 'Add New Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Add New Post', 'wpmastertoolkit' ),
                "description" => __( 'The add new item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'add_new' => array(
                "label"       => __( 'Add New', 'wpmastertoolkit' ),
                "placeholder" => __( 'Add New Post', 'wpmastertoolkit' ),
                "description" => __( 'The add new label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'new_item' => array(
                "label"       => __( 'New Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'New Post', 'wpmastertoolkit' ),
                "description" => __( 'The new item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'parent_item_colon' => array(
                "label"       => __( 'Parent Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Parent Post', 'wpmastertoolkit' ),
                "description" => __( 'The parent item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'search_items' => array(
                "label"       => __( 'Search Items', 'wpmastertoolkit' ),
                "placeholder" => __( 'Search Posts', 'wpmastertoolkit' ),
                "description" => __( 'The search items label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'not_found' => array(
                "label"       => __( 'Not Found', 'wpmastertoolkit' ),
                "placeholder" => __( 'No Posts Found', 'wpmastertoolkit' ),
                "description" => __( 'The not found label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'not_found_in_trash' => array(
                "label"       => __( 'Not Found in Trash', 'wpmastertoolkit' ),
                "placeholder" => __( 'No Posts Found in Trash', 'wpmastertoolkit' ),
                "description" => __( 'The not found in trash label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'archives' => array(
                "label"       => __( 'Archives', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Archives', 'wpmastertoolkit' ),
                "description" => __( 'The archives label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'attributes' => array(
                "label"       => __( 'Attributes', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Attributes', 'wpmastertoolkit' ),
                "description" => __( 'The attributes label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'featured_image' => array(
                "label"       => __( 'Featured Image', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Featured Image', 'wpmastertoolkit' ),
                "description" => __( 'The featured image label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'set_featured_image' => array(
                "label"       => __( 'Set Featured Image', 'wpmastertoolkit' ),
                "placeholder" => __( 'Set Post Featured Image', 'wpmastertoolkit' ),
                "description" => __( 'The set featured image label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'remove_featured_image' => array(
                "label"       => __( 'Remove Featured Image', 'wpmastertoolkit' ),
                "placeholder" => __( 'Remove Post Featured Image', 'wpmastertoolkit' ),
                "description" => __( 'The remove featured image label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'use_featured_image' => array(
                "label"       => __( 'Use Featured Image', 'wpmastertoolkit' ),
                "placeholder" => __( 'Use Post Featured Image', 'wpmastertoolkit' ),
                "description" => __( 'The use featured image label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'insert_into_item' => array(
                "label"       => __( 'Insert into Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Insert into Post', 'wpmastertoolkit' ),
                "description" => __( 'The insert into item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'uploaded_to_this_item' => array(
                "label"       => __( 'Uploaded to this Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Uploaded to this Post', 'wpmastertoolkit' ),
                "description" => __( 'The uploaded to this item label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'filter_items_list' => array(
                "label"       => __( 'Filter Items List', 'wpmastertoolkit' ),
                "placeholder" => __( 'Filter Posts List', 'wpmastertoolkit' ),
                "description" => __( 'The filter items list label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'filter_by_date' => array(
                "label"       => __( 'Filter by Date', 'wpmastertoolkit' ),
                "placeholder" => __( 'Filter Posts by Date', 'wpmastertoolkit' ),
                "description" => __( 'The filter by date label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'items_list_navigation' => array(
                "label"       => __( 'Items List Navigation', 'wpmastertoolkit' ),
                "placeholder" => __( 'Posts List Navigation', 'wpmastertoolkit' ),
                "description" => __( 'The items list navigation label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'items_list' => array(
                "label"       => __( 'Items List', 'wpmastertoolkit' ),
                "placeholder" => __( 'Posts List', 'wpmastertoolkit' ),
                "description" => __( 'The items list label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_published' => array(
                "label"       => __( 'Item Published', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Published', 'wpmastertoolkit' ),
                "description" => __( 'The item published label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_published_privately' => array(
                "label"       => __( 'Item Published Privately', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Published Privately', 'wpmastertoolkit' ),
                "description" => __( 'The item published privately label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_reverted_to_draft' => array(
                "label"       => __( 'Item Reverted to Draft', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Reverted to Draft', 'wpmastertoolkit' ),
                "description" => __( 'The item reverted to draft label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_scheduled' => array(
                "label"       => __( 'Item Scheduled', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Scheduled', 'wpmastertoolkit' ),
                "description" => __( 'The item scheduled label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_updated' => array(
                "label"       => __( 'Item Updated', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Updated', 'wpmastertoolkit' ),
                "description" => __( 'The item updated label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_link' => array(
                "label"       => __( 'Item Link', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Link', 'wpmastertoolkit' ),
                "description" => __( 'The item link label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'item_link_description' => array(
                "label"       => __( 'Item Link Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'Post Link Description', 'wpmastertoolkit' ),
                "description" => __( 'The item link description label for the custom post type.', 'wpmastertoolkit' ),
            ),
            'enter_title_here' => array(
                "label"       => __( 'Enter Title Here', 'wpmastertoolkit' ),
                "placeholder" => __( 'Enter Post Title Here', 'wpmastertoolkit' ),
                "description" => __( 'The enter title here label for the custom post type.', 'wpmastertoolkit' ),
            ),
        );
        switch( $filter ) {
            case 'required':
                return array_filter( $labels, function( $label ) {
                    return $label['required'] ?? false;
                });
            case 'optional':
                return array_filter( $labels, function( $label ) {
                    return ! ( $label['required'] ?? false );
                });
            default:
                return $labels;
        }
    }
    
    /**
     * get_taxonomy_labels
     *
     * @param  mixed $filter
     * @return void
     */
    public function get_taxonomy_labels( $filter = false ) {
        $labels = array(
            'name' => array(
                "label"       => __( 'Plural Label', 'wpmastertoolkit' ),
                "required"    => true,
                "placeholder" => __( 'Genres', 'wpmastertoolkit' ),
                "description" => __( 'The plural label for the taxonomy.', 'wpmastertoolkit' ),
            ),
            'singular_name' => array(
                "label"       => __( 'Singular Label', 'wpmastertoolkit' ),
                "required"    => true,
                "placeholder" => __( 'Genre', 'wpmastertoolkit' ),
                "description" => __( 'The singular label for the taxonomy.', 'wpmastertoolkit' ),
            ),
            'taxonomy' => array(
                "label"       => __( 'Taxonomy Key', 'wpmastertoolkit' ),
                "required"    => true,
                "placeholder" => __( 'genre', 'wpmastertoolkit' ),
                "description" => __( 'The post type key for the taxonomy.', 'wpmastertoolkit' ),
            ),
            'description' => array(
                "label"       => __( 'Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'This content type is used to...', 'wpmastertoolkit' ),
                "description" => __( 'A short description of the taxonomy.', 'wpmastertoolkit' ),
            ),
            'menu_name' => array(
                "label"       => __( 'Menu Label', 'wpmastertoolkit' ),
                "placeholder" => __( 'Tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the menu name text.', 'wpmastertoolkit' ),
            ),
            'all_items' => array(
                "label"       => __( 'All Items', 'wpmastertoolkit' ),
                "placeholder" => __( 'All Tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the all items text.', 'wpmastertoolkit' ),
            ),
            'edit_item' => array(
                "label"       => __( 'Edit Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Edit Tag', 'wpmastertoolkit' ),
                "description" => __( 'At the top of the editor screen when editing a term.', 'wpmastertoolkit' ),
            ),
            'view_item' => array(
                "label"       => __( 'View Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'View Tag', 'wpmastertoolkit' ),
                "description" => __( 'In the admin bar to view term during editing.', 'wpmastertoolkit' ),
            ),
            'update_item' => array(
                "label"       => __( 'Update Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Update Tag', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the update item text.', 'wpmastertoolkit' ),
            ),
            'add_new_item' => array(
                "label"       => __( 'Add New Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Add New Tag', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the add new item text.', 'wpmastertoolkit' ),
            ),
            'new_item_name' => array(
                "label"       => __( 'New Item Name', 'wpmastertoolkit' ),
                "placeholder" => __( 'New Tag Name', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the new item name text.', 'wpmastertoolkit' ),
            ),
            'parent_item' => array(
                "label"       => __( 'Parent Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Parent Category', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the parent item text.', 'wpmastertoolkit' ),
            ),
            'parent_item_colon' => array(
                "label"       => __( 'Parent Item With Colon', 'wpmastertoolkit' ),
                "placeholder" => __( 'Parent Category:', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the parent item with colon text.', 'wpmastertoolkit' ),
            ),
            'search_items' => array(
                "label"       => __( 'Search Items', 'wpmastertoolkit' ),
                "placeholder" => __( 'Search Tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the search items text.', 'wpmastertoolkit' ),
            ),
            'popular_items' => array(
                "label"       => __( 'Popular Items', 'wpmastertoolkit' ),
                "placeholder" => __( 'Popular Tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the popular items text.', 'wpmastertoolkit' ),
            ),
            'separate_items_with_commas' => array(
                "label"       => __( 'Separate Items With Commas', 'wpmastertoolkit' ),
                "placeholder" => __( 'Separate tags with commas', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the separate items with commas text.', 'wpmastertoolkit' ),
            ),
            'add_or_remove_items' => array(
                "label"       => __( 'Add Or Remove Items', 'wpmastertoolkit' ),
                "placeholder" => __( 'Add or remove tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the add or remove items text.', 'wpmastertoolkit' ),
            ),
            'choose_from_most_used' => array(
                "label"       => __( 'Choose From Most Used', 'wpmastertoolkit' ),
                "placeholder" => __( 'Choose from the most used tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the choose from most used text.', 'wpmastertoolkit' ),
            ),
            'most_used' => array(
                "label"       => __( 'Most Used', 'wpmastertoolkit' ),
                "placeholder" => __( 'Most Used', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the most used text.', 'wpmastertoolkit' ),
            ),
            'not_found' => array(
                "label"       => __( 'Not Found', 'wpmastertoolkit' ),
                "placeholder" => __( 'No tags found', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the not found text.', 'wpmastertoolkit' ),
            ),
            'no_terms' => array(
                "label"       => __( 'No Terms', 'wpmastertoolkit' ),
                "placeholder" => __( 'No tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the no terms text.', 'wpmastertoolkit' ),
            ),
            'name_field_description' => array(
                "label"       => __( 'Name Field Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'The name is how it appears on your site', 'wpmastertoolkit' ),
                "description" => __( 'The name is how it appears on your site', 'wpmastertoolkit' ),
            ),
            'slug_field_description' => array(
                "label"       => __( 'Slug Field Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'The &quot;slug&quot; is the URL-friendly version of the name. It is usually all lower case and contains only letters, numbers, and hyphens.', 'wpmastertoolkit' ),
                "description" => __( 'Describes the Slug field on the Edit Tags screen.', 'wpmastertoolkit' ),
            ),
            'parent_field_description' => array(
                "label"       => __( 'Parent Field Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band', 'wpmastertoolkit' ),
                "description" => __( 'Describes the Parent field on the Edit Tags screen.', 'wpmastertoolkit' ),
            ),
            'desc_field_description' => array(
                "label"       => __( 'Description Field Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'The description is not prominent by default; however, some themes may show it.', 'wpmastertoolkit' ),
                "description" => __( 'Describes the Description field on the Edit Tags screen.', 'wpmastertoolkit' ),
            ),
            'filter_by_item' => array(
                "label"       => __( 'Filter By Item', 'wpmastertoolkit' ),
                "placeholder" => __( 'Filter by category', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the filter by item text.', 'wpmastertoolkit' ),
            ),
            'items_list_navigation' => array(
                "label"       => __( 'Items List Navigation', 'wpmastertoolkit' ),
                "placeholder" => __( 'Tags list navigation', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the items list navigation text.', 'wpmastertoolkit' ),
            ),
            'items_list' => array(
                "label"       => __( 'Items List', 'wpmastertoolkit' ),
                "placeholder" => __( 'Tags list', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the items list text.', 'wpmastertoolkit' ),
            ),
            'back_to_items' => array(
                "label"       => __( 'Back To Items', 'wpmastertoolkit' ),
                "placeholder" => __( '← Go to tags', 'wpmastertoolkit' ),
                "description" => __( 'Assigns the back to items text.', 'wpmastertoolkit' ),
            ),
            'item_link' => array(
                "label"       => __( 'Item Link', 'wpmastertoolkit' ),
                "placeholder" => __( 'Tag Link', 'wpmastertoolkit' ),
                "description" => __( 'Assigns a title for navigation link block variation used in the block editor.', 'wpmastertoolkit' ),
            ),
            'item_link_description' => array(
                "label"       => __( 'Item Link Description', 'wpmastertoolkit' ),
                "placeholder" => __( 'Describes a navigation link block variation used in the block editor.', 'wpmastertoolkit' ),
            ),
        );
        switch( $filter ) {
            case 'required':
                return array_filter( $labels, function( $label ) {
                    return $label['required'] ?? false;
                });
            case 'optional':
                return array_filter( $labels, function( $label ) {
                    return ! ( $label['required'] ?? false );
                });
            default:
                return $labels;
        }
    }
    
    /**
     * save_post
     *
     * @param  mixed $post_id
     * @return void
     */
    public function save_post( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== $this->post_type ) return;

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['content_type'] ) ) {
			//phpcs:ignore WordPress.Security.NonceVerification.Missing
            $content_type = sanitize_text_field( wp_unslash( $_POST['content_type'] ) );
            update_post_meta( $post_id, 'content_type', $content_type );
        }
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST[ $this->content_type_settings ] ) && is_array( $_POST[ $this->content_type_settings ] ) ) {
            $content_type = get_post_meta( $post_id, 'content_type', true );
            switch( $content_type ) {
                case 'cpt':
                    //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $settings = $this->clean_settings_cpt( wp_unslash( $_POST[ $this->content_type_settings ] ) );
                    break;
                    case 'taxonomy':
                    //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $settings = $this->clean_settings_taxonomy( wp_unslash( $_POST[ $this->content_type_settings ] ) );
                    break;
                case 'option_page':
                    // TODO: Add option page settings
                    break;
                default:
                    return false;
                    break;
            }
            update_post_meta( $post_id, $this->content_type_settings, $settings );

			//phpcs:ignore WordPress.Security.NonceVerification.Missing
            if( isset( $_POST['post_status'] ) && $_POST['post_status'] === 'publish' ) {
                $this->generate_registration_file( $post_id );
            } else {
                $this->delete_registration_file( $post_id );
            }
        }
    }
    
    /**
     * text_to_boolean
     *
     * @param  mixed $bool_text
     * @return void
     */
    public function text_to_boolean( $bool_text ) {
        $bool_text = (string) $bool_text;
        if ( empty( $bool_text ) || '0' === $bool_text || 'false' === $bool_text ) {
            return 'false';
        }
    
        return 'true';
    }
    
    /**
     * generate_cpt_registration_code
     *
     * @param  mixed $post_id
     * @return void
     */
    public function generate_cpt_registration_code( $post_id ) {
        $content_type = get_post_meta( $post_id, 'content_type', true );

        if( $content_type !== 'cpt' ) return;

        $settings = $this->get_settings_cpt( $post_id );

        ob_start();
        include ( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/cpt-code-template.php' );
        return ob_get_clean();
    }

    /**
     * generate_taxonomy_registration_code
     *
     * @param  mixed $post_id
     * @return void
     */
    public function generate_taxonomy_registration_code( $post_id ) {
        $content_type = get_post_meta( $post_id, 'content_type', true );

        if( $content_type !== 'taxonomy' ) return;

        $settings = $this->get_settings_taxonomy( $post_id );

        ob_start();
        include ( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/taxonomy-code-template.php' );
        return ob_get_clean();
    }
    
    /**
     * generate_registration_file
     *
     * @return void
     */
    public function generate_registration_file($post_id){
        $title = get_the_title( $post_id );

        $content  = '<?php'. PHP_EOL;
        $content .='if ( ! defined( \'ABSPATH\' ) ) exit; // Exit if accessed directly'. PHP_EOL . PHP_EOL;
        $content .= '/**' . PHP_EOL;
        $content .= !empty($title) ? ' * Title: ' . $title . PHP_EOL : '';
        $content .= ' * ID: ' . $post_id . PHP_EOL;
        $content .= ' * Generated at: ' . wp_date('Y-m-d H:i:s') . PHP_EOL;
        $content .= ' *' . PHP_EOL;
        $content .= ' * @author This code is generated by WPMasterToolkit' . PHP_EOL;
        $content .= ' * @link ' . get_edit_post_link( $post_id, '' ) . PHP_EOL;
        $content .= ' * @since ' . WPMASTERTOOLKIT_VERSION . PHP_EOL;
        $content .= ' *' . PHP_EOL;
        $content .= '**/' . PHP_EOL;
        
        $content_type = get_post_meta( $post_id, 'content_type', true );
        
        switch( $content_type ) {
            case 'cpt':
                $content .= $this->generate_cpt_registration_code( $post_id );
                break;
            case 'taxonomy':
                $content .= $this->generate_taxonomy_registration_code( $post_id );
                break;
            case 'option_page':
                break;
            default:
                return false;
                break;
        }

        $file_path = $this->get_code_file_path( $post_id );
        
        file_put_contents( $file_path, $content );
		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
        chmod( $file_path, 0644 );
    }
    
    /**
     * get_settings_cpt
     *
     * @param  mixed $post_id
     * @return void
     */
    public function get_settings_cpt( $post_id ) {
        $settings = get_post_meta( $post_id, $this->content_type_settings, true );

        if( empty( $settings ) || !is_array( $settings ) ) {
            $settings = $this->default_settings_cpt();
        } else {
            $settings = $this->clean_settings_cpt( $settings );
        }

        return $settings;
    }
    
    /**
     * clean_settings_cpt
     *
     * @param  mixed $settings
     * @return void
     */
    public function clean_settings_cpt( $settings ){
		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if( isset($_POST[$this->content_type_settings]) && is_array( $_POST[$this->content_type_settings] ) ) {
            $settings['supports'] = isset($settings['supports']) ? $settings['supports'] : array();
        }
        $settings         = !empty( $settings ) && is_array( $settings ) ? $settings : array();
        $default_settings = $this->default_settings_cpt();
        $settings         = array_merge( $default_settings, $settings );
        $settings         = array_map( function( $item ) {
            return is_array( $item ) ? array_map( 'sanitize_text_field', $item ) : sanitize_text_field( $item );
        }, $settings );

        $settings['post_type'] = sanitize_title( $settings['post_type'] );

        return $settings;
    }
    
    /**
     * get_all_wp_capabilities
     *
     * @return void
     */
    public function get_all_wp_capabilities() {
        if( $this->all_wp_capabilities ) {
            return $this->all_wp_capabilities;
        }
        $all_capabilities = array();
    
        foreach ( wp_roles()->roles as $role ) {
            if ( isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ) {
                $all_capabilities += $role['capabilities'];
            }
        }
    
        $unique_caps = array_keys( array_filter( $all_capabilities ) );
        $this->all_wp_capabilities = array_combine( $unique_caps, $unique_caps );
        
        return $this->all_wp_capabilities;
    }
    
    /**
     * default_settings_cpt
     *
     * @return void
     */
    public function default_settings_cpt(){
        return array ( 
            'public' => '1', 
            'hierarchical' => '0', 
            'supports' => array ( 'title', 'editor', 'thumbnail', 'custom-fields', ), 
            'taxonomies' => array(),
            'name' => '', 
            'singular_name' => '', 
            'post_type' => '', 
            'text_domain' => '', 
            'manage_optional_labels' => '0', 
            'description' => '', 
            'menu_name' => '', 
            'all_items' => '', 
            'edit_item' => '', 
            'view_item' => '', 
            'add_new_item' => '', 
            'add_new' => '', 
            'new_item' => '', 
            'parent_item_colon' => '', 
            'search_items' => '', 
            'not_found' => '', 
            'not_found_in_trash' => '', 
            'archives' => '', 
            'attributes' => '', 
            'featured_image' => '', 
            'set_featured_image' => '', 
            'remove_featured_image' => '', 
            'use_featured_image' => '', 
            'insert_into_item' => '', 
            'uploaded_to_this_item' => '', 
            'filter_items_list' => '', 
            'filter_by_date' => '', 
            'items_list_navigation' => '', 
            'items_list' => '', 
            'item_published' => '', 
            'item_published_privately' => '', 
            'item_reverted_to_draft' => '', 
            'item_scheduled' => '', 
            'item_updated' => '', 
            'item_link' => '', 
            'item_link_description' => '', 
            'enter_title_here' => '', 
            'show_ui' => '1', 
            'show_in_menu' => '1',
            'admin_menu_parent' => '',
            'menu_position' => '5', 
            'use_dashicon' => '1', 
            'menu_icon' => 'dashicons-admin-post', 
            'custom_menu_icon' => '', 
            'show_in_admin_bar' => '1', 
            'show_in_nav_menus' => '1', 
            'exclude_from_search' => '0', 
            'permalink_rewrite' => 'post_type_key', 
            'slug' => '', 
            'with_front' => '1', 
            'feeds' => '0', 
            'pages' => '1', 
            'has_archive' => '0', 
            'archive_slug' => '', 
            'publicly_queryable' => '1', 
            'query_var' => 'post_type_key', 
            'query_var_name' => '',
            'rename_capabilities' => '0',
            'singular_capability_name' => '', 
            'plural_capability_name' => '', 
            'can_export' => '1', 
            'delete_with_user' => '0', 
            'show_in_rest' => '1', 
            'rest_base' => '', 
            'rest_namespace' => 'wp/v2', 
            'rest_controller_class' => 'WP_REST_Posts_Controller', 
        );
    }
    
    /**
     * get_settings_taxonomy
     *
     * @param  mixed $post_id
     * @return void
     */
    public function get_settings_taxonomy( $post_id ) {
        $settings = get_post_meta( $post_id, $this->content_type_settings, true );

        if( empty( $settings ) || !is_array( $settings ) ) {
            $settings = $this->default_settings_taxonomy();
        } else {
            $settings = $this->clean_settings_taxonomy( $settings );
        }

        return $settings;
    }

    /**
     * clean_settings_taxonomy
     *
     * @param  mixed $settings
     * @return void
     */
    public function clean_settings_taxonomy( $settings ){
        $settings         = !empty( $settings ) && is_array( $settings ) ? $settings : array();
        $default_settings = $this->default_settings_taxonomy();
        $settings         = array_merge( $default_settings, $settings );
        $settings         = array_map( function( $item ) {
            return is_array( $item ) ? array_map( 'sanitize_text_field', $item ) : sanitize_text_field( $item );
        }, $settings );

        $settings['taxonomy'] = sanitize_title( $settings['taxonomy'] );

        return $settings;
    }

    /**
     * default_settings_taxonomy
     *
     * @return void
     */
    public function default_settings_taxonomy(){
        return array(
            'public' => '1',
            'hierarchical' => '0',
            'object_type' => array(),
            'sort' => '0',
            'name' => '',
            'singular_name' => '',
            'taxonomy' => '',
            'text_domain' => '',
            'manage_optional_labels' => '0',
            'description' => '',
            'menu_name' => '',
            'all_items' => '',
            'edit_item' => '',
            'view_item' => '',
            'update_item' => '',
            'add_new_item' => '',
            'new_item_name' => '',
            'parent_item' => '',
            'parent_item_colon' => '',
            'search_items' => '',
            'popular_items' => '',
            'separate_items_with_commas' => '',
            'add_or_remove_items' => '',
            'choose_from_most_used' => '',
            'most_used' => '',
            'not_found' => '',
            'no_terms' => '',
            'name_field_description' => '',
            'slug_field_description' => '',
            'parent_field_description' => '',
            'desc_field_description' => '',
            'filter_by_item' => '',
            'items_list_navigation' => '',
            'items_list' => '',
            'back_to_items' => '',
            'item_link' => '',
            'item_link_description' => '',
            'show_ui' => '1',
            'show_in_menu' => '1',
            'show_in_nav_menus' => '1',
            'show_tagcloud' => '1',
            'show_in_quick_edit' => '1',
            'show_admin_column' => '0',
            'default_term_enabled' => '0',
            'default_term_name' => '',
            'default_term_slug' => '',
            'default_term_description' => '',
            'permalink_rewrite' => 'taxonomy_key',
            'slug' => '',
            'with_front' => '1',
            'rewrite_hierarchical' => '0',
            'pages' => '1',
            'publicly_queryable' => '1',
            'query_var' => 'taxonomy_key',
            'query_var_name' => '',
            'manage_terms' => 'manage_categories',
            'edit_terms' => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_posts',
            'show_in_rest' => '0',
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
          );
    }

    /**
     * get_code_folder_path
     *
     * @return void
     */
    public function get_code_folder_path(){
        if( !empty($this->code_folder_path) ){
            return $this->code_folder_path;
        }
        return wpmastertoolkit_folders() . '/register-custom-content-types';
    }
    
    /**
     * get_code_file_path
     *
     * @param  mixed $post_id
     * @param  mixed $type
     * @return void
     */
    public function get_code_file_path( $post_id, $type = null ) {
        $code_folder_path = $this->get_code_folder_path();
        $type             = !empty($type) ? $type : get_post_meta( $post_id, 'content_type', true );
        $type             = sanitize_title( $type );
        return $code_folder_path . '/register-' . esc_attr( $type ) . '-' . $post_id . '.php';
    }
    
    /**
     * delete_registration_file
     *
     * @param  mixed $post_id
     * @return void
     */
    public function delete_registration_file( $post_id ){
        $file_path = $this->get_code_file_path( $post_id );
        if( file_exists($file_path) ){
            return wp_delete_file( $file_path );
        }
    }
    
    /**
     * delete_file_on_delete_post
     *
     * @param  mixed $post_id
     * @return void
     */
    public function delete_file_on_delete_post( $post_id ){
        if( get_post_type( $post_id ) === $this->post_type ){
            $this->delete_registration_file( $post_id );
        }
    }
    
    /**
     * custom_content_types_loader
     *
     * @return void
     */
    public function custom_content_types_loader(){
        if( defined('WPMASTERTOOLKIT_REGISTER_CUSTOM_CONTENT_TYPES_SAFE_MODE') && WPMASTERTOOLKIT_REGISTER_CUSTOM_CONTENT_TYPES_SAFE_MODE === true ) return;

        $code_folder_path = $this->get_code_folder_path();
        $files = glob( $code_folder_path . '/register-*.php' );
        foreach( $files as $file ){
            $file_name = str_replace('.php', '', basename( $file ));
            if( preg_match('/^register-([a-zA-Z0-9-_]+)-([0-9]+)$/', $file_name, $matches) ){
                require_once $file;
            }
        }
    }

	/**
	 * Render change key popup
	 * 
	 * @since   2.8.0
	 */
	public function render_key_change_popup() {
		global $post_type;

		if ( ! $post_type || ( $post_type !== $this->post_type ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
		if ( $action !== 'edit' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id      = sanitize_text_field( wp_unslash( $_GET['post'] ?? '' ) );
		$content_type = get_post_meta( $post_id, 'content_type', true );
		$is_pro       = wpmastertoolkit_is_pro();

		if ( 'cpt' === $content_type ) {
			include ( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/cpt-key-change-popup.php' );
		} elseif ( 'taxonomy' === $content_type ) {
			include ( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/register-custom-content-types/taxonomy-key-change-popup.php' );
		}
	}

	/**
	 * Handle ajax actions
	 * 
	 * @since   2.8.0
	 */
	public function handle_ajax_actions() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action_02 ) ) {
			wp_send_json_error( __( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			wp_send_json_error( __( 'Upgrade to Pro to use this feature.', 'wpmastertoolkit' ) );
		}

		$content_type = sanitize_text_field( wp_unslash( $_POST['contentType'] ?? '' ) );
		$type         = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$old_value    = sanitize_text_field( wp_unslash( $_POST['oldValue'] ?? '' ) );
		$new_value    = sanitize_text_field( wp_unslash( $_POST['newValue'] ?? '' ) );

		if ( empty( $content_type ) || empty( $type ) || empty( $old_value ) || empty( $new_value ) ) {
			wp_send_json_error( __( 'Invalid request. Please try again.', 'wpmastertoolkit' ) );
		}

		if ( 'cpt' === $content_type ) {
			if ( $type === 'migrate' ) {
				$this->start_cron_cpt_migrate( $old_value, $new_value );
				wp_send_json_success();
			} elseif ( $type === 'delete' ) {
				$this->start_cron_cpt_delete( $old_value, $new_value );
				wp_send_json_success();
			}
		} elseif ( 'taxonomy' === $content_type ) {
			if ( $type === 'migrate' ) {
				$this->start_cron_taxonomy_migrate( $old_value, $new_value );
				wp_send_json_success();
			} elseif ( $type === 'delete' ) {
				$this->start_cron_taxonomy_delete( $old_value, $new_value );
				wp_send_json_success();
			}
		}

		wp_send_json_error();
	}

	/**
	 * Register cron jobs
	 * 
	 * @since   2.8.0
	 */
	public function crons_registrations( $schedules ) {
		$schedules[ $this->cron_cpt_migrate_recurrence ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'wpmastertoolkit' )
		);
		$schedules[ $this->cron_cpt_delete_recurrence ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'wpmastertoolkit' )
		);
		$schedules[ $this->cron_taxonomy_migrate_recurrence ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'wpmastertoolkit' )
		);
		$schedules[ $this->cron_taxonomy_delete_recurrence ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'wpmastertoolkit' )
		);

		return $schedules;
	}

	/**
	 * Execute cron cpt migrate
	 * 
	 * @since   2.8.0
	 */
	public function excute_cron_cpt_migrate() {
		$time_start = microtime(true);
		$status     = get_option( $this->option_cpt_migrate_status, 'finish' );

		if ( $status != 'running' ) {
			$this->end_cron_cpt_migrate();
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->end_cron_cpt_migrate();
		}

		$post_types = get_option( $this->option_cpt_migrate_posts, array() );
		$posts      = $this->posts_to_handle( $post_types );
		foreach ( $posts as $post ) {
			if ( $time_start + 55 < microtime(true) ) {
				exit;
			}

			$args = array(
				'ID'        => $post['id'],
				'post_type' => $post['new'],
			);
			wp_update_post( $args );
		}

		$this->end_cron_cpt_migrate();
	}

	/**
	 * Execute cron cpt delete
	 * 
	 * @since   2.8.0
	 */
	public function excute_cron_cpt_delete() {
		$time_start = microtime(true);
		$status     = get_option( $this->option_cpt_delete_status, 'finish' );

		if ( $status != 'running' ) {
			$this->end_cron_cpt_delete();
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->end_cron_cpt_delete();
		}

		$post_types = get_option( $this->option_cpt_delete_posts, array() );
		$posts      = $this->posts_to_handle( $post_types );
		foreach ( $posts as $post ) {
			if ( $time_start + 55 < microtime(true) ) {
				exit;
			}

			wp_delete_post( $post['id'], true );
		}

		$this->end_cron_cpt_delete();
	}

	/**
	 * Execute cron taxonomy migrate
	 * 
	 * @since   2.8.0
	 */
	public function excute_cron_taxonomy_migrate() {
		global $wpdb;

		$time_start = microtime(true);
		$status     = get_option( $this->option_taxonomy_migrate_status, 'finish' );

		if ( $status != 'running' ) {
			$this->end_cron_taxonomy_migrate();
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->end_cron_taxonomy_migrate();
		}

		$taxonomies = get_option( $this->option_taxonomy_migrate_terms, array() );
		$terms      = $this->terms_to_handle( $taxonomies );
		foreach ( $terms as $term ) {
			if ( $time_start + 55 < microtime(true) ) {
				exit;
			}

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $term['new'] ) , array( 'term_taxonomy_id' => $term['id'] ) );
		}

		delete_option( $this->option_taxonomy_migrate_terms );
		$this->end_cron_taxonomy_migrate();
	}

	/**
	 * Execute cron taxonomy delete
	 * 
	 * @since   2.8.0
	 */
	public function excute_cron_taxonomy_delete() {
		global $wpdb;

		$time_start = microtime(true);
		$status     = get_option( $this->option_taxonomy_delete_status, 'finish' );

		if ( $status != 'running' ) {
			$this->end_cron_taxonomy_delete();
		}

		if ( ! wpmastertoolkit_is_pro() ) {
			$this->end_cron_taxonomy_delete();
		}

		$taxonomies = get_option( $this->option_taxonomy_delete_terms, array() );
		$terms      = $this->terms_to_handle( $taxonomies );
		foreach ( $terms as $term ) {
			if ( $time_start + 55 < microtime(true) ) {
				exit;
			}

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term['id'] ) );
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => $term['id'] ) );
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $wpdb->terms, array( 'term_id' => $term['id'] ) );
		}

		delete_option( $this->option_taxonomy_delete_terms );
		$this->end_cron_taxonomy_delete();
	}

	/**
	 * Bulk actions
	 * 
	 * @since   2.8.0
	 */
	public function bulk_actions( $actions ) {

		$title = __( 'Export code', 'wpmastertoolkit' );

		if ( ! wpmastertoolkit_is_pro() ) {
			$title .= ' (' . __( 'Pro only', 'wpmastertoolkit' ) . ')';
		}

		$actions[ 'wpmtk-code-export' ] = $title;
		return $actions;
	}

	/**
	 * Handle bulk actions
	 * 
	 * @since   2.8.0
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		
		if ( wpmastertoolkit_is_pro() && $doaction == 'wpmtk-code-export' ) {

			$content_types = array();
			foreach ( $post_ids as $post_id ) {
				$content_type    = get_post_meta( $post_id, 'content_type', true );
				$content_types[] = $content_type;
			}

			$redirect_to = add_query_arg(
				array(
					'post_type'     => $this->post_type,
					'page'          => $this->export_submenu,
					'nonce'         => wp_create_nonce( $this->nonce_action_02 ),
					'content_types' => implode( ',', $content_types ),
					'post_ids'      => implode( ',', $post_ids ),
				),
				admin_url( 'edit.php' ),
			);
		}

		return $redirect_to;
	}

	/**
	 * Add submenu pages
	 * 
	 * @since   2.8.0
	 */
	public function add_submenu() {
		add_submenu_page(
			'edit.php?post_type=' . $this->post_type,
			__( 'Register Custom Content Types Export', 'wpmastertoolkit' ),
			__( 'Register Custom Content Types Export', 'wpmastertoolkit' ),
			'manage_options',
			$this->export_submenu,
			array( $this, 'render_export_submenu' ),
			null
		);
	}

	/**
	 * Render export submenu page
	 * 
	 * @since   2.8.0
	 */
	public function render_export_submenu() {
		$code_editor = wp_enqueue_code_editor( array( 
            'type' => 'php',
            'codemirror' => array(
                'mode' => array(
                    'name'      => 'php',
                    'startOpen' => true
                ),
                'inputStyle'      => 'textarea',
                'matchBrackets'   => true,
                'extraKeys'       => array(
                    'Alt-F'      => 'findPersistent',
                    'Ctrl-Space' => 'autocomplete',
                    'Ctrl-/'     => 'toggleComment',
                    'Cmd-/'      => 'toggleComment',
                    'Alt-Up'     => 'swapLineUp',
                    'Alt-Down'   => 'swapLineDown',
                ),
                'lint'             => true,
                'direction'        => 'ltr',
				'readOnly'         => 'nocursor',
                'colorpicker'      => array( 'mode' => 'read' ),
                'foldOptions'      => array( 'widget' => '...' ),
                'theme'            => 'wpmastertoolkit',
                'continueComments' => true,
            ),
        ) );

		$export_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/pro/register-custom-content-types-export.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_export_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/pro/register-custom-content-types-export.css', array(), $export_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_export_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/pro/register-custom-content-types-export.js', $export_assets['dependencies'], $export_assets['version'], true );
		wp_localize_script( 'WPMastertoolkit_export_submenu', 'wpmastertoolkit_export_submenu', array(
            'code_editor' => $code_editor,
        ) );

		$this->header_title = __( 'Register Custom Content Types Export', 'wpmastertoolkit' );
		$this->disable_form = true;
		include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
	}

	/**
     * Add the submenu content
     * 
     * @since   2.8.0
     */
    private function submenu_content() {

		if ( ! wpmastertoolkit_is_pro() ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action_02 ) ) {
			wp_die( esc_html__( 'Refresh the page and try again.', 'wpmastertoolkit' ) );
		}

		$content_types = sanitize_text_field( wp_unslash( $_GET['content_types'] ?? '' ) );
		$post_ids      = sanitize_text_field( wp_unslash( $_GET['post_ids'] ?? '' ) );

		$content_types = explode( ',', $content_types );
		$post_ids      = explode( ',', $post_ids );

		$posts = array();
		foreach ( $post_ids as $index => $post_id ) {
			if ( ! isset( $content_types[ $index ] ) ) {
				continue;
			}
			$posts[ $post_id ] = $content_types[ $index ];
		}


		$content = '';
		foreach ( $posts as $post_id => $content_type ) {
			if ( 'cpt' == $content_type ) {
				$content .= $this->generate_cpt_registration_code( $post_id );
				$content .= "\n\n";
			} elseif ( 'taxonomy' == $content_type ) {
				$content .= $this->generate_taxonomy_registration_code( $post_id );
				$content .= "\n\n";
			}
		}

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__body">
					<div class="wp-mastertoolkit__section__body__item">
						<div class="wp-mastertoolkit__section__body__item__content">
							<textarea id="JS-code-editor"><?php echo esc_textarea( wp_unslash( $content ) ); ?></textarea>
						</div>
                    </div>
                </div>
            </div>
        <?php
    }

	/**
	 * Start cron job for cpt migrate
	 * 
	 * @since   2.8.0
	 */
	private function start_cron_cpt_migrate( $old_value, $new_value ) {
		if ( ! wp_next_scheduled( $this->cron_cpt_migrate_hook ) ) {
			wp_schedule_event( time(), $this->cron_cpt_migrate_recurrence, $this->cron_cpt_migrate_hook );
		}

		$posts = get_option( $this->option_cpt_migrate_posts, array() );
		$posts[$old_value] = $new_value;

		update_option( $this->option_cpt_migrate_posts, $posts );
		update_option( $this->option_cpt_migrate_status, 'running' );
	}

	/**
	 * Start cron job for cpt delete
	 * 
	 * @since   2.8.0
	 */
	private function start_cron_cpt_delete( $old_value, $new_value ) {
		if ( ! wp_next_scheduled( $this->cron_cpt_delete_hook ) ) {
			wp_schedule_event( time(), $this->cron_cpt_delete_recurrence, $this->cron_cpt_delete_hook );
		}

		$posts = get_option( $this->option_cpt_delete_posts, array() );
		$posts[$old_value] = $new_value;

		update_option( $this->option_cpt_delete_posts, $posts );
		update_option( $this->option_cpt_delete_status, 'running' );
	}

	/**
	 * Start cron job for taxonomy migrate
	 * 
	 * @since   2.8.0
	 */
	private function start_cron_taxonomy_migrate( $old_value, $new_value ) {
		if ( ! wp_next_scheduled( $this->cron_taxonomy_migrate_hook ) ) {
			wp_schedule_event( time(), $this->cron_taxonomy_migrate_recurrence, $this->cron_taxonomy_migrate_hook );
		}

		$taxonomies = get_option( $this->option_taxonomy_migrate_terms, array() );
		$taxonomies[$old_value] = $new_value;

		update_option( $this->option_taxonomy_migrate_terms, $taxonomies );
		update_option( $this->option_taxonomy_migrate_status, 'running' );
	}

	/**
	 * Start cron job for taxonomy delete
	 * 
	 * @since   2.8.0
	 */
	private function start_cron_taxonomy_delete( $old_value, $new_value ) {
		if ( ! wp_next_scheduled( $this->cron_taxonomy_delete_hook ) ) {
			wp_schedule_event( time(), $this->cron_taxonomy_delete_recurrence, $this->cron_taxonomy_delete_hook );
		}

		$taxonomies = get_option( $this->option_taxonomy_delete_terms, array() );
		$taxonomies[$old_value] = $new_value;

		update_option( $this->option_taxonomy_delete_terms, $taxonomies );
		update_option( $this->option_taxonomy_delete_status, 'running' );
	}

	/**
	 * End cron job for cpt migrate
	 * 
	 * @since   2.8.0
	 */
	private function end_cron_cpt_migrate() {
		update_option( $this->option_cpt_migrate_status, 'finish' );

		if ( wp_next_scheduled( $this->cron_cpt_migrate_hook ) ) {
			wp_clear_scheduled_hook( $this->cron_cpt_migrate_hook );
		}

		exit;
	}
	
	/**
	 * End cron job for cpt delete
	 * 
	 * @since   2.8.0
	 */
	private function end_cron_cpt_delete() {
		update_option( $this->option_cpt_delete_status, 'finish' );

		if ( wp_next_scheduled( $this->cron_cpt_delete_hook ) ) {
			wp_clear_scheduled_hook( $this->cron_cpt_delete_hook );
		}

		exit;
	}

	/**
	 * End cron job for taxonomy migrate
	 * 
	 * @since   2.8.0
	 */
	private function end_cron_taxonomy_migrate() {
		update_option( $this->option_taxonomy_migrate_status, 'finish' );

		if ( wp_next_scheduled( $this->cron_taxonomy_migrate_hook ) ) {
			wp_clear_scheduled_hook( $this->cron_taxonomy_migrate_hook );
		}

		exit;
	}

	/**
	 * End cron job for taxonomy delete
	 * 
	 * @since   2.8.0
	 */
	private function end_cron_taxonomy_delete() {
		update_option( $this->option_taxonomy_delete_status, 'finish' );

		if ( wp_next_scheduled( $this->cron_taxonomy_delete_hook ) ) {
			wp_clear_scheduled_hook( $this->cron_taxonomy_delete_hook );
		}

		exit;
	}

	/**
	 * Get posts to handle
	 * 
	 * @since   2.8.0
	 */
	private function posts_to_handle( $post_types ) {
		$result = array();
		foreach ( $post_types as $old_post_type => $new_post_type ) {
			$posts = get_posts( array(
				'post_type'   => $old_post_type,
				'numberposts' => -1,
				'fields'      => 'ids',
			) );

			foreach ( $posts as $post_id ) {
				$result[] = array(
					'id'  => $post_id,
					'old' => $old_post_type,
					'new' => $new_post_type,
				);
			}
		}

		return $result;
	}

	/**
	 * Get terms to handle
	 * 
	 * @since   2.8.0
	 */
	private function terms_to_handle( $taxonomies ) {
		$result = array();
		foreach ( $taxonomies as $old_taxonomy => $new_taxonomy ) {
			// Dont use get_terms() due to the use of taxonomy_exists().
			$args = array(
				'taxonomy'   => $old_taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			);
			$term_query = new WP_Term_Query();
			$terms      = $term_query->query( $args );

			foreach ( $terms as $term_id ) {
				$result[] = array(
					'id'  => $term_id,
					'old' => $old_taxonomy,
					'new' => $new_taxonomy,
				);
			}
		}

		return $result;
	}
}
