<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Post Per Page
 * Description: Specifying the number of posts to display per page, for each post type.
 * @since 1.4.0
 */
class WPMastertoolkit_Post_Per_Page {

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

        $this->option_id    = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_post_per_page';
        $this->nonce_action = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'pre_get_posts', array( $this, 'limit_posts_per_page' ) );
    }

    /**
     * Initialize the class
     * 
     * @since    1.4.0
     * @return   void
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Post Per Page', 'wpmastertoolkit' );
    }

    /**
     * Limit posts per page
     * 
     * @since    1.4.0
     * @return   void
     */
    public function limit_posts_per_page( $query ) {

        if ( ! is_admin() && $query->is_main_query() ) {
            
            $settings            = $this->get_settings();
            $settings_post_types = $settings['post_types'];
            $post_type           = $query->get( 'post_type' );
            $post_type           = empty( $post_type ) ? 'post' : $post_type;
            $is_enabled          = $settings_post_types[$post_type]['enabled'] ?? '0';

            if ( '1' === $is_enabled ) {
                $num = $settings_post_types[$post_type]['number'] ?? '0';
                $query->set( 'posts_per_page', $num );
            }
        }
    }

    /**
     * Add a submenu
     * 
     * @since   1.4.0
     */
    public function add_submenu(){

        WPMastertoolkit_Settings::add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-post-per-page', 
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

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/post-per-page.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/post-per-page.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/post-per-page.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

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
            
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( wp_unslash( $_POST[ $this->option_id ] ?? array() ) );

            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
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
                        $sanitized_settings[$settings_key][$post_type]['enabled'] = sanitize_text_field( $new_settings[$settings_key][$post_type]['enabled'] ?? '1' );
                        $sanitized_settings[$settings_key][$post_type]['number']  = sanitize_text_field( $new_settings[$settings_key][$post_type]['number'] ?? '20' );
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
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Specifying the number of posts to display per page, for each post type.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__content flex flex-wrap">
                            <?php foreach ( $post_types as $post_type ): ?>

                                <div class="wp-mastertoolkit__checkbox-number">
                                    <label class="wp-mastertoolkit__checkbox-number__label">

                                        <div class="wp-mastertoolkit__checkbox-number__label__checkbox">
                                            <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .'][enabled]' ); ?>" value="0">
                                            <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .'][enabled]' ); ?>" value="1"<?php checked( $this->settings['post_types'][$post_type->name]['enabled'] ?? '', '1' ); ?>>
                                            <span class="mark"></span>
                                        </div>

                                        <div class="wp-mastertoolkit__checkbox-number__label__number">
                                            <input type="number" name="<?php echo esc_attr( $this->option_id . '[post_types]['.$post_type->name .'][number]' ); ?>" value="<?php echo esc_attr( $this->settings['post_types'][$post_type->name]['number'] ?? '0' ); ?>">
                                        </div>

                                        <span class="wp-mastertoolkit__checkbox-number__label__text"><?php echo esc_html($post_type->label); ?> <span>(<?php echo esc_html($post_type->name); ?>)</span></span>
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
        $exclud = array( 'attachment', 'page' );

        foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
            if ( ! in_array( $post_type, $exclud ) ) {
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

        $result           = array();
        $default_per_page = get_option( 'posts_per_page', '10' );

        foreach ( $this->get_post_types() as $post_type ) {

            $slug = $post_type->name;
            $result[$slug] = array(
                'enabled' => '1',
                'number'  => $default_per_page,
            );
        }

        return $result;
    }
}
