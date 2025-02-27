<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Disable Gutenberg
 * Description: Disable the Gutenberg block editor
 * @since 1.0.0
 */
class WPMastertoolkit_Disable_Gutenberg {

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;
    private $gutenberg_not_applicable_types;

    /**
     * Invoke the hooks
     * 
     * @since    1.0.0
     */
    public function __construct() {

        $this->option_id                        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_disable_gutenberg';
        $this->nonce_action                     = $this->option_id . '_action';
        $this->gutenberg_not_applicable_types   = array(
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation'
        );

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_filter( 'use_block_editor_for_post', array( $this, 'disable_gutenberg_admin' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'disable_gutenberg_front' ), 100 );
        add_filter( 'gutenberg_use_widgets_block_editor', array( $this, 'disable_gutenberg_widgets' ) );
        add_filter( 'use_widgets_block_editor', array( $this, 'disable_gutenberg_widgets' ) );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Disable Gutenberg', 'wpmastertoolkit' );
    }
    
    /**
     * get_settings
     *
     * @return void
     */
    public function get_settings(){
        $this->default_settings = $this->get_default_settings();
        return get_option( $this->option_id, $this->default_settings );
    }
    
    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'post_types'        => $this->get_post_types(),
            'frontend_styles'   => '1',
            'widgets'           => '1',
        );
    }

     /**
     * Save settings
     */
    public function save_settings( $new_settings ) {

		update_option( $this->option_id, $new_settings );
    }

    /**
     * sanitize_settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings     = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'post_types':
                    foreach ( $settings_value as $post_type => $post_status ) {
                        $sanitized_settings[$settings_key][$post_type] = sanitize_text_field($new_settings[$settings_key][$post_type] ?? '0');
                    }
                break;
                case 'frontend_styles':
                case 'widgets':
                    $sanitized_settings[$settings_key] = sanitize_text_field($new_settings[$settings_key] ?? '0');
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * Add a submenu
     * 
     * @since   1.0.0
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-disable-gutenberg', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     * @since   1.0.0
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/disable-gutenberg.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/disable-gutenberg.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/disable-gutenberg.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     * 
     * @since   1.0.0
     */
    public function save_submenu() {
        
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {
            
            $this->default_settings = $this->get_default_settings();
            
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings   = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );

            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * Disable Gutenberg in admin
     * 
     * @since   1.0.0
     */
    public function disable_gutenberg_admin( $use_block_editor, $post  ) {
        $this->settings    = $this->get_settings();

        $current_post_type  = $post->post_type ?? '';
        $post_types         = $this->settings['post_types'] ?? array();
        $status             = $post_types[$current_post_type] ?? '';

        if ( $status == '1' ) {
            $use_block_editor = false;
        }

        return $use_block_editor;
    }

    /**
     * Disable Gutenberg in frontend
     * 
     * @since   1.0.0
     */
    public function disable_gutenberg_front () {
        $this->settings    = $this->get_settings();

        $disable_front = $this->settings['frontend_styles'] ?? '';

        if ( $disable_front === '1' ) {

            $post = get_queried_object();
            
            if ( !is_null( $post ) ) {
                if ( property_exists( $post, 'post_type' ) ) {
    
                    $current_post_type  = $post->post_type;
                    $post_types         = $this->settings['post_types'] ?? array();
                    $status             = $post_types[$current_post_type] ?? '';
    
                    if ( $status == '1' ) {

                        global $wp_styles;

                        foreach ( $wp_styles->queue as $handle ) {
                            if ( false !== strpos( $handle, 'wp-block' ) ) {
                                wp_dequeue_style( $handle );
                            }
                        }

                        wp_dequeue_style( 'core-block-supports' );
                        wp_dequeue_style( 'global-styles' );
                        wp_dequeue_style( 'classic-theme-styles' );
                    }
                }
            }
        }
    }

    /**
     * Disable Gutenberg widgets
     * 
     * @since   1.0.0
     */
    public function disable_gutenberg_widgets() {
        $this->settings    = $this->get_settings();

        $disable_widgets = $this->settings['widgets'] ?? '';

        if ( $disable_widgets === '1' ) {
            return false;
        }

        return true;
    }

    /**
     * Add the submenu content
     * 
     * @since   1.0.0
     */
    private function submenu_content() {
        $this->settings    = $this->get_settings();
        $post_types        = $this->get_post_types( false );

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Deactivate the Gutenberg block editor selectively, allowing you to control its usage for specific or all relevant post types.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Disable Gutenberg block editor via post type', 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
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
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Disable Gutenberg in frontend', 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__checkbox">
                                <label class="wp-mastertoolkit__checkbox__label">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[frontend_styles]' ); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[frontend_styles]' ); ?>" value="1"<?php checked( $this->settings['frontend_styles']??'', '1' ); ?>>
                                    <span class="mark"></span>
                                    <span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e('Disable frontend block styles / CSS files for the selected post types.', 'wpmastertoolkit'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Disable Gutenberg Widgets', 'wpmastertoolkit'); ?></div>
                        <div class="wp-mastertoolkit__section__body__item__content">
                            <div class="wp-mastertoolkit__checkbox">
                                <label class="wp-mastertoolkit__checkbox__label">
                                    <input type="hidden" name="<?php echo esc_attr( $this->option_id . '[widgets]' ); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr( $this->option_id . '[widgets]' ); ?>" value="1"<?php checked( $this->settings['widgets']??'', '1' ); ?>>
                                    <span class="mark"></span>
                                    <span class="wp-mastertoolkit__checkbox__label__text"><?php esc_html_e('Restores the classic widgets settings screen when using a classic (non-block) theme. This has no effect on block themes.', 'wpmastertoolkit'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }

    /**
     * Get the post types with default value
     * 
     * @since   1.0.0
     * @return  array
     */
    private function get_post_types( $get_default_values = true ) {
        $result     = array();
        $post_types = get_post_types( array(), 'object' );

        $post_types = array_filter( $post_types, function( $post_type ) {
            return !in_array( $post_type->name, $this->gutenberg_not_applicable_types );
        });

        if ( $get_default_values ) {
            foreach ( $post_types as $post_name => $post_type ) {
                $result[$post_name] = '1';
            }
        } else {
            $result = $post_types;
        }

        return $result;
    }

}