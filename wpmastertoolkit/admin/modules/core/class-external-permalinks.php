<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: External Permalinks
 * Description: Enable pages, posts and/or custom post types to have permalinks that point to external URLs. The rel="noopener noreferrer nofollow" attribute will also be added for enhanced security and SEO benefits.
 * @since 1.4.0
 */
class WPMastertoolkit_External_Permalinks {

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
        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_external_permalinks';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
        add_filter( 'page_link', array( $this, 'use_external_permalink' ), 20, 2 );
        add_filter( 'post_link', array( $this, 'use_external_permalink' ), 20, 2 );
        add_filter( 'post_type_link', array( $this, 'use_external_permalink' ), 20, 2 );
        add_action( 'wp', array( $this, 'redirect_to_external_permalink' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'External Permalinks', 'wpmastertoolkit' );
    }

    /**
     * Add meta boxes
     * 
     * @since   1.4.0
     */
    public function add_meta_boxes( $post_type ) {

        $settings            = $this->get_settings();
        $settings_post_types = $settings['post_types'];

        if ( empty( $settings_post_types ) || ! is_array( $settings_post_types ) ) {
            return;
        }

        if ( ! in_array( $post_type, array_keys( $settings_post_types ) ) ) {
            return;
        }

        if ( ! $settings_post_types[ $post_type ] ) {
            return;
        }

        add_meta_box(
            'wpmastertoolkit-external-permalinks',
            esc_html__( 'External Permalinks', 'wpmastertoolkit' ),
            array( $this, 'render_external_permalinks' ),
            $post_type,
            'side',
            'low'
        );
    }

    /**
     * Render external permalinks meta box
     * 
     * @since   1.4.0
     */
    public function render_external_permalinks( $post ) {
        $value = get_post_meta( $post->ID, 'wpmastertoolkit_external_permalink', true );

        ?>
            <div>
                <input class="large-text" name="wpmastertoolkit_external_permalink" id="wpmastertoolkit_external_permalink" type="url" value="<?php echo esc_attr( $value ); ?>" />
                <p class="howto"><?php esc_html_e( 'The external permalink will open in a new browser tab, Leave this field empty to use the default WordPress permalink.', 'wpmastertoolkit' ); ?></p>
            </div>
        <?php
    }

    /**
     * Save meta boxes
     * 
     * @since   1.4.0
     */
    public function save_meta_boxes( $post_id ) {

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['wpmastertoolkit_external_permalink'] ) ) {

			//phpcs:ignore WordPress.Security.NonceVerification.Missing
            $external_permalink = trim( sanitize_url( wp_unslash( $_POST['wpmastertoolkit_external_permalink'] ) ) );

            if ( ! empty( $external_permalink ) ) {
                update_post_meta( $post_id, 'wpmastertoolkit_external_permalink', $external_permalink );
            } else {
                delete_post_meta( $post_id, 'wpmastertoolkit_external_permalink' );
            }
        }
    }

    /**
     * Use the custom permalink if exist
     * 
     * @since   1.4.0
     */
    public function use_external_permalink( $permalink, $post ) {

        if ( ! is_a( $post, 'WP_Post' ) ) {
            $post = get_post( $post );
        }

        $post_type           = $post->post_type;
        $settings            = $this->get_settings();
        $settings_post_types = $settings['post_types'];

        if ( empty( $settings_post_types ) || ! is_array( $settings_post_types ) ) {
            return $permalink;
        }

        if ( ! in_array( $post_type, array_keys( $settings_post_types ) ) ) {
            return $permalink;
        }

        if ( ! $settings_post_types[ $post_type ] ) {
            return $permalink;
        }

        $external_permalink = get_post_meta( $post->ID, 'wpmastertoolkit_external_permalink', true );
        if ( ! empty( $external_permalink ) && filter_var( $external_permalink, FILTER_VALIDATE_URL ) ) {
            $permalink = $external_permalink;

            if ( ! is_admin() ) { 
                $permalink = $permalink . '#wpmastertoolkit_new_tab';
            }
        }

        return $permalink;
    }

    /**
     * 
     * 
     * @since   1.4.0
     */
    public function redirect_to_external_permalink() {
        global $post;

        if ( ! is_singular() ) {
            return;
        }

        $post_type           = $post->post_type;
        $settings            = $this->get_settings();
        $settings_post_types = $settings['post_types'];

        if ( empty( $settings_post_types ) || ! is_array( $settings_post_types ) ) {
            return;
        }

        if ( ! in_array( $post_type, array_keys( $settings_post_types ) ) ) {
            return;
        }

        if ( ! $settings_post_types[ $post_type ] ) {
            return;
        }

        $external_permalink = get_post_meta( $post->ID, 'wpmastertoolkit_external_permalink', true );
        if ( ! empty( $external_permalink ) && filter_var( $external_permalink, FILTER_VALIDATE_URL ) ) {
            wp_redirect( $external_permalink );
            exit;
        }
    }

    /**
     * Enqueue scripts
     * 
     * @since   1.4.0
     */
    public function enqueue_scripts() {

        $external_permalink_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/external-permalinks-front.asset.php' );
        wp_enqueue_script( 'WPMastertoolkit_external_permalink_front', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/external-permalinks-front.js', $external_permalink_assets['dependencies'], $external_permalink_assets['version'], true );
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
            'wp-mastertoolkit-settings-external-permalinks', 
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
        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/content-order.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/content-order.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/content-order.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     * 
     * @since   1.4.0
     */
    public function save_submenu() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( $_POST[ $this->option_id ] ?? array() );
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash(  $_SERVER['REQUEST_URI'] ?? '' ) ) );
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
            'post_types' => $this->get_post_types_settings(),
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
        $post_types     = $this->get_post_types( false );

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e( "Enable pages, posts and/or custom post types to have permalinks that point to external URLs. The rel=\"noopener noreferrer nofollow\" attribute will also be added for enhanced security and SEO benefits.", 'wpmastertoolkit'); ?></div>
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
     * Get the post types with default value
     * 
     * @return  array
     */
    private function get_post_types() {
        $result = array();

        foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
            if ( 'attachment' != $post_type ) {
                $result[] = get_post_type_object( $post_type );
            }
        }

        return $result;
    }

    /**
     * Get the post types settings
     * 
     * @since   1.4.0
     * @return  array
     */
    private function get_post_types_settings() {
        $result = array();

        foreach ( $this->get_post_types() as $post_type ) {
            $slug = $post_type->name;
            $result[$slug] = '1';
        }

        return $result;
    }
}
