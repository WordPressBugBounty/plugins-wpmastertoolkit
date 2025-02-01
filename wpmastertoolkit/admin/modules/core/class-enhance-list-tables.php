<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Enhance List Tables
 * Description: Improve the usefulness of listing pages for various post types and taxonomies, media, comments and users by adding / removing columns and elements.
 * @since 1.4.0
 */
class WPMastertoolkit_Enhance_List_Tables {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     * 
     * @since    1.4.0
     */
    public function __construct() {
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_enhance_list_tables';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'manage_posts_columns', array( $this, 'add_post_type_column' ), 10, 2 );
        add_filter( 'manage_pages_columns', array( $this, 'add_post_type_column' ) );
        add_action( 'manage_posts_custom_column', array( $this, 'render_post_type_column' ), 10, 2 );
        add_action( 'manage_pages_custom_column', array( $this, 'render_post_type_column' ), 10, 2 );
        add_action( 'init', array( $this, 'add_taxonomies_columns' ) );
        add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
        add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
        add_filter( 'manage_edit-comments_columns', array( $this, 'add_comments_column' ) );
        add_action( 'manage_comments_custom_column', array( $this, 'render_comments_column' ), 10, 2 );
        add_filter( 'manage_users_columns', array( $this, 'add_users_column' ) );
        add_action( 'manage_users_custom_column', array( $this, 'render_users_column' ), 10, 3 );

        add_filter( 'post_row_actions', array( $this, 'add_post_type_action' ), 10, 2 );
        add_filter( 'page_row_actions', array( $this, 'add_post_type_action' ), 10, 2 );
        add_filter( 'tag_row_actions', array( $this, 'add_post_type_action' ), 10, 2 );
        add_filter( 'media_row_actions', array( $this, 'add_post_type_action' ), 10, 2 );
        add_filter( 'comment_row_actions', array( $this, 'add_post_type_action' ), 10, 2 );
        add_filter( 'user_row_actions', array( $this, 'add_post_type_action' ), 10, 2 );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Enhance List Tables', 'wpmastertoolkit' );
    }

    /**
     * Add a post type column
     * 
     * @since   1.4.0
     */
    public function add_post_type_column( $post_columns, $post_type = 'page' ) {
        $settings       = $this->get_settings();
        $for_post_types = $settings['for_post_types'] ?? array();

        if ( isset( $for_post_types['title_column'] ) && $for_post_types['title_column'] == '1' && isset( $post_columns['title'] ) ) {
            unset( $post_columns['title'] );
        }
        if ( isset( $for_post_types['author_column'] ) && $for_post_types['author_column'] == '1' && isset( $post_columns['author'] ) ) {
            unset( $post_columns['author'] );
        }
        if ( isset( $for_post_types['categories_column'] ) && $for_post_types['categories_column'] == '1' && isset( $post_columns['categories'] ) ) {
            unset( $post_columns['categories'] );
        }
        if ( isset( $for_post_types['tags_column'] ) && $for_post_types['tags_column'] == '1' && isset( $post_columns['tags'] ) ) {
            unset( $post_columns['tags'] );
        }
        if ( isset( $for_post_types['comments_column'] ) && $for_post_types['comments_column'] == '1' && isset( $post_columns['comments'] ) ) {
            unset( $post_columns['comments'] );
        }
        if ( isset( $for_post_types['date_column'] ) && $for_post_types['date_column'] == '1' && isset( $post_columns['date'] ) ) {
            unset( $post_columns['date'] );
        }
        if ( isset( $for_post_types['id_column'] ) && $for_post_types['id_column'] == '1' ) {
            $post_columns['wpmastertoolkit-id'] = __( 'ID', 'wpmastertoolkit' );
        }   
        $supports_thumbnail = post_type_supports( $post_type, 'thumbnail' );
        if ( isset( $for_post_types['image_column'] ) && $for_post_types['image_column'] == '1' && $supports_thumbnail ) {
            $post_columns['wpmastertoolkit-image'] = __( 'Featured image', 'wpmastertoolkit' );
        }
        $supports_excerpt = post_type_supports( $post_type, 'excerpt' );
        if ( isset( $for_post_types['excerpt_column'] ) && $for_post_types['excerpt_column'] == '1' && $supports_excerpt ) {
            $post_columns['wpmastertoolkit-excerpt'] = __( 'Excerpt', 'wpmastertoolkit' );
        }

        return $post_columns;
    }

    /**
     * Render the post type column
     * 
     * @since   1.4.0
     */
    public function render_post_type_column( $column_name, $post_id ) {
        if ( 'wpmastertoolkit-id' === $column_name ) {
            echo esc_html( $post_id );
        }
        if ( 'wpmastertoolkit-image' === $column_name ) {
            echo get_the_post_thumbnail( $post_id, array( 70, 70 ) );
        }
        if ( 'wpmastertoolkit-excerpt' === $column_name ) {
            $excerpt   = get_the_excerpt( $post_id );
            $max_chars = 100;
            if ( strlen( $excerpt ) > $max_chars ) {
                $excerpt = substr( $excerpt, 0, $max_chars );
                $excerpt = substr( $excerpt, 0, strrpos( $excerpt, ' ' ) );
                $excerpt = $excerpt . '[…]';
            }
            echo wp_kses_post( $excerpt );
        }
    }

    /**
     * Add taxonomies columns
     * 
     * @since   1.4.0
     */
    public function add_taxonomies_columns() {
        $taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
        foreach ( $taxonomies as $taxonomy ) {
            add_filter( 'manage_edit-' . $taxonomy . '_columns', array( $this, 'add_taxonomy_column' ) );
            add_action( 'manage_' . $taxonomy . '_custom_column', array( $this, 'render_taxonomy_column' ), 10, 3 );
        }
    }

    /**
     * Add a post type column
     * 
     * @since   1.4.0
     */
    public function add_taxonomy_column( $columns ) {
        $settings       = $this->get_settings();
        $for_taxonomies = $settings['for_taxonomies'] ?? array();

        if ( isset( $for_taxonomies['name_column'] ) && $for_taxonomies['name_column'] == '1' && isset( $columns['name'] ) ) {
            unset( $columns['name'] );
        }
        if ( isset( $for_taxonomies['description_column'] ) && $for_taxonomies['description_column'] == '1' && isset( $columns['description'] ) ) {
            unset( $columns['description'] );
        }
        if ( isset( $for_taxonomies['slug_column'] ) && $for_taxonomies['slug_column'] == '1' && isset( $columns['slug'] ) ) {
            unset( $columns['slug'] );
        }
        if ( isset( $for_taxonomies['posts_column'] ) && $for_taxonomies['posts_column'] == '1' && isset( $columns['posts'] ) ) {
            unset( $columns['posts'] );
        }
        if ( isset( $for_taxonomies['id_column'] ) && $for_taxonomies['id_column'] == '1' ) {
            $columns['wpmastertoolkit-id'] = __( 'ID', 'wpmastertoolkit' );
        }

        return $columns;
    }

    /**
     * Render the taxonomy column
     * 
     * @since   1.4.0
     */
    public function render_taxonomy_column( $string, $column_name, $term_id ) {
        if ( 'wpmastertoolkit-id' === $column_name ) {
            $string = esc_html__( $term_id );
        }

        return $string;
    }

    /**
     * Add media column
     * 
     * @since   1.4.0
     */
    public function add_media_column( $columns ) {
        $settings  = $this->get_settings();
        $for_media = $settings['for_media'] ?? array();

        if ( isset( $for_media['title_column'] ) && $for_media['title_column'] == '1' && isset( $columns['title'] ) ) {
            unset( $columns['title'] );
        }
        if ( isset( $for_media['author_column'] ) && $for_media['author_column'] == '1' && isset( $columns['author'] ) ) {
            unset( $columns['author'] );
        }
        if ( isset( $for_media['parent_column'] ) && $for_media['parent_column'] == '1' && isset( $columns['parent'] ) ) {
            unset( $columns['parent'] );
        }
        if ( isset( $for_media['comments_column'] ) && $for_media['comments_column'] == '1' && isset( $columns['comments'] ) ) {
            unset( $columns['comments'] );
        }
        if ( isset( $for_media['date_column'] ) && $for_media['date_column'] == '1' && isset( $columns['date'] ) ) {
            unset( $columns['date'] );
        }
        if ( isset( $for_media['id_column'] ) && $for_media['id_column'] == '1' ) {
            $columns['wpmastertoolkit-id'] = __( 'ID', 'wpmastertoolkit' );
        }
        if ( isset( $for_media['file_size_column'] ) && $for_media['file_size_column'] == '1' ) {
            $columns['wpmastertoolkit-file-size'] = __( 'File Size', 'wpmastertoolkit' );
        }

        return $columns;
    }

    /**
     * Render the media column
     * 
     * @since   1.4.0
     */
    public function render_media_column( $column_name, $post_id ) {
        if ( 'wpmastertoolkit-id' === $column_name ) {
            echo esc_html( $post_id );
        }
        if ( 'wpmastertoolkit-file-size' === $column_name ) {
            $file_size = filesize( get_attached_file( $post_id ) );
            $file_size = size_format( $file_size, 1 );
            echo esc_html( $file_size );
        }
    }

    /**
     * Add comments column
     * 
     * @since   1.4.0
     */
    public function add_comments_column( $columns ) {
        $settings     = $this->get_settings();
        $for_comments = $settings['for_comments'] ?? array();

        if ( isset( $for_comments['author_column'] ) && $for_comments['author_column'] == '1' && isset( $columns['author'] ) ) {
            unset( $columns['author'] );
        }
        if ( isset( $for_comments['comment_column'] ) && $for_comments['comment_column'] == '1' && isset( $columns['comment'] ) ) {
            unset( $columns['comment'] );
        }
        if ( isset( $for_comments['response_column'] ) && $for_comments['response_column'] == '1' && isset( $columns['response'] ) ) {
            unset( $columns['response'] );
        }
        if ( isset( $for_comments['date_column'] ) && $for_comments['date_column'] == '1' && isset( $columns['date'] ) ) {
            unset( $columns['date'] );
        }
        if ( isset( $for_comments['id_column'] ) && $for_comments['id_column'] == '1' ) {
            $columns['wpmastertoolkit-id'] = __( 'ID', 'wpmastertoolkit' );
        }

        return $columns;
    }

    /**
     * Render the comments column
     * 
     * @since   1.4.0
     */
    public function render_comments_column( $column_name, $comment_id ) {
        if ( 'wpmastertoolkit-id' === $column_name ) {
            echo esc_html( $comment_id );
        }
    }

    /**
     * Add a user column
     * 
     * @since   1.4.0
     */
    public function add_users_column( $columns ) {
        $settings  = $this->get_settings();
        $for_users = $settings['for_users'] ?? array();

        if ( isset( $for_users['username_column'] ) && $for_users['username_column'] == '1' && isset( $columns['username'] ) ) {
            unset( $columns['username'] );
        }
        if ( isset( $for_users['name_column'] ) && $for_users['name_column'] == '1' && isset( $columns['name'] ) ) {
            unset( $columns['name'] );
        }
        if ( isset( $for_users['email_column'] ) && $for_users['email_column'] == '1' && isset( $columns['email'] ) ) {
            unset( $columns['email'] );
        }
        if ( isset( $for_users['role_column'] ) && $for_users['role_column'] == '1' && isset( $columns['role'] ) ) {
            unset( $columns['role'] );
        }
        if ( isset( $for_users['posts_column'] ) && $for_users['posts_column'] == '1' && isset( $columns['posts'] ) ) {
            unset( $columns['posts'] );
        }
        if ( isset( $for_users['id_column'] ) && $for_users['id_column'] == '1' ) {
            $columns['wpmastertoolkit-id'] = __( 'ID', 'wpmastertoolkit' );
        }

        return $columns;
    }

    /**
     * Render the users column
     * 
     * @since   1.4.0
     */
    public function render_users_column( $string, $column_name, $user_id ) {
        if ( 'wpmastertoolkit-id' === $column_name ) {
            $string = esc_html__( $user_id );
        }

        return $string;
    }

    /**
     * Add a post type action
     * 
     * @since   1.4.0
     */
    public function add_post_type_action( $actions, $object ) {

        $settings = $this->get_settings();

        if ( property_exists( $object, 'ID' ) ) {

            if ( is_a( $object, 'WP_User' ) ) {
                if ( isset( $settings['for_users']['id_action'] ) && $settings['for_users']['id_action'] == '1' ) {
                    $actions['wpmastertoolkit-id'] = 'ID: ' . $object->ID;
                }
            } else if ( 'attachment' == $object->post_type ) {
                if ( isset( $settings['for_media']['id_action'] ) && $settings['for_media']['id_action'] == '1' ) {
                    $actions['wpmastertoolkit-id'] = 'ID: ' . $object->ID;
                }
            } else {
                if ( isset( $settings['for_post_types']['id_action'] ) && $settings['for_post_types']['id_action'] == '1' ) {
                    $actions['wpmastertoolkit-id'] = 'ID: ' . $object->ID;
                }
            }
        }

        if ( isset( $settings['for_taxonomies']['id_action'] ) && $settings['for_taxonomies']['id_action'] == '1' && property_exists( $object, 'term_id' ) ) {
            $actions['wpmastertoolkit-id'] = 'ID: ' . $object->term_id;
        }

        if ( isset( $settings['for_comments']['id_action'] ) && $settings['for_comments']['id_action'] == '1' && property_exists( $object, 'comment_ID' ) ) {
            $actions['wpmastertoolkit-id'] = 'ID: ' . $object->comment_ID;
        }

        return $actions;
    }

    /**
     * Add a submenu
     * 
     * @since   1.4.0
     */
    public function add_submenu(){
        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-enhance-list-tables',
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     * @since   1.4.0
     */
    public function render_submenu() {
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/content-order.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/content-order.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/content-order.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/submenu/footer.php';
    }

    /**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( $_POST['_wpnonce'] ?? '' );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @since   1.4.0
     * @return array
     */
    public function sanitize_settings( $new_settings ){
        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            switch ($settings_key) {
                case 'for_post_types':
                case 'for_taxonomies':
                case 'for_media':
                case 'for_comments':
                case 'for_users':
                    foreach ( $settings_value as $element => $element_status ) {
                        $sanitized_settings[ $settings_key ][ $element ] = sanitize_text_field( $new_settings[ $settings_key ][ $element ] ?? '0' );
                    }
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_settings
     *
     * @since   1.4.0
     * @return void
     */
    private function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }

    /**
     * Save settings
     * 
     * @since   1.4.0
     */
    private function save_settings( $new_settings ) {
		update_option( $this->option_id, $new_settings );
    }

    /**
     * get_default_settings
     *
     * @since   1.4.0
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'for_post_types' => $this->get_elements_settings_for_post_types(),
            'for_taxonomies' => $this->get_elements_settings_for_taxonomies(),
            'for_media'      => $this->get_elements_settings_for_media(),
            'for_comments'   => $this->get_elements_settings_for_comments(),
            'for_users'      => $this->get_elements_settings_for_users(),
        );
    }

    /**
     * Add the submenu content
     * 
     * @since   1.4.0
     * @return void
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $for_post_types = $this->get_elements_for_post_types();
        $for_taxonomies = $this->get_elements_for_taxonomies();
        $for_media      = $this->get_elements_for_media();
        $for_comments   = $this->get_elements_for_comments();
        $for_users      = $this->get_elements_for_users();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Improve the usefulness of listing pages for various post types and taxonomies, media, comments and users by adding / removing columns and elements.", 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Post types', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $for_post_types as $element_id => $element_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[for_post_types][' . $element_id . ']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[for_post_types][' . $element_id . ']' ); ?>" value="1"<?php checked( $this->settings['for_post_types'][ $element_id ] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $element_label ); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Taxonomies', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $for_taxonomies as $element_id => $element_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[for_taxonomies][' . $element_id . ']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[for_taxonomies][' . $element_id . ']' ); ?>" value="1"<?php checked( $this->settings['for_taxonomies'][ $element_id ] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $element_label ); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Media', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $for_media as $element_id => $element_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[for_media][' . $element_id . ']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[for_media][' . $element_id . ']' ); ?>" value="1"<?php checked( $this->settings['for_media'][ $element_id ] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $element_label ); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Comments', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $for_comments as $element_id => $element_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[for_comments][' . $element_id . ']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[for_comments][' . $element_id . ']' ); ?>" value="1"<?php checked( $this->settings['for_comments'][ $element_id ] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $element_label ); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Users', 'wpmastertoolkit' ); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $for_users as $element_id => $element_label ): ?>
                                <div class="wp-mastertoolkit__checkbox">
                                    <label class="wp-mastertoolkit__checkbox__label">
                                        <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[for_users][' . $element_id . ']' ); ?>" value="0">
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[for_users][' . $element_id . ']' ); ?>" value="1"<?php checked( $this->settings['for_users'][ $element_id ] ?? '', '1' ); ?>>
                                        <span class="mark"></span>
                                        <span class="wp-mastertoolkit__checkbox__label__text"><?php echo esc_html( $element_label ); ?></span>
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
     * Get the post types with default value
     * 
     * @return  array
     */
    private function get_elements_for_post_types() {
        $result = array(
            'title_column'      => __( 'Remove title column', 'wpmastertoolkit' ),
            'author_column'     => __( 'Remove author column', 'wpmastertoolkit' ),
            'categories_column' => __( 'Remove categories column', 'wpmastertoolkit' ),
            'tags_column'       => __( 'Remove tags column', 'wpmastertoolkit' ),
            'comments_column'   => __( 'Remove comments column', 'wpmastertoolkit' ),
            'date_column'       => __( 'Remove date column', 'wpmastertoolkit' ),
            'id_column'         => __( 'Add ID column', 'wpmastertoolkit' ),
            'id_action'         => __( 'Add ID in actions.', 'wpmastertoolkit' ),
            'image_column'      => __( 'Add featured image column', 'wpmastertoolkit' ),
            'excerpt_column'    => __( 'Add excerpt column', 'wpmastertoolkit' ),
        );

        return $result;
    }

    /**
     * Get the post types settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_settings_for_post_types() {
        $result = array();

        foreach ( $this->get_elements_for_post_types() as $element_id => $element_label ) {
            $result[ $element_id ] = '0';
        }

        return $result;
    }

    /**
     * Get the taxonomies with default value
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_for_taxonomies() {
        $result = array(
            'name_column'        => __( 'Remove name column', 'wpmastertoolkit' ),
            'description_column' => __( 'Remove description column', 'wpmastertoolkit' ),
            'slug_column'        => __( 'Remove slug column', 'wpmastertoolkit' ),
            'posts_column'       => __( 'Remove count column', 'wpmastertoolkit' ),
            'id_column'          => __( 'Add ID column', 'wpmastertoolkit' ),
            'id_action'          => __( 'Add ID in actions.', 'wpmastertoolkit' ),
        );

        return $result;
    }

    /**
     * Get the taxonomies settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_settings_for_taxonomies() {
        $result = array();

        foreach ( $this->get_elements_for_taxonomies() as $element_id => $element_label ) {
            $result[ $element_id ] = '0';
        }

        return $result;
    }

    /**
     * Get the media with default value
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_for_media() {
        $result = array(
            'title_column'     => __( 'Remove File column', 'wpmastertoolkit' ),
            'author_column'    => __( 'Remove author column', 'wpmastertoolkit' ),
            'parent_column'    => __( 'Remove uploaded to column', 'wpmastertoolkit' ),
            'comments_column'  => __( 'Remove comments column', 'wpmastertoolkit' ),
            'date_column'      => __( 'Remove date column', 'wpmastertoolkit' ),
            'id_column'        => __( 'Add ID column', 'wpmastertoolkit' ),
            'id_action'        => __( 'Add ID in actions.', 'wpmastertoolkit' ),
            'file_size_column' => __( 'Add file size column', 'wpmastertoolkit' ),
        );

        return $result;
    }

    /**
     * Get the media settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_settings_for_media() {
        $result = array();

        foreach ( $this->get_elements_for_media() as $element_id => $element_label ) {
            $result[ $element_id ] = '0';
        }

        return $result;
    }

    /**
     * Get the comments with default value
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_for_comments() {
        $result = array(
            'author_column'   => __( 'Remove author column', 'wpmastertoolkit' ),
            'comment_column'  => __( 'Remove comment column', 'wpmastertoolkit' ),
            'response_column' => __( 'Remove in response to column', 'wpmastertoolkit' ),
            'date_column'     => __( 'Remove submitted on column', 'wpmastertoolkit' ),
            'id_column'       => __( 'Add ID column', 'wpmastertoolkit' ),
            'id_action'       => __( 'Add ID in actions.', 'wpmastertoolkit' ),
        );

        return $result;
    }

    /**
     * Get the comments settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_settings_for_comments() {
        $result = array();

        foreach ( $this->get_elements_for_comments() as $element_id => $element_label ) {
            $result[ $element_id ] = '0';
        }

        return $result;
    }

    /**
     * Get the users with default value
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_for_users() {
        $result = array(
            'username_column' => __( 'Remove username column', 'wpmastertoolkit' ),
            'name_column'     => __( 'Remove name column', 'wpmastertoolkit' ),
            'email_column'    => __( 'Remove email column', 'wpmastertoolkit' ),
            'email_column'    => __( 'Remove email column', 'wpmastertoolkit' ),
            'role_column'     => __( 'Remove role column', 'wpmastertoolkit' ),
            'posts_column'    => __( 'Remove posts column', 'wpmastertoolkit' ),
            'id_column'       => __( 'Add ID column', 'wpmastertoolkit' ),
            'id_action'       => __( 'Add ID in actions.', 'wpmastertoolkit' ),
        );

        return $result;
    }

    /**
     * Get the users settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_elements_settings_for_users() {
        $result = array();

        foreach ( $this->get_elements_for_users() as $element_id => $element_label ) {
            $result[ $element_id ] = '0';
        }

        return $result;
    }
}
