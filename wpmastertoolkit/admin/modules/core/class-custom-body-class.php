<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Custom Body Class
 * Description: 
 * @since 1.3.0
 */
class WPMastertoolkit_Custom_Body_Class {

    const CUSTOM_BODY_CLASS_ID = '_wp_mastertoolkit_custom_body_class';

    private $option_id;
    private $header_title;
    private $nonce_action;
    private $settings;
    private $default_settings;

    /**
     * Invoke the hooks
     * 
     */
    public function __construct() {

        $this->option_id        = WPMASTERTOOLKIT_PLUGIN_SETTINGS . '_custom_body_class';
        $this->nonce_action     = $this->option_id . '_action';

        add_action( 'init', array( $this, 'class_init' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 999 );
        add_action( 'admin_init', array( $this, 'save_submenu' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
        add_action( 'save_post', array( $this, 'save_meta_box' ), 999, 3 );
        add_filter( 'body_class', array( $this, 'append_body_class' ), 999, 2 );
    }

    /**
     * Initialize the class
     */
    public function class_init() {
        $this->header_title = esc_html__( 'Custom Body Class', 'wpmastertoolkit' );
    }

    /**
     * Add metabox
     */
    public function add_meta_box( $post_type, $post ) {

        $settings   = $this->get_settings();
        $post_types = $settings['post_types'];
        $is_enabled = $post_types[$post_type] ?? '0';

        if ( '1' === $is_enabled ) {

            add_meta_box(
                'wp-mastertoolkit-custom-body-class',
                sprintf( 
                    /* translators: %s: module title */
                    __( 'WPMasterToolkit(%s)', 'wpmastertoolkit' ), 
                    $this->header_title 
                ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Save the metabox
     */
    public function save_meta_box( $post_id, $post, $update ) {

		//phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST[ self::CUSTOM_BODY_CLASS_ID ] ) ) {

            //phpcs:ignore WordPress.Security.NonceVerification.Missing
            $custom_body_class = sanitize_text_field( wp_unslash( $_POST[ self::CUSTOM_BODY_CLASS_ID ] ?? '' ) );
            update_post_meta( $post_id, self::CUSTOM_BODY_CLASS_ID, $custom_body_class ); 
        }
    }

    /**
     * Append the body class
     */
    public function append_body_class( $classes, $css_class ) {
        global $post;

        if ( is_singular() ) {

            $settings   = $this->get_settings();
            $post_types = $settings['post_types'];
            $is_enabled = $post_types[$post->post_type] ?? '0';

            if ( '1' === $is_enabled ) {

                $custom_body_classes = get_post_meta( $post->ID, self::CUSTOM_BODY_CLASS_ID, true );
    
                if ( ! empty( $custom_body_classes ) ) {
    
                    $custom_body_classes = explode( ',', $custom_body_classes );
    
                    foreach( $custom_body_classes as $custom_body_class ) {
                        $classes[] = sanitize_html_class( $custom_body_class );
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * Render the metabox
     */
    public function render_meta_box( $post ) {

        $input_value = get_post_meta( $post->ID, self::CUSTOM_BODY_CLASS_ID, true );

        ?>
            <div>
                <input type="text" class="large-text" id="<?php echo esc_attr( self::CUSTOM_BODY_CLASS_ID ); ?>" name="<?php echo esc_attr( self::CUSTOM_BODY_CLASS_ID ); ?>" value="<?php echo esc_attr( $input_value ); ?>"/>
                <div><?php echo esc_html_e( 'Use comma to separate multiple classes. Example: myclass,myother-class', 'wpmastertoolkit' ); ?></div>
            </div>
        <?php
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
     * Save settings
     */
    public function save_settings( $new_settings ) {

		update_option( $this->option_id, $new_settings );
    }

    /**
     * Add a submenu
     *
     */
    public function add_submenu(){

        add_submenu_page(
            'wp-mastertoolkit-settings',
            $this->header_title,
            $this->header_title,
            'manage_options',
            'wp-mastertoolkit-settings-custom-body-class', 
            array( $this, 'render_submenu'),
            null
        );
    }

    /**
     * Render the submenu
     * 
     */
    public function render_submenu() {

        $submenu_assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/custom-body-class.asset.php' );
        wp_enqueue_style( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/custom-body-class.css', array(), $submenu_assets['version'], 'all' );
        wp_enqueue_script( 'WPMastertoolkit_submenu', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/custom-body-class.js', $submenu_assets['dependencies'], $submenu_assets['version'], true );

        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/header.php';
        $this->submenu_content();
        include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/submenu/footer.php';
    }

    /**
     * Save the submenu option
     *
     */
    public function save_submenu() {

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		
		if ( wp_verify_nonce($nonce, $this->nonce_action) ) {

			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_settings = $this->sanitize_settings( $_POST[$this->option_id] ?? array() );
            
            $this->save_settings( $new_settings );
            wp_safe_redirect( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			exit;
		}
    }

    /**
     * sanitize_settings
     * 
     * @return array
     */
    public function sanitize_settings($new_settings){

        $this->default_settings = $this->get_default_settings();
        $sanitized_settings = array();

        foreach ( $this->default_settings as $settings_key => $settings_value ) {
            
            switch ($settings_key) {
                case 'post_types':
                    foreach ( $settings_value as $post_type => $post_status ) {
                        $sanitized_settings[$settings_key][$post_type] = sanitize_text_field($new_settings[$settings_key][$post_type] ?? '0');
                    }
                break;
            }
        }

        return $sanitized_settings;
    }

    /**
     * get_default_settings
     *
     * @return array
     */
    private function get_default_settings(){
        if( $this->default_settings !== null ) return $this->default_settings;

        return array(
            'post_types'    => $this->get_post_types_settings(),
        );
    }

    /**
     * Add the submenu content
     *
     */
    private function submenu_content() {
        $this->settings = $this->get_settings();
        $post_types     = $this->get_post_types();

        ?>
            <div class="wp-mastertoolkit__section">
                <div class="wp-mastertoolkit__section__desc"><?php esc_html_e('Add custom <body> class(es) on the singular view of some or all public post types.', 'wpmastertoolkit'); ?></div>
                <div class="wp-mastertoolkit__section__body">
                    <div class="wp-mastertoolkit__section__body__item">
                        <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e('Add metabox in', 'wpmastertoolkit'); ?></div>
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
                </div>
            </div>
        <?php
    }

    /**
     * Get the post types with default value
     * 
     */
    private function get_post_types() {

        $result = array();

        foreach ( get_post_types( array('public' => true), 'names' ) as $post_type ) {

            if ( $post_type === 'attachment' ) {
                continue;
            }

            $result[] = get_post_type_object( $post_type );
        }

        return $result;
    }

    /**
     * Get the post types settings
     */
    private function get_post_types_settings() {

        $result = array();

        foreach ( $this->get_post_types() as $post_type ) {
                
            $slug = $post_type->name;
            $result[$slug] = '0';
        }

        return $result;
    }
}
