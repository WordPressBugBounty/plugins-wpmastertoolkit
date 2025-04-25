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
    
    private $code_folder_path;

    /**
     * Constructor.
     * 
     * @since 2.6.0
     */
    public function __construct() {
        
        add_action( 'init', array( $this, 'register_content_type_cpt' ) );
        add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'wp_sitemaps_post_types', array( $this, 'remove_from_sitemap' ), 10, 2 );
        add_action( 'edit_form_top', array( $this, 'render_edit_post_html' ) );
        add_action( 'save_post_' . $this->post_type, array( $this, 'save_post' ) );
        
        add_action( 'before_delete_post', array( $this, 'delete_file_on_delete_post' ) );
        add_action( 'wp_trash_post', array( $this, 'delete_file_on_delete_post' ) );

        add_filter( 'wpmastertoolkit/folders', array( $this, 'create_folders' ) );

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
            'add_new'            => __( 'Add New Content Type', 'wpmastertoolkit' ),
            'add_new_item'       => __( 'Create New Content Type', 'wpmastertoolkit' ),
            'edit_item'          => __( 'Edit Content Type', 'wpmastertoolkit' ),
            'new_item'           => __( 'New Content Type', 'wpmastertoolkit' ),
            'all_items'          => __( 'All Content Types', 'wpmastertoolkit' ),
            'view_item'          => __( 'View Content Type', 'wpmastertoolkit' ),
            'search_items'       => __( 'Search Content Types', 'wpmastertoolkit' ),
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
        }

        if ( $column === 'status' ) {
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
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            if ( get_post_type() !== $this->post_type ) return;

            require_once( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/helpers/core/select-dashicon/class-select-dashicon.php' );
            wp_enqueue_style('dashicons');
            
            $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/register-custom-content-types.asset.php' );
            wp_enqueue_style( 'WPMastertoolkit_custom_content_types_post', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/register-custom-content-types.css', array(), $assets['version'], 'all' );
            wp_enqueue_script( 'WPMastertoolkit_custom_content_types_post', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/register-custom-content-types.js', $assets['dependencies'], $assets['version'], true );
            wp_localize_script( 'WPMastertoolkit_custom_content_types_post', 'wpmtk_custom_content_types', array(
                'content_type' => get_post_meta( get_the_ID(), 'content_type', true ),
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
            //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $settings = $this->clean_settings_cpt( wp_unslash( $_POST[ $this->content_type_settings ] ) );
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
}
